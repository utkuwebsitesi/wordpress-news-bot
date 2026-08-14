<?php
declare(strict_types=1);
namespace WordPressNewsBot\Tests;

use PHPUnit\Framework\TestCase;
use WordPressNewsBot\DatabaseSchema;

final class DatabaseSchemaTest extends TestCase
{
    public function testSourceInsertSchemaAndCanonicalUniqueIndexAreExplicit():void{$source=DatabaseSchema::tables()['sources'];foreach(['id','name','feed_url','canonical_hash','allowed_domains','category_id','active','last_checked_at','last_result','last_error','created_at','updated_at']as$column)$this->assertArrayHasKey($column,$source['columns']);$this->assertSame('tinyint(1) NOT NULL DEFAULT 1',$source['columns']['show_attribution']);$this->assertSame(['unique'=>true,'columns'=>['canonical_hash']],$source['indexes']['canonical_hash_unique']);$this->assertArrayNotHasKey('canonical_url',$source['columns']);$this->assertArrayNotHasKey('allowed_host',$source['columns']);$this->assertArrayNotHasKey('is_active',$source['columns']);}
    public function testRepairSchemaContainsEveryProductionTable():void{$this->assertSame(['sources','feed_items','jobs','ai_generations','logs','daily_usage','migration_journal'],array_keys(DatabaseSchema::tables()));}
    public function testFeedItemsPersistSourceCategoryAndWordPressPostRelationship():void{$table=DatabaseSchema::tables()['feed_items'];$this->assertArrayHasKey('source_category',$table['columns']);$this->assertArrayHasKey('wordpress_category_id',$table['columns']);$this->assertSame('bigint(20) unsigned NOT NULL DEFAULT 0',$table['columns']['wordpress_post_id']);$this->assertSame(['unique'=>false,'columns'=>['wordpress_post_id']],$table['indexes']['wordpress_post_id']);}
    public function testCreateSqlAlwaysForcesInnoDbAndKeepsWordPressCharsetCollation():void{global$wpdb;$wpdb=(object)['prefix'=>'wp_'];$sql=DatabaseSchema::createSql('sources','DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');$this->assertStringContainsString('ENGINE=InnoDB',$sql);$this->assertStringContainsString('DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci',$sql);}
    public function testCanonicalSchemaDerivesWriteFormatsForEveryColumnType():void{$this->assertSame(['%s','%d','%s'],DatabaseSchema::formatsFor('sources',['name'=>'A','active'=>1,'updated_at'=>'2025-01-01 00:00:00']));$this->assertSame(['%d','%d','%d'],DatabaseSchema::formatsFor('sources',['import_images'=>1,'draft_without_image'=>1,'use_og_image'=>0]));$this->assertSame(['%s','%d','%s'],DatabaseSchema::formatsFor('feed_items',['image_status'=>'ready','image_attachment_id'=>12,'image_hash'=>str_repeat('a',64)]));$this->assertSame(['%d','%f','%s'],DatabaseSchema::formatsFor('ai_generations',['feed_item_id'=>1,'estimated_cost'=>0.1,'created_at'=>'2025-01-01 00:00:00']));$this->expectException(\InvalidArgumentException::class);DatabaseSchema::formatsFor('sources',['not_a_column'=>'x']);}
}
