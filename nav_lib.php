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
function nav_taxonomy(){
    return [
        // ── ÇALIŞMA (primary adayları + iletişim) ───────────────────────────
        ['key'=>'dashboard','label'=>'Komuta Merkezi','url'=>'dashboard.php','group'=>'çalışma','perm'=>null,'primary'=>true],
        ['key'=>'mytasks','label'=>'Görevlerim','url'=>'mytasks.php','group'=>'çalışma','perm'=>null,'primary'=>true],
        ['key'=>'jobs','label'=>'İş / Üretim','url'=>'jobs.php','group'=>'çalışma','perm'=>'jobs','primary'=>true],
        ['key'=>'job_new','label'=>'Yeni İş Aç','url'=>'job_new.php','group'=>'is_takip','perm'=>'jobs','primary'=>false],
        ['key'=>'production','label'=>'Üretimdeki İşleri Gör','url'=>'production.php','mobileUrl'=>'uretim.php','group'=>'is_takip','perm'=>'jobs','primary'=>false],
        ['key'=>'assembly','label'=>'Montajdaki İşleri Gör','url'=>'assembly.php','mobileHide'=>true,'group'=>'is_takip','perm'=>'jobs','primary'=>false],
        ['key'=>'external','label'=>'Dış Atölye İşlerini Gör','url'=>'external.php','group'=>'is_takip','perm'=>'jobs','primary'=>false],
        ['key'=>'design','label'=>'Tasarımdaki İşleri Gör','url'=>'design.php','mobileHide'=>true,'group'=>'is_takip','perm'=>'jobs','primary'=>false],
        ['key'=>'work_center','label'=>'İş İstasyonunu Gör','url'=>'work_center.php','mobileHide'=>true,'group'=>'is_takip','perm'=>'jobs','primary'=>false],
        ['key'=>'approval_waiting','label'=>'Onay Bekleyen Dosyaları Gör','url'=>'approval_waiting.php','group'=>'is_takip','perm'=>'jobs','primary'=>false],
        ['key'=>'tasks','label'=>'Tüm Görevleri Gör','url'=>'tasks.php','group'=>'is_takip','perm'=>'tasks','primary'=>false],
        ['key'=>'takvim','label'=>'Takvim','url'=>'takvim.php','group'=>'çalışma','perm'=>'jobs','primary'=>true],
        ['key'=>'messages','label'=>'Mesajlar','url'=>'messages.php','group'=>'çalışma','perm'=>null,'primary'=>true],
        ['key'=>'notifications','label'=>'Bildirimler','url'=>'notifications.php','group'=>'çalışma','perm'=>null,'primary'=>true],
        ['key'=>'notes','label'=>'Notlarım','url'=>'notes.php','group'=>'çalışma','perm'=>null,'primary'=>true],
        ['key'=>'requests','label'=>'Talepleri Gör','url'=>'requests.php','group'=>'iletisim','perm'=>null,'primary'=>false,'adminOnly'=>true],
        ['key'=>'request_new','label'=>'Yeni Talep Oluştur','url'=>'request_new.php','group'=>'iletisim','perm'=>null,'primary'=>false],
        ['key'=>'wa_conversations','label'=>'WhatsApp Konuşmalarını Gör','url'=>'wa_conversations.php','group'=>'iletisim','perm'=>'users','primary'=>false],
        ['key'=>'wa_send_now','label'=>'WhatsApp Toplu Mesaj Gönder','url'=>'wa_send_now.php','group'=>'iletisim','perm'=>'users','primary'=>false],

        // ── SAT & TAHSİL ET ──────────────────────────────────────────────────
        ['key'=>'contacts','label'=>'Cari Bul / Görüntüle','url'=>'contacts.php','group'=>'sat_tahsil','perm'=>'contacts','primary'=>false],
        ['key'=>'contact_new','label'=>'Yeni Cari Ekle','url'=>'contact_new.php','group'=>'sat_tahsil','perm'=>'contacts','primary'=>false],
        ['key'=>'trade_documents','label'=>'Alış / Satış Belgelerini Gör','url'=>'trade_documents.php','mobileHide'=>true,'group'=>'sat_tahsil','perm'=>'contacts','primary'=>false],
        ['key'=>'teklif','label'=>'Teklif Hazırla','url'=>'teklif.php','group'=>'sat_tahsil','perm'=>'teklif','primary'=>false],
        ['key'=>'sales','label'=>'Satış Yap','url'=>'sales.php','group'=>'sat_tahsil','perm'=>'stock','primary'=>false],
        ['key'=>'purchase','label'=>'Satın Alma Yap','url'=>'purchase.php','group'=>'sat_tahsil','perm'=>'stock','primary'=>false],
        ['key'=>'finance_new_in','label'=>'Tahsilat Al','url'=>'finance_new.php?direction=in','mobileUrl'=>'collection.php','group'=>'sat_tahsil','perm'=>'finance','primary'=>false],
        ['key'=>'finance_new_out','label'=>'Ödeme Yap','url'=>'finance_new.php?direction=out','mobileUrl'=>'payment.php','group'=>'sat_tahsil','perm'=>'finance','primary'=>false],
        ['key'=>'finance_transfer','label'=>'Hesaplar Arası Transfer Yap','url'=>'finance_transfer.php','mobileUrl'=>'transfer.php','group'=>'sat_tahsil','perm'=>'finance','primary'=>false],
        ['key'=>'checks_notes','label'=>'Çek / Senet Takip Et','url'=>'checks_notes.php','group'=>'sat_tahsil','perm'=>'finance','primary'=>false],

        // ── STOK YÖNET ───────────────────────────────────────────────────────
        ['key'=>'stock','label'=>'Stok Kontrol Et','url'=>'stock.php','group'=>'stok','perm'=>'stock','primary'=>false],
        ['key'=>'product_new','label'=>'Yeni Ürün Ekle','url'=>'product_new.php','group'=>'stok','perm'=>'stock','primary'=>false],
        ['key'=>'stock_movement_new','label'=>'Stok Giriş / Çıkış Yap','url'=>'stock_movement_new.php?type=in','group'=>'stok','perm'=>'stock','primary'=>false],
        ['key'=>'product_categories','label'=>'Ürün Kategorilerini Düzenle','url'=>'product_categories.php','group'=>'stok','perm'=>'stock','primary'=>false],
        ['key'=>'product_taxonomy','label'=>'Marka / Birim Düzenle','url'=>'product_taxonomy.php','group'=>'stok','perm'=>'stock','primary'=>false],

        // ── YÖNET (arka ofis / raporlama / sistem) ──────────────────────────
        ['key'=>'finance','label'=>'Kasa / Banka Panelini Gör','url'=>'finance.php','mobileUrl'=>'kasa.php','group'=>'yonet','perm'=>'finance','primary'=>false],
        ['key'=>'finance_accounts','label'=>'Banka / Kasa / Kart Hesaplarını Yönet','url'=>'finance_accounts.php','mobileHide'=>true,'group'=>'yonet','perm'=>'finance','primary'=>false],
        ['key'=>'accounting','label'=>'Muhasebe Kayıtlarını Gör','url'=>'accounting.php','group'=>'yonet','perm'=>'muhasebe','primary'=>false],
        ['key'=>'accounting_categories','label'=>'Muhasebe Kategorilerini Düzenle','url'=>'accounting_categories.php','group'=>'yonet','perm'=>'muhasebe','primary'=>false,'adminOnly'=>true],
        ['key'=>'personnel','label'=>'Personeli Yönet','url'=>'personnel.php','group'=>'yonet','perm'=>'personnel','primary'=>false],
        ['key'=>'personnel_new','label'=>'Yeni Personel Ekle','url'=>'personnel_new.php','group'=>'yonet','perm'=>'personnel','primary'=>false],
        ['key'=>'kpi','label'=>'Performans / KPI Gör','url'=>'kpi.php','group'=>'yonet','perm'=>'personnel','primary'=>false],
        ['key'=>'report','label'=>'Rapor Al','url'=>'report.php','group'=>'yonet','perm'=>'report','primary'=>false],
        ['key'=>'gunluk_rapor','label'=>'Günlük İş Raporu Al','url'=>'gunluk_rapor.php','group'=>'yonet','perm'=>'report','primary'=>false],
        ['key'=>'contacts_report','label'=>'Cari Ekstresi Al','url'=>'contacts_report.php','group'=>'yonet','perm'=>'contacts','primary'=>false],
        ['key'=>'activity','label'=>'Son İşlemleri Gör','url'=>'activity.php','group'=>'yonet','perm'=>null,'primary'=>false,'adminOnly'=>true],
        ['key'=>'users','label'=>'Kullanıcı & Yetki Yönet','url'=>'users.php','group'=>'yonet','perm'=>'users','primary'=>false],
        ['key'=>'audit_log','label'=>'Denetim Günlüğünü Gör','url'=>'audit_log.php','group'=>'yonet','perm'=>'users','primary'=>false],
        ['key'=>'wa_settings','label'=>'WhatsApp Ayarlarını Düzenle','url'=>'wa_settings.php','group'=>'yonet','perm'=>'users','primary'=>false],
        ['key'=>'brand_settings','label'=>'Logo / Marka Düzenle','url'=>'brand_settings.php','group'=>'yonet','perm'=>'users','primary'=>false],
        ['key'=>'temizle_veri','label'=>'Veri Temizle','url'=>'temizle_veri.php','group'=>'yonet','perm'=>null,'primary'=>false,'adminOnly'=>true],
        ['key'=>'profile','label'=>'Profilim / Şifre','url'=>'profile.php','group'=>'yonet','perm'=>null,'primary'=>false],
    ];
}

function nav_group_label($group){
    return [
        'is_takip'=>'İş Takip Et', 'sat_tahsil'=>'Sat & Tahsil Et', 'stok'=>'Stok Yönet',
        'iletisim'=>'İletişim & Talep', 'yonet'=>'Yönet',
    ][$group] ?? $group;
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
    if($savedMode === 'compact' || $savedMode === 'legacy') return $savedMode;
    return ($isAdmin || $isPilotUser) ? 'compact' : 'legacy';
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
