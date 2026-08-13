# WordPress News Bot

Current release: `0.3.1`

0.3.1 sürümü Haber Kaynakları ekranına kaydetmeden önce bağlantı testi, düzenleme, aktif/pasif durumu, onaylı silme ve toplu işlemler ekler. Canonical URL SHA-256 benzersizliği eşzamanlı isteklerde dahi duplicate feed kaydını engeller; şema yükseltmesi mevcut duplicate kaynakları güvenli biçimde birleştirip ilişkili kayıtları ana kaynağa taşır.

Her WordPress sitesinde kullanılabilecek bağımsız, tema bağımsız ve güvenlik odaklı haber botu eklentisi.

Global kullanım için plugin slug, namespace, sabitler, option/transient isimleri ve veritabanı tabloları `wordpress-news-bot`, `WordPressNewsBot`, `WPNB_` ve `wpnb_` kimliklerini kullanır.

## Phase 1 ve Phase 2

Bu sürüm RSS/Atom kaynaklarını yönetir, haber havuzuna güvenli ve idempotent biçimde alır, duplicate kontrolü yapar ve yönetim panelinde editör incelemesine sunar. Phase 2’de OpenAI Responses API ile strict JSON Schema çıktısı alınabilir; taslaklar WordPress’te daima `draft` statüsünde oluşturulur. Otomatik yayın yoktur.

OpenAI kullanımı için WordPress yönetim panelinde **WordPress News Bot → Ayarlar → OpenAI Bağlantısı** kartını açın. API anahtarı yalnızca sunucu tarafında test edilir; test başarılı olursa WordPress salt değerlerinden türetilen anahtarla şifrelenerek, autoload kapalı biçimde saklanır. Kaydedilen anahtar tekrar gösterilmez. Varsayılan cron kapalı, günlük kota 25 ve çalıştırma başına limit 5’tir.

İlk etkinleştirmede beş adımlı kurulum sihirbazı OpenAI bağlantısı, içerik ayarları, limitler, ilk RSS kaynağı ve özeti yönlendirir. Sihirbaz atlanabilir ve ayarlar daha sonra tamamlanabilir.

Kurulum için [docs/INSTALLATION.md](docs/INSTALLATION.md), mimari için [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md) dosyasına bakın.

## Güvenlik

Kaynak URL'leri HTTPS ve allowlist ile sınırlandırılır; özel IP, localhost ve yönlendirme hedefleri reddedilir. API anahtarı yalnızca başarılı bağlantı testi sonrasında authenticated encryption ile saklanır; ayrıntılar için [SECURITY.md](SECURITY.md) okunmalıdır.

## Lisans

Private/proprietary — [LICENSE](LICENSE).
