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
<?php if($error): ?><div class="err"><?=htmlspecialchars($error)?></div><?php endif; ?>
<?php if($ok): ?><div class="notice"><?=htmlspecialchars($ok)?></div><?php endif; ?>

<div style="font-size:12px;color:#94a3b8;margin:4px 4px 6px">Markalar</div>
<div class="panel">
  <form method="post">
    <label style="color:#94a3b8;font-size:12px">Yeni Marka</label>
    <input name="brand_name" placeholder="Örn: Elegoo, Sunlu, Primac">
    <button class="btn dark" name="add_brand" value="1" style="width:100%;padding:11px;margin-top:6px">Marka Ekle</button>
  </form>
</div>
<?php foreach($brands as $b): ?>
<div class="item">
  <form method="post" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
    <input type="hidden" name="id" value="<?=$b['id']?>">
    <input name="name" value="<?=htmlspecialchars($b['name'])?>" style="flex:1;min-width:120px;margin:0">
    <label style="display:flex;align-items:center;gap:6px;font-size:13px"><input type="checkbox" name="active" <?=$b['active']?'checked':''?> style="width:auto"> Aktif</label>
    <button class="btn dark" name="update_brand" value="1" style="padding:8px 12px">Kaydet</button>
  </form>
</div>
<?php endforeach; ?>
<?php if(!$brands): ?><div class="panel muted" style="text-align:center">Marka yok.</div><?php endif; ?>

<div style="font-size:12px;color:#94a3b8;margin:16px 4px 6px">Birimler</div>
<div class="panel">
  <form method="post">
    <label style="color:#94a3b8;font-size:12px">Birim Adı</label>
    <input name="unit_name" placeholder="Örn: Kilogram">
    <label style="color:#94a3b8;font-size:12px">Kısa Ad</label>
    <input name="short_name" placeholder="kg">
    <button class="btn dark" name="add_unit" value="1" style="width:100%;padding:11px;margin-top:6px">Birim Ekle</button>
  </form>
</div>
<?php foreach($units as $u): ?>
<div class="item">
  <form method="post" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
    <input type="hidden" name="id" value="<?=$u['id']?>">
    <input name="name" value="<?=htmlspecialchars($u['name'])?>" style="flex:1;min-width:100px;margin:0">
    <input name="short_name" value="<?=htmlspecialchars($u['short_name'])?>" style="width:60px;margin:0">
    <label style="display:flex;align-items:center;gap:6px;font-size:13px"><input type="checkbox" name="active" <?=$u['active']?'checked':''?> style="width:auto"> Aktif</label>
    <button class="btn dark" name="update_unit" value="1" style="padding:8px 12px">Kaydet</button>
  </form>
</div>
<?php endforeach; ?>
<?php if(!$units): ?><div class="panel muted" style="text-align:center">Birim yok.</div><?php endif; ?>
<?php botx(); ?>
