<?php require_once __DIR__.'/boot.php'; require_login();
if(file_exists(__DIR__.'/activity_lib.php')) require_once __DIR__.'/activity_lib.php';
if(file_exists(__DIR__.'/notifications_lib.php')) require_once __DIR__.'/notifications_lib.php';
$notifCount = 0;
$__me=(int)(current_user()['id'] ?? 0);
try { $notifCount = function_exists('notif_unread_count') ? notif_unread_count(db(),$__me) : 0; } catch(Throwable $e) {}
$cur = basename($_SERVER['SCRIPT_NAME']);
?>
<!doctype html>
<html lang="tr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?=htmlspecialchars(app_config()['app_name'] ?? 'OTS')?> — Online Takip Sistemi</title>
<?php
// Favicon — masaüstü web tarafında hiç <link rel="icon"> yoktu (mobil PWA'da vardı, bkz.
// mobile/common.php icon.php), tarayıcı sekmesinde marka görünmüyordu — kullanıcı bildirimi:
// "her iki site için de daha önce aktif faviconlar vardı, yeniden ayarla, tarayıcıda çıksın".
// brand_icon() zaten ACANS/PRIMAC'ın kendi app_settings'inden (ayrı DB) doğru ikonu döndürüyor.
$__favicon = brand_icon();
$__faviconExt = strtolower(pathinfo($__favicon, PATHINFO_EXTENSION));
$__faviconType = ['png'=>'image/png','jpg'=>'image/jpeg','jpeg'=>'image/jpeg','webp'=>'image/webp','gif'=>'image/gif'][$__faviconExt] ?? 'image/png';
?>
<link rel="icon" type="<?=$__faviconType?>" href="<?=h($__favicon)?>?v=<?=@filemtime(__DIR__.'/'.$__favicon)?>">
<link rel="apple-touch-icon" href="<?=h($__favicon)?>?v=<?=@filemtime(__DIR__.'/'.$__favicon)?>">
<style>
*{box-sizing:border-box}
html,body{max-width:100%;overflow-x:hidden}
body{margin:0;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Arial,sans-serif;background:#f5f7fb;color:#101828}
.app-shell{display:flex;min-height:100vh}
.sidebar{width:284px;background:#071326;color:#fff;position:fixed;top:0;bottom:0;left:0;overflow:auto;padding:0 0 18px;scrollbar-width:thin;scrollbar-color:rgba(255,255,255,.12) transparent}
.sidebar::-webkit-scrollbar{width:4px}.sidebar::-webkit-scrollbar-thumb{background:rgba(255,255,255,.12);border-radius:4px}

/* Marka alanı */
.brand{display:flex;gap:12px;align-items:center;padding:20px 18px 16px;margin:0;position:relative;border-bottom:1px solid rgba(255,255,255,.07)}
.brand::after{content:'';position:absolute;bottom:0;left:18px;right:18px;height:1px;background:linear-gradient(90deg,transparent,rgba(37,99,235,.6),transparent)}
.brand-mark{width:44px;height:44px;border-radius:15px;background:linear-gradient(135deg,#fff 60%,#dbeafe);color:#071326;display:flex;align-items:center;justify-content:center;font-weight:900;font-size:20px;box-shadow:0 4px 14px rgba(37,99,235,.35),0 0 0 2px rgba(255,255,255,.15)}
.brand-title{font-weight:900;font-size:18px;letter-spacing:.2px;line-height:1.2}
.brand-subtitle{color:#7fa8d0;font-size:11.5px;margin-top:3px;letter-spacing:.03em}

/* Çalışma Alanı bilgi etiketi (2026-07-04: eskiden işlevsiz "Aktif Şirket" dropdown'ıydı — sahte
   seçim kutusu kaldırıldı, tek gerçek değer bilgi metni olarak gösteriliyor. Gerçek çoklu-çalışma-
   alanı mimarisi ayrı bir proje, bkz. ROADMAP.md "Workspace (Multi-Tenant) Architecture") */
.workspace-box{background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.09);border-radius:14px;padding:11px 13px;margin:14px 12px 4px}
.workspace-box small{display:block;color:#7fa8d0;font-size:10.5px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;margin-bottom:6px}
.workspace-name{color:#e7eefc;font-weight:800;font-size:14px}

/* Navigasyon başlıkları */
.nav-title{font-size:10.5px;color:#4d6a8a;letter-spacing:.12em;font-weight:900;margin:16px 18px 6px;text-transform:uppercase}

/* Nav linkleri ve summary'ler */
.nav{padding:6px 10px}
.nav a,.nav details summary{display:flex;align-items:center;gap:10px;color:#c8d8f0;text-decoration:none;padding:9px 12px;border-radius:10px;margin:2px 0;font-size:14px;font-weight:700;cursor:pointer;list-style:none;transition:background .18s ease,color .18s ease,padding-left .18s ease;position:relative}
.nav details summary::-webkit-details-marker{display:none}

/* Hover */
.nav a:hover,.nav details summary:hover{background:rgba(255,255,255,.07);color:#fff}

/* Aktif sayfa vurgusu */
.nav a.active{background:rgba(37,99,235,.22);color:#fff;font-weight:800}
.nav a.active::before{content:'';position:absolute;left:0;top:20%;bottom:20%;width:3px;border-radius:0 3px 3px 0;background:#2563eb}

/* Açık grup summary */
.nav details[open]>summary{background:rgba(255,255,255,.06);color:#fff}

/* Alt menü */
.nav details .sub{margin:3px 0 6px 20px;border-left:2px solid rgba(255,255,255,.08);padding-left:6px}
.nav details .sub a{font-size:13.5px;padding:7px 10px;color:#8bafd4;font-weight:600;border-radius:8px}
.nav details .sub a:hover{background:rgba(255,255,255,.07);color:#fff}
.nav details .sub a.active{background:rgba(37,99,235,.22);color:#93c5fd;font-weight:700}
.nav details .sub a.active::before{content:'';position:absolute;left:0;top:20%;bottom:20%;width:3px;border-radius:0 3px 3px 0;background:#2563eb}

/* Footer */
.sidebar-footer{border-top:1px solid rgba(255,255,255,.07);margin:14px 10px 0;padding-top:10px}
.main{margin-left:284px;flex:1;padding:24px}
.topbar{height:44px;display:flex;justify-content:space-between;align-items:center;background:#fff;border-radius:18px;padding:0 16px;margin-bottom:18px;box-shadow:0 8px 28px rgba(16,24,40,.05)}
.search{display:flex;align-items:center;gap:8px;color:#667085;background:#f7f9fc;border-radius:999px;padding:9px 14px;min-width:280px}
.top-actions{display:flex;gap:10px;align-items:center}
.pill{background:#eef2f6;border-radius:999px;padding:8px 11px;color:#344054;font-weight:800;text-decoration:none;position:relative}
.pill.alert-pill{background:#fee2e2;color:#991b1b}
.top-user{color:#101828;font-weight:800}
h1{margin:10px 0 18px;font-size:30px}.muted{color:#667085;font-size:12px}
.cards{display:grid;grid-template-columns:repeat(4,minmax(150px,1fr));gap:14px;margin:14px 0 20px}
.card,.panel{background:#fff;border-radius:20px;box-shadow:0 8px 28px rgba(16,24,40,.06);padding:18px}
.card small{color:#667085;display:block}.card strong{display:block;font-size:25px;margin-top:8px}
.panel{margin-top:16px}.panel-head{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:12px}
.panel-head h2,.panel h2{margin:0}
.btn,button{border:0;border-radius:12px;background:#111827;color:#fff;text-decoration:none;padding:10px 14px;font-weight:800;cursor:pointer;display:inline-flex;align-items:center;justify-content:center}
.btn.secondary{background:#eef2f6;color:#101828}.btn.danger{background:#b42318}.btn.small{padding:7px 10px;font-size:12px}
.btn.ghost{background:transparent;color:#374151;border:1px solid #d0d5dd}.btn.ghost:hover{background:#f7f9fc}
table{width:100%;border-collapse:collapse}th,td{text-align:left;border-bottom:1px solid #eef2f6;padding:11px;vertical-align:top}
th{font-size:13px;color:#667085}.badge{display:inline-flex;border-radius:999px;padding:5px 9px;font-size:12px;font-weight:900}
.badge.gray{background:#f2f4f7;color:#344054}.badge.blue{background:#dbeafe;color:#1e40af}.badge.yellow{background:#fef3c7;color:#92400e}.badge.purple{background:#ede9fe;color:#5b21b6}.badge.orange{background:#ffedd5;color:#9a3412}.badge.teal{background:#ccfbf1;color:#115e59}.badge.green{background:#dcfce7;color:#166534}.badge.red{background:#fee2e2;color:#991b1b}
/* 2026-07-03: formlar sistem genelinde büyütüldü — kullanıcı isteği: "biraz daha büyük olsun,
   gözü küçük ya da büyük olarak yormasın". input/select/textarea 16px'ten büyük tarayıcı zoom
   tetiklemediği için mobilde de güvenli. */
input,select,textarea{font-size:16px}
.form-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:14px}.form-grid label{font-weight:800;color:#344054;font-size:15.5px;display:block;margin-bottom:2px}
.form-grid input,.form-grid select,.form-grid textarea,.inline select,.inline input{width:100%;border:1.5px solid #d0d5dd;border-radius:12px;padding:13px 14px;margin-top:6px;background:#fff;font-size:16px}
.form-grid .full{grid-column:1/-1}.inline{display:flex;gap:8px;align-items:center}
.filters{display:flex;gap:8px;flex-wrap:wrap;margin:10px 0 16px}.filters a{background:#fff;color:#101828;text-decoration:none;border-radius:999px;padding:9px 12px;font-weight:700}
.alert{background:#fee2e2;color:#991b1b;border-radius:14px;padding:12px;margin:10px 0}.ok{background:#dcfce7;color:#166534;border-radius:14px;padding:12px;margin:10px 0}
.notice-card{border:1px solid #eef2f6;border-radius:16px;padding:12px;margin:8px 0;background:#fff}
.notice-card.unread{background:#fff7ed;border-color:#fed7aa}
.danger-row{background:#fff7ed}.stage-list{display:grid;gap:8px}.stage{display:flex;justify-content:space-between;align-items:center;padding:12px;border:1px solid #eef2f6;border-radius:14px}
.actions{display:flex;gap:8px;flex-wrap:wrap}.nowrap{white-space:nowrap}
.menu-toggle{display:none;background:#0b1f3a;color:#fff;border:0;border-radius:12px;width:44px;height:44px;font-size:21px;cursor:pointer;flex:0 0 auto}
.nav-overlay{display:none}
@media(max-width:960px){
.sidebar{width:284px;height:auto;transform:translateX(-100%);transition:transform .25s ease;z-index:1000;box-shadow:2px 0 22px rgba(0,0,0,.35)}
.app-shell.nav-open .sidebar{transform:translateX(0)}
.app-shell{display:block}
.main{margin-left:0;padding:14px}
.cards,.form-grid{grid-template-columns:1fr}
.panel{overflow:auto}
.topbar{display:flex;align-items:center;height:auto;padding:10px;gap:8px}
.search{display:none}
.top-actions{flex:1;justify-content:flex-end;gap:6px;overflow-x:auto;-webkit-overflow-scrolling:touch}
.pill{padding:8px 10px;font-size:13px;white-space:nowrap;flex:0 0 auto}
.uname{display:none}
.menu-toggle{display:flex;align-items:center;justify-content:center}
.nav-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:999}
.app-shell.nav-open .nav-overlay{display:block}
}

.brand-link{text-decoration:none;color:#fff;display:flex;align-items:center;gap:12px}
.brand-logo{width:44px;height:44px;border-radius:15px;object-fit:contain;background:#fff;padding:4px;box-shadow:0 4px 14px rgba(37,99,235,.35),0 0 0 2px rgba(255,255,255,.15)}
.activity-list{display:flex;flex-direction:column;gap:10px}
.activity-item{display:flex;gap:12px;text-decoration:none;color:#101828;background:#f8fafc;border:1px solid #eef2f6;border-radius:16px;padding:12px}
.activity-item:hover{background:#eef2f6}
.activity-icon{width:42px;height:42px;border-radius:14px;background:#111827;color:#fff;display:flex;align-items:center;justify-content:center;font-size:20px;flex:0 0 auto}
.activity-body p{margin:4px 0;color:#475467}
.activity-body small{color:#667085;font-size:12px}

/* Büyük renkli navigasyon kartları (mobildeki gibi) — dashboard hızlı erişim */
.navtiles{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin:6px 0 22px}
.ntile{min-height:118px;border-radius:22px;padding:18px;color:#0f172a;text-decoration:none;display:flex;flex-direction:column;justify-content:space-between;box-shadow:0 8px 24px rgba(16,24,40,.06);transition:transform .12s ease,box-shadow .12s ease}
.ntile:hover{transform:translateY(-3px);box-shadow:0 16px 38px rgba(16,24,40,.14)}
.ntile .ic{font-size:30px;line-height:1}
.ntile b{font-size:17px;font-weight:800;margin-top:6px}
.ntile small{color:#475467;font-size:12.5px}
.ntile.blue{background:#dbeafe}.ntile.green{background:#dcfce7}.ntile.orange{background:#ffedd5}.ntile.purple{background:#ede9fe}.ntile.red{background:#fee2e2}.ntile.yellow{background:#fef3c7}.ntile.teal{background:#ccfbf1}.ntile.gray{background:#eef2f6}
@media(max-width:960px){.navtiles{grid-template-columns:1fr 1fr;gap:11px}.ntile{min-height:104px;border-radius:18px;padding:15px}}
</style>
</head>
<body>
<div class="app-shell">
<aside class="sidebar">
    <a class="brand brand-link" href="dashboard.php" title="Ana sayfa">
    <?php $__blogo=brand_logo(); if($__blogo && file_exists(__DIR__.'/'.$__blogo)): ?>
        <img class="brand-logo" src="<?=h($__blogo)?>" alt="Logo">
    <?php else: ?>
        <div class="brand-mark">A</div>
    <?php endif; ?>
    <div>
        <div class="brand-title"><?=h(app_config()['app_name'] ?? 'OTS')?></div>
        <div class="brand-subtitle">Online Takip ve Yönetim Sistemi</div>
    </div>
</a>

<div class="workspace-box">
        <small>Çalışma Alanı</small>
        <div class="workspace-name"><?=htmlspecialchars(app_config()['app_name'] ?? 'OTS')?></div>
    </div>

    <nav class="nav">
        <a href="dashboard.php" <?=($cur==='dashboard.php'?'class="active"':'')?>><span>🏛</span> Komuta Merkezi</a>
        <a href="takvim.php" <?=($cur==='takvim.php'?'class="active"':'')?>><span>📅</span> Takvim</a>
        <a href="notes.php" <?=($cur==='notes.php'?'class="active"':'')?>><span>📝</span> Notlarım</a>
        <a href="mytasks.php" <?=($cur==='mytasks.php'?'class="active"':'')?>><span>✅</span> İşlerim</a>

        <?php
        /* Taksonomi — 4 grup (2026-07-03: kullanıcı isteğiyle 6 gruptan sadeleştirildi).
           Raporlar artık ayrı bir grup değil, ilgili alanın içine gömülü (report.php?modul=
           parametreleri DEĞİŞMEDİ, sadece hangi menüden erişildiği değişti).
           2026-07-03 (2. tur): "Mesajlar" hem üstte tek link hem "Mesajlaşma ve Raporlama"
           grubunun içinde (Bildirimler/WhatsApp ile) iki ayrı yerde duruyordu — kullanıcı
           bildirimi: "mesajlaşmaları tek yere al, raporlama ayrı kalsın". Artık üstteki tek
           link bir ağaca (İç Mesajlar + Bildirimler + WhatsApp Gönder) dönüştü, Raporlama
           kendi başına ayrı bir grup. */
        $personelIsTakip_pages = ['jobs.php','job_new.php','takvim.php','tasks.php','approval_waiting.php','external.php','production.php','assembly.php','design.php','work_center.php','requests.php','personnel.php','personnel_new.php','kpi.php','gunluk_rapor.php'];
        $muhasebe_pages = ['contacts.php','contact_new.php','contacts_report.php','teklif.php','sales.php','purchase.php','stock.php','product_new.php','stock_movement_new.php','product_categories.php','product_taxonomy.php','finance.php','finance_accounts.php','finance_new.php','finance_transfer.php','checks_notes.php','check_note_view.php','accounting.php','accounting_categories.php','trade_documents.php','trade_document_new.php','trade_document_view.php'];
        $mesajlar_pages = ['messages.php','notifications.php','wa_send_now.php'];
        $rapor_pages = ['activity.php','report.php'];
        $sistem_pages = ['users.php','audit_log.php','wa_settings.php','brand_settings.php','profile.php','request_new.php','temizle_veri.php','logout.php'];
        ?>

        <details <?=(in_array($cur,$mesajlar_pages)?'open':'')?>><summary><span>💬</span> Mesajlar</summary>
            <div class="sub">
                <a href="messages.php" <?=($cur==='messages.php'?'class="active"':'')?>><span>💬</span> İç Mesajlar</a>
                <a href="notifications.php" <?=($cur==='notifications.php'?'class="active"':'')?>><span>🔔</span> Bildirimler</a>
                <?php if(user_can('users')): ?>
                <a href="wa_send_now.php" <?=($cur==='wa_send_now.php'?'class="active"':'')?>><span>📤</span> WhatsApp Mesaj Gönder</a>
                <?php endif; ?>
            </div>
        </details>

        <?php if(user_can('jobs')||user_can('tasks')||user_can('personnel')||user_can('users')): ?>
        <details <?=(in_array($cur,$personelIsTakip_pages)?'open':'')?>><summary><span>🧭</span> İş / Üretim Yönetimi</summary>
            <div class="sub">
                <?php if(user_can('jobs')): ?>
                <a href="jobs.php" <?=($cur==='jobs.php'?'class="active"':'')?>><span>📁</span> İş Merkezi</a>
                <a href="job_new.php" <?=($cur==='job_new.php'?'class="active"':'')?>><span>➕</span> Yeni İş</a>
                <a href="takvim.php" <?=($cur==='takvim.php'?'class="active"':'')?>><span>📅</span> Takvim</a>
                <a href="approval_waiting.php" <?=($cur==='approval_waiting.php'?'class="active"':'')?>><span>🔍</span> Müşteri Onayları</a>
                <a href="external.php" <?=($cur==='external.php'?'class="active"':'')?>><span>🔧</span> Dış Tedarik / Atölye</a>
                <a href="production.php" <?=($cur==='production.php'?'class="active"':'')?>><span>🏭</span> Üretim</a>
                <a href="assembly.php" <?=($cur==='assembly.php'?'class="active"':'')?>><span>🔩</span> Montaj</a>
                <a href="design.php" <?=($cur==='design.php'?'class="active"':'')?>><span>🎨</span> Grafik Tasarım</a>
                <a href="work_center.php" <?=($cur==='work_center.php'?'class="active"':'')?>><span>⚙️</span> İş Motoru</a>
                <?php endif; ?>
                <?php if(user_can('tasks')): ?>
                <a href="tasks.php" <?=($cur==='tasks.php'?'class="active"':'')?>><span>✅</span> Görevler</a>
                <?php endif; ?>
                <?php if(user_can('personnel')||user_can('users')): ?>
                <a href="requests.php" <?=($cur==='requests.php'?'class="active"':'')?>><span>📨</span> Talepler / Onaylar</a>
                <?php endif; ?>
                <?php if(user_can('personnel')): ?>
                <a href="personnel.php" <?=($cur==='personnel.php'?'class="active"':'')?>><span>👤</span> Personeller</a>
                <a href="personnel_new.php" <?=($cur==='personnel_new.php'?'class="active"':'')?>><span>➕</span> Yeni Personel</a>
                <a href="kpi.php" <?=($cur==='kpi.php'?'class="active"':'')?>><span>📈</span> KPI</a>
                <?php endif; ?>
                <?php if(user_can('report')): ?>
                <a href="gunluk_rapor.php" <?=($cur==='gunluk_rapor.php'?'class="active"':'')?>><span>📅</span> Günlük İş Raporu</a>
                <a href="report.php?modul=is"><span>📊</span> İş Takip Raporu</a>
                <a href="report.php?modul=gorevler"><span>📊</span> Görevler Raporu</a>
                <a href="report.php?modul=personel"><span>📊</span> Personel Performansı</a>
                <?php endif; ?>
            </div>
        </details>
        <?php endif; ?>

        <?php if(user_can('contacts')||user_can('teklif')||user_can('stock')||user_can('finance')||user_can('muhasebe')): ?>
        <details <?=(in_array($cur,$muhasebe_pages)?'open':'')?>><summary><span>💰</span> Muhasebe İşlemleri</summary>
            <div class="sub">
                <?php if(user_can('contacts')): ?>
                <a href="contacts.php" <?=($cur==='contacts.php' && empty($_GET['type'])?'class="active"':'')?>><span>📋</span> Tüm Cariler</a>
                <a href="contacts.php?type=Müşteri" <?=($cur==='contacts.php' && ($_GET['type']??'')==='Müşteri'?'class="active"':'')?>><span>👤</span> Müşteri</a>
                <a href="contacts.php?type=Tedarikçi" <?=($cur==='contacts.php' && ($_GET['type']??'')==='Tedarikçi'?'class="active"':'')?>><span>🚚</span> Tedarikçi</a>
                <a href="trade_documents.php" <?=($cur==='trade_documents.php'?'class="active"':'')?>><span>🧾</span> Alış / Satış Belgeleri</a>
                <?php endif; ?>
                <?php if(user_can('teklif')): ?>
                <a href="teklif.php" <?=($cur==='teklif.php'?'class="active"':'')?>><span>📄</span> Tüm Teklifler</a>
                <a href="teklif.php?new=1"><span>➕</span> Yeni Teklif</a>
                <?php endif; ?>
                <?php if(user_can('stock')): ?>
                <a href="stock.php" <?=($cur==='stock.php'?'class="active"':'')?>><span>📦</span> Ürün / Stok Listesi</a>
                <a href="product_new.php" <?=($cur==='product_new.php'?'class="active"':'')?>><span>➕</span> Yeni Ürün</a>
                <a href="stock_movement_new.php?type=in" <?=($cur==='stock_movement_new.php'?'class="active"':'')?>><span>⬆</span> Stok Giriş</a>
                <a href="stock_movement_new.php?type=out"><span>⬇</span> Stok Çıkış</a>
                <a href="product_categories.php" <?=($cur==='product_categories.php'?'class="active"':'')?>><span>🏷</span> Kategoriler</a>
                <a href="product_taxonomy.php" <?=($cur==='product_taxonomy.php'?'class="active"':'')?>><span>🔖</span> Marka / Birim</a>
                <a href="sales.php" <?=($cur==='sales.php'?'class="active"':'')?>><span>🧾</span> Satış</a>
                <a href="purchase.php" <?=($cur==='purchase.php'?'class="active"':'')?>><span>🛒</span> Satın Alma İşleri</a>
                <?php endif; ?>
                <?php if(user_can('finance')): ?>
                <a href="finance.php" <?=($cur==='finance.php'?'class="active"':'')?>><span>📊</span> Finans Paneli</a>
                <a href="finance_accounts.php" <?=($cur==='finance_accounts.php'?'class="active"':'')?>><span>🏦</span> Banka / Kasa / Kart / POS</a>
                <a href="finance_new.php?direction=in" <?=($cur==='finance_new.php'?'class="active"':'')?>><span>➕</span> Tahsilat</a>
                <a href="finance_new.php?direction=out"><span>➖</span> Ödeme</a>
                <a href="finance_transfer.php" <?=($cur==='finance_transfer.php'?'class="active"':'')?>><span>↔</span> Hesap Transferi</a>
                <a href="checks_notes.php" <?=($cur==='checks_notes.php'||$cur==='check_note_view.php'?'class="active"':'')?>><span>🧾</span> Çek / Senet</a>
                <?php endif; ?>
                <?php if(user_can('muhasebe')): ?>
                <a href="accounting.php" <?=($cur==='accounting.php'?'class="active"':'')?>><span>📒</span> Muhasebe Kayıtları</a>
                <a href="accounting.php?tab=yeni"><span>➕</span> Yeni Kayıt</a>
                <a href="accounting.php?tab=personel"><span>👷</span> Personel Ödemeleri</a>
                <a href="accounting.php?tab=ozet"><span>📊</span> Muhasebe Özeti</a>
                <?php if(is_admin()): ?><a href="accounting_categories.php" <?=($cur==='accounting_categories.php'?'class="active"':'')?>><span>⚙</span> Muhasebe Kategorileri</a><?php endif; ?>
                <?php endif; ?>
                <?php if(user_can('report')): ?>
                <a href="report.php?modul=tahsilat"><span>📊</span> Finans / Tahsilat Raporu</a>
                <a href="report.php?modul=muhasebe"><span>📊</span> Muhasebe Raporu</a>
                <a href="report.php?modul=cari"><span>📊</span> Cari Bakiye Raporu</a>
                <a href="contacts_report.php" <?=($cur==='contacts_report.php'?'class="active"':'')?>><span>📊</span> Cari Raporu / Toplu Ekstre</a>
                <a href="report.php?modul=satis"><span>📊</span> Satış Raporu</a>
                <a href="report.php?modul=satinalma"><span>📊</span> Satın Alma Raporu</a>
                <a href="report.php?modul=teklif"><span>📊</span> Teklif Raporu</a>
                <a href="report.php?modul=stok"><span>📊</span> Stok Raporu</a>
                <?php endif; ?>
            </div>
        </details>
        <?php endif; ?>

        <details <?=(in_array($cur,$rapor_pages)?'open':'')?>><summary><span>📊</span> Raporlama</summary>
            <div class="sub">
                <a href="activity.php" <?=($cur==='activity.php'?'class="active"':'')?>><span>📜</span> Son İşlemler</a>
                <?php if(user_can('report')): ?>
                <a href="report.php" <?=($cur==='report.php'?'class="active"':'')?>><span>📊</span> Genel Özet Rapor</a>
                <a href="report.php?modul=tumu"><span>🗂️</span> Tüm Modüller (Detaylı)</a>
                <?php endif; ?>
            </div>
        </details>

        <details <?=(in_array($cur,$sistem_pages)?'open':'')?>><summary><span>🕘</span> Genel Sistem Yönetimi</summary>
            <div class="sub">
                <a href="profile.php" <?=($cur==='profile.php'?'class="active"':'')?>><span>👤</span> Profilim / Şifre</a>
                <a href="request_new.php" <?=($cur==='request_new.php'?'class="active"':'')?>><span>📨</span> Talep Oluştur</a>
                <?php if(user_can('users')): ?>
                <a href="users.php" <?=($cur==='users.php'?'class="active"':'')?>><span>👥</span> Kullanıcılar & Yetkiler</a>
                <a href="audit_log.php" <?=($cur==='audit_log.php'?'class="active"':'')?>><span>🔍</span> Denetim Günlüğü</a>
                <a href="wa_settings.php" <?=($cur==='wa_settings.php'?'class="active"':'')?>><span>📱</span> WhatsApp Ayarları</a>
                <a href="brand_settings.php" <?=($cur==='brand_settings.php'?'class="active"':'')?>><span>🎨</span> Logo / Marka</a>
                <a href="temizle_veri.php" <?=($cur==='temizle_veri.php'?'class="active"':'')?> style="color:#fca5a5"><span>🧹</span> Veri Temizleme (canlıya hazırlık)</a>
                <?php endif; ?>
                <a href="logout.php"><span>🚪</span> Çıkış</a>
            </div>
        </details>

        <div class="sidebar-footer">
            <a href="logout.php"><span>🚪</span> Çıkış</a>
        </div>
    </nav>
</aside>
<div class="nav-overlay" onclick="document.querySelector('.app-shell').classList.remove('nav-open')"></div>

<main class="main">
<header class="topbar">
    <button class="menu-toggle" onclick="document.querySelector('.app-shell').classList.toggle('nav-open')" aria-label="Menü">☰</button>
    <form class="search" method="get" action="search.php" role="search">
        <span style="font-size:15px;flex:0 0 auto">🔍</span>
        <input name="q" placeholder="İş, müşteri, banka/kart, işlem, çek/senet, teklif, stok, personel ara…"
            style="border:0;background:transparent;outline:none;flex:1;font-size:14px;color:#344054;min-width:0"
            autocomplete="off" value="<?=htmlspecialchars($_GET['q'] ?? '')?>">
    </form>
    <div class="top-actions">
        <a class="pill <?=$notifCount?'alert-pill':''?>" href="notifications.php">🔔 <?=$notifCount?></a>
        <a class="pill" href="request_new.php">📨 Talep</a>
        <a class="pill" href="job_new.php">+ İş</a>
        <a class="pill" href="profile.php">👤 <span class="uname"><?=h($_SESSION['user']['name'] ?? 'Kullanıcı')?></span></a>
    </div>
</header>
