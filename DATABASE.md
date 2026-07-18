# DATABASE.md — Şema Envanteri ve Kurallar

## Ortamlar (ÖNEMLİ: DEV/PROD ayrı DB, tek geliştirme ortamı modeli — 2026-07-03)
| Ortam | Rol | DB | Not |
|---|---|---|---|
| **DEV** | primac.tr | `u7883898_primactr` | Kod geliştirme/test ortamı — gerçek PRIMAC işletme verisi taşıdığı için dikkatli kullanılmalı, "sandbox" değildir. |
| **PROD (LIVE)** | acanstr.com/ots/ | `u7883898_primacos` | Sadece canlı — geliştirme yapılmaz, sadece "DEPLOY MODE" ile güncellenir (bkz. `PROJECT_RULES.md`). |
| Lokal | localhost:8080 | kendi `config.php`'niz | |

DEV ve PROD ayrı DB kullanır — bu ortam modeli sadece KOD dağıtım hedefidir, DEV'de test edilen VERİ
PROD'a taşınmaz/senkron edilmez. Aynı migration seti her iki ortama da elle/`guncelle.php` ile
uygulanır ama **veri** paylaşılmaz. Bir ortamda çalışan bir sorgu diğerinde tablo/kolon eksikliğinden
patlayabilir eğer migration sırası atlanmışsa — bkz. aşağıdaki "Şema Sapması (Drift) Riski".

## Migration Mekanizması
- Dosyalar: `database/migrations/NNN_aciklama.sql`, sıra numarasıyla (şu an 001–047).
- Lokal/CLI erişimi olan ortamda `migrate.php` çalıştırır.
- Prod'da gerçek deploy aracı `guncelle.php` (repo dışı, `~/Desktop/ACANS-GUNCELLEME/` ve
  `~/Desktop/PRIMAC-GUNCELLEME/` klasörlerinde) — kendi `schema_migrations` tablosuyla hangi
  dosyanın uygulandığını takip eder.
- **Tuzak**: Migration bazen phpMyAdmin → İçe Aktar ile ELLE çalıştırılıyor (CLI erişimi olmayan
  bir ortamdan deploy yapılırken). Bu durumda `schema_migrations`'a satır düşmez, bir sonraki
  `guncelle.php` çalıştırmasında aynı migration TEKRAR uygulanır. Unique kısıtı olmayan
  `INSERT`'lerde bu mükerrer veri üretir. Elle migration çalıştırılan her durumda hemen ardından
  `schema_migrations`'a `INSERT IGNORE` ile ilgili dosya adı elle işaretlenmeli. Detay →
  `memory/deploy.md` (2026-07-02 kaydı).

## Şema Sapması (Drift) Riski
İki ayrı DB olduğu için kodun varsaydığı bir tablo/kolon bir ortamda migration eksikliğinden
gerçekte yok olabilir ("tablo bulunamadı"/"bilinmeyen kolon" hataları). Bilinen somut örnek:
commit `3137e68` — `stock_movements` tablosunda kodun varsaydığı bir kolon/görünüm
(`product_view.php`/`stock_movement_new.php` akışı) bir ortamda eksikti. Bu tür sapmaları
denetlemek için proje ajanı **`ots-schema-drift-guard`** var (bkz. `CLAUDE.md`) — yeni bir sorgu
yazıldığında veya "tablo/kolon bulunamadı" hatası bildirildiğinde proaktif kullanılmalı.

## Tablo Envanteri (migration kaynağına göre gruplu)

### Kimlik / Personel (001)
- `app_users` — giriş yapan hesaplar (username, permissions JSON, personnel_id, reset_token, last_seen)
- `personnel` — personel kartları (cv_path → 036)
- `personnel_devices` — cihaz/push eşlemesi
- `activity_logs` — genel aktivite log (immutable DEĞİL, bkz. `audit_log` 028)

### CRM (002)
- `contacts` — cariler (phone2/website/postal_code/iban → 018)
- `contact_representatives` — cari temsilcileri

### İş / Görev (003)
- `jobs` — üretim/iş takibi (overdue_notified_at, produce_item_id/qty/produced → 013/016)
- `job_stages` — iş aşamaları
- `job_files` — işe ekli dosyalar
- `tasks` — görevler/işler (**"İşlerim" ekranının kaynağı** — bkz. `PROJECT_RULES.md` kavram
  standardı; `check_note_id`-benzeri bağlantı için `task_id` kolonu `checks_notes`'ta, tersi yön)
- `work_checklists`, `work_events` — iş motoru

### Stok / Ürün (004)
- `product_brands`, `product_categories`, `product_suppliers`, `product_units`
- `stock_items` — ürün/stok kartı
- `stock_movements` — stok hareketleri (finance_movement_id → 030, bkz. schema drift notu)

### Finans (005)
- `finance_accounts` — banka/kasa/kart hesapları
- `finance_movements` — hareketler (category_id → 023, vat_rate/vat_amount → 032)

### Ticari Belgeler (006, 014, 015, 017)
- `trade_documents`, `trade_document_items` — teklif/sipariş/fatura
- `quotes`, `quote_items` — teklif modülü (firm → 015, intro_note → 017, approval_token → 029)

### CPA — Müşteriye Özel Tedarik Takibi (045, 046, 047)
- `cpa_preferences` (045) — müşteri+ürün bazında tercih edilen tedarikçi hafızası (öncelik sırası,
  varsayılan bayrağı, Aktif/Pasif — SİLİNMEZ, kaldırma status değişimiyle olur). `cpa_lib.php`
  runtime'da kendi şemasını `CREATE TABLE IF NOT EXISTS` ile kurar (proje geneli 6. kural — bkz.
  `CLAUDE.md`), migration bağımsız çalışabilir.
- `cpa_allocations` (046) — satın alınan miktarın belirli bir kısmının belirli bir müşteriye
  TAHSİS edilmesi (`purchase_movement_id`+`stock_item_id`+`customer_id`, `allocated_qty`/
  `consumed_qty`, status Aktif/Tüketildi/İptal). `purchase_movement_id` bilerek
  `finance_movements.id`'ye bağlanır (`stock_movements.id`'ye DEĞİL — bir alış düzenlendiğinde
  stock_movements satırları silinip yeniden oluşturuluyor, o id'ye bağlansaydı tahsisler öksüz
  kalırdı). "Serbest stok" hiçbir yerde saklanmaz, her zaman `stock_items.quantity - SUM(aktif
  tahsis kalanı)` formülüyle türetilir.
- `cpa_allocation_consumptions` (047) — bir satışın hangi tahsis(ler)den ne kadar tükettiğinin
  defteri (`allocation_id`+`sale_movement_id`+`consumed_qty`). Satış düzenlenince/silinince
  `cpa_alloc_reverse_for_sale()` bu defterden okuyup geri alır ve satırları SİLER (idempotent —
  aynı satış için ikinci "reverse" çağrısı no-op olur, çift iade oluşmaz).
- **Şema otoritesi farkı (bilerek)**: `cpa_allocations`/`cpa_allocation_consumptions`
  (`cpa_allocation_lib.php`) runtime'da CREATE TABLE YAPMAZ — 046/047 migration'ları TEK otoritedir,
  migrate.php çalıştırılmadan yazma fonksiyonları (create/reduce/cancel/transfer) açık hata verir,
  otomatik satış-tüketim kancaları sessizce no-op olur. Bu, proje geneli "IF NOT EXISTS ile
  dayanıklı ol" kuralının BİLİNÇLİ bir istisnasıdır (P0 CPA Veri Bütünlüğü Kapanışı, 2026-07-18,
  Product Owner kararı: "migrate.php uygulanmadan özellik aktifmiş gibi davranmasın") — `cpa_preferences`
  (045) bu istisnanın DIŞINDA, eski davranışını korur.

### Mesajlaşma / Bildirim / Push (007, 009, 010)
- `internal_messages` — 1-1 ve grup mesajları (thread_id → 010, `sender_user_id` NULL olabilir ama
  bu bir "hayalet mesaj" bug sınıfı yaratıyor, bkz. `KNOWN_BUGS.md` ve migration 022/038)
- `internal_notifications` — bildirimler (action_url → 021, sahiplik kontrolü eksik, bkz. `KNOWN_BUGS.md`)
- `push_subs` — Web Push abonelikleri
- `chat_typing` — "yazıyor..." göstergesi
- `chat_threads`, `chat_thread_members` — grup/iş/cari sohbetleri

### Diğer / Talep (008)
- `management_requests` — personelden yönetime talep akışı

### İş Notları / Üretim (012, 013)
- `job_notes` — işe zaman damgalı not

### Muhasebe (020, 023, 031, 035)
- `accounting_categories`, `accounting_entries` (vat_mode/vat_rate/vat_amount → 031, finans ile
  birleşme → 035 — bkz. `memory/features.md` "Finans/Muhasebe iki ayrı defterdi" kaydı)

### Çek / Senet (024, 026, 027, 033, 034)
- `checks_notes` — vade takibi (attachment → 026, task_id → 027, direction → 033, finans bağlantısı → 034)

### Denetim (028)
- `audit_log` — DEĞİŞMEZ (immutable) kim-ne-zaman-eski→yeni-değer kaydı (activity_logs'tan farklı,
  o silinebilir/genel; audit_log hassas alanlar için)

### İş Logları (025)
- `job_logs` — `job_view.php` "Zaman Çizelgesi" bölümünün kaynağı

### Kişisel Not/Görev (037)
- `personal_notes` — "Notlarım" (user_id ile sıkı filtreli, `tasks`'tan bilerek AYRI tablo —
  gizlilik garantisi, hiçbir admin görünümü buna dokunmuyor)

## Yetki Modeli (DB'ye bağlı ama ayrı bir konsept)
`app_users.permissions` JSON kolonu, `boot.php`'deki `module_list()`/`page_module_map()`/`user_can()`
üçlüsüyle birlikte çalışır — bir sayfa `page_module_map()`'te bir modüle bağlıysa `require_permission()`
otomatik uygulanır. Personelin KENDİNE ait kayda (job_view.php GET, mytasks.php, mytask_new.php gibi)
erişimi bilinçli olarak bu haritanın DIŞINDA tutulur (bildirimden kendi işini/görevini açabilsin diye)
— YAZMA işlemleri ayrıca kontrol edilmeli (bkz. `memory/features.md` "5-ajan güvenlik denetimi").

## Referanslar
Deploy detayları → `memory/deploy.md`. Şema/veri bug geçmişi → `memory/bugs.md`, `KNOWN_BUGS.md`.
Migration yazım kuralları → `.claude/agents/` içindeki `ots-db-migration-dev` (Burak) ajanı.
