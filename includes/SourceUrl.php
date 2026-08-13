<?php
declare(strict_types=1);
namespace WordPressNewsBot;

final class SourceUrl
{
    public static function canonicalize(string $url): string
    {
        $parts = wp_parse_url(trim($url));
        if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            throw new \InvalidArgumentException(__('A valid RSS/Atom URL is required.', 'wordpress-news-bot'));
        }
        if (isset($parts['user']) || isset($parts['pass'])) {
            throw new \InvalidArgumentException(__('URLs containing a username or password are not allowed.', 'wordpress-news-bot'));
        }

        $scheme = strtolower((string) $parts['scheme']);
        if (!in_array($scheme, ['http', 'https'], true)) {
            throw new \InvalidArgumentException(__('Only HTTP and HTTPS feed URLs are supported.', 'wordpress-news-bot'));
        }

        $host = self::normalizeHost((string) $parts['host']);
        if ($host === '') {
            throw new \InvalidArgumentException(__('The feed hostname is invalid.', 'wordpress-news-bot'));
        }

        $port = isset($parts['port']) ? (int) $parts['port'] : null;
        $portPart = $port !== null && !(($scheme === 'http' && $port === 80) || ($scheme === 'https' && $port === 443))
            ? ':' . $port
            : '';
        $path = (string) ($parts['path'] ?? '/');
        $path = $path === '' || $path === '/' ? '/' : rtrim($path, '/');
        $query = isset($parts['query']) && $parts['query'] !== '' ? '?' . $parts['query'] : '';

        return $scheme . '://' . $host . $portPart . $path . $query;
    }

    public static function hash(string $url): string
    {
        return hash('sha256', self::canonicalize($url));
    }

    public static function normalizeHost(string $host): string
    {
        $host = strtolower(rtrim(trim($host), '.'));
        if ($host === '') {
            return '';
        }
        if (function_exists('idn_to_ascii') && !filter_var($host, FILTER_VALIDATE_IP)) {
            $variant = defined('INTL_IDNA_VARIANT_UTS46') ? INTL_IDNA_VARIANT_UTS46 : 0;
            $ascii = idn_to_ascii($host, IDNA_DEFAULT, $variant);
            if ($ascii === false) {
                return '';
            }
            $host = strtolower($ascii);
        }
        return $host;
    }
}
