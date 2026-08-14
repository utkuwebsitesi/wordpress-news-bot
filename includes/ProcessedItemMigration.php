<?php
declare(strict_types=1);
namespace WordPressNewsBot;

final class ProcessedItemMigration
{
    public const VERSION='1.0.0';
    public function run():array
    {
        global$wpdb;$updated=0;$posts=get_posts(['post_type'=>'post','post_status'=>['draft','pending','publish','future','private'],'numberposts'=>-1,'fields'=>'ids','meta_key'=>'_wpnb_feed_item_id']);
        foreach(array_map('intval',$posts)as$postId){$itemId=(int)get_post_meta($postId,'_wpnb_feed_item_id',true);if($itemId<1)continue;$status=get_post_status($postId)==='publish'?'published':'processed';$result=$wpdb->update(Support::table('feed_items'),['wordpress_post_id'=>$postId,'status'=>$status,'updated_at'=>Support::now()],['id'=>$itemId],['%d','%s','%s'],['%d']);if($result>0)$updated++;}
        update_option('wpnb_processed_item_migration',self::VERSION,false);return['scanned'=>count($posts),'updated'=>$updated];
    }
}
