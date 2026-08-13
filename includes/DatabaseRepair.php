<?php
declare(strict_types=1);
namespace WordPressNewsBot;

final class DatabaseRepair
{
    public function __construct(private readonly object $db) {}

    /** @return array{status:string,changed:int,before:string,after:string,issues:list<array<string,mixed>>} */
    public function run(bool $automatic=false): array
    {
        $health=new DatabaseHealth($this->db);$before=$health->inspect();$changed=0;
        $this->ensureJournalTable();$journal=$this->startJournal($before,$automatic);
        try{
            foreach($before['issues']as$issue){$code=$issue['code'];$logical=(string)($issue['table']??'');if($code==='table_missing'&&isset(DatabaseSchema::tables()[$logical])){$this->mustQuery(DatabaseSchema::createSql($logical,$this->db->get_charset_collate()));$changed++;continue;}
                if($code==='column_missing'&&$this->addMissingColumn($logical,(string)$issue['column'])){$changed++;continue;}
                if($code==='index_missing'&&$this->addMissingIndex($logical,(string)$issue['index'],$automatic)){$changed++;continue;}
            }
            if(!$automatic&&$this->tableExists(Support::table('sources'))){(new SourceMigration($this->db))->run((string)get_option('wpnb_schema_version',''));}
            $after=$health->inspect();if($after['status']==='healthy'||($after['issues']!==[]&&count($after['issues'])===1&&$after['issues'][0]['code']==='schema_version_mismatch')){update_option('wpnb_schema_version',WPNB_SCHEMA_VERSION,false);delete_option('wpnb_source_recovery_required');$after=$health->inspect();}
            $status=$after['status']==='healthy'?'healthy':'repair_required';$this->finishJournal($journal,$status,['changed'=>$changed,'after_fingerprint'=>$after['fingerprint'],'issues'=>$after['issues']]);return ['status'=>$status,'changed'=>$changed,'before'=>$before['fingerprint'],'after'=>$after['fingerprint'],'issues'=>$after['issues']];
        }catch(\Throwable $e){$after=$health->inspect();$this->finishJournal($journal,'repair_failed',['changed'=>$changed,'after_fingerprint'=>$after['fingerprint'],'error_class'=>get_class($e)]);throw $e;}
    }

    private function addMissingColumn(string $logical,string $column):bool
    {
        $spec=DatabaseSchema::tables()[$logical]['columns'][$column]??null;if($spec===null)return false;$table=Support::table($logical);$rows=(int)$this->db->get_var('SELECT COUNT(*) FROM '.DatabaseSchema::identifier($table));$safe=$rows===0||str_contains($spec,' NULL')||str_contains($spec,' DEFAULT ')||str_contains($spec,'AUTO_INCREMENT');if(!$safe)return false;$this->mustQuery('ALTER TABLE '.DatabaseSchema::identifier($table).' ADD COLUMN '.DatabaseSchema::identifier($column).' '.$spec);return true;
    }
    private function addMissingIndex(string $logical,string $name,bool $automatic):bool
    {
        if($name==='PRIMARY')return false;$index=DatabaseSchema::tables()[$logical]['indexes'][$name]??null;if(!$index)return false;$table=Support::table($logical);if($index['unique']){$cols=implode(',',array_map([DatabaseSchema::class,'identifier'],$index['columns']));$duplicates=(int)$this->db->get_var('SELECT COUNT(*) FROM (SELECT 1 FROM '.DatabaseSchema::identifier($table).' GROUP BY '.$cols.' HAVING COUNT(*)>1 LIMIT 1) wpnb_duplicates');if($duplicates>0)return false;if($automatic&&$logical==='sources'&&$name==='canonical_hash_unique'&&(int)$this->db->get_var('SELECT COUNT(*) FROM '.DatabaseSchema::identifier($table)." WHERE canonical_hash='' ")>0)return false;}
        $cols=implode(',',array_map([DatabaseSchema::class,'identifier'],$index['columns']));$this->mustQuery('ALTER TABLE '.DatabaseSchema::identifier($table).' ADD '.($index['unique']?'UNIQUE ':'').'INDEX '.DatabaseSchema::identifier($name).' ('.$cols.')');return true;
    }
    private function ensureJournalTable():void { if(!$this->tableExists(Support::table('migration_journal')))$this->mustQuery(DatabaseSchema::createSql('migration_journal',$this->db->get_charset_collate())); }
    private function startJournal(array $before,bool $automatic):int{$snapshot=['schema_fingerprint'=>$before['fingerprint'],'issues'=>$before['issues'],'required_indexes'=>array_map(static fn(array$s):array=>$s['indexes'],DatabaseSchema::tables()),'automatic'=>$automatic];$ok=$this->db->insert(Support::table('migration_journal'),['migration'=>'database-repair-1.5.0','status'=>'started','source_count'=>$this->tableExists(Support::table('sources'))?(int)$this->db->get_var('SELECT COUNT(*) FROM '.DatabaseSchema::identifier(Support::table('sources'))):0,'snapshot_json'=>Support::json($snapshot),'report_json'=>null,'created_at'=>Support::now(),'completed_at'=>null],['%s','%s','%d','%s','%s','%s','%s']);if($ok===false)throw new \RuntimeException('Repair journal could not be created.');return(int)$this->db->insert_id;}
    private function finishJournal(int$id,string$status,array$report):void{$this->db->update(Support::table('migration_journal'),['status'=>$status,'report_json'=>Support::json($report),'completed_at'=>Support::now()],['id'=>$id],['%s','%s','%s'],['%d']);}
    private function tableExists(string$table):bool{return(string)$this->db->get_var($this->db->prepare('SHOW TABLES LIKE %s',$table))===$table;}
    private function mustQuery(string$sql):void{if($this->db->query($sql)===false)throw new \RuntimeException('Safe schema operation failed.');}
}
