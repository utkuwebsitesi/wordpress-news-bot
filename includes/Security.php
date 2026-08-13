<?php
declare(strict_types=1);
namespace WordPressNewsBot;

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
    public static function cleanLogContext(array $context): array
    {
        foreach($context as$key=>$value){$normalized=strtolower((string)$key);if(in_array($normalized,['api_key','authorization','token','email','secret','ciphertext'],true)){unset($context[$key]);continue;}if(is_array($value))$context[$key]=self::cleanLogContext($value);elseif(is_string($value))$context[$key]=preg_replace('/(?:bearer\s+|api[_ -]?key\s*[:=]\s*)\S+/i','[redacted]',$value);}
        return $context;
    }
}
