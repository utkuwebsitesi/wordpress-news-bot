<?php
/**
 * Plugin Name: Neyelazım NewsBot
 * Description: Güvenli RSS/Atom haber havuzu ve editör kontrollü içerik hazırlama altyapısı.
 * Version: 0.2.0
 * Requires at least: 6.4
 * Requires PHP: 8.1
 * Author: Neyelazım
 * Text Domain: neyelazim-newsbot
 */
declare(strict_types=1);

if (!defined('ABSPATH')) { exit; }

define('NYB_VERSION', '0.2.0');
define('NYB_SCHEMA_VERSION', '1.1.0');
define('NYB_FILE', __FILE__);
define('NYB_DIR', plugin_dir_path(__FILE__));

require_once NYB_DIR . 'src/Support.php';
require_once NYB_DIR . 'src/Database.php';
require_once NYB_DIR . 'src/FeedParser.php';
require_once NYB_DIR . 'src/Security.php';
require_once NYB_DIR . 'src/DuplicateDetector.php';
require_once NYB_DIR . 'src/AiProvider.php';
require_once NYB_DIR . 'src/MockAiProvider.php';
require_once NYB_DIR . 'src/AiResponseValidator.php';
require_once NYB_DIR . 'src/ContentSanitizer.php';
require_once NYB_DIR . 'src/Credentials.php';
require_once NYB_DIR . 'src/OpenAiProvider.php';
require_once NYB_DIR . 'src/DraftService.php';
require_once NYB_DIR . 'src/DraftPolicy.php';
require_once NYB_DIR . 'src/SourceImporter.php';
require_once NYB_DIR . 'src/Admin.php';
require_once NYB_DIR . 'src/Plugin.php';

register_activation_hook(__FILE__, ['Neyelazim\\NewsBot\\Plugin', 'activate']);
register_deactivation_hook(__FILE__, ['Neyelazim\\NewsBot\\Plugin', 'deactivate']);

add_action('plugins_loaded', static function (): void {
    (new Neyelazim\NewsBot\Plugin())->boot();
});
