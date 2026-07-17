<?php
require_once __DIR__.'/layout_top.php';

$categoryId=(int)($_GET['category_id'] ?? 0);
$pasifDahil=isset($_GET['pasif_dahil']);
$criticalOnly=isset($_GET['critical']);
$where=[];
$params=[];
if($categoryId){ $where[]='s.category_id=?'; $params[]=$categoryId; }
if(!$pasifDahil){ $where[]='(s.active IS NULL OR s.active=1)'; }
if($criticalOnly){ $where[]='s.quantity<=s.critical_level'; }
$sqlWhere=$where ? 'WHERE '.implode(' AND ',$where) : '';

$categories=db()->query("SELECT * FROM product_categories WHERE active=1 ORDER BY name")->fetchAll();
?>

<?php
$__stockActions = ds_button('Ürün','product_new.php','primary','','',true)
    . ds_button('Stok Giriş','stock_movement_new.php?type=in','secondary','','',true)
    . ds_button('Stok Çıkış / Satış','stock_movement_new.php?type=out','secondary','','',true)
    . ds_button('Kategoriler','product_categories.php','secondary','','',true);
ds_page_header('Ürün / Stok Yönetimi', ds_icon('box',24), '', $__stockActions, false, true);
?>

<section class="crm-tabs" style="grid-template-columns:repeat(4,minmax(160px,1fr))">
<a class="crm-card crm-blue" href="stock.php"><small>Ürün</small><strong><?=safe_count("SELECT COUNT(*) c FROM stock_items")?></strong><span>Toplam ürün</span></a>
<a class="crm-card" style="background:linear-gradient(135deg,#fee2e2,#fef2f2)" href="stock.php?critical=1"><small>Kritik</small><strong><?=safe_count("SELECT COUNT(*) c FROM stock_items WHERE quantity <= critical_level")?></strong><span>Kritik stok</span></a>
<a class="crm-card crm-green" href="stock_movement_new.php?type=in"><small>Giriş</small><strong>+</strong><span>Alış / tedarik</span></a>
<a class="crm-card crm-orange" href="stock_movement_new.php?type=out"><small>Çıkış</small><strong>-</strong><span>Satış / kullanım</span></a>
</section>

<section class="df-card">
<form method="get" class="df-form-grid-2">
<?php
$__catOpts='<option value="">Tüm Kategoriler</option>';
foreach($categories as $c){ $__catOpts.='<option value="'.$c['id'].'" '.($categoryId===(int)$c['id']?'selected':'').'>'.h($c['name']).'</option>'; }
ds_form_field('Kategori', '<select name="category_id">'.$__catOpts.'</select>');
?>
<div style="display:flex;gap:var(--df-space-4);align-items:center">
<label style="display:flex;align-items:center;gap:6px"><input type="checkbox" name="pasif_dahil" value="1" <?=$pasifDahil?'checked':''?> style="width:auto"> Pasif Dahil</label>
<label style="display:flex;align-items:center;gap:6px"><input type="checkbox" name="critical" value="1" <?=$criticalOnly?'checked':''?> style="width:auto"> Sadece Kritik Stok</label>
<button class="df-btn df-btn--secondary">Filtrele</button>
</div>
</form>
</section>

<section class="df-card" style="margin-top:var(--df-space-4)">
<div class="df-table-wrap"><table class="df-table">
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
        $isPasif=isset($r['active']) && !$r['active'];
        $rowStyle=$isPasif?' style="opacity:.45"':'';
        echo "<tr$rowStyle>";
        echo "<td><b>".h($r['product_code'] ?: '-')."</b></td>";
        echo "<td><a href='product_view.php?id=".h($r['id'])."'><b>".h($r['name'])."</b></a>".($isPasif?" <span class='df-badge df-badge--danger'>Pasif</span>":'')."<br><span style='color:var(--df-ink-500)'>".h($r['variant_name'] ?: $r['brand'])."</span></td>";
        echo "<td>".h($r['category_name'] ?: '-')."</td>";
        echo "<td>".h($r['quantity'])." ".h($r['unit'])."</td>";
        echo "<td>".h($r['critical_level'])." ".h($r['unit'])."</td>";
        echo "<td>".money($r['avg_cost'] ?: $r['purchase_price'])."</td>";
        echo "<td>".money($r['sale_price'])."</td>";
        echo "<td>".money($profit)."</td>";
        echo "<td><a class='df-btn df-btn--secondary df-btn--sm' href='product_view.php?id=".h($r['id'])."'>Profil</a></td>";
        echo "</tr>";
    }
    if(!$rows) echo "<tr><td colspan='9' style='color:var(--df-ink-500)'>Ürün yok.</td></tr>";
}catch(Throwable $e){
    echo "<tr><td colspan='9'>".ds_alert('danger',$e->getMessage())."</td></tr>";
}
?>
</tbody>
</table></div>
</section>

<style>
body.nav-compact .df-form-grid-2{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:0 var(--df-space-4);align-items:end}
@media(max-width:640px){body.nav-compact .df-form-grid-2{grid-template-columns:1fr}}
.crm-tabs{display:grid;gap:var(--df-space-4);margin:var(--df-space-4) 0 var(--df-space-5)}
.crm-card{display:block;text-decoration:none;border-radius:var(--df-radius-lg);padding:var(--df-space-4);box-shadow:var(--df-elevation-raised);border:1px solid var(--df-hairline);color:var(--df-ink-900)}
.crm-card small{display:block;color:var(--df-ink-600);font-weight:900;margin-bottom:6px}.crm-card strong{display:block;font-size:26px;line-height:1;margin-bottom:8px}.crm-card span{display:block;color:var(--df-ink-500);font-size:13px}
.crm-blue{background:linear-gradient(135deg,#dbeafe,#eff6ff)}.crm-green{background:linear-gradient(135deg,#dcfce7,#f0fdf4)}.crm-orange{background:linear-gradient(135deg,#ffedd5,#fff7ed)}
@media(max-width:960px){.crm-tabs{grid-template-columns:1fr!important}}
</style>

<?php require_once __DIR__.'/layout_bottom.php'; ?>
