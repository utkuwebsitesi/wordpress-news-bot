# Mimari

- `neyelazim-newsbot.php`: WordPress bootstrap ve hook’lar. Dosya adı geriye dönük uyumluluk için korunmuştur.
- `src/Database.php`: dbDelta ile idempotent schema kurulumu.
- `src/FeedParser.php`: yalnızca verilen RSS/Atom XML’ini, dış ağ çağrısı yapmadan normalize eder.
- `src/Security.php`: HTTPS, public host ve allowlist kontrolleri.
- `src/DuplicateDetector.php`: GUID, normalize URL, hash ve zaman pencereli başlık kontrolleri.
- `src/MockAiProvider.php`: Phase 1 test sağlayıcısı; Phase 2 provider sözleşmesinin başlangıcı.
- `src/Admin.php`: Türkçe yönetim ekranları, nonce, capability ve Settings API.

Akış: kaynak → parse/normalize → duplicate kontrolü → `nyb_feed_items` → kuyruk → editör incelemesi. Otomatik yayın ve gerçek OpenAI Responses API Phase 2’dir.
