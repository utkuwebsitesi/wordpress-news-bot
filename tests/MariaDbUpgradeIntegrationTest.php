<?php
declare(strict_types=1);
namespace WordPressNewsBot\Tests;

use DomainException;
use PHPUnit\Framework\TestCase;
use WordPressNewsBot\DatabaseHealth;
use WordPressNewsBot\DatabaseRepair;
use WordPressNewsBot\SourceService;

final class MariaDbUpgradeIntegrationTest extends TestCase
{
    private MariaDbWpdb $db;
    protected function setUp():void{$db=MariaDbWpdb::connectFromEnvironment();if(!$db)$this->markTestSkipped('WPNB_TEST_DB_DSN is not configured.');$this->db=$db;global$wpdb;$wpdb=$db;$db->reset();$GLOBALS['wpnb_test_options']=[];}
    protected function tearDown():void{if(isset($this->db))$this->db->reset();}

    public function testCleanInstallAndSecondRepairAreHealthyAndIdempotent():void{$first=(new DatabaseRepair($this->db))->run(true);$second=(new DatabaseRepair($this->db))->run(true);$this->assertSame('healthy',$first['status']);$this->assertGreaterThan(0,$first['changed']);$this->assertSame('healthy',$second['status']);$this->assertSame(0,$second['changed']);$this->assertSame(WPNB_SCHEMA_VERSION,get_option('wpnb_schema_version'));}

    public function test030UpgradeAddsMissingColumnsAndPreservesSource():void{$this->legacySources();$this->db->insert($this->db->prefix.'wpnb_sources',['name'=>'NTV','feed_url'=>'https://www.ntv.com.tr/tr/turkiye.rss','active'=>1,'category_id'=>0,'allowed_domains'=>'www.ntv.com.tr','created_at'=>'2026-01-01 00:00:00','updated_at'=>'2026-01-01 00:00:00']);update_option('wpnb_schema_version','1.2.0');$result=(new DatabaseRepair($this->db))->run(false);$this->assertSame('healthy',$result['status']);$row=$this->db->get_row('SELECT * FROM `'.$this->db->prefix.'wpnb_sources` LIMIT 1',ARRAY_A);$this->assertSame('NTV',$row['name']);$this->assertSame(hash('sha256','https://www.ntv.com.tr/tr/turkiye.rss'),$row['canonical_hash']);$this->assertArrayHasKey('last_checked_at',$row);}

    public function testFailedDuplicateMigrationStateIsRecoveredAndUniqueIndexCreated():void{$this->legacySources();foreach(['https://example.com/feed','HTTPS://example.com:443/feed/']as$i=>$url)$this->db->insert($this->db->prefix.'wpnb_sources',['name'=>'D'.($i+1),'feed_url'=>$url,'active'=>1,'category_id'=>0,'allowed_domains'=>'example.com','created_at'=>'2026-01-0'.($i+1).' 00:00:00','updated_at'=>'2026-01-01 00:00:00']);update_option('wpnb_schema_version','1.3.0');$result=(new DatabaseRepair($this->db))->run(false);$this->assertSame('healthy',$result['status']);$this->assertSame(1,(int)$this->db->get_var('SELECT COUNT(*) FROM `'.$this->db->prefix.'wpnb_sources`'));$this->assertNotNull($this->db->get_var("SHOW INDEX FROM `{$this->db->prefix}wpnb_sources` WHERE Key_name='canonical_hash_unique'"));}

    public function testEmptyAndHalfFinishedSchemasRepairButUnsafeTypeChangeIsBlocked():void{$this->legacySources();$empty=(new DatabaseRepair($this->db))->run(false);$this->assertSame('healthy',$empty['status']);$this->db->reset();$this->db->query('CREATE TABLE `'.$this->db->prefix."wpnb_sources` (`id` bigint unsigned NOT NULL AUTO_INCREMENT,`name` text NOT NULL,`feed_url` text NOT NULL,`created_at` datetime NOT NULL,`updated_at` datetime NOT NULL,PRIMARY KEY (`id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");$blocked=(new DatabaseRepair($this->db))->run(false);$this->assertSame('repair_required',$blocked['status']);$codes=array_column($blocked['issues'],'code');$this->assertContains('column_type_mismatch',$codes);}

    public function testVerifiedNtvLikeTokenInsertsOnceAndDuplicateGetsSpecificException():void{(new DatabaseRepair($this->db))->run(true);$service=new SourceService($this->db);$input=['name'=>'NTV','feed_url'=>'https://www.ntv.com.tr/tr/turkiye.rss','allowed_domains'=>'www.ntv.com.tr','category_id'=>4,'active'=>1];$verified=['test_id'=>'0e4828f44c90f0af','http_status'=>200,'feed_type'=>'Atom','item_count'=>20,'duration_ms'=>162];$id=$service->save($input,0,$verified);$this->assertGreaterThan(0,$id);$this->assertSame(1,(int)$this->db->get_var('SELECT COUNT(*) FROM `'.$this->db->prefix.'wpnb_sources`'));$this->expectException(DomainException::class);$service->save($input,0,$verified);}

    private function legacySources():void{$this->db->query('CREATE TABLE `'.$this->db->prefix."wpnb_sources` (`id` bigint unsigned NOT NULL AUTO_INCREMENT,`name` varchar(190) NOT NULL,`feed_url` text NOT NULL,`active` tinyint NOT NULL DEFAULT 1,`category_id` bigint unsigned NOT NULL DEFAULT 0,`allowed_domains` text NULL,`created_at` datetime NOT NULL,`updated_at` datetime NOT NULL,PRIMARY KEY (`id`),KEY `active` (`active`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");}
}
