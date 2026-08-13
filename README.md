# Neyelazım NewsBot

Neyelazım.com için bağımsız, tema bağımsız ve güvenlik odaklı WordPress haber botu eklentisi.

## Phase 1

Bu sürüm RSS/Atom kaynaklarını yönetir, haber havuzuna güvenli ve idempotent biçimde alır, duplicate kontrolü yapar, mock AI sağlayıcısı ile yapılandırılmış çıktı üretir ve yönetim panelinde editör incelemesine sunar. Otomatik yayın ve gerçek OpenAI çağrıları Phase 2 kapsamındadır. Varsayılan içerik durumu `draft` olarak kalır.

Kurulum için [docs/INSTALLATION.md](docs/INSTALLATION.md), mimari için [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md) dosyasına bakın.

## Güvenlik

Kaynak URL'leri HTTPS ve allowlist ile sınırlandırılır; özel IP, localhost ve yönlendirme hedefleri reddedilir. API anahtarı depolanmaz; Phase 2 sabit/şifreli ayar planı için [SECURITY.md](SECURITY.md) okunmalıdır.

## Lisans

Private/proprietary — [LICENSE](LICENSE).
