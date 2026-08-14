# Release process

WordPress News Bot sürümleri doğrudan `main` üzerinde geliştirilmez. Her çalışma ayrı bir geliştirme dalında hazırlanır.

## Push öncesi zorunlu yerel kapılar

Tek push yapılmadan önce aşağıdaki kontrollerin tamamı yerelde başarıyla bitmelidir:

1. Bütün PHP dosyalarında lint.
2. PHPUnit; zorunlu testlerde skip veya failure olmamalıdır.
3. Composer strict validation.
4. npm high-severity audit.
5. Eski kimlik, secret ve i18n kalite kapıları.
6. Güncel release ZIP üretimi ve arşiv entry doğrulaması.
7. Gerçek WordPress + MariaDB ortamında install, upgrade ve admin E2E kontrolleri.

Docker, MariaDB veya başka bir zorunlu çalışma zamanı yoksa süreç başarılı kabul edilmez. Eksik kontrol açıkça blocker olarak raporlanır ve branch push edilmez.

## GitHub Actions

- `Continuous quality`, pull request ve `main` push olaylarında yalnız hafif lint, unit, güvenlik ve ZIP kontrollerini çalıştırır.
- `Stabilization gates`, yalnız `workflow_dispatch` ile RC hazır olduğunda manuel çalıştırılır.
- Her workflow `concurrency` ve `cancel-in-progress: true` kullanır.
- Ağır stabilizasyon matrisi ara commit veya ara push ile otomatik tetiklenmez.

## Tamamlama kuralı

Başarısız, bekleyen veya çalıştırılmamış zorunlu workflow varken sürüm tamamlandı, hazır ya da production-ready olarak raporlanmaz. Production deploy ayrı ve açık bir onay gerektirir.
