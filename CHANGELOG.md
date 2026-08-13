# Changelog

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
