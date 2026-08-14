<?php
declare(strict_types=1);
namespace WordPressNewsBot;

final class Database
{
    public static function activate(): void
    {
        global $wpdb;
        try {
            $result=(new DatabaseRepair($wpdb))->run(true);
            if($result['status']==='healthy')update_option('wpnb_plugin_version',WPNB_VERSION,false);
            else update_option('wpnb_source_recovery_required',['reason'=>'database_repair_required','detected_at'=>Support::now(),'schema_fingerprint'=>$result['after']],false);
        } catch (\Throwable $e) {
            update_option('wpnb_source_recovery_required', ['reason'=>'database_repair_failed','detected_at'=>Support::now(),'error_class'=>get_class($e)], false);
        }
        add_option('wpnb_settings', ['ai_provider'=>'openai','ai_model'=>'gpt-4o-mini','language'=>'tr','tone'=>'professional','min_words'=>300,'max_words'=>700,'show_attribution'=>0,'daily_ai_quota'=>25,'max_run_items'=>5,'cron_enabled'=>0,'retention_days'=>90], '', false);
        add_option('wpnb_setup_state', SetupState::initial(), '', false);
        add_option('wpnb_connection_status', ['connected'=>0], '', false);
    }
    public static function deactivate(): void { wp_clear_scheduled_hook('wpnb_poll_sources'); }
}
