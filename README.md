# WordPress News Bot

Current release: `0.3.5`

Geliştirici: **Utkuweb**

0.3.1 sürümü Haber Kaynakları ekranına kaydetmeden önce bağlantı testi, düzenleme, aktif/pasif durumu, onaylı silme ve toplu işlemler ekler. Canonical URL SHA-256 benzersizliği eşzamanlı isteklerde dahi duplicate feed kaydını engeller; şema yükseltmesi mevcut duplicate kaynakları güvenli biçimde birleştirip ilişkili kayıtları ana kaynağa taşır.

0.3.2 migration işlemi öncesinde kaynaklar ve ilişkili havuz/kuyruk kayıtları için güvenli bir journal snapshot'ı oluşturur. Satır sayısı veya ilişki taşıma doğrulaması başarısızsa rollback ve snapshot geri yükleme uygulanır; schema sürümü yalnızca başarıdan sonra yükseltilir. 0.3.1 sonrasında kaynak tablosu boş ve kurtarılabilir snapshot yoksa yönetici açıkça uyarılır ve uydurma kaynak oluşturulmaz. Ana izin verilen host RSS URL'sinden otomatik çıkarılır; güvenli gerçek alt alan eşleşmesi desteklenir.

0.3.3 kaynak testini URL, DNS/IP, HTTP, redirect, Content-Type, body, XML, feed ve veritabanı aşamalarında güvenli sonuç kodlarıyla izler. RSS 2.0, RSS 1.0/RDF ve Atom; XML charset değerleri ve genel Content-Type ile gelen geçerli feed'ler desteklenir. Başarısız form girdileri korunur, başarılı test sonucu kısa süreli kullanıcı/form tokenıyla yeniden kullanılabilir ve migration uyarısı kullanıcı tarafından kapatılabilir.

0.3.4 fiziksel veritabanı şemasını kayıtlı sürümden bağımsız denetler. Sistem Sağlığı ekranı tablo, sütun, index, motor ve collation durumunu içerik göstermeden raporlar; güvenli ve idempotent onarım eksik yapıları migration journal ile tamamlar. RSS testi başarılı olduğu halde şema bozuksa aynı test tokenı korunur ve onarım sonrasında yeniden HTTP isteği yapılmadan kaynak kaydedilebilir.

0.3.5 eski kurulumlarda sunucu varsayılanı nedeniyle MyISAM oluşmuş WordPress News Bot tablolarını, yönetici onayı ve InnoDB/ALTER ön kontrolünden sonra veri korumalı biçimde InnoDB'ye dönüştürür. Tablolar güvenilir allowlist sırasıyla tek tek işlenir; satır sayısı, checksum ve motor her adımda doğrulanır. Yeni tablolar sunucu varsayılanından bağımsız olarak açıkça `ENGINE=InnoDB` ve WordPress charset/collation değeriyle oluşturulur.

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
