<?php
declare(strict_types=1);
namespace WordPressNewsBot;

final class SourceImporter
{
    public function __construct(private readonly ?SourceConnectionTester $tester=null,private readonly ?object$db=null){}

    public function import(int $sourceId): int
    {
        return(int)$this->importDetailed($sourceId)['new'];
    }

    /** @return array{source_id:int,source_name:string,read:int,new:int,duplicate:int,invalid:int,failed:int,duration_ms:int,test_id:string,status:string} */
    public function importDetailed(int$sourceId,int$limit=50):array
    {
        $lock='wpnb_import_lock_'.$sourceId;if(!$this->acquireLock($lock))throw new \RuntimeException(__('This source is already being imported.','wordpress-news-bot'));
        try{return$this->importLocked($sourceId,max(1,min(100,$limit)));}finally{delete_option($lock);}
    }

    private function importLocked(int $sourceId,int$limit):array
    {
        global $wpdb;
        $db=$this->db??$wpdb;
        $source=$db->get_row($db->prepare('SELECT * FROM '.Support::table('sources').' WHERE id=%d LIMIT 1',$sourceId),ARRAY_A);
        if(!$source||!(int)$source['active'])throw new \RuntimeException(__('No active source was found.','wordpress-news-bot'));

        $started=microtime(true);
        $allowed=preg_split('/[\r\n,]+/',(string)($source['allowed_domains']??''))?:[];
        $result=($this->tester??new SourceConnectionTester())->fetch((string)$source['feed_url'],$allowed);
        $detector=new DuplicateDetector($db);
        $summary=['source_id'=>$sourceId,'source_name'=>(string)$source['name'],'read'=>count($result['items']),'new'=>0,'duplicate'=>0,'invalid'=>0,'failed'=>0,'duration_ms'=>0,'test_id'=>(string)$result['test_id'],'status'=>'success'];

        foreach(array_slice($result['items'],0,$limit)as$item){
            $guid=sanitize_text_field((string)($item['guid']??''));$url=esc_url_raw((string)($item['source_url']??''));$title=sanitize_text_field((string)($item['title']??''));$hash=sanitize_text_field((string)($item['content_hash']??''));
            if($guid===''||$url===''||$title===''||$hash===''){$summary['invalid']++;continue;}
            if($detector->isDuplicate($item,$sourceId)){$summary['duplicate']++;continue;}
            $now=Support::now();
            $imageUrl=esc_url_raw((string)($item['image_url']??''));$imageSource=(string)($item['image_source']??'');if(!in_array($imageSource,['media:content','media:thumbnail','enclosure','atom:enclosure','html:img','og:image'],true))$imageSource='';$record=['source_id'=>$sourceId,'source_name'=>(string)$source['name'],'source_feed_url'=>(string)$source['feed_url'],'guid'=>$guid,'source_url'=>$url,'normalized_url'=>Support::normalizeUrl($url),'title'=>$title,'excerpt'=>sanitize_textarea_field((string)($item['excerpt']??'')),'source_category'=>sanitize_text_field((string)($item['source_category']??'')),'wordpress_category_id'=>(int)($source['category_id']??0),'content_hash'=>$hash,'image_url'=>$imageUrl,'image_source'=>$imageSource,'image_status'=>'missing','image_attachment_id'=>0,'image_hash'=>'','image_error_code'=>'','published_at'=>$this->mysqlDate((string)($item['published_at']??'')),'status'=>'new','raw_data'=>null,'created_at'=>$now,'updated_at'=>$now];
            $inserted=$db->insert(Support::table('feed_items'),$record,DatabaseSchema::formatsFor('feed_items',$record));
            if($inserted===false){if($this->duplicateError($db))$summary['duplicate']++;else$summary['failed']++;continue;}
            $summary['new']++;$itemId=(int)($db->insert_id??0);if($itemId>0&&(!empty($source['import_images'])||!array_key_exists('import_images',$source))&&($imageUrl!==''||!empty($source['use_og_image'])))(new ImageImportService(null,$db))->import($itemId);
        }

        if($summary['read']>$limit)$summary['invalid']+=($summary['read']-$limit);
        $summary['duration_ms']=max(0,(int)round((microtime(true)-$started)*1000));if($summary['failed']>0)$summary['status']='partial';
        $now=Support::now();
        $update=['last_success'=>$now,'last_checked_at'=>$now,'last_result'=>sprintf(__('%1$d read, %2$d new, %3$d duplicate.','wordpress-news-bot'),$summary['read'],$summary['new'],$summary['duplicate']),'last_error'=>$summary['failed']>0?__('Some feed items could not be saved.','wordpress-news-bot'):null,'updated_at'=>$now];
        if($db->update(Support::table('sources'),$update,['id'=>$sourceId],DatabaseSchema::formatsFor('sources',$update),['%d'])===false)throw new \RuntimeException(__('The source status could not be updated.','wordpress-news-bot'));
        $log=['level'=>$summary['failed']>0?'warning':'info','event'=>'source_import_completed','message'=>'A source import completed.','context_json'=>Support::json(Security::cleanLogContext($summary)),'created_at'=>$now];$db->insert(Support::table('logs'),$log,DatabaseSchema::formatsFor('logs',$log));
        return$summary;
    }

    private function mysqlDate(string$value):?string{$timestamp=$value===''?false:strtotime($value);return$timestamp===false?null:gmdate('Y-m-d H:i:s',$timestamp);}
    private function duplicateError(object$db):bool{return(int)($db->last_errno??0)===1062||str_contains(strtolower((string)($db->last_error??'')),'duplicate');}
    private function acquireLock(string$key):bool{if(add_option($key,time(),'','no'))return true;$created=(int)get_option($key,0);if($created>0&&$created<time()-10*MINUTE_IN_SECONDS){delete_option($key);return add_option($key,time(),'','no');}return false;}
}
