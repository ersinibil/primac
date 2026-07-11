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
