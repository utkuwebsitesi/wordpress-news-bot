<?php
declare(strict_types=1);
namespace WordPressNewsBot\Tests;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use WordPressNewsBot\SourceMigration;
use WordPressNewsBot\SourceRecoveryRequired;

final class SourceMigrationTest extends TestCase
{
    private SqliteWpdb $db;
    protected function setUp():void{global$wpdb;$wpdb=$this->db=new SqliteWpdb();$this->seedDuplicates();}

    public function testRealDatabaseMergeKeepsOldestPrimaryKeyMovesRelationsAndIsIdempotent():void{
        $migration=new SourceMigration($this->db);$first=$migration->run('1.2.0');$second=$migration->run('1.4.0');
        $this->assertSame(['groups'=>1,'sources_merged'=>1,'items_merged'=>1,'recovered'=>0],$first);$this->assertSame(1,(int)$this->db->get_var('SELECT COUNT(*) FROM wp_wpnb_sources'));$this->assertSame(1,(int)$this->db->get_var('SELECT COUNT(*) FROM wp_wpnb_sources WHERE id=1'));$this->assertSame(0,(int)$this->db->get_var('SELECT COUNT(*) FROM wp_wpnb_sources WHERE id=2'));$this->assertSame(10,(int)$this->db->get_var('SELECT feed_item_id FROM wp_wpnb_jobs WHERE id=100'));$this->assertSame(10,(int)$this->db->get_var('SELECT feed_item_id FROM wp_wpnb_ai_generations WHERE id=200'));$this->assertSame(['groups'=>0,'sources_merged'=>0,'items_merged'=>0,'recovered'=>0],$second);
    }
    public function testUnexpectedDeleteCountRollsBackAndRestoresSnapshot():void{$this->db->forcedSourceDeleteCount=2;$this->expectException(RuntimeException::class);try{(new SourceMigration($this->db))->run('1.2.0');}finally{$this->assertSame(2,(int)$this->db->get_var('SELECT COUNT(*) FROM wp_wpnb_sources'));$this->assertSame(1,(int)$this->db->get_var('SELECT COUNT(*) FROM wp_wpnb_sources WHERE id=1'));}}
    public function testRelationMoveFailureRollsBackAndRestoresSnapshot():void{$this->db->failRelationMove=true;$this->expectException(RuntimeException::class);try{(new SourceMigration($this->db))->run('1.2.0');}finally{$this->assertSame(2,(int)$this->db->get_var('SELECT COUNT(*) FROM wp_wpnb_sources'));$this->assertSame(20,(int)$this->db->get_var('SELECT feed_item_id FROM wp_wpnb_jobs WHERE id=100'));}}
    public function testUniqueIndexFailureRestoresPreMigrationRows():void{$this->db->failIndex=true;$this->expectException(RuntimeException::class);try{(new SourceMigration($this->db))->run('1.2.0');}finally{$this->assertSame(2,(int)$this->db->get_var('SELECT COUNT(*) FROM wp_wpnb_sources'));$this->assertSame(20,(int)$this->db->get_var('SELECT feed_item_id FROM wp_wpnb_jobs WHERE id=100'));}}
    public function testIncompleteJournalRestoresSnapshotBeforeRerun():void{$snapshot=['sources'=>$this->db->get_results('SELECT * FROM wp_wpnb_sources'),'feed_items'=>$this->db->get_results('SELECT * FROM wp_wpnb_feed_items'),'jobs'=>$this->db->get_results('SELECT * FROM wp_wpnb_jobs'),'ai_generations'=>$this->db->get_results('SELECT * FROM wp_wpnb_ai_generations'),'had_unique_index'=>false];$this->db->seed('wp_wpnb_migration_journal',['migration'=>'sources-1.4.0','status'=>'started','source_count'=>2,'snapshot_json'=>json_encode($snapshot),'report_json'=>null,'created_at'=>'2025-01-01','completed_at'=>null]);$this->db->query('DELETE FROM wp_wpnb_sources');$report=(new SourceMigration($this->db))->run('1.3.0');$this->assertSame(1,$report['recovered']);$this->assertSame(1,(int)$this->db->get_var('SELECT COUNT(*) FROM wp_wpnb_sources'));$this->assertSame(1,(int)$this->db->get_var("SELECT COUNT(*) FROM wp_wpnb_migration_journal WHERE status='restored'"));}
    public function testEmpty031WithoutSnapshotRequiresManualRecovery():void{$this->db->query('DELETE FROM wp_wpnb_jobs');$this->db->query('DELETE FROM wp_wpnb_ai_generations');$this->db->query('DELETE FROM wp_wpnb_feed_items');$this->db->query('DELETE FROM wp_wpnb_sources');$this->expectException(SourceRecoveryRequired::class);(new SourceMigration($this->db))->run('1.3.0');}
    public function testFreshEmptyInstallCreatesUniqueIndexWithoutRecoveryWarning():void{$this->db=new SqliteWpdb();global$wpdb;$wpdb=$this->db;$report=(new SourceMigration($this->db))->run('');$this->assertSame(['groups'=>0,'sources_merged'=>0,'items_merged'=>0,'recovered'=>0],$report);$this->assertSame('canonical_hash_unique',$this->db->get_var("SHOW INDEX FROM wp_wpnb_sources WHERE Key_name='canonical_hash_unique'"));}
    private function seedDuplicates():void{$base=['name'=>'AA','active'=>1,'category_id'=>0,'allowed_domains'=>'aa.com.tr','updated_at'=>'2025-01-01'];$this->db->seed('wp_wpnb_sources',['id'=>1,'feed_url'=>'https://www.aa.com.tr/feed','canonical_hash'=>'']+$base+['created_at'=>'2025-01-01']);$this->db->seed('wp_wpnb_sources',['id'=>2,'feed_url'=>'HTTPS://www.aa.com.tr:443/feed/','canonical_hash'=>'']+$base+['created_at'=>'2025-02-01']);foreach([10=>1,20=>2]as$id=>$source)$this->db->seed('wp_wpnb_feed_items',['id'=>$id,'source_id'=>$source,'guid'=>'same','normalized_url'=>'https://aa/item','content_hash'=>'hash','source_url'=>'https://aa/item','title'=>'Item','status'=>'new','created_at'=>'2025-01-01','updated_at'=>'2025-01-01']);$this->db->seed('wp_wpnb_jobs',['id'=>100,'feed_item_id'=>20,'status'=>'queued']);$this->db->seed('wp_wpnb_ai_generations',['id'=>200,'feed_item_id'=>20]);}
}
