<?php
declare(strict_types=1);
namespace WordPressNewsBot;

final class SourceMigration
{
    public function __construct(private readonly object $db) {}

    /** @return array{groups:int,sources_merged:int,items_merged:int} */
    public function run(): array
    {
        $sourcesTable = Support::table('sources');
        $sources = (array) $this->db->get_results("SELECT id,feed_url,created_at FROM $sourcesTable ORDER BY created_at ASC,id ASC", ARRAY_A);
        $groups = [];
        foreach ($sources as $source) {
            try {
                $canonical = SourceUrl::canonicalize((string) $source['feed_url']);
                $hash = hash('sha256', $canonical);
                $this->db->update($sourcesTable, ['feed_url' => $canonical, 'canonical_hash' => $hash], ['id' => (int) $source['id']]);
                $groups[$hash][] = (int) $source['id'];
            } catch (\Throwable) {
                $this->db->update($sourcesTable, ['canonical_hash' => hash('sha256', 'invalid-source-' . (int) $source['id'])], ['id' => (int) $source['id']]);
            }
        }

        $report = ['groups' => 0, 'sources_merged' => 0, 'items_merged' => 0];
        foreach ($groups as $ids) {
            if (count($ids) < 2) {
                continue;
            }
            $report['groups']++;
            $masterId = array_shift($ids);
            $this->db->query('START TRANSACTION');
            try {
                foreach ($ids as $duplicateId) {
                    $report['items_merged'] += $this->mergeItems($masterId, $duplicateId);
                    $this->db->delete($sourcesTable, ['id' => $duplicateId], ['%d']);
                    $report['sources_merged']++;
                }
                $this->db->query('COMMIT');
            } catch (\Throwable $e) {
                $this->db->query('ROLLBACK');
                throw $e;
            }
        }

        $index = $this->db->get_var("SHOW INDEX FROM $sourcesTable WHERE Key_name='canonical_hash_unique'");
        if (!$index) {
            $this->db->query("ALTER TABLE $sourcesTable ADD UNIQUE KEY canonical_hash_unique (canonical_hash)");
        }
        $this->log($report);
        return $report;
    }

    private function mergeItems(int $masterId, int $duplicateId): int
    {
        $itemsTable = Support::table('feed_items');
        $jobsTable = Support::table('jobs');
        $generationsTable = Support::table('ai_generations');
        $items = (array) $this->db->get_results($this->db->prepare("SELECT * FROM $itemsTable WHERE source_id=%d ORDER BY id ASC FOR UPDATE", $duplicateId), ARRAY_A);
        $merged = 0;
        foreach ($items as $item) {
            $conditions = [];
            $values = [$masterId];
            foreach (['guid', 'normalized_url', 'content_hash'] as $column) {
                if ((string) ($item[$column] ?? '') !== '') {
                    $conditions[] = "$column=%s";
                    $values[] = (string) $item[$column];
                }
            }
            $existingId = 0;
            if ($conditions) {
                $sql = "SELECT id FROM $itemsTable WHERE source_id=%d AND (" . implode(' OR ', $conditions) . ') LIMIT 1';
                $existingId = (int) $this->db->get_var($this->db->prepare($sql, ...$values));
            }
            $itemId = (int) $item['id'];
            if ($existingId > 0) {
                $this->db->query($this->db->prepare("UPDATE $jobsTable SET feed_item_id=%d WHERE feed_item_id=%d", $existingId, $itemId));
                $this->db->query($this->db->prepare("UPDATE $generationsTable SET feed_item_id=%d WHERE feed_item_id=%d", $existingId, $itemId));
                $this->db->delete($itemsTable, ['id' => $itemId], ['%d']);
            } else {
                $this->db->update($itemsTable, ['source_id' => $masterId], ['id' => $itemId]);
            }
            $merged++;
        }
        return $merged;
    }

    private function log(array $report): void
    {
        $this->db->insert(Support::table('logs'), [
            'level' => 'info',
            'event' => 'source_duplicate_migration',
            'message' => 'Source duplicate migration completed.',
            'context_json' => Support::json(Security::cleanLogContext($report)),
            'created_at' => Support::now(),
        ]);
    }
}
