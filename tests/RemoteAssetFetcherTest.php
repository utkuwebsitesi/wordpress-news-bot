<?php
declare(strict_types=1);
namespace WordPressNewsBot\Tests;
use PHPUnit\Framework\TestCase;use WordPressNewsBot\RemoteAssetException;use WordPressNewsBot\RemoteAssetFetcher;
final class RemoteAssetFetcherTest extends TestCase
{
    private function response(int$status,string$body='',array$headers=[]):array{return['response'=>['code'=>$status],'body'=>$body,'headers'=>$headers];}
    public function testFetchesPublicHttpsAssetWithinLimit():void{$fetcher=new RemoteAssetFetcher(fn()=>$this->response(200,'abc',['content-type'=>'image/png','content-length'=>'3']),fn()=>['203.0.113.10']);$result=$fetcher->fetch('https://cdn.example.com/a.png',[],10,['image/png']);$this->assertSame('abc',$result['body']);$this->assertSame('image/png',$result['content_type']);}
    public function testBlocksPrivateIpBeforeTransport():void{$called=false;$fetcher=new RemoteAssetFetcher(function()use(&$called){$called=true;return[];},fn()=>['127.0.0.1']);try{$fetcher->fetch('https://internal.example/a.jpg',[],1024,['image/jpeg']);$this->fail('Private IP accepted.');}catch(RemoteAssetException$e){$this->assertSame('ip_blocked',$e->resultCode);$this->assertFalse($called);}}
    public function testBlocksRedirectToUnapprovedHost():void{$fetcher=new RemoteAssetFetcher(fn()=>$this->response(302,'',['location'=>'https://other.example/image.jpg']),fn()=>['93.184.216.34']);try{$fetcher->fetch('https://cdn.example/image.jpg',[],1024,['image/jpeg']);$this->fail('Unapproved redirect accepted.');}catch(RemoteAssetException$e){$this->assertSame('redirect_blocked',$e->resultCode);}}
    public function testRejectsOversizedBodyAndInvalidHeaderMime():void{foreach([[$this->response(200,str_repeat('a',11),['content-type'=>'image/jpeg']),10,'body_too_large'],[$this->response(200,'abc',['content-type'=>'text/html']),10,'content_type_invalid']]as[$response,$max,$code]){$fetcher=new RemoteAssetFetcher(fn()=>$response,fn()=>['203.0.113.30']);try{$fetcher->fetch('https://cdn.example/a.jpg',[],$max,['image/jpeg']);$this->fail('Unsafe response accepted.');}catch(RemoteAssetException$e){$this->assertSame($code,$e->resultCode);}}}
}
