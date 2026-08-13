<?php
declare(strict_types=1);
namespace WordPressNewsBot;

final class SourceService
{
    public function __construct(private readonly object $db, private readonly ?SourceConnectionTester $tester = null) {}

    /** @return array{http_status:int,feed_type:string,item_count:int,duration_ms:int} */
    public function testConnection(array $input): array
    {
        $data = $this->sanitize($input);
        return ($this->tester ?? new SourceConnectionTester())->test($data['feed_url'], $data['allowed_domains_list']);
    }

    public function save(array $input, int $sourceId = 0): int
    {
        $data = $this->sanitize($input);
        $duplicate = (int) $this->db->get_var($this->db->prepare(
            'SELECT id FROM ' . Support::table('sources') . ' WHERE canonical_hash=%s AND id<>%d LIMIT 1',
            $data['canonical_hash'],
            $sourceId
        ));
        if ($duplicate > 0) {
            throw new \DomainException(__('This RSS source is already registered.', 'wordpress-news-bot'));
        }

        $existing = $sourceId > 0 ? $this->db->get_row($this->db->prepare('SELECT * FROM ' . Support::table('sources') . ' WHERE id=%d LIMIT 1', $sourceId), ARRAY_A) : null;
        if ($sourceId > 0 && !$existing) {
            throw new \RuntimeException(__('The news source was not found.', 'wordpress-news-bot'));
        }
        $urlChanged = !$existing || !hash_equals((string) ($existing['canonical_hash'] ?? ''), $data['canonical_hash'])
            || (string) ($existing['allowed_domains'] ?? '') !== $data['allowed_domains'];
        $testResult = $urlChanged ? $this->testConnection($input) : null;

        $now = Support::now();
        $record = [
            'name' => $data['name'],
            'feed_url' => $data['feed_url'],
            'canonical_hash' => $data['canonical_hash'],
            'allowed_domains' => $data['allowed_domains'],
            'category_id' => $data['category_id'],
            'active' => $data['active'],
            'updated_at' => $now,
        ];
        if ($testResult !== null) {
            $record['last_checked_at'] = $now;
            $record['last_result'] = sprintf(
                __('HTTP %1$d, %2$s, %3$d accessible items, %4$d ms', 'wordpress-news-bot'),
                $testResult['http_status'],
                $testResult['feed_type'],
                $testResult['item_count'],
                $testResult['duration_ms']
            );
            $record['last_error'] = null;
        }
        if ($sourceId > 0) {
            $ok = $this->db->update(Support::table('sources'), $record, ['id' => $sourceId]);
            if ($ok === false) {
                $this->throwDatabaseError();
            }
            return $sourceId;
        }
        $record['created_at'] = $now;
        $ok = $this->db->insert(Support::table('sources'), $record);
        if ($ok === false) {
            $this->throwDatabaseError();
        }
        return (int) $this->db->insert_id;
    }

    public function toggle(int $sourceId, ?bool $active = null): bool
    {
        $row = $this->db->get_row($this->db->prepare('SELECT id,active FROM ' . Support::table('sources') . ' WHERE id=%d LIMIT 1', $sourceId), ARRAY_A);
        if (!$row) {
            throw new \RuntimeException(__('The news source was not found.', 'wordpress-news-bot'));
        }
        $newState = $active ?? !(bool) $row['active'];
        if ($this->db->update(Support::table('sources'), ['active' => $newState ? 1 : 0, 'updated_at' => Support::now()], ['id' => $sourceId]) === false) {
            $this->throwDatabaseError();
        }
        return $newState;
    }

    /** @return array{pending:int,processed:int,drafts:int,locked:int} */
    public function deletionSummary(int $sourceId): array
    {
        $table = Support::table('feed_items');
        $jobs = Support::table('jobs');
        return [
            'pending' => (int) $this->db->get_var($this->db->prepare("SELECT COUNT(*) FROM $table WHERE source_id=%d AND status IN ('new','review')", $sourceId)),
            'processed' => (int) $this->db->get_var($this->db->prepare("SELECT COUNT(*) FROM $table WHERE source_id=%d AND status NOT IN ('new','review')", $sourceId)),
            'drafts' => (int) $this->db->get_var($this->db->prepare("SELECT COUNT(*) FROM $table WHERE source_id=%d AND status='draft_created'", $sourceId)),
            'locked' => (int) $this->db->get_var($this->db->prepare("SELECT COUNT(*) FROM $jobs j JOIN $table f ON f.id=j.feed_item_id WHERE f.source_id=%d AND (j.status IN ('running','processing') OR (j.locked_at IS NOT NULL AND j.locked_at >= %s))", $sourceId, gmdate('Y-m-d H:i:s', time() - 15 * 60))),
        ];
    }

    public function delete(int $sourceId): array
    {
        if ($sourceId < 1) {
            throw new \InvalidArgumentException(__('Invalid source ID.', 'wordpress-news-bot'));
        }
        $source = $this->db->get_row($this->db->prepare('SELECT * FROM ' . Support::table('sources') . ' WHERE id=%d LIMIT 1', $sourceId), ARRAY_A);
        if (!$source) {
            throw new \RuntimeException(__('The news source was not found.', 'wordpress-news-bot'));
        }
        $summary = $this->deletionSummary($sourceId);
        if ($summary['locked'] > 0) {
            throw new \RuntimeException(__('This source has running or locked jobs and cannot be deleted safely yet.', 'wordpress-news-bot'));
        }

        $items = Support::table('feed_items');
        $jobs = Support::table('jobs');
        $generations = Support::table('ai_generations');
        $sources = Support::table('sources');
        $this->db->query('START TRANSACTION');
        try {
            $pendingIds = $this->db->get_col($this->db->prepare("SELECT id FROM $items WHERE source_id=%d AND status IN ('new','review') FOR UPDATE", $sourceId));
            if ($pendingIds) {
                $ids = implode(',', array_map('absint', $pendingIds));
                $this->db->query("DELETE FROM $jobs WHERE feed_item_id IN ($ids)");
                $this->db->query("DELETE FROM $generations WHERE feed_item_id IN ($ids)");
                $this->db->query("DELETE FROM $items WHERE id IN ($ids)");
            }
            $this->db->query($this->db->prepare(
                "UPDATE $items SET source_id=0, source_name=%s, source_feed_url=%s, guid=CONCAT('archived-',id,'-',LEFT(guid,220)), updated_at=%s WHERE source_id=%d",
                (string) $source['name'],
                (string) $source['feed_url'],
                Support::now(),
                $sourceId
            ));
            if ($this->db->delete($sources, ['id' => $sourceId], ['%d']) === false) {
                $this->throwDatabaseError();
            }
            $this->db->query('COMMIT');
        } catch (\Throwable $e) {
            $this->db->query('ROLLBACK');
            throw $e;
        }
        return $summary;
    }

    private function sanitize(array $input): array
    {
        $name = sanitize_text_field((string) ($input['name'] ?? ''));
        $url = SourceUrl::canonicalize(esc_url_raw((string) ($input['feed_url'] ?? '')));
        $parts = wp_parse_url($url);
        $primaryHost = SourceUrl::normalizeHost((string) ($parts['host'] ?? ''));
        $domains = preg_split('/[\r\n,]+/', (string) ($input['allowed_domains'] ?? '')) ?: [];
        $domains = array_values(array_unique(array_filter(array_map([SourceUrl::class, 'normalizeHost'], array_map('trim', $domains)))));
        array_unshift($domains, $primaryHost);
        $wwwParent = str_starts_with($primaryHost, 'www.') ? substr($primaryHost, 4) : '';
        if ($wwwParent !== '' && count(explode('.', $wwwParent)) >= 3) $domains[] = $wwwParent;
        $domains = array_values(array_unique(array_filter($domains)));
        if ($name === '' || !Security::validateFeedUrl($url, $domains)) {
            throw new \InvalidArgumentException(__('The source details did not pass security validation.', 'wordpress-news-bot'));
        }
        return [
            'name' => $name,
            'feed_url' => $url,
            'canonical_hash' => hash('sha256', $url),
            'allowed_domains' => implode("\n", $domains),
            'allowed_domains_list' => $domains,
            'category_id' => max(0, absint($input['category_id'] ?? 0)),
            'active' => empty($input['active']) ? 0 : 1,
        ];
    }

    private function throwDatabaseError(): never
    {
        $message = strtolower((string) ($this->db->last_error ?? ''));
        if (str_contains($message, 'duplicate')) {
            throw new \DomainException(__('This RSS source is already registered.', 'wordpress-news-bot'));
        }
        throw new \RuntimeException(__('The source could not be saved because of a database error.', 'wordpress-news-bot'));
    }
}
