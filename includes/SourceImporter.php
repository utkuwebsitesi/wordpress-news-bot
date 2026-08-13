<?php
declare(strict_types=1);
namespace WordPressNewsBot;

final class SourceImporter
{
    public function __construct(private readonly ?SourceConnectionTester $tester=null,private readonly ?object$db=null){}

    public function import(int $sourceId): int
    {
        $lock='wpnb_import_lock_'.$sourceId;if(!$this->acquireLock($lock))throw new \RuntimeException(__('This source is already being imported.','wordpress-news-bot'));
        try{return$this->importLocked($sourceId);}finally{delete_option($lock);}
    }

    private function importLocked(int $sourceId):int
    {
        global $wpdb;
        $db=$this->db??$wpdb;
        $source=$db->get_row($db->prepare('SELECT * FROM '.Support::table('sources').' WHERE id=%d LIMIT 1',$sourceId),ARRAY_A);
        if(!$source||!(int)$source['active'])throw new \RuntimeException(__('No active source was found.','wordpress-news-bot'));

        $allowed=preg_split('/[\r\n,]+/',(string)($source['allowed_domains']??''))?:[];
        $result=($this->tester??new SourceConnectionTester())->fetch((string)$source['feed_url'],$allowed);
        $quota=max(0,(int)($source['daily_quota']??10));
        $today=(int)$db->get_var($db->prepare('SELECT COUNT(*) FROM '.Support::table('feed_items').' WHERE source_id=%d AND created_at>=%s',$sourceId,gmdate('Y-m-d 00:00:00')));
        $detector=new DuplicateDetector($db);
        $count=0;

        foreach($result['items']as$item){
            if($today+$count>=$quota)break;
            if($detector->isDuplicate($item,$sourceId))continue;
            $now=Support::now();
            $record=['source_id'=>$sourceId,'source_name'=>(string)$source['name'],'source_feed_url'=>(string)$source['feed_url'],'guid'=>sanitize_text_field((string)$item['guid']),'source_url'=>esc_url_raw((string)$item['source_url']),'normalized_url'=>Support::normalizeUrl((string)$item['source_url']),'title'=>sanitize_text_field((string)$item['title']),'excerpt'=>sanitize_textarea_field((string)$item['excerpt']),'content_hash'=>sanitize_text_field((string)$item['content_hash']),'published_at'=>$this->mysqlDate((string)($item['published_at']??'')),'status'=>'new','raw_data'=>null,'created_at'=>$now,'updated_at'=>$now];
            $inserted=$db->insert(Support::table('feed_items'),$record,DatabaseSchema::formatsFor('feed_items',$record));
            if($inserted===false&&!$this->duplicateError($db))throw new \RuntimeException(__('The feed item could not be saved.','wordpress-news-bot'));
            if($inserted!==false)$count++;
        }

        $now=Support::now();
        $update=['last_success'=>$now,'last_checked_at'=>$now,'last_result'=>sprintf(__('Imported %d new items.','wordpress-news-bot'),$count),'last_error'=>null,'updated_at'=>$now];
        if($db->update(Support::table('sources'),$update,['id'=>$sourceId],DatabaseSchema::formatsFor('sources',$update),['%d'])===false)throw new \RuntimeException(__('The source status could not be updated.','wordpress-news-bot'));
        return$count;
    }

    private function mysqlDate(string$value):?string{$timestamp=$value===''?false:strtotime($value);return$timestamp===false?null:gmdate('Y-m-d H:i:s',$timestamp);}
    private function duplicateError(object$db):bool{return(int)($db->last_errno??0)===1062||str_contains(strtolower((string)($db->last_error??'')),'duplicate');}
    private function acquireLock(string$key):bool{if(add_option($key,time(),'','no'))return true;$created=(int)get_option($key,0);if($created>0&&$created<time()-10*MINUTE_IN_SECONDS){delete_option($key);return add_option($key,time(),'','no');}return false;}
}
