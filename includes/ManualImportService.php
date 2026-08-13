<?php
declare(strict_types=1);
namespace WordPressNewsBot;

final class ManualImportService
{
    public function __construct(private readonly ?object $db=null,private readonly ?SourceImporter $importer=null){}

    /** @return array<string,mixed> */
    public function importSource(int$sourceId):array
    {
        try{return($this->importer??new SourceImporter(null,$this->db))->importDetailed($sourceId);}
        catch(\Throwable$e){
            global$wpdb;$db=$this->db??$wpdb;$testId=$e instanceof SourceTestException?$e->testId:bin2hex(random_bytes(8));$now=Support::now();
            $update=['last_checked_at'=>$now,'last_result'=>__('Failed','wordpress-news-bot'),'last_error'=>__('The source could not be imported. Check the RSS URL and try again.','wordpress-news-bot'),'updated_at'=>$now];
            $db->update(Support::table('sources'),$update,['id'=>$sourceId],DatabaseSchema::formatsFor('sources',$update),['%d']);$this->log('manual_import_failed',$sourceId,$testId,get_class($e));throw$e;
        }
    }

    /** @return array{test_id:string,results:list<array<string,mixed>>,processed:int,remaining:int} */
    public function importAll(int$offset=0,int$batchSize=5):array
    {
        global$wpdb;$db=$this->db??$wpdb;$lock='wpnb_import_all_lock';
        if(!$this->acquireLock($lock))throw new \RuntimeException(__('An all-source import is already running.','wordpress-news-bot'));
        try{
            $batchSize=max(1,min(10,$batchSize));$sources=(array)$db->get_results($db->prepare('SELECT id,name FROM '.Support::table('sources').' WHERE active=1 ORDER BY id ASC LIMIT %d OFFSET %d',$batchSize,$offset),ARRAY_A);
            $total=(int)$db->get_var('SELECT COUNT(*) FROM '.Support::table('sources').' WHERE active=1');$results=[];
            foreach($sources as$source){$id=(int)$source['id'];try{$results[]=$this->importSource($id);}catch(\Throwable$e){$testId=$e instanceof SourceTestException?$e->testId:bin2hex(random_bytes(8));$results[]=['source_id'=>$id,'source_name'=>sanitize_text_field((string)$source['name']),'read'=>0,'new'=>0,'duplicate'=>0,'invalid'=>0,'failed'=>1,'duration_ms'=>0,'test_id'=>$testId,'status'=>'failed'];}}
            return['test_id'=>bin2hex(random_bytes(8)),'results'=>$results,'processed'=>count($sources),'remaining'=>max(0,$total-($offset+count($sources)))];
        }finally{delete_option($lock);}
    }

    private function acquireLock(string$key):bool
    {
        if(add_option($key,time(),'','no'))return true;$created=(int)get_option($key,0);if($created>0&&$created<time()-10*MINUTE_IN_SECONDS){delete_option($key);return add_option($key,time(),'','no');}return false;
    }

    private function log(string$event,int$sourceId,string$testId,string$exceptionClass):void
    {
        global$wpdb;$db=$this->db??$wpdb;$db->insert(Support::table('logs'),['level'=>'error','event'=>$event,'message'=>'A manual source import failed.','context_json'=>Support::json(Security::cleanLogContext(['source_id'=>$sourceId,'test_id'=>$testId,'exception_class'=>$exceptionClass])),'created_at'=>Support::now()],DatabaseSchema::formatsFor('logs',['level'=>'error','event'=>$event,'message'=>'A manual source import failed.','context_json'=>'','created_at'=>'']));
    }
}
