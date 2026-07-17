<?php
require_once __DIR__.'/boot.php';
require_login();

$pdo=db();
$error='';
$ok='';

if($_SERVER['REQUEST_METHOD']==='POST'){
    try{
        if(isset($_POST['add_brand'])){
            $name=trim($_POST['brand_name']);
            if($name==='') throw new Exception('Marka adı boş olamaz.');
            $pdo->prepare("INSERT IGNORE INTO product_brands(name,active) VALUES(?,1)")->execute([$name]);
            $ok='Marka eklendi.';
            if(function_exists('activity_log')) activity_log('Stok','Marka Ekleme',$name,'','brand',(int)$pdo->lastInsertId(),'product_taxonomy.php','🏷️');
        }
        if(isset($_POST['update_brand'])){
            $pdo->prepare("UPDATE product_brands SET name=?, active=? WHERE id=?")
                ->execute([trim($_POST['name']),isset($_POST['active'])?1:0,(int)$_POST['id']]);
            $ok='Marka güncellendi.';
            if(function_exists('activity_log')) activity_log('Stok','Marka Düzenleme',trim($_POST['name']),'','brand',(int)$_POST['id'],'product_taxonomy.php','✏️');
        }
        if(isset($_POST['add_unit'])){
            $name=trim($_POST['unit_name']);
            $short=trim($_POST['short_name']);
            if($name==='' || $short==='') throw new Exception('Birim adı ve kısa adı boş olamaz.');
            $pdo->prepare("INSERT IGNORE INTO product_units(name,short_name,active) VALUES(?,?,1)")->execute([$name,$short]);
            $ok='Birim eklendi.';
            if(function_exists('activity_log')) activity_log('Stok','Birim Ekleme',$name,'','unit',(int)$pdo->lastInsertId(),'product_taxonomy.php','📏');
        }
        if(isset($_POST['update_unit'])){
            $pdo->prepare("UPDATE product_units SET name=?, short_name=?, active=? WHERE id=?")
                ->execute([trim($_POST['name']),trim($_POST['short_name']),isset($_POST['active'])?1:0,(int)$_POST['id']]);
            $ok='Birim güncellendi.';
            if(function_exists('activity_log')) activity_log('Stok','Birim Düzenleme',trim($_POST['name']),'','unit',(int)$_POST['id'],'product_taxonomy.php','✏️');
        }
    }catch(Throwable $e){
        $error=$e->getMessage();
    }
}

require_once __DIR__.'/layout_top.php';

$brands=$pdo->query("SELECT * FROM product_brands ORDER BY active DESC, name")->fetchAll();
$units=$pdo->query("SELECT * FROM product_units ORDER BY active DESC, name")->fetchAll();
?>

<?php
// RELEASE 0.9 — DS Migration (2026-07-17): render katmanı ds_lib.php desenine taşındı, POST/iş
// mantığı (yukarısı) HİÇ değişmedi — sadece HTML/CSS.
$__actions = ds_button('Kategoriler','product_categories.php','secondary','','',true).ds_button('Stok Listesi','stock.php','secondary','','',true);
ds_page_header('Marka & Birim Yönetimi', ds_icon('tag',24), '', $__actions, false, true);
?>

<?php if($error): ?><?=ds_alert('danger',$error)?><?php endif; ?>
<?php if($ok): ?><?=ds_alert('success',$ok)?><?php endif; ?>

<div class="df-taxonomy-grid">
<section class="df-card">
<h2 class="df-section-title">Markalar</h2>
<form method="post" style="margin-bottom:var(--df-space-4)">
<?php ds_form_field('Yeni Marka', '<input name="brand_name" placeholder="Örn: Elegoo, Sunlu, Primac">'); ?>
<button class="df-btn df-btn--primary" name="add_brand" value="1">Marka Ekle</button>
</form>

<div class="df-table-wrap"><table class="df-table">
<thead><tr><th>Marka</th><th>Durum</th><th>İşlem</th></tr></thead>
<tbody>
<?php foreach($brands as $b): ?>
<tr>
<form method="post">
<input type="hidden" name="id" value="<?=$b['id']?>">
<td><input name="name" value="<?=h($b['name'])?>"></td>
<td><label style="display:flex;align-items:center;gap:6px"><input type="checkbox" name="active" <?=$b['active']?'checked':''?> style="width:auto"> Aktif</label></td>
<td><button class="df-btn df-btn--sm df-btn--secondary" name="update_brand" value="1">Kaydet</button></td>
</form>
</tr>
<?php endforeach; ?>
<?php if(!$brands): ?><tr><td colspan="3" class="df-muted">Marka yok.</td></tr><?php endif; ?>
</tbody>
</table></div>
</section>

<section class="df-card">
<h2 class="df-section-title">Birimler</h2>
<form method="post" class="df-form-grid-2" style="margin-bottom:var(--df-space-4)">
<?php ds_form_field('Birim Adı', '<input name="unit_name" placeholder="Örn: Kilogram">'); ?>
<?php ds_form_field('Kısa Ad', '<input name="short_name" placeholder="kg">'); ?>
<div class="df-form-span-2"><button class="df-btn df-btn--primary" name="add_unit" value="1">Birim Ekle</button></div>
</form>

<div class="df-table-wrap"><table class="df-table">
<thead><tr><th>Birim</th><th>Kısa</th><th>Durum</th><th>İşlem</th></tr></thead>
<tbody>
<?php foreach($units as $u): ?>
<tr>
<form method="post">
<input type="hidden" name="id" value="<?=$u['id']?>">
<td><input name="name" value="<?=h($u['name'])?>"></td>
<td><input name="short_name" value="<?=h($u['short_name'])?>" style="width:80px"></td>
<td><label style="display:flex;align-items:center;gap:6px"><input type="checkbox" name="active" <?=$u['active']?'checked':''?> style="width:auto"> Aktif</label></td>
<td><button class="df-btn df-btn--sm df-btn--secondary" name="update_unit" value="1">Kaydet</button></td>
</form>
</tr>
<?php endforeach; ?>
<?php if(!$units): ?><tr><td colspan="4" class="df-muted">Birim yok.</td></tr><?php endif; ?>
</tbody>
</table></div>
</section>
</div>

<style>
body.nav-compact .df-form-grid-2{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:0 var(--df-space-4)}
body.nav-compact .df-form-span-2{grid-column:1 / -1}
body.nav-compact .df-taxonomy-grid{display:grid;grid-template-columns:1fr 1fr;gap:var(--df-space-4)}
body.nav-compact .df-section-title{font-size:var(--df-type-section-size);font-weight:var(--df-type-section-weight);color:var(--df-ink-900);margin:0 0 var(--df-space-3)}
body.nav-compact .df-muted{color:var(--df-ink-500)}
@media(max-width:960px){body.nav-compact .df-taxonomy-grid{grid-template-columns:1fr}}
@media(max-width:640px){body.nav-compact .df-form-grid-2{grid-template-columns:1fr}}
</style>

<?php require_once __DIR__.'/layout_bottom.php'; ?>
