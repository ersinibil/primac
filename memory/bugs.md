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
