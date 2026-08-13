<?php
declare(strict_types=1);
namespace Neyelazim\NewsBot;

final class Plugin
{
    public static function activate(): void { Database::activate(); }
    public static function deactivate(): void { Database::deactivate(); }
    public function boot(): void { (new Admin())->register(); add_action('nyb_poll_sources', [$this, 'poll']); add_action('update_option_nyb_settings', [$this, 'syncCron'], 10, 3); }
    public function syncCron(mixed $old, mixed $new): void { if ((int)($new['cron_enabled'] ?? 0) && !wp_next_scheduled('nyb_poll_sources')) wp_schedule_event(time() + 300, 'hourly', 'nyb_poll_sources'); if (!(int)($new['cron_enabled'] ?? 0)) wp_clear_scheduled_hook('nyb_poll_sources'); }
    public function poll(): void { if (!(int) (get_option('nyb_settings', [])['cron_enabled'] ?? 0) || get_transient('nyb_cron_lock')) return; set_transient('nyb_cron_lock', 1, 10 * MINUTE_IN_SECONDS); try { do_action('nyb_sources_polled'); } finally { delete_transient('nyb_cron_lock'); } }
}
