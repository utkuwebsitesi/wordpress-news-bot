# WordPress News Bot

Current candidate: `0.5.0-rc.4`

0.5.0-rc.4 preserves the immutable rc.3 package and fixes UTC/local display, 20-minute heartbeat tolerance, quarter-hour external trigger forecasting and safe zero-publication diagnostics without changing the stable release line.

P0 manuel akış: `Kaynak ekle → Haberleri çek → Haber havuzunda incele → AI ile taslak oluştur → WordPress taslağını düzenle`. Kaynak bazlı ve toplu haber çekme işlemleri WP-Cron kapalıyken de aynı güvenli import servisini kullanır; kullanıcının WP-CLI, phpMyAdmin veya sunucu cron'una ihtiyacı yoktur.

Durum: **release candidate / P0 otomasyon kabulü bekliyor**. Production deploy yapılmamıştır; gerçek WordPress + MariaDB ortamında Türkçe yönetim arayüzü, bağımsız heartbeat, dış cron, kota, zaman dağıtımı, kilit, hata ve duplicate senaryoları doğrulanmadan `0.5.0-rc.4` hazır veya production-ready kabul edilmez.

Geliştirici: **Utkuweb**

0.3.1 sürümü Haber Kaynakları ekranına kaydetmeden önce bağlantı testi, düzenleme, aktif/pasif durumu, onaylı silme ve toplu işlemler ekler. Canonical URL SHA-256 benzersizliği eşzamanlı isteklerde dahi duplicate feed kaydını engeller; şema yükseltmesi mevcut duplicate kaynakları güvenli biçimde birleştirip ilişkili kayıtları ana kaynağa taşır.

0.3.2 migration işlemi öncesinde kaynaklar ve ilişkili havuz/kuyruk kayıtları için güvenli bir journal snapshot'ı oluşturur. Satır sayısı veya ilişki taşıma doğrulaması başarısızsa rollback ve snapshot geri yükleme uygulanır; schema sürümü yalnızca başarıdan sonra yükseltilir. 0.3.1 sonrasında kaynak tablosu boş ve kurtarılabilir snapshot yoksa yönetici açıkça uyarılır ve uydurma kaynak oluşturulmaz. Ana izin verilen host RSS URL'sinden otomatik çıkarılır; güvenli gerçek alt alan eşleşmesi desteklenir.

0.3.3 kaynak testini URL, DNS/IP, HTTP, redirect, Content-Type, body, XML, feed ve veritabanı aşamalarında güvenli sonuç kodlarıyla izler. RSS 2.0, RSS 1.0/RDF ve Atom; XML charset değerleri ve genel Content-Type ile gelen geçerli feed'ler desteklenir. Başarısız form girdileri korunur, başarılı test sonucu kısa süreli kullanıcı/form tokenıyla yeniden kullanılabilir ve migration uyarısı kullanıcı tarafından kapatılabilir.

0.3.4 fiziksel veritabanı şemasını kayıtlı sürümden bağımsız denetler. Sistem Sağlığı ekranı tablo, sütun, index, motor ve collation durumunu içerik göstermeden raporlar; güvenli ve idempotent onarım eksik yapıları migration journal ile tamamlar. RSS testi başarılı olduğu halde şema bozuksa aynı test tokenı korunur ve onarım sonrasında yeniden HTTP isteği yapılmadan kaynak kaydedilebilir.

0.3.5 eski kurulumlarda sunucu varsayılanı nedeniyle MyISAM oluşmuş WordPress News Bot tablolarını, yönetici onayı ve InnoDB/ALTER ön kontrolünden sonra veri korumalı biçimde InnoDB'ye dönüştürür. Tablolar güvenilir allowlist sırasıyla tek tek işlenir; satır sayısı, checksum ve motor her adımda doğrulanır. Yeni tablolar sunucu varsayılanından bağımsız olarak açıkça `ENGINE=InnoDB` ve WordPress charset/collation değeriyle oluşturulur.

0.4.0-rc.1 stabilizasyon çalışması gerçek release ZIP kurulumunu, WordPress 7.0.4/6.4 ve PHP 8.3/8.1 ortamlarını, MyISAM/InnoDB varsayılanlarını, 0.3.0–0.3.5 upgrade matrisini ve Playwright admin akışlarını kalıcı CI gate'lerine bağlar. Gerçek import artık bağlantı testiyle aynı SSRF/DNS/redirect motorunu kullanır; fiziksel şema ve yazma formatları canonical tanımdan türetilir; taslak üretimi atomik kilit ve kota rezervasyonu kullanır.

0.4.0-rc.3 haber havuzuna sayfa ve filtre kapsamlı güvenli toplu seçim; AI taslak oluşturma, kuyruğa alma ve havuzdan silme işlemleri ekler. OpenAI çıktıları başlık özgünlüğü, uzun kaynak pasajı kopyası, kaynak URL'si ve minimum kullanılabilirlik açısından doğrulanır; ilk uygunsuz sonuçta yalnızca bir kez yeniden denenir. Yeni taslaklarda görünür kaynak bloğu bulunmaz, kaynak ilişkisi yalnızca korumalı post meta alanlarında tutulur. Eski eklenti taslakları için yayımlanmış içeriklere dokunmayan, yönetici onaylı bakım aracı sağlanır.

0.4.0-rc.4 işlenmiş haberleri silmeden `processed` veya `published` durumuna taşır, WordPress post ilişkisini feed kaydında korur ve varsayılan havuzdan çıkarır. Varsayılan yayınlama modu doğrudan yayındır: yazı önce ziyaretçiye kapalı taslak olarak hazırlanır; AI, içerik, kategori, korumalı meta ve gerekiyorsa öne çıkan görsel doğrulandıktan sonra yayınlanır. Önceki eklenti taslakları yalnız yönetici seçimi ve açık onayla yayımlanabilir.

Medya P0 kapsamı RSS/Atom `media:content`, `media:thumbnail`, görsel enclosure ve içerikteki ilk geçerli görsel adayını öncelik sırasıyla ayrıştırır. Görseller SSRF, DNS, redirect, gerçek MIME, boyut ve ölçü kontrollerinden sonra WordPress Ortam Kütüphanesine alınır; URL ve dosya hash'i duplicate attachment oluşmasını engeller. Kaynak bazında görsel içe aktarma, görselsiz taslak ve gelişmiş `og:image` seçenekleri; Haber Havuzunda yerel önizleme, durum, filtre ve tekli/toplu yeniden çekme işlemleri bulunur. Uzak görsel URL'si yalnızca `_wpnb_` korumalı meta alanlarında saklanır.

Her WordPress sitesinde kullanılabilecek bağımsız, tema bağımsız ve güvenlik odaklı haber botu eklentisi.

Global kullanım için plugin slug, namespace, sabitler, option/transient isimleri ve veritabanı tabloları `wordpress-news-bot`, `WordPressNewsBot`, `WPNB_` ve `wpnb_` kimliklerini kullanır.

## Phase 1 ve Phase 2

Bu sürüm RSS/Atom kaynaklarını yönetir, haber havuzuna güvenli ve idempotent biçimde alır, duplicate kontrolü yapar ve yönetim panelinde editör incelemesine sunar. OpenAI Responses API strict JSON Schema çıktısı üretir. Yönetici yayınlama modunu taslak veya doğrudan yayın olarak seçebilir; otomasyon varsayılan olarak kapalıdır ve yalnız bütün kalite kapıları geçtiğinde yayın yapar.

OpenAI kullanımı için WordPress yönetim panelinde **WordPress News Bot → Ayarlar → OpenAI Bağlantısı** kartını açın. API anahtarı yalnızca sunucu tarafında test edilir; test başarılı olursa WordPress salt değerlerinden türetilen anahtarla şifrelenerek, autoload kapalı biçimde saklanır. Kaydedilen anahtar tekrar gösterilmez. Otomasyonun önerilen başlangıç profili günlük 20 haber, kaynak başına 10 haber, 08:00–23:00 aralığı, 45 dakika minimum mesafe ve çalıştırma başına bir haberdir.

İlk etkinleştirmede beş adımlı kurulum sihirbazı OpenAI bağlantısı, içerik ayarları, limitler, ilk RSS kaynağı ve özeti yönlendirir. Sihirbaz atlanabilir ve ayarlar daha sonra tamamlanabilir.

Kurulum için [docs/INSTALLATION.md](docs/INSTALLATION.md), mimari için [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md), zorunlu yerel ve manuel RC kapıları için [docs/RELEASE-PROCESS.md](docs/RELEASE-PROCESS.md) dosyasına bakın.

## Güvenlik

Kaynak URL'leri HTTPS ve allowlist ile sınırlandırılır; özel IP, localhost ve yönlendirme hedefleri reddedilir. API anahtarı yalnızca başarılı bağlantı testi sonrasında authenticated encryption ile saklanır; ayrıntılar için [SECURITY.md](SECURITY.md) okunmalıdır.

## Lisans

Private/proprietary — [LICENSE](LICENSE).
