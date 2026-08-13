<?php
declare(strict_types=1);
namespace Neyelazim\NewsBot;

final class Plugin
{
    public static function activate(): void { Database::activate(); if (!wp_next_scheduled('nyb_poll_sources')) wp_schedule_event(time() + 300, 'hourly', 'nyb_poll_sources'); }
    public static function deactivate(): void { Database::deactivate(); }
    public function boot(): void { (new Admin())->register(); add_action('nyb_poll_sources', [$this, 'poll']); }
    public function poll(): void { if (get_transient('nyb_cron_lock')) return; set_transient('nyb_cron_lock', 1, 10 * MINUTE_IN_SECONDS); try { do_action('nyb_sources_polled'); } finally { delete_transient('nyb_cron_lock'); } }
}
