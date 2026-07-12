# Bilinen Sorunlar

<!-- Çözülen ve açık bug'lar. Çözüldüğünde tarih+çözüm notu ekleyip üstte "ÇÖZÜLDÜ" ile işaretle, silmeyin. -->

## Açık

- **Sabit migration/temizlik anahtarı**: `migrate.php` ve `temizle.php` içinde aynı sabit kodlanmış
  `acans-migrate-2026` anahtarı var. Admin oturumu zaten yeterli yetki kontrolü sağlıyor, anahtar sadece
  kurulum-öncesi (kullanıcı yokken) bypass amaçlı. Düşük risk ama repo genel erişime açılırsa (public repo)
  değiştirilmeli.
- **Bildirim id'lerinde sahiplik kontrolü yok (IDOR, düşük öncelik)**: `notifications.php` (web)
  `?read=<id>` ve `mobile/notifications.php` `?del=<id>` başka bir kullanıcıya ait bildirimi
  `target_user_id` kontrolü yapmadan okundu işaretleyebiliyor/silebiliyor (id tahmin edilebilir).
  `ots-security-auditor` 2026-07-02 denetiminde bulundu, önceden beri vardı (bu oturumun ürünü değil).
  İçerik zaten çoğunlukla dahili/az hassas olduğu için düşük öncelikli, ama düzeltilmeli: `WHERE id=? AND
  (target_user_id IS NULL OR target_user_id=?)` şeklinde oturum kullanıcısı kontrolü eklenmeli.

## Çözüldü

- **contact_report.php sol menüde yanlış yetki şartına bağlıydı** (2026-07-13, WEB UI ALIGNMENT &
  NAVIGATION SPRINT 001, commit `9bdff1e`, Elif/parity-auditor'da bulundu): `layout_top.php`'de
  `contacts_report.php` linki `user_can('report')` şartı altında gösteriliyordu, ama gerçek sayfa
  erişimi `page_module_map()`'te `contacts` modülüne bağlıydı. Sonuç: `contacts` yetkisi olup
  `report` yetkisi olmayan personel linki menüde GÖRMÜYORDU ama URL'yi bilirse sayfaya erişebiliyordu;
  tersi durumda ise linke tıklayan kullanıcı "yetkisiz" hatası alıyordu. Pre-existing (bu sprint
  yaratmadı, sadece grubu Muhasebe İşlemleri'nden Raporlama'ya taşırken fark edildi) — mobil
  tarafı zaten doğruydu (`user_can('contacts')`). Web artık mobille aynı şarta bağlı.
- **mobile/purchase.php + mobile/sales.php "🧾 Belgeyi Aç" linki 404 veriyordu** (2026-07-12,
  commit `1cb9e31`, Ece/code-review'da bulundu — bkz. [[features]] "Finance CRUD UX Patch 001"):
  Flow Unification 001'de (commit `d518103`) eklenen link `href="trade_document_view.php?id=..."`
  yazılmıştı — ama `trade_document_view.php` sadece repo kökünde var, `mobile/` altında yok. Mobil
  sayfalarda kök dizindeki bir dosyaya link verirken `../` öneki gerekiyor (bkz. `mobile/job_view.php`,
  `mobile/profile.php` gibi mevcut örnekler) — bu unutulmuştu. `../trade_document_view.php?id=...`
  olarak düzeltildi.
- **contact_view.php + finance_account_view.php'de "Tip"/"Tür" etiketi satış/alış kaynaklı
  satırları yanlış gösteriyordu** (2026-07-12, commit `1cb9e31`): direction'a (`in`/`out`) bakarak
  düz "Tahsilat"/"Ödeme" yazan eski kod, `finance_movement_type_label()` merkezi fonksiyonu
  ÇAĞIRMIYORDU — bu fonksiyon Finans Çekirdek Stabilizasyonu sırasında `finance.php` gibi diğer
  ekranlara uygulanmıştı ama bu iki dosyada unutulmuş kalmıştı (contact_view.php'deki Finance CRUD
  UX Patch 001 çalışması sırasında fark edildi; finance_account_view.php'deki eşi Ece'nin code
  review'unda bulundu). İkisi de düzeltildi.
- **İki paralel alış/satış veri modeli — `trade_documents` akışı Finance Core Stabilization
  kapsamı dışında kalmış** (2026-07-11, kullanıcı BUG REPORT: `ALI-20260707-5177` cari ekranında/
  purchase.php listesinde tutarsız görünüyordu; çözüm commit `d518103`, bkz. [[features]] "Flow
  Unification 001"): Kök neden doğrulandı — bu bir görüntüleme hatası değil, ana menüde aynı anda
  İKİ bağımsız "alış/satış girişi" akışı canlıydı: `purchase.php`/`sales.php` (Finance Core
  Stabilization kapsamında, `movement_type='purchase'/'sale'`) ile `trade_document_new.php`
  (`trade_core.php::trade_apply_document()` üzerinden, ayrı `movement_type='document'`, hâlâ ödeme
  hesabı seçtiren ve `finance_accounts.current_balance`'ı doğrudan güncelleyen bağımsız bir kod
  yolu). `trade_apply_document()` artık kendi inline stok/finans mantığını yazmıyor,
  `stock_lib.php::stock_create_purchase()`/yeni `stock_create_sale()` fonksiyonlarını kullanıyor —
  belge kaynaklı kayıtlar artık `movement_type='purchase'/'sale'` + `document_id` ile tek modele
  bağlı, `account_id` her zaman NULL, durum her zaman `Bekliyor`. Transaction sahipliği
  `trade_document_new.php`'ye taşındı (belge+satır+stok+finans tek transaction, hata olursa tamamı
  rollback). Eski veriye dokunulmadı (backfill/toplu UPDATE yok).
  **Yan bulgu — aynı sprintte kapatıldı:** kod incelemesinde (Ece, Elif) `stock_can_edit_purchase()`/
  `stock_can_edit_sale()`/`stock_reverse_purchase()`/`stock_reverse_sale()` fonksiyonlarının
  `document_id`'yi hiç kontrol etmediği bulundu — web'de bu sadece listede Düzenle/Sil butonunu
  gizliyordu (backend'de hâlâ açıktı, crafted POST ile bypass edilebilirdi), mobilde hiç koruma
  yoktu (buton gösteriliyordu). `trade_apply_document()`'ın artık `movement_type='purchase'/'sale'`
  kullanması bu satırları ilk kez "Son Alışlar/Satışlar" listelerine (dolayısıyla Düzenle/Sil'e)
  maruz bıraktığı için risk bu sprintle büyümüştü. Dört fonksiyona da `document_id IS NOT NULL`
  kilidi eklendi (tek merkezden hem web hem mobili kapsıyor); Selin'in önerisiyle
  `stock_update_purchase()`/`stock_update_sale()`'e de aynı kilit savunma-derinliği olarak eklendi.
  `mobile/purchase.php`/`mobile/sales.php` listeleri de web ile aynı "🧾 Belgeyi Aç" davranışına
  getirildi. USER TEST: DEV'de kullanıcı testi bekliyor (bkz. [[features]]).
- **Satışta sessiz negatif stok — görünürlük/onay yoktu** (2026-07-11, commit `3d927c7`, USER
  TEST: Web PASS / Mobile Pending, bkz. [[features]] "Kontrollü Negatif Stok Politikası"): satış
  oluşturma/düzenlemede stok yetersiz
  olsa da hiçbir uyarı çıkmadan işlem tamamlanıp stok eksiye düşüyordu — kullanıcının kendisi
  DEV'de fark edip bildirdi. **Not: negatif stok kasıtlı olarak YASAKLANMADI** (satın alımdan önce
  satış siparişi girilebilmesi gerçek bir iş akışı) — çözüm, `StockShortageException` +
  `allow_negative_stock` backend onayı ile "sessiz" olan kısmı kapatmak oldu: yetersiz stokta artık
  açık bir uyarı + onay adımı var, onaylanırsa satış açıklamasına görünür bir işaret ekleniyor.
  İlk düzeltme turu (commit `b536494`) yanlışlıkla sert bir RET eklemişti, kullanıcı DEV testinde
  bunun gerçek iş akışını bozduğunu bildirince geri alındı (commit `e330b99`) ve doğru (onay
  bazlı) çözümle değiştirildi.
- **Cari bakiye double-counting (satış + kendi tahsilatı aynı yönde toplanıyordu)** (2026-07-11,
  commit `d02665b`, bkz. [[features]] "Finance Core Stabilization"): `finance_movements`'ta bir
  satışın kendisi VE onun sonradan girilen tahsilatı ikisi de `direction='in'` ile toplandığı için
  cari açık bakiye olduğundan yüksek/yanlış görünüyordu — bu sorunun ilk somut belirtisi bu
  oturumun başında "Yasmin Gelişim Merkezi" cari kaydında yaşanan mükerrer kayıt olayıydı.
  `contacts_lib.php::contact_balance_case_sql()` ile kalıcı çözüldü: Tahsilat/Ödeme artık ters
  işaretle sayılıyor. Kök çözümün bir parçası olarak satış/alış ekranları da artık hiç kasa/banka
  etkilemiyor (sadece "Bekliyor" açık borç), ödeme yöntemi seçimi kaldırıldı. USER TEST: Web PASS,
  mobil doğrulaması ayrı Mobile Regression Sprint'te (bkz. [[backlog]]).
- **Web'de bildirime tıklayınca mobile'a zıplama + hayalet "okunmamış mesaj" rozeti + mobil görev
  yetki açığı** (2026-07-03, commit bb8a710): Kullanıcı PRIMAC'ta test etti — zip iki kere yüklenmiş
  olmasına rağmen "bildirim var ama mesaj yok" ve "web'de bildirime tıklayınca mobil ekrana düşüyorum"
  şikayetleri sürdü. Kök nedenler kod incelemesiyle bulundu (deploy sorunu değildi):
  1. Web'de `mytasks.php` hiç yoktu → `notifications.php`'deki mobile-fallback (dosya web'de yoksa
     mobile/'a yönlendir) her zaman tetikleniyordu. Web `mytasks.php` eklendi (mobile/mytasks.php
     paritesi, 'tasks' yetkisi istemiyor), `task_new.php`'nin bildirim action_url'i `tasks.php`'den
     `mytasks.php`'ye çevrildi, `takvim.php`'deki eski "yetkisizse düz metin" iş-around'ı kaldırıldı.
  2. `messages.php`/`mobile/messages.php` kişi listesi sadece `active=1` kullanıcıları gösteriyordu;
     `mobile/common.php`'deki küresel okunmamış-mesaj rozeti ise `active` şartı olmadan sayıyordu —
     deaktif bir kullanıcıyla (veya sender_user_id NULL hayalet satırla) geçmiş varsa rozet "1"
     gösteriyor ama hiçbir sohbette mesaj görünmüyordu. Kişi listesi artık geçmişi olan deaktif
     kullanıcıyı da gösteriyor; rozet sorguları (`unread_msg()`, `poll.php`) NULL sender'ı saymıyor;
     migration 038 birikmiş hayalet satırları temizliyor (022'nin tekrarı — muhtemelen 022'den sonra
     yeniden birikmiş veya farklı bir kod yolundan gelmiş).
  3. Bu incelemenin yan ürünü: `mobile/mytasks.php` görev durumu güncelleme sorgusunda `personnel_id`
     kontrolü YOKTU — herhangi bir oturum, `tid` bilerek/tahmin ederek başkasının görevini
     güncelleyebilirdi (ots-code-reviewer denetiminde bulundu, önceden beri vardı). `AND
     personnel_id=?` eklenerek kapatıldı, web sürümü zaten bu korumayla yazılmıştı.
  Ayrıca (ilgisiz ama aynı oturumda bulunan görsel bug): mobil mesaj composer'daki emoji butonu
  (`share_lib.php: emoji_picker_html()`) flex satırında sıkışıp "😀"/"Emo" olarak 2 satıra
  bölünüyordu → `flex:0 0 auto` + `white-space:nowrap` ile düzeltildi.
- **Bildirim `action_url` eksikliği + mesaj görünürlük hatası** (2026-07-02): bkz. [[features]] "Bildirim
  action_url + mesaj görünürlük onarımı". Ek olarak: bu düzeltme `notifications.php`'deki tablo adı hatasını
  giderince, daha önce hiç çalışmayan `?go=` redirect kod yolu ilk kez fiilen erişilebilir hale geldi ve
  `ots-security-auditor` bunun bir open-redirect olduğunu tespit etti (`$_GET['go']` whitelist'siz
  `header("Location: ...")`'a gidiyordu). Aynı oturumda `notifications.php`'ye `^(https?:)?//` ile başlayan
  go değerlerini `dashboard.php`'ye düşüren bir kontrol eklendi.
- **Mesajlaşma çoklu dosya "bağlantı hatası"** (2026-06-30): AJAX yanıtına PHP uyarısı karışıp JSON'u
  bozuyordu → ob_start/ob_end_clean + display_errors off ile çözüldü. Bkz. [[features]].
- **Teklif PDF ikinci sayfaya taşma** (2026-06-30): html2canvas flexbox render etmiyordu → footer
  absolute-bottom + JS ile sabit A4 yükseklik. Bkz. [[features]].
- **RAPOR.md'de "yapılmadı" işaretli 6 madde aslında tamamlanmış** (2026-07-02 denetiminde bulundu):
  mobile/sales.php, mobile/job_view.php, mobile/jobs.php, tasks.php, search.php, raporlama — hepsi kodda
  çalışır durumda. RAPOR.md güncellenmeden bırakıldığı için yanlış proje durumu izlenimi veriyordu;
  dosya kaldırıldı, güncel kalan açık maddeler [[backlog]]'a taşındı.


- **Mesajlaşma çoklu dosya "bağlantı hatası"** (2026-06-30): AJAX yanıtına PHP uyarısı karışıp JSON'u
  bozuyordu → ob_start/ob_end_clean + display_errors off ile çözüldü. Bkz. [[features]].
- **Teklif PDF ikinci sayfaya taşma** (2026-06-30): html2canvas flexbox render etmiyordu → footer
  absolute-bottom + JS ile sabit A4 yükseklik. Bkz. [[features]].
- **RAPOR.md'de "yapılmadı" işaretli 6 madde aslında tamamlanmış** (2026-07-02 denetiminde bulundu):
  mobile/sales.php, mobile/job_view.php, mobile/jobs.php, tasks.php, search.php, raporlama — hepsi kodda
  çalışır durumda. RAPOR.md güncellenmeden bırakıldığı için yanlış proje durumu izlenimi veriyordu;
  dosya kaldırıldı, güncel kalan açık maddeler [[backlog]]'a taşındı.
