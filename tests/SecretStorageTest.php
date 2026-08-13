<?php
declare(strict_types=1);
namespace WordPressNewsBot\Tests;
use PHPUnit\Framework\TestCase; use WordPressNewsBot\SecretStorage;
final class SecretStorageTest extends TestCase
{
    private function storage(string $algorithm='sodium', string $auth='auth', string $secure='secure'): SecretStorage { return new SecretStorage($auth,$secure,$algorithm); }
    public function testEncryptsAndDecryptsWithoutPlaintext(): void { $secret='future-format-key_value';$payload=$this->storage()->encrypt($secret);$this->assertSame($secret,$this->storage()->decrypt($payload));$this->assertStringNotContainsString($secret,json_encode($payload)); }
    public function testRandomNonceProducesDifferentCiphertext(): void { $a=$this->storage()->encrypt('same-secret');$b=$this->storage()->encrypt('same-secret');$this->assertNotSame($a['ciphertext'],$b['ciphertext']); }
    public function testSodiumFlow(): void { if(!function_exists('sodium_crypto_secretbox'))$this->markTestSkipped('Sodium unavailable');$p=$this->storage('sodium')->encrypt('secret');$this->assertSame('sodium',$p['algorithm']);$this->assertSame('secret',$this->storage('sodium')->decrypt($p)); }
    public function testOpenSslFallbackFlow(): void { if(!function_exists('openssl_encrypt'))$this->markTestSkipped('OpenSSL unavailable');$p=$this->storage('openssl')->encrypt('secret');$this->assertSame('openssl-aes-256-gcm',$p['algorithm']);$this->assertSame('secret',$this->storage('openssl')->decrypt($p)); }
    public function testRejectsStorageWithoutEncryption(): void { $this->expectException(\RuntimeException::class);$this->storage('none')->encrypt('secret'); }
    public function testSaltChangeCannotDecrypt(): void { $p=$this->storage('sodium','a','b')->encrypt('secret');$this->expectException(\RuntimeException::class);$this->storage('sodium','changed','b')->decrypt($p); }
    public function testRejectsCorruptPayload(): void { $this->expectException(\RuntimeException::class);$this->storage()->decrypt(['version'=>1,'algorithm'=>'sodium','ciphertext'=>'broken']); }
    public function testStoreUsesEncryptedPayloadAndDeleteRemovesIt(): void { $saved=null;$storage=new SecretStorage('a','b','sodium',fn()=>$saved, function(array$p)use(&$saved){$saved=$p;return true;},function()use(&$saved){$saved=null;});$storage->store('plain-secret');$this->assertIsArray($saved);$this->assertStringNotContainsString('plain-secret',json_encode($saved));$storage->delete();$this->assertNull($saved); }
    public function testRetrieveConvertsCorruptionToFriendlyError():void{$payload=['version'=>1,'algorithm'=>'sodium','nonce'=>'bad','ciphertext'=>'bad'];$storage=new SecretStorage('a','b','sodium',fn()=>$payload);$this->expectExceptionMessage('could not be decrypted');$storage->retrieve();}
    public function testReplacingKeyProducesNewEncryptedPayload():void{$saved=null;$storage=new SecretStorage('a','b','sodium',function()use(&$saved){return$saved;},function(array$p)use(&$saved){$saved=$p;return true;});$storage->store('first');$first=$saved;$storage->store('second');$this->assertNotSame($first['ciphertext'],$saved['ciphertext']);$this->assertSame('second',$storage->retrieve());}
}
