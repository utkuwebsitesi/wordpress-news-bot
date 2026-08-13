<?php
declare(strict_types=1);
namespace WordPressNewsBot\Tests;

use PHPUnit\Framework\TestCase;
use WordPressNewsBot\SourceConnectionTester;
use WordPressNewsBot\SourceService;

final class SourceSaveIntegrationTest extends TestCase
{
    public function testNtvLikeAtomPassesHttpParserAndDatabaseStages():void
    {
        global$wpdb;$wpdb=$db=new SqliteWpdb();
        $body='<feed xmlns="http://www.w3.org/2005/Atom"><entry><id>anon-1</id><title>Anonymous item</title><link href="https://example.com/item"/><updated>2025-01-01T10:00:00Z</updated></entry></feed>';
        $transport=static fn()=>['response'=>['code'=>200],'headers'=>['content-type'=>'application/xml; charset=utf-8'],'body'=>$body];
        $tester=new SourceConnectionTester($transport,static fn()=>['93.184.216.34','2606:4700:4700::1111']);
        $service=new SourceService($db,$tester);
        $id=$service->save(['name'=>'Test source','feed_url'=>'https://www.example.com/turkiye.rss','allowed_domains'=>'example.com','category_id'=>4,'active'=>1]);
        $row=$db->get_row($db->prepare('SELECT * FROM wp_wpnb_sources WHERE id=%d',$id),ARRAY_A);
        $this->assertSame('Test source',$row['name']);$this->assertSame('https://www.example.com/turkiye.rss',$row['feed_url']);$this->assertSame(4,(int)$row['category_id']);$this->assertStringContainsString('Atom',$row['last_result']);
    }
}
