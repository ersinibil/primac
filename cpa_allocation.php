<?php
/* P0 SON KAPANIŞ (2026-07-18) — CPA Miktarsal Tahsis: bir alışın satırlarından müşteriye tahsis
 * oluşturma/azaltma/iptal/aktarım ekranı. purchase.php "Son Alışlar" listesindeki "🎯 Tahsis Et"
 * bağlantısından açılır. Tüm iş mantığı cpa_allocation_lib.php'de — bu dosya sadece form/liste.
 */
require_once __DIR__.'/boot.php';
require_once __DIR__.'/cpa_allocation_lib.php';
require_login();

$pdo=db();
$purchaseId=(int)($_GET['purchase_id'] ?? 0);
$error=''; $ok='';

if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['create_alloc'])){
    try{
        cpa_alloc_create($pdo, $_SESSION['user']['id']??0, $purchaseId, $_POST['stock_item_id']??0, $_POST['customer_id']??0, $_POST['qty']??0, $_POST['notes']??'');
        $_SESSION['cpa_alloc_ok']='Tahsis oluşturuldu.';
    }catch(Throwable $e){ $_SESSION['cpa_alloc_er']=$e->getMessage(); }
    header('Location: cpa_allocation.php?purchase_id='.$purchaseId); exit;
}
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['reduce_alloc'])){
    try{
        cpa_alloc_reduce($pdo, $_SESSION['user']['id']??0, $_POST['alloc_id']??0, $_POST['new_qty']??0);
        $_SESSION['cpa_alloc_ok']='Tahsis miktarı güncellendi.';
    }catch(Throwable $e){ $_SESSION['cpa_alloc_er']=$e->getMessage(); }
    header('Location: cpa_allocation.php?purchase_id='.$purchaseId); exit;
}
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['cancel_alloc'])){
    try{
        cpa_alloc_cancel($pdo, $_SESSION['user']['id']??0, $_POST['alloc_id']??0);
        $_SESSION['cpa_alloc_ok']='Tahsis iptal edildi.';
    }catch(Throwable $e){ $_SESSION['cpa_alloc_er']=$e->getMessage(); }
    header('Location: cpa_allocation.php?purchase_id='.$purchaseId); exit;
}
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['transfer_alloc'])){
    try{
        cpa_alloc_transfer($pdo, $_SESSION['user']['id']??0, $_POST['alloc_id']??0, $_POST['new_customer_id']??0, $_POST['transfer_qty']??0);
        $_SESSION['cpa_alloc_ok']='Tahsis aktarıldı.';
    }catch(Throwable $e){ $_SESSION['cpa_alloc_er']=$e->getMessage(); }
    header('Location: cpa_allocation.php?purchase_id='.$purchaseId); exit;
}

if(!empty($_SESSION['cpa_alloc_ok'])){ $ok=$_SESSION['cpa_alloc_ok']; unset($_SESSION['cpa_alloc_ok']); }
if(!empty($_SESSION['cpa_alloc_er'])){ $error=$_SESSION['cpa_alloc_er']; unset($_SESSION['cpa_alloc_er']); }

$purchase=null;
if($purchaseId){
    $ps=$pdo->prepare("SELECT fm.*, c.name AS supplier_name FROM finance_movements fm LEFT JOIN contacts c ON c.id=fm.contact_id WHERE fm.id=? AND fm.movement_type='purchase'");
    $ps->execute([$purchaseId]);
    $purchase=$ps->fetch();
}
if(!$purchase){
    require_once __DIR__.'/layout_top.php';
    ds_page_header('Tahsis Yönetimi', ds_icon('box',24), '', ds_button('Satın Almaya Dön','purchase.php','secondary','','',true), false, true);
    echo ds_alert('danger','Alış kaydı bulunamadı.');
    require __DIR__.'/layout_bottom.php';
    exit;
}

$canEdit = cpa_alloc_can_edit();
$lineSummary = cpa_alloc_purchase_line_summary($pdo, $purchaseId);
$allocations = cpa_alloc_list_for_purchase($pdo, $purchaseId);

$customers=[];
try{ $customers=$pdo->query("SELECT id,name FROM contacts ORDER BY name")->fetchAll(); }catch(Throwable $e){}

require_once __DIR__.'/layout_top.php';
ds_page_header('🎯 Tahsis Yönetimi — '.h($purchase['supplier_name'] ?: 'Alış #'.$purchaseId), '', h($purchase['movement_date']).' · '.money($purchase['amount']), ds_button('Satın Almaya Dön','purchase.php','secondary','','',true), false, true);
?>

<?php if($error): ?><?=ds_alert('danger',$error)?><?php endif; ?>
<?php if($ok): ?><?=ds_alert('success',$ok)?><?php endif; ?>

<section class="df-card">
<h2 class="df-section-title">Alış Satırları — Tahsis Durumu</h2>
<?php if(!$lineSummary): ?>
<?=ds_empty_state('Bu alışa bağlı stok hareketi bulunamadı.')?>
<?php else: ?>
<div class="df-table-wrap"><table class="df-table">
<thead><tr><th>Ürün</th><th style="text-align:right">Satın Alınan</th><th style="text-align:right">Tahsisli</th><th style="text-align:right">Bu Alıştan Serbest</th></tr></thead>
<tbody>
<?php foreach($lineSummary as $l): ?>
<tr>
<td><b><?=h($l['product_name'])?></b></td>
<td style="text-align:right"><?=stock_qty_fmt($l['purchased_qty'])?> <?=h($l['unit'])?></td>
<td style="text-align:right;color:var(--df-warning-ink)"><?=stock_qty_fmt($l['allocated_from_purchase'])?> <?=h($l['unit'])?></td>
<td style="text-align:right;font-weight:800;color:var(--df-success-ink)"><?=stock_qty_fmt($l['free_on_purchase'])?> <?=h($l['unit'])?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table></div>
<?php endif; ?>
</section>

<?php if($canEdit && $lineSummary): ?>
<section class="df-card" style="margin-top:var(--df-space-4)">
<h2 class="df-section-title">Yeni Tahsis Oluştur</h2>
<form method="post" class="df-form-grid-2">
<input type="hidden" name="create_alloc" value="1">
<?php
$__lineOpts='';
foreach($lineSummary as $l){ $__lineOpts.='<option value="'.(int)$l['stock_item_id'].'">'.h($l['product_name']).' — serbest '.stock_qty_fmt($l['free_on_purchase']).' '.h($l['unit']).'</option>'; }
ds_form_field('Ürün (Bu Alıştan)', '<select name="stock_item_id" required>'.$__lineOpts.'</select>');
$__custOpts='';
foreach($customers as $c){ $__custOpts.='<option value="'.(int)$c['id'].'">'.h($c['name']).'</option>'; }
ds_form_field('Müşteri', '<select name="customer_id" required><option value="">— Seç —</option>'.$__custOpts.'</select>');
ds_form_field('Miktar', '<input type="number" step="0.001" min="0.001" name="qty" required>');
?>
<div class="df-form-span-2"><?php ds_form_field('Not', '<input name="notes" placeholder="Opsiyonel">'); ?></div>
<div class="df-form-span-2"><button class="df-btn df-btn--primary">🎯 Tahsis Et</button></div>
</form>
</section>
<?php endif; ?>

<section class="df-card" style="margin-top:var(--df-space-4)">
<h2 class="df-section-title">Bu Alıştan Yapılan Tahsisler</h2>
<?php if(!$allocations): ?>
<?=ds_empty_state('Henüz tahsis yapılmamış.')?>
<?php else: foreach($allocations as $a):
    $remaining=(float)$a['allocated_qty']-(float)$a['consumed_qty'];
?>
<div class="df-card" style="margin-top:var(--df-space-3);background:var(--df-surface-sunken)">
<div style="display:flex;justify-content:space-between;flex-wrap:wrap;gap:8px;align-items:center">
<div>
<b><?=h($a['customer_name'] ?: '—')?></b> — <?=h($a['product_name'])?>
<div class="df-muted" style="font-size:12px;margin-top:2px">
Tahsis: <?=stock_qty_fmt($a['allocated_qty'])?> <?=h($a['unit'])?> · Tüketilen: <?=stock_qty_fmt($a['consumed_qty'])?> <?=h($a['unit'])?> · Kalan: <b><?=stock_qty_fmt($remaining)?> <?=h($a['unit'])?></b>
<?php if($a['notes']): ?><br><?=h($a['notes'])?><?php endif; ?>
</div>
</div>
<?=ds_badge($a['status'])?>
</div>
<?php if($canEdit && $a['status']!=='İptal'): ?>
<div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:var(--df-space-3)">
<form method="post" style="display:flex;gap:6px;align-items:center;margin:0">
<input type="hidden" name="alloc_id" value="<?=(int)$a['id']?>">
<input type="number" step="0.001" min="0" name="new_qty" value="<?=h($a['allocated_qty'])?>" style="width:110px;margin:0">
<button class="df-btn df-btn--secondary df-btn--sm" type="submit" name="reduce_alloc" value="1">Miktarı Güncelle</button>
</form>
<form method="post" style="display:flex;gap:6px;align-items:center;margin:0">
<input type="hidden" name="alloc_id" value="<?=(int)$a['id']?>">
<select name="new_customer_id" style="width:160px;margin:0"><option value="">— Aktarılacak Müşteri —</option><?php foreach($customers as $c): if((int)$c['id']===(int)$a['customer_id']) continue; ?><option value="<?=(int)$c['id']?>"><?=h($c['name'])?></option><?php endforeach; ?></select>
<input type="number" step="0.001" min="0.001" max="<?=h($remaining)?>" name="transfer_qty" placeholder="Miktar" style="width:90px;margin:0">
<button class="df-btn df-btn--secondary df-btn--sm" type="submit" name="transfer_alloc" value="1">🔄 Aktar</button>
</form>
<form method="post" style="margin:0" onsubmit="return confirm('Bu tahsis iptal edilecek, kalan miktar serbest stoğa dönecek. Emin misiniz?')">
<input type="hidden" name="alloc_id" value="<?=(int)$a['id']?>">
<button class="df-btn df-btn--danger df-btn--sm" type="submit" name="cancel_alloc" value="1">✕ İptal Et</button>
</form>
</div>
<?php endif; ?>
</div>
<?php endforeach; endif; ?>
</section>

<?php require_once __DIR__.'/layout_bottom.php'; ?>
