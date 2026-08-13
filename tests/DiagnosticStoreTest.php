<?php
declare(strict_types=1);
namespace WordPressNewsBot\Tests;

use PHPUnit\Framework\TestCase;
use WordPressNewsBot\DiagnosticStore;

final class DiagnosticStoreTest extends TestCase
{
    protected function setUp():void{$GLOBALS['wpnb_test_options']=[];}
    public function testLookupByTestIdContainsOnlyAllowlistedSafeFields():void
    {
        DiagnosticStore::record('0e4828f44c90f0af','database','db_column_missing','source_insert',['db_errno'=>1054,'affected_rows'=>0,'schema_fingerprint'=>'abc123','suggestion'=>'Repair schema','sql'=>'SELECT secret','api_key'=>'sk-secret','raw_feed'=>'private']);
        $record=DiagnosticStore::find('0e4828f44c90f0af');$this->assertNotNull($record);$this->assertSame('db_column_missing',$record['db_code']);$this->assertSame('source_insert',$record['operation']);$encoded=json_encode($record);$this->assertStringNotContainsString('SELECT',$encoded);$this->assertStringNotContainsString('sk-secret',$encoded);$this->assertStringNotContainsString('private',$encoded);
    }
}
