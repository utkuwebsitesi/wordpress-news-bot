# Security

Güvenlik bildirimleri public issue yerine repository sahibine özel kanal üzerinden iletilmelidir. Secret’lar source control’e alınmaz. API anahtarı Sodium secretbox veya OpenSSL AES-256-GCM ile, her kayıtta rastgele nonce/IV kullanılarak şifrelenir. Güvenli şifreleme yoksa kayıt reddedilir. URL doğrulaması HTTPS, localhost/private IP reddi ve domain allowlist ile yapılır. Tüm yönetim yazma işlemleri capability ve nonce kontrolünden geçer.
