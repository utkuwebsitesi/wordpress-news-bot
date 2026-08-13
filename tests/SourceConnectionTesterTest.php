<?php
declare(strict_types=1);
namespace WordPressNewsBot\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use WordPressNewsBot\SourceConnectionTester;
use WordPressNewsBot\SourceTestException;

final class SourceConnectionTesterTest extends TestCase
{
    private const RSS='<rss version="2.0"><channel><item><guid>1</guid><title>Anonymous</title><link>https://example.com/n</link><pubDate>Wed, 01 Jan 2025 10:00:00 GMT</pubDate></item></channel></rss>';
    private const ATOM='<feed xmlns="http://www.w3.org/2005/Atom"><entry><id>1</id><title>Anonymous</title><link href="https://example.com/n"/><updated>2025-01-01T10:00:00Z</updated></entry></feed>';
    private const RDF='<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"><item><title>Anonymous</title><link>https://example.com/n</link></item></rdf:RDF>';

    public function testNtvLikeApplicationXmlAtomFixture():void{$tester=$this->tester($this->response(200,self::ATOM,'application/xml'));$result=$tester->test('https://www.example.com/turkiye.rss',['example.com']);$this->assertSame('Atom',$result['feed_type']);$this->assertSame(1,$result['item_count']);$this->assertSame('2025-01-01T10:00:00Z',$result['last_item_date']);$this->assertSame('www.example.com',$result['final_host']);$this->assertNotEmpty($result['test_id']);$this->assertArrayNotHasKey('items',$result);$fetched=$tester->fetch('https://www.example.com/turkiye.rss',['example.com']);$this->assertCount(1,$fetched['items']);$this->assertSame('Anonymous',$fetched['items'][0]['title']);}
    public function testRedirectBetweenWwwAndBareDomainIsRevalidated():void{$calls=0;$transport=function()use(&$calls){$calls++;$response=$calls===1?$this->response(301,'','text/plain',['location'=>'https://example.com/feed']):$this->response(200,self::RSS,'text/xml; charset=utf-8');return$response();};$result=$this->tester($transport)->test('https://www.example.com/feed',['example.com']);$this->assertSame(['example.com'],$result['redirect_hosts']);$this->assertSame('RSS 2.0',$result['feed_type']);}
    #[DataProvider('validFeeds')]
    public function testSupportedContentTypesAndFeedFormats(string$body,string$type,string$expected):void{$result=$this->tester($this->response(200,$body,$type))->test('https://feeds.example.com/feed',['example.com']);$this->assertSame($expected,$result['feed_type']);$this->assertSame(1,$result['item_count']);}
    public static function validFeeds():array{return[[self::RSS,'application/rss+xml','RSS 2.0'],[self::RSS,'text/xml; charset=utf-8','RSS 2.0'],[self::RDF,'application/xml','RSS 1.0/RDF'],[self::ATOM,'application/atom+xml','Atom'],[self::ATOM,'application/octet-stream','Atom']];}
    public function testHtmlBotBlockIsRejected():void{$this->assertFailure('content_type_invalid',$this->response(200,'<html><title>Blocked</title></html>','text/html'));}
    #[DataProvider('badStatuses')]
    public function testInvalidHttpStatuses(int$status):void{$this->assertFailure('http_status_invalid',$this->response($status,'','text/plain'));}
    public static function badStatuses():array{return[[403],[404],[429],[500],[503]];}
    public function testEmptyBodyAndBrokenXmlHaveDistinctCodes():void{$this->assertFailure('body_empty',$this->response(200,'','application/xml'));$this->assertFailure('xml_invalid',$this->response(200,'<rss>','application/xml'));}
    public function testOversizedResponseIsRejectedBeforeXmlParsing():void{$this->assertFailure('body_too_large',$this->response(200,str_repeat('x',2*1024*1024),'application/xml'));}
    public function testDnsAndHttpFailuresHaveDistinctCodes():void{try{(new SourceConnectionTester($this->response(200,self::RSS,'application/xml'),static fn()=>[]))->test('https://feeds.example.com/feed',['example.com']);$this->fail();}catch(SourceTestException$e){$this->assertSame('dns_failed',$e->resultCode);}$this->assertFailure('http_failed',static fn()=>new \WP_Error('timeout'));}
    public function testInvalidUrlAndHostHaveSafeCodes():void{try{$this->tester($this->response(200,self::RSS,'application/xml'))->test('not-a-url',[]);$this->fail();}catch(SourceTestException$e){$this->assertSame('url_invalid',$e->resultCode);}try{$this->tester($this->response(200,self::RSS,'application/xml'))->test('https://evil.example/feed',['example.com']);$this->fail();}catch(SourceTestException$e){$this->assertSame('host_invalid',$e->resultCode);}}
    public function testAllIpv4AndIpv6ResultsAreChecked():void{$resolver=static fn()=>['93.184.216.34','2606:4700:4700::1111','169.254.169.254'];$tester=new SourceConnectionTester($this->response(200,self::RSS,'application/xml'),$resolver);$this->expectException(SourceTestException::class);try{$tester->test('https://feeds.example.com/feed',['example.com']);}catch(SourceTestException$e){$this->assertSame('ip_blocked',$e->resultCode);throw$e;}}
    public function testRedirectToPrivateTargetIsRejected():void{$transport=$this->response(302,'','text/plain',['location'=>'https://metadata.example.com/feed']);$resolver=static fn(string$host)=>$host==='metadata.example.com'?['169.254.169.254']:['93.184.216.34'];try{(new SourceConnectionTester($transport,$resolver))->test('https://feeds.example.com/feed',['example.com']);$this->fail();}catch(SourceTestException$e){$this->assertSame('redirect_blocked',$e->resultCode);}}
    private function assertFailure(string$code,callable$transport):void{try{$this->tester($transport)->test('https://feeds.example.com/feed',['example.com']);$this->fail();}catch(SourceTestException$e){$this->assertSame($code,$e->resultCode);$this->assertMatchesRegularExpression('/^[a-f0-9]{16}$/',$e->testId);}}
    private function tester(callable$transport):SourceConnectionTester{return new SourceConnectionTester($transport,static fn()=>['93.184.216.34','2606:4700:4700::1111']);}
    private function response(int$status,string$body,string$type,array$headers=[]):callable{return static fn()=>['response'=>['code'=>$status],'headers'=>array_merge(['content-type'=>$type],$headers),'body'=>$body];}
}
