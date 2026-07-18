<?php
/* P0 SON KAPANIŞ (2026-07-18) — CPA Miktarsal Tahsis (mobil). Web cpa_allocation.php ile BİREBİR
 * aynı iş mantığı (cpa_allocation_lib.php), sadece mobil sunum. mobile/purchase.php "Son Alışlar"
 * listesindeki "🎯 Tahsis Et" bağlantısından açılır. POST önce işlenir, sonra redirect (PRG).
 */
require_once 'common.php';
require_once dirname(__DIR__).'/cpa_allocation_lib.php';
$pdo=db();

$purchaseId=(int)($_GET['purchase_id'] ?? 0);

if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['create_alloc'])){
    try{
        cpa_alloc_create($pdo, $u['id']??0, $purchaseId, $_POST['stock_item_id']??0, $_POST['customer_id']??0, $_POST['qty']??0, $_POST['notes']??'');
        $_SESSION['cpa_alloc_ok']='Müşteriye ayrıldı.';
    }catch(Throwable $e){ $_SESSION['cpa_alloc_er']=$e->getMessage(); }
    header('Location: cpa_allocation.php?purchase_id='.$purchaseId); exit;
}
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['reduce_alloc'])){
    try{
        cpa_alloc_reduce($pdo, $u['id']??0, $_POST['alloc_id']??0, $_POST['new_qty']??0);
        $_SESSION['cpa_alloc_ok']='Ayrılan miktar güncellendi.';
    }catch(Throwable $e){ $_SESSION['cpa_alloc_er']=$e->getMessage(); }
    header('Location: cpa_allocation.php?purchase_id='.$purchaseId); exit;
}
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['cancel_alloc'])){
    try{
        cpa_alloc_cancel($pdo, $u['id']??0, $_POST['alloc_id']??0);
        $_SESSION['cpa_alloc_ok']='Ayırma iptal edildi.';
    }catch(Throwable $e){ $_SESSION['cpa_alloc_er']=$e->getMessage(); }
    header('Location: cpa_allocation.php?purchase_id='.$purchaseId); exit;
}
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['transfer_alloc'])){
    try{
        cpa_alloc_transfer($pdo, $u['id']??0, $_POST['alloc_id']??0, $_POST['new_customer_id']??0, $_POST['transfer_qty']??0);
        $_SESSION['cpa_alloc_ok']='Başka müşteriye aktarıldı.';
    }catch(Throwable $e){ $_SESSION['cpa_alloc_er']=$e->getMessage(); }
    header('Location: cpa_allocation.php?purchase_id='.$purchaseId); exit;
}

$purchase=null;
if($purchaseId){
    $ps=$pdo->prepare("SELECT fm.*, c.name AS supplier_name FROM finance_movements fm LEFT JOIN contacts c ON c.id=fm.contact_id WHERE fm.id=? AND fm.movement_type='purchase'");
    $ps->execute([$purchaseId]);
    $purchase=$ps->fetch();
}

topx('Müşteriye Ayır');
$__preselectItem = (int)($_GET['stock_item_id'] ?? 0);
if(!empty($_SESSION['cpa_alloc_ok'])){ echo ds_alert('success',$_SESSION['cpa_alloc_ok']); unset($_SESSION['cpa_alloc_ok']); }
if(!empty($_SESSION['cpa_alloc_er'])){ echo ds_alert('danger',$_SESSION['cpa_alloc_er']); unset($_SESSION['cpa_alloc_er']); }

if(!$purchase){
    echo ds_alert('danger','Alış kaydı bulunamadı.');
    botx();
    exit;
}

$canEdit = cpa_alloc_can_edit();
$lineSummary = cpa_alloc_purchase_line_summary($pdo, $purchaseId);
$allocations = cpa_alloc_list_for_purchase($pdo, $purchaseId);
$customers=[];
try{ $customers=$pdo->query("SELECT id,name FROM contacts ORDER BY name")->fetchAll(); }catch(Throwable $e){}
?>

<div class="df-panel">
  <b><?=h($purchase['supplier_name'] ?: 'Alış #'.$purchaseId)?></b>
  <div class="df-list-row-meta" style="margin-top:4px"><span><?=h($purchase['movement_date'])?></span><span><?=mm($purchase['amount'])?></span></div>
</div>

<div class="df-panel" style="margin-top:12px">
  <b><?=ds_icon('box',16)?> Alış Satırları — Müşteriye Ayrılan</b>
  <?php if(!$lineSummary): ?>
  <?php ds_empty_state('Bu alışa bağlı stok hareketi bulunamadı.'); ?>
  <?php else: foreach($lineSummary as $l): ?>
  <div class="df-panel" style="margin-top:10px;background:var(--df-surface-sunken,rgba(255,255,255,.06))">
    <b><?=h($l['product_name'])?></b>
    <div class="df-list-row-meta" style="margin-top:6px">
      <span>Alınan: <?=stock_qty_fmt($l['purchased_qty'])?> <?=h($l['unit'])?></span>
      <span>Müşteriye Ayrılan: <?=stock_qty_fmt($l['allocated_from_purchase'])?> <?=h($l['unit'])?></span>
    </div>
    <div style="margin-top:4px;font-weight:800;color:var(--df-success-ink)">Serbest: <?=stock_qty_fmt($l['free_on_purchase'])?> <?=h($l['unit'])?></div>
  </div>
  <?php endforeach; endif; ?>
</div>

<?php if($canEdit && $lineSummary): ?>
<details class="df-panel" style="margin-top:12px" <?=$__preselectItem?'open':''?>><summary style="font-weight:900;cursor:pointer"><?=ds_icon('plus',14)?> Müşteriye Ayır</summary>
<form method="post" style="margin-top:10px">
<input type="hidden" name="create_alloc" value="1">
<label>Ürün (Bu Alıştan)</label>
<select name="stock_item_id" required>
<?php foreach($lineSummary as $l): ?>
<option value="<?=(int)$l['stock_item_id']?>" <?=$__preselectItem===(int)$l['stock_item_id']?'selected':''?>><?=h($l['product_name'])?> — serbest <?=stock_qty_fmt($l['free_on_purchase'])?> <?=h($l['unit'])?></option>
<?php endforeach; ?>
</select>
<label>Müşteri</label>
<select name="customer_id" required>
<option value="">— Seç —</option>
<?php foreach($customers as $c): ?><option value="<?=(int)$c['id']?>"><?=h($c['name'])?></option><?php endforeach; ?>
</select>
<label>Miktar</label>
<input type="number" step="0.001" min="0.001" name="qty" required>
<label>Not <small class="muted">(opsiyonel)</small></label>
<input name="notes">
<button class="df-btn df-btn--primary df-btn--lg" style="width:100%;margin-top:8px">🤝 Müşteriye Ayır</button>
</form>
</details>
<?php endif; ?>

<div class="df-panel" style="margin-top:12px">
<b><?=ds_icon('info',16)?> Bu Alıştan Ayrılanlar</b>
<?php if(!$allocations): ?>
<?php ds_empty_state('Henüz müşteriye ayrılmamış.'); ?>
<?php else: foreach($allocations as $a):
    $remaining=(float)$a['allocated_qty']-(float)$a['consumed_qty'];
?>
<div class="df-panel" style="margin-top:10px;background:var(--df-surface-sunken,rgba(255,255,255,.06))">
  <div style="display:flex;justify-content:space-between;gap:8px;align-items:flex-start">
    <b><?=h($a['customer_name'] ?: '—')?></b>
    <?=ds_badge($a['status'])?>
  </div>
  <div class="df-list-row-desc" style="margin-top:4px"><?=h($a['product_name'])?></div>
  <div class="df-list-row-meta" style="margin-top:4px">
    <span>Ayrılan: <?=stock_qty_fmt($a['allocated_qty'])?> <?=h($a['unit'])?></span>
    <span>Tüketilen: <?=stock_qty_fmt($a['consumed_qty'])?> <?=h($a['unit'])?></span>
  </div>
  <div style="margin-top:4px;font-weight:800">Kalan: <?=stock_qty_fmt($remaining)?> <?=h($a['unit'])?></div>
  <?php if($a['notes']): ?><div class="df-list-row-desc" style="margin-top:4px"><?=h($a['notes'])?></div><?php endif; ?>
  <?php if($canEdit && $a['status']!=='İptal'): ?>
  <form method="post" style="display:flex;gap:6px;align-items:center;margin-top:10px">
    <input type="hidden" name="alloc_id" value="<?=(int)$a['id']?>">
    <input type="number" step="0.001" min="0" name="new_qty" value="<?=h($a['allocated_qty'])?>" style="flex:1;margin:0">
    <button class="df-btn df-btn--secondary df-btn--sm" type="submit" name="reduce_alloc" value="1">Güncelle</button>
  </form>
  <form method="post" style="display:flex;gap:6px;align-items:center;margin-top:6px">
    <input type="hidden" name="alloc_id" value="<?=(int)$a['id']?>">
    <select name="new_customer_id" style="flex:1;margin:0"><option value="">— Aktarılacak Müşteri —</option><?php foreach($customers as $c): if((int)$c['id']===(int)$a['customer_id']) continue; ?><option value="<?=(int)$c['id']?>"><?=h($c['name'])?></option><?php endforeach; ?></select>
    <input type="number" step="0.001" min="0.001" max="<?=h($remaining)?>" name="transfer_qty" placeholder="Miktar" style="width:80px;margin:0">
    <button class="df-btn df-btn--secondary df-btn--sm" type="submit" name="transfer_alloc" value="1">🔄</button>
  </form>
  <form method="post" style="margin-top:6px" onsubmit="return confirm('Bu tahsis iptal edilecek, kalan miktar serbest stoğa dönecek. Emin misiniz?')">
    <input type="hidden" name="alloc_id" value="<?=(int)$a['id']?>">
    <button class="df-btn df-btn--danger df-btn--sm" type="submit" name="cancel_alloc" value="1" style="width:100%">✕ İptal Et</button>
  </form>
  <?php endif; ?>
</div>
<?php endforeach; endif; ?>
</div>

<?php botx(); ?>
