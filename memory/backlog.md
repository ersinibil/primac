# Backlog & Yapılacaklar

<!-- Açık geliştirme görevleri. Kapanan madde buradan silinip memory/features.md'ye taşınır. -->

## Ölü link — kpi.php web'de var olmayan personnel_view.php'ye linkliyor
- `kpi.php` (web) satır ~96: `<a href="personnel_view.php?id=...">` — bu dosya web'de hiç yok (sadece
  mobilde var, `mobile/personnel_view.php`). `search.php`'nin 2026-07-02 düzeltmesinde aynı bug bulunup
  arama sonuçlarında `personnel_edit.php?id=`'e çevrildi; `kpi.php`'deki link bu turda kapsam dışı
  bırakıldı, aynı düzeltme (personnel_edit.php?id=) oraya da uygulanmalı.

## Menü eksikleri (kod var, giriş noktası yok)
- **work_center.php** ("İş Motoru") sol menüde hiç link yok. `layout_top_patch_note.txt`'den taşındı
  (2026-06-27 tarihli not, hâlâ yapılmamış). boot.php nav eşlemesinde de yok.
- **trade_documents.php** (alış/satış belgeleri) Cari menüsü altında link yok. `v19_not.txt`'den taşındı.
  trade_document_new.php / trade_document_view.php sayfaları çalışıyor ama menüden erişilemiyor.

## Yarım/stub sayfa
- **design.php** ("Grafik Tasarım") — jobs tablosundan `grafik_tasarim` tipini listeliyor ama hiçbir menüde
  veya boot.php nav eşlemesinde referansı yok (assembly.php'nin aksine — o menüde aktif). job_new.php'de bu
  tip seçiliyorsa oluşan işler görünmüyor demektir. Ya menüye eklenmeli ya da job_type kaldırılmalı.
- **mobile/push_enable.php** ("Bildirim Kur & Teşhis") — gerçek bir özellik (push_subscribe.php'ye
  bağlanıp VAPID abonelik akışını çalıştırıyor), diagnostik değil. Ama hiçbir mobil menüden/profil
  sayfasından linklenmiyor — kullanıcı bu sayfaya nasıl ulaşacağını bilemez. `temizle.php`'nin silme
  listesinde yanlışlıkla gerçek tanı dosyalarıyla (kontrol.php vb.) birlikte anılmış; 2026-07-02
  denetiminde içeriği okunup gerçek özellik olduğu anlaşıldı, `temizle.php`'den de çıkarılmalı. Ya
  mobil profil/ayarlar sayfasına link eklenmeli ya da bilinçli olarak "gizli URL" kalacaksa not düşülmeli.

## ÇÖZÜLDÜ (yanlış çıktı) — Web tarafı rol kısıtı
- RAPOR.md'nin (2026-06-28) "web'de rol kısıtı yok" iddiası 2026-07-02 denetiminde YANLIŞ bulundu:
  `boot.php`'de `page_module_map()`, `user_can()`, `require_permission()` ile gerçek modül-bazlı yetki
  sistemi var ve `$__pmap` üzerinden sayfa başına otomatik koruma uygulanıyor. Madde kaldırıldı.

## Modüllerde dropdown'dan inline "Yeni" ekleme (kullanıcı isteği, 2026-07-03 — daha önce de iletilmiş)
- İstek: örn. `sales.php`/`mobile/sales.php`'deki "Cari (Müşteri)" dropdown'unda listede olmayan bir
  cari varsa, sayfadan ayrılmadan orada bir "+ Yeni" seçeneğiyle adını yazıp hemen o alanda
  açabilmeli (diğer alanlar sonra tamamlanabilir/revize edilebilir). Kullanıcı bunun **tüm
  modüllerde** (Cari, Ürün, Personel, vb. geçen her dropdown'da) istendiğini belirtti.
- Kapsam büyük: en az contacts (satış/satın alma/teklif/finans'taki cari seçiciler), stock (ürün
  seçiciler), personnel (görev atama vb.) dropdown'larını kapsıyor — hem web hem mobil.
- Henüz tasarlanmadı/başlanmadı. Önce hangi modül/dropdown'dan başlanacağı ve "hızlı ekle" formunun
  minimum alan seti netleşmeli.

## Diagnostik dosyalar prod sunucuda kalmış olabilir
- `temizle.php` (kökte, admin girişi veya `?key=acans-migrate-2026` ile çalışır) install_*.php,
  kontrol.php, iz.php, bak.php, fix_login.php, ac_extract.php, dev_check.php, ac.php, eski not
  dosyalarını siler ve kendini de siler. NOT (2026-07-02 düzeltmesi): asıl deploy aracı `guncelle.php`
  (Masaüstü'nde, repo dışı — bkz. [[deploy]]) her deploy'da KENDİ yardımcı dosyalarını zaten siliyor;
  `temizle.php` bundan bağımsız, daha eski legacy artıkları (install_*.php vb.) temizlemek için ayrı
  bir araç — deploy'un zorunlu bir adımı değil, gerektiğinde manuel çalıştırılır.

## Muhasebe: KDV dahil/hariç giriş (kullanıcı isteği, 2026-07-03)
- İstek: accounting.php (Muhasebe) gider/gelir kaydı girerken "KDV Dahil" / "KDV Hariç" seçimi
  olmalı. KDV Dahil seçilirse girilen tutar direkt kaydedilir. KDV Hariç seçilirse bir KDV oranı
  alanı (ör. %1/%10/%20) seçtirilip tutar ona göre hesaplanmalı (muhtemelen KDV'siz tutar + KDV
  tutarı ayrı gösterilmeli).
- Henüz tasarlanmadı/başlanmadı. accounting.php + mobile/accounting.php ikisinde de yapılmalı (parite).
- Netleşmesi gereken: KDV dahil toplam mı saklanacak yoksa KDV hariç taban tutar mı? Rapor/özet
  ekranlarında KDV ayrımı gösterilecek mi?
