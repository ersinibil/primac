# MASTER_TEST_PLAN.md — PRIMAC OTS Doğrulama Planı

`memory/MASTER_STATUS.md`'deki "CODE PASS / TEST PENDING" ve "FAIL" maddelerini gerçek kullanıcı/
cihaz/DB ile kapatmak için sıralı test planı. Her madde tek başına çalıştırılabilir, birbirine
bağımlı değil — istediğiniz sırayla ilerleyebilirsiniz. Sonucu `MASTER_STATUS.md`'ye geri işlemek
için ilgili maddenin statüsünü güncelleyin (USER PASS / FAIL).

---

## 0. ÖN KOŞUL — Migration Durumu (her şeyden önce, PILOT BLOCKER #1)

1. primac.tr'de `https://primac.tr/migrate.php` çalıştırılsın (admin oturumuyla).
2. Migration listesinde 045-049'un hepsi "OK"/"already applied" görünmeli.
3. Sonuç: 5 dakikada çözülür, ama ÇEK/SENET + CPA + STOK-geri-alma modüllerinin hepsini etkiler —
   önce bu yapılmadan aşağıdaki 2/6/9 no'lu testler anlamsız çıkabilir.

---

## 1. FİNANS — Bahçera Çek Senaryosu (P0)

1. Yeni bir "Nakit Kasa" hesabı aç (`finance_accounts.php`).
2. Herhangi bir cariden 1.000.000 TL'lik çek al (Portföyde).
3. Çeki "Tahsil Et" → hesap olarak yeni Nakit Kasa'yı seç.
4. **A) Nakit Kasa'yı silmeyi dene** → beklenen: BLOKE, "Pasife Al" önerisi gösterilmeli.
5. **B) Çek tahsilatını "İşlemi Geri Al"** → beklenen: Nakit Kasa hesabı SİLİNMEDİ, sadece -1.000.000
   hareketi tersleniyor, çek "Portföyde"ye dönüyor, cari borcu tekrar açılıyor, ikinci bir orphan
   kayıt OLUŞMUYOR.
6. **C) Nakit Kasa'yı Pasife Al** → geçmişte adı hâlâ görünmeli, yeni işlem seçicisinde ARTIK
   görünmemeli.

## 2. ÇEK/SENET — Tam Yaşam Döngüsü

1. `checks_notes.php` açıldığında sarı "migration 048 aktif değil" uyarısı GÖRÜNMÜYORSA migration
   koşulu tamam demektir — devam edin. Görünüyorsa 0. maddeye dönün.
2. Alınan çek: Portföyde → Tahsil Et → Ciro Et → Karşılıksız → İptal senaryolarının HER BİRİNİ ayrı
   ayrı bir test çekiyle deneyin, her adımda cari bakiyesinin (Cari Detay) beklenen yönde değiştiğini
   doğrulayın.
3. Liste satırından TEK TIKLA "↩️ Geri Al" ile Detay sayfasındaki "İşlemi Geri Al"ın AYNI sonucu
   verdiğini doğrulayın.

## 3. STOK — Manuel Hareket Geri Alma

1. Bir üründe manuel bir "Çıkış" stok hareketi oluşturun (cari/satış bağlantısı olmadan).
2. Ürün Detayı → Stok Hareketleri'nde bu satırda "Hareketi Geri Al" görünmeli.
3. Geri alındıktan sonra: stok miktarı düzeldi mi, "Geri alındı" rozeti göründü mü, ikinci kez
   "Hareketi Geri Al" denendiğinde reddediliyor mu?
4. Satıştan/alıştan gelen bir stok hareketinde bu buton GÖRÜNMEMELİ — bunun yerine "Kaynak Satışı
   Gör"/"Kaynak Alışı Gör" linki olmalı, tıklanınca doğru ekrana gitmeli.

## 4. PERSONEL — Orphan Hesap Eşleştirme

1. `app_users` tablosunda `personnel_id IS NULL` olan (personelsiz) bir test hesabı olduğundan emin
   olun (ya da böyle bir kayıt varsa onu kullanın — Muhammet senaryosu).
2. Yeni/mevcut bir personelin OTS Hesabı bölümünde bu olası eşleşme uyarı olarak görünmeli.
3. "Bu Hesaba Bağla" ile eşleştirin, ardından bu personeli normal şekilde giriş yaptırıp yetkilerinin
   doğru geldiğini doğrulayın.
4. Bir personeli "Pasife Al" ile pasifleştirin — geçmiş iş/görev/finans kayıtları KAYBOLMAMALI,
   personel fiziksel olarak silinmemiş olmalı (DB'de hâlâ görünür, sadece `active=0`).

## 5. TALEPLERİM — Kendi Talebini İptal

1. Bir talep oluşturun (İletişim Merkezi → Yeni Talep).
2. Taleplerim'de "İptal Et" görünmeli (durum Yeni/İnceleniyor iken).
3. İptal edildikten sonra durum "İptal Edildi" olmalı, buton kaybolmalı.
4. Admin tarafında zaten Onaylandı/Reddedildi/Tamamlandı olmuş bir talepte bu buton hiç
   GÖRÜNMEMELİ.
5. Başka bir kullanıcının talebini `cancel_request` id'sini manuel değiştirerek iptal etmeye çalışın
   (IDOR testi) — reddedilmeli.

## 6. CARİ — Drill-down

1. Bahçera (veya çeki olan herhangi bir cari) → Cari Detay → Finans Hareketleri.
2. Çek satırındaki "İşlem" linkine tıklayın → gerçek Çek/Senet Detayı'na gitmeli (Otomatik yazmamalı).
3. Belge tabanlı bir satış/alış satırında "Belgeyi Aç" gerçek `trade_document_view.php`'ye gitmeli.

## 7. PDF — Rapor İndirme

1. Herhangi bir rapor modülünde "📄 PDF" indirin.
2. KPI kutuları DOLU görünmeli (boş/kesik olmamalı).
3. Türkçe karakterler doğru render olmalı, sayfa düzeni bozulmamalı.
4. Koyu/karışık arkaplan OLMAMALI.

## 8. MOBİL SHELL — Bottom Nav / Composer

1. Gerçek bir iPhone/Android'de İletişim Merkezi → bir sohbeti açın, composer (mesaj yazma kutusu)
   ile alt navigasyonun ÇAKIŞMADIĞINI doğrulayın.
2. Uzun bir liste sayfasında (kullanıcının "bazı uzun sayfalarda taşma" dediği ekranlar — hangi
   ekran olduğu belirtilmemişti, karşılaştığınız her ekranı not edin) alt navigasyonun sabit kalıp
   kalmadığını, sayfanın yatay kaymadığını kontrol edin.
3. 320/375/390/393/414/430px genişliklerin hepsinde (gerçek cihaz yoksa tarayıcı responsive modu)
   tekrarlayın.

## 9. LEGACY UI — Gerçek Route Taraması

1. Menü → her kategori → her liste → her detay → oluştur/düzenle → raporlar → finans → muhasebe →
   çek/senet → stok → üretim → personel → iletişim sırasıyla gezin.
2. `gunluk_rapor.php`/`teklif.php`'nin PDF-önizleme bölümü dışında eski görünüm gördüğünüz her
   ekranı not edin — bu liste `MASTER_STATUS.md` madde 22'yi güncellemek için kullanılacak.

---

## Sonuçları Nasıl İşleyeceksiniz

Her test için PASS/FAIL sonucunu bu dosyaya (ya da doğrudan `MASTER_STATUS.md`'nin ilgili modülüne)
not düşün — "USER PASS" sadece bu tür gerçek bir testten sonra yazılmalı, kod incelemesiyle DEĞİL.
