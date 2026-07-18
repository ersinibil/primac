<?php
require_once 'common.php';
if(!user_can('stock')){ header('Location: index.php'); exit; }
$pdo=db();
$error=''; $ok='';

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
    }catch(Throwable $e){ $error=$e->getMessage(); }
}

$brands=$pdo->query("SELECT * FROM product_brands ORDER BY active DESC, name")->fetchAll();
$units=$pdo->query("SELECT * FROM product_units ORDER BY active DESC, name")->fetchAll();

topx('Marka / Birim');
?>
<?php if($error): ?><?=ds_alert('danger',$error)?><?php endif; ?>
<?php if($ok): ?><?=ds_alert('success',$ok)?><?php endif; ?>

<div class="muted" style="font-size:12px;margin:4px 4px 6px;font-weight:700">Markalar</div>
<div class="df-panel">
  <form method="post">
    <label>Yeni Marka</label>
    <input name="brand_name" placeholder="Örn: Elegoo, Sunlu, Primac">
    <button class="df-btn df-btn--primary" name="add_brand" value="1" style="width:100%;margin-top:6px"><?=ds_icon('plus',15)?> Marka Ekle</button>
  </form>
</div>
<?php foreach($brands as $b): ?>
<div class="df-panel" style="margin-top:10px">
  <form method="post" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
    <input type="hidden" name="id" value="<?=$b['id']?>">
    <input name="name" value="<?=htmlspecialchars($b['name'])?>" style="flex:1;min-width:120px;margin:0">
    <label style="display:flex;align-items:center;gap:6px;font-size:13px;width:auto"><input type="checkbox" name="active" <?=$b['active']?'checked':''?> style="width:auto"> Aktif</label>
    <button class="df-btn df-btn--secondary" name="update_brand" value="1"><?=ds_icon('check',14)?> Kaydet</button>
  </form>
</div>
<?php endforeach; ?>
<?php if(!$brands): ?><?php ds_empty_state('Marka yok.', null, ds_icon('tag',20)); ?><?php endif; ?>

<div class="muted" style="font-size:12px;margin:16px 4px 6px;font-weight:700">Birimler</div>
<div class="df-panel">
  <form method="post">
    <label>Birim Adı</label>
    <input name="unit_name" placeholder="Örn: Kilogram">
    <label>Kısa Ad</label>
    <input name="short_name" placeholder="kg">
    <button class="df-btn df-btn--primary" name="add_unit" value="1" style="width:100%;margin-top:6px"><?=ds_icon('plus',15)?> Birim Ekle</button>
  </form>
</div>
<?php foreach($units as $u): ?>
<div class="df-panel" style="margin-top:10px">
  <form method="post" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
    <input type="hidden" name="id" value="<?=$u['id']?>">
    <input name="name" value="<?=htmlspecialchars($u['name'])?>" style="flex:1;min-width:100px;margin:0">
    <input name="short_name" value="<?=htmlspecialchars($u['short_name'])?>" style="width:60px;margin:0">
    <label style="display:flex;align-items:center;gap:6px;font-size:13px;width:auto"><input type="checkbox" name="active" <?=$u['active']?'checked':''?> style="width:auto"> Aktif</label>
    <button class="df-btn df-btn--secondary" name="update_unit" value="1"><?=ds_icon('check',14)?> Kaydet</button>
  </form>
</div>
<?php endforeach; ?>
<?php if(!$units): ?><?php ds_empty_state('Birim yok.', null, ds_icon('tag',20)); ?><?php endif; ?>
<?php botx(); ?>
