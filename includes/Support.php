<?php
declare(strict_types=1);
namespace WordPressNewsBot;

final class Support
{
    public static function table(string $name): string { global $wpdb; return $wpdb->prefix . 'wpnb_' . $name; }
    public static function now(): string { return gmdate('Y-m-d H:i:s'); }
    public static function siteNow(): \DateTimeImmutable { $timezone=function_exists('wp_timezone')?wp_timezone():new \DateTimeZone('Europe/Istanbul');return new \DateTimeImmutable('now',$timezone); }
    public static function siteDate(): string { return self::siteNow()->format('Y-m-d'); }
    /** @return array{0:string,1:string} */
    public static function siteDayUtcBounds(?\DateTimeImmutable$now=null):array{$now=$now??self::siteNow();$start=$now->setTime(0,0)->setTimezone(new \DateTimeZone('UTC'));return[$start->format('Y-m-d H:i:s'),$start->modify('+1 day')->format('Y-m-d H:i:s')];}
    public static function json(mixed $value): string { return (string) wp_json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); }
    public static function normalizeUrl(string $url): string
    {
        $parts = wp_parse_url(trim($url));
        if (!$parts || empty($parts['host'])) return '';
        $path = rtrim($parts['path'] ?? '/', '/');
        $query = [];
        parse_str($parts['query'] ?? '', $query);
        foreach (array_keys($query) as $key) if (str_starts_with(strtolower((string) $key), 'utm_')) unset($query[$key]);
        ksort($query);
        $scheme = strtolower((string) ($parts['scheme'] ?? 'https'));
        return $scheme . '://' . SourceUrl::normalizeHost((string) $parts['host']) . ($path ?: '/') . ($query ? '?' . http_build_query($query) : '');
    }
}
