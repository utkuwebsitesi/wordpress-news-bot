<?php
declare(strict_types=1);
namespace Neyelazim\NewsBot;

final class Security
{
    public static function validateFeedUrl(string $url, array $allowed = []): bool
    {
        $parts = wp_parse_url($url);
        if (!$parts || ($parts['scheme'] ?? '') !== 'https' || empty($parts['host'])) return false;
        $host = strtolower((string) $parts['host']);
        if (filter_var($host, FILTER_VALIDATE_IP) && !self::isPublicIp($host)) return false;
        if (in_array($host, ['localhost', 'localhost.localdomain'], true) || str_ends_with($host, '.local')) return false;
        return !$allowed || in_array($host, array_map('strtolower', $allowed), true);
    }
    private static function isPublicIp(string $ip): bool { return (bool) filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE); }
    public static function canManage(): bool { return current_user_can('manage_options'); }
    public static function canReview(): bool { return current_user_can('manage_options') || current_user_can('edit_posts'); }
    public static function cleanLogContext(array $context): array { unset($context['api_key'], $context['authorization'], $context['token'], $context['email']); return $context; }
}
