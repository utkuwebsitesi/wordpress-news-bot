# cPanel / Cron

WP-Cron sayfa ziyaretleriyle tetiklendiği için düşük trafikli sitelerde gecikme görülebilir. Sistem Sağlığı ekranında bu durum gösterilir.

Phase 1’de güvenli iş kilidi hazırdır. cPanel Cron için WordPress’in kendi cron endpoint’ini yalnızca HTTPS ve sabit bir gizli değerle korunan sunucu tarafı komutundan çağırın; public bir URL’ye sır koymayın. Önerilen periyot saatte birdir.
