<?php
declare(strict_types=1);
if (!defined('ARRAY_A')) { define('ARRAY_A', 'ARRAY_A'); }
if (!function_exists('wp_strip_all_tags')) { function wp_strip_all_tags(string $v): string { return trim(strip_tags($v)); } }
if (!function_exists('wp_trim_words')) { function wp_trim_words(string $v, int $n): string { return implode(' ', array_slice(preg_split('/\s+/', trim($v)) ?: [], 0, $n)); } }
if (!function_exists('esc_url_raw')) { function esc_url_raw(string $v): string { return trim($v); } }
if (!function_exists('wp_parse_url')) { function wp_parse_url(string $v): array|false { return parse_url($v); } }
if (!function_exists('wp_json_encode')) { function wp_json_encode(mixed $v, int $flags=0): string|false { return json_encode($v,$flags); } }
if (!function_exists('sanitize_title')) { function sanitize_title(string $v): string { return strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', iconv('UTF-8','ASCII//TRANSLIT',$v) ?: $v), '-')); } }
if (!function_exists('sanitize_text_field')) { function sanitize_text_field(string $v): string { return trim(strip_tags($v)); } }
if (!function_exists('sanitize_textarea_field')) { function sanitize_textarea_field(string $v): string { return trim(strip_tags($v)); } }
if (!function_exists('absint')) { function absint(mixed $v): int { return abs((int)$v); } }
if (!function_exists('wp_kses')) { function wp_kses(string $v, array $allowed): string { return strip_tags($v, '<p><br><strong><em><ul><ol><li><blockquote><a>'); } }
if (!function_exists('wp_remote_retrieve_response_code')) { function wp_remote_retrieve_response_code(array $r): int { return (int)($r['response']['code'] ?? 200); } }
if (!function_exists('wp_remote_retrieve_body')) { function wp_remote_retrieve_body(array $r): string { return (string)($r['body'] ?? ''); } }
if (!class_exists('WP_Error')) { class WP_Error { public function __construct(public string $code='error'){} } }
if (!function_exists('is_wp_error')) { function is_wp_error(mixed $v): bool { return $v instanceof WP_Error; } }
if (!function_exists('__')) { function __(string $v, string $domain='default'): string { return $v; } }
if (!function_exists('wp_salt')) { function wp_salt(string $scheme='auth'): string { return 'unit-test-'.$scheme.'-salt'; } }
if (!function_exists('wp_remote_retrieve_header')) { function wp_remote_retrieve_header(array $r, string $name): string { return (string)($r['headers'][$name] ?? ''); } }
require dirname(__DIR__) . '/includes/FeedParser.php'; require dirname(__DIR__) . '/includes/SourceUrl.php'; require dirname(__DIR__) . '/includes/Security.php'; require dirname(__DIR__) . '/includes/Support.php';
require dirname(__DIR__) . '/includes/AiProvider.php'; require dirname(__DIR__) . '/includes/MockAiProvider.php'; require dirname(__DIR__) . '/includes/ContentSanitizer.php'; require dirname(__DIR__) . '/includes/AiResponseValidator.php'; require dirname(__DIR__) . '/includes/OpenAiProvider.php'; require dirname(__DIR__) . '/includes/SecretStorage.php'; require dirname(__DIR__) . '/includes/Credentials.php'; require dirname(__DIR__) . '/includes/ConnectionService.php'; require dirname(__DIR__) . '/includes/SetupState.php'; require dirname(__DIR__) . '/includes/DraftPolicy.php';
require dirname(__DIR__) . '/includes/SourceTestException.php'; require dirname(__DIR__) . '/includes/SourceConnectionTester.php'; require dirname(__DIR__) . '/includes/SourceService.php'; require dirname(__DIR__) . '/includes/SourceRecoveryRequired.php'; require dirname(__DIR__) . '/includes/SourceMigration.php';
require __DIR__ . '/SqliteWpdb.php';
