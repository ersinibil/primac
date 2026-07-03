<?php
require_once __DIR__.'/boot.php';
require_once __DIR__.'/share_lib.php';
require_login();
if(!is_admin()){ http_response_code(403); echo '<h2>Bu sayfa yalnızca yöneticilere açıktır.</h2>'; exit; }

// uploads/ yazılabilir güvencesi
$uploads = __DIR__.'/uploads';
if(!is_dir($uploads)) mkdir($uploads, 0755, true);

$msg = '';
$err = '';

// İzin verilen resim uzantıları — _brand_upload() artık share_lib.php'de (web+mobil ortak)

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
        // Ana logo
        if(!empty($_FILES['brand_logo']['tmp_name'])){
            $dest = $uploads.'/brand_logo.png';
            if(_brand_upload('brand_logo', $dest, $err)){
                set_setting('brand_logo','uploads/brand_logo.png');
                $msg .= 'Ana logo güncellendi. ';
                $uploaded = true;
            }
        }
        // Uygulama ikonu
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

require __DIR__.'/layout_top.php';
?>
<h1>🎨 Logo / Marka Ayarları</h1>
<p class="muted">Yüklenen logo; giriş ekranı, üst menü ve mobil uygulamada görünür. PNG veya JPG önerilir (şeffaf arkaplan için PNG).</p>

<?php if($msg): ?><div class="ok"><?=h($msg)?></div><?php endif; ?>
<?php if($err): ?><div class="alert"><?=h($err)?></div><?php endif; ?>

<!-- Reset formları — yükleme formunun dışında, iç içe form yok -->
<?php if($cur_logo && is_file(__DIR__.'/'.$cur_logo)): ?>
<form method="post" id="frm-reset-logo" style="display:none">
    <input type="hidden" name="action" value="reset_logo">
</form>
<?php endif; ?>
<?php if($cur_icon && is_file(__DIR__.'/'.$cur_icon)): ?>
<form method="post" id="frm-reset-icon" style="display:none">
    <input type="hidden" name="action" value="reset_icon">
</form>
<?php endif; ?>

<form method="post" enctype="multipart/form-data">
<input type="hidden" name="action" value="upload">
<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-top:18px">

    <!-- Ana Logo -->
    <div class="panel">
        <h2 style="margin:0 0 12px;font-size:18px">📷 Ana Logo</h2>
        <p class="muted" style="margin:0 0 12px">Giriş sayfası ve yan menüde kullanılır. Yatay/kare logo önerilir.</p>

        <?php if($cur_logo && is_file(__DIR__.'/'.$cur_logo)): ?>
        <div style="background:#f5f7fb;border:1px solid #eef2f6;border-radius:14px;padding:14px;text-align:center;margin-bottom:14px">
            <img src="<?=h($cur_logo)?>?v=<?=filemtime(__DIR__.'/'.$cur_logo)?>" alt="Mevcut Logo"
                 style="max-width:100%;max-height:120px;object-fit:contain;display:block;margin:auto">
            <div class="muted" style="margin-top:8px;font-size:12px">Mevcut logo</div>
        </div>
        <button type="button" class="btn secondary" style="margin-bottom:14px;font-size:13px"
                onclick="if(confirm('Ana logoyu varsayılana döndür?'))document.getElementById('frm-reset-logo').submit()">↩ Varsayılana dön</button>
        <?php else: ?>
        <div style="background:#f5f7fb;border:1px dashed #d0d5dd;border-radius:14px;padding:20px;text-align:center;margin-bottom:14px;color:#667085">
            Özel logo yok — varsayılan kullanılıyor
        </div>
        <?php endif; ?>

        <label style="font-weight:800;display:block;margin-bottom:6px">Yeni logo yükle</label>
        <input type="file" name="brand_logo" accept="image/png,image/jpeg,image/webp,image/gif"
               style="border:1px solid #d0d5dd;border-radius:12px;padding:10px;width:100%;background:#fff">
        <div class="muted" style="margin-top:6px;font-size:12px">Önerilen: PNG, şeffaf, min 200×60 px</div>
    </div>

    <!-- Uygulama İkonu -->
    <div class="panel">
        <h2 style="margin:0 0 12px;font-size:18px">📱 Uygulama İkonu (PWA)</h2>
        <p class="muted" style="margin:0 0 12px">Mobil ana ekrana eklenen uygulama ikonu. Kare resim önerilir.</p>

        <?php if($cur_icon && is_file(__DIR__.'/'.$cur_icon)): ?>
        <div style="background:#f5f7fb;border:1px solid #eef2f6;border-radius:14px;padding:14px;text-align:center;margin-bottom:14px">
            <img src="<?=h($cur_icon)?>?v=<?=filemtime(__DIR__.'/'.$cur_icon)?>" alt="Mevcut İkon"
                 style="width:80px;height:80px;object-fit:contain;border-radius:18px;border:1px solid #eef2f6;display:block;margin:auto">
            <div class="muted" style="margin-top:8px;font-size:12px">Mevcut ikon</div>
        </div>
        <button type="button" class="btn secondary" style="margin-bottom:14px;font-size:13px"
                onclick="if(confirm('Uygulama ikonunu varsayılana döndür?'))document.getElementById('frm-reset-icon').submit()">↩ Varsayılana dön</button>
        <?php else: ?>
        <div style="background:#f5f7fb;border:1px dashed #d0d5dd;border-radius:14px;padding:20px;text-align:center;margin-bottom:14px;color:#667085">
            Özel ikon yok — varsayılan kullanılıyor
        </div>
        <?php endif; ?>

        <label style="font-weight:800;display:block;margin-bottom:6px">Yeni ikon yükle</label>
        <input type="file" name="brand_icon" accept="image/png,image/jpeg,image/webp,image/gif"
               style="border:1px solid #d0d5dd;border-radius:12px;padding:10px;width:100%;background:#fff">
        <div class="muted" style="margin-top:6px;font-size:12px">Önerilen: PNG, kare, min 192×192 px</div>
    </div>

</div>

<div style="margin-top:16px">
    <button type="submit" class="btn" style="padding:13px 28px">⬆ Logo / İkonu Kaydet</button>
</div>
</form>

<div class="panel" style="margin-top:24px;background:#f0f9ff;border:1px solid #bae6fd">
    <strong style="color:#0369a1">ℹ Bilgi</strong>
    <ul style="margin:8px 0 0;padding-left:20px;color:#0c4a6e;font-size:14px;line-height:1.7">
        <li>Yüklenen logo anında aktif olur — sayfayı yenileyince görünür.</li>
        <li>Mobil PWA ikonunun güncellenmesi için tarayıcı önbelleğini temizleyip uygulamayı yeniden ana ekrana ekleyin.</li>
        <li>Her site (PRIMAC/ACANS) kendi <code>uploads/brand_logo.png</code> dosyasını barındırır; logolar karışmaz.</li>
        <li>"Varsayılana dön" seçeneği ayarı siler; sistem otomatik olarak varsayılan logoya döner.</li>
    </ul>
</div>

<?php require __DIR__.'/layout_bottom.php'; ?>
