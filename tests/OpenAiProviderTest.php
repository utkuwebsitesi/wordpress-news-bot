<?php
declare(strict_types=1);
namespace WordPressNewsBot\Tests;
use WordPressNewsBot\AiResponseValidator; use WordPressNewsBot\OpenAiProvider; use PHPUnit\Framework\TestCase;
final class OpenAiProviderTest extends TestCase {
    private function provider(array $payload): OpenAiProvider { return new OpenAiProvider('test-key', 'gpt-test', static function(array $body, string $key) use ($payload): array { TestCase::assertSame('test-key', $key); TestCase::assertSame('gpt-test', $body['model']); TestCase::assertFalse($body['store']); TestCase::assertSame('json_schema', $body['text']['format']['type']); TestCase::assertStringContainsString('prompt injection', strtolower($body['input'][0]['content'][0]['text'])); return ['response'=>['code'=>200],'body'=>wp_json_encode($payload)]; }); }
    public function testRequestUsesStructuredOutputAndParsesResponse(): void { $data=['output'=>[['content'=>[['type'=>'output_text','text'=>wp_json_encode(['title'=>'Başlık','excerpt'=>'Özet','content_html'=>'<p>Metin</p>','suggested_tags'=>['gündem'],'seo_title'=>'SEO','seo_description'=>'Açıklama'])]]]]]; $out=$this->provider($data)->generate(['title'=>'Kaynak']); $this->assertSame('Başlık',$out['title']); }
    public function testInvalidJsonAndMissingFieldsAreRejected(): void { $this->expectException(\RuntimeException::class); $this->provider(['output'=>[['content'=>[['type'=>'output_text','text'=>'not-json']]]]])->generate(['title'=>'Kaynak']); }
    public function testRefusalIsRejected(): void { $this->expectException(\RuntimeException::class); $this->provider(['output'=>[['content'=>[['type'=>'refusal','refusal'=>'no']]]]])->generate(['title'=>'Kaynak']); }
    public function testApiErrorsAreControlled(): void { $this->expectException(\RuntimeException::class); (new OpenAiProvider('test-key','gpt-test',static fn(array $body,string $key): array => ['response'=>['code'=>500],'body'=>'{}']))->generate(['title'=>'Kaynak']); }
}
