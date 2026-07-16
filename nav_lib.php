<?php
/* NAV-001B (2026-07-16) — Navigasyon bilgi mimarisi: TEK kaynak. Web sidebar (layout_top.php),
 * Web Module Launcher, mobile/more.php ve mobile/common.php::botx() hepsi buradan beslenir.
 * ds_lib.php SADECE görsel bileşen kütüphanesidir (Product Owner kararı) — bilgi mimarisi
 * kasıtlı olarak ayrı bir dosyada tutuluyor.
 *
 * Bir satır eklemek/taşımak = SADECE nav_taxonomy()'yi değiştirmek. Grup/sıra/yetki tek yerde.
 */

// group: çalışma|ticaret|finans|yönetim  ·  perm: null (herkese açık) veya module_list() anahtarı
// primary: web compact sidebar'da (Sabitlenenler dışında) her zaman görünen 7 Çalışma satırı
// adminOnly: perm yetkisi yetmez, ayrıca is_admin() da gerekir (bugünkü mobil/more.php davranışı)
function nav_taxonomy(){
    return [
        // ── ÇALIŞMA ──────────────────────────────────────────────────────────
        ['key'=>'dashboard','label'=>'Komuta Merkezi','url'=>'dashboard.php','group'=>'çalışma','perm'=>null,'primary'=>true],
        ['key'=>'mytasks','label'=>'Görevlerim','url'=>'mytasks.php','group'=>'çalışma','perm'=>null,'primary'=>true],
        ['key'=>'jobs','label'=>'İş / Üretim','url'=>'jobs.php','group'=>'çalışma','perm'=>'jobs','primary'=>true],
        ['key'=>'job_new','label'=>'Yeni İş','url'=>'job_new.php','group'=>'çalışma','perm'=>'jobs','primary'=>false],
        ['key'=>'production','label'=>'Üretim','url'=>'production.php','group'=>'çalışma','perm'=>'jobs','primary'=>false],
        ['key'=>'assembly','label'=>'Montaj','url'=>'assembly.php','group'=>'çalışma','perm'=>'jobs','primary'=>false],
        ['key'=>'external','label'=>'Dış Tedarik / Atölye','url'=>'external.php','group'=>'çalışma','perm'=>'jobs','primary'=>false],
        ['key'=>'design','label'=>'Grafik Tasarım','url'=>'design.php','group'=>'çalışma','perm'=>'jobs','primary'=>false],
        ['key'=>'work_center','label'=>'İş Motoru','url'=>'work_center.php','group'=>'çalışma','perm'=>'jobs','primary'=>false],
        ['key'=>'approval_waiting','label'=>'Müşteri Onayları','url'=>'approval_waiting.php','group'=>'çalışma','perm'=>'jobs','primary'=>false],
        ['key'=>'tasks','label'=>'Tüm Görevler','url'=>'tasks.php','group'=>'çalışma','perm'=>'tasks','primary'=>false],
        ['key'=>'takvim','label'=>'Takvim','url'=>'takvim.php','group'=>'çalışma','perm'=>'jobs','primary'=>true],
        ['key'=>'messages','label'=>'Mesajlar','url'=>'messages.php','group'=>'çalışma','perm'=>null,'primary'=>true],
        ['key'=>'notifications','label'=>'Bildirimler','url'=>'notifications.php','group'=>'çalışma','perm'=>null,'primary'=>true],
        ['key'=>'notes','label'=>'Notlarım','url'=>'notes.php','group'=>'çalışma','perm'=>null,'primary'=>true],
        ['key'=>'requests','label'=>'Talepler','url'=>'requests.php','group'=>'çalışma','perm'=>null,'primary'=>false,'adminOnly'=>true],
        ['key'=>'request_new','label'=>'Talep Oluştur','url'=>'request_new.php','group'=>'çalışma','perm'=>null,'primary'=>false],
        ['key'=>'wa_conversations','label'=>'WhatsApp Konuşmaları','url'=>'wa_conversations.php','group'=>'çalışma','perm'=>'users','primary'=>false],
        ['key'=>'wa_send_now','label'=>'WhatsApp Toplu Gönderim','url'=>'wa_send_now.php','group'=>'çalışma','perm'=>'users','primary'=>false],

        // ── TİCARET ──────────────────────────────────────────────────────────
        ['key'=>'contacts','label'=>'Cariler','url'=>'contacts.php','group'=>'ticaret','perm'=>'contacts','primary'=>false],
        ['key'=>'contact_new','label'=>'Yeni Cari','url'=>'contact_new.php','group'=>'ticaret','perm'=>'contacts','primary'=>false],
        ['key'=>'trade_documents','label'=>'Alış / Satış Belgeleri','url'=>'trade_documents.php','group'=>'ticaret','perm'=>'contacts','primary'=>false],
        ['key'=>'teklif','label'=>'Teklifler','url'=>'teklif.php','group'=>'ticaret','perm'=>'teklif','primary'=>false],
        ['key'=>'stock','label'=>'Ürün / Stok Listesi','url'=>'stock.php','group'=>'ticaret','perm'=>'stock','primary'=>false],
        ['key'=>'product_new','label'=>'Yeni Ürün','url'=>'product_new.php','group'=>'ticaret','perm'=>'stock','primary'=>false],
        ['key'=>'sales','label'=>'Satış','url'=>'sales.php','group'=>'ticaret','perm'=>'stock','primary'=>false],
        ['key'=>'purchase','label'=>'Satın Alma','url'=>'purchase.php','group'=>'ticaret','perm'=>'stock','primary'=>false],
        ['key'=>'stock_movement_new','label'=>'Stok Giriş / Çıkış','url'=>'stock_movement_new.php?type=in','group'=>'ticaret','perm'=>'stock','primary'=>false],
        ['key'=>'product_categories','label'=>'Ürün Kategorileri','url'=>'product_categories.php','group'=>'ticaret','perm'=>'stock','primary'=>false],
        ['key'=>'product_taxonomy','label'=>'Marka / Birim','url'=>'product_taxonomy.php','group'=>'ticaret','perm'=>'stock','primary'=>false],

        // ── FİNANS ───────────────────────────────────────────────────────────
        ['key'=>'finance','label'=>'Finans Paneli','url'=>'finance.php','group'=>'finans','perm'=>'finance','primary'=>false],
        ['key'=>'finance_accounts','label'=>'Banka / Kasa / Kart / POS','url'=>'finance_accounts.php','group'=>'finans','perm'=>'finance','primary'=>false],
        ['key'=>'finance_new_in','label'=>'Tahsilat','url'=>'finance_new.php?direction=in','group'=>'finans','perm'=>'finance','primary'=>false],
        ['key'=>'finance_new_out','label'=>'Ödeme','url'=>'finance_new.php?direction=out','group'=>'finans','perm'=>'finance','primary'=>false],
        ['key'=>'finance_transfer','label'=>'Hesap Transferi','url'=>'finance_transfer.php','group'=>'finans','perm'=>'finance','primary'=>false],
        ['key'=>'checks_notes','label'=>'Çek / Senet','url'=>'checks_notes.php','group'=>'finans','perm'=>'finance','primary'=>false],
        ['key'=>'accounting','label'=>'Muhasebe','url'=>'accounting.php','group'=>'finans','perm'=>'muhasebe','primary'=>false],
        ['key'=>'accounting_categories','label'=>'Muhasebe Kategorileri','url'=>'accounting_categories.php','group'=>'finans','perm'=>'muhasebe','primary'=>false,'adminOnly'=>true],

        // ── YÖNETİM (Raporlama dahil — 4 grup dışına çıkılmadı) ─────────────
        ['key'=>'personnel','label'=>'Personel','url'=>'personnel.php','group'=>'yönetim','perm'=>'personnel','primary'=>false],
        ['key'=>'personnel_new','label'=>'Yeni Personel','url'=>'personnel_new.php','group'=>'yönetim','perm'=>'personnel','primary'=>false],
        ['key'=>'kpi','label'=>'Performans / KPI','url'=>'kpi.php','group'=>'yönetim','perm'=>'personnel','primary'=>false],
        ['key'=>'report','label'=>'Raporlar','url'=>'report.php','group'=>'yönetim','perm'=>'report','primary'=>false],
        ['key'=>'gunluk_rapor','label'=>'Günlük İş Raporu','url'=>'gunluk_rapor.php','group'=>'yönetim','perm'=>'report','primary'=>false],
        ['key'=>'contacts_report','label'=>'Cari Raporu / Ekstre','url'=>'contacts_report.php','group'=>'yönetim','perm'=>'contacts','primary'=>false],
        ['key'=>'activity','label'=>'Son İşlemler','url'=>'activity.php','group'=>'yönetim','perm'=>null,'primary'=>false,'adminOnly'=>true],
        ['key'=>'users','label'=>'Kullanıcılar & Yetkiler','url'=>'users.php','group'=>'yönetim','perm'=>'users','primary'=>false],
        ['key'=>'audit_log','label'=>'Denetim Günlüğü','url'=>'audit_log.php','group'=>'yönetim','perm'=>'users','primary'=>false],
        ['key'=>'wa_settings','label'=>'WhatsApp Ayarları','url'=>'wa_settings.php','group'=>'yönetim','perm'=>'users','primary'=>false],
        ['key'=>'brand_settings','label'=>'Logo / Marka','url'=>'brand_settings.php','group'=>'yönetim','perm'=>'users','primary'=>false],
        ['key'=>'temizle_veri','label'=>'Veri Temizleme','url'=>'temizle_veri.php','group'=>'yönetim','perm'=>null,'primary'=>false,'adminOnly'=>true],
        ['key'=>'profile','label'=>'Profilim / Şifre','url'=>'profile.php','group'=>'yönetim','perm'=>null,'primary'=>false],
    ];
}

function nav_group_label($group){
    return ['çalışma'=>'Çalışma','ticaret'=>'Ticaret','finans'=>'Finans','yönetim'=>'Yönetim'][$group] ?? $group;
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

// Yetkili + primary=true olan satırlar (compact sidebar'ın sabit Çalışma listesi)
function nav_primary_modules($canSee, $isAdmin){
    $out = [];
    foreach(nav_authorized_modules($canSee, $isAdmin) as $item){
        if(!empty($item['primary'])) $out[] = $item;
    }
    return $out;
}

// Yetkili + pin edilmiş (ve primary OLMAYAN — primary zaten hep görünür) satırlar, kayıtlı sırayla
function nav_pinned_modules($canSee, $isAdmin, $pinnedKeysCsv){
    $keys = array_filter(array_map('trim', explode(',', (string)$pinnedKeysCsv)));
    $authorized = nav_authorized_modules($canSee, $isAdmin);
    $byKey = [];
    foreach($authorized as $item) $byKey[$item['key']] = $item;
    $out = [];
    foreach($keys as $k){
        if(isset($byKey[$k]) && empty($byKey[$k]['primary'])) $out[] = $byKey[$k];
    }
    return $out;
}

// Launcher'da gruplanmış tam liste (primary olsun olmasın hepsi — kullanıcı her şeyi buradan görür/pinler)
function nav_grouped_for_launcher($canSee, $isAdmin){
    $out = ['çalışma'=>[], 'ticaret'=>[], 'finans'=>[], 'yönetim'=>[]];
    foreach(nav_authorized_modules($canSee, $isAdmin) as $item){
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
