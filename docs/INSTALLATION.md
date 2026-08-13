# Kurulum

1. `wordpress-news-bot` klasörünü ZIP olarak WordPress yönetim panelinden yükleyin.
2. Eklentiyi etkinleştirin; `dbDelta` altı tabloyu ve schema sürümünü oluşturur.
3. “Kurulumu Başlat” ile sihirbazı açın veya `WordPress News Bot > Ayarlar > OpenAI Bağlantısı` kartından API anahtarını girin.
4. “Kaydet ve Bağlantıyı Test Et” işlemi başarılı olursa anahtar şifreli biçimde saklanır ve açık metin olarak tekrar gösterilmez.
5. Kaynaklar HTTPS olmalı ve ilk eklemede host allowlist politikasına uygun olmalıdır.

Normal kurulum tamamen yönetim panelinden tamamlanır; yapılandırma dosyası değişikliği gerekmez. Sunucuda Sodium veya OpenSSL AES-256-GCM bulunmuyorsa API anahtarı veritabanına kaydedilmez.

İlk sürüm gerçek AI çağrısı yapmaz ve haber yayınlamaz.
