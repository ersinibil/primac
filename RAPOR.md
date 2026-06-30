# ACANS OS — Sistem İnceleme Raporu (2026-06-28)

Sistem: PHP 7.2 ERP + bağımsız mobil PWA. Web = masaüstü panel (kök), Mobil = `mobile/` (PWA).
Canlı test: acanstr.com/dev. DB: u7883898_primacos.

## ✅ ÇALIŞAN (sağlam)
- Web ERP: dashboard, işler, cari, finans, stok, ticari belgeler, personel — tam
- Mobil: giriş, **kişiden kişiye mesajlaşma** (sesli banner + Web Push kapalıyken bildirim)
- Cari kartı (bakiye + hareket + ara/whatsapp)
- Personel menü kısıtı (mobilde finansal ekranlar kapalı)
- PWA kurulum (ana ekrana ekle, standalone, safe-area responsive)

## ❌ ÇALIŞMAYAN / YARIM (öncelik sırası)

| # | Sorun | Etki | Öncelik |
|---|---|---|---|
| 1 | **mobile/sales.php placeholder** — satış yapılamıyor | Saha satışı yok | 🔴 Yüksek |
| 2 | **mobile/job_view.php sadece görüntüleme** — durum güncelleme/aksiyon yok | İş takibi eksik | 🔴 Yüksek |
| 3 | **mobile/jobs.php basit liste** — personel kendi işini filtreleyemiyor, durum değişmiyor | Personel iş akışı zayıf | 🔴 Yüksek |
| 4 | **Raporlama yok** — satış/cari/stok/personel raporu yok (eski reports.php silinmiş) | Yönetim körlüğü | 🟠 Orta |
| 5 | **Web tasks.php salt-okunur** — web'de görev atanamıyor | Görev yönetimi tek yönlü | 🟠 Orta |
| 6 | **Global arama dekoratif** — web topbar arama kutusu çalışmıyor (search.php yok) | Hızlı erişim yok | 🟠 Orta |
| 7 | **Web'de rol kısıtı yok** — personel web ERP'de her şeyi görebilir | Güvenlik/yetki | 🟠 Orta |
| 8 | **Bildirim merkezi ortak** — internal_notifications herkese aynı, kişiye özel değil | Karışık bildirim | 🟡 Düşük |
| 9 | **Ölü Telegram kodu** — 13 telegram dosyası, kaldırıldı ama duruyor | Dağınıklık | 🟡 Düşük |
| 10 | **Artık stub'lar** — assembly.php, design.php, dashboard-2.php ince/kullanılmıyor | Dağınıklık | 🟡 Düşük |

## 🔒 GÜVENLİK
- Tanı dosyaları sunucuda kalmış olabilir (kontrol.php, iz.php, bak.php, fix_login.php, ac_extract.php, kaynak.php) — **DB şifresi/erişim sızdırır, SİLİNMELİ**
- config.php düz şifre (normal) ama `.htaccess` ile korunmalı
- push_subscribe / poll — auth kontrollü ✅

## 🎯 ÖNERİLEN YOL HARİTASI
**Faz 1 — Mobil saha (en kritik):**
- Satış ekranı: ürün seç + miktar + fiyat → stok düş + tahsilat
- İş detayı: durum güncelle (Başla/Tamamla), fotoğraf/not ekle
- İş listesi: personel kendi işleri + durum filtreleri

**Faz 2 — Yönetim:**
- Raporlar: satış, cari bakiye, stok değeri, personel performansı (+Excel/PDF)
- Web rol kısıtı (personel sınırlı görsün)
- Global arama (iş/cari/stok/personel)

**Faz 3 — Temizlik:**
- Telegram + stub dosyaları kaldır
- Bildirim merkezini kişiselleştir
- Tanı dosyalarını sil
