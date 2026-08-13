# WordPress News Bot

Her WordPress sitesinde kullanılabilecek bağımsız, tema bağımsız ve güvenlik odaklı haber botu eklentisi.

Global kullanım için plugin slug, namespace, sabitler, option/transient isimleri ve veritabanı tabloları `wordpress-news-bot`, `WordPressNewsBot`, `WPNB_` ve `wpnb_` kimliklerini kullanır.

## Phase 1 ve Phase 2

Bu sürüm RSS/Atom kaynaklarını yönetir, haber havuzuna güvenli ve idempotent biçimde alır, duplicate kontrolü yapar ve yönetim panelinde editör incelemesine sunar. Phase 2’de OpenAI Responses API ile strict JSON Schema çıktısı alınabilir; taslaklar WordPress’te daima `draft` statüsünde oluşturulur. Otomatik yayın yoktur.

OpenAI kullanımı için `wp-config.php` içine `define('WPNB_OPENAI_API_KEY', '...');` ekleyin, ayarlardan sağlayıcıyı OpenAI seçin ve “Bağlantıyı Test Et” düğmesini kullanın. Anahtar repository’ye veya WordPress ayarlarına yazılmaz. Varsayılan sağlayıcı mock, cron kapalı, günlük kota 25 ve çalıştırma başına limit 5’tir.

Kurulum için [docs/INSTALLATION.md](docs/INSTALLATION.md), mimari için [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md) dosyasına bakın.

## Güvenlik

Kaynak URL'leri HTTPS ve allowlist ile sınırlandırılır; özel IP, localhost ve yönlendirme hedefleri reddedilir. API anahtarı depolanmaz; Phase 2 sabit/şifreli ayar planı için [SECURITY.md](SECURITY.md) okunmalıdır.

## Lisans

Private/proprietary — [LICENSE](LICENSE).
