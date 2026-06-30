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

<div class="panel-head">
<h1>Yeni Ürün / Stok Kartı</h1>
<div class="actions">
<a class="btn secondary" href="stock.php">Stok Listesi</a>
<a class="btn secondary" href="product_categories.php">+ Kategori</a>
<a class="btn secondary" href="product_taxonomy.php">+ Marka/Birim</a>
</div>
</div>

<?php if($error): ?><div class="alert"><?=h($error)?></div><?php endif; ?>

<section class="panel">
<form method="post" class="form-grid">
<label>Ürün Kodu<input name="product_code" placeholder="Boş bırakılırsa otomatik oluşur"></label>
<label>Barkod<input name="barcode"></label>
<label>Ürün Adı<input name="name" required></label>
<label>Varyant / Çeşit<input name="variant_name"></label>
<label>Marka<select name="brand_id"><option value="">Marka seç</option><?php foreach($brands as $b): ?><option value="<?=$b['id']?>"><?=h($b['name'])?></option><?php endforeach; ?></select></label>
<label>Kategori<select name="category_id"><option value="">Kategori seç</option><?php foreach($categories as $c): ?><option value="<?=$c['id']?>"><?=h(($c['parent_name'] ? $c['parent_name'].' / ' : '').$c['name'])?></option><?php endforeach; ?></select></label>
<label>Birim<select name="unit_id"><option value="">Seç</option><?php foreach($units as $u): ?><option value="<?=$u['id']?>"><?=h($u['name'].' / '.$u['short_name'])?></option><?php endforeach; ?></select></label>
<label>Başlangıç Stok<input type="number" step="0.001" name="quantity" value="0"></label>
<label>Kritik Seviye<input type="number" step="0.001" name="critical_level" value="0"></label>
<label>Maksimum Stok<input type="number" step="0.001" name="max_stock" value="0"></label>
<label>Raf Kodu<input name="shelf_code"></label>
<label>Depo<input name="warehouse"></label>
<label>KDV %<input type="number" step="0.01" name="vat_rate" value="20"></label>
<label>Alış Fiyatı<input type="number" step="0.01" name="purchase_price" value="0"></label>
<label>Satış Fiyatı<input type="number" step="0.01" name="sale_price" value="0"></label>
<label>Para Birimi<select name="currency"><option>TRY</option><option>USD</option><option>EUR</option></select></label>
<label class="full">Varsayılan Tedarikçi<select name="default_supplier_id"><option value="">Seçiniz</option><?php foreach($suppliers as $s): ?><option value="<?=$s['id']?>"><?=h($s['name'])?></option><?php endforeach; ?></select></label>
<label class="full">Notlar<textarea name="notes" rows="3"></textarea></label>
<label class="full"><input type="checkbox" name="active" checked style="width:auto"> Aktif ürün</label>
<button class="btn">Ürünü Kaydet</button>
</form>
</section>
<?php require_once __DIR__.'/layout_bottom.php'; ?>
