<?php
declare(strict_types=1);
namespace WordPressNewsBot\Tests;

use PHPUnit\Framework\TestCase;
use WordPressNewsBot\SourceMigration;

final class SourceMigrationTest extends TestCase
{
    public function testDuplicateMigrationMovesRelationsKeepsOldestAndIsIdempotent(): void
    {
        global $wpdb;
        $wpdb = new FakeMigrationDb();
        $migration = new SourceMigration($wpdb);
        $first = $migration->run();
        $second = $migration->run();

        $this->assertSame(['groups'=>1,'sources_merged'=>1,'items_merged'=>1], $first);
        $this->assertSame(['groups'=>0,'sources_merged'=>0,'items_merged'=>0], $second);
        $this->assertSame([1], array_keys($wpdb->sources));
        $sql = implode("\n", $wpdb->queries);
        $this->assertStringContainsString('UPDATE wp_wpnb_jobs SET feed_item_id=10 WHERE feed_item_id=20', $sql);
        $this->assertStringContainsString('UPDATE wp_wpnb_ai_generations SET feed_item_id=10 WHERE feed_item_id=20', $sql);
        $this->assertStringContainsString('ADD UNIQUE KEY canonical_hash_unique', $sql);
        $this->assertSame(2, $wpdb->logCount);
    }
}

final class FakeMigrationDb
{
    public string $prefix='wp_'; public bool $index=false; public int $logCount=0; public array $queries=[];
    public array $sources=[1=>['id'=>1,'feed_url'=>'https://example.com/feed','created_at'=>'2025-01-01 00:00:00'],2=>['id'=>2,'feed_url'=>'https://EXAMPLE.com:443/feed/','created_at'=>'2025-02-01 00:00:00']];
    public function prepare(string $sql,mixed ...$values):string{foreach($values as$value){$replacement=is_int($value)?(string)$value:"'".str_replace("'","''",(string)$value)."'";$sql=preg_replace('/%[ds]/',$replacement,$sql,1)??$sql;}return$sql;}
    public function get_results(string$sql,mixed$format=null):array{if(str_contains($sql,'FROM wp_wpnb_sources'))return array_values($this->sources);if(str_contains($sql,'FROM wp_wpnb_feed_items')&&str_contains($sql,'source_id=2'))return[['id'=>20,'source_id'=>2,'guid'=>'same-guid','normalized_url'=>'https://example.com/item','content_hash'=>'hash']];return[];}
    public function get_var(string$sql):mixed{if(str_starts_with($sql,'SHOW INDEX'))return$this->index?'canonical_hash_unique':null;if(str_contains($sql,'SELECT id FROM wp_wpnb_feed_items'))return 10;return null;}
    public function update(string$table,array$data,array$where):int{if($table==='wp_wpnb_sources'&&isset($this->sources[(int)$where['id']]))$this->sources[(int)$where['id']]=array_merge($this->sources[(int)$where['id']],$data);return 1;}
    public function delete(string$table,array$where,array$format=[]):int{if($table==='wp_wpnb_sources')unset($this->sources[(int)$where['id']]);$this->queries[]='DELETE '.$table.' '.(int)$where['id'];return 1;}
    public function query(string$sql):int{$this->queries[]=$sql;if(str_contains($sql,'ADD UNIQUE KEY'))$this->index=true;return 1;}
    public function insert(string$table,array$data):bool{if($table==='wp_wpnb_logs')$this->logCount++;return true;}
}
