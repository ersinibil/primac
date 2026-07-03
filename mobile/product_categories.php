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
<?php if($error): ?><div class="err"><?=htmlspecialchars($error)?></div><?php endif; ?>
<?php if($ok): ?><div class="notice"><?=htmlspecialchars($ok)?></div><?php endif; ?>

<details class="panel" open>
  <summary style="font-weight:900;cursor:pointer">➕ Yeni Kategori / Alt Kategori</summary>
  <form method="post" style="margin-top:10px">
    <label style="color:#94a3b8;font-size:12px">Kategori Adı</label>
    <input name="name" required placeholder="Örn: Filament, Kompozit, Dekota">
    <label style="color:#94a3b8;font-size:12px">Üst Kategori</label>
    <select name="parent_id">
    <option value="">Ana kategori</option>
    <?php foreach($cats as $c): ?>
    <option value="<?=$c['id']?>"><?=htmlspecialchars(($c['parent_name'] ? $c['parent_name'].' / ' : '').$c['name'])?></option>
    <?php endforeach; ?>
    </select>
    <label style="color:#94a3b8;font-size:12px">Sıra</label>
    <input type="number" name="sort_order" value="0">
    <label style="display:flex;align-items:center;gap:8px;margin:8px 0"><input type="checkbox" name="active" checked style="width:auto"> Aktif</label>
    <button class="btn dark" name="add" value="1" style="width:100%;padding:12px">Kategori Ekle</button>
  </form>
</details>

<div style="font-size:12px;color:#94a3b8;margin:12px 4px 6px">Kategori Ağacı</div>
<?php foreach($cats as $c): $productCount=(int)$pdo->query("SELECT COUNT(*) c FROM stock_items WHERE category_id=".(int)$c['id'])->fetch()['c']; ?>
<div class="item">
  <form method="post">
  <input type="hidden" name="id" value="<?=$c['id']?>">
  <label style="color:#94a3b8;font-size:12px">Kategori Adı</label>
  <input name="name" value="<?=htmlspecialchars($c['name'])?>">
  <label style="color:#94a3b8;font-size:12px">Üst Kategori</label>
  <select name="parent_id">
  <option value="">Ana kategori</option>
  <?php foreach($cats as $p): if($p['id']==$c['id']) continue; ?>
  <option value="<?=$p['id']?>" <?=$c['parent_id']==$p['id']?'selected':''?>><?=htmlspecialchars($p['name'])?></option>
  <?php endforeach; ?>
  </select>
  <div style="display:flex;gap:10px;align-items:center;margin:8px 0">
    <label style="display:flex;align-items:center;gap:6px;font-size:13px"><input type="checkbox" name="active" <?=$c['active']?'checked':''?> style="width:auto"> Aktif</label>
    <span class="small"><?=$productCount?> ürün</span>
  </div>
  <label style="color:#94a3b8;font-size:12px">Sıra</label>
  <input type="number" name="sort_order" value="<?=htmlspecialchars($c['sort_order'] ?? 0)?>">
  <button class="btn dark" name="update" value="1" style="width:100%;padding:10px;margin-top:8px">💾 Kaydet</button>
  </form>
  <?php if(can_edit_delete()): ?>
  <form method="post" onsubmit="return confirm('<?=$productCount>0?$productCount.' ürünle bağlı — pasife alınacak, emin misin?':'Silinsin mi?'?>')" style="margin-top:6px">
    <input type="hidden" name="id" value="<?=$c['id']?>">
    <button class="btn" name="delete" value="1" style="width:100%;background:rgba(239,68,68,.2);color:#fca5a5;padding:8px">🗑 Sil</button>
  </form>
  <?php endif; ?>
</div>
<?php endforeach; ?>
<?php if(!$cats): ?><div class="panel muted" style="text-align:center">Kategori yok.</div><?php endif; ?>
<?php botx(); ?>
