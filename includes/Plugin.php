<?php
declare(strict_types=1);
namespace WordPressNewsBot;

final class Plugin
{
    public static function activate(): void { Database::activate(); }
    public static function deactivate(): void { Database::deactivate(); }
    public function boot(): void { load_plugin_textdomain('wordpress-news-bot', false, dirname(plugin_basename(WPNB_FILE)).'/languages'); (new Admin())->register(); add_action('wpnb_poll_sources', [$this, 'poll']); add_action('update_option_wpnb_settings', [$this, 'syncCron'], 10, 3); }
    public function syncCron(mixed $old, mixed $new): void { if ((int)($new['cron_enabled'] ?? 0) && !wp_next_scheduled('wpnb_poll_sources')) wp_schedule_event(time() + 300, 'hourly', 'wpnb_poll_sources'); if (!(int)($new['cron_enabled'] ?? 0)) wp_clear_scheduled_hook('wpnb_poll_sources'); }
    public function poll(): void { if (!(int) (get_option('wpnb_settings', [])['cron_enabled'] ?? 0) || get_transient('wpnb_cron_lock')) return; set_transient('wpnb_cron_lock', 1, 10 * MINUTE_IN_SECONDS); try { do_action('wpnb_sources_polled'); update_option('wpnb_last_automation_run',Support::now(),false); } finally { delete_transient('wpnb_cron_lock'); } }
}
