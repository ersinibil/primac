<?php
require_once 'common.php';
if(!user_can('stock')){ header('Location: index.php'); exit; }
$pdo=db();
$error=''; $ok='';

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
        if(isset($_POST['delete']) && can_edit_delete()){
            $id=(int)$_POST['id'];
            $cnt=(int)$pdo->query("SELECT COUNT(*) c FROM stock_items WHERE category_id=".$id)->fetch()['c'];
            if($cnt>0){ $pdo->prepare("UPDATE product_categories SET active=0 WHERE id=?")->execute([$id]); $ok='Ürünlerle bağlı olduğu için pasife alındı.'; }
            else { $pdo->prepare("DELETE FROM product_categories WHERE id=?")->execute([$id]); $ok='Kategori silindi.'; }
        }
    }catch(Throwable $e){ $error=$e->getMessage(); }
}

$cats=$pdo->query("SELECT c.*, p.name parent_name FROM product_categories c LEFT JOIN product_categories p ON p.id=c.parent_id ORDER BY COALESCE(c.parent_id,c.id), c.parent_id IS NOT NULL, c.sort_order, c.name")->fetchAll();

topx('Ürün Kategorileri');
?>
<?php if($error): ?><?=ds_alert('danger',$error)?><?php endif; ?>
<?php if($ok): ?><?=ds_alert('success',$ok)?><?php endif; ?>

<details class="df-panel" open>
  <summary style="font-weight:900;cursor:pointer"><?=ds_icon('plus',16)?> Yeni Kategori / Alt Kategori</summary>
  <form method="post" style="margin-top:10px">
    <label>Kategori Adı</label>
    <input name="name" required placeholder="Örn: Filament, Kompozit, Dekota">
    <label>Üst Kategori</label>
    <select name="parent_id">
    <option value="">Ana kategori</option>
    <?php foreach($cats as $c): ?>
    <option value="<?=$c['id']?>"><?=htmlspecialchars(($c['parent_name'] ? $c['parent_name'].' / ' : '').$c['name'])?></option>
    <?php endforeach; ?>
    </select>
    <label>Sıra</label>
    <input type="number" name="sort_order" value="0">
    <label style="display:flex;align-items:center;gap:8px;margin:8px 0"><input type="checkbox" name="active" checked style="width:auto"> Aktif</label>
    <button class="df-btn df-btn--primary df-btn--lg" name="add" value="1" style="width:100%"><?=ds_icon('plus',16)?> Kategori Ekle</button>
  </form>
</details>

<div class="muted" style="font-size:12px;margin:12px 4px 6px;font-weight:700">Kategori Ağacı</div>
<?php foreach($cats as $c): $productCount=(int)$pdo->query("SELECT COUNT(*) c FROM stock_items WHERE category_id=".(int)$c['id'])->fetch()['c']; ?>
<div class="df-panel" style="margin-top:10px">
  <form method="post">
  <input type="hidden" name="id" value="<?=$c['id']?>">
  <label>Kategori Adı</label>
  <input name="name" value="<?=htmlspecialchars($c['name'])?>">
  <label>Üst Kategori</label>
  <select name="parent_id">
  <option value="">Ana kategori</option>
  <?php foreach($cats as $p): if($p['id']==$c['id']) continue; ?>
  <option value="<?=$p['id']?>" <?=$c['parent_id']==$p['id']?'selected':''?>><?=htmlspecialchars($p['name'])?></option>
  <?php endforeach; ?>
  </select>
  <div style="display:flex;gap:10px;align-items:center;margin:8px 0">
    <label style="display:flex;align-items:center;gap:6px;font-size:13px;width:auto"><input type="checkbox" name="active" <?=$c['active']?'checked':''?> style="width:auto"> Aktif</label>
    <span class="df-badge df-badge--info"><?=$productCount?> ürün</span>
  </div>
  <label>Sıra</label>
  <input type="number" name="sort_order" value="<?=htmlspecialchars($c['sort_order'] ?? 0)?>">
  <button class="df-btn df-btn--primary" name="update" value="1" style="width:100%;margin-top:8px"><?=ds_icon('check',16)?> Kaydet</button>
  </form>
  <?php if(can_edit_delete()): ?>
  <form method="post" onsubmit="return confirm('<?=$productCount>0?$productCount.' ürünle bağlı — pasife alınacak, emin misin?':'Silinsin mi?'?>')" style="margin-top:6px">
    <input type="hidden" name="id" value="<?=$c['id']?>">
    <button class="df-btn df-btn--danger" name="delete" value="1" style="width:100%"><?=ds_icon('trash',15)?> Sil</button>
  </form>
  <?php endif; ?>
</div>
<?php endforeach; ?>
<?php if(!$cats): ?><?php ds_empty_state('Kategori yok.', null, ds_icon('tag',20)); ?><?php endif; ?>
<?php botx(); ?>
