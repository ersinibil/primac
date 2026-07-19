<?php
/* SATIN ALMA OPERASYON MERKEZİ — Satın Alma Detayı (mobil, 2026-07-19, P0-3). */
require_once 'common.php';
require_once __DIR__.'/../stock_lib.php';
require_once __DIR__.'/../cpa_allocation_lib.php';
require_once __DIR__.'/../contacts_lib.php';
$pdo=db();
$id=(int)($_GET['id'] ?? 0);

if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['delete_purchase'])){
    try{
        if(!can_edit_delete()) throw new Exception('Silme için yetkiniz yok.');
        $res=stock_reverse_purchase($pdo,$id);
        if($res['ok']){ redirect('purchase_list.php?deleted=1'); }
        $_SESSION['purchase_er']=$res['message'];
    }catch(Throwable $e){ $_SESSION['purchase_er']=$e->getMessage(); }
    redirect('purchase_view.php?id='.$id);
}

$st=$pdo->prepare("SELECT fm.*, c.name AS cname FROM finance_movements fm LEFT JOIN contacts c ON c.id=fm.contact_id WHERE fm.id=? AND fm.movement_type='purchase'");
$st->execute([$id]);
$purchase=$st->fetch();

if($purchase && !empty($purchase['document_id'])){ redirect('../trade_document_view.php?id='.(int)$purchase['document_id'].'&web=1'); }

topx('Alış #'.$id, 'purchase_list.php', 'Satın Almalar');

if(!$purchase){
    echo ds_alert('danger','Alış kaydı bulunamadı — silinmiş/geri alınmış olabilir.');
    botx(); exit;
}

if(!empty($_SESSION['purchase_er'])){ echo ds_alert('danger',$_SESSION['purchase_er']); unset($_SESSION['purchase_er']); }

$lines=$pdo->prepare("SELECT sm.*, si.name AS item_name, si.unit AS item_unit FROM stock_movements sm LEFT JOIN stock_items si ON si.id=sm.stock_item_id WHERE sm.finance_movement_id=? AND sm.direction='in' ORDER BY sm.id");
$lines->execute([$id]);
$lines=$lines->fetchAll();

$elig = can_edit_delete() ? stock_can_edit_purchase($pdo,$id) : ['editable'=>false,'reason'=>null];
$balance = $purchase['contact_id'] ? contact_balance($pdo,(int)$purchase['contact_id']) : null;
?>
<div class="df-panel">
  <div style="display:flex;justify-content:space-between;gap:8px;align-items:flex-start">
    <div class="df-list-row-title" style="color:var(--df-danger-ink)"><?=mm($purchase['amount'])?></div>
    <?=ds_badge($purchase['status'])?>
  </div>
  <div class="df-list-row-meta" style="margin-top:6px"><span><?=h($purchase['movement_date'])?></span><span><?=h($purchase['cname'] ?: '—')?></span></div>
  <?php if($balance!==null): ?><div class="muted" style="margin-top:6px;font-size:12px">Cari Bakiyesi: <b><?=mm($balance)?></b></div><?php endif; ?>
</div>

<?php if(!$elig['editable'] && $elig['reason'] && can_edit_delete()): ?><div style="margin-top:10px"><?=ds_alert('info',$elig['reason'])?></div><?php endif; ?>

<div style="margin-top:10px"><?=ds_alert('info','Bu alış ödeme yapmadı — açık borç kaydedildi, kasa/banka etkilenmedi.')?></div>

<div class="df-panel" style="margin-top:10px">
  <b><?=ds_icon('box',16)?> Alış Kalemleri</b>
  <?php foreach($lines as $l): ?>
  <div style="display:flex;justify-content:space-between;gap:8px;padding:6px 0;border-top:1px solid var(--df-hairline)">
    <a href="product_view.php?id=<?=(int)$l['stock_item_id']?>"><?=h($l['item_name'] ?: '#'.$l['stock_item_id'])?></a>
    <span><?=stock_qty_fmt($l['quantity'])?> <?=h($l['item_unit'])?></span>
  </div>
  <?php endforeach; ?>
  <?php if(!$lines): ?><p class="muted" style="margin:8px 0 0">Stok satırı bulunamadı.</p><?php endif; ?>
</div>

<?php if($purchase['description']): ?>
<div class="df-panel" style="margin-top:10px"><b>Açıklama</b><p style="margin:6px 0 0"><?=nl2br(h($purchase['description']))?></p></div>
<?php endif; ?>

<div style="display:flex;gap:8px;margin-top:14px;flex-wrap:wrap">
<?php if($purchase['contact_id']): ?><a class="df-btn df-btn--secondary" href="contact_view.php?id=<?=(int)$purchase['contact_id']?>">Cari Profil</a><?php endif; ?>
<?php if($elig['editable']): ?><a class="df-btn df-btn--secondary" href="purchase.php?edit_id=<?=$id?>">✏️ Düzenle</a><?php endif; ?>
<?php if(can_edit_delete()): ?><a class="df-btn df-btn--secondary" href="cpa_allocation.php?purchase_id=<?=$id?>">🤝 Müşteriye Ayır</a><?php endif; ?>
</div>

<?php if(can_edit_delete()): ?>
<form method="post" style="margin-top:10px" onsubmit="return confirm('Bu alış geri alınacak: stok geri düşülür, cari borcu tersleşir. Emin misiniz?')">
<input type="hidden" name="delete_purchase" value="1">
<button class="df-btn df-btn--danger" type="submit" style="width:100%">↩️ Alışı Geri Al</button>
</form>
<?php endif; ?>
<?php botx(); ?>
</content>
