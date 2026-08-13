<?php
declare(strict_types=1);
namespace WordPressNewsBot\Tests;
use PHPUnit\Framework\TestCase; use WordPressNewsBot\AiProvider; use WordPressNewsBot\ConnectionService; use WordPressNewsBot\SecretStorage;
final class ConnectionServiceTest extends TestCase
{
    public function testSuccessfulTestStoresKey(): void { $saved=null;$storage=new SecretStorage('a','b','sodium',fn()=>$saved,function(array$p)use(&$saved){$saved=$p;return true;});$factory=fn()=>new FakeConnectionProvider(true);$result=(new ConnectionService($storage,$factory))->saveAndTest('secret-key','model');$this->assertTrue($result['success']);$this->assertIsArray($saved);$this->assertStringNotContainsString('secret-key',json_encode($saved)); }
    public function testFailedTestDoesNotStoreKey(): void { $writes=0;$storage=new SecretStorage('a','b','sodium',fn()=>null,function()use(&$writes){$writes++;return true;});$this->expectException(\RuntimeException::class);try{(new ConnectionService($storage,fn()=>new FakeConnectionProvider(false)))->saveAndTest('secret-key','model');}finally{$this->assertSame(0,$writes);} }
}
final class FakeConnectionProvider implements AiProvider { public function __construct(private bool$success){} public function model():string{return'model';} public function generate(array$item):array{return[];} public function testConnection():array{if(!$this->success)throw new \RuntimeException('safe failure');return['success'=>true,'model'=>'model','duration_ms'=>1,'request_id'=>'req_safe','http_class'=>2];} }
