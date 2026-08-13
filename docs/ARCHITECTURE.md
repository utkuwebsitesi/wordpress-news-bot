# Mimari

- `wordpress-news-bot.php`: WordPress bootstrap ve hook’lar.
- `includes/Database.php`: dbDelta ile idempotent schema kurulumu.
- `includes/FeedParser.php`: yalnızca verilen RSS/Atom XML’ini, dış ağ çağrısı yapmadan normalize eder.
- `includes/Security.php`: HTTPS, public host ve allowlist kontrolleri.
- `includes/DuplicateDetector.php`: GUID, normalize URL, hash ve zaman pencereli başlık kontrolleri.
- `includes/MockAiProvider.php`: Phase 1 test sağlayıcısı; Phase 2 provider sözleşmesinin başlangıcı.
- `admin/Admin.php`: Türkçe yönetim ekranları, nonce, capability ve Settings API.

Akış: kaynak → parse/normalize → duplicate kontrolü → `wpnb_feed_items` → kuyruk → editör incelemesi. Otomatik yayın ve gerçek OpenAI Responses API Phase 2’dir.
