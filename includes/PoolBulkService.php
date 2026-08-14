<?php
declare(strict_types=1);
namespace WordPressNewsBot;

final class PoolBulkService
{
    /** @return array{successful:int,skipped:int,failed:int} */
    public function process(array$ids,string$operation,int$userId):array
    {
        global$wpdb;$ids=array_slice(array_values(array_unique(array_filter(array_map('absint',$ids)))),0,500);$lock='wpnb_pool_bulk_lock_'.$userId;
        if(!add_option($lock,time(),'','no'))throw new \RuntimeException(__('Another pool bulk operation is already running.','wordpress-news-bot'));
        try{$limit=$operation==='draft'?max(1,min(50,(int)(((array)get_option('wpnb_settings',[]))['max_run_items']??5))):($operation==='images'?25:100);$selected=array_slice($ids,0,$limit);$result=['successful'=>0,'skipped'=>max(0,count($ids)-count($selected)),'failed'=>0];foreach($selected as$id){try{if($operation==='images'){$image=(new ImageImportService())->import($id);if($image['status']==='ready')$result['successful']++;elseif($image['status']==='missing')$result['skipped']++;else$result['failed']++;continue;}$status=(string)$wpdb->get_var($wpdb->prepare('SELECT status FROM '.Support::table('feed_items').' WHERE id=%d LIMIT 1',$id));if(!in_array($status,['new','review','error'],true)){$result['skipped']++;continue;}if($operation==='draft'){(new DraftService())->create($id);$result['successful']++;continue;}if($operation==='queue'){$exists=(int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM ".Support::table('jobs')." WHERE feed_item_id=%d AND status IN ('queued','running')",$id));if($exists>0){$result['skipped']++;continue;}$job=['feed_item_id'=>$id,'type'=>'create_draft','status'=>'queued','attempts'=>0,'locked_at'=>null,'error_message'=>null,'created_at'=>Support::now(),'updated_at'=>Support::now()];if($wpdb->insert(Support::table('jobs'),$job,DatabaseSchema::formatsFor('jobs',$job))===false)throw new \RuntimeException();$wpdb->update(Support::table('feed_items'),['status'=>'review','updated_at'=>Support::now()],['id'=>$id],['%s','%s'],['%d']);$result['successful']++;continue;}if($operation==='delete'){$wpdb->query('START TRANSACTION');$wpdb->delete(Support::table('jobs'),['feed_item_id'=>$id],['%d']);$wpdb->delete(Support::table('ai_generations'),['feed_item_id'=>$id],['%d']);if($wpdb->delete(Support::table('feed_items'),['id'=>$id],['%d'])!==1)throw new \RuntimeException();$wpdb->query('COMMIT');$result['successful']++;continue;}$result['failed']++;}catch(\Throwable){if($operation==='delete')$wpdb->query('ROLLBACK');$result['failed']++;}}return$result;}finally{delete_option($lock);}
    }
}
