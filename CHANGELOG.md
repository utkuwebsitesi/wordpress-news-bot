# Changelog

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
