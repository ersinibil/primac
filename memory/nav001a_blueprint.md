---
NAV-001A — OPTIONAL MODULE NAVIGATION + MOBILE EXPERIENCE REDESIGN BLUEPRINT
Durum: Product Owner incelemesine sunuldu (2026-07-16). KOD YOK — sadece analiz + karar önerisi.
Bu dosya, sohbette sunulan Blueprint'in kalıcı kopyasıdır.
---

(İçerik: bkz. 2026-07-16 tarihli sohbet mesajı — "NAV-001A BLUEPRINT" başlıklı tam rapor.
Kısaca özet: 25 zorunlu bölümün tamamı, gerçek dosya/satır referanslarıyla dolduruldu. Öne çıkan
bulgular: web sidebar'da admin için ~55-58 aynı-anda-görünür link (layout_top.php); mobilde
mobile/index.php'nin "Açık İş"/"Cariler" kartları ve mobile/common.php::botx()'un bottom nav'ı
user_can('jobs')/user_can('contacts') kontrolü OLMADAN herkese aynı şekilde gösteriliyor — yetkisiz
personel tıklarsa page_module_map() otomatik 403 veriyor (somut, kanıtlanmış "Ne nerede?" kök
nedeni). user_preferences tablosu (migration 044) + user_prefs_lib.php + ajax_dashboard_order.php
zaten genel-amaçlı key/value + whitelist-validated sıralama deseni sağlıyor — NAV-001B pin/order
için YENİ migration GEREKMİYOR, aynı desen genişletilebilir. ROADMAP.md'de "Workspace (Multi-Tenant)
Architecture" adında TAMAMEN AYRI bir gelecek proje zaten var — NAV-001'in "Workspace" terimiyle
karışmaması için ayrı isimlendirme önerildi ("Odak Alanı" / "Çalışma Sekmesi").
Önerilen pilot (NAV-001B): web'de sadece 3 destek modülü (Cari/Teklif, Muhasebe Kategorileri,
WhatsApp Ayarları) launcher'a taşı + "Tüm Modüller" launcher ekle; mobilde more.php'yi gruplu
launcher'a çevir, index.php'den KPI/karşılaştırma panellerini kaldır, bottom nav'ı DEĞİŞTİRME.
Detaylı 25 bölüm için sohbet geçmişindeki tam Blueprint mesajına bakın.
