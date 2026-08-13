<?php
declare(strict_types=1);
namespace WordPressNewsBot;

final class Database
{
    public static function activate(): void
    {
        global $wpdb;
        $previousVersion = (string) get_option('wpnb_schema_version', '');
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset = $wpdb->get_charset_collate();
        $tables = [
            'sources' => "id bigint(20) unsigned NOT NULL AUTO_INCREMENT, name varchar(190) NOT NULL, feed_url text NOT NULL, canonical_hash char(64) NOT NULL DEFAULT '', active tinyint(1) NOT NULL DEFAULT 1, category_id bigint(20) unsigned NOT NULL DEFAULT 0, daily_quota int unsigned NOT NULL DEFAULT 10, interval_minutes int unsigned NOT NULL DEFAULT 60, reliability varchar(20) NOT NULL DEFAULT 'medium', author_id bigint(20) unsigned NOT NULL DEFAULT 0, post_status varchar(20) NOT NULL DEFAULT 'draft', show_attribution tinyint(1) NOT NULL DEFAULT 1, allowed_domains text NULL, last_success datetime NULL, last_checked_at datetime NULL, last_result varchar(255) NULL, last_error text NULL, created_at datetime NOT NULL, updated_at datetime NOT NULL, PRIMARY KEY (id), KEY active (active), KEY canonical_hash (canonical_hash)",
            'feed_items' => "id bigint(20) unsigned NOT NULL AUTO_INCREMENT, source_id bigint(20) unsigned NOT NULL, source_name varchar(190) NOT NULL DEFAULT '', source_feed_url text NULL, guid varchar(255) NOT NULL DEFAULT '', source_url text NOT NULL, normalized_url varchar(512) NOT NULL DEFAULT '', title text NOT NULL, excerpt text NULL, content_hash char(64) NOT NULL DEFAULT '', published_at datetime NULL, status varchar(30) NOT NULL DEFAULT 'new', raw_data longtext NULL, created_at datetime NOT NULL, updated_at datetime NOT NULL, PRIMARY KEY (id), UNIQUE KEY source_guid (source_id,guid(191)), KEY source_id (source_id), KEY normalized_url (normalized_url(191)), KEY content_hash (content_hash)",
            'jobs' => "id bigint(20) unsigned NOT NULL AUTO_INCREMENT, feed_item_id bigint(20) unsigned NOT NULL, type varchar(30) NOT NULL, status varchar(20) NOT NULL DEFAULT 'queued', attempts tinyint unsigned NOT NULL DEFAULT 0, locked_at datetime NULL, error_message text NULL, created_at datetime NOT NULL, updated_at datetime NOT NULL, PRIMARY KEY (id), KEY feed_item_id (feed_item_id), KEY status (status)",
            'ai_generations' => "id bigint(20) unsigned NOT NULL AUTO_INCREMENT, feed_item_id bigint(20) unsigned NOT NULL, provider varchar(50) NOT NULL, model varchar(100) NOT NULL, output_json longtext NOT NULL, input_tokens int unsigned NOT NULL DEFAULT 0, output_tokens int unsigned NOT NULL DEFAULT 0, estimated_cost decimal(12,6) NOT NULL DEFAULT 0, created_at datetime NOT NULL, PRIMARY KEY (id)",
            'logs' => "id bigint(20) unsigned NOT NULL AUTO_INCREMENT, level varchar(20) NOT NULL, event varchar(100) NOT NULL, message text NOT NULL, context_json longtext NULL, created_at datetime NOT NULL, PRIMARY KEY (id), KEY level (level), KEY event (event)",
            'daily_usage' => "id bigint(20) unsigned NOT NULL AUTO_INCREMENT, usage_date date NOT NULL, ai_requests int unsigned NOT NULL DEFAULT 0, input_tokens int unsigned NOT NULL DEFAULT 0, output_tokens int unsigned NOT NULL DEFAULT 0, estimated_cost decimal(12,6) NOT NULL DEFAULT 0, PRIMARY KEY (id), UNIQUE KEY usage_date (usage_date)",
            'migration_journal' => "id bigint(20) unsigned NOT NULL AUTO_INCREMENT, migration varchar(100) NOT NULL, status varchar(30) NOT NULL, source_count int unsigned NOT NULL DEFAULT 0, snapshot_json longtext NOT NULL, report_json longtext NULL, created_at datetime NOT NULL, completed_at datetime NULL, PRIMARY KEY (id), KEY migration_status (migration,status)"
        ];
        foreach ($tables as $name => $schema) dbDelta('CREATE TABLE ' . Support::table($name) . ' (' . $schema . ") $charset;");
        try {
            (new SourceMigration($wpdb))->run($previousVersion);
            delete_option('wpnb_source_recovery_required');
            update_option('wpnb_schema_version', WPNB_SCHEMA_VERSION, false);
        } catch (SourceRecoveryRequired) {
            update_option('wpnb_source_recovery_required', ['reason'=>'missing_snapshot','detected_at'=>Support::now(),'from_schema'=>$previousVersion], false);
        } catch (\Throwable $e) {
            update_option('wpnb_source_recovery_required', ['reason'=>'migration_failed','detected_at'=>Support::now(),'from_schema'=>$previousVersion,'error_class'=>get_class($e)], false);
        }
        add_option('wpnb_settings', ['ai_provider'=>'openai','ai_model'=>'gpt-4o-mini','language'=>'tr','tone'=>'professional','min_words'=>300,'max_words'=>700,'show_attribution'=>1,'daily_ai_quota'=>25,'max_run_items'=>5,'cron_enabled'=>0,'retention_days'=>90], '', false);
        add_option('wpnb_setup_state', SetupState::initial(), '', false);
        add_option('wpnb_connection_status', ['connected'=>0], '', false);
    }
    public static function deactivate(): void { wp_clear_scheduled_hook('wpnb_poll_sources'); }
}
