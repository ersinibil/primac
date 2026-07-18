<?php
require_once __DIR__.'/boot.php';
require_once __DIR__.'/share_lib.php';
require_login();
// PDP-001 (2026-07-15): sol menüdeki "Logo / Marka" linki zaten user_can('users') şartıyla
// gösteriliyor (layout_top.php) — buradaki gate sadece is_admin() olunca 'users' yetkili
// admin-olmayanlar linki görüp tıklayınca 403 alıyordu. Gate menüyle birebir hizalandı.
if(!is_admin() && !user_can('users')){ http_response_code(403); echo '<h2>Bu sayfa yalnızca yöneticilere açıktır.</h2>'; exit; }

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
ds_page_header('Logo / Marka Ayarları', ds_icon('tag',24), 'Yüklenen logo; giriş ekranı, üst menü ve mobil uygulamada görünür. PNG veya JPG önerilir (şeffaf arkaplan için PNG).', '', false, true);
?>

<?php if($msg): ?><?=ds_alert('success',$msg)?><?php endif; ?>
<?php if($err): ?><?=ds_alert('danger',$err)?><?php endif; ?>

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
<div class="df-brand-grid" style="margin-top:var(--df-space-4)">

    <!-- Ana Logo -->
    <div class="df-card">
        <h2 style="margin:0 0 var(--df-space-3);font-size:18px"><?=ds_icon('tag',18)?> Ana Logo</h2>
        <p class="df-muted" style="margin:0 0 var(--df-space-3)">Giriş sayfası ve yan menüde kullanılır. Yatay/kare logo önerilir.</p>

        <?php if($cur_logo && is_file(__DIR__.'/'.$cur_logo)): ?>
        <div style="background:var(--df-surface-sunken);border:1px solid var(--df-hairline);border-radius:var(--df-radius-md);padding:var(--df-space-4);text-align:center;margin-bottom:var(--df-space-4)">
            <img src="<?=h($cur_logo)?>?v=<?=filemtime(__DIR__.'/'.$cur_logo)?>" alt="Mevcut Logo"
                 style="max-width:100%;max-height:120px;object-fit:contain;display:block;margin:auto">
            <div class="df-muted" style="margin-top:8px;font-size:12px">Mevcut logo</div>
        </div>
        <button type="button" class="df-btn df-btn--secondary df-btn--sm" style="margin-bottom:var(--df-space-4)"
                onclick="if(confirm('Ana logoyu varsayılana döndür?'))document.getElementById('frm-reset-logo').submit()">↩ Varsayılana dön</button>
        <?php else: ?>
        <div style="background:var(--df-surface-sunken);border:1px dashed var(--df-hairline);border-radius:var(--df-radius-md);padding:var(--df-space-5);text-align:center;margin-bottom:var(--df-space-4);color:var(--df-ink-500)">
            Özel logo yok — varsayılan kullanılıyor
        </div>
        <?php endif; ?>

        <?php ds_form_field('Yeni logo yükle', '<input type="file" name="brand_logo" accept="image/png,image/jpeg,image/webp,image/gif">', 'Önerilen: PNG, şeffaf, min 200×60 px'); ?>
    </div>

    <!-- Uygulama İkonu -->
    <div class="df-card">
        <h2 style="margin:0 0 var(--df-space-3);font-size:18px"><?=ds_icon('box',18)?> Uygulama İkonu (PWA)</h2>
        <p class="df-muted" style="margin:0 0 var(--df-space-3)">Mobil ana ekrana eklenen uygulama ikonu. Kare resim önerilir.</p>

        <?php if($cur_icon && is_file(__DIR__.'/'.$cur_icon)): ?>
        <div style="background:var(--df-surface-sunken);border:1px solid var(--df-hairline);border-radius:var(--df-radius-md);padding:var(--df-space-4);text-align:center;margin-bottom:var(--df-space-4)">
            <img src="<?=h($cur_icon)?>?v=<?=filemtime(__DIR__.'/'.$cur_icon)?>" alt="Mevcut İkon"
                 style="width:80px;height:80px;object-fit:contain;border-radius:18px;border:1px solid var(--df-hairline);display:block;margin:auto">
            <div class="df-muted" style="margin-top:8px;font-size:12px">Mevcut ikon</div>
        </div>
        <button type="button" class="df-btn df-btn--secondary df-btn--sm" style="margin-bottom:var(--df-space-4)"
                onclick="if(confirm('Uygulama ikonunu varsayılana döndür?'))document.getElementById('frm-reset-icon').submit()">↩ Varsayılana dön</button>
        <?php else: ?>
        <div style="background:var(--df-surface-sunken);border:1px dashed var(--df-hairline);border-radius:var(--df-radius-md);padding:var(--df-space-5);text-align:center;margin-bottom:var(--df-space-4);color:var(--df-ink-500)">
            Özel ikon yok — varsayılan kullanılıyor
        </div>
        <?php endif; ?>

        <?php ds_form_field('Yeni ikon yükle', '<input type="file" name="brand_icon" accept="image/png,image/jpeg,image/webp,image/gif">', 'Önerilen: PNG, kare, min 192×192 px'); ?>
    </div>

</div>

<div style="margin-top:var(--df-space-4)">
    <button type="submit" class="df-btn df-btn--primary df-btn--lg"><?=ds_icon('check',16)?> Logo / İkonu Kaydet</button>
</div>
</form>

<div class="df-card" style="margin-top:var(--df-space-5);background:var(--df-info-soft);border-color:var(--df-info)">
    <strong style="color:var(--df-info-ink)"><?=ds_icon('info',16)?> Bilgi</strong>
    <ul style="margin:8px 0 0;padding-left:20px;color:var(--df-info-ink);font-size:14px;line-height:1.7">
        <li>Yüklenen logo anında aktif olur — sayfayı yenileyince görünür.</li>
        <li>Mobil PWA ikonunun güncellenmesi için tarayıcı önbelleğini temizleyip uygulamayı yeniden ana ekrana ekleyin.</li>
        <li>Her site (PRIMAC/ACANS) kendi <code>uploads/brand_logo.png</code> dosyasını barındırır; logolar karışmaz.</li>
        <li>"Varsayılana dön" seçeneği ayarı siler; sistem otomatik olarak varsayılan logoya döner.</li>
    </ul>
</div>

<style>
body.nav-compact .df-brand-grid{display:grid;grid-template-columns:1fr 1fr;gap:var(--df-space-5)}
@media(max-width:760px){body.nav-compact .df-brand-grid{grid-template-columns:1fr}}
</style>

<?php require __DIR__.'/layout_bottom.php'; ?>
