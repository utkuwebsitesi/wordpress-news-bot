# Changelog

## 0.4.0-rc.1 - Stabilization and release candidate gates

- Gerçek import ile kaynak bağlantı testinin SSRF, DNS, redirect, response-size ve feed doğrulama motoru birleştirildi.
- Canonical veritabanı şeması INSERT/UPDATE formatlarının tek kaynağı haline getirildi; şema fingerprint'inden değişken satır/byte değerleri çıkarıldı.
- Taslak üretiminde atomik option kilidi, eşzamanlı kota rezervasyonu, job/generation günlüğü ve kontrollü hata geri alma eklendi.
- Bütün admin mutasyonları POST, capability ve nonce kapılarına alındı; haber havuzu tekli/toplu taslak eylemleri gerçek ayar limitini kullanıyor.
- Docker tabanlı WordPress 7.0.4/PHP 8.3/MariaDB 10.11 MyISAM ve InnoDB ortamları ile WordPress 6.4/PHP 8.1 minimum ortamı eklendi.
- Gerçek ZIP install/activate/deactivate/reactivate, 0.3.0–0.3.5 upgrade matrisi, yerel deterministik RSS/OpenAI servisi ve Playwright admin E2E gate'leri eklendi.
- ZIP, PHP lint, PHPUnit/MariaDB, Composer, npm audit, i18n, secret ve eski kimlik taramaları tek RC gate altında birleştirildi.
- Bu sürüm release candidate'dır; production deploy ve stable `0.4.0` etiketi içermez.

## 0.3.5 - Safe MyISAM to InnoDB conversion

- 0.3.0–0.3.3 tablo oluşturma SQL'lerinin sunucu varsayılan motoruna bağlı kalması nedeniyle oluşabilen MyISAM kök nedeni doğrulandı.
- Yeni kurulumlar için açık `ENGINE=InnoDB` ve `$wpdb->get_charset_collate()` kullanımı test altına alındı.
- `SHOW ENGINES`, ALTER yetkisi, tablo boyutu ve satır sayısı ön kontrolleri eklendi.
- Yalnız yedi güvenilir eklenti tablosunu etkileyen, yönetici onaylı MyISAM → InnoDB dönüşümü eklendi.
- Option tabanlı ilk repair state ve migration journal ile tablo bazlı başlangıç/bitiş/motor/satır/checksum kaydı eklendi.
- İlk ALTER hatasında güvenli durma, kısmi durumdan devam ve ikinci çalıştırmada sıfır değişiklik doğrulandı.
- `innodb_unavailable`, `alter_permission_denied`, `engine_conversion_required`, `engine_conversion_failed` ve `engine_conversion_verified` tanılama kodları eklendi.
- MariaDB CI matrisi varsayılan MyISAM, veri koruma, ilişkili kayıt, checksum, kısmi dönüşüm ve NTV insert senaryolarıyla genişletildi.

## 0.3.4 - Database diagnostics and safe repair

- Fiziksel tablo/sütun/index/tür/default/motor/charset/collation denetimi ve güvenli schema fingerprint'i.
- Test Kimliğiyle aranabilen, allowlist alanlı ve secret/ham SQL/içerik içermeyen tanılama kayıtları.
- `manage_options`, POST ve nonce korumalı; journal kullanan, aşamalı ve idempotent “Veritabanını Onar” işlemi.
- 0.3.0 eksik `canonical_hash`, `last_checked_at`, `last_result` yapılarından güvenli yükseltme ve duplicate doğrulamalı unique index onarımı.
- Kaynak insert/update işlemlerinde açık `$wpdb` format dizileri, etkilenen satır doğrulaması ve güvenli DB hata sınıflandırması.
- Onarım sırasında başarılı RSS test tokenının korunması; başarılı insert sonrasında tüketilmesi.
- MariaDB 10.11 servisli CI upgrade/integration test matrisi.

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
