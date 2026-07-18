<?php
require_once __DIR__.'/boot.php';
require_once __DIR__.'/cpa_lib.php';
require_once __DIR__.'/cpa_allocation_lib.php';
require_login();

$pdo=db();
$id=(int)($_GET['id'] ?? 0);
$error='';
$ok='';

if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['save_product'])){
    if(!user_can('stock')){ http_response_code(403); exit('Yetkiniz yok.'); }
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
    if(!user_can('stock')){ http_response_code(403); exit('Yetkiniz yok.'); }
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

// NOT: stock_movements tablosunda unit_cost/unit_sale/total_cost/total_sale kolonları YOK (gerçek
// şema: id, stock_item_id, job_id, finance_movement_id, direction, quantity, reason, note,
// created_at — bkz. database/migrations/004_stock_products.sql). Satış/kâr özetini ürünün GÜNCEL
// satış/maliyet fiyatı üzerinden, çıkış (direction='out') miktarlarını toplayarak yaklaşık hesaplıyoruz.
$outQty=safe_sum("SELECT COALESCE(SUM(quantity),0) s FROM stock_movements WHERE stock_item_id=$id AND direction='out'");
$unitSaleNow=(float)($p['sale_price'] ?? 0);
$unitCostNow=(float)($p['avg_cost'] ?: $p['purchase_price'] ?: 0);
$sales=$outQty*$unitSaleNow;
$cost=$outQty*$unitCostNow;
$profit=$sales-$cost;
?>

<?php
// RELEASE 0.9 — DS Migration (2026-07-17): render katmanı ds_lib.php desenine taşındı. POST
// handler'lar (yukarısı, user_can('stock') yetki kontrolleri DAHİL) HİÇ değişmedi — sadece HTML/CSS.
$__actions = ds_button('+ Alış / Giriş','stock_movement_new.php?type=in&product_id='.$id,'primary','','',true)
    .ds_button('+ Satış / Çıkış','stock_movement_new.php?type=out&product_id='.$id,'secondary','','',true)
    .ds_button('Stok Listesi','stock.php','secondary','','',true)
    .delete_button('product',$id);
ds_page_header($p['name'], ds_icon('box',24), '', $__actions, false, true);
?>

<?php if($error): ?><?=ds_alert('danger',$error)?><?php endif; ?>
<?php if($ok): ?><?=ds_alert('success',$ok)?><?php endif; ?>

<div class="df-stat-row">
<div class="df-stat"><span>Ürün Kodu</span><strong><?=h($p['product_code'] ?: '-')?></strong></div>
<div class="df-stat"><span>Stok</span><strong><?=h($p['quantity']).' '.h($p['unit'])?></strong></div>
<div class="df-stat"><span>Ortalama Maliyet</span><strong><?=money($p['avg_cost'] ?: $p['purchase_price'])?></strong></div>
<div class="df-stat"><span>Satış Fiyatı</span><strong><?=money($p['sale_price'])?></strong></div>
</div>

<div class="df-stat-row">
<div class="df-stat"><span>Toplam Satış</span><strong><?=money($sales)?></strong></div>
<div class="df-stat"><span>Satış Maliyeti</span><strong><?=money($cost)?></strong></div>
<div class="df-stat"><span>Kâr / Zarar</span><strong><?=money($profit)?></strong></div>
<div class="df-stat"><span>Tedarikçi</span><strong><?=h($p['supplier_name'] ?: '-')?></strong></div>
</div>

<section class="df-card" style="margin-top:var(--df-space-4)">
<h2 class="df-section-title">Ürün Profili</h2>
<form method="post" class="df-form-grid-2">

<input type="hidden" name="save_product" value="1">

<?php ds_form_field('Ürün Kodu', '<input name="product_code" value="'.h($p['product_code'] ?? '').'">'); ?>
<?php ds_form_field('Barkod', '<input name="barcode" value="'.h($p['barcode'] ?? '').'">'); ?>
<?php ds_form_field('Ürün Adı', '<input name="name" required value="'.h($p['name']).'">'); ?>
<?php ds_form_field('Varyant / Çeşit', '<input name="variant_name" value="'.h($p['variant_name'] ?? '').'">'); ?>
<?php ds_form_field('Marka', '<input name="brand" value="'.h($p['brand'] ?? '').'">'); ?>

<?php
$__catOpts='<option value="">Kategori seç</option>';
foreach($categories as $c){ $__catOpts.='<option value="'.(int)$c['id'].'" '.($p['category_id']==$c['id']?'selected':'').'>'.h($c['name']).'</option>'; }
ds_form_field('Kategori', '<select name="category_id">'.$__catOpts.'</select>');
?>

<?php ds_form_field('Birim', '<input name="unit" value="'.h($p['unit']).'">'); ?>
<?php ds_form_field('Kritik Seviye', '<input type="number" step="0.001" name="critical_level" value="'.h($p['critical_level']).'">'); ?>
<?php ds_form_field('Alış Fiyatı', '<input type="number" step="0.01" name="purchase_price" value="'.h($p['purchase_price']).'">'); ?>
<?php ds_form_field('Satış Fiyatı', '<input type="number" step="0.01" name="sale_price" value="'.h($p['sale_price']).'">'); ?>

<?php
$__curOpts='';
foreach(['TRY','USD','EUR'] as $cur){ $__curOpts.='<option '.($p['currency']===$cur?'selected':'').'>'.h($cur).'</option>'; }
ds_form_field('Para Birimi', '<select name="currency">'.$__curOpts.'</select>');
?>

<div class="df-form-span-2">
<?php
$__supOpts='<option value="">Seçiniz</option>';
foreach($suppliers as $s){ $__supOpts.='<option value="'.(int)$s['id'].'" '.($p['default_supplier_id']==$s['id']?'selected':'').'>'.h($s['name']).'</option>'; }
ds_form_field('Varsayılan Tedarikçi', '<select name="default_supplier_id">'.$__supOpts.'</select>');
?>
</div>

<div class="df-form-span-2"><?php ds_form_field('Notlar', '<textarea name="notes" rows="3">'.h($p['notes'] ?? '').'</textarea>'); ?></div>

<div class="df-form-span-2">
<label style="display:flex;align-items:center;gap:8px;font-size:var(--df-type-body-size);color:var(--df-ink-900)">
<input type="checkbox" name="active" <?=$p['active']?'checked':''?> style="width:auto"> Aktif ürün
</label>
</div>

<div class="df-form-span-2"><button class="df-btn df-btn--primary">Ürünü Güncelle</button></div>

</form>
</section>

<section class="df-card" style="margin-top:var(--df-space-4)">
<h2 class="df-section-title">Tedarikçiler</h2>
<form method="post" class="df-form-grid-2" style="margin-bottom:var(--df-space-4)">
<input type="hidden" name="add_supplier" value="1">

<?php
$__supOpts2='<option value="">Seçiniz</option>';
foreach($suppliers as $s){ $__supOpts2.='<option value="'.(int)$s['id'].'">'.h($s['name']).'</option>'; }
ds_form_field('Tedarikçi', '<select name="supplier_id" required>'.$__supOpts2.'</select>');
?>
<?php ds_form_field('Tedarikçi Ürün Kodu', '<input name="supplier_sku">'); ?>
<?php ds_form_field('Son Alış Fiyatı', '<input type="number" step="0.01" name="last_purchase_price" value="0">'); ?>
<?php ds_form_field('Para Birimi', '<select name="currency"><option>TRY</option><option>USD</option><option>EUR</option></select>'); ?>
<?php ds_form_field('Tedarik Süresi / Gün', '<input type="number" name="lead_time_days" value="0">'); ?>

<div class="df-form-span-2">
<label style="display:flex;align-items:center;gap:8px;font-size:var(--df-type-body-size);color:var(--df-ink-900)">
<input type="checkbox" name="is_default" style="width:auto"> Varsayılan tedarikçi yap
</label>
</div>

<div class="df-form-span-2"><button class="df-btn df-btn--secondary">Tedarikçi Ekle</button></div>
</form>

<div class="df-table-wrap"><table class="df-table">
<thead><tr><th>Tedarikçi</th><th>Kod</th><th>Son Alış</th><th>Süre</th><th>Varsayılan</th></tr></thead>
<tbody>
<?php
$ps=$pdo->prepare("SELECT ps.*, c.name supplier_name FROM product_suppliers ps LEFT JOIN contacts c ON c.id=ps.supplier_id WHERE ps.product_id=? ORDER BY ps.is_default DESC, c.name");
$ps->execute([$id]);
$rows=$ps->fetchAll();
foreach($rows as $r){
    echo "<tr><td>".h($r['supplier_name'])."</td><td>".h($r['supplier_sku'])."</td><td>".money($r['last_purchase_price'])."</td><td>".h($r['lead_time_days'])." gün</td><td>".($r['is_default']?ds_badge('Evet','green'):'-')."</td></tr>";
}
if(!$rows) echo "<tr><td colspan='5' class='df-muted'>Tedarikçi tanımlı değil.</td></tr>";
?>
</tbody>
</table></div>
</section>

<?php if(cpa_can_view()): ?>
<section class="df-card" style="margin-top:var(--df-space-4)">
<h2 class="df-section-title">🎯 Tercih Edilen Tedarikçi</h2>
<p class="df-section-hint" style="margin:0 0 var(--df-space-3)">Bu ürün hangi müşteriler için özel tedarikçi tercihi içeriyor — yönetmek için ilgili cari kartındaki "Tercih Edilen Tedarikçiler" bölümü kullanılır.</p>
<div class="df-table-wrap"><table class="df-table">
<thead><tr><th>Müşteri</th><th>Tercih Edilen Tedarikçi</th><th>Öncelik</th><th>Varsayılan</th><th>Durum</th></tr></thead>
<tbody>
<?php
$__cpaUsage = cpa_list_for_product($pdo, $id, true);
foreach($__cpaUsage as $cu){
    echo "<tr>";
    echo "<td><a href='contact_view.php?id=".(int)$cu['customer_id']."'>".h($cu['customer_name'] ?: '#'.$cu['customer_id'])."</a></td>";
    echo "<td>".h($cu['supplier_name'] ?: '#'.$cu['supplier_id'])."</td>";
    echo "<td>".(int)$cu['priority']."</td>";
    echo "<td>".($cu['is_default']?ds_badge('Varsayılan','green'):'-')."</td>";
    echo "<td>".ds_badge($cu['status'])."</td>";
    echo "</tr>";
}
if(!$__cpaUsage) echo "<tr><td colspan='5' class='df-muted'>Bu ürün için tanımlı müşteri-tedarikçi tercihi yok.</td></tr>";
?>
</tbody>
</table></div>
</section>
<?php endif; ?>

<?php if(cpa_alloc_can_view()):
$__free = cpa_alloc_free_stock($pdo, $id);
$__allocUsage = cpa_alloc_list_for_product($pdo, $id, true);
?>
<section class="df-card" style="margin-top:var(--df-space-4)">
<h2 class="df-section-title">📦 Müşteriye Ayrılan / Serbest Stok</h2>
<p class="df-section-hint" style="margin:0 0 var(--df-space-3)">Satın alınan miktarın müşterilere ayrılan (tahsis edilen) kısmı fiziksel stoktan ayrı izlenir — tahsis oluşturmak/yönetmek için ilgili alışın "Tahsis Et" bağlantısı kullanılır.</p>
<div class="df-stat-row" style="margin:0 0 var(--df-space-4)">
<div class="df-stat"><span>Fiziksel Stok</span><strong><?=stock_qty_fmt($__free['physical'])?></strong></div>
<div class="df-stat"><span>Müşteriye Ayrılan</span><strong style="color:var(--df-warning-ink)"><?=stock_qty_fmt($__free['allocated'])?></strong></div>
<div class="df-stat"><span>Serbest Stok</span><strong style="color:var(--df-success-ink)"><?=stock_qty_fmt($__free['free'])?></strong></div>
</div>
<div class="df-table-wrap"><table class="df-table">
<thead><tr><th>Müşteri</th><th style="text-align:right">Tahsis</th><th style="text-align:right">Tüketilen</th><th style="text-align:right">Kalan</th><th>Durum</th><?php if(cpa_alloc_can_edit()): ?><th></th><?php endif; ?></tr></thead>
<tbody>
<?php
$__canSellAlloc = cpa_alloc_can_edit();
foreach($__allocUsage as $au){
    $__rem=(float)$au['allocated_qty']-(float)$au['consumed_qty'];
    echo "<tr>";
    echo "<td><a href='contact_view.php?id=".(int)$au['customer_id']."'>".h($au['customer_name'] ?: '#'.$au['customer_id'])."</a></td>";
    echo "<td style='text-align:right'>".stock_qty_fmt($au['allocated_qty'])."</td>";
    echo "<td style='text-align:right'>".stock_qty_fmt($au['consumed_qty'])."</td>";
    echo "<td style='text-align:right;font-weight:800'>".stock_qty_fmt($__rem)."</td>";
    echo "<td>".ds_badge($au['status'])."</td>";
    if($__canSellAlloc){
        echo "<td class='nowrap'>";
        if($au['status']!=='İptal' && $__rem>0.0000001){
            echo "<a class='df-btn df-btn--primary df-btn--sm' href='sales.php?contact_id=".(int)$au['customer_id']."&stock_item_id=".$id."&qty=".h($__rem)."'>🧾 Sat</a>";
        }
        echo "</td>";
    }
    echo "</tr>";
}
if(!$__allocUsage) echo "<tr><td colspan='".($__canSellAlloc?6:5)."' class='df-muted'>Bu ürün için henüz tahsis yapılmamış.</td></tr>";
?>
</tbody>
</table></div>
</section>
<style>
body.nav-compact .df-stat-row{display:flex;flex-wrap:wrap;gap:var(--df-space-3)}
body.nav-compact .df-stat{flex:1;min-width:140px;background:var(--df-surface);border:1px solid var(--df-hairline);border-radius:var(--df-radius-md);padding:var(--df-space-3);display:flex;flex-direction:column;gap:4px}
body.nav-compact .df-stat span{font-size:var(--df-type-caption-size);color:var(--df-ink-500)}
body.nav-compact .df-stat strong{font-size:var(--df-type-subtitle-size);color:var(--df-ink-900)}
</style>
<?php endif; ?>

<section class="df-card" style="margin-top:var(--df-space-4)">
<h2 class="df-section-title">Stok Hareketleri</h2>
<div class="df-table-wrap"><table class="df-table">
<thead><tr><th>Tarih</th><th>Yön</th><th>Miktar</th><th>Sebep</th><th>Not</th></tr></thead>
<tbody>
<?php
$mv=$pdo->prepare("SELECT * FROM stock_movements WHERE stock_item_id=? ORDER BY id DESC LIMIT 30");
$mv->execute([$id]);
$rows=$mv->fetchAll();
foreach($rows as $r){
    $dirLabel=$r['direction']==='in' ? ds_badge('Giriş','green') : ds_badge('Çıkış','red');
    echo "<tr>";
    echo "<td>".h($r['created_at'])."</td>";
    echo "<td>".$dirLabel."</td>";
    echo "<td>".h($r['quantity'])."</td>";
    echo "<td>".h($r['reason'])."</td>";
    echo "<td>".h($r['note'])."</td>";
    echo "</tr>";
}
if(!$rows) echo "<tr><td colspan='5' class='df-muted'>Hareket yok.</td></tr>";
?>
</tbody>
</table></div>
</section>

<style>
body.nav-compact .df-form-grid-2{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:0 var(--df-space-4)}
body.nav-compact .df-form-span-2{grid-column:1 / -1}
body.nav-compact .df-stat-row{display:flex;flex-wrap:wrap;gap:var(--df-space-3);margin:var(--df-space-4) 0}
body.nav-compact .df-stat{flex:1;min-width:120px;background:var(--df-surface);border:1px solid var(--df-hairline);border-radius:var(--df-radius-md);padding:var(--df-space-3);display:flex;flex-direction:column;gap:4px}
body.nav-compact .df-stat span{font-size:var(--df-type-caption-size);color:var(--df-ink-500)}
body.nav-compact .df-stat strong{font-size:var(--df-type-subtitle-size);color:var(--df-ink-900)}
body.nav-compact .df-section-title{font-size:var(--df-type-section-size);font-weight:var(--df-type-section-weight);color:var(--df-ink-900);margin:0 0 var(--df-space-3)}
body.nav-compact .df-muted{color:var(--df-ink-500)}
@media(max-width:640px){body.nav-compact .df-form-grid-2{grid-template-columns:1fr}}
</style>

<?php require_once __DIR__.'/layout_bottom.php'; ?>
