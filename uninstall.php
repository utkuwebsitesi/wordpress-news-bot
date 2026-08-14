<?php
if (!defined('WP_UNINSTALL_PLUGIN')) exit;
$settings = get_option('wpnb_settings', []);
if (!empty($settings['delete_data_on_uninstall'])) {
    global $wpdb;
    foreach (['sources','feed_items','jobs','ai_generations','logs','daily_usage','migration_journal'] as $table) {$name=$wpdb->prefix.'wpnb_'.$table;$wpdb->query('DROP TABLE IF EXISTS `'.str_replace('`','``',$name).'`');}
    foreach(['wpnb_settings','wpnb_schema_version','wpnb_plugin_version','wpnb_processed_item_migration','wpnb_setup_state','wpnb_connection_status','wpnb_openai_credentials','wpnb_last_automation_run','wpnb_source_recovery_required','wpnb_engine_repair_state','wpnb_diagnostics']as$option)delete_option($option);
    $wpdb->query($wpdb->prepare("DELETE FROM `{$wpdb->options}` WHERE option_name LIKE %s OR option_name LIKE %s",$wpdb->esc_like('wpnb_draft_lock_').'%', $wpdb->esc_like('_transient_wpnb_').'%'));
}
