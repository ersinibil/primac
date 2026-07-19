<?php
/* NAV-001B (2026-07-16) — Navigasyon bilgi mimarisi: TEK kaynak. Web sidebar (layout_top.php),
 * Web Module Launcher, mobile/more.php ve mobile/common.php::botx() hepsi buradan beslenir.
 * ds_lib.php SADECE görsel bileşen kütüphanesidir (Product Owner kararı) — bilgi mimarisi
 * kasıtlı olarak ayrı bir dosyada tutuluyor.
 *
 * Bir satır eklemek/taşımak = SADECE nav_taxonomy()'yi değiştirmek. Grup/sıra/yetki tek yerde.
 */

// group (NAV-001 v3, 2026-07-16): primary-olmayan satırlar artık departman değil NİYET kümesine
// göre gruplanıyor — is_takip|sat_tahsil|stok|iletisim|yonet. primary=true satırların 'group'
// değeri kasıtlı olarak eski haliyle bırakıldı (Launcher'a hiç girmedikleri için işlevsiz, ama
// gereksiz diff üretmemek için dokunulmadı).
// perm: null (herkese açık) veya module_list() anahtarı
// primary: web compact sidebar'da (Sabitlenenler dışında) her zaman görünen 7 Çalışma satırı —
// ANLAMI DEĞİŞMEDİ (NAV-001 v3 Product Owner kararı: bu alan başka bir amaçla yeniden yorumlanmaz,
// Launcher görünürlüğü nav_visible_targets()/nav_grouped_for_launcher() kendi kuralıyla belirler).
// adminOnly: perm yetkisi yetmez, ayrıca is_admin() da gerekir (bugünkü mobil/more.php davranışı)
// PX-002 FAZ 2B-i (2026-07-17) — NAV DATA FOUNDATION. Product Owner IA FREEZE kararıyla eklenen
// additive alanlar: 'category' (isler|ticaret|uretim_stok|finans|yonetim|null=global),
// 'categoryOrder' (kategori içi sıra), 'isPrimaryAction' (kategori başına TAM 1 tane true —
// Yönetim'de hiç yok, Kural 9 istisnası), 'searchKeywords' (arama veri sözleşmesi, madde 10),
// 'actionLabel' (yalnızca 'label'den FARKLIYSA var — Product Owner'ın onayladığı Route/Taxonomy
// matrisinin "Yeni Eylem Etiketi" kolonu). Mevcut alanlar (key/label/url/mobileUrl/mobileHide/
// group/perm/primary/adminOnly) BİR TEK KARAKTER bile değişmedi — legacy dal ve eski
// nav_grouped_for_launcher()/nav_pinned_modules()/nav_visible_targets() bunları aynen kullanmaya
// devam ediyor (Product Owner kararı: bu turda silinmez/yeniden anlamlandırılmaz).
// production kesin kararı: category=uretim_stok, label DEĞİŞMEDİ ("Üretimdeki İşleri Gör"),
// isPrimaryAction=true — "Üretimi Başlat" etiketi koda girmedi (Design/Workflow Backlog'da).
function nav_taxonomy(){
    return [
        // ── ÇALIŞMA (primary adayları + iletişim) ───────────────────────────
        ['key'=>'dashboard','label'=>'Komuta Merkezi','url'=>'dashboard.php','mobileUrl'=>'index.php','group'=>'çalışma','perm'=>null,'primary'=>true,'category'=>null,'categoryOrder'=>null,'isPrimaryAction'=>false,'searchKeywords'=>['ana sayfa','home','komuta merkezi']],
        // MOBİL UX/IA KONSOLİDE PASS (2026-07-19, bölüm 6, Product Owner kararı) — "Görevlerimi Gör"/
        // "İş Emirlerini Gör"/"Takvime Bak"/"Onay Bekleyenleri Gör" gibi fiilli eski link dili kısa
        // isimlere çevrildi (Görevlerim/İş Emirleri/Takvim/Onaylar) — SADECE görüntü metni, key/url/
        // perm/category değişmedi. label ile aynı hale gelen actionLabel'lar (artık gereksiz) kaldırıldı.
        ['key'=>'mytasks','label'=>'Görevlerim','url'=>'mytasks.php','group'=>'çalışma','perm'=>null,'primary'=>true,'category'=>'isler','categoryOrder'=>2,'isPrimaryAction'=>false,'searchKeywords'=>['görev','task','yapılacak']],
        ['key'=>'jobs','label'=>'İş / Üretim','url'=>'jobs.php','group'=>'çalışma','perm'=>'jobs','primary'=>true,'actionLabel'=>'İş Emirleri','category'=>'isler','categoryOrder'=>3,'isPrimaryAction'=>false,'searchKeywords'=>['iş emri','iş listesi']],
        ['key'=>'job_new','label'=>'Yeni İş Aç','url'=>'job_new.php','group'=>'is_takip','perm'=>'jobs','primary'=>false,'category'=>'isler','categoryOrder'=>1,'isPrimaryAction'=>true,'searchKeywords'=>['yeni iş','iş aç','sipariş']],
        ['key'=>'production','label'=>'Üretimdeki İşleri Gör','url'=>'production.php','mobileUrl'=>'uretim.php','group'=>'is_takip','perm'=>'jobs','primary'=>false,'category'=>'uretim_stok','categoryOrder'=>1,'isPrimaryAction'=>true,'searchKeywords'=>['üretim','üretim başlat','aşama','üretim panosu']],
        ['key'=>'assembly','label'=>'Montajdaki İşleri Gör','url'=>'assembly.php','mobileHide'=>true,'group'=>'is_takip','perm'=>'jobs','primary'=>false,'category'=>'uretim_stok','categoryOrder'=>2,'isPrimaryAction'=>false,'searchKeywords'=>['montaj']],
        ['key'=>'external','label'=>'Dış Atölye İşlerini Gör','url'=>'external.php','group'=>'is_takip','perm'=>'jobs','primary'=>false,'category'=>'isler','categoryOrder'=>6,'isPrimaryAction'=>false,'searchKeywords'=>['dış atölye','dış tedarik']],
        ['key'=>'design','label'=>'Tasarımdaki İşleri Gör','url'=>'design.php','mobileHide'=>true,'group'=>'is_takip','perm'=>'jobs','primary'=>false,'category'=>'uretim_stok','categoryOrder'=>3,'isPrimaryAction'=>false,'searchKeywords'=>['tasarım','grafik']],
        ['key'=>'work_center','label'=>'İş İstasyonunu Gör','url'=>'work_center.php','mobileHide'=>true,'group'=>'is_takip','perm'=>'jobs','primary'=>false,'category'=>'uretim_stok','categoryOrder'=>4,'isPrimaryAction'=>false,'searchKeywords'=>['iş istasyonu','iş motoru']],
        ['key'=>'approval_waiting','label'=>'Onay Bekleyen Dosyaları Gör','url'=>'approval_waiting.php','group'=>'is_takip','perm'=>'jobs','primary'=>false,'actionLabel'=>'Onaylar','category'=>'isler','categoryOrder'=>5,'isPrimaryAction'=>false,'searchKeywords'=>['onay','müşteri onayı']],
        ['key'=>'tasks','label'=>'Tüm Görevleri Gör','url'=>'tasks.php','group'=>'is_takip','perm'=>'tasks','primary'=>false,'category'=>'isler','categoryOrder'=>7,'isPrimaryAction'=>false,'searchKeywords'=>['tüm görevler','ekip görevi']],
        ['key'=>'takvim','label'=>'Takvim','url'=>'takvim.php','mobileUrl'=>'calendar.php','group'=>'çalışma','perm'=>'jobs','primary'=>true,'category'=>'isler','categoryOrder'=>4,'isPrimaryAction'=>false,'searchKeywords'=>['takvim','termin','tarih']],
        ['key'=>'messages','label'=>'İletişim Merkezi','url'=>'messages.php','group'=>'çalışma','perm'=>null,'primary'=>true,'category'=>null,'categoryOrder'=>null,'isPrimaryAction'=>false,'searchKeywords'=>['mesaj','yazış','chat','iletişim']],
        ['key'=>'notifications','label'=>'Bildirimler','url'=>'notifications.php','group'=>'çalışma','perm'=>null,'primary'=>true,'category'=>null,'categoryOrder'=>null,'isPrimaryAction'=>false,'searchKeywords'=>['bildirim','uyarı']],
        ['key'=>'notes','label'=>'Notlarım','url'=>'notes.php','mobileUrl'=>'mytasks.php','group'=>'çalışma','perm'=>null,'primary'=>true,'category'=>'isler','categoryOrder'=>10,'isPrimaryAction'=>false,'searchKeywords'=>['not','hatırlatma']],
        ['key'=>'requests','label'=>'Talepleri Gör','url'=>'requests.php','group'=>'iletisim','perm'=>null,'primary'=>false,'adminOnly'=>true,'category'=>'isler','categoryOrder'=>9,'isPrimaryAction'=>false,'searchKeywords'=>['talep onayı']],
        ['key'=>'request_new','label'=>'Yeni Talep Oluştur','url'=>'request_new.php','group'=>'iletisim','perm'=>null,'primary'=>false,'category'=>'isler','categoryOrder'=>8,'isPrimaryAction'=>false,'searchKeywords'=>['talep','izin','satın alma talebi']],
        ['key'=>'wa_conversations','label'=>'WhatsApp Konuşmalarını Gör','url'=>'wa_conversations.php','group'=>'iletisim','perm'=>'users','primary'=>false,'category'=>null,'categoryOrder'=>null,'isPrimaryAction'=>false,'searchKeywords'=>['whatsapp','konuşma','wp','wa','mesaj','sohbet','chat']],
        // P0 SON KAPANIŞ (2026-07-18): önceden category='yonetim' idi — web Rail'de "Yönetim"
        // altında görünüyordu, ama bu bir iletişim eylemi (toplu WhatsApp gönderimi), teknik
        // ayar değil. wa_conversations ile aynı desen: category=null (Rail kategori döngüsüne
        // hiç girmez), sadece İletişim Merkezi zinciri üzerinden erişilir (messages.php →
        // WhatsApp sekmesi → wa_conversations.php → "Toplu Gönderim" butonu). Mobilde group
        // zaten 'iletisim' idi (doğruydu, dokunulmadı) — bu satır sadece web/mobil paritesini
        // tamamlıyor. wa_settings.php (teknik bağlantı ayarı) category='yonetim' olarak KALDI.
        ['key'=>'wa_send_now','label'=>'WhatsApp Toplu Mesaj Gönder','url'=>'wa_send_now.php','group'=>'iletisim','perm'=>'users','primary'=>false,'actionLabel'=>'WhatsApp Toplu Gönderim','category'=>null,'categoryOrder'=>null,'isPrimaryAction'=>false,'searchKeywords'=>['toplu mesaj','whatsapp gönder']],

        // ── SAT & TAHSİL ET ──────────────────────────────────────────────────
        ['key'=>'contacts','label'=>'Cari Bul / Görüntüle','url'=>'contacts.php','group'=>'sat_tahsil','perm'=>'contacts','primary'=>false,'actionLabel'=>'Cari Bul','category'=>'ticaret','categoryOrder'=>1,'isPrimaryAction'=>true,'searchKeywords'=>['cari','müşteri','tedarikçi','firma','şirket','borç']],
        ['key'=>'contact_new','label'=>'Yeni Cari Ekle','url'=>'contact_new.php','group'=>'sat_tahsil','perm'=>'contacts','primary'=>false,'category'=>'ticaret','categoryOrder'=>2,'isPrimaryAction'=>false,'searchKeywords'=>['yeni müşteri','yeni cari']],
        ['key'=>'trade_documents','label'=>'Alış / Satış Belgelerini Gör','url'=>'trade_documents.php','mobileHide'=>true,'group'=>'sat_tahsil','perm'=>'contacts','primary'=>false,'actionLabel'=>'Belgeleri Gör','category'=>'ticaret','categoryOrder'=>6,'isPrimaryAction'=>false,'searchKeywords'=>['belge','irsaliye','fatura']],
        ['key'=>'teklif','label'=>'Teklif Hazırla','url'=>'teklif.php','group'=>'sat_tahsil','perm'=>'teklif','primary'=>false,'category'=>'ticaret','categoryOrder'=>3,'isPrimaryAction'=>false,'searchKeywords'=>['teklif','fiyat teklifi','fiyat','proforma','quotation']],
        ['key'=>'sales','label'=>'Satış Yap','url'=>'sales.php','group'=>'sat_tahsil','perm'=>'stock','primary'=>false,'category'=>'ticaret','categoryOrder'=>4,'isPrimaryAction'=>false,'searchKeywords'=>['satış','sat']],
        ['key'=>'purchase','label'=>'Satın Alma Yap','url'=>'purchase.php','group'=>'sat_tahsil','perm'=>'stock','primary'=>false,'category'=>'ticaret','categoryOrder'=>5,'isPrimaryAction'=>false,'searchKeywords'=>['satın alma','alış']],
        ['key'=>'finance_new_in','label'=>'Tahsilat Al','url'=>'finance_new.php?direction=in','mobileUrl'=>'collection.php','group'=>'sat_tahsil','perm'=>'finance','primary'=>false,'category'=>'finans','categoryOrder'=>1,'isPrimaryAction'=>true,'searchKeywords'=>['tahsilat','tahsil et']],
        ['key'=>'finance_new_out','label'=>'Ödeme Yap','url'=>'finance_new.php?direction=out','mobileUrl'=>'payment.php','group'=>'sat_tahsil','perm'=>'finance','primary'=>false,'category'=>'finans','categoryOrder'=>2,'isPrimaryAction'=>false,'searchKeywords'=>['ödeme','gider']],
        ['key'=>'finance_transfer','label'=>'Hesaplar Arası Transfer Yap','url'=>'finance_transfer.php','mobileUrl'=>'transfer.php','group'=>'sat_tahsil','perm'=>'finance','primary'=>false,'actionLabel'=>'Transfer Yap','category'=>'finans','categoryOrder'=>3,'isPrimaryAction'=>false,'searchKeywords'=>['transfer','hesap aktar']],
        ['key'=>'checks_notes','label'=>'Çek / Senet Takip Et','url'=>'checks_notes.php','group'=>'sat_tahsil','perm'=>'finance','primary'=>false,'actionLabel'=>'Çek ve Senetleri Gör','category'=>'finans','categoryOrder'=>6,'isPrimaryAction'=>false,'searchKeywords'=>['çek','senet','vade']],

        // ── STOK YÖNET ───────────────────────────────────────────────────────
        ['key'=>'stock','label'=>'Stok Kontrol Et','url'=>'stock.php','group'=>'stok','perm'=>'stock','primary'=>false,'category'=>'uretim_stok','categoryOrder'=>5,'isPrimaryAction'=>false,'searchKeywords'=>['stok','envanter']],
        ['key'=>'product_new','label'=>'Yeni Ürün Ekle','url'=>'product_new.php','group'=>'stok','perm'=>'stock','primary'=>false,'category'=>'uretim_stok','categoryOrder'=>7,'isPrimaryAction'=>false,'searchKeywords'=>['yeni ürün']],
        ['key'=>'stock_movement_new','label'=>'Stok Giriş / Çıkış Yap','url'=>'stock_movement_new.php?type=in','group'=>'stok','perm'=>'stock','primary'=>false,'actionLabel'=>'Stok Hareketi Yap','category'=>'uretim_stok','categoryOrder'=>6,'isPrimaryAction'=>false,'searchKeywords'=>['stok hareketi','giriş çıkış']],
        ['key'=>'product_categories','label'=>'Ürün Kategorilerini Düzenle','url'=>'product_categories.php','group'=>'stok','perm'=>'stock','primary'=>false,'category'=>'uretim_stok','categoryOrder'=>8,'isPrimaryAction'=>false,'searchKeywords'=>['kategori','ürün kategorisi']],
        ['key'=>'product_taxonomy','label'=>'Marka / Birim Düzenle','url'=>'product_taxonomy.php','group'=>'stok','perm'=>'stock','primary'=>false,'category'=>'uretim_stok','categoryOrder'=>9,'isPrimaryAction'=>false,'searchKeywords'=>['marka','birim']],

        // ── YÖNET (arka ofis / raporlama / sistem) ──────────────────────────
        ['key'=>'finance','label'=>'Kasa / Banka Panelini Gör','url'=>'finance.php','mobileUrl'=>'kasa.php','group'=>'yonet','perm'=>'finance','primary'=>false,'actionLabel'=>'Kasa ve Bankaları Gör','category'=>'finans','categoryOrder'=>4,'isPrimaryAction'=>false,'searchKeywords'=>['kasa','banka','finans paneli']],
        ['key'=>'finance_accounts','label'=>'Banka / Kasa / Kart Hesaplarını Yönet','url'=>'finance_accounts.php','mobileHide'=>true,'group'=>'yonet','perm'=>'finance','primary'=>false,'actionLabel'=>'Hesapları Yönet','category'=>'finans','categoryOrder'=>5,'isPrimaryAction'=>false,'searchKeywords'=>['hesap','pos','kart']],
        ['key'=>'accounting','label'=>'Muhasebe Kayıtlarını Gör','url'=>'accounting.php','group'=>'yonet','perm'=>'muhasebe','primary'=>false,'category'=>'finans','categoryOrder'=>7,'isPrimaryAction'=>false,'searchKeywords'=>['muhasebe','gider gelir']],
        ['key'=>'accounting_categories','label'=>'Muhasebe Kategorilerini Düzenle','url'=>'accounting_categories.php','group'=>'yonet','perm'=>'muhasebe','primary'=>false,'adminOnly'=>true,'category'=>'finans','categoryOrder'=>8,'isPrimaryAction'=>false,'searchKeywords'=>['muhasebe kategori']],
        // PERSONEL+KULLANICI+YETKİ TEKLEŞTİRME (2026-07-19, Product Owner kararı): "Personel" ANA
        // varlık — Yönetim altında tek giriş "Personeller" (personnel.php), OTS hesabı/yetkileri
        // personel detayının ("OTS Hesabı & Yetkiler" sekmesi) içinde yönetiliyor. "Yeni Personel
        // Ekle" artık ayrı bir menü maddesi DEĞİL — personnel.php/mobile/personnel.php'nin kendi
        // içindeki "+ Yeni Personel" butonundan erişiliyor (route personnel_new.php SİLİNMEDİ,
        // sadece kategori listesinden çıktı — category=null, arama/doğrudan URL ile hâlâ erişilebilir).
        ['key'=>'personnel','label'=>'Personeller','url'=>'personnel.php','group'=>'yonet','perm'=>'personnel','primary'=>false,'actionLabel'=>'Personeller','category'=>'yonetim','categoryOrder'=>1,'isPrimaryAction'=>false,'searchKeywords'=>['personel','ekip','maaş','çalışan','kullanıcı','yetki','hesap']],
        ['key'=>'personnel_new','label'=>'Yeni Personel Ekle','url'=>'personnel_new.php','group'=>'yonet','perm'=>'personnel','primary'=>false,'category'=>null,'categoryOrder'=>null,'isPrimaryAction'=>false,'searchKeywords'=>['yeni personel']],
        ['key'=>'kpi','label'=>'Performans / KPI Gör','url'=>'kpi.php','group'=>'yonet','perm'=>'personnel','primary'=>false,'category'=>'yonetim','categoryOrder'=>3,'isPrimaryAction'=>false,'searchKeywords'=>['performans','kpi']],
        ['key'=>'report','label'=>'Rapor Al','url'=>'report.php','group'=>'yonet','perm'=>'report','primary'=>false,'actionLabel'=>'Raporları Gör','category'=>'yonetim','categoryOrder'=>5,'isPrimaryAction'=>false,'searchKeywords'=>['rapor','özet']],
        ['key'=>'gunluk_rapor','label'=>'Günlük İş Raporu Al','url'=>'gunluk_rapor.php','group'=>'yonet','perm'=>'report','primary'=>false,'category'=>'yonetim','categoryOrder'=>6,'isPrimaryAction'=>false,'searchKeywords'=>['günlük rapor']],
        ['key'=>'contacts_report','label'=>'Cari Ekstresi Al','url'=>'contacts_report.php','group'=>'yonet','perm'=>'contacts','primary'=>false,'category'=>'ticaret','categoryOrder'=>7,'isPrimaryAction'=>false,'searchKeywords'=>['cari ekstre','bakiye raporu']],
        ['key'=>'activity','label'=>'Son İşlemleri Gör','url'=>'activity.php','group'=>'yonet','perm'=>null,'primary'=>false,'adminOnly'=>true,'category'=>'yonetim','categoryOrder'=>7,'isPrimaryAction'=>false,'searchKeywords'=>['son işlemler','aktivite']],
        // PERSONEL+KULLANICI+YETKİ TEKLEŞTİRME (2026-07-19, Product Owner kararı — bir önceki
        // "adminOnly + en düşük sıra" ara adımının SONRAKI turu): günlük personel/hesap/yetki akışı
        // TAMAMEN personnel.php→personnel_edit.php'nin "OTS Hesabı & Yetkiler" sekmesinde olduğu
        // için bu giriş artık Yönetim kategori listesinde HİÇ görünmüyor (category=null) — normal
        // kullanıcı akışından tamamen çıktı. Route (users.php) ve backend/auth mantığı SİLİNMEDİ:
        // toplu WhatsApp gönderimi + bağsız hesap temizliği gibi nadir/ileri senaryolar için admin
        // hâlâ doğrudan URL ile erişebiliyor, arama sonuçlarında da (searchKeywords) bulunabiliyor.
        ['key'=>'users','label'=>'Sistem Kullanıcıları','url'=>'users.php','group'=>'yonet','perm'=>'users','primary'=>false,'adminOnly'=>true,'actionLabel'=>'Sistem Kullanıcılarını Yönet','category'=>null,'categoryOrder'=>null,'isPrimaryAction'=>false,'searchKeywords'=>['sistem kullanıcıları','ileri yönetim']],
        ['key'=>'audit_log','label'=>'Denetim Günlüğünü Gör','url'=>'audit_log.php','group'=>'yonet','perm'=>'users','primary'=>false,'actionLabel'=>'Denetim Kayıtlarını Gör','category'=>'yonetim','categoryOrder'=>8,'isPrimaryAction'=>false,'searchKeywords'=>['denetim','log']],
        ['key'=>'wa_settings','label'=>'WhatsApp Ayarlarını Düzenle','url'=>'wa_settings.php','group'=>'yonet','perm'=>'users','primary'=>false,'category'=>'yonetim','categoryOrder'=>9,'isPrimaryAction'=>false,'searchKeywords'=>['whatsapp ayar']],
        ['key'=>'brand_settings','label'=>'Logo / Marka Düzenle','url'=>'brand_settings.php','group'=>'yonet','perm'=>'users','primary'=>false,'category'=>'yonetim','categoryOrder'=>11,'isPrimaryAction'=>false,'searchKeywords'=>['logo','marka']],
        ['key'=>'temizle_veri','label'=>'Veri Temizle','url'=>'temizle_veri.php','group'=>'yonet','perm'=>null,'primary'=>false,'adminOnly'=>true,'category'=>'yonetim','categoryOrder'=>12,'isPrimaryAction'=>false,'searchKeywords'=>['veri temizle']],
        ['key'=>'profile','label'=>'Profilim / Şifre','url'=>'profile.php','group'=>'yonet','perm'=>null,'primary'=>false,'category'=>null,'categoryOrder'=>null,'isPrimaryAction'=>false,'searchKeywords'=>['profil','şifre','hesap']],
    ];
}

function nav_group_label($group){
    return [
        'is_takip'=>'İş Takip Et', 'sat_tahsil'=>'Sat & Tahsil Et', 'stok'=>'Stok Yönet',
        'iletisim'=>'İletişim & Talep', 'yonet'=>'Yönet',
    ][$group] ?? $group;
}

// ══════════════════════════════════════════════════════════════════════════
// PX-002 FAZ 2B-i (2026-07-17) — NAV DATA FOUNDATION. Product Owner IA FREEZE kararıyla
// onaylanan 5 kategori (İşler/Ticaret/Üretim & Stok/Finans/Yönetim) için yeni okuma katmanı.
// Eski nav_grouped_for_launcher()/nav_pinned_modules()/nav_visible_targets() (aşağıda,
// DEĞİŞMEDİ) Compact Mode'da artık ÇAĞRILMIYOR — Launcher/pin retirement kararı (Flag 1) —
// ama silinmedi: Legacy Mode ve olası geri-dönüş senaryosu için aynen duruyor.
// ══════════════════════════════════════════════════════════════════════════

// Kategori anahtarlarının kesin listesi — whitelist doğrulaması için TEK kaynak (FAZ 2B-iii'te
// mobile/more.php'nin ?open= parametresi bu listeye karşı doğrulanacak).
function nav_category_keys(){
    return ['isler','ticaret','uretim_stok','finans','yonetim'];
}

// Görüntü ismi TEK kaynağı — web Rail, mobil Menü/İşler, arama sonucu, breadcrumb hepsi
// buradan okur (Product Owner kararı: "her yüzeyde aynı ad").
function nav_category_label($category){
    return [
        'isler'=>'İşler', 'ticaret'=>'Ticaret', 'uretim_stok'=>'Üretim & Stok',
        'finans'=>'Finans', 'yonetim'=>'Yönetim',
    ][$category] ?? $category;
}

// Bir kategorideki yetkili satırlar, categoryOrder'a göre sıralı; isPrimaryAction=true olan
// satır (varsa) her zaman en başta gösterilir. Filtre sırası nav_authorized_modules() ile
// BİREBİR aynı (adminOnly -> perm -> platform/mobileHide) — madde 8'in "tek ortak filtre
// sırası" kararı, üç fonksiyonda da (bu, nav_global_items, nav_search_index) tekrarlanıyor.
function nav_items_for_category($canSee, $isAdmin, $category, $platform = 'web'){
    $out = [];
    foreach(nav_taxonomy() as $item){
        if(($item['category'] ?? null) !== $category) continue;
        if(!empty($item['adminOnly']) && !$isAdmin) continue;
        if($item['perm']!==null && !$canSee($item['perm'])) continue;
        if($platform === 'mobile' && !empty($item['mobileHide'])) continue;
        $out[] = $item;
    }
    usort($out, function($a, $b){
        $ap = empty($a['isPrimaryAction']) ? 1 : 0;
        $bp = empty($b['isPrimaryAction']) ? 1 : 0;
        if($ap !== $bp) return $ap <=> $bp;
        return ($a['categoryOrder'] ?? 999) <=> ($b['categoryOrder'] ?? 999);
    });
    return $out;
}

// MOBİL UX/IA KONSOLİDE PASS (2026-07-19, Product Owner kararı, bölüm 4) — Menü ana ekranındaki
// kategori kutularında "N aksiyon" gibi kullanıcı açısından anlamsız sayaçlar yerine kısa, somut
// içerik özeti. Yeni bir alan/route İCAT EDİLMEDİ, sadece sunum metni — nav_taxonomy() değişmedi.
function nav_category_short_desc($category, $fallbackCount = 0){
    $map = [
        'isler'      => 'Görevler · İş Emirleri · Takvim',
        'ticaret'    => 'Satış · Alış · Teklif · Belgeler',
        'uretim_stok'=> 'Ürünler · Stok · Üretim · Dış Atölye',
        'finans'     => 'Tahsilat · Ödeme · Kasa/Banka · Çek/Senet',
        'yonetim'    => 'Personel · Sistem · Raporlama',
    ];
    return $map[$category] ?? ($fallbackCount.' aksiyon');
}

// MOBİL UX/IA KONSOLİDE PASS (2026-07-19, Product Owner kararı, bölüm 5) — kategori içi ekranlar
// düz alt alta link listesiydi ("eski launcher hissi", günlük işlem ile yönetim aksiyonu aynı
// görsel ağırlıkta). Bu SADECE bir SUNUM gruplaması — nav_taxonomy()'nin key/perm/category/url
// alanlarına dokunmaz, yeni bir route YOK. Haritada karşılığı olmayan (yeni eklenen ama burada
// unutulan) bir satır sessizce "Diğer" grubuna düşer — kırılma yok, sadece gruplanmamış görünür.
function nav_category_menu_groups(){
    return [
        'ticaret' => [
            'Hızlı İşlemler' => ['sales','purchase','teklif'],
            'Cariler'        => ['contacts','contact_new','contacts_report'],
            'Belgeler'       => ['trade_documents'],
        ],
        'uretim_stok' => [
            'Operasyon' => ['production','assembly','design','work_center','stock','stock_movement_new'],
            'Tanımlar'  => ['product_new','product_categories','product_taxonomy'],
        ],
        'finans' => [
            'Günlük İşlemler'  => ['finance_new_in','finance_new_out','finance_transfer'],
            'Hesaplar'         => ['finance','finance_accounts'],
            'Belge/Enstrüman'  => ['checks_notes'],
            'Muhasebe'         => ['accounting','accounting_categories'],
        ],
        'yonetim' => [
            'Personel'   => ['personnel','kpi'],
            'Raporlama'  => ['report','gunluk_rapor'],
            'Sistem'     => ['users','audit_log','wa_settings','brand_settings','temizle_veri'],
        ],
        'isler' => [
            'Bugün'        => ['mytasks','jobs','takvim'],
            'Talep & Onay' => ['approval_waiting','requests','request_new'],
            'Diğer'        => ['job_new','external','tasks','notes'],
        ],
    ];
}

// $items = nav_items_for_category() sonucu. Dönüş: [['label'=>string|null,'items'=>[...]], ...].
// Haritalanmamış bir kategori gelirse (bugün hepsi haritalı) eski düz-liste davranışına (tek grup,
// label=null) sessizce döner — çağıran taraf (mobile/more.php) label null ise başlık basmaz.
function nav_group_category_items($items, $category){
    $map = nav_category_menu_groups()[$category] ?? null;
    if(!$map) return $items ? [['label'=>null,'items'=>$items]] : [];
    $byKey = [];
    foreach($items as $it){ $byKey[$it['key']] = $it; }
    $used = [];
    $out = [];
    foreach($map as $groupLabel=>$keys){
        $groupItems = [];
        foreach($keys as $k){ if(isset($byKey[$k])){ $groupItems[] = $byKey[$k]; $used[$k] = true; } }
        if($groupItems) $out[] = ['label'=>$groupLabel,'items'=>$groupItems];
    }
    $rest = [];
    foreach($items as $it){ if(empty($used[$it['key']])) $rest[] = $it; }
    if($rest) $out[] = ['label'=>'Diğer','items'=>$rest];
    return $out;
}

// Kategori DIŞI (category===null) global katman satırları — Ana Sayfa/Mesajlar/Bildirimler/
// Hesap gibi her zaman erişilebilir hedefler. Aynı filtre sırası burada da geçerli.
function nav_global_items($canSee, $isAdmin, $platform = 'web'){
    $out = [];
    foreach(nav_taxonomy() as $item){
        if(($item['category'] ?? null) !== null) continue;
        if(!empty($item['adminOnly']) && !$isAdmin) continue;
        if($item['perm']!==null && !$canSee($item['perm'])) continue;
        if($platform === 'mobile' && !empty($item['mobileHide'])) continue;
        $out[] = $item;
    }
    return $out;
}

// Arama veri sözleşmesi (madde 10) — search.php'nin motoru/UI'si bu turda YENİDEN YAZILMIYOR,
// bu yalnızca ileride kullanılacak düz veri listesi. Yetkisiz/platformda gizli hedef ASLA
// girmez (madde: "Menüde görünmüyorsa arama indeksinde de görünmemeli"). keywords: searchKeywords
// + label + actionLabel birleşimi, küçük harfe çevrilmiş, boşluklardan arındırılmış, tekilleştirilmiş.
function nav_search_index($canSee, $isAdmin, $platform = 'web'){
    $out = [];
    foreach(nav_taxonomy() as $item){
        if(!empty($item['adminOnly']) && !$isAdmin) continue;
        if($item['perm']!==null && !$canSee($item['perm'])) continue;
        if($platform === 'mobile' && !empty($item['mobileHide'])) continue;
        $category = $item['category'] ?? null;
        $actionLabel = $item['actionLabel'] ?? $item['label'];
        $raw = array_merge($item['searchKeywords'] ?? [], [$item['label'], $actionLabel]);
        $seen = [];
        foreach($raw as $k){
            $k = mb_strtolower(trim((string)$k));
            if($k !== '') $seen[$k] = true;
        }
        $out[] = [
            'key' => $item['key'],
            'actionLabel' => $actionLabel,
            'categoryKey' => $category,
            'categoryLabel' => $category !== null ? nav_category_label($category) : 'Global',
            'platformUrl' => nav_url_for_platform($item, $platform),
            'keywords' => array_keys($seen),
        ];
    }
    return $out;
}

// $canSee: function(string $perm): bool  ·  $isAdmin: adminOnly satırlar için
function nav_authorized_modules($canSee, $isAdmin){
    $out = [];
    foreach(nav_taxonomy() as $item){
        if(!empty($item['adminOnly']) && !$isAdmin) continue;
        if($item['perm']!==null && !$canSee($item['perm'])) continue;
        $out[] = $item;
    }
    return $out;
}

function nav_module_by_key($key){
    foreach(nav_taxonomy() as $item) if($item['key']===$key) return $item;
    return null;
}

// PX-002 / FAIL düzeltmesi (2026-07-17) — kök neden: nav_taxonomy() satır başına TEK url
// tutuyordu (web dosya adı), mobil sayfalar web'den farklı isimlerde olduğu için (örn.
// finance.php -> mobile/kasa.php) mobil Menü/Sabitlenenler doğrudan bu url'i kullanınca 404
// veriyordu. 'mobileUrl' varsa mobilde o kullanılır, yoksa 'url' aynen kullanılır (web hiç
// etkilenmez — bu fonksiyon platform='web' için her zaman $item['url'] döner).
function nav_url_for_platform($item, $platform){
    if($platform === 'mobile' && !empty($item['mobileUrl'])) return $item['mobileUrl'];
    return $item['url'];
}

// Yetkili + primary=true olan satırlar (compact sidebar'ın sabit Çalışma listesi)
function nav_primary_modules($canSee, $isAdmin){
    $out = [];
    foreach(nav_authorized_modules($canSee, $isAdmin) as $item){
        if(!empty($item['primary'])) $out[] = $item;
    }
    return $out;
}

// Platforma özel, DAİMA görünür (bottom-nav gibi) sabit hedefler — Elif'in NAV-001 v3 parite
// incelemesinde bulduğu boşluk: mobilde 'contacts' bottom-nav'ın sabit "Cari" ikonu olduğu için
// ne Launcher grubunda ne "Sabitlenenler"de tekrar görünmeli, kalıcı/eski bir pin kaydı olsa bile.
// TEK yerde tanımlı — nav_pinned_modules()/nav_visible_targets()/ajax_nav_prefs.php hepsi buradan okur.
function nav_platform_fixed_keys($platform){
    return $platform === 'mobile' ? ['contacts'] : [];
}

// Yetkili + pin edilmiş (ve primary OLMAYAN — primary zaten hep görünür) satırlar, kayıtlı sırayla.
// $platform: platforma özel sabit hedefler (nav_platform_fixed_keys) burada da dışlanır — eski/
// elle oluşturulmuş bir pin kaydı olsa bile "Sabitlenenler" bottom-nav ile tekrar etmez.
function nav_pinned_modules($canSee, $isAdmin, $pinnedKeysCsv, $platform = 'web'){
    $keys = array_filter(array_map('trim', explode(',', (string)$pinnedKeysCsv)));
    $fixed = nav_platform_fixed_keys($platform);
    $authorized = nav_authorized_modules($canSee, $isAdmin);
    $byKey = [];
    foreach($authorized as $item) $byKey[$item['key']] = $item;
    $out = [];
    foreach($keys as $k){
        if(in_array($k, $fixed, true)) continue;
        if(!isset($byKey[$k]) || !empty($byKey[$k]['primary'])) continue;
        // Mobilde hiç karşılığı olmayan bir modül eski/elle oluşturulmuş bir pin kaydından
        // geliyor olsa bile mobilde 404 satırı olarak render edilmesin.
        if($platform === 'mobile' && !empty($byKey[$k]['mobileHide'])) continue;
        $out[] = $byKey[$k];
    }
    return $out;
}

// NAV-001 v3 (2026-07-16) Product Owner kararı — TEK genel görünürlük kaynağı: "Bu kullanıcı için
// şu anda fiilen görünür olan navigasyon hedefleri nelerdir?" Sidebar'a özel DEĞİL — Sidebar,
// Command Launcher ve ileride eklenebilecek başka yüzeyler (Dashboard, Capture, ...) hepsi bu
// TEK fonksiyona sorar. Bugünkü girdiler: yetkili primary hedefler + kullanıcının kişisel
// sabitledikleri + (mobilde) bottom nav'ın sabit "Cari" ikonu (jobs zaten primary=true olduğu
// için ayrıca eklenmesine gerek yok). `primary` alanının anlamı burada DEĞİŞMİYOR — sadece
// nav_primary_modules() üzerinden, kendi orijinal amacıyla okunuyor.
function nav_visible_targets($canSee, $isAdmin, $pinnedKeysCsv, $platform = 'web'){
    $out = nav_platform_fixed_keys($platform); // örn. mobilde bottom nav'ın sabit "Cari" ikonu
    foreach(nav_primary_modules($canSee, $isAdmin) as $item) $out[] = $item['key'];
    foreach(nav_pinned_modules($canSee, $isAdmin, $pinnedKeysCsv, $platform) as $item) $out[] = $item['key'];
    return array_values(array_unique($out));
}

// Command Launcher'da gösterilecek liste, niyet kümesine göre gruplu — Sidebar/bottom-nav'da
// (+ kişisel sabitlenenlerde) ZATEN görünen hiçbir hedefi tekrar etmez (nav_visible_targets()).
function nav_grouped_for_launcher($canSee, $isAdmin, $pinnedKeysCsv = '', $platform = 'web'){
    $hidden = nav_visible_targets($canSee, $isAdmin, $pinnedKeysCsv, $platform);
    $out = ['is_takip'=>[], 'sat_tahsil'=>[], 'stok'=>[], 'iletisim'=>[], 'yonet'=>[]];
    foreach(nav_authorized_modules($canSee, $isAdmin) as $item){
        if(in_array($item['key'], $hidden, true)) continue;
        // Mobilde hiç karşılığı olmayan modüller (assembly/design/work_center/trade_documents/
        // finance_accounts) mobil Launcher'da hiç gösterilmez — legacy mobil menüde de zaten
        // hiç yoktular, bu davranışa dönüş, kayıp değil (2026-07-17 FAIL düzeltmesi).
        if($platform === 'mobile' && !empty($item['mobileHide'])) continue;
        $out[$item['group']][] = $item;
    }
    return $out;
}

function nav_module_is_active($key, $currentScript){
    $item = nav_module_by_key($key);
    if(!$item) return false;
    $url = explode('?', $item['url'])[0];
    return $url === $currentScript;
}

// Tri-state layout modu: kullanıcı açıkça seçtiyse o kazanır; seçmediyse admin varsayılan
// compact, diğerleri legacy görür (Product Owner kararı — "admin hesabı" otomatik pilotta).
function nav_effective_mode($savedMode, $isAdmin, $isPilotUser){
    // RELEASE 0.9 — LEGACY TEMİZLİĞİ (2026-07-17, Product Owner kararı): "ne mobilde ne web de eski
    // görünüm görmek istemiyorum, bütün sayfalar yeni görünüme geçsin" — Compact/DS artık pilot-gated
    // değil, HERKES için tek mod. Daha önce kaydedilmiş 'legacy' tercihi de artık göz ardı edilir
    // (bilinçli — eski tercih varlığını korumak "eski görünüme dönüş" kapısı bırakırdı).
    return 'compact';
}

// app_settings üzerinden pilot kullanıcı listesi — BU SPRİNTTE ADMIN ARAYÜZÜ YOK (bilinçli kapsam
// kararı, forced-visibility UI ile aynı gerekçeyle NAV-001C'ye bırakıldı). Okuma tarafı hazır;
// admin gerekirse app_settings'e elle 'nav_pilot_user_ids' => "3,7,12" yazabilir.
function nav_is_pilot_user($userId){
    if(!function_exists('get_setting')){
        if(is_file(__DIR__.'/share_lib.php')) require_once __DIR__.'/share_lib.php';
    }
    if(!function_exists('get_setting')) return false;
    try{
        $raw = get_setting('nav_pilot_user_ids','');
        if($raw==='') return false;
        $ids = array_filter(array_map('trim', explode(',', $raw)));
        return in_array((string)$userId, $ids, true);
    }catch(Throwable $e){ return false; }
}
