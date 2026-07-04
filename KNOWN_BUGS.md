# KNOWN_BUGS.md — Bilinen Hatalar (Hızlı Bakış)

Bu dosya `memory/bugs.md`'nin kök dizindeki hızlı-erişim özetidir. Tam geçmiş (çözülenler dahil,
tarih + kök neden analizi) için `memory/bugs.md`'ye bakın — burada sadece **şu an açık** olanlar
ve en yakın zamanda çözülenlerin kısa özeti tutulur.

## Açık — SYSTEM AUDIT MODE bulguları (2026-07-04, read-only denetim, henüz düzeltilmedi)

1. **YÜKSEK — `sifre_sifirla.php` brute-force koruması yok** — 6 haneli kod (`random_int(100000,999999)`),
   30 dakika geçerli, deneme sayısı sınırı/lockout yok.
2. **ORTA-YÜKSEK — `accounting.php` yansıyan XSS** — satır 8, 111-130: `$_GET['tab']` escape
   edilmeden href'e basılıyor (`h()`/`htmlspecialchars()` eksik). Mobilde bu parametre kullanılmıyor.
3. **ORTA — `users.php` rol yükseltme** — satır 41-51: `users` modül yetkisi olan admin-olmayan
   biri kendini/başkasını `admin` rolüne yükseltebilir, `uid===1` dışında kısıtlama yok.
4. **ORTA — `is_admin()` session'da bayatlıyor** — `boot.php:124-127`, `user_can()` gibi DB'den
   taze okumuyor; rolü alınan bir admin aktif oturumu boyunca admin yetkisiyle işlem yapabilir.
5. **ORTA — Login'de session fixation koruması yok** — `index.php:12-40`, başarılı girişte
   `session_regenerate_id(true)` çağrılmıyor.
6. **DÜŞÜK — `cron.php:7`'de yeni sabit anahtar** (`acans-cron-2026`) — `acans-migrate-2026`'dan
   farklı, daha önce raporlanmamış.
7. **BİLGİ — Proje genelinde CSRF token mekanizması yok.**

Tam denetim raporu (mimari/performans/UX/veri modeli dahil) → System Audit Artifact (2026-07-04),
öncelik sırası → `ROADMAP.md` "SYSTEM AUDIT — Teknik Borç ve Öncelikler" bölümü.

## Açık — önceki turlardan

8. **Sabit migration/temizlik anahtarı** — `migrate.php` ve `temizle.php` aynı sabit kodlanmış
   `acans-migrate-2026` anahtarını paylaşıyor. Admin oturumu zaten yeterli kontrol sağlıyor, anahtar
   sadece kurulum-öncesi (kullanıcı yokken) bypass amaçlı. Repo public olursa değiştirilmeli.

## Son Çözülenler (detay → `memory/bugs.md`)

- **`tasks` tablosunda kayıt kaynağı ayrımı yoktu (SPRINT-003, 2026-07-04, henüz commit
  edilmedi)** — İşlerim ekranı Düzenle/Detay/Sil işi sırasında migration 040 ile `tasks.created_by`/
  `updated_by` kolonları eklendi, `task_new.php`/`mobile/task_new.php`/`mytask_new.php`/
  `mobile/mytask_new.php` artık `created_by` dolduruyor — admin'in atadığı iş ile kullanıcının kendine
  eklediği iş artık ayırt edilebilir.
- **YÜKSEK — `mobile/task_view.php` IDOR (SPRINT-003, 2026-07-04, henüz commit edilmedi)** —
  `task_status` güncellemesi `WHERE id=?` kullanıyordu, sahiplik kontrolü (`AND personnel_id=?`)
  yoktu; herhangi bir giriş yapmış kullanıcı `?id=` değiştirerek başkasının görev durumunu
  değiştirebiliyordu. İşlerim ekranı Düzenle/Detay/Sil işi sırasında bulunup `tasks_lib.php::
  task_can_edit()` ile kapatıldı (bonus düzeltme, asıl görev bu değildi).
- **KRİTİK — `mobile/personnel_view.php` keyfi şifre sıfırlama (SECURITY SPRINT-001,
  2026-07-04)** — `reset_pw` POST işlemi hedef hesabı `$_POST['uid']`'den değil, DB'den
  görüntülenen personele (`$id`, `app_users.personnel_id`/`personnel.user_id` bağı) göre
  çekecek şekilde değiştirildi; artık POST'a hangi `uid` yazılırsa yazılsın sadece o personelin
  gerçek hesabı etkileniyor. Kullanılmayan gizli `uid` form alanı da kaldırıldı. Henüz DEV'de
  (primac.tr) smoke test edilmedi.
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
