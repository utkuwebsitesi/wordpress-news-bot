<?php
declare(strict_types=1);
namespace WordPressNewsBot\Tests;
use PHPUnit\Framework\TestCase;use WordPressNewsBot\ImageValidator;use WordPressNewsBot\RemoteAssetException;
final class ImageValidatorTest extends TestCase
{
    private function png():string{return(string)base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',true);}
    public function testAcceptsRealAllowedImageAndReturnsHash():void{$result=(new ImageValidator())->validate($this->png(),'image/png',1,1);$this->assertSame('image/png',$result['mime']);$this->assertSame('png',$result['extension']);$this->assertSame(1,$result['width']);$this->assertSame(1,$result['height']);$this->assertSame(hash('sha256',$this->png()),$result['hash']);}
    public function testRejectsHtmlDeclaredAsImage():void{$this->expectException(RemoteAssetException::class);(new ImageValidator())->validate('<html>not an image</html>','image/jpeg',1,1);}
    public function testRejectsSvgAndMimeMismatch():void{foreach([['<svg xmlns="http://www.w3.org/2000/svg"></svg>','image/svg+xml'],[$this->png(),'image/jpeg']]as[$body,$mime]){try{(new ImageValidator())->validate($body,$mime,1,1);$this->fail('Unsafe image accepted.');}catch(RemoteAssetException$e){$this->assertContains($e->resultCode,['mime_invalid','mime_mismatch']);}}}
    public function testRejectsSmallDimensions():void{try{(new ImageValidator())->validate($this->png(),'image/png',300,200);$this->fail('Small image accepted.');}catch(RemoteAssetException$e){$this->assertSame('dimensions_too_small',$e->resultCode);}}
}
