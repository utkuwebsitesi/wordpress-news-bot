<?php
declare(strict_types=1);
namespace WordPressNewsBot;

final class MockAiProvider implements AiProvider
{
    public function model(): string { return 'mock-turkish-v1'; }
    public function testConnection(): array { return ['success'=>true,'model'=>$this->model(),'duration_ms'=>0,'request_id'=>'mock','http_class'=>2]; }
    public function generate(array $item): array
    {
        $source=trim((string)($item['title']??'gelişme'));$title='Gündemdeki gelişmenin ayrıntıları netleşiyor';$content='<p>'.$source.' başlığıyla duyurulan gelişme, doğrulanabilir temel bilgiler korunarak editör incelemesine uygun yeni bir anlatımla hazırlandı. Olayın kişi, kurum, yer, tarih ve sayı bilgileri değiştirilmeden aktarılırken kaynak metindeki cümle yapıları tekrarlanmadı.</p><p>Hazırlanan taslak yalnız mevcut verilerde yer alan olguları kapsıyor. Yeni yorum, iddia veya alıntı eklenmedi ve metin yayımlanmadan önce editör kontrolüne bırakıldı.</p>';
        return ['title'=>$title,'excerpt'=>'Gelişmenin doğrulanabilir ayrıntıları, yeni bir Türkçe haber anlatımıyla editör incelemesine hazırlandı.','content_html'=>$content,'suggested_tags'=>['gündem'],'seo_title'=>$title,'seo_description'=>'Gelişmenin doğrulanabilir ayrıntılarını içeren editör kontrollü haber taslağı.'];
    }
}
