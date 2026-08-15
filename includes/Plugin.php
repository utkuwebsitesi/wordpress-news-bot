<?php
declare(strict_types=1);
namespace WordPressNewsBot;

final class Plugin
{
    public static function activate(): void { Database::activate();$plugin=new self();add_filter('cron_schedules',[$plugin,'cronSchedules']);$plugin->ensureHeartbeatSchedule();$plugin->ensureAutomationSchedule(); }
    public static function deactivate(): void { Database::deactivate(); }
    public function boot(): void { CriticalErrorGuard::register();if ((string)get_option('wpnb_schema_version','')!==WPNB_SCHEMA_VERSION||(string)get_option('wpnb_plugin_version','')!==WPNB_VERSION) Database::activate(); (new Admin())->register(); add_filter('cron_schedules',[$this,'cronSchedules']);add_action('init',[$this,'initialize']);add_action('wpnb_poll_sources', [$this, 'poll']);add_action('wpnb_automation_tick',[$this,'automationTick']);add_action(CronHealth::HEARTBEAT_HOOK,[$this,'heartbeat']);add_action(CronHealth::TEST_HOOK,[$this,'heartbeat'],10,1); add_action('wpnb_sources_polled', [$this, 'importActiveSources']); add_action('update_option_wpnb_settings', [$this, 'syncCron'], 10, 3); }
    public function initialize():void
    {
        load_plugin_textdomain('wordpress-news-bot',false,dirname(plugin_basename(WPNB_FILE)).'/languages');
        $settings=(array)get_option('wpnb_settings',[]);
        if(($settings['language']??'tr')==='tr'){
            $turkish=WPNB_DIR.'languages/wordpress-news-bot-tr_TR.mo';
            if(is_readable($turkish))load_textdomain('wordpress-news-bot',$turkish,'tr_TR');
        }
        $this->ensureHeartbeatSchedule();$this->ensureAutomationSchedule();
    }
    public function cronSchedules(array$schedules):array{$schedules['wpnb_five_minutes']=['interval'=>5*MINUTE_IN_SECONDS,'display'=>__('Every five minutes','wordpress-news-bot')];return$schedules;}
    public function syncCron(mixed $old, mixed $new): void { if ((int)($new['cron_enabled'] ?? 0) && !wp_next_scheduled('wpnb_poll_sources')) wp_schedule_event(time() + 300, 'hourly', 'wpnb_poll_sources'); if (!(int)($new['cron_enabled'] ?? 0)) wp_clear_scheduled_hook('wpnb_poll_sources');$this->ensureHeartbeatSchedule();$this->ensureAutomationSchedule(); }
    public function ensureHeartbeatSchedule():void{if(!wp_next_scheduled(CronHealth::HEARTBEAT_HOOK))wp_schedule_event(time(),'wpnb_five_minutes',CronHealth::HEARTBEAT_HOOK);}
    public function ensureAutomationSchedule():void{$enabled=!empty(get_option('wpnb_settings',[])['automation_enabled']);if($enabled&&!wp_next_scheduled('wpnb_automation_tick'))wp_schedule_event(time()+60,'wpnb_five_minutes','wpnb_automation_tick');if(!$enabled)wp_clear_scheduled_hook('wpnb_automation_tick');}
    public function heartbeat(string$testId=''):void{CronHealth::record($testId);}
    public function automationTick():void{(new AutomationService())->run('cron');}
    public function poll(): void { if (!(int) (get_option('wpnb_settings', [])['cron_enabled'] ?? 0) || get_transient('wpnb_cron_lock')) return; set_transient('wpnb_cron_lock', 1, 10 * MINUTE_IN_SECONDS); try { do_action('wpnb_sources_polled'); update_option('wpnb_last_automation_run',Support::now(),false); } finally { delete_transient('wpnb_cron_lock'); } }
    public function importActiveSources(bool $force=false): void { global$wpdb;$sources=(array)$wpdb->get_results('SELECT id,interval_minutes,last_checked_at FROM '.Support::table('sources').' WHERE active=1 ORDER BY id ASC',ARRAY_A);foreach($sources as$source){$interval=max(5,(int)($source['interval_minutes']??60));$last=Support::utcTimestamp((string)($source['last_checked_at']??''));if(!$force&&$last!==null&&$last>time()-$interval*60)continue;$id=(int)$source['id'];try{(new SourceImporter())->import($id);}catch(\Throwable$e){$update=['last_checked_at'=>Support::now(),'last_result'=>__('Failed','wordpress-news-bot'),'last_error'=>__('The source could not be imported. Check the RSS URL and try again.','wordpress-news-bot'),'updated_at'=>Support::now()];$wpdb->update(Support::table('sources'),$update,['id'=>$id],DatabaseSchema::formatsFor('sources',$update),['%d']);$log=['level'=>'error','event'=>'source_import_failed','message'=>'A source import failed.','context_json'=>Support::json(Security::cleanLogContext(['source_id'=>$id,'exception_class'=>get_class($e)])),'created_at'=>Support::now()];$wpdb->insert(Support::table('logs'),$log,DatabaseSchema::formatsFor('logs',$log));}} }
}
