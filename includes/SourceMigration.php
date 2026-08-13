<?php
declare(strict_types=1);
namespace WordPressNewsBot;

final class SourceMigration
{
    private const MIGRATION = 'sources-1.4.0';
    private bool $transactionOpen = false;

    public function __construct(private readonly object $db) {}

    /** @return array{groups:int,sources_merged:int,items_merged:int,recovered:int} */
    public function run(string $previousVersion = ''): array
    {
        $recovered = $this->recoverIncompleteJournal() ? 1 : 0;
        $sourcesTable = Support::table('sources');
        $sources = (array) $this->db->get_results("SELECT * FROM $sourcesTable ORDER BY created_at ASC,id ASC", ARRAY_A);
        if (!$sources && $previousVersion === '1.3.0') {
            throw new SourceRecoveryRequired(__('The previous source migration did not complete. Source information could not be restored automatically. Please add the source again.', 'wordpress-news-bot'));
        }
        if (!$sources) {
            $this->ensureUniqueIndex();
            return ['groups'=>0,'sources_merged'=>0,'items_merged'=>0,'recovered'=>$recovered];
        }

        $snapshot = $this->snapshot($sources);
        $journalId = $this->createJournal($snapshot);
        $report = ['groups'=>0,'sources_merged'=>0,'items_merged'=>0,'recovered'=>$recovered];
        try {
            $groups = $this->canonicalizeAndGroup($sources);
            $expectedSourceCount = count($sources);
            $this->begin();
            foreach ($groups as $ids) {
                if (count($ids) < 2) continue;
                $report['groups']++;
                $masterId = (int) array_shift($ids);
                $this->assertSourceExists($masterId);
                foreach ($ids as $duplicateId) {
                    $report['items_merged'] += $this->mergeItems($masterId, (int) $duplicateId);
                    $deleted = $this->db->delete($sourcesTable, ['id'=>(int)$duplicateId], ['%d']);
                    if ($deleted !== 1) throw new \RuntimeException('Unexpected duplicate source delete count.');
                    $expectedSourceCount--;
                    $report['sources_merged']++;
                    $this->assertSourceExists($masterId);
                }
            }
            $actualSourceCount = (int) $this->db->get_var("SELECT COUNT(*) FROM $sourcesTable");
            if ($actualSourceCount !== $expectedSourceCount || $actualSourceCount < 1) {
                throw new \RuntimeException('Unexpected source count after merge.');
            }
            $this->commit();

            $this->ensureUniqueIndex();
            $updated = $this->db->update(Support::table('migration_journal'), ['status'=>'completed','completed_at'=>Support::now(),'report_json'=>Support::json($report)], ['id'=>$journalId], ['%s','%s','%s'], ['%d']);
            if ($updated !== 1) throw new \RuntimeException('Migration journal completion could not be recorded.');
            $this->log('info', 'source_duplicate_migration', 'Source duplicate migration completed.', $report);
            return $report;
        } catch (\Throwable $e) {
            $this->rollback();
            try {
                $this->restoreSnapshot($snapshot);
                $this->db->update(Support::table('migration_journal'), ['status'=>'restored','completed_at'=>Support::now(),'report_json'=>Support::json(['error_class'=>get_class($e)])], ['id'=>$journalId]);
            } catch (\Throwable $restoreError) {
                $this->db->update(Support::table('migration_journal'), ['status'=>'restore_failed','completed_at'=>Support::now(),'report_json'=>Support::json(['error_class'=>get_class($e),'restore_error_class'=>get_class($restoreError)])], ['id'=>$journalId]);
            }
            $this->log('error', 'source_duplicate_migration_failed', 'Source duplicate migration failed and recovery was attempted.', ['error_class'=>get_class($e)]);
            throw $e;
        }
    }

    private function canonicalizeAndGroup(array $sources): array
    {
        $table = Support::table('sources');
        $groups = [];
        foreach ($sources as $source) {
            $id = (int) $source['id'];
            try {
                $canonical = SourceUrl::canonicalize((string) $source['feed_url']);
            } catch (\Throwable $e) {
                throw new \RuntimeException('No valid master source can be selected.', 0, $e);
            }
            $hash = hash('sha256', $canonical);
            $result = $this->db->update($table, ['feed_url'=>$canonical,'canonical_hash'=>$hash], ['id'=>$id]);
            if ($result === false) throw new \RuntimeException('Source canonicalization update failed.');
            $stored = $this->db->get_row($this->db->prepare("SELECT id,canonical_hash FROM $table WHERE id=%d LIMIT 1", $id), ARRAY_A);
            if (!$stored || !hash_equals($hash, (string)$stored['canonical_hash'])) throw new \RuntimeException('Source canonicalization verification failed.');
            $groups[$hash][] = $id;
        }
        return $groups;
    }

    private function mergeItems(int $masterId, int $duplicateId): int
    {
        $itemsTable=Support::table('feed_items');$jobsTable=Support::table('jobs');$generationsTable=Support::table('ai_generations');
        $items=(array)$this->db->get_results($this->db->prepare("SELECT * FROM $itemsTable WHERE source_id=%d ORDER BY id ASC FOR UPDATE",$duplicateId),ARRAY_A);$merged=0;
        foreach($items as$item){$conditions=[];$values=[$masterId];foreach(['guid','normalized_url','content_hash']as$column){if((string)($item[$column]??'')!==''){$conditions[]="$column=%s";$values[]=(string)$item[$column];}}$existingId=0;if($conditions){$sql="SELECT id FROM $itemsTable WHERE source_id=%d AND (".implode(' OR ',$conditions).') LIMIT 1';$existingId=(int)$this->db->get_var($this->db->prepare($sql,...$values));}$itemId=(int)$item['id'];
            if($existingId>0){$this->moveRelations($jobsTable,$itemId,$existingId);$this->moveRelations($generationsTable,$itemId,$existingId);$deleted=$this->db->delete($itemsTable,['id'=>$itemId],['%d']);if($deleted!==1)throw new \RuntimeException('Unexpected duplicate feed item delete count.');}
            else{$updated=$this->db->update($itemsTable,['source_id'=>$masterId],['id'=>$itemId]);if($updated!==1)throw new \RuntimeException('Unexpected feed item move count.');}
            $merged++;
        }
        $remaining=(int)$this->db->get_var($this->db->prepare("SELECT COUNT(*) FROM $itemsTable WHERE source_id=%d",$duplicateId));if($remaining!==0)throw new \RuntimeException('Duplicate source still owns feed items.');
        return $merged;
    }

    private function moveRelations(string $table,int $from,int $to):void
    {
        $expected=(int)$this->db->get_var($this->db->prepare("SELECT COUNT(*) FROM $table WHERE feed_item_id=%d",$from));
        $affected=$this->db->query($this->db->prepare("UPDATE $table SET feed_item_id=%d WHERE feed_item_id=%d",$to,$from));
        if($affected===false||$affected!==$expected)throw new \RuntimeException('Unexpected relation move count.');
    }

    private function snapshot(array $sources): array
    {
        $sourceIds=array_map(static fn(array$row):int=>(int)$row['id'],$sources);$sourceList=implode(',',$sourceIds);$itemsTable=Support::table('feed_items');
        $items=$sourceList===''?[]:(array)$this->db->get_results("SELECT * FROM $itemsTable WHERE source_id IN ($sourceList)",ARRAY_A);$itemIds=array_map(static fn(array$row):int=>(int)$row['id'],$items);$itemList=implode(',',$itemIds);
        $jobs=$itemList===''?[]:(array)$this->db->get_results('SELECT * FROM '.Support::table('jobs')." WHERE feed_item_id IN ($itemList)",ARRAY_A);
        $generations=$itemList===''?[]:(array)$this->db->get_results('SELECT * FROM '.Support::table('ai_generations')." WHERE feed_item_id IN ($itemList)",ARRAY_A);
        return ['sources'=>$sources,'feed_items'=>$items,'jobs'=>$jobs,'ai_generations'=>$generations,'had_unique_index'=>$this->hasUniqueIndex()];
    }

    private function createJournal(array $snapshot): int
    {
        $ok=$this->db->insert(Support::table('migration_journal'),['migration'=>self::MIGRATION,'status'=>'started','source_count'=>count($snapshot['sources']),'snapshot_json'=>Support::json($snapshot),'report_json'=>null,'created_at'=>Support::now(),'completed_at'=>null]);
        if($ok===false||(int)$this->db->insert_id<1)throw new \RuntimeException('Migration journal could not be created.');return(int)$this->db->insert_id;
    }

    private function recoverIncompleteJournal(): bool
    {
        $table=Support::table('migration_journal');$row=$this->db->get_row($this->db->prepare("SELECT * FROM $table WHERE migration=%s AND status IN ('started','restore_failed') ORDER BY id DESC LIMIT 1",self::MIGRATION),ARRAY_A);
        if(!$row)return false;$snapshot=json_decode((string)$row['snapshot_json'],true);if(!is_array($snapshot))throw new SourceRecoveryRequired(__('The previous source migration did not complete. Source information could not be restored automatically. Please add the source again.','wordpress-news-bot'));
        $this->restoreSnapshot($snapshot);$updated=$this->db->update($table,['status'=>'restored','completed_at'=>Support::now()],['id'=>(int)$row['id']]);if($updated!==1)throw new \RuntimeException('Recovered journal could not be finalized.');return true;
    }

    private function restoreSnapshot(array $snapshot): void
    {
        if(empty($snapshot['had_unique_index'])&&$this->hasUniqueIndex())$this->dropUniqueIndex();
        $this->begin();
        try{
            foreach(['jobs','ai_generations','feed_items','sources']as$name){$rows=(array)($snapshot[$name]??[]);if(!$rows)continue;$table=Support::table($name);$ids=array_map(static fn(array$row):int=>(int)$row['id'],$rows);$sql="DELETE FROM $table WHERE id IN (".implode(',',$ids).')';if($this->db->query($sql)===false)throw new \RuntimeException('Snapshot cleanup failed.');}
            foreach(['sources','feed_items','jobs','ai_generations']as$name){foreach((array)($snapshot[$name]??[])as$row){$result=$this->db->replace(Support::table($name),$row);if($result===false)throw new \RuntimeException('Snapshot row restore failed.');}}
            foreach((array)($snapshot['sources']??[])as$source)$this->assertSourceExists((int)$source['id']);$this->commit();
        }catch(\Throwable$e){$this->rollback();throw$e;}
    }

    private function ensureUniqueIndex(): void {if($this->hasUniqueIndex())return;$result=$this->db->query('ALTER TABLE '.Support::table('sources').' ADD UNIQUE KEY canonical_hash_unique (canonical_hash)');if($result===false||!$this->hasUniqueIndex())throw new \RuntimeException('Canonical source unique index could not be created.');}
    private function dropUniqueIndex(): void {$result=$this->db->query('ALTER TABLE '.Support::table('sources').' DROP INDEX canonical_hash_unique');if($result===false)throw new \RuntimeException('Canonical source unique index could not be removed for recovery.');}
    private function hasUniqueIndex(): bool {return(bool)$this->db->get_var("SHOW INDEX FROM ".Support::table('sources')." WHERE Key_name='canonical_hash_unique'");}
    private function assertSourceExists(int$id):void{$count=(int)$this->db->get_var($this->db->prepare('SELECT COUNT(*) FROM '.Support::table('sources').' WHERE id=%d',$id));if($count!==1)throw new \RuntimeException('Master source primary key verification failed.');}
    private function begin():void{if($this->transactionOpen)return;if($this->db->query('START TRANSACTION')===false)throw new \RuntimeException('Transaction could not start.');$this->transactionOpen=true;}
    private function commit():void{if(!$this->transactionOpen)return;if($this->db->query('COMMIT')===false)throw new \RuntimeException('Transaction could not commit.');$this->transactionOpen=false;}
    private function rollback():void{if(!$this->transactionOpen)return;$this->db->query('ROLLBACK');$this->transactionOpen=false;}
    private function log(string$level,string$event,string$message,array$context):void{$this->db->insert(Support::table('logs'),['level'=>$level,'event'=>$event,'message'=>$message,'context_json'=>Support::json(Security::cleanLogContext($context)),'created_at'=>Support::now()]);}
}
