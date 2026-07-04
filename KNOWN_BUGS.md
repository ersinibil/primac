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

## Açık — DEV QA'da SERVER-SIDE PASS aldı, gerçek iPhone Safari cihaz testi bekliyor (2026-07-05)

9. **PWA Push — Safari'de arka planda/uygulama kapalıyken bildirim gelmiyor.** `push_to_user()`'a
   loglama eklendi (`push_log()`, `push_debug.log`) ve yerel QA'da hem başarı (gerçek bir Chrome/FCM
   aboneliğine gönderim, sunucu 201 "OK" ile kabul etti) hem başarısızlık (410 Gone) senaryosu uçtan
   uca doğrulandı — **SERVER-SIDE PASS**. Ancak Safari/iOS'a özgü arka plan teslimatı bu ortamdan
   (gerçek bir iOS aboneliği yok) test edilemedi. primac.tr'de gerçek bir iPhone'dan test + başarısız
   olursa `push_debug.log` kontrolü hâlâ gerekiyor (bkz. `ROADMAP.md` "Sıradaki sıra").
10. **Mobil Mesajlaşma — liste/composer arası boşluk (regresyon).** CSS özgüllük çakışması
    (`body.chat-mode` vs `body.kb`) `body.chat-mode.kb{padding-bottom:0}` ile düzeltildi, DEV QA'da
    render edilen sayfada kural varlığı ve üstünlüğü (2 sınıf > 1 sınıf, sıradan bağımsız) doğrulandı
    — **CONDITIONAL PASS**. Piksel-seviye görsel doğrulama (gerçek klavye açılışı) yerel ortamda
    yapılamadı, gerçek iPhone Safari cihaz testi bekliyor.
11. **WhatsApp — gelen (inbound) mesajlar sistemde görünmüyor.** API kısıtı değil — mevcut entegrasyon
    (`share_lib.php`, UltraMsg) sadece gönderim yapıyor, hiçbir webhook alıcı endpoint'i veya
    konuşma geçmişi tablosu yok. Eklemek yeni webhook + yeni tablo gerektirir (yeni özellik),
    kullanıcı onayı bekliyor.

## Son Çözülenler (detay → `memory/bugs.md`)

- **Takvimde aynı çek/senet iki kez görünüyordu (UX/STABILITY PATCH-002, 2026-07-05)** —
  `checks_notes.php` (web) `save_cn` işleminde PRG (redirect) yoktu, sayfa yenilenince form
  yeniden POST edilip ikinci bir çek/senet kaydı + ikinci bir otomatik hatırlatma görevi
  oluşuyordu (takvime düşen bu görev "iki kayıt" görünümüne sebep oluyordu). PRG eklendi.
- **Takvim günlük detay filtresi "çalışmıyor" gibi görünüyordu (UX/STABILITY PATCH-002,
  2026-07-05)** — `takvim.php`'nin AY IZGARASI bir gün seçilse de her günün madde başlıklarını
  basmaya devam ediyordu (alttaki detay paneli aslında doğru filtreleniyordu). Bir gün
  seçiliyken diğer günler artık sadece bir sayı rozeti gösteriyor.
- **"Son İşlemler" birçok kayıtta yanlış/kırık sayfaya gidiyordu (UX/STABILITY PATCH-002,
  2026-07-05)** — 11 `activity_log()` çağrısı web/mobil arasında isim uyuşmazlığı yüzünden
  (`kasa.php`↔`finance.php`, sadece mobilde olan `personnel_view.php` vb.) yanlış platforma veya
  404'e gidiyordu. `base_url()` ile mutlak path / gereksiz `mobile/` önekinin kaldırılmasıyla
  düzeltildi.
- **Teklif listesinde/detayında Düzenle-Sil-Detay eksikti (UX/STABILITY PATCH-002, 2026-07-05)** —
  liste satırına görünür "Detay" bağlantısı eklendi; Düzenle (web+mobil) ham `is_admin()` yerine
  projenin kademeli `can_edit_delete()` yetkisini kullanacak şekilde düzeltildi.
- **Mesajlaşmada liste/composer arası boşluk (regresyon, UX/STABILITY PATCH-002, 2026-07-05)** —
  `body.chat-mode` ile `body.kb` CSS kuralları aynı özgüllükte çakışıyordu, klavye açılınca
  `chat-mode`'un sıfırladığı boşluk geri geliyordu. Bileşik seçici (`body.chat-mode.kb`) ile
  kalıcı olarak düzeltildi.
- **Web takvimde gün tıklama hiçbir şey yapmıyordu (SPRINT CLOSE, 2026-07-04, `d7c593a`)** —
  `takvim.php`'de gün numarasının hiç linki yoktu, ay ızgarası zaten tüm günleri iç içe gösterdiği
  için tıklamanın hiçbir etkisi olamazdı. Mobildeki `?g=` gün-filtreleme deseni web'e de eklendi.
- **Takvimde silinmiş görev hâlâ görünüyordu (SPRINT CLOSE, 2026-07-04, `013e96a`)** — hem
  `takvim.php` hem `mobile/calendar.php`'nin `tasks` sorgusunda `deleted_at IS NULL` eksikti.
- **Takvimde görev/not linkleri yanlış sayfaya gidiyordu (SPRINT CLOSE, 2026-07-04, `9b5eb33`)** —
  web'de notlar alakasız `dashboard.php`'ye, görevler her zaman genel `mytasks.php`'ye gidiyordu.
  Artık `notes.php` / spesifik `task_view.php?id=`'e gidiyor.
- **Web'de mesaj bildirimi hiç yoktu (SPRINT CLOSE, 2026-07-04, `03a0df3`)** — web'de mobildeki
  `unread_msg()` rozetinin eşdeğeri yoktu (kapatıldı) VE web push bildirimi için hiçbir service
  worker/kayıt kodu yoktu (sıfırdan inşa edildi, gerçek Chromium ile uçtan uca doğrulandı).
- **`tasks` tablosunda kayıt kaynağı ayrımı yoktu (SPRINT-003, 2026-07-04, `5fb2c43`)** — İşlerim
  ekranı Düzenle/Detay/Sil işi sırasında migration 040 ile `tasks.created_by`/`updated_by`
  kolonları eklendi, `task_new.php`/`mobile/task_new.php`/`mytask_new.php`/`mobile/mytask_new.php`
  artık `created_by` dolduruyor — admin'in atadığı iş ile kullanıcının kendine eklediği iş artık
  ayırt edilebilir.
- **YÜKSEK — Web'den Satın Alma tamamen kırıktı (LOCAL QA MODE'da bulundu, 2026-07-04,
  `697f985`)** — `stock_lib.php:84` ve `purchase.php:73,77`'de kullanılan `mm()` fonksiyonu SADECE
  `mobile/common.php`'de tanımlıydı; web'den (`purchase.php`) her satın alma denemesi "Call to
  undefined function mm()" ile çöküp transaction rollback oluyordu — hiçbir stok/finans kaydı
  yazılmıyordu, kullanıcıya da hata mesajı dışında bir ipucu verilmiyordu. Bugünkü sprintten ÖNCE de
  vardı (mobil akış her zaman sorunsuzdu, mm() orada tanımlıydı). `money()` (boot.php, her iki
  platformda evrensel) ile değiştirilerek kapatıldı.
- **DÜŞÜK — Muhasebe'de boş tarih SQL hatası (LOCAL QA MODE'da bulundu, 2026-07-04,
  `697f985`)** — `accounting.php`/`accounting_lib.php`/`mobile/accounting.php`'de
  `$_POST['entry_date'] ?? date('Y-m-d')` deseni boş string'i (NULL değil) yakalamıyordu, tarih alanı
  boş gönderilirse "Incorrect date value" SQL hatası veriyordu. `??` yerine `?:` ile düzeltildi.
- **YÜKSEK — `mobile/task_view.php` IDOR (SPRINT-003, 2026-07-04, `5fb2c43`)** —
  `task_status` güncellemesi `WHERE id=?` kullanıyordu, sahiplik kontrolü (`AND personnel_id=?`)
  yoktu; herhangi bir giriş yapmış kullanıcı `?id=` değiştirerek başkasının görev durumunu
  değiştirebiliyordu. İşlerim ekranı Düzenle/Detay/Sil işi sırasında bulunup `tasks_lib.php::
  task_can_edit()` ile kapatıldı (bonus düzeltme, asıl görev bu değildi).
- **KRİTİK — `mobile/personnel_view.php` keyfi şifre sıfırlama (SECURITY SPRINT-001,
  2026-07-04, `d511fad`)** — `reset_pw` POST işlemi hedef hesabı `$_POST['uid']`'den değil, DB'den
  görüntülenen personele (`$id`, `app_users.personnel_id`/`personnel.user_id` bağı) göre
  çekecek şekilde değiştirildi; artık POST'a hangi `uid` yazılırsa yazılsın sadece o personelin
  gerçek hesabı etkileniyor. Kullanılmayan gizli `uid` form alanı da kaldırıldı. primac.tr'de
  smoke test edilip PASS alındı.
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
