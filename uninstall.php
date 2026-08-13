<?php
if (!defined('WP_UNINSTALL_PLUGIN')) exit;
$settings = get_option('wpnb_settings', []);
if (!empty($settings['delete_data_on_uninstall'])) {
    global $wpdb;
    foreach (['sources','feed_items','jobs','ai_generations','logs','daily_usage'] as $table) $wpdb->query('DROP TABLE IF EXISTS ' . $wpdb->prefix . 'wpnb_' . $table);
    delete_option('wpnb_settings'); delete_option('wpnb_schema_version'); delete_option('wpnb_setup_state'); delete_option('wpnb_connection_status'); delete_option('wpnb_openai_credentials'); delete_option('wpnb_last_automation_run');
}
