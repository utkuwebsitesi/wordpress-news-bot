<?php
declare(strict_types=1);
if (!function_exists('wp_strip_all_tags')) { function wp_strip_all_tags(string $v): string { return trim(strip_tags($v)); } }
if (!function_exists('wp_trim_words')) { function wp_trim_words(string $v, int $n): string { return implode(' ', array_slice(preg_split('/\s+/', trim($v)) ?: [], 0, $n)); } }
if (!function_exists('esc_url_raw')) { function esc_url_raw(string $v): string { return trim($v); } }
if (!function_exists('wp_parse_url')) { function wp_parse_url(string $v): array|false { return parse_url($v); } }
if (!function_exists('sanitize_title')) { function sanitize_title(string $v): string { return strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', iconv('UTF-8','ASCII//TRANSLIT',$v) ?: $v), '-')); } }
require dirname(__DIR__) . '/src/FeedParser.php'; require dirname(__DIR__) . '/src/Security.php'; require dirname(__DIR__) . '/src/MockAiProvider.php'; require dirname(__DIR__) . '/src/Support.php';
