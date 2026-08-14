<?php
declare(strict_types=1);
namespace WordPressNewsBot;

final class LegacyDraftPublisher
{
    /** @return list<array{id:int,title:string,category:string,image:string,eligible:bool,reason:string}> */
    public function preview():array
    {
        $rows=[];foreach(array_map('intval',get_posts(['post_type'=>'post','post_status'=>'draft','numberposts'=>-1,'fields'=>'ids','meta_key'=>'_wpnb_feed_item_id']))as$postId){$item=$this->item($postId);if(!$item)continue;$check=(new PublicationGuard())->validate($postId,$item,!empty($item['import_images']));$categories=wp_get_post_categories($postId,['fields'=>'names']);$rows[]=['id'=>$postId,'title'=>(string)get_the_title($postId),'category'=>implode(', ',array_map('strval',(array)$categories)),'image'=>get_post_thumbnail_id($postId)?__('Ready','wordpress-news-bot'):__('Missing','wordpress-news-bot'),'eligible'=>$check['valid'],'reason'=>$check['reason']];}return$rows;
    }
    /** @return array{published:int,skipped:int,failed:int,duplicate:int,image_missing:int,ai_invalid:int} */
    public function publish(array$postIds):array
    {
        if(!current_user_can('publish_posts'))throw new \RuntimeException(__('You do not have permission to publish posts.','wordpress-news-bot'));
        $result=['published'=>0,'skipped'=>0,'failed'=>0,'duplicate'=>0,'image_missing'=>0,'ai_invalid'=>0];foreach(array_slice(array_values(array_unique(array_filter(array_map('absint',$postIds)))),0,100)as$postId){$item=$this->item($postId);if(!$item||get_post_status($postId)!=='draft'){$result['skipped']++;continue;}$duplicates=array_filter(array_map('intval',get_posts(['post_type'=>'post','post_status'=>'publish','numberposts'=>5,'fields'=>'ids','meta_query'=>['relation'=>'OR',['key'=>'_wpnb_source_url','value'=>(string)$item['source_url']],['key'=>'_wpnb_content_hash','value'=>(string)$item['content_hash']]]])),static fn(int$id):bool=>$id!==$postId);if($duplicates){$result['duplicate']++;continue;}$check=(new PublicationGuard())->validate($postId,$item,!empty($item['import_images']));if(!$check['valid']){if($check['reason']==='image_missing')$result['image_missing']++;elseif($check['reason']==='ai_invalid')$result['ai_invalid']++;else$result['failed']++;continue;}$published=wp_update_post(['ID'=>$postId,'post_status'=>'publish'],true);if(is_wp_error($published)||get_post_status($postId)!=='publish'){$result['failed']++;continue;}global$wpdb;if($wpdb->update(Support::table('feed_items'),['wordpress_post_id'=>$postId,'status'=>'published','updated_at'=>Support::now()],['id'=>(int)$item['id']],['%d','%s','%s'],['%d'])===false){wp_update_post(['ID'=>$postId,'post_status'=>'draft']);$result['failed']++;continue;}$result['published']++;}return$result;
    }
    private function item(int$postId):?array
    {
        $itemId=(int)get_post_meta($postId,'_wpnb_feed_item_id',true);if($itemId<1||(int)get_post_meta($postId,'_wpnb_source_id',true)<1||(string)get_post_meta($postId,'_wpnb_content_hash',true)==='')return null;global$wpdb;return$wpdb->get_row($wpdb->prepare('SELECT f.*,s.category_id,s.import_images FROM '.Support::table('feed_items').' f JOIN '.Support::table('sources').' s ON s.id=f.source_id WHERE f.id=%d LIMIT 1',$itemId),ARRAY_A)?:null;
    }
}
