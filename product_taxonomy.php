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

<style>
.mini-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}
</style>

<div class="panel-head">
<h1>Marka & Birim Yönetimi</h1>
<div class="actions">
<a class="btn secondary" href="product_categories.php">Kategoriler</a>
<a class="btn secondary" href="stock.php">Stok Listesi</a>
</div>
</div>

<?php if($error): ?><div class="alert"><?=h($error)?></div><?php endif; ?>
<?php if($ok): ?><div class="ok"><?=h($ok)?></div><?php endif; ?>

<div class="mini-grid">
<section class="panel">
<h2>Markalar</h2>
<form method="post" class="form-grid">
<label class="full">Yeni Marka
<input name="brand_name" placeholder="Örn: Elegoo, Sunlu, Primac">
</label>
<button class="btn" name="add_brand" value="1">Marka Ekle</button>
</form>

<table>
<thead><tr><th>Marka</th><th>Durum</th><th>İşlem</th></tr></thead>
<tbody>
<?php foreach($brands as $b): ?>
<tr>
<form method="post">
<input type="hidden" name="id" value="<?=$b['id']?>">
<td><input name="name" value="<?=h($b['name'])?>"></td>
<td><label><input type="checkbox" name="active" <?=$b['active']?'checked':''?> style="width:auto"> Aktif</label></td>
<td><button class="btn small secondary" name="update_brand" value="1">Kaydet</button></td>
</form>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</section>

<section class="panel">
<h2>Birimler</h2>
<form method="post" class="form-grid">
<label>Birim Adı
<input name="unit_name" placeholder="Örn: Kilogram">
</label>
<label>Kısa Ad
<input name="short_name" placeholder="kg">
</label>
<button class="btn" name="add_unit" value="1">Birim Ekle</button>
</form>

<table>
<thead><tr><th>Birim</th><th>Kısa</th><th>Durum</th><th>İşlem</th></tr></thead>
<tbody>
<?php foreach($units as $u): ?>
<tr>
<form method="post">
<input type="hidden" name="id" value="<?=$u['id']?>">
<td><input name="name" value="<?=h($u['name'])?>"></td>
<td><input name="short_name" value="<?=h($u['short_name'])?>" style="width:80px"></td>
<td><label><input type="checkbox" name="active" <?=$u['active']?'checked':''?> style="width:auto"> Aktif</label></td>
<td><button class="btn small secondary" name="update_unit" value="1">Kaydet</button></td>
</form>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</section>
</div>

<?php require_once __DIR__.'/layout_bottom.php'; ?>
