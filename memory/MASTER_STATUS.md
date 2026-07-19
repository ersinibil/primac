# MASTER_STATUS.md — PRIMAC OTS Tek Gerçek Durum Tablosu

**Oluşturulma:** 2026-07-19. **Yöntem:** gerçek kod (bu oturumda doğrudan okunan dosyalar), `git log`
(linear tek dal, `main`==`origin/main`, HEAD `ca2c3d3`, hiç kayıp/diverge commit YOK — doğrulandı),
`memory/backlog.md`/`memory/features.md`/`KNOWN_BUGS.md` ve kullanıcının bu turda verdiği bilinen
USER PASS listesi karşılaştırıldı. **"Kapandı"/"muhtemelen tamam" yazılmadı** — her madde 5 statüden
biriyle işaretli: **USER PASS** (gerçek kullanıcı/cihaz testinde onaylandı) / **CODE PASS / TEST
PENDING** (kod doğru görünüyor, gerçek test yok) / **FAIL** (bilinen/kanıtlı sorun) / **OPEN**
(planlanmış ama başlanmamış/yarım) / **DEFERRED** (bilinçli ertelendi, PO kararı bekliyor).

**Bu dosya artık TEK SOURCE OF TRUTH'tur.** `memory/backlog.md`, `memory/features.md`,
`KNOWN_BUGS.md`, `ROADMAP.md` **silinmedi**, tarihsel referans olarak duruyor — ama çelişki
durumunda bu dosya esas alınır (bkz. sondaki "Eski Dosyalarla Çelişkiler").

**Not:** Bu ortamdan primac.tr'ye canlı DB/tarayıcı/cihaz erişimi YOKTUR. "CODE PASS / TEST PENDING"
etiketi olan her madde, kod seviyesinde doğru görünüyor demektir — gerçek kullanıcı/cihaz onayı değil.

---

## 1. HOME / DASHBOARD

**Durum: CODE PASS / TEST PENDING**

CODE PASS / TEST PENDING:
- Home v2 (Nabız+Queue+Hızlı İşlemler+Devam Et+Genel Bakış) — commit `c5870c4`, 29/29 DB-free test.
- `dashboard.php`'nin `$__navMode==='legacy'` dalı DEAD KOD (`nav_effective_mode()` her zaman
  `'compact'` döndürüyor) — dokunulmadı, bilinçli.

FAIL:
- FAZ 2C-ii USER TEST'te (2026-07-17) 7 P0 blocker bulunmuştu (Nabız pasif banner aksiyon
  üretmiyor, Home'dan açılan ekranların önemli kısmı o zaman legacy'di, personel/kullanıcı/yetki
  parçalıydı, yetki sistemi personelin görmemesi gereken alanı gösteriyordu, P0-AUTH-01, mobil
  bottom nav dokunma hassasiyeti). Maddelerin çoğu sonradan başka turlarda kapatıldığı iddia edildi
  (bkz. PERSONEL, MOBILE SHELL, SECURITY) ama **Home'un kendisi bu 7 maddeyle yeniden USER TEST
  edilmedi** — orijinal FAZ 2C-ii kapanışı hâlâ resmi olarak açık.
- `dashboard.php`'nin `critical_alerts` bölümü hâlâ yetki kontrolsüz (mobil tarafı 2026-07-15'te
  kapatıldı, web tarafı hâlâ açık) — jobs/stock yetkisi olmayan kullanıcı geciken iş/kritik stok
  detayını görebiliyor.

OPEN:
- PX-001: "Devam Et"/"Sırada" sorguları kayıt SAHİPLİĞİNE değil modül YETKİSİNE göre filtreli
  (bilinçli tasarım, ürün kararı bekliyor).

---

## 2. MOBILE SHELL / NAVIGATION

**Durum: FAIL (yüksek risk — tekrarlayan regresyon deseni)**

⚠️ **Bu modül en az 6 ayrı "kök neden bulundu" turundan geçti** (`74e797a`→`f2eff0d`→`e022f4a`→
`8f52b8d`→`4c9511e`→`deb40fd`, hepsi 2026-07-18/19), her biri kendinden önceki "kesin çözüm"ü
yetersiz bularak üzerine yazdı. Bu döngü tek başına **bu modülün "tamam" sayılamayacağının kanıtı** —
kod bugün `--df-navh` sabit token'ından türeyen sert yükseklik kilidi (height, min-height DEĞİL,
+overflow:hidden+ellipsis, `ds-foundation.css:873`) kullanıyor, cache `v31`'e bumplandı, mantıken
önceki turların hepsinden daha sağlam — **ama aynı iddia her turda yapıldı.**

USER PASS:
- Kullanıcının kendi ifadesiyle "bazı mobil shell/nav testleri" — **hangi ekran/round olduğu
  belirtilmedi**, bu belirsizlikle FAIL'i geçersiz kılacak kapsamda sayılamaz.

CODE PASS / TEST PENDING:
- Composer/bottom-nav çakışması — güncel CSS mimarisi (`deb40fd`) sağlam görünüyor, gerçek
  cihazda henüz doğrulanmadı (bu round için).
- Tema kaydı "Bağlantı hatası" kök nedeni (`8f52b8d`) — mobil-kabuk yönlendirmesinin ajax uçlarını
  yuttuğu bulundu, düzeltildi.
- Geri buton (THY-tarzı) + Ayarlar toplanması (`f2eff0d`).

FAIL:
- Bu oturumun ("PILOT ÖNCESİ SON KAPANIŞ") kendi 3. maddesinde kullanıcı "bazı uzun mobil sayfalarda
  hâlâ nav taşması/scroll sorunu" bildirdi — statik CSS/DOM incelemesi kök neden bulamadı, **canlı
  cihaz reprodüksiyonu olmadan kapanamaz**.

---

## 3. MENU / IA

**Durum: CODE PASS / TEST PENDING**

USER PASS:
- "Menü gruplaması belirli ekranlarda" — kullanıcı onayı var (hangi ekranlar belirtilmedi).

CODE PASS / TEST PENDING:
- 5 kategori (İşler/Ticaret/Üretim & Stok/Finans/Yönetim), `nav_lib.php` veri-güdümlü taksonomi,
  Personel+Kullanıcı+Yetki menü sadeleştirmesi (`2e98f95`).

FAIL:
- Ticaret kategorisi tamamen "create" ağırlıklı (Satış Yap/Satın Alma Yap/Teklif Hazırla) — gerçek
  "Satışlar"/"Satın Almalar" YÖNETİM/liste girişi nav'da YOK (bkz. SATIŞ/SATIN ALMA maddeleri).
  `trade_documents` (Alış/Satış Belgelerini Gör) var ama bu ayrı bir veri modeli (aşağı bakın).

OPEN:
- Cari Tek Merkez + Satış/Satın Alma Operasyon Merkezi eklendiğinde nav'a yeni "yönetim" girişleri
  eklenmesi gerekiyor — henüz yapılmadı.

DEFERRED:
- PARITY-003 (orijinal madde, çözüldü) hariç, `mobileUrl`'siz bazı satırlar (assembly/design/
  work_center/trade_documents/finance_accounts) mobilde `mobileHide` ile gizli — bilinçli.

---

## 4. CARİ

**Durum: FAIL**

USER PASS:
- "Cari context: Tahsilat/Ödeme/Satış/Alış" (cariden başlatılan işlemde bağlam korunuyor,
  commit `dcf1c51`) — kullanıcı onayı var.
- Cari listesine modül-içi arama (isim/yetkili/telefon, commit `c66148c`).

FAIL (bu oturumun kendi tespiti, kullanıcının "kaybetme" listesindeki maddeler):
- **Cari Detay canonical bir operasyon merkezi DEĞİL** — Finans Hareketleri/Belgeler/İş Emirleri/
  CPA/Tahsis ayrı ayrı `<section>` bloklarında, ortak kronolojik bir "Cari Hareketleri" ledger'ı YOK.
- **Tüm hareketler tek timeline'da görünmüyor** — satış/alış (finance_movements), çek/senet, iş
  emri, belge ayrı tablolarda, tarihe göre birleşik sıralama yok.
- **Kaynak işlem drill-down kısmen var, eksik**: Finans Hareketleri bölümü artık
  `finance_movement_actions($r,$pdo)` ile çek/senet kaynağına linkliyor (commit `1dc49c1`, bu kod
  seviyesinde doğrulandı — `contact_view.php:427`) — ama Satış/Alış (belgesiz, quick-sale/purchase)
  satırları için "Kaynak Cari"dan öteye gerçek bir "Satış Detayı"na gidilmiyor (böyle bir ekran yok,
  bkz. SATIŞ/SATIN ALMA).
- **Cari Raporu (`report.php?modul=cari_detay`) hâlâ "Ekstre/PDF" ve genel rapor amacıyla karışık
  kullanılıyor** — Cari Detay'daki "📊 Cari Raporu" ve "Ekstre / PDF" butonları aynı URL'e gidiyor,
  analiz (Rapor) ile operasyon (Detay) rolü net ayrılmamış.
- Bahçera 1.000.000 TL çek senaryosu artık drill-down ile Çek/Senet Detayı'na gidiyor (kod
  seviyesinde, `finance_movement_actions()` çek/senet dalı) — **canlıda hiç doğrulanmadı.**

OPEN:
- "Cari Tek Merkez" (Genel/Hareketler/Satış-Alış/Çek-Senet/İşler/Belgeler/Notlar sekme/section
  yapısı) — kullanıcının 3 ayrı mesajda (CARİ TEK MERKEZ, TODO'ya ekle, MASTER PASS madde 1) istediği
  tam IA restructuring **henüz kod olarak başlamadı** (bu oturumda mimari analiz yapıldı, implementasyon
  "KOD YAZMAYI DURDUR" talimatıyla durduruldu).

---

## 5. SATIŞ

**Durum: FAIL**

CODE PASS / TEST PENDING:
- Çekirdek matematik: satış → STOK-/CARİ BORÇ+/kasa hiç etkilenmiyor (Bekliyor durumu) —
  `stock_create_sale()`, tek ortak fonksiyon, web+mobil ortak (mobil kendi inline bloğunu kullanıyor
  ama satır satır karşılaştırılmış, aynı davranış — bkz. Mobile Regression Sprint notu).
  Düzenle/Sil → `stock_update_sale()`/`stock_reverse_sale()` atomik ters çevirme.
  CPA tüketimi satışla senkron (`cpa_alloc_consume_for_sale()`).

FAIL:
- **Gerçek "Satışlar" liste/yönetim ekranı YOK.** `sales.php` bir "Hızlı Satış" formu + son 10
  kayıt tablosu — filtre (arama/tarih/cari/durum) YOK, sayfalama YOK.
- **Satış Detayı ekranı YOK** (belgesiz/quick satışlar için) — tek aksiyon satır içi Düzenle/Sil,
  "Stok Hareketi Gör"/"Tahsilat Gör"/"Kaynak Cari" gibi bir detay sayfası hiç yok. Sadece
  `document_id` dolu olan (trade_document_new.php'den gelen) satışlar `trade_document_view.php`
  üzerinden tam detaya sahip.
- **"Ödeme durumu" filtresi için veri altyapısı yok** — satış anında durum HER ZAMAN "Bekliyor"
  (satış kendi başına ödeme durumu taşımıyor, ödeme cari bazlı ayrı bir Tahsilat kaydı) — kullanıcının
  istediği "ödeme durumu" filtresi mevcut mimaride per-sale bir veri değil, uydurulamaz.

OPEN:
- Satış Operasyon Merkezi (liste+filtre+detay+aksiyon) — kullanıcının 3 ayrı mesajda istediği,
  mimari analiz bu oturumda yapıldı (finance_movements zaten quick+belge satışları tek noktada
  tutuyor — birleşik liste inşası için ek mimari gerekmiyor), **implementasyon başlamadı.**

---

## 6. SATIN ALMA

**Durum: FAIL**

CODE PASS / TEST PENDING:
- Çekirdek matematik: STOK+/TEDARİKÇİ CARİ BORÇ artışı/ödeme hiç etkilenmiyor — `stock_create_purchase()`,
  web+mobil ortak. Düzenle/Sil → `stock_update_purchase()`/`stock_reverse_purchase()`.
  CPA "Müşteriye Ayır" opsiyonel satır alanı satın alma anında (P0 2026-07-18).

FAIL:
- **Gerçek "Satın Almalar" liste/yönetim ekranı YOK** — SATIŞ ile birebir aynı durum (`purchase.php`
  hızlı form + son 10 kayıt, filtre yok).
- **Satın Alma Detayı ekranı YOK** (belgesiz alışlar için) — aynı SATIŞ maddesindeki eksiklik.

OPEN:
- Satın Alma Operasyon Merkezi — aynı SATIŞ maddesi, implementasyon başlamadı.

---

## 7. TEKLİF

**Durum: CODE PASS / TEST PENDING**

CODE PASS / TEST PENDING:
- Dinamik kalemli form, liste, PDF/WhatsApp/Mail paylaşımı, durum (Taslak/Gönderildi/Kabul/Red),
  web+mobil parite (2026-06-30).

FAIL:
- `teklif.php`/`mobile/teklif.php` hâlâ **MIXED** (Explore agent taraması, 2026-07-19) — `.paper`/
  `#repArea` printable-preview bölümü DS dışı, PDF-koruma gerekçesiyle bu turda dokunulmadı.

DEFERRED:
- Kabul edilen teklif otomatik Satışa/Belgeye dönüşmüyor — kullanıcı elle tekrar giriyor. PO kararı
  bekliyor (otomatik mi, "Satışa Çevir" butonu mu, hiç mi olmasın).

---

## 8. ALIŞ/SATIŞ BELGELERİ

**Durum: CODE PASS / TEST PENDING**

CODE PASS / TEST PENDING:
- `trade_documents.php` — tip filtresi (`?type=purchase/sale`) VAR, gerçek liste+"Aç" (bu, SATIŞ/
  SATIN ALMA modüllerinde eksik olan "liste" desenini zaten kanıtlıyor — aynı desen quick-sale/
  purchase'a da uygulanabilir, yeni mimari gerekmiyor).
- `trade_document_view.php` — gerçek bir DETAY ekranı: cari bakiyesi, belge satırları, CPA "Müşteriye
  Ayrılan", Düzenle (`trade_document_can_edit()` kapılı), İptal Et (`trade_document_cancel()` —
  stok/cari/CPA etkisini tam tersleyen atomik fonksiyon, `trade_core.php`).

FAIL: yok (bu modül nispeten olgun, SATIŞ/SATIN ALMA'nın ulaşması gereken referans desen bu).

---

## 9. STOK / ÜRÜN

**Durum: CODE PASS / TEST PENDING**

CODE PASS / TEST PENDING:
- `stock_items.quantity` stored counter, her hareket `stock_movements`'a yazıyor.
- **Manuel/orphan stok hareketi geri alma** (bu turdan önceki oturum) — migration 049
  (`reversed_movement_id`), `stock_reverse_manual_movement()`, `product_view.php`/
  `mobile/product_view.php`'de "Hareketi Geri Al" aksiyonu. Bu oturumda kod okunarak DOĞRULANDI
  (fonksiyon var, çağrı zinciri tutarlı) — **primac.tr'de migrate.php çalışmadıysa (bkz. DATA
  INTEGRITY) bu özellik SQL hatası verir.**
- Kaynak drill-down: `product_view.php` Stok Hareketleri artık `finance_movement_id` üzerinden
  `trade_document_view.php`/`sales.php?edit_id`/`purchase.php?edit_id`'ye linkliyor.

FAIL:
- Kullanıcının orijinal "HATALI STOK HAREKETİ" senaryosu (100 adet Çıkış, cari yok, kaynak belirsiz)
  — kod düzeltmesi yapıldı ama **canlı DB'de o spesifik satırın gerçekten düzeltilebildiği hiç
  doğrulanmadı** (migration 049 primac.tr'de çalışmadan test edilemez).

OPEN:
- Kaynak sınıflandırması (Satış/Satın Alma/Manuel/Üretim/İade/Tahsis) — şu an sadece
  `finance_movement_id` dolu/boş ayrımı var, "Üretim"/"İade"/"Tahsis" gibi ayrı source_type kategorik
  olarak izlenmiyor (serbest metin `reason` alanına dayanıyor).

---

## 10. CPA (Customer Procurement Allocation)

**Durum: CODE PASS / TEST PENDING**

CODE PASS / TEST PENDING:
- `cpa_preferences` (migration 045) + `cpa_allocations`/`cpa_allocation_consumptions` (046+047) —
  satış düzenleme/silme artık tek ortak noktadan (`stock_update_sale()`/`stock_reverse_sale()`)
  tüketimi geri alıp yeniden uyguluyor (ledger-sil bazlı idempotent). Senaryo kod izi ile doğrulandı.

FAIL:
- **`primac.tr'de migrate.php çalıştırılmadan bu özellik tamamen kullanılamaz`** — yazma
  fonksiyonları runtime CREATE TABLE yapmıyor, açık hata verir (bkz. DATA INTEGRITY).

DEFERRED:
- Alış silme/düzenleme ile CPA tahsis referansı ilişkisi (sadece SATIŞ tarafı ele alındı,
  tüketilmemiş tahsis varken alış silinmesi durumu kapsanmadı).

---

## 11. FİNANS

**Durum: CODE PASS / TEST PENDING**

USER PASS:
- "Finans Ödeme Yap: Cari/Tedarikçi + Genel Gider ayrımı" (commit `4d9fbb6`).
- "Finans cari autocomplete + Müşteri/Tedarikçi filtreleri" (commit `4a5ab5f`).

CODE PASS / TEST PENDING:
- Çekirdek matematik: satış/alış ödeme yapmaz, Tahsilat/Ödeme ters işaretle borcu kapatır
  (`contact_balance_case_sql()`), double-counting yok (2026-07-11 Finance Core Stabilization).
- Finans hesabı silme guard'ı genişletildi (`finance_account_has_movements()` artık
  `trade_documents.account_id`+`checks_notes.settle_account_id`'i de kontrol ediyor, commit
  `f54c33e`) — kod seviyesinde doğrulandı (`finance_lib.php:112`).
- Çek/senet kaynaklı finans hareketleri artık gerçek Çek/Senet Detayı'na drill-down yapıyor
  (`finance_movement_actions($r,$pdo)`, commit `1dc49c1`) — `contact_view.php`, `finance.php`,
  `finance_account_view.php`, `mobile/account_view.php`, `mobile/contact_view.php` hepsi bu
  turda `$pdo` parametresiyle güncellendi (kod seviyesinde doğrulandı).

FAIL:
- **Bahçera 1.000.000 TL senaryosunun kökeni (Nakit Kasa'nın hareketliyken silinmesi) canlı DB'de
  hiç doğrulanamadı** — bu ortamdan DB erişimi yok. Mevcut guard'ın (`finance_account_delete()`)
  o olayı engelleyip engellemeyeceği kod okumasıyla "engellerdi" sonucuna varıldı, ama olayın
  GERÇEKTE nasıl olduğu (guard bypass mı, guard eklenmeden ÖNCEki bir işlem mi) doğrulanamadı.
- `finance_account_orphan_report()` sadece RAPORLUYOR, otomatik onarım YOK (bilinçli, kanıtlanamayan
  eşleşme uydurulmayacak) — canlıda kaç orphan olduğu **bilinmiyor**, primac.tr'de admin panelinden
  (`finance_accounts.php`) çalıştırılıp bakılmalı.

OPEN:
- Genel "master-data silme guard" deseni finans hesapları dışındaki tablolara (contacts, products,
  personnel) aynı titizlikte henüz genişletilmedi — sadece finans hesapları P0 olarak ele alındı.

---

## 12. KASA/BANKA/KART/POS

**Durum: CODE PASS / TEST PENDING**

CODE PASS / TEST PENDING:
- "Kullanılmış hesap" ile "kullanılmamış hesap" silme terminolojisi ayrıştırıldı — kullanılmış
  hesaplar "⏸ Pasife Al" (`df-btn--secondary`), gerçekten boş hesaplar "🗑 Sil" (`df-btn--danger`)
  gösteriyor (`finance_accounts.php`, `mobile/account_view.php`, `mobile/kasa.php`).
- Admin-only orphan-integrity uyarı paneli eklendi (`finance_accounts.php`, `mobile/kasa.php`).

FAIL: yok (bu dar kapsamdaki P0 kod seviyesinde tamamlandı) — ama bkz. FİNANS modülündeki "canlı
doğrulama yapılamadı" notu, aynı sınırlama burada da geçerli.

---

## 13. ÇEK/SENET

**Durum: FAIL (fonksiyonel olarak muhtemelen ÇALIŞMIYOR — migration 048 blocker)**

⚠️ **En kritik bulgu bu turda:** `checks_notes.php:79`'da kodun KENDİSİ şu uyarıyı basıyor:
*"Çek/Senet yaşam döngüsü (Tahsil Et / Öde / Ciro Et / İşlemi Geri Al) bu sunucuda henüz AKTİF
DEĞİL — migration 048 çalıştırılmamış."* Yani **bu modülün tüm CODE PASS iddiaları, migration 048
primac.tr'de çalıştırılmadıysa, canlıda FAIL'e dönüşür.** `memory/backlog.md`'nin 2026-07-18 notu
"primac.tr'de migrate.php'nin GERÇEKTEN çalıştırılıp çalıştırılmadığı HÂLÂ TEYİT EDİLEMEDİ" diyor —
bu tur da bunu değiştirmedi (DB erişimi yok).

CODE PASS / TEST PENDING (migration 048 koşuluyla):
- Durum makinesi: portfoyde→(tahsil_edildi/ciro_edildi/karsiliksiz/iptal), sadece 'portfoyde'den
  aksiyon alınabilir, final durum sabit.
- `checks_notes_collect()`/`pay()` — kasa/banka hareketi, `contact_id=NULL`, cari İKİNCİ KEZ
  etkilenmiyor. `checks_notes_endorse()` — kasa hareketi YOK, sadece ciro edilenin borcu kapanıyor.
- `checks_notes_reopen()` — sadece kendi settle/ciro hareketini geri alıyor, orijinal kabul
  hareketine VE `finance_accounts` master kaydına DOKUNMUYOR (bu turda kod okunarak doğrulandı —
  hem Detay hem liste satırından tek tıkla, aynı fonksiyon, `checks_notes.php:53,191`).
- Çek/senet cari bakiye işareti — `contact_balance_case_sql()` üzerinden DOĞRU (stale yorum
  temizlendi, commit `3f985aa`, bu daha önce yanlışlıkla "hâlâ açık" raporlanmıştı — YANLIŞ ALARM).

FAIL:
- Migration 048 durumu primac.tr'de bilinmiyor — bu tek başına modülü fonksiyonel olarak
  kullanılamaz kılabilir.

---

## 14. İŞLER / İŞ EMİRLERİ / GÖREVLER

**Durum: CODE PASS / TEST PENDING**

CODE PASS / TEST PENDING:
- İş emri CRUD, durum güncelleme, sorumlu atama, "İşlerim" Düzenle/Detay/Sil (soft-delete
  `deleted_at`), görev IDOR düzeltmesi (`task_can_edit()`).

FAIL:
- `tasks.deleted_at` filtresi bazı sayaç/rapor sorgularına hâlâ eklenmedi (`jobs.php`, `kpi.php`,
  `dashboard.php`, `report_lib.php`, `gunluk_rapor.php`, `takvim.php` vb. — 2026-07-04'ten beri açık,
  hiç kapatılmadı) — soft-silinen görev bazı sayaçlara hâlâ dahil olabilir.
- `job_view.php` (web) ve `mobile/job_view.php` iki ayrı zaman çizelgesi kaynağı kullanıyor
  (`job_logs` vs `activity_logs`) — birbirlerinin olaylarını görmüyorlar (2026-07-16'dan beri açık,
  legacy dal için hiç kapatılmadı).

---

## 15. PERSONEL / OTS HESABI / YETKİ

**Durum: CODE PASS / TEST PENDING**

USER PASS:
- "Personel + Kullanıcı + Yetki tekleştirme ana UX" — kullanıcı onayı var (commit `2e98f95` +
  önceki oturumun sticky kimlik başlığı/sekme birleştirmesi).

CODE PASS / TEST PENDING:
- Orphan OTS hesabı tespiti+eşleştirme (`personnel_find_orphan_matches()`/`personnel_link_account()`,
  web+mobil), kör cascade DELETE'in KALDIRILMASI (2 yerde: `sil.php`, `mobile/personnel_view.php` —
  artık sadece pasife alma), `users.php`'nin tek-yönlü senkron hatası düzeltildi (commit `9798fe3`,
  bu turda `contact_view.php`/`personnel.php` gibi dosyalar okunarak dolaylı doğrulandı).
- P0-AUTH-01 (mükerrer hesap/stale user_id şifre sıfırlama hedefi) — kod düzeltildi (`91d0567`),
  **gerçek cihaz testi hiç yapılmadı.**
- P0-AUTH-02 (Şifre Sıfırla WA gönderimi başarısızsa hesabı kilitlemesin) — kod düzeltildi
  (`b0f8ec6`), **gerçek cihaz testi hiç yapılmadı.**

FAIL:
- Muhammet orphan personel/user senaryosu (kullanıcının "kaybetme" listesinde) — genel orphan-eşleme
  mekanizması kod olarak var, ama **bu spesifik canlı kaydın düzeldiği doğrulanmadı** (DB erişimi yok).

DEFERRED:
- `app_users.personnel_id` üzerinde DB-seviyeli UNIQUE kısıt yok (teorik TOCTOU, düşük risk,
  migration gerektirir, PO kararı bekliyor).

---

## 16. İLETİŞİM MERKEZİ

**Durum: CODE PASS / TEST PENDING**

CODE PASS / TEST PENDING:
- Sohbetler/WhatsApp/Bildirimler/Taleplerim/Duyurular 5 sekmeli yapı (`ic_tabs()`), P0 mobil yatay
  taşma düzeltmesi (`db4ad66`), sistem içi sohbette "Temizle" web+mobil parite.
- **Taleplerim kendi açık talebini iptal etme** (bu turdan önceki oturum, commit `4f38524`) — bu
  oturumda `taleplerim.php`/`mobile/taleplerim.php` TAM okunarak doğrulandı: `cancel_request` POST
  handler'ı `WHERE id=? AND created_by=?` ile IDOR'a kapalı, sadece Yeni/İnceleniyor durumunda
  aktif, fiziksel DELETE yok (durum→'İptal Edildi'). **Kod kesin doğru, gerçek kullanıcı testi yok.**

FAIL: bu turda yeni bulunan yok.

---

## 17. WHATSAPP

**Durum: FAIL (kısmi)**

CODE PASS / TEST PENDING:
- Giden mesaj gönderimi (UltraMsg entegrasyonu), konuşma geçmişi (`wa_conversations`), toplu
  gönderim, DS dönüşümü.

FAIL:
- **Gelen (inbound) mesajlar sistemde görünmüyor** — webhook alıcı endpoint'i yok, API kısıtı değil,
  mimari eksiklik (2026-07-05'ten beri açık, hiç ele alınmadı, yeni özellik kapsamında).
- iOS Safari arka plan push teslimatı hiç test edilemedi (bu ortamda gerçek iOS aboneliği yok).

---

## 18. TALEPLER / DUYURULAR / BİLDİRİMLER

**Durum: CODE PASS / TEST PENDING**

CODE PASS / TEST PENDING:
- Taleplerim iptal (bkz. İLETİŞİM MERKEZİ — aynı madde, tekrar sayılmadı).
- Duyurularda "Herkes İçin Kalıcı Sil" (bu turdan önceki oturumda bağlandığı iddia edilen 3 CPA-tipi
  bulgunun biri, commit `3897394`) — bu turda YENİDEN doğrulanmadı, sadece commit mesajı üzerinden
  referanslandı.
- Bildirim genel/kişisel ayrımı (`user_notification_status`, migration 039) — sahiplik kontrolü var.

FAIL: bu turda yeni bulunan yok, ama üstteki maddelerin hiçbiri bu oturumda yeniden kod okunarak
doğrulanmadı (sadece commit geçmişine güvenildi) — **CODE VERIFIED değil, CODE REFERENCED.**

---

## 19. RAPORLAR

**Durum: FAIL**

CODE PASS / TEST PENDING:
- Rapor motoru (`report_render()`) DS token'larına taşındı (commit `3ee0926`, `7950c86`).
- Cari/Satış/Tahsilat/Muhasebe/Teklif modülleri, CSV export, detay linkleri (satış→cari,
  iş takip→job_view vb.).

FAIL (kullanıcının MASTER PASS mesajındaki madde 7 birebir):
- Rapor ekranı hâlâ ayrı/eski bir uygulama gibi görünüyor — "dev gradient KPI kutuları + dashboard
  snapshot" yaklaşımı **bu turda kaldırılmadı** (implementasyon "KOD YAZMA" talimatıyla durduruldu).
- KPI drill-down (bir rakama tıklayınca hangi tarih/cari/hesap/kaynak işlem olduğunu görme) YOK.
- `gunluk_rapor.php`/`mobile/gunluk_rapor.php`, `teklif.php`/`mobile/teklif.php` hâlâ **MIXED**
  (Explore agent taraması, 2026-07-19) — PDF-önizleme bloğu DS dışı.

---

## 20. PDF / EXPORT

**Durum: FAIL**

CODE PASS / TEST PENDING:
- KPI kutuları için CSS Grid→Flexbox (html2canvas uyumluluğu, commit `2d330e4`) — **hiç canlı PDF
  indirilerek doğrulanmadı**, sadece en güçlü kanıta dayalı aday düzeltme.

FAIL (kullanıcının MASTER PASS madde 8 + gerçek USER TEST FAIL raporu):
- Mevcut mimari hâlâ html2canvas DOM-screenshot tabanlı — "PDF = ekran görüntüsü değildir" prensibi
  karşılanmıyor. Ayrı bir print/PDF template **henüz yok**.
- Kullanıcının gerçek cihazda bildirdiği "KPI kutuları boş/görsel bozuk/eski-karışık görünüm" sorunu
  kök nedeni (CSS Grid) düzeltildi ama **doğrulanmadı** — hâlâ FAIL olarak sayılmalı, USER TEST
  gelene kadar.

---

## 21. TAKVİM

**Durum: CODE PASS / TEST PENDING**

CODE PASS / TEST PENDING:
- Mobil takvim kök neden (renk kontrastı) düzeltmesi rapor ailesiyle birlikte taşındı (`7950c86`).
- Gün tıklama, silinmiş görev filtresi, görev/not linkleri (2026-07-04'ten beri stabil).
- "Tüm günler görünür" düzeltmesi (kullanıcının PILOT ÖNCESİ mesajında "korunmalı" dediği).

FAIL: bu turda yeni bulunan yok.

DEFERRED:
- "Yaklaşan İşler/Vadeler" widget'ı (2026-07-13'ten beri backlog, mimariye açık ama eklenmedi).

---

## 22. LEGACY UI / DESIGN SYSTEM

**Durum: FAIL**

⚠️ Kullanıcının kesin kararı ("LEGACY / ESKİ GÖRÜNÜM İÇİN KESİN PRODUCT OWNER KARARI"): sadece
MODERN kabul edilir, MIXED=FAIL, LEGACY=FAIL. Buna göre:

CODE PASS / TEST PENDING (MODERN sayılanlar):
- Explore agent taraması (2026-07-19): ~135 aktif route, çoğunluğu MODERN. `activity.php`+
  `mobile/activity.php` bu turda MIXED'den MODERN'e taşındı (`ds_list_item()`/`df-list`,
  commit `ca2c3d3`) — bu oturumda `activity_lib.php` dosya boyutu nedeniyle TAM yeniden okunamadı,
  önceki tur özetine güveniliyor (CODE REFERENCED, yeniden doğrulanmadı).

FAIL:
- **`gunluk_rapor.php`, `mobile/gunluk_rapor.php`, `teklif.php`, `mobile/teklif.php` — 4 dosya hâlâ
  MIXED.** Kullanıcının "MIXED=FAIL, istisna yok" kararına göre bunlar teknik olarak FAIL sayılmalı.
  Önceki tur bunu "PDF-belge karakteri koruma" gerekçesiyle PO kararına bırakmıştı — **ama kullanıcının
  net talimatı istisna tanımıyor.** Bu çelişki çözülmedi, aşağıdaki "Eski Dosyalarla Çelişkiler"
  bölümünde ayrıca işaretlendi.
- SATIŞ/SATIN ALMA'da eksik olan Liste/Detay ekranları inşa edildiğinde onlar da bu standarda
  (MODERN, DS bileşenleri) tabi olacak — henüz yok oldukları için değerlendirme dışı.

OPEN:
- Cari Tek Merkez, Satış/Satın Alma Operasyon Merkezi implementasyonu tamamlanmadan bu modülün
  "0 MIXED/0 LEGACY" hedefine ulaşması mümkün değil (yeni ekranlar bu standartta doğacak, ama henüz
  yoklar).

---

## 23. SECURITY / AUTH

**Durum: CODE PASS / TEST PENDING**

CODE PASS / TEST PENDING:
- CSRF (SECURITY SPRINT-004, 57 sayfa enforced), session fixation koruması (`session_regenerate_id`),
  `accounting.php` XSS (commit `14f1485`), `users.php` rol yükseltme (commit `b198be8`),
  P0-AUTH-01/02 (bkz. PERSONEL — kod düzeltildi, cihaz testi yok).

FAIL:
- `is_admin()` session'da bayatlıyor (DB'den taze okumuyor) — rolü alınan admin oturum boyunca
  yetkili kalabilir. Bilinçli açık, Release 1.0'a planlı.
- `users.php` `permissions[]` alanında whitelist yok — `users` yetkili admin-olmayan biri kendi/
  başkasının hassas yetkilerini işaretleyebiliyor.
- `cron.php`'de sabit anahtar (`acans-cron-2026`), `migrate.php`/`temizle.php` paylaşılan sabit
  anahtar (`acans-migrate-2026`) — düşük risk, admin oturumu zaten ek katman.

---

## 24. DATA INTEGRITY / ORPHAN RECORDS

**Durum: OPEN**

CODE PASS / TEST PENDING:
- `finance_account_orphan_report()`/`finance_account_orphan_count()` — finance_movements/
  trade_documents/checks_notes için orphan account_id taraması, salt-okunur, admin-only panel.

FAIL:
- **Kapsam dar** — kullanıcının MASTER PASS madde 11'de istediği tam tarama (stock_movements→missing
  source, sales/purchases→missing contact, checks_notes→broken finance movement, personnel↔users
  orphan tam iki yönlü, trade documents→broken source links) **henüz tek bir merkezi araçta değil.**

OPEN:
- Tek merkezi "Veri Bütünlüğü" denetim ekranı (tüm orphan tiplerini tek yerde gösteren) —
  implementasyon "KOD YAZMA" talimatıyla durduruldu, mimari analiz yapıldı, kod yazılmadı.

### P0.1 — Migration 045-049 doğrulaması (2026-07-19, ayrı tur)

**DB/SCHEMA RESULT: UNKNOWN — primac.tr'ye bu ortamdan hiçbir DB/SSH/cPanel erişimi yok.** Lokal
`config.php` var ama `db_host=localhost` (primac.tr DEĞİL) ve önceki bir oturumda `db_name`'in
PROD (`u7883898_primacos`) ile aynı göründüğü tespit edilmişti — bu belirsizlik nedeniyle lokal DB'ye
DE bağlanılmadı (bağlanılsa bile primac.tr'nin GERÇEK/GÜNCEL durumunu kanıtlamaz). **Varsayım
yapılmadı, "uygulandı" denmedi.**

**Migration mekanizması (`migrate.php`) kod okunarak DOĞRULANDI — CODE VERIFIED, güvenli/idempotent:**
- `schema_migrations` takip tablosu (dosya adı bazlı) + SQL seviyesinde MySQL hata kodu toleransı
  (1050 tablo var/1060 kolon var/1061 index var/1091 yok → hepsi "zaten uygulanmış" sayılıp atlanır).
- Sıralı çalışır, gerçek bir hatada DURUR (sıradaki migration'lara geçmez).
- **Başarılı ve hatasız biterse KENDİNİ SİLER** (`@unlink(__FILE__)`) — yani `migrate.php`'nin
  sunucuda hâlâ var olması TEK BAŞINA "hiç çalışmadı" anlamına gelmez (her deploy zip'i dosyayı
  yeniden yükler); ama içinde `❌ hata` olmadan koştuğunu gösteren bir ekran çıktısı YOKSA kanıt yok.
- 045/046/047 (`CREATE TABLE IF NOT EXISTS`) doğal olarak idempotent. 048/049 (`ALTER TABLE ADD
  COLUMN`/`CREATE INDEX`) MySQL 5.7'de native `IF NOT EXISTS` desteklemiyor — idempotentliği
  SADECE migrate.php'nin 1060/1061 hata-yutma mekanizması sağlıyor (ham SQL ile elle tekrar
  çalıştırılırsa hataya düşer, bu yüzden "migrate.php DIŞINDA manuel SQL çalıştırma" kuralı kritik).

**045: UNKNOWN — 046: UNKNOWN — 047: UNKNOWN — 048: UNKNOWN — 049: UNKNOWN** (primac.tr'de
doğrulanmadı). Beklenen şema imzaları (gelecekte tek satır sorguyla kontrol için):
`SHOW TABLES LIKE 'cpa_preferences'` (045), `SHOW TABLES LIKE 'cpa_allocations'` (046),
`SHOW TABLES LIKE 'cpa_allocation_consumptions'` (047), `SHOW COLUMNS FROM checks_notes LIKE
'settle_account_id'` (048), `SHOW COLUMNS FROM stock_movements LIKE 'reversed_movement_id'` (049).

**TEK GÜVENLİ ADIM:** primac.tr'de `https://primac.tr/migrate.php` çalıştırılsın (admin girişiyle,
ya da `?key=acans-migrate-2026`). Ekranda her migration için ✅ uygulandı / ⏭️ zaten uygulanmış /
❌ hata satırı görülecek — bu, mekanizmanın kendi tasarımı gereği kesin ve güvenli kanıttır. Manuel
SQL çalıştırılmamalı (idempotentlik garantisi migrate.php'nin hata-yutma mantığına bağlı).

---

## 25. WEB / MOBILE PARITY

**Durum: CODE PASS / TEST PENDING**

CODE PASS / TEST PENDING:
- Bu oturumda P0 olarak ele alınan her madde (personel orphan, taleplerim iptal, çek/senet
  drill-down, finans hesap güvenliği) web+mobil simetrik uygulandı (iki dosya da bu oturumda
  okunarak/onaylanarak doğrulandı: `taleplerim.php`+`mobile/taleplerim.php` TAM okundu ve birebir
  aynı mantık doğrulandı).

FAIL:
- `work_center.php`, `trade_documents.php`, `design.php`, `finance_accounts.php` mobilde hiç yok
  (bilinçli `mobileHide`, ama parite kuralı — CLAUDE.md madde 7 — açısından teknik olarak eksik).
- `mobile/sales.php` hâlâ `stock_create_sale()` ortak fonksiyonuna bağlı değil (kendi inline bloğu,
  davranış aynı ama kod paylaşımı yok — 2026-07-11'den beri açık, düşük risk).
- SATIŞ/SATIN ALMA operasyon merkezi eksikliği hem web hem mobilde aynı derecede (bu açıdan en
  azından "eşit derecede eksik" — parite kırılması yok, ikisi de aynı oranda geride).

---

# ESKİ MEMORY DOSYALARIYLA ÇELİŞKİLER

1. **"Legacy UI CLOSED" (çeşitli eski turlar) vs bu dosya** — `memory/backlog.md`'de FAZ 2C/DS
   Migration turları defalarca "tamam" dedi, ama Explore agent taraması (2026-07-19) 5 MIXED bulmuştu,
   bu turda 4'e indi. **MASTER_STATUS = FAIL** (kullanıcının "MIXED=FAIL, istisna yok" kararı
   nedeniyle), eski "CLOSED" ifadeleri bağlayıcı değil.
2. **"Çek/senet cari bakiye işareti tutarsızlığı" (2026-07-19 sabahki not) vs bu dosya** — kodda
   zaten düzeltilmiş bulundu (yorum stale'di), **MASTER_STATUS = CODE VERIFIED**, gerçek bug değildi.
3. **"Mobil shell 3 kez CLOSED denip 3 kez FAIL aldı" — bu dosya bunu 4./5./6. tur olarak sayıyor**,
   `backlog.md`'nin "Round 3 (`f2eff0d`) şu an en son durum" ifadesi ARTIK GÜNCEL DEĞİL — sonrasında
   en az 3 tur daha oldu (`e022f4a`, `4c9511e`, `deb40fd`). **MASTER_STATUS = FAIL (yüksek risk)**,
   `backlog.md`'nin bu bölümü okunurken tarih sırası dikkatle takip edilmeli.
4. **"guncelleme.zip primac.tr'ye yüklendi mi / migrate.php çalıştı mı" hiçbir eski dosyada KESİN
   YANITLANMAMIŞ** — bu belirsizlik `backlog.md`'de 2026-07-18'den beri var, bu dosya da çözemedi
   (DB/sunucu erişimi yok). **MASTER_STATUS = OPEN, projenin en kritik bilinmeyeni.**
5. **"P0 finans/personel/çek/senet kapandı" (2026-07-19 pilot öncesi backlog notu) vs bu dosya** —
   kod seviyesinde gerçek (bu turda tekrar okunarak doğrulandı), ama migration 048 koşulu nedeniyle
   ÇEK/SENET modülü için CLOSED denemez. **MASTER_STATUS = kısmi FAIL, koşullu CODE PASS.**

---

# MASTER TODO

## P0 — Veri bütünlüğü / yanlış matematik / orphan / işlem zinciri
1. **primac.tr'de hangi migration'ların gerçekten çalıştığını doğrula** (özellikle 046/047/048/049)
   — bu, ÇEK/SENET, CPA, STOK-geri-alma modüllerinin hepsinin önkoşulu.
2. Finans hesabı orphan durumunun canlı DB'de gerçek sayısını `finance_accounts.php` admin panelinden
   kontrol et (Bahçera senaryosunun kökeni dahil).
3. Data Integrity merkezi denetim ekranını tamamla (stock_movements/sales/purchases/checks_notes/
   personnel↔users/trade_documents — tam kapsam, MASTER PASS madde 11).
4. `tasks.deleted_at` filtresi eksik kalan sayaç/rapor sorgularına ekle (soft-silinen görev hâlâ
   bazı sayaçlara dahil).

## P1 — Kullanıcının temel operasyonu yapmasını engelleyen UX/IA
5. Satış Operasyon Merkezi (liste+filtre+detay+aksiyon, web+mobil).
6. Satın Alma Operasyon Merkezi (aynı desen).
7. Cari Tek Merkez (kronolojik birleşik ledger + tam drill-down + section/tab IA).
8. Mobil bottom nav/composer çakışmasını GERÇEK cihazda doğrula (6. tur — bu sefer kapanmalı).
9. PDF motorunu html2canvas screenshot'tan ayrı, gerçek print/A4 template'e taşı.
10. Rapor ekranını DS'e tam taşı (gradient KPI kaldır) + KPI drill-down ekle.
11. `gunluk_rapor.php`/`teklif.php` (4 dosya) MIXED durumunu çöz (PDF-koruma vs zero-legacy çelişkisini
    PO kararıyla kapat).

## P2 — Görsel/parity/iyileştirme
12. WhatsApp inbound webhook (gelen mesajlar sistemde görünmüyor).
13. `dashboard.php` critical_alerts web tarafına yetki filtresi ekle (mobil zaten kapandı).
14. `is_admin()` session bayatlaması, `users.php` permissions[] whitelist.
15. `job_view.php`/`mobile/job_view.php` iki ayrı zaman çizelgesi kaynağını birleştir.
16. `mobile/sales.php`'yi `stock_create_sale()` ortak fonksiyonuna bağla (kod paylaşımı, davranış
    zaten aynı).

---

# PILOT BLOCKERS

Gerçekten pilot kullanıcı yayınını engelleyen, doğrulanmış maddeler (max 10):

1. **Migration durumu bilinmiyor** (primac.tr'de 046-049 çalıştı mı?) — çek/senet, CPA, stok geri
   alma bu olmadan canlıda çalışmaz.
2. **Mobil bottom nav/composer** — 6 turdur kapanmadı, gerçek cihaz retest şart.
3. **Cari Tek Merkez yok** — kullanıcı temel "cariyi bul, geçmişini gör, düzelt" akışını tek ekranda
   yapamıyor.
4. **Satış Operasyon Merkezi yok** — geçmiş satışları arayıp yönetemiyor.
5. **Satın Alma Operasyon Merkezi yok** — aynı, alış tarafı.
6. **PDF güvenilirliği doğrulanmadı** — kullanıcı gerçek cihazda bozuk PDF bildirdi, düzeltme
   canlıda test edilmedi.
7. **Finans hesabı orphan riski canlı DB'de doğrulanmadı** — Bahçera senaryosunun gerçek kapsamı
   bilinmiyor.
8. **4 ekran hâlâ MIXED** (gunluk_rapor×2, teklif×2) — kullanıcının "0 legacy" kesin kararına aykırı.
9. **Rapor sistemi hâlâ "ayrı eski uygulama" hissi veriyor** — kullanıcının doğrudan şikayeti.
10. **Hiçbir P0/P1 maddesi gerçek USER TEST almadı** (bu turda) — hepsi CODE PASS / TEST PENDING.

---

# Değerlendirme Notu

Bu doküman **kod yazmadan** üretildi (kullanıcı talimatı, "KOD YAZMAYI ŞİMDİLİK DURDUR"). Buradaki
FAIL/OPEN maddelerinin çoğu için kod seviyesinde makul çözüm yolları bu oturumda analiz edildi
(Satış/Satın Alma listesi için `trade_documents.php`'nin zaten kanıtlanmış deseni, Cari ledger için
`finance_movement_actions()`'ın zaten çalışan drill-down mekanizması, PDF için ayrı print-template
yaklaşımı) — ama **hiçbiri implemente edilmedi.**
