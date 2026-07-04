# KNOWN_BUGS.md — Bilinen Hatalar (Hızlı Bakış)

Bu dosya `memory/bugs.md`'nin kök dizindeki hızlı-erişim özetidir. Tam geçmiş (çözülenler dahil,
tarih + kök neden analizi) için `memory/bugs.md`'ye bakın — burada sadece **şu an açık** olanlar
ve en yakın zamanda çözülenlerin kısa özeti tutulur.

## Açık

1. **Sabit migration/temizlik anahtarı** — `migrate.php` ve `temizle.php` aynı sabit kodlanmış
   `acans-migrate-2026` anahtarını paylaşıyor. Admin oturumu zaten yeterli kontrol sağlıyor, anahtar
   sadece kurulum-öncesi (kullanıcı yokken) bypass amaçlı. Repo public olursa değiştirilmeli.

2. **`tasks` tablosunda kayıt kaynağı ayrımı yok** — Admin'in başkasına atadığı iş (`task_new.php`)
   ile kullanıcının kendine eklediği iş (`mytask_new.php`, 2026-07-03'te eklendi) aynı tabloya,
   ayrım kolonu olmadan yazılıyor. `tasks.php` ("Tüm Görevler") admin görünümünde ikisi görsel
   olarak ayırt edilemiyor. Güvenlik açığı değil, izlenebilirlik eksikliği — gerekirse `created_by`
   kolonu eklenebilir (yeni migration gerektirir).

## Son Çözülenler (detay → `memory/bugs.md`)

- **Mesaj rozeti kalıcı şişiyordu (Sprint-001 DEV testinde bulundu, 2026-07-04, `0ba36da` ile
  commit edildi)** — `notes_lib.php`'nin "Kendime Not Ekle" kendine-mesaj kaydı `is_read=0` ile
  oluşuyordu; mesaj kişi listesi kullanıcının kendisini hariç tuttuğu için bu mesaj hiçbir zaman
  okundu işaretlenemiyordu, 💬 rozeti kalıcı olarak artık sayı gösteriyordu. `is_read=1` yapıldı.
- **Emoji butonu hâlâ taşıyordu (Sprint-001 DEV testinde bulundu, 2026-07-04)** — önceki turda
  panelin konumu düzeltilmişti ama butonun kendisi composer'ın genel `.composer button{width:50px}`
  kuralına takılıp "😀 Emoji" metniyle taşmaya devam ediyordu (gerçek DEV testinde primac.tr'de
  gözlemlendi). Metin kaldırıldı, ikon-only yapıldı (📎/🎤 ile tutarlı) — kök neden tamamen kapandı.
- **Bildirim toplu silme TÜM kullanıcıları etkiliyordu (Sprint-001, 2026-07-04, `0ba36da` ile
  commit edildi)** — Önceki madde 1'de "düşük risk IDOR" olarak tanımlanmıştı ama gerçek kapsamı daha
  ciddiydi: `mobile/notifications.php`'deki "Okunanları Sil" SİSTEMDEKİ TÜM kullanıcıların okunmuş
  bildirimlerini, "Tümünü Sil" TÜM bildirimleri (herkesinkini) siliyordu, tekil `?del=` de sahiplik
  kontrolü yapmıyordu. Kök çözüm: yeni `user_notification_status` tablosu (migration 039) +
  `notifications_lib.php` — genel bildirimler artık asla fiziksel silinmiyor, her kullanıcının
  okuma/gizleme durumu ayrı tutuluyor, kişisel bildirimi sadece sahibi silebiliyor. Web'e de aynı
  (artık güvenli) silme/temizleme özelliği eklendi (önceden web'de bu özellik hiç yoktu).
- **Web'de bildirime tıklayınca mobile'a zıplama + hayalet "okunmamış mesaj" rozeti + mobil görev
  yetki açığı** (2026-07-03, commit `bb8a710`) — kök neden: web'de `mytasks.php` yoktu, mesaj kişi
  listesi ile rozet sayaçları farklı `active`/`sender_user_id` kriterleri kullanıyordu, mobil görev
  güncellemede `personnel_id` kontrolü eksikti. Üçü de kapatıldı.
- **Bildirim `action_url` eksikliği + mesaj görünürlük hatası + open-redirect** (2026-07-02).
- **Mesajlaşma çoklu dosya "bağlantı hatası"** (2026-06-30) — AJAX yanıtına PHP uyarısı karışması.
- **Teklif PDF ikinci sayfaya taşma** (2026-06-30).

## Referanslar
Tam geçmiş → `memory/bugs.md`. Açık geliştirme kararları → `ROADMAP.md`. Kural/öncelik → `PROJECT_RULES.md`.
