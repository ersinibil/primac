<?php
require_once __DIR__.'/boot.php';
require_login();
require_once __DIR__.'/activity_lib.php';

$pdo=db();
$error='';

if($_SERVER['REQUEST_METHOD']==='POST'){
    try{
        $code=trim($_POST['product_code']);
        if($code==='') $code='URN-'.date('ymd').'-'.random_int(100,999);

        $brandName='';
        if(!empty($_POST['brand_id'])){
            $bs=$pdo->prepare("SELECT name FROM product_brands WHERE id=?");
            $bs->execute([(int)$_POST['brand_id']]);
            $brandName=(string)($bs->fetch()['name'] ?? '');
        }

        $unitShort='adet';
        if(!empty($_POST['unit_id'])){
            $us=$pdo->prepare("SELECT short_name FROM product_units WHERE id=?");
            $us->execute([(int)$_POST['unit_id']]);
            $unitShort=(string)($us->fetch()['short_name'] ?? 'adet');
        }

        $stmt=$pdo->prepare("INSERT INTO stock_items(
            product_code,barcode,name,variant_name,brand,brand_id,category_id,unit,unit_id,quantity,critical_level,max_stock,
            purchase_price,sale_price,avg_cost,last_purchase_price,currency,default_supplier_id,active,vat_rate,shelf_code,warehouse,notes
        ) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
        $stmt->execute([
            $code, trim($_POST['barcode']), trim($_POST['name']), trim($_POST['variant_name']),
            $brandName, (int)$_POST['brand_id'] ?: null, (int)$_POST['category_id'] ?: null,
            $unitShort, (int)$_POST['unit_id'] ?: null, (float)$_POST['quantity'], (float)$_POST['critical_level'],
            (float)$_POST['max_stock'], (float)$_POST['purchase_price'], (float)$_POST['sale_price'],
            (float)$_POST['purchase_price'], (float)$_POST['purchase_price'], $_POST['currency'],
            (int)$_POST['default_supplier_id'] ?: null, isset($_POST['active'])?1:0,
            (float)$_POST['vat_rate'], trim($_POST['shelf_code']), trim($_POST['warehouse']), trim($_POST['notes'])
        ]);
        $id=$pdo->lastInsertId();

        activity_log('Stok','Yeni Ürün','Yeni ürün oluşturuldu',$code.' · '.trim($_POST['name']),'product',$id,'product_view.php?id='.$id,'📦');

        header("Location: product_view.php?id=".$id);
        exit;
    }catch(Throwable $e){ $error=$e->getMessage(); }
}

require_once __DIR__.'/layout_top.php';
$categories=$pdo->query("SELECT c.*, p.name parent_name FROM product_categories c LEFT JOIN product_categories p ON p.id=c.parent_id WHERE c.active=1 ORDER BY COALESCE(p.name,c.name), c.parent_id IS NOT NULL, c.name")->fetchAll();
$brands=$pdo->query("SELECT * FROM product_brands WHERE active=1 ORDER BY name")->fetchAll();
$units=$pdo->query("SELECT * FROM product_units WHERE active=1 ORDER BY name")->fetchAll();
$suppliers=$pdo->query("SELECT * FROM contacts WHERE type IN ('Tedarikçi','Her İkisi') ORDER BY name")->fetchAll();
?>

<?php
// RELEASE 0.9 — DS Migration (2026-07-17): render katmanı ds_lib.php desenine taşındı, POST/iş
// mantığı (yukarısı) HİÇ değişmedi — sadece HTML/CSS.
$__actions = ds_button('Stok Listesi','stock.php','secondary','','',true)
    .ds_button('+ Kategori','product_categories.php','secondary','','',true)
    .ds_button('+ Marka/Birim','product_taxonomy.php','secondary','','',true);
ds_page_header('Yeni Ürün / Stok Kartı', ds_icon('box',24), '', $__actions, false, true);
?>

<?php if($error): ?><?=ds_alert('danger',$error)?><?php endif; ?>

<section class="df-card">
<form method="post" class="df-form-grid-2">

<?php ds_form_field('Ürün Kodu', '<input name="product_code" placeholder="Boş bırakılırsa otomatik oluşur">'); ?>
<?php ds_form_field('Barkod', '<input name="barcode">'); ?>
<?php ds_form_field('Ürün Adı', '<input name="name" required>'); ?>
<?php ds_form_field('Varyant / Çeşit', '<input name="variant_name">'); ?>

<?php
$__brandOpts='<option value="">Marka seç</option>';
foreach($brands as $b){ $__brandOpts.='<option value="'.(int)$b['id'].'">'.h($b['name']).'</option>'; }
ds_form_field('Marka', '<select name="brand_id">'.$__brandOpts.'</select>');

$__catOpts='<option value="">Kategori seç</option>';
foreach($categories as $c){ $__catOpts.='<option value="'.(int)$c['id'].'">'.h(($c['parent_name'] ? $c['parent_name'].' / ' : '').$c['name']).'</option>'; }
ds_form_field('Kategori', '<select name="category_id">'.$__catOpts.'</select>');

$__unitOpts='<option value="">Seç</option>';
foreach($units as $u){ $__unitOpts.='<option value="'.(int)$u['id'].'">'.h($u['name'].' / '.$u['short_name']).'</option>'; }
ds_form_field('Birim', '<select name="unit_id">'.$__unitOpts.'</select>');
?>

<?php ds_form_field('Başlangıç Stok', '<input type="number" step="0.001" name="quantity" value="0">'); ?>
<?php ds_form_field('Kritik Seviye', '<input type="number" step="0.001" name="critical_level" value="0">'); ?>
<?php ds_form_field('Maksimum Stok', '<input type="number" step="0.001" name="max_stock" value="0">'); ?>
<?php ds_form_field('Raf Kodu', '<input name="shelf_code">'); ?>
<?php ds_form_field('Depo', '<input name="warehouse">'); ?>
<?php ds_form_field('KDV %', '<input type="number" step="0.01" name="vat_rate" value="20">'); ?>
<?php ds_form_field('Alış Fiyatı', '<input type="number" step="0.01" name="purchase_price" value="0">'); ?>
<?php ds_form_field('Satış Fiyatı', '<input type="number" step="0.01" name="sale_price" value="0">'); ?>
<?php ds_form_field('Para Birimi', '<select name="currency"><option>TRY</option><option>USD</option><option>EUR</option></select>'); ?>

<div class="df-form-span-2">
<?php
$__supOpts='<option value="">Seçiniz</option>';
foreach($suppliers as $s){ $__supOpts.='<option value="'.(int)$s['id'].'">'.h($s['name']).'</option>'; }
ds_form_field('Varsayılan Tedarikçi', '<select name="default_supplier_id">'.$__supOpts.'</select>');
?>
</div>
<div class="df-form-span-2"><?php ds_form_field('Notlar', '<textarea name="notes" rows="3"></textarea>'); ?></div>

<div class="df-form-span-2">
<label style="display:flex;align-items:center;gap:8px;font-size:var(--df-type-body-size);color:var(--df-ink-900)">
<input type="checkbox" name="active" checked style="width:auto"> Aktif ürün
</label>
</div>

<div class="df-form-span-2"><button class="df-btn df-btn--primary">Ürünü Kaydet</button></div>

</form>
</section>

<style>
body.nav-compact .df-form-grid-2{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:0 var(--df-space-4)}
body.nav-compact .df-form-span-2{grid-column:1 / -1}
@media(max-width:640px){body.nav-compact .df-form-grid-2{grid-template-columns:1fr}}
</style>

<?php require_once __DIR__.'/layout_bottom.php'; ?>
