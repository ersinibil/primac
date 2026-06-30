<?php
require_once __DIR__.'/boot.php';
require_login();

$pdo=db();
$id=(int)($_GET['id'] ?? 0);
$error='';
$ok='';

if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['save_product'])){
    try{
        $stmt=$pdo->prepare("UPDATE stock_items SET
            product_code=?, barcode=?, name=?, variant_name=?, brand=?, category_id=?, unit=?, critical_level=?,
            purchase_price=?, sale_price=?, currency=?, default_supplier_id=?, active=?, notes=?
            WHERE id=?
        ");
        $stmt->execute([
            trim($_POST['product_code']),
            trim($_POST['barcode']),
            trim($_POST['name']),
            trim($_POST['variant_name']),
            trim($_POST['brand']),
            (int)$_POST['category_id'] ?: null,
            trim($_POST['unit']),
            (float)$_POST['critical_level'],
            (float)$_POST['purchase_price'],
            (float)$_POST['sale_price'],
            $_POST['currency'],
            (int)$_POST['default_supplier_id'] ?: null,
            isset($_POST['active'])?1:0,
            trim($_POST['notes']),
            $id
        ]);
        $ok='Ürün kartı güncellendi.';
        if(function_exists('activity_log')) activity_log('Stok','Ürün Düzenleme',trim($_POST['name']),'','product',$id,'product_view.php?id='.$id,'✏️');
    }catch(Throwable $e){
        $error=$e->getMessage();
    }
}

if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['add_supplier'])){
    try{
        $pdo->prepare("INSERT INTO product_suppliers(product_id,supplier_id,supplier_sku,last_purchase_price,currency,lead_time_days,is_default)
            VALUES(?,?,?,?,?,?,?)")
            ->execute([$id,(int)$_POST['supplier_id'],trim($_POST['supplier_sku']),(float)$_POST['last_purchase_price'],$_POST['currency'],(int)$_POST['lead_time_days'],isset($_POST['is_default'])?1:0]);
        if(isset($_POST['is_default'])){
            $pdo->prepare("UPDATE stock_items SET default_supplier_id=?, last_purchase_price=? WHERE id=?")
                ->execute([(int)$_POST['supplier_id'],(float)$_POST['last_purchase_price'],$id]);
        }
        $ok='Tedarikçi eklendi.';
        if(function_exists('activity_log')) activity_log('Stok','Tedarikçi Ekleme','Ürün #'.$id,'','product',$id,'product_view.php?id='.$id,'🏷️');
    }catch(Throwable $e){
        $error=$e->getMessage();
    }
}

$stmt=$pdo->prepare("SELECT s.*, c.name category_name, sup.name supplier_name
    FROM stock_items s
    LEFT JOIN product_categories c ON c.id=s.category_id
    LEFT JOIN contacts sup ON sup.id=s.default_supplier_id
    WHERE s.id=?");
$stmt->execute([$id]);
$p=$stmt->fetch();

require_once __DIR__.'/layout_top.php';

if(!$p){
    echo "<h1>Ürün bulunamadı</h1>";
    require __DIR__.'/layout_bottom.php';
    exit;
}

$categories=$pdo->query("SELECT * FROM product_categories WHERE active=1 ORDER BY name")->fetchAll();
$suppliers=$pdo->query("SELECT * FROM contacts WHERE type IN ('Tedarikçi','Her İkisi') ORDER BY name")->fetchAll();

$sales=safe_sum("SELECT COALESCE(SUM(total_sale),0) s FROM stock_movements WHERE stock_item_id=$id AND movement_type IN ('out','sale')");
$cost=safe_sum("SELECT COALESCE(SUM(total_cost),0) s FROM stock_movements WHERE stock_item_id=$id AND movement_type IN ('out','sale')");
$profit=$sales-$cost;
?>

<div class="panel-head">
<h1><?=h($p['name'])?></h1>
<div class="actions">
<a class="btn" href="stock_movement_new.php?type=in&product_id=<?=$id?>">+ Alış / Giriş</a>
<a class="btn secondary" href="stock_movement_new.php?type=out&product_id=<?=$id?>">+ Satış / Çıkış</a>
<a class="btn secondary" href="stock.php">Stok Listesi</a>
</div>
</div>

<?php if($error): ?><div class="alert"><?=h($error)?></div><?php endif; ?>
<?php if($ok): ?><div class="ok"><?=h($ok)?></div><?php endif; ?>

<div class="cards">
<div class="card"><small>Ürün Kodu</small><strong><?=h($p['product_code'] ?: '-')?></strong></div>
<div class="card"><small>Stok</small><strong><?=h($p['quantity']).' '.h($p['unit'])?></strong></div>
<div class="card"><small>Ortalama Maliyet</small><strong><?=money($p['avg_cost'] ?: $p['purchase_price'])?></strong></div>
<div class="card"><small>Satış Fiyatı</small><strong><?=money($p['sale_price'])?></strong></div>
</div>

<div class="cards">
<div class="card"><small>Toplam Satış</small><strong><?=money($sales)?></strong></div>
<div class="card"><small>Satış Maliyeti</small><strong><?=money($cost)?></strong></div>
<div class="card"><small>Kâr / Zarar</small><strong><?=money($profit)?></strong></div>
<div class="card"><small>Tedarikçi</small><strong><?=h($p['supplier_name'] ?: '-')?></strong></div>
</div>

<section class="panel">
<h2>Ürün Profili</h2>
<form method="post" class="form-grid">

<input type="hidden" name="save_product" value="1">

<label>Ürün Kodu
<input name="product_code" value="<?=h($p['product_code'] ?? '')?>">
</label>

<label>Barkod
<input name="barcode" value="<?=h($p['barcode'] ?? '')?>">
</label>

<label>Ürün Adı
<input name="name" required value="<?=h($p['name'])?>">
</label>

<label>Varyant / Çeşit
<input name="variant_name" value="<?=h($p['variant_name'] ?? '')?>">
</label>

<label>Marka
<input name="brand" value="<?=h($p['brand'] ?? '')?>">
</label>

<label>Kategori
<select name="category_id">
<option value="">Kategori seç</option>
<?php foreach($categories as $c): ?>
<option value="<?=$c['id']?>" <?=$p['category_id']==$c['id']?'selected':''?>><?=h($c['name'])?></option>
<?php endforeach; ?>
</select>
</label>

<label>Birim
<input name="unit" value="<?=h($p['unit'])?>">
</label>

<label>Kritik Seviye
<input type="number" step="0.001" name="critical_level" value="<?=h($p['critical_level'])?>">
</label>

<label>Alış Fiyatı
<input type="number" step="0.01" name="purchase_price" value="<?=h($p['purchase_price'])?>">
</label>

<label>Satış Fiyatı
<input type="number" step="0.01" name="sale_price" value="<?=h($p['sale_price'])?>">
</label>

<label>Para Birimi
<select name="currency">
<?php foreach(['TRY','USD','EUR'] as $cur): ?>
<option <?=$p['currency']===$cur?'selected':''?>><?=$cur?></option>
<?php endforeach; ?>
</select>
</label>

<label>Varsayılan Tedarikçi
<select name="default_supplier_id">
<option value="">Seçiniz</option>
<?php foreach($suppliers as $s): ?>
<option value="<?=$s['id']?>" <?=$p['default_supplier_id']==$s['id']?'selected':''?>><?=h($s['name'])?></option>
<?php endforeach; ?>
</select>
</label>

<label class="full">Notlar
<textarea name="notes" rows="3"><?=h($p['notes'] ?? '')?></textarea>
</label>

<label class="full"><input type="checkbox" name="active" <?=$p['active']?'checked':''?> style="width:auto"> Aktif ürün</label>

<button class="btn">Ürünü Güncelle</button>

</form>
</section>

<section class="panel">
<h2>Tedarikçiler</h2>
<form method="post" class="form-grid">
<input type="hidden" name="add_supplier" value="1">

<label>Tedarikçi
<select name="supplier_id" required>
<option value="">Seçiniz</option>
<?php foreach($suppliers as $s): ?>
<option value="<?=$s['id']?>"><?=h($s['name'])?></option>
<?php endforeach; ?>
</select>
</label>

<label>Tedarikçi Ürün Kodu
<input name="supplier_sku">
</label>

<label>Son Alış Fiyatı
<input type="number" step="0.01" name="last_purchase_price" value="0">
</label>

<label>Para Birimi
<select name="currency"><option>TRY</option><option>USD</option><option>EUR</option></select>
</label>

<label>Tedarik Süresi / Gün
<input type="number" name="lead_time_days" value="0">
</label>

<label class="full"><input type="checkbox" name="is_default" style="width:auto"> Varsayılan tedarikçi yap</label>

<button class="btn secondary">Tedarikçi Ekle</button>
</form>

<table>
<thead><tr><th>Tedarikçi</th><th>Kod</th><th>Son Alış</th><th>Süre</th><th>Varsayılan</th></tr></thead>
<tbody>
<?php
$ps=$pdo->prepare("SELECT ps.*, c.name supplier_name FROM product_suppliers ps LEFT JOIN contacts c ON c.id=ps.supplier_id WHERE ps.product_id=? ORDER BY ps.is_default DESC, c.name");
$ps->execute([$id]);
$rows=$ps->fetchAll();
foreach($rows as $r){
    echo "<tr><td>".h($r['supplier_name'])."</td><td>".h($r['supplier_sku'])."</td><td>".money($r['last_purchase_price'])."</td><td>".h($r['lead_time_days'])." gün</td><td>".($r['is_default']?badge('Evet','green'):'-')."</td></tr>";
}
if(!$rows) echo "<tr><td colspan='5' class='muted'>Tedarikçi tanımlı değil.</td></tr>";
?>
</tbody>
</table>
</section>

<section class="panel">
<h2>Stok Hareketleri</h2>
<table>
<thead><tr><th>Tarih</th><th>Tip</th><th>Miktar</th><th>Birim Maliyet</th><th>Birim Satış</th><th>Kâr</th><th>Açıklama</th></tr></thead>
<tbody>
<?php
$mv=$pdo->prepare("SELECT * FROM stock_movements WHERE stock_item_id=? ORDER BY id DESC LIMIT 30");
$mv->execute([$id]);
$rows=$mv->fetchAll();
foreach($rows as $r){
    $rowProfit=(float)$r['total_sale']-(float)$r['total_cost'];
    echo "<tr>";
    echo "<td>".h($r['movement_date'])."</td>";
    echo "<td>".h($r['movement_type'])."</td>";
    echo "<td>".h($r['quantity'])."</td>";
    echo "<td>".money($r['unit_cost'])."</td>";
    echo "<td>".money($r['unit_sale'])."</td>";
    echo "<td>".money($rowProfit)."</td>";
    echo "<td>".h($r['description'])."</td>";
    echo "</tr>";
}
if(!$rows) echo "<tr><td colspan='7' class='muted'>Hareket yok.</td></tr>";
?>
</tbody>
</table>
</section>

<?php require_once __DIR__.'/layout_bottom.php'; ?>
