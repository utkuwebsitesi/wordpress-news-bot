<?php
/**
 * Plugin Name: WordPress News Bot
 * Description: Güvenli RSS/Atom haber havuzu ve editör kontrollü içerik hazırlama altyapısı.
 * Version: 0.4.0-rc.1
 * Requires at least: 6.4
 * Requires PHP: 8.1
 * Author: Utkuweb
 * Text Domain: wordpress-news-bot
 */
declare(strict_types=1);

if (!defined('ABSPATH')) { exit; }

define('WPNB_VERSION', '0.4.0-rc.1');
define('WPNB_SCHEMA_VERSION', '1.7.0');
define('WPNB_FILE', __FILE__);
define('WPNB_DIR', plugin_dir_path(__FILE__));

require_once WPNB_DIR . 'includes/Support.php';
require_once WPNB_DIR . 'includes/DatabaseErrorClassifier.php';
require_once WPNB_DIR . 'includes/DatabaseSchema.php';
require_once WPNB_DIR . 'includes/DatabaseHealth.php';
require_once WPNB_DIR . 'includes/DiagnosticStore.php';
require_once WPNB_DIR . 'includes/DatabaseEngineRepairException.php';
require_once WPNB_DIR . 'includes/DatabaseEngineRepair.php';
require_once WPNB_DIR . 'includes/DatabaseRepair.php';
require_once WPNB_DIR . 'includes/SourceUrl.php';
require_once WPNB_DIR . 'includes/Database.php';
require_once WPNB_DIR . 'includes/FeedParser.php';
require_once WPNB_DIR . 'includes/Security.php';
require_once WPNB_DIR . 'includes/DuplicateDetector.php';
require_once WPNB_DIR . 'includes/AiProvider.php';
require_once WPNB_DIR . 'includes/MockAiProvider.php';
require_once WPNB_DIR . 'includes/AiResponseValidator.php';
require_once WPNB_DIR . 'includes/ContentSanitizer.php';
require_once WPNB_DIR . 'includes/SecretStorage.php';
require_once WPNB_DIR . 'includes/Credentials.php';
require_once WPNB_DIR . 'includes/OpenAiProvider.php';
require_once WPNB_DIR . 'includes/ConnectionService.php';
require_once WPNB_DIR . 'includes/SetupState.php';
require_once WPNB_DIR . 'includes/DraftService.php';
require_once WPNB_DIR . 'includes/DraftPolicy.php';
require_once WPNB_DIR . 'includes/SourceImporter.php';
require_once WPNB_DIR . 'includes/ManualImportService.php';
require_once WPNB_DIR . 'includes/SourceTestException.php';
require_once WPNB_DIR . 'includes/SourceConnectionTester.php';
require_once WPNB_DIR . 'includes/SourceService.php';
require_once WPNB_DIR . 'includes/SourceRecoveryRequired.php';
require_once WPNB_DIR . 'includes/SourceMigration.php';
require_once WPNB_DIR . 'admin/Admin.php';
require_once WPNB_DIR . 'includes/Plugin.php';

register_activation_hook(__FILE__, ['WordPressNewsBot\\Plugin', 'activate']);
register_deactivation_hook(__FILE__, ['WordPressNewsBot\\Plugin', 'deactivate']);

add_action('plugins_loaded', static function (): void {
    (new WordPressNewsBot\Plugin())->boot();
});
