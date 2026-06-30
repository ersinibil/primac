<?php require_once __DIR__.'/boot.php'; require_login();
if(file_exists(__DIR__.'/activity_lib.php')) require_once __DIR__.'/activity_lib.php'; 
$notifCount = 0;
try { $notifCount = safe_count("SELECT COUNT(*) c FROM notifications WHERE is_read=0"); } catch(Throwable $e) {}
?>
<!doctype html>
<html lang="tr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>ACANS OTS — Özel Proje</title>
<style>
*{box-sizing:border-box}
html,body{max-width:100%;overflow-x:hidden}
body{margin:0;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Arial,sans-serif;background:#f5f7fb;color:#101828}
.app-shell{display:flex;min-height:100vh}
.sidebar{width:284px;background:#071326;color:#fff;position:fixed;top:0;bottom:0;left:0;overflow:auto;padding:18px 14px}
.brand{display:flex;gap:12px;align-items:center;margin:4px 6px 18px}
.brand-mark{width:44px;height:44px;border-radius:15px;background:#fff;color:#071326;display:flex;align-items:center;justify-content:center;font-weight:900;font-size:20px}
.brand-title{font-weight:900;font-size:18px;letter-spacing:.2px}
.brand-subtitle{color:#9fb0c7;font-size:12px;margin-top:3px}
.company-box{background:#0f2138;border:1px solid rgba(255,255,255,.08);border-radius:16px;padding:12px;margin:0 4px 16px}
.company-box small{display:block;color:#9fb0c7;font-size:11px;margin-bottom:4px}
.company-select{width:100%;background:#071326;color:white;border:1px solid rgba(255,255,255,.15);border-radius:12px;padding:10px;font-weight:800}
.nav-title{font-size:11px;color:#7f95b2;letter-spacing:.08em;font-weight:900;margin:18px 8px 8px;text-transform:uppercase}
.nav a,.nav details summary{display:flex;align-items:center;gap:9px;color:#e7eefc;text-decoration:none;padding:10px 12px;border-radius:12px;margin:3px 0;font-weight:700;cursor:pointer;list-style:none}
.nav details summary::-webkit-details-marker{display:none}
.nav a:hover,.nav details summary:hover{background:#14243d}
.nav details[open] summary{background:#12233a}
.nav details .sub{margin:4px 0 8px 18px;border-left:1px solid rgba(255,255,255,.1);padding-left:8px}
.nav details .sub a{font-size:14px;padding:8px 10px;color:#cbd8ea}
.sidebar-footer{border-top:1px solid rgba(255,255,255,.08);margin:18px 4px 0;padding-top:14px}
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
table{width:100%;border-collapse:collapse}th,td{text-align:left;border-bottom:1px solid #eef2f6;padding:11px;vertical-align:top}
th{font-size:13px;color:#667085}.badge{display:inline-flex;border-radius:999px;padding:5px 9px;font-size:12px;font-weight:900}
.badge.gray{background:#f2f4f7;color:#344054}.badge.blue{background:#dbeafe;color:#1e40af}.badge.yellow{background:#fef3c7;color:#92400e}.badge.purple{background:#ede9fe;color:#5b21b6}.badge.orange{background:#ffedd5;color:#9a3412}.badge.teal{background:#ccfbf1;color:#115e59}.badge.green{background:#dcfce7;color:#166534}.badge.red{background:#fee2e2;color:#991b1b}
.form-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:14px}.form-grid label{font-weight:800;color:#344054}
.form-grid input,.form-grid select,.form-grid textarea,.inline select,.inline input{width:100%;border:1px solid #d0d5dd;border-radius:12px;padding:11px;margin-top:6px;background:#fff}
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

.brand-link{text-decoration:none;color:#fff}
.brand-logo{width:44px;height:44px;border-radius:15px;object-fit:contain;background:#fff;padding:4px}
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
    <?php if(file_exists(__DIR__.'/logo.png')): ?>
        <img class="brand-logo" src="logo.png" alt="ACANS" style="background:#fff;border-radius:10px;padding:3px">
    <?php elseif(file_exists(__DIR__.'/uploads/company_logo.png')): ?>
        <img class="brand-logo" src="uploads/company_logo.png" alt="Logo">
    <?php else: ?>
        <div class="brand-mark">A</div>
    <?php endif; ?>
    <div>
        <div class="brand-title">ACANS ERP</div>
        <div class="brand-subtitle">İşletim Sistemi</div>
    </div>
</a>

<div class="company-box">
        <small>Şirket</small>
        <select class="company-select">
            <option>PRIMAC</option>
            <option>ACANS</option>
            <option>MEDYAROTA</option>
            <option>DİJİMED</option>
        </select>
    </div>

    <nav class="nav">
        <a href="dashboard.php">🏛 Komuta Merkezi</a>
        <a href="messages.php">💬 Mesajlar</a>

        <?php if(user_can('jobs')): ?>
        <details open>
            <summary>📋 İşler</summary>
            <div class="sub">
                <a href="jobs.php">İş Merkezi</a>
                <a href="job_new.php">+ Yeni İş</a>
                <a href="takvim.php">📅 Takvim</a>
                <a href="tasks.php">Görevler</a>
                <a href="approval_waiting.php">Müşteri Onayları</a>
                <a href="external.php">Dış Tedarik / Atölye</a>
                <a href="production.php">Üretim</a>
                <a href="assembly.php">Montaj</a>
            </div>
        </details>
        <?php endif; ?>

        <?php if(user_can('personnel')): ?>
        <details>
            <summary>👥 Personel & Görevler</summary>
            <div class="sub">
                <a href="personnel.php">Personeller</a>
                <a href="personnel_new.php">+ Yeni Personel</a>
                <a href="tasks.php">Görevler</a>
                <a href="requests.php">Personel Talepleri</a>
            </div>
        </details>
        <?php endif; ?>

        <?php if(user_can('contacts')): ?>
        <details>
            <summary>👥 Cari Hesaplar</summary>
            <div class="sub">
                <a href="contacts.php">Tüm Cariler</a>
                <a href="contact_new.php?type=Müşteri">+ Müşteri</a>
                <a href="contact_new.php?type=Tedarikçi">+ Tedarikçi</a>
                <a href="external.php">Dış Atölyeler</a>
            </div>
        </details>
        <?php endif; ?>

        <?php if(user_can('teklif')): ?>
        <details>
            <summary>📄 Teklifler</summary>
            <div class="sub">
                <a href="teklif.php">Tüm Teklifler</a>
                <a href="teklif.php?new=1">+ Yeni Teklif</a>
            </div>
        </details>
        <?php endif; ?>

        <?php if(user_can('finance')): ?>
        <details>
            <summary>💰 Finans</summary>
            <div class="sub">
                <a href="finance.php">Finans Paneli</a>
                <a href="finance_accounts.php">Banka / Kasa / Kart / POS</a>
                <a href="finance_new.php?direction=in">+ Tahsilat</a>
                <a href="finance_new.php?direction=out">+ Ödeme</a>
                <a href="finance_transfer.php">Hesap Transferi</a>
            </div>
        </details>
        <?php endif; ?>

        <?php if(user_can('report')): ?>
        <a href="report.php">📊 Raporlar</a>
        <?php endif; ?>

        <?php if(user_can('stock')): ?>
        <details>
            <summary>📦 Ürün / Stok</summary>
            <div class="sub">
                <a href="stock.php">Ürün / Stok Listesi</a>
                <a href="product_new.php">+ Yeni Ürün</a>
                <a href="stock_movement_new.php?type=in">+ Stok Giriş</a>
                <a href="stock_movement_new.php?type=out">+ Stok Çıkış</a>
                <a href="product_categories.php">Kategoriler</a>
                <a href="product_taxonomy.php">Marka / Birim</a>
                <a href="purchase.php">Satın Alma İşleri</a>
            </div>
        </details>
        <?php endif; ?>

        <div class="nav-title">Sistem</div>
        <details>
            <summary>🕘 İzleme</summary>
            <div class="sub">
                <a href="activity.php">Son İşlemler</a>
                <a href="notifications.php">Bildirimler</a>
            </div>
        </details>

        <?php if(user_can('users')): ?>
        <details>
            <summary>⚙ Yönetim</summary>
            <div class="sub">
                <a href="dashboard.php">Komuta Merkezi</a>
                <a href="requests.php">Onay Bekleyenler</a>
                <a href="profile.php">Profilim / Şifre</a>
                <a href="users.php">Kullanıcılar & Yetkiler</a>
                <a href="temizle_veri.php" style="color:#fca5a5">🧹 Veri Temizleme (canlıya hazırlık)</a>
                <a href="logout.php">Çıkış</a>
            </div>
        </details>
        <?php endif; ?>
        <?php if(!user_can('users')): ?>
        <details>
            <summary>⚙ Hesabım</summary>
            <div class="sub">
                <a href="profile.php">Profilim / Şifre</a>
                <a href="request_new.php">Talep Oluştur</a>
                <a href="logout.php">Çıkış</a>
            </div>
        </details>
        <?php endif; ?>

        <div class="sidebar-footer">
            <a href="logout.php">🚪 Çıkış</a>
        </div>
    </nav>
</aside>
<div class="nav-overlay" onclick="document.querySelector('.app-shell').classList.remove('nav-open')"></div>

<main class="main">
<header class="topbar">
    <button class="menu-toggle" onclick="document.querySelector('.app-shell').classList.toggle('nav-open')" aria-label="Menü">☰</button>
    <div class="search">🔍 İş, müşteri, stok, personel ara...</div>
    <div class="top-actions">
        <a class="pill <?=$notifCount?'alert-pill':''?>" href="notifications.php">🔔 <?=$notifCount?></a>
        <a class="pill" href="request_new.php">📨 Talep</a>
        <a class="pill" href="job_new.php">+ İş</a>
        <a class="pill" href="profile.php">👤 <span class="uname"><?=h($_SESSION['user']['name'] ?? 'Kullanıcı')?></span></a>
    </div>
</header>
