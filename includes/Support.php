<?php
declare(strict_types=1);
namespace WordPressNewsBot;

final class Support
{
    private const UTC_SQL_FORMAT = 'Y-m-d H:i:s';
    public static function table(string $name): string { global $wpdb; return $wpdb->prefix . 'wpnb_' . $name; }
    public static function now(): string { return gmdate('Y-m-d H:i:s'); }
    public static function siteNow(): \DateTimeImmutable { $timezone=function_exists('wp_timezone')?wp_timezone():new \DateTimeZone('Europe/Istanbul');return new \DateTimeImmutable('now',$timezone); }
    public static function siteDate(): string { return self::siteNow()->format('Y-m-d'); }
    public static function utcTimestamp(string|int|null $value): ?int
    {
        if (is_int($value)) return $value > 0 ? $value : null;
        $value = trim((string) $value);
        if ($value === '') return null;
        if (ctype_digit($value)) return (int) $value > 0 ? (int) $value : null;
        if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/D', $value)) {
            $date = \DateTimeImmutable::createFromFormat('!' . self::UTC_SQL_FORMAT, $value, new \DateTimeZone('UTC'));
            $errors = \DateTimeImmutable::getLastErrors();
            return $date !== false && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0)) ? $date->getTimestamp() : null;
        }
        if (!preg_match('/(?:Z|[+-]\d{2}:?\d{2})$/i', $value)) return null;
        try { return (new \DateTimeImmutable($value))->getTimestamp(); } catch (\Throwable) { return null; }
    }
    public static function localDateTime(string|int|null $utc, string $format='Y-m-d H:i:s'): string
    {
        $timestamp=self::utcTimestamp($utc);if($timestamp===null)return'';
        if(function_exists('wp_date')&&function_exists('wp_timezone'))return wp_date($format,$timestamp,wp_timezone());
        return(new \DateTimeImmutable('@'.$timestamp))->setTimezone(self::siteNow()->getTimezone())->format($format);
    }
    public static function nextQuarterHour(?int $timestamp=null):int{$timestamp=$timestamp??time();return intdiv($timestamp,15*MINUTE_IN_SECONDS)*15*MINUTE_IN_SECONDS+15*MINUTE_IN_SECONDS;}
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
