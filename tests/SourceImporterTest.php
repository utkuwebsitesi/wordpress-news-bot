<?php
declare(strict_types=1);
namespace WordPressNewsBot\Tests;

use PHPUnit\Framework\TestCase;
use WordPressNewsBot\SourceConnectionTester;
use WordPressNewsBot\SourceImporter;
use WordPressNewsBot\ManualImportService;
use WordPressNewsBot\SourceTestException;

final class SourceImporterTest extends TestCase
{
    protected function setUp():void{$GLOBALS['wpnb_test_options']=[];}
    public function testSecureFetcherDetailedSummaryCategoryAndCanonicalFormatsAreUsed():void
    {
        $xml='<rss><channel><item><guid>1</guid><title>One</title><link>https://feed.example/items/1</link></item><item><guid>2</guid><title>Two</title><link>https://feed.example/items/2</link></item></channel></rss>';
        $tester=new SourceConnectionTester(static fn()=>['response'=>['code'=>200],'headers'=>['content-type'=>'application/rss+xml'],'body'=>$xml],static fn()=>['93.184.216.34']);$db=new ImportWpdb(1);global$wpdb;$wpdb=$db;
        $summary=(new SourceImporter($tester,$db))->importDetailed(7);$this->assertSame(['read'=>2,'new'=>2,'duplicate'=>0,'invalid'=>0,'failed'=>0],array_intersect_key($summary,array_flip(['read','new','duplicate','invalid','failed'])));$this->assertCount(2,$db->feedInserts);$this->assertCount(16,$db->feedFormats);$this->assertSame(0,$db->feedInserts[0]['wordpress_category_id']);$this->assertSame('One',$db->feedInserts[0]['title']);$this->assertSame('2 read, 2 new, 0 duplicate.',$db->sourceUpdate['last_result']);
    }
    public function testExistingAtomicImportLockStopsConcurrentFetch():void{$db=new ImportWpdb(10);global$wpdb;$wpdb=$db;add_option('wpnb_import_lock_7',time());$this->expectException(\RuntimeException::class);(new SourceImporter(null,$db))->import(7);}
    public function testManualFailureIsPersistedWithoutLosingDiagnosticIdentity():void
    {
        $tester=new SourceConnectionTester(static fn()=>['response'=>['code'=>503],'headers'=>['content-type'=>'text/plain'],'body'=>''],static fn()=>['93.184.216.34']);$db=new ImportWpdb(10);global$wpdb;$wpdb=$db;$service=new ManualImportService($db,new SourceImporter($tester,$db));
        try{$service->importSource(7);$this->fail('Expected the secure fetch failure.');}catch(SourceTestException$e){$this->assertMatchesRegularExpression('/^[a-f0-9]{16}$/',$e->testId);}
        $this->assertSame('Failed',$db->sourceUpdate['last_result']);$this->assertArrayNotHasKey('wpnb_import_lock_7',$GLOBALS['wpnb_test_options']);
    }
}

final class ImportWpdb
{
    public string$prefix='wp_';public string$last_error='';public int$last_errno=0;public int$insert_id=0;public array$feedInserts=[];public array$feedFormats=[];public array$sourceUpdate=[];
    public function __construct(private int$quota){}
    public function prepare(string$sql,mixed...$args):string{return$sql.' '.json_encode($args);}
    public function get_row(string$sql,mixed$output=null):array{return['id'=>7,'active'=>1,'name'=>'Fixture','feed_url'=>'https://feed.example/rss','allowed_domains'=>'feed.example','daily_quota'=>$this->quota];}
    public function get_var(string$sql):mixed{return str_contains($sql,'COUNT(*)')?0:null;}
    public function insert(string$table,array$data,array$formats=[]):int{if(str_ends_with($table,'feed_items')){$this->feedInserts[]=$data;$this->feedFormats=$formats;$this->insert_id=count($this->feedInserts);}return 1;}
    public function update(string$table,array$data,array$where,array$formats=[],array$whereFormats=[]):int{$this->sourceUpdate=$data;return 1;}
}
