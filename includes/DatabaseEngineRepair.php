<?php
declare(strict_types=1);
namespace WordPressNewsBot;

final class DatabaseEngineRepair
{
    private const STATE_OPTION='wpnb_engine_repair_state';
    private const ORDER=['migration_journal','sources','feed_items','jobs','ai_generations','logs','daily_usage'];
    public function __construct(private readonly object$db){}

    /** @return array{code:string,innodb_available:bool,alter_allowed:bool,tables:array<string,array<string,mixed>>,total_rows:int,total_bytes:int} */
    public function preview():array
    {
        $innodb=$this->innodbAvailable();$alter=$this->alterAllowed();$tables=[];$totalRows=0;$totalBytes=0;
        foreach(self::ORDER as$logical){$table=$this->trustedTable($logical);$status=$this->tableStatus($table);if(!$status)continue;$rows=(int)($status['Rows']??0);$bytes=(int)($status['Data_length']??0)+(int)($status['Index_length']??0);$tables[$logical]=['engine'=>(string)($status['Engine']??''),'rows'=>$rows,'bytes'=>$bytes];$totalRows+=$rows;$totalBytes+=$bytes;}
        $code=!$innodb?'innodb_unavailable':(!$alter?'alter_permission_denied':($this->needsConversion($tables)?'engine_conversion_required':'engine_conversion_verified'));
        return['code'=>$code,'innodb_available'=>$innodb,'alter_allowed'=>$alter,'tables'=>$tables,'total_rows'=>$totalRows,'total_bytes'=>$totalBytes];
    }

    /** @return array{code:string,changed:int,tables:array<string,array<string,mixed>>} */
    public function convert():array
    {
        $preview=$this->preview();if(!$preview['innodb_available'])throw new DatabaseEngineRepairException('innodb_unavailable',['suggestion'=>'Hosting provider must enable InnoDB before conversion.']);if(!$preview['alter_allowed'])throw new DatabaseEngineRepairException('alter_permission_denied',['suggestion'=>'The WordPress database user needs ALTER privilege for plugin tables.']);
        $state=['status'=>'started','code'=>'engine_conversion_required','plugin_version'=>WPNB_VERSION,'schema_version'=>WPNB_SCHEMA_VERSION,'started_at'=>Support::now(),'updated_at'=>Support::now(),'tables'=>$preview['tables'],'total_rows'=>$preview['total_rows'],'total_bytes'=>$preview['total_bytes']];$this->saveState($state);$changed=0;$journalId=0;
        foreach(self::ORDER as$logical){$table=$this->trustedTable($logical);$before=$this->tableStatus($table);if(!$before)continue;$beforeEngine=(string)($before['Engine']??'');$beforeCount=$this->rowCount($table);$beforeChecksum=$this->checksum($table);$state['tables'][$logical]=['from_engine'=>$beforeEngine,'to_engine'=>'InnoDB','rows_before'=>$beforeCount,'checksum_before'=>$beforeChecksum,'status'=>strtolower($beforeEngine)==='innodb'?'already_innodb':'started','started_at'=>Support::now()];$state['updated_at']=Support::now();$this->saveState($state);
            if(strtolower($beforeEngine)!=='innodb'){$result=$this->db->query('ALTER TABLE '.DatabaseSchema::identifier($table).' ENGINE=InnoDB');if($result===false){$verified=$this->tableStatus($table);$rowsAfterFailure=$this->rowCount($table);$classified=DatabaseErrorClassifier::classify((string)($this->db->last_error??''),(int)($this->db->last_errno??0));$code=$classified['code']==='db_permission_denied'?'alter_permission_denied':'engine_conversion_failed';$state['status']='failed';$state['code']=$code;$state['tables'][$logical]+=['status'=>'failed','verified_engine'=>(string)($verified['Engine']??''),'rows_after'=>$rowsAfterFailure,'data_preserved'=>$rowsAfterFailure===$beforeCount,'error_fingerprint'=>$classified['error_fingerprint']];$state['updated_at']=Support::now();$this->saveState($state);throw new DatabaseEngineRepairException($code,['table'=>$logical,'db_code'=>$classified['code'],'db_errno'=>$classified['errno'],'affected_rows'=>0,'error_fingerprint'=>$classified['error_fingerprint'],'suggestion'=>$classified['suggestion']]);}$changed++;}
            $after=$this->tableStatus($table);$afterEngine=(string)($after['Engine']??'');$afterCount=$this->rowCount($table);$afterChecksum=$this->checksum($table);$preserved=$beforeCount===$afterCount&&($beforeChecksum===null||$afterChecksum===null||$beforeChecksum===$afterChecksum);if(strtolower($afterEngine)!=='innodb'||!$preserved){$state['status']='failed';$state['code']='engine_conversion_failed';$state['tables'][$logical]+=['status'=>'verification_failed','verified_engine'=>$afterEngine,'rows_after'=>$afterCount,'checksum_after'=>$afterChecksum];$state['updated_at']=Support::now();$this->saveState($state);throw new DatabaseEngineRepairException('engine_conversion_failed',['table'=>$logical,'suggestion'=>'Table engine or row preservation verification failed.']);}
            $state['tables'][$logical]+=['status'=>'verified','verified_engine'=>$afterEngine,'rows_after'=>$afterCount,'checksum_after'=>$afterChecksum,'completed_at'=>Support::now()];$state['updated_at']=Support::now();$this->saveState($state);
            if($logical==='migration_journal'){$journalId=$this->startJournal($state);if($journalId<1)throw new DatabaseEngineRepairException('engine_conversion_failed',['table'=>'migration_journal','suggestion'=>'The InnoDB migration journal could not be started.']);}elseif($journalId>0)$this->updateJournal($journalId,'started',$state);
        }
        $state['status']='completed';$state['code']='engine_conversion_verified';$state['changed']=$changed;$state['completed_at']=$state['updated_at']=Support::now();$this->saveState($state);if($journalId>0)$this->updateJournal($journalId,'completed',$state);return['code'=>'engine_conversion_verified','changed'=>$changed,'tables'=>$state['tables']];
    }

    public static function state():array{return(array)get_option(self::STATE_OPTION,[]);}
    private function innodbAvailable():bool{$this->clearError();$rows=(array)$this->db->get_results('SHOW ENGINES',ARRAY_A);foreach($rows as$row)if(strtolower((string)($row['Engine']??''))==='innodb')return in_array(strtoupper((string)($row['Support']??'')),['YES','DEFAULT'],true);return false;}
    private function alterAllowed():bool{$this->clearError();$rows=(array)$this->db->get_results('SHOW GRANTS FOR CURRENT_USER',ARRAY_A);if((string)($this->db->last_error??'')!=='')return true;if(!$rows)return true;$text=strtoupper(implode(' ',array_map(static fn(array$row):string=>implode(' ',array_values($row)),$rows)));return str_contains($text,'ALL PRIVILEGES')||preg_match('/(?:GRANT|,)\s+ALTER(?:\s|,|ON)/',$text)===1;}
    private function needsConversion(array$tables):bool{foreach($tables as$table)if(strtolower((string)($table['engine']??''))!=='innodb')return true;return false;}
    private function trustedTable(string$logical):string{if(!in_array($logical,self::ORDER,true)||!isset(DatabaseSchema::tables()[$logical]))throw new DatabaseEngineRepairException('engine_conversion_failed');$table=Support::table($logical);if(!preg_match('/^[A-Za-z0-9_]+$/',$table))throw new DatabaseEngineRepairException('engine_conversion_failed',['table'=>$logical]);return$table;}
    private function tableStatus(string$table):array{$row=$this->db->get_row($this->db->prepare('SHOW TABLE STATUS LIKE %s',$table),ARRAY_A);return is_array($row)?$row:[];}
    private function rowCount(string$table):int{return(int)$this->db->get_var('SELECT COUNT(*) FROM '.DatabaseSchema::identifier($table));}
    private function checksum(string$table):?string{$row=$this->db->get_row('CHECKSUM TABLE '.DatabaseSchema::identifier($table).' EXTENDED',ARRAY_A);$value=$row['Checksum']??null;return$value===null?null:(string)$value;}
    private function saveState(array$state):void{update_option(self::STATE_OPTION,$state,false);}
    private function clearError():void{if(property_exists($this->db,'last_error'))$this->db->last_error='';if(property_exists($this->db,'last_errno'))$this->db->last_errno=0;}
    private function startJournal(array$state):int{$ok=$this->db->insert(Support::table('migration_journal'),['migration'=>'engine-repair-1.6.0','status'=>'started','source_count'=>$this->rowCount(Support::table('sources')),'snapshot_json'=>Support::json(['option_state'=>self::STATE_OPTION,'started_at'=>$state['started_at'],'tables'=>$state['tables']]),'report_json'=>Support::json($state),'created_at'=>Support::now(),'completed_at'=>null],['%s','%s','%d','%s','%s','%s','%s']);return$ok===false?0:(int)$this->db->insert_id;}
    private function updateJournal(int$id,string$status,array$state):void{if($this->db->update(Support::table('migration_journal'),['status'=>$status,'report_json'=>Support::json($state),'completed_at'=>$status==='completed'?Support::now():null],['id'=>$id],['%s','%s','%s'],['%d'])===false)throw new DatabaseEngineRepairException('engine_conversion_failed',['table'=>'migration_journal','suggestion'=>'Engine repair progress could not be journaled.']);}
}
