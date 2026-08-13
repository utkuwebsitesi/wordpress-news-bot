<?php
declare(strict_types=1);
namespace WordPressNewsBot;

final class Security
{
    public static function validateFeedUrl(string $url, array $allowed = []): bool
    {
        $parts = wp_parse_url($url);
        if (!$parts || strtolower((string) ($parts['scheme'] ?? '')) !== 'https' || empty($parts['host']) || isset($parts['user']) || isset($parts['pass'])) return false;
        $host = SourceUrl::normalizeHost((string) $parts['host']);
        if ($host === '') return false;
        if (filter_var($host, FILTER_VALIDATE_IP) && !self::isPublicIp($host)) return false;
        if (in_array($host, ['localhost', 'localhost.localdomain'], true) || str_ends_with($host, '.local')) return false;
        if (!$allowed) return true;
        foreach (array_map([SourceUrl::class, 'normalizeHost'], $allowed) as $allowedHost) {
            if (self::hostMatchesAllowed($host, $allowedHost)) return true;
        }
        return false;
    }
    public static function hostMatchesAllowed(string $host, string $allowedHost): bool { $host=SourceUrl::normalizeHost($host);$allowedHost=SourceUrl::normalizeHost($allowedHost);return $host!==''&&$allowedHost!==''&&($host===$allowedHost||str_ends_with($host,'.'.$allowedHost)); }
    private static function isPublicIp(string $ip): bool { return (bool) filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE); }
    public static function canManage(): bool { return current_user_can('manage_options'); }
    public static function canReview(): bool { return current_user_can('manage_options') || current_user_can('edit_posts'); }
    public static function cleanLogContext(array $context): array
    {
        foreach($context as$key=>$value){$normalized=strtolower((string)$key);if(in_array($normalized,['api_key','authorization','token','email','secret','ciphertext'],true)){unset($context[$key]);continue;}if(is_array($value))$context[$key]=self::cleanLogContext($value);elseif(is_string($value))$context[$key]=preg_replace('/(?:bearer\s+|api[_ -]?key\s*[:=]\s*)\S+/i','[redacted]',$value);}
        return $context;
    }
}
