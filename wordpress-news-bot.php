<?php
/**
 * Plugin Name: WordPress News Bot
 * Description: Güvenli RSS/Atom haber havuzu ve editör kontrollü içerik hazırlama altyapısı.
 * Version: 0.2.1
 * Requires at least: 6.4
 * Requires PHP: 8.1
 * Author: WordPress News Bot
 * Text Domain: wordpress-news-bot
 */
declare(strict_types=1);

if (!defined('ABSPATH')) { exit; }

define('WPNB_VERSION', '0.2.1');
define('WPNB_SCHEMA_VERSION', '1.1.0');
define('WPNB_FILE', __FILE__);
define('WPNB_DIR', plugin_dir_path(__FILE__));

require_once WPNB_DIR . 'includes/Support.php';
require_once WPNB_DIR . 'includes/Database.php';
require_once WPNB_DIR . 'includes/FeedParser.php';
require_once WPNB_DIR . 'includes/Security.php';
require_once WPNB_DIR . 'includes/DuplicateDetector.php';
require_once WPNB_DIR . 'includes/AiProvider.php';
require_once WPNB_DIR . 'includes/MockAiProvider.php';
require_once WPNB_DIR . 'includes/AiResponseValidator.php';
require_once WPNB_DIR . 'includes/ContentSanitizer.php';
require_once WPNB_DIR . 'includes/Credentials.php';
require_once WPNB_DIR . 'includes/OpenAiProvider.php';
require_once WPNB_DIR . 'includes/DraftService.php';
require_once WPNB_DIR . 'includes/DraftPolicy.php';
require_once WPNB_DIR . 'includes/SourceImporter.php';
require_once WPNB_DIR . 'admin/Admin.php';
require_once WPNB_DIR . 'includes/Plugin.php';

register_activation_hook(__FILE__, ['WordPressNewsBot\\Plugin', 'activate']);
register_deactivation_hook(__FILE__, ['WordPressNewsBot\\Plugin', 'deactivate']);

add_action('plugins_loaded', static function (): void {
    (new WordPressNewsBot\Plugin())->boot();
});
