<?php
require_once __DIR__.'/boot.php';
require_login();

$pdo=db();
$error='';
$ok='';

if($_SERVER['REQUEST_METHOD']==='POST'){
    try{
        if(isset($_POST['add'])){
            $name=trim($_POST['name']);
            if($name==='') throw new Exception('Kategori adı boş olamaz.');
            $pdo->prepare("INSERT INTO product_categories(name,parent_id,active,sort_order) VALUES(?,?,?,?)")
                ->execute([$name,(int)$_POST['parent_id'] ?: null,isset($_POST['active'])?1:0,(int)$_POST['sort_order']]);
            $ok='Kategori eklendi.';
            if(function_exists('activity_log')) activity_log('Stok','Kategori Ekleme',$name,'','category',(int)$pdo->lastInsertId(),'product_categories.php','🗂️');
        }

        if(isset($_POST['update'])){
            $id=(int)$_POST['id'];
            $name=trim($_POST['name']);
            if($name==='') throw new Exception('Kategori adı boş olamaz.');
            $pdo->prepare("UPDATE product_categories SET name=?, parent_id=?, active=?, sort_order=? WHERE id=?")
                ->execute([$name,(int)$_POST['parent_id'] ?: null,isset($_POST['active'])?1:0,(int)$_POST['sort_order'],$id]);
            $ok='Kategori güncellendi.';
            if(function_exists('activity_log')) activity_log('Stok','Kategori Düzenleme',$name,'','category',$id,'product_categories.php','✏️');
        }
    }catch(Throwable $e){
        $error=$e->getMessage();
    }
}

require_once __DIR__.'/layout_top.php';

$cats=$pdo->query("SELECT c.*, p.name parent_name FROM product_categories c LEFT JOIN product_categories p ON p.id=c.parent_id ORDER BY COALESCE(c.parent_id,c.id), c.parent_id IS NOT NULL, c.sort_order, c.name")->fetchAll();
?>

<div class="panel-head">
<h1>Ürün Kategorileri</h1>
<div class="actions">
<a class="btn secondary" href="stock.php">Stok Listesi</a>
<a class="btn secondary" href="product_taxonomy.php">Marka / Birim</a>
</div>
</div>

<?php if($error): ?><div class="alert"><?=h($error)?></div><?php endif; ?>
<?php if($ok): ?><div class="ok"><?=h($ok)?></div><?php endif; ?>

<section class="panel">
<h2>Yeni Kategori / Alt Kategori</h2>
<form method="post" class="form-grid">
<label>Kategori Adı
<input name="name" required placeholder="Örn: Filament, Kompozit, Dekota">
</label>

<label>Üst Kategori
<select name="parent_id">
<option value="">Ana kategori</option>
<?php foreach($cats as $c): ?>
<option value="<?=$c['id']?>"><?=h(($c['parent_name'] ? $c['parent_name'].' / ' : '').$c['name'])?></option>
<?php endforeach; ?>
</select>
</label>

<label>Sıra
<input type="number" name="sort_order" value="0">
</label>

<label class="full"><input type="checkbox" name="active" checked style="width:auto"> Aktif</label>

<button class="btn" name="add" value="1">Kategori Ekle</button>
</form>
</section>

<section class="panel">
<h2>Kategori Ağacı</h2>
<table>
<thead><tr><th>Kategori</th><th>Üst Kategori</th><th>Ürün</th><th>Durum</th><th>Düzenle</th></tr></thead>
<tbody>
<?php foreach($cats as $c): ?>
<tr>
<form method="post">
<input type="hidden" name="id" value="<?=$c['id']?>">
<td><input name="name" value="<?=h($c['name'])?>"></td>
<td>
<select name="parent_id">
<option value="">Ana kategori</option>
<?php foreach($cats as $p): if($p['id']==$c['id']) continue; ?>
<option value="<?=$p['id']?>" <?=$c['parent_id']==$p['id']?'selected':''?>><?=h($p['name'])?></option>
<?php endforeach; ?>
</select>
</td>
<td><?=safe_count("SELECT COUNT(*) c FROM stock_items WHERE category_id=".(int)$c['id'])?></td>
<td><label><input type="checkbox" name="active" <?=$c['active']?'checked':''?> style="width:auto"> Aktif</label></td>
<td>
<input type="number" name="sort_order" value="<?=h($c['sort_order'] ?? 0)?>" style="width:80px">
<button class="btn small secondary" name="update" value="1">Kaydet</button>
</td>
</form>
</tr>
<?php endforeach; ?>
<?php if(!$cats): ?><tr><td colspan="5" class="muted">Kategori yok.</td></tr><?php endif; ?>
</tbody>
</table>
</section>

<?php require_once __DIR__.'/layout_bottom.php'; ?>
