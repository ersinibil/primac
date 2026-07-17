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

<?php
// RELEASE 0.9 — DS Migration (2026-07-17): render katmanı ds_lib.php desenine taşındı, POST/iş
// mantığı (yukarısı) HİÇ değişmedi — sadece HTML/CSS.
$__actions = ds_button('Stok Listesi','stock.php','secondary','','',true).ds_button('Marka / Birim','product_taxonomy.php','secondary','','',true);
ds_page_header('Ürün Kategorileri', ds_icon('tag',24), '', $__actions, false, true);
?>

<?php if($error): ?><?=ds_alert('danger',$error)?><?php endif; ?>
<?php if($ok): ?><?=ds_alert('success',$ok)?><?php endif; ?>

<section class="df-card">
<h2 class="df-section-title">Yeni Kategori / Alt Kategori</h2>
<form method="post" class="df-form-grid-2">

<div class="df-form-span-2"><?php ds_form_field('Kategori Adı', '<input name="name" required placeholder="Örn: Filament, Kompozit, Dekota">'); ?></div>

<?php
$__parentOpts='<option value="">Ana kategori</option>';
foreach($cats as $c){ $__parentOpts.='<option value="'.(int)$c['id'].'">'.h(($c['parent_name'] ? $c['parent_name'].' / ' : '').$c['name']).'</option>'; }
ds_form_field('Üst Kategori', '<select name="parent_id">'.$__parentOpts.'</select>');
?>
<?php ds_form_field('Sıra', '<input type="number" name="sort_order" value="0">'); ?>

<div class="df-form-span-2">
<label style="display:flex;align-items:center;gap:8px;font-size:var(--df-type-body-size);color:var(--df-ink-900)">
<input type="checkbox" name="active" checked style="width:auto"> Aktif
</label>
</div>

<div class="df-form-span-2"><button class="df-btn df-btn--primary" name="add" value="1">Kategori Ekle</button></div>

</form>
</section>

<section class="df-card" style="margin-top:var(--df-space-4)">
<h2 class="df-section-title">Kategori Ağacı</h2>
<div class="df-table-wrap"><table class="df-table">
<thead><tr><th>Kategori</th><th>Üst Kategori</th><th>Ürün</th><th>Durum</th><th>Düzenle</th><th>Sil</th></tr></thead>
<tbody>
<?php foreach($cats as $c): $productCount=safe_count("SELECT COUNT(*) c FROM stock_items WHERE category_id=".(int)$c['id']); ?>
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
<td><?=$productCount?></td>
<td><label style="display:flex;align-items:center;gap:6px"><input type="checkbox" name="active" <?=$c['active']?'checked':''?> style="width:auto"> Aktif</label></td>
<td>
<input type="number" name="sort_order" value="<?=h($c['sort_order'] ?? 0)?>" style="width:80px">
<button class="df-btn df-btn--sm df-btn--secondary" name="update" value="1">Kaydet</button>
</td>
<td>
<?php if(can_edit_delete()): ?>
<form method="post" action="sil.php" style="display:inline" onsubmit="return confirm('Kategori<?=$productCount>0?' '.$productCount.' ürün ile bağlı — ':' '?>silinemez mi?<?=$productCount>0?' (pasife alınacak)':' Emin misiniz?'?>')">
<input type="hidden" name="t" value="product_category">
<input type="hidden" name="id" value="<?=$c['id']?>">
<button class="df-btn df-btn--sm df-btn--danger" type="submit"><?=ds_icon('trash',14)?> Sil</button>
</form>
<?php endif; ?>
</td>
</form>
</tr>
<?php endforeach; ?>
<?php if(!$cats): ?><tr><td colspan="6" class="df-muted">Kategori yok.</td></tr><?php endif; ?>
</tbody>
</table></div>
</section>

<style>
body.nav-compact .df-form-grid-2{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:0 var(--df-space-4)}
body.nav-compact .df-form-span-2{grid-column:1 / -1}
body.nav-compact .df-section-title{font-size:var(--df-type-section-size);font-weight:var(--df-type-section-weight);color:var(--df-ink-900);margin:0 0 var(--df-space-3)}
body.nav-compact .df-muted{color:var(--df-ink-500)}
@media(max-width:640px){body.nav-compact .df-form-grid-2{grid-template-columns:1fr}}
</style>

<?php require_once __DIR__.'/layout_bottom.php'; ?>
