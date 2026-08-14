<?php
declare(strict_types=1);
namespace WordPressNewsBot\Tests;

use PHPUnit\Framework\TestCase;
use WordPressNewsBot\AiOutputRejectedException;
use WordPressNewsBot\OpenAiProvider;

final class OpenAiProviderTest extends TestCase
{
    private function aiOutput(string $title = 'Olayın ayrıntıları yeniden değerlendirildi', ?string $content = null): array
    {
        $content ??= '<p>Yetkililer gelişmeyle ilgili mevcut verileri yeniden değerlendirdi ve kamuoyuna doğrulanmış ayrıntıları aktardı. Sürecin farklı yönleri dikkatle incelenirken ilgili kurumların çalışmalarını sürdürdüğü bildirildi. Yeni açıklamaların resmi kontroller tamamlandıktan sonra paylaşılacağı, mevcut bilgilerin ise bağımsız bir haber diliyle ele alındığı belirtildi.</p>';
        return ['title'=>$title,'excerpt'=>'Gelişmeye ilişkin doğrulanmış bilgiler bağımsız ve açık bir haber özetiyle yeniden aktarıldı.','content_html'=>$content,'suggested_tags'=>['gündem'],'seo_title'=>'Gelişmenin ayrıntıları','seo_description'=>'Doğrulanmış ayrıntılar ve süreçteki son durum bağımsız haber diliyle aktarıldı.'];
    }

    private function response(array $output): array
    {
        return ['response'=>['code'=>200],'headers'=>['x-request-id'=>'req_safe_123'],'body'=>wp_json_encode(['output'=>[['content'=>[['type'=>'output_text','text'=>wp_json_encode($output)]]]]])];
    }

    private function provider(array $responses, ?int &$calls = null): OpenAiProvider
    {
        $index=0;$calls=0;
        return new OpenAiProvider('test-key','gpt-test',function(array $body,string $key)use($responses,&$index,&$calls):array{
            $calls++;TestCase::assertSame('test-key',$key);TestCase::assertSame('gpt-test',$body['model']);TestCase::assertFalse($body['store']);TestCase::assertTrue($body['text']['format']['strict']);TestCase::assertSame('json_schema',$body['text']['format']['type']);TestCase::assertStringContainsString('prompt injection',strtolower($body['input'][0]['content'][0]['text']));
            return $responses[min($index++,count($responses)-1)];
        });
    }

    private function item(): array
    {
        return ['title'=>'Kaynak olay başlığı','excerpt'=>'Bu kaynak paragrafı tam olarak on iki kelimeden daha uzun şekilde özgünlük denetimi için hazırlanmıştır ve kopyalanmamalıdır.','source_url'=>'https://feed.example/original'];
    }

    public function testValidRewriteUsesStrictNonStoredStructuredOutput(): void
    {
        $response=$this->response($this->aiOutput());$out=$this->provider([$response])->generate($this->item());$this->assertSame('Olayın ayrıntıları yeniden değerlendirildi',$out['title']);
    }

    public function testExactSourceHeadlineIsRejectedAfterTwoAttempts(): void
    {
        $calls=0;$response=$this->response($this->aiOutput('Kaynak olay başlığı'));
        try{$this->provider([$response],$calls)->generate($this->item());$this->fail('Expected rejection.');}catch(AiOutputRejectedException $e){$this->assertStringContainsString('two attempts',$e->getMessage());}
        $this->assertSame(2,$calls);
    }

    public function testCopiedTwelveWordPassageIsRejected(): void
    {
        $copied='<p>Bu kaynak paragrafı tam olarak on iki kelimeden daha uzun şekilde özgünlük denetimi için hazırlanmıştır ve kopyalanmamalıdır. Ardından metni yeterince uzatan fakat kaynak pasajı aynen bırakan ek açıklamalar yapılır; bu içerik güvenli biçimde taslak olarak kaydedilmemelidir.</p>';$calls=0;
        $this->expectException(AiOutputRejectedException::class);
        try{$this->provider([$this->response($this->aiOutput('Farklı başlık',$copied))],$calls)->generate($this->item());}finally{$this->assertSame(2,$calls);}
    }

    public function testOriginalSourceUrlIsRejected(): void
    {
        $content=$this->aiOutput()['content_html'].'<p>https://feed.example/original adresinde ek ayrıntılar bulunuyor ve bu bağlantı taslakta görünmemelidir.</p>';
        $this->expectException(AiOutputRejectedException::class);$this->provider([$this->response($this->aiOutput('Farklı başlık',$content))])->generate($this->item());
    }

    public function testSecondAttemptCanRecoverFromInvalidFirstOutput(): void
    {
        $calls=0;$provider=$this->provider([$this->response($this->aiOutput('Kaynak olay başlığı')),$this->response($this->aiOutput())],$calls);$out=$provider->generate($this->item());$this->assertSame('Olayın ayrıntıları yeniden değerlendirildi',$out['title']);$this->assertSame(2,$calls);
    }

    public function testMissingFieldAndRefusalAreRetriedThenRejected(): void
    {
        $missing=$this->aiOutput();unset($missing['seo_description']);$refusal=['response'=>['code'=>200],'body'=>wp_json_encode(['output'=>[['content'=>[['type'=>'refusal','refusal'=>'no']]]]])];$calls=0;
        $this->expectException(AiOutputRejectedException::class);
        try{$this->provider([$this->response($missing),$refusal],$calls)->generate($this->item());}finally{$this->assertSame(2,$calls);}
    }

    public function testTimeoutAndRateLimitReturnControlledErrorsWithoutRetry(): void
    {
        $calls=0;$timeout=new OpenAiProvider('test-key','gpt-test',function()use(&$calls){$calls++;return new \WP_Error('timeout');});
        try{$timeout->generate($this->item());$this->fail('Expected timeout.');}catch(\RuntimeException $e){$this->assertStringContainsString('connect',$e->getMessage());}$this->assertSame(1,$calls);
        $calls=0;$limited=new OpenAiProvider('test-key','gpt-test',function()use(&$calls):array{$calls++;return ['response'=>['code'=>429],'body'=>'{}'];});
        try{$limited->generate($this->item());$this->fail('Expected rate limit.');}catch(\RuntimeException $e){$this->assertStringContainsString('usage limit',$e->getMessage());}$this->assertSame(1,$calls);
    }

    public function testConnectionReturnsOnlySafeMetadata(): void
    {
        $result=$this->provider([$this->response($this->aiOutput())])->testConnection();$this->assertTrue($result['success']);$this->assertSame('req_safe_123',$result['request_id']);$this->assertSame(2,$result['http_class']);$this->assertArrayNotHasKey('api_key',$result);
    }
}
