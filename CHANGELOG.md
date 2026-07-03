# CHANGELOG.md — Özet Değişiklik Geçmişi

Bu dosya `memory/features.md`'nin (tam gerekçe/kod detayıyla) kök dizindeki kısa özetidir — hızlı
taramak için. Detaylı "neden böyle yapıldı" analizleri için `memory/features.md`'ye bakın.

## Sprint-001 (2026-07-04, DEV — henüz commit edilmedi, release paketi yok)
8 hedef modül (İşler, İşlerim, İş Ekle, Kendime İş Ekle, Notlarım, Kendime Not Ekle, Mesajlar,
Bildirimler) tarandı; İşler temiz bulundu, diğerlerinde:
- **Mesajlar — DEV testinde bulundu, kalıcı okunmamış rozet hatası**: `notes_lib.php`'nin
  "Kendime Not Ekle" sırasında kendine attığı iç mesaj `is_read=0` ile oluşturuluyordu, ama mesaj
  kişi listesi kullanıcının kendisini hariç tuttuğu için (kendinle sohbet diye bir giriş yok) bu
  mesaj HİÇBİR ZAMAN okundu işaretlenemiyordu — 💬 rozeti kalıcı olarak şişiyordu. `is_read=1` ile
  oluşturulacak şekilde düzeltildi (kendi yazdığın bir notun kopyası için "okunmadı" uyarısı
  anlamsız). Zaten var olan takılı kalmış satırlar için tek kullanımlık `debug_unread.php` yardımcı
  script'i hazırlandı.
- **Emoji butonu hâlâ taşıyordu**: önceki turda panel konumu düzeltilmişti ama butonun kendisi
  composer'daki genel `.composer button{width:50px}` kuralı yüzünden "😀 Emoji" metniyle taşmaya
  devam ediyordu. Metin kaldırıldı, diğer ikon-only composer butonlarıyla (📎, 🎤) tutarlı hale
  getirildi (`share_lib.php`).
- **Bildirimler — güvenlik açığı kapatıldı**: `mobile/notifications.php`'deki "Okunanları Sil"/
  "Tümünü Sil"/tekil silme sahiplik kontrolü YOKTU, SİSTEMDEKİ TÜM KULLANICILARIN bildirimlerini
  silebiliyordu. Kök çözüm: yeni `user_notification_status` tablosu (migration 039) + yeni
  `notifications_lib.php` — genel (target_user_id=NULL) bildirimler artık HİÇBİR ZAMAN fiziksel
  silinmiyor, her kullanıcının okunma/gizleme durumu kendi satırında tutuluyor, kişisel bildirimi
  sadece sahibi silebiliyor. `mobile/common.php`, `layout_top.php`, `mobile/poll.php`,
  `dashboard.php` artık tek ortak sayaç/liste fonksiyonunu kullanıyor (3 yerde kopyalanmış sorgu
  kaldırıldı). Web'e mobildeki silme/temizleme butonları artık güvenli şekilde eklendi (parite).
  Ayrıca web'deki DB'de hiç var olmayan `type`/`severity` kolon referansı (dead code) kaldırıldı.
  `temizle_veri.php` + mobil karşılığı yeni tabloyu da temizleme listesine aldı.
- **İşlerim**: `mobile/mytasks.php`'deki ham int-cast sorgu prepared statement'a çevrildi (stil
  tutarlılığı, CLAUDE.md kural 2).
- **İş Ekle**: `mobile/task_new.php`'de yanlış etiketlenmiş `activity_log` kaynağı (`jobs.php` →
  `tasks.php`) düzeltildi.
- **Kendime İş Ekle**: `mytasks.php`'de daha önce hiç okunmayan `?ok=1` parametresi artık
  "İş eklendi" mesajı gösteriyor (notes.php'deki mevcut desenle aynı). Ayrıca DEV testinde bulundu:
  personel kaydı olmayan hesaplar (örn. saf admin) için hata mesajı `mytask_new.php` +
  `mobile/mytask_new.php`'de yönlendirici hale getirildi ("Genel Sistem Yönetimi > Kullanıcılar
  bölümünden personel eşleştirmesi yapabilirsiniz") — veri yapılandırması (personel eşleştirme)
  hâlâ kullanıcı tarafından `users.php` üzerinden yapılması gerekiyor, kod bunu otomatik çözmüyor.

## 2026-07-03 (en yoğun gün — çoklu tur)
- **Tek geliştirme ortamı modeli resmileşti**: DEV=primac.tr / PROD(LIVE)=acanstr.com/ots ayrımı
  kondu (`CLAUDE.md`, `PROJECT_RULES.md`, `memory/deploy.md` güncellendi). PROD'a artık SADECE
  "DEPLOY MODE" komutuyla dokunuluyor. `VERSIONING.md` (resmi sürüm dokümanı) oluşturuldu, Release
  (DEV'e paket hazırlama) ve Deploy (PROD'a DEPLOY MODE ile gönderme) süreçleri artık ayrı adımlar.
- **İşlerim/Görevlerim terim standardizasyonu + "Kendime İş Ekle"**: "Görevlerim" ifadesi projede
  her yerde "İşlerim" olarak birleştirildi (mytasks.php, mobile/mytasks.php, mobile/index.php'deki
  yanlış eşleşen kart etiketi düzeltildi). Admin'in başkasına iş ataması artık "İş Ekle" olarak
  adlandırılıyor (`task_new.php`). Yeni: kullanıcının kendine iş ekleyebildiği ayrı bir form
  (`mytask_new.php` + `mobile/mytask_new.php`, `tasks` yetkisi istemiyor). Emoji seçici paneli
  artık mesaj kutusunun ÜZERİNE binmeden (yukarı) açılıyor.
- **Web'de bildirime tıklayınca mobile'a zıplama + hayalet mesaj rozeti + mobil görev yetki açığı**
  (commit `bb8a710`): web'e `mytasks.php` eklendi, bildirim rozeti/kişi listesi sorguları tutarlı
  hale getirildi, mobil görev güncellemesine `personnel_id` kilidi eklendi. Migration 038.
  Ayrıca aynı gün önceki turda: takvime `tasks` (görevler) entegrasyonu, kişisel Not/Görev alanı
  ("Notlarım", migration 037), 5-ajan güvenlik denetimi (job_view.php yazma yetkisi, requests.php/
  activity.php/contact_documents.php IDOR kapatmaları), satış/satın alma sepet + KDV, personel
  yetki senkronu, mobil PWA offline + barkod/QR okutma, çek/senet modülü genişletmeleri, muhasebe
  KDV, kullanıcı/yetki ekranı iyileştirmeleri, stock_movements şema sapması düzeltmesi (commit
  `3137e68`), personel CV/özgeçmiş yükleme (commit `f606cf9`).
- Bu günün tam listesi (30+ madde) → `memory/features.md` üst kısmı.

## 2026-07-02
- Çek/Senet takip modülü (dosya eki + otomatik hatırlatma görevi), Global arama güvenlik düzeltmesi
  + kapsam genişletme, "Düzenleme/Silme Yetkisi" kademeli izin, Finans hesapları/hareketleri
  düzenle-sil, Marka adı yaygınlaştırma + yetki canlı yenileme, Gider kaydında kategori desteği,
  Bildirim `action_url` + mesaj görünürlük onarımı (+ open-redirect kapatma), Muhasebe modülü.

## 2026-07-01
- Sabah raporu + geciken iş sayısı bildirimi.

## 2026-06-30
- Günlük yönetici PDF raporu + talep akışı, 4 ajan turu (cari alanları, modül aktif/pasif, şifre
  sıfırlama, mesaj düzenle), Paylaşım + Teklif + Mesajlaşma onarımı, Teklif PDF + ACANS logo,
  Geciken İş + Teklif Raporu + Takvim + Web Responsive.

## Daha eskisi
Proje kuruluşundan 2026-06-30'a kadar olan temel modüller (auth, CRM, işler/görevler, stok/ürün,
finans, ticari belgeler, mesajlaşma, muhasebe...) migration `001`–`020` ile atıldı — bkz.
`DATABASE.md` tablo envanteri ve `memory/features.md`'nin alt kısımları.

## Referanslar
Tam detay → `memory/features.md`. Şema değişiklikleri → `DATABASE.md`. Açık işler → `ROADMAP.md`.
