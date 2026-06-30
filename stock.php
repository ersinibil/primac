<?php
require_once __DIR__.'/layout_top.php';

$categoryId=(int)($_GET['category_id'] ?? 0);
$where=[];
$params=[];
if($categoryId){ $where[]='s.category_id=?'; $params[]=$categoryId; }
$sqlWhere=$where ? 'WHERE '.implode(' AND ',$where) : '';

$categories=db()->query("SELECT * FROM product_categories WHERE active=1 ORDER BY name")->fetchAll();
?>

<div class="panel-head">
<h1>Ürün / Stok Yönetimi</h1>
<div class="actions">
<a class="btn" href="product_new.php">+ Ürün</a>
<a class="btn secondary" href="stock_movement_new.php?type=in">+ Stok Giriş</a>
<a class="btn secondary" href="stock_movement_new.php?type=out">+ Stok Çıkış / Satış</a>
<a class="btn secondary" href="product_categories.php">Kategoriler</a>
</div>
</div>

<section class="command-grid">
<a class="command-card blue" href="stock.php"><small>Ürün</small><strong><?=safe_count("SELECT COUNT(*) c FROM stock_items")?></strong><span>Toplam ürün</span></a>
<a class="command-card red" href="stock.php?critical=1"><small>Kritik</small><strong><?=safe_count("SELECT COUNT(*) c FROM stock_items WHERE quantity <= critical_level")?></strong><span>Kritik stok</span></a>
<a class="command-card green" href="stock_movement_new.php?type=in"><small>Giriş</small><strong>+</strong><span>Alış / tedarik</span></a>
<a class="command-card orange" href="stock_movement_new.php?type=out"><small>Çıkış</small><strong>-</strong><span>Satış / kullanım</span></a>
</section>

<section class="panel">
<form method="get" class="form-grid">
<label>Kategori
<select name="category_id">
<option value="">Tüm Kategoriler</option>
<?php foreach($categories as $c): ?>
<option value="<?=$c['id']?>" <?=$categoryId===(int)$c['id']?'selected':''?>><?=h($c['name'])?></option>
<?php endforeach; ?>
</select>
</label>
<div style="align-self:end"><button class="btn secondary">Filtrele</button></div>
</form>
</section>

<section class="panel">
<table>
<thead>
<tr>
<th>Kod</th>
<th>Ürün</th>
<th>Kategori</th>
<th>Stok</th>
<th>Kritik</th>
<th>Alış</th>
<th>Satış</th>
<th>Kâr</th>
<th>İşlem</th>
</tr>
</thead>
<tbody>
<?php
try{
    $st=db()->prepare("SELECT s.*, c.name category_name
        FROM stock_items s
        LEFT JOIN product_categories c ON c.id=s.category_id
        $sqlWhere
        ORDER BY s.name");
    $st->execute($params);
    $rows=$st->fetchAll();
    foreach($rows as $r){
        $profit=(float)($r['sale_price'] ?? 0)-(float)($r['avg_cost'] ?: ($r['purchase_price'] ?? 0));
        echo "<tr>";
        echo "<td><b>".h($r['product_code'] ?: '-')."</b></td>";
        echo "<td><a href='product_view.php?id=".h($r['id'])."'><b>".h($r['name'])."</b></a><br><span class='muted'>".h($r['variant_name'] ?: $r['brand'])."</span></td>";
        echo "<td>".h($r['category_name'] ?: '-')."</td>";
        echo "<td>".h($r['quantity'])." ".h($r['unit'])."</td>";
        echo "<td>".h($r['critical_level'])." ".h($r['unit'])."</td>";
        echo "<td>".money($r['avg_cost'] ?: $r['purchase_price'])."</td>";
        echo "<td>".money($r['sale_price'])."</td>";
        echo "<td>".money($profit)."</td>";
        echo "<td><a class='btn small secondary' href='product_view.php?id=".h($r['id'])."'>Profil</a></td>";
        echo "</tr>";
    }
    if(!$rows) echo "<tr><td colspan='9' class='muted'>Ürün yok.</td></tr>";
}catch(Throwable $e){
    echo "<tr><td colspan='9'><div class='alert'>".h($e->getMessage())."</div></td></tr>";
}
?>
</tbody>
</table>
</section>

<?php require_once __DIR__.'/layout_bottom.php'; ?>
