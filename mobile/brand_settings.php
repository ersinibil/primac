<?php
require_once 'common.php';
// PDP-001 (2026-07-15): web tarafındaki brand_settings.php ile aynı düzeltme — mobil menüde
// (more.php) bu kart zaten user_can('users') şartıyla gösteriliyordu, ama sayfanın kendisi
// sadece $isAdmin ile kilitliydi. 'users' yetkili-ama-admin-olmayan biri kartı görüp tıklayınca
// sessizce index.php'ye atılıyordu. Gate menüyle hizalandı.
if(!$isAdmin && !user_can('users')){ header('Location: index.php'); exit; }
require_once __DIR__.'/../share_lib.php';

$uploads = __DIR__.'/../uploads';
if(!is_dir($uploads)) mkdir($uploads, 0755, true);

$msg=''; $err='';

if($_SERVER['REQUEST_METHOD']==='POST'){
    $action = $_POST['action'] ?? 'upload';

    if($action === 'reset_logo'){
        set_setting('brand_logo','');
        $msg = 'Ana logo varsayılana döndürüldü.';
    } elseif($action === 'reset_icon'){
        set_setting('brand_icon','');
        $msg = 'Uygulama ikonu varsayılana döndürüldü.';
    } elseif($action === 'upload'){
        $uploaded = false;
        if(!empty($_FILES['brand_logo']['tmp_name'])){
            $dest = $uploads.'/brand_logo.png';
            if(_brand_upload('brand_logo', $dest, $err)){
                set_setting('brand_logo','uploads/brand_logo.png');
                $msg .= 'Ana logo güncellendi. ';
                $uploaded = true;
            }
        }
        if(!$err && !empty($_FILES['brand_icon']['tmp_name'])){
            $dest = $uploads.'/brand_icon.png';
            if(_brand_upload('brand_icon', $dest, $err)){
                set_setting('brand_icon','uploads/brand_icon.png');
                $msg .= 'Uygulama ikonu güncellendi. ';
                $uploaded = true;
            }
        }
        if(!$uploaded && !$err) $err = 'Yüklenecek dosya seçilmedi.';
    }
}

$cur_logo = get_setting('brand_logo','');
$cur_icon = get_setting('brand_icon','');

topx('Logo / Marka');
?>
<p class="small">Yüklenen logo; giriş ekranı, üst menü ve mobil uygulamada görünür. PNG veya JPG önerilir (şeffaf arkaplan için PNG).</p>

<?php if($msg): ?><?=ds_alert('success',$msg)?><?php endif; ?>
<?php if($err): ?><?=ds_alert('danger',$err)?><?php endif; ?>

<?php if($cur_logo && is_file(__DIR__.'/../'.$cur_logo)): ?>
<form method="post" id="frm-reset-logo" style="display:none"><input type="hidden" name="action" value="reset_logo"></form>
<?php endif; ?>
<?php if($cur_icon && is_file(__DIR__.'/../'.$cur_icon)): ?>
<form method="post" id="frm-reset-icon" style="display:none"><input type="hidden" name="action" value="reset_icon"></form>
<?php endif; ?>

<form method="post" enctype="multipart/form-data">
<input type="hidden" name="action" value="upload">

<div class="df-panel">
    <b><?=ds_icon('box',16)?> Ana Logo</b>
    <p class="small" style="margin:6px 0 12px">Giriş sayfası ve yan menüde kullanılır.</p>
    <?php if($cur_logo && is_file(__DIR__.'/../'.$cur_logo)): ?>
    <div style="background:rgba(255,255,255,.06);border-radius:14px;padding:14px;text-align:center;margin-bottom:12px">
        <img src="../<?=h($cur_logo)?>?v=<?=filemtime(__DIR__.'/../'.$cur_logo)?>" alt="Mevcut Logo" style="max-width:100%;max-height:100px;object-fit:contain;display:block;margin:auto">
        <div class="small" style="margin-top:8px">Mevcut logo</div>
    </div>
    <button type="button" class="df-btn df-btn--secondary" style="width:100%;margin-bottom:12px" onclick="if(confirm('Ana logoyu varsayılana döndür?'))document.getElementById('frm-reset-logo').submit()">↩ Varsayılana dön</button>
    <?php else: ?>
    <div style="background:rgba(255,255,255,.06);border:1px dashed rgba(255,255,255,.2);border-radius:14px;padding:18px;text-align:center;margin-bottom:12px;color:#94a3b8">Özel logo yok — varsayılan kullanılıyor</div>
    <?php endif; ?>
    <label style="color:#94a3b8;font-size:12px">Yeni logo yükle</label>
    <input type="file" name="brand_logo" accept="image/png,image/jpeg,image/webp,image/gif">
</div>

<div class="df-panel">
    <b><?=ds_icon('home',16)?> Uygulama İkonu (PWA)</b>
    <p class="small" style="margin:6px 0 12px">Mobil ana ekrana eklenen uygulama ikonu. Kare resim önerilir.</p>
    <?php if($cur_icon && is_file(__DIR__.'/../'.$cur_icon)): ?>
    <div style="background:rgba(255,255,255,.06);border-radius:14px;padding:14px;text-align:center;margin-bottom:12px">
        <img src="../<?=h($cur_icon)?>?v=<?=filemtime(__DIR__.'/../'.$cur_icon)?>" alt="Mevcut İkon" style="width:72px;height:72px;object-fit:contain;border-radius:18px;display:block;margin:auto">
        <div class="small" style="margin-top:8px">Mevcut ikon</div>
    </div>
    <button type="button" class="df-btn df-btn--secondary" style="width:100%;margin-bottom:12px" onclick="if(confirm('Uygulama ikonunu varsayılana döndür?'))document.getElementById('frm-reset-icon').submit()">↩ Varsayılana dön</button>
    <?php else: ?>
    <div style="background:rgba(255,255,255,.06);border:1px dashed rgba(255,255,255,.2);border-radius:14px;padding:18px;text-align:center;margin-bottom:12px;color:#94a3b8">Özel ikon yok — varsayılan kullanılıyor</div>
    <?php endif; ?>
    <label style="color:#94a3b8;font-size:12px">Yeni ikon yükle</label>
    <input type="file" name="brand_icon" accept="image/png,image/jpeg,image/webp,image/gif">
</div>

<button type="submit" class="df-btn df-btn--primary df-btn--lg" style="width:100%"><?=ds_icon('check',16)?> Logo / İkonu Kaydet</button>
</form>

<div class="df-panel" style="margin-top:14px">
    <b style="color:#93c5fd"><?=ds_icon('info',16)?> Bilgi</b>
    <ul style="margin:8px 0 0;padding-left:18px;color:#cbd5e1;font-size:13px;line-height:1.7">
        <li>Yüklenen logo anında aktif olur.</li>
        <li>PWA ikonu için tarayıcı önbelleğini temizleyip uygulamayı yeniden ana ekrana ekleyin.</li>
        <li>Her site (PRIMAC/ACANS) kendi logosunu barındırır, karışmaz.</li>
    </ul>
</div>
<?php botx(); ?>
