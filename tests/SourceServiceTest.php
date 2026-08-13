<?php
declare(strict_types=1);
namespace WordPressNewsBot\Tests;

use DomainException;
use PHPUnit\Framework\TestCase;
use WordPressNewsBot\SourceConnectionTester;
use WordPressNewsBot\SourceService;

final class SourceServiceTest extends TestCase
{
    private FakeSourceDb $db;
    private SourceService $service;

    protected function setUp(): void
    {
        global $wpdb;
        $this->db = new FakeSourceDb();
        $wpdb = $this->db;
        $tester = new SourceConnectionTester(static fn(): array => ['response'=>['code'=>200],'headers'=>[],'body'=>'<rss><channel><item><guid>1</guid><title>News</title><link>https://example.com/n</link></item></channel></rss>']);
        $this->service = new SourceService($this->db, $tester);
    }

    public function testDuplicateAndConcurrentDuplicateAreRejected(): void
    {
        $this->db->duplicateId = 8;
        $this->expectException(DomainException::class);
        $this->service->save($this->input());
    }

    public function testUniqueConstraintStillStopsConcurrentInsert(): void
    {
        $this->db->failInsertAsDuplicate = true;
        $this->expectException(DomainException::class);
        $this->service->save($this->input());
    }

    public function testToggleAndTransactionalDeletePreserveProcessedSnapshots(): void
    {
        $this->db->row = ['id'=>3,'name'=>'Agency','feed_url'=>'https://example.com/feed','active'=>1];
        $this->assertFalse($this->service->toggle(3));
        $summary = $this->service->delete(3);
        $this->assertSame(2, $summary['pending']);
        $sql = implode("\n", $this->db->queries);
        $this->assertStringContainsString('START TRANSACTION', $sql);
        $this->assertStringContainsString('DELETE FROM wp_wpnb_feed_items', $sql);
        $this->assertStringContainsString('source_id=0', $sql);
        $this->assertStringContainsString('source_name=', $sql);
        $this->assertStringContainsString('COMMIT', $sql);
        $this->assertStringNotContainsString('wp_posts', $sql);
    }

    private function input(): array { return ['name'=>'Agency','feed_url'=>'https://EXAMPLE.com:443/feed/','allowed_domains'=>'example.com','category_id'=>2,'active'=>1]; }
}

final class FakeSourceDb
{
    public string $prefix='wp_'; public int $insert_id=10; public string $last_error=''; public int $duplicateId=0; public bool $failInsertAsDuplicate=false; public array $queries=[]; public ?array $row=null;
    public function prepare(string $sql, mixed ...$values): string { foreach($values as$value){$replacement=is_int($value)?(string)$value:"'".str_replace("'","''",(string)$value)."'";$sql=preg_replace('/%[ds]/',$replacement,$sql,1)??$sql;}return $sql; }
    public function get_var(string $sql): int { if(str_contains($sql,'canonical_hash'))return $this->duplicateId;if(str_contains($sql,"status IN ('new','review')"))return 2;if(str_contains($sql,"status NOT IN"))return 4;if(str_contains($sql,"status='draft_created'"))return 1;if(str_contains($sql,'locked_at'))return 0;return 0; }
    public function get_row(string $sql, mixed $format=null): ?array { return $this->row; }
    public function get_col(string $sql): array { return [11,12]; }
    public function insert(string $table,array $data): bool { if($this->failInsertAsDuplicate){$this->last_error='Duplicate entry';return false;}return true; }
    public function update(string $table,array $data,array $where): int { $this->queries[]='UPDATE '.$table.' '.json_encode($data);if(array_key_exists('active',$data))$this->row['active']=$data['active'];return 1; }
    public function delete(string $table,array $where,array $format=[]): int { $this->queries[]='DELETE '.$table;return 1; }
    public function query(string $sql): int { $this->queries[]=$sql;return 1; }
}
