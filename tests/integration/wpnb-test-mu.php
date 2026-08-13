<?php
add_filter('wpnb_openai_endpoint',static fn():string=>get_option('wpnb_test_invalid_ai',0)?'https://openai.test/v1/invalid-responses':'https://openai.test/v1/responses');

add_action('rest_api_init',static function():void{
    $permission=static fn(WP_REST_Request$request):bool=>hash_equals('1',(string)$request->get_header('x-wpnb-test'));
    register_rest_route('wpnb-test/v1','/state',['methods'=>'GET','permission_callback'=>$permission,'callback'=>static function():array{
        global$wpdb;$prefix=$wpdb->prefix.'wpnb_';$engines=[];foreach(['sources','feed_items','jobs','ai_generations','logs','daily_usage','migration_journal']as$table){$status=$wpdb->get_row($wpdb->prepare('SHOW TABLE STATUS LIKE %s',$prefix.$table),ARRAY_A);$engines[$table]=strtolower((string)($status['Engine']??''));}
        $drafts=get_posts(['post_type'=>'post','post_status'=>'draft','numberposts'=>-1,'fields'=>'ids']);
        return['engines'=>$engines,'sources'=>(int)$wpdb->get_var("SELECT COUNT(*) FROM {$prefix}sources"),'feed_items'=>(int)$wpdb->get_var("SELECT COUNT(*) FROM {$prefix}feed_items"),'items_by_source'=>$wpdb->get_results("SELECT source_name,COUNT(*) total FROM {$prefix}feed_items GROUP BY source_name",ARRAY_A),'drafts'=>count($drafts),'draft_statuses'=>array_values(array_map(static fn(int$id):string=>(string)get_post_status($id),$drafts)),'draft_feed_ids'=>array_values(array_map(static fn(int$id):int=>(int)get_post_meta($id,'_wpnb_feed_item_id',true),$drafts)),'category_links'=>(int)$wpdb->get_var("SELECT COUNT(*) FROM {$prefix}feed_items WHERE wordpress_category_id>0"),'cron_disabled'=>defined('DISABLE_WP_CRON')&&DISABLE_WP_CRON];
    }]);
    register_rest_route('wpnb-test/v1','/invalid-ai',['methods'=>'POST','permission_callback'=>$permission,'callback'=>static function(WP_REST_Request$request):array{$enabled=(bool)$request->get_param('enabled');update_option('wpnb_test_invalid_ai',$enabled?1:0,false);return['enabled'=>$enabled];}]);
});
