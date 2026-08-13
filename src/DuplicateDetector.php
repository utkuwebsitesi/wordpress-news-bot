<?php
declare(strict_types=1);
namespace Neyelazim\NewsBot;

final class DuplicateDetector
{
    public function __construct(private readonly object $db) {}
    public function isDuplicate(array $item, int $sourceId, int $windowHours = 72): bool
    {
        global $wpdb; $table = Support::table('feed_items');
        $url = Support::normalizeUrl((string) ($item['source_url'] ?? '')); $guid = (string) ($item['guid'] ?? ''); $hash = (string) ($item['content_hash'] ?? '');
        $sql = "SELECT id FROM $table WHERE source_id=%d AND (guid=%s OR normalized_url=%s OR content_hash=%s OR (title=%s AND created_at >= %s)) LIMIT 1";
        return (bool) $wpdb->get_var($wpdb->prepare($sql, $sourceId, $guid, $url, $hash, (string) ($item['title'] ?? ''), gmdate('Y-m-d H:i:s', time() - ($windowHours * 3600))));
    }
}
