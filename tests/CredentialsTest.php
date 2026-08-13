<?php
declare(strict_types=1);
namespace WordPressNewsBot\Tests;
use PHPUnit\Framework\Attributes\RunInSeparateProcess; use PHPUnit\Framework\TestCase; use WordPressNewsBot\Credentials; use WordPressNewsBot\SecretStorage;
final class CredentialsTest extends TestCase
{
    public function testWorksWithoutConstantUsingEncryptedStorage():void{$payload=null;$storage=new SecretStorage('a','b','sodium',function()use(&$payload){return$payload;},function(array$p)use(&$payload){$payload=$p;return true;});$storage->store('stored-key');$this->assertSame('stored-key',Credentials::openAiKey($storage));}
    #[RunInSeparateProcess] public function testOptionalConstantOverridesStorage():void{define('WPNB_OPENAI_API_KEY','constant-key');$this->assertSame('constant-key',Credentials::openAiKey(new SecretStorage('a','b','none')));}
}
