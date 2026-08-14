<?php
declare(strict_types=1);
namespace WordPressNewsBot;

final class DraftService
{
    public function create(int $itemId): int
    {
        if(!Security::canReview())throw new \RuntimeException(__('You do not have permission to create drafts.','wordpress-news-bot'));
        $lock='wpnb_draft_lock_'.$itemId;
        if(!$this->acquireLock($lock))throw new \RuntimeException(__('This news item is already being processed.','wordpress-news-bot'));
        global$wpdb;$reserved=false;$generationId=0;$jobId=0;
        try{
            $item=$wpdb->get_row($wpdb->prepare('SELECT f.*, s.name AS source_name, s.category_id, s.post_status FROM '.Support::table('feed_items').' f JOIN '.Support::table('sources').' s ON s.id=f.source_id WHERE f.id=%d LIMIT 1',$itemId),ARRAY_A);
            if(!$item)throw new \RuntimeException(__('The news item was not found.','wordpress-news-bot'));
            $existing=get_posts(['post_type'=>'post','post_status'=>['draft','pending','publish','future'],'numberposts'=>1,'fields'=>'ids','meta_query'=>['relation'=>'OR',['key'=>'_wpnb_feed_item_id','value'=>(string)$itemId],['key'=>'_wpnb_source_url','value'=>esc_url_raw((string)$item['source_url'])],['key'=>'_wpnb_content_hash','value'=>sanitize_text_field((string)$item['content_hash'])]]]);
            if($existing){$wpdb->update(Support::table('feed_items'),['status'=>'duplicate','updated_at'=>Support::now()],['id'=>$itemId],['%s','%s'],['%d']);return(int)$existing[0];}

            $job=['feed_item_id'=>$itemId,'type'=>'create_draft','status'=>'running','attempts'=>1,'locked_at'=>Support::now(),'error_message'=>null,'created_at'=>Support::now(),'updated_at'=>Support::now()];
            if($wpdb->insert(Support::table('jobs'),$job,DatabaseSchema::formatsFor('jobs',$job))!==false)$jobId=(int)$wpdb->insert_id;
            $settings=(array)get_option('wpnb_settings',[]);$quota=max(0,(int)($settings['daily_ai_quota']??25));
            if(!$this->reserveQuota($quota))throw new \RuntimeException(__('The daily AI quota has been reached.','wordpress-news-bot'));
            $reserved=true;
            $provider=(($settings['ai_provider']??'openai')==='openai')?new OpenAiProvider(Credentials::openAiKey(),(string)($settings['ai_model']??'gpt-4o-mini')):new MockAiProvider();
            $output=$provider->generate($item);
            $generation=['feed_item_id'=>$itemId,'provider'=>$provider instanceof OpenAiProvider?'openai':'mock','model'=>$provider->model(),'output_json'=>Support::json($output),'input_tokens'=>0,'output_tokens'=>0,'estimated_cost'=>0,'created_at'=>Support::now()];
            if($wpdb->insert(Support::table('ai_generations'),$generation,DatabaseSchema::formatsFor('ai_generations',$generation))===false)throw new \RuntimeException(__('The AI generation record could not be saved.','wordpress-news-bot'));
            $generationId=(int)$wpdb->insert_id;
            $content=ContentSanitizer::clean((string)$output['content_html']);
            $postId=wp_insert_post(DraftPolicy::postArgs($output,get_current_user_id(),(int)$item['category_id'],$content),true);
            if(is_wp_error($postId))throw new \RuntimeException(__('The WordPress draft could not be created.','wordpress-news-bot'));
            update_post_meta($postId,'_wpnb_source_id',(int)$item['source_id']);update_post_meta($postId,'_wpnb_source_url',esc_url_raw((string)$item['source_url']));update_post_meta($postId,'_wpnb_feed_item_id',$itemId);update_post_meta($postId,'_wpnb_content_hash',sanitize_text_field((string)$item['content_hash']));update_post_meta($postId,'_wpnb_ai_provider',$generation['provider']);update_post_meta($postId,'_wpnb_ai_model',$provider->model());update_post_meta($postId,'_wpnb_generated_at',Support::now());wp_set_post_tags($postId,$output['suggested_tags']);
            $wpdb->update(Support::table('feed_items'),['status'=>'draft_created','updated_at'=>Support::now()],['id'=>$itemId],['%s','%s'],['%d']);
            if($jobId>0)$wpdb->update(Support::table('jobs'),['status'=>'completed','locked_at'=>null,'updated_at'=>Support::now()],['id'=>$jobId],['%s','%s','%s'],['%d']);
            return(int)$postId;
        }catch(\Throwable$e){
            if($reserved)$this->releaseQuota();
            if($generationId>0)$wpdb->delete(Support::table('ai_generations'),['id'=>$generationId],['%d']);
            if($jobId>0)$wpdb->update(Support::table('jobs'),['status'=>'failed','locked_at'=>null,'error_message'=>__('Draft creation failed.','wordpress-news-bot'),'updated_at'=>Support::now()],['id'=>$jobId],['%s','%s','%s','%s'],['%d']);
            $wpdb->update(Support::table('feed_items'),['status'=>'error','updated_at'=>Support::now()],['id'=>$itemId],['%s','%s'],['%d']);throw$e;
        }finally{$this->releaseLock($lock);}
    }

    private function acquireLock(string$key):bool
    {
        if(add_option($key,time(),'','no'))return true;$created=(int)get_option($key,0);if($created>0&&$created<time()-5*MINUTE_IN_SECONDS){delete_option($key);return add_option($key,time(),'','no');}return false;
    }
    private function releaseLock(string$key):void{delete_option($key);}
    private function reserveQuota(int$quota):bool
    {
        if($quota<1)return false;global$wpdb;$table=DatabaseSchema::identifier(Support::table('daily_usage'));$date=gmdate('Y-m-d');$updated=$wpdb->query($wpdb->prepare("UPDATE $table SET ai_requests=ai_requests+1 WHERE usage_date=%s AND ai_requests<%d",$date,$quota));if($updated===1)return true;$inserted=$wpdb->query($wpdb->prepare("INSERT IGNORE INTO $table (usage_date,ai_requests) VALUES (%s,1)",$date));if($inserted===1)return true;return$wpdb->query($wpdb->prepare("UPDATE $table SET ai_requests=ai_requests+1 WHERE usage_date=%s AND ai_requests<%d",$date,$quota))===1;
    }
    private function releaseQuota():void{global$wpdb;$table=DatabaseSchema::identifier(Support::table('daily_usage'));$wpdb->query($wpdb->prepare("UPDATE $table SET ai_requests=GREATEST(0,ai_requests-1) WHERE usage_date=%s",gmdate('Y-m-d')));}
}
