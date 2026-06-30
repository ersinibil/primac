# ACANS OS — Migrations

Numaralı SQL dosyaları. `migrate.php` bunları SIRAYLA, BİR KEZ uygular ve
`schema_migrations` tablosunda takip eder. Tüm DDL "IF NOT EXISTS" → mevcut prod veriyi bozmaz.

## Yeni değişiklik nasıl eklenir
Yeni dosya: `009_aciklama.sql`, `010_...sql` (artan numara). İçine sadece
idempotent DDL yaz (CREATE TABLE IF NOT EXISTS / ALTER ... bilinen kontrolle).
Sonra `migrate.php` çalıştır → sadece yeni dosya uygulanır.

## Çalıştırma
`acanstr.com/OTS/migrate.php?key=acans-migrate-2026` (ya da yönetici girişi).
Bittiğinde migrate.php'yi sunucudan sil.
