# Kaynak politikası

## Kaynak yönetimi

Her feed için görünen bir kaynak adı, HTTPS RSS/Atom URL'si, izin verilen yönlendirme alan adları, WordPress kategorisi ve aktif/pasif durumu kaydedilir. Kaydetmeden önce test işlemi HTTP durumu, feed türü, erişilebilen kayıt sayısı ve yanıt süresini güvenli biçimde gösterir.

Aynı canonical feed URL'si SHA-256 unique index ile yalnızca bir kez kaydedilebilir. Kaynak silme işlemi transaction içinde yalnızca işlenmemiş havuz kayıtlarını temizler; daha önce oluşturulan WordPress yazıları ve kaynak post meta değerleri korunur. Çalışan veya kilitli kuyruğu bulunan kaynak silinmez.

Yalnızca izinli RSS/Atom akışları kullanılmalıdır. Rastgele scraping, güvenlik engeli aşma, tam makale kopyalama ve hotlink yapılmaz. Kaynak bağlantısı iç kayıtta saklanır; atıf tercihi editör politikasına göre uygulanır.
