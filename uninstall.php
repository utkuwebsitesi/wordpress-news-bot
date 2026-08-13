<?php
if (!defined('WP_UNINSTALL_PLUGIN')) exit;
$settings = get_option('nyb_settings', []);
if (!empty($settings['delete_data_on_uninstall'])) {
    global $wpdb;
    foreach (['sources','feed_items','jobs','ai_generations','logs','daily_usage'] as $table) $wpdb->query('DROP TABLE IF EXISTS ' . $wpdb->prefix . 'nyb_' . $table);
    delete_option('nyb_settings'); delete_option('nyb_schema_version');
}
