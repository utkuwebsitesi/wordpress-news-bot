<?php
declare(strict_types=1);
namespace WordPressNewsBot\Tests;

use PHPUnit\Framework\TestCase;
use WordPressNewsBot\DatabaseSchema;

final class DatabaseSchemaTest extends TestCase
{
    public function testSourceInsertSchemaAndCanonicalUniqueIndexAreExplicit():void{$source=DatabaseSchema::tables()['sources'];foreach(['id','name','feed_url','canonical_hash','allowed_domains','category_id','active','last_checked_at','last_result','last_error','created_at','updated_at']as$column)$this->assertArrayHasKey($column,$source['columns']);$this->assertSame(['unique'=>true,'columns'=>['canonical_hash']],$source['indexes']['canonical_hash_unique']);$this->assertArrayNotHasKey('canonical_url',$source['columns']);$this->assertArrayNotHasKey('allowed_host',$source['columns']);$this->assertArrayNotHasKey('is_active',$source['columns']);}
    public function testRepairSchemaContainsEveryProductionTable():void{$this->assertSame(['sources','feed_items','jobs','ai_generations','logs','daily_usage','migration_journal'],array_keys(DatabaseSchema::tables()));}
    public function testCreateSqlAlwaysForcesInnoDbAndKeepsWordPressCharsetCollation():void{global$wpdb;$wpdb=(object)['prefix'=>'wp_'];$sql=DatabaseSchema::createSql('sources','DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');$this->assertStringContainsString('ENGINE=InnoDB',$sql);$this->assertStringContainsString('DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci',$sql);}
}
