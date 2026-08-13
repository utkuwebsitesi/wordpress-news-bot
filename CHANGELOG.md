# Changelog

## 0.3.3 - RSS diagnostics and form recovery

- Eklenti geliştirici/yazar bilgisi kalıcı olarak `Utkuweb` şeklinde tanımlandı.
- URL'den veritabanına kadar aşama bazlı, secretsız sonuç kodları ve destek Test Kimliği.
- WordPress HTTP API için sade User-Agent, geniş XML Accept header'ı, sınırlı redirect ve her hedefte DNS/IP SSRF doğrulaması.
- RSS 2.0, RSS 1.0/RDF, Atom, charset içeren XML Content-Type ve geçerli XML body fallback desteği.
- Başarısız kaynak formunda ad, URL, kategori, durum ve gelişmiş ayarların korunması.
- Başarılı test sonucunun kullanıcı ve form girdisine bağlı kısa süreli token ile güvenli yeniden kullanımı.
- Kapatılabilir migration uyarısı; başarılı kayıt sonrası gerçek migration çalıştırılarak güvenli temizleme.

## 0.3.2 - Source migration recovery

- Migration öncesi kaynak, havuz, kuyruk ve AI ilişki snapshot'ı tutan migration journal.
- Primary key ana kaynak doğrulaması, affected-row kontrolleri, rollback ve otomatik snapshot geri yükleme.
- Yalnızca başarılı migration sonrasında schema version güncellemesi ve unique index doğrulaması.
- Yarım kalmış journal'dan otomatik toparlanma; snapshot bulunmayan boş 0.3.1 kurulumu için açık yönetici uyarısı.
- RSS URL'sinden otomatik host çıkarma, güvenli alt alan eşleşmesi ve suffix saldırısı koruması.
- Kullanıcıya ham exception göstermeyen Türkçe kaynak hata akışı.

## 0.3.1 - News source maintenance

- Source connection test, edit, active/inactive, confirmed delete, and bulk action flows.
- RSS/Atom tests protected by SSRF, redirect, and allowed-host validation.
- Canonical feed URL SHA-256 unique index and concurrent duplicate protection.
- Idempotent schema migration that merges existing duplicate sources and reassigns related records.
- Transactional deletion with job-lock checks, pending-pool cleanup, and preservation of existing WordPress posts.

## 0.3.0 - Secure onboarding and credentials

- Yönetim panelinden test edilerek kaydedilen, Sodium/AES-256-GCM şifreli OpenAI API anahtarı yönetimi.
- Beş adımlı ilk kurulum sihirbazı, bağlantı metadatası ve profesyonel ayar/durum kartları.
- API anahtarı değiştirme, yeniden test etme ve onaylı silme akışları.
- WordPress i18n altyapısı, güvenli salt değişimi davranışı ve genişletilmiş credential testleri.

## 0.2.2 - Cross-platform release packaging

- ZIP entry names are generated with forward slashes on every operating system.
- PHP `ZipArchive` now validates every entry, the single plugin root, the main plugin file, and extracted layout.
- Release creation uses a clean temporary staging directory and production-only Composer dependencies.

## 0.2.1 - Global identity cleanup

- Eklenti kimlikleri tamamen `WordPress News Bot`, `wordpress-news-bot`, `WordPressNewsBot`, `wpnb_` ve `WPNB_` olarak yeniden adlandırıldı.
- Üretim ZIP yapısı düzeltildi; ana plugin dosyası `wordpress-news-bot/wordpress-news-bot.php` konumundadır.

## 0.2.1 - WordPress News Bot rebrand

- Eklentinin görünen adı tüm WordPress sitelerinde kullanılacak şekilde `WordPress News Bot` olarak güncellendi.
- GitHub repository adı `wordpress-news-bot` olarak değiştirildi.
- Mevcut kurulum uyumluluğu için iç slug, `wpnb_` prefix’i ve namespace korunmuştur.

## 0.1.0 - Phase 1

- Eklenti iskeleti, aktivasyon/deaktivasyon ve güvenli uninstall.
- RSS/Atom kaynak yönetimi, feed havuzu ve duplicate kontrolü.
- Mock AI provider, sağlık ekranı, işlem kuyruğu ve Türkçe yönetim menüleri.
- PHPUnit test altyapısı ve cPanel/release dokümantasyonu.

## 0.2.0 - Phase 2

- OpenAI Responses API provider, `store:false` ve strict Structured Outputs JSON Schema.
- Güvenilmeyen RSS içeriği için prompt-injection talimatları ve güvenli HTML temizliği.
- Günlük kota, maksimum batch sayısı, admin bağlantı testi, tekli/toplu draft üretimi.
- `wp_insert_post()` ile yalnızca `draft` taslaklar ve kaynak/AI post meta kayıtları.
- Cron varsayılan olarak kapalı; gerçek API çağrıları testlerde mock HTTP transport ile yalıtılmıştır.
