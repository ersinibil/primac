<?php
/* SATIŞ OPERASYON MERKEZİ — Satış Detayı (mobil, 2026-07-19, P0-2). Web sale_view.php ile AYNI
 * mantık — bkz. o dosyadaki not. */
require_once 'common.php';
require_once __DIR__.'/../stock_lib.php';
require_once __DIR__.'/../contacts_lib.php';
$pdo=db();
$id=(int)($_GET['id'] ?? 0);

if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['delete_sale'])){
    try{
        if(!can_edit_delete()) throw new Exception('Silme için yetkiniz yok.');
        $res=stock_reverse_sale($pdo,$id);
        if($res['ok']){ redirect('sales_list.php?deleted=1'); }
        $_SESSION['sale_er']=$res['message'];
    }catch(Throwable $e){ $_SESSION['sale_er']=$e->getMessage(); }
    redirect('sale_view.php?id='.$id);
}

$st=$pdo->prepare("SELECT fm.*, c.name AS cname FROM finance_movements fm LEFT JOIN contacts c ON c.id=fm.contact_id WHERE fm.id=? AND (fm.movement_type='sale' OR fm.movement_type='mobile_sale')");
$st->execute([$id]);
$sale=$st->fetch();

if($sale && !empty($sale['document_id'])){ redirect('../trade_document_view.php?id='.(int)$sale['document_id'].'&web=1'); }

topx('Satış #'.$id, 'sales_list.php', 'Satışlar');

if(!$sale){
    echo ds_alert('danger','Satış kaydı bulunamadı — silinmiş/geri alınmış olabilir.');
    botx(); exit;
}

if(!empty($_SESSION['sale_er'])){ echo ds_alert('danger',$_SESSION['sale_er']); unset($_SESSION['sale_er']); }

$lines=$pdo->prepare("SELECT sm.*, si.name AS item_name, si.unit AS item_unit FROM stock_movements sm LEFT JOIN stock_items si ON si.id=sm.stock_item_id WHERE sm.finance_movement_id=? ORDER BY sm.id");
$lines->execute([$id]);
$lines=$lines->fetchAll();

$elig = can_edit_delete() ? stock_can_edit_sale($pdo,$id) : ['editable'=>false,'reason'=>null];
$balance = $sale['contact_id'] ? contact_balance($pdo,(int)$sale['contact_id']) : null;
?>
<div class="df-panel">
  <div style="display:flex;justify-content:space-between;gap:8px;align-items:flex-start">
    <div class="df-list-row-title" style="color:var(--df-success-ink)"><?=mm($sale['amount'])?></div>
    <?=ds_badge($sale['status'])?>
  </div>
  <div class="df-list-row-meta" style="margin-top:6px"><span><?=h($sale['movement_date'])?></span><span><?=h($sale['cname'] ?: '—')?></span></div>
  <?php if($balance!==null): ?><div class="muted" style="margin-top:6px;font-size:12px">Cari Bakiyesi: <b><?=mm($balance)?></b></div><?php endif; ?>
</div>

<?php if(!$elig['editable'] && $elig['reason'] && can_edit_delete()): ?><div style="margin-top:10px"><?=ds_alert('info',$elig['reason'])?></div><?php endif; ?>

<div style="margin-top:10px"><?=ds_alert('info','Bu satış tahsilat yapmadı — açık borç kaydedildi, kasa/banka etkilenmedi.')?></div>

<div class="df-panel" style="margin-top:10px">
  <b><?=ds_icon('box',16)?> Satış Kalemleri</b>
  <?php foreach($lines as $l): ?>
  <div style="display:flex;justify-content:space-between;gap:8px;padding:6px 0;border-top:1px solid var(--df-hairline)">
    <a href="product_view.php?id=<?=(int)$l['stock_item_id']?>"><?=h($l['item_name'] ?: '#'.$l['stock_item_id'])?></a>
    <span><?=stock_qty_fmt($l['quantity'])?> <?=h($l['item_unit'])?></span>
  </div>
  <?php endforeach; ?>
  <?php if(!$lines): ?><p class="muted" style="margin:8px 0 0">Stok satırı bulunamadı.</p><?php endif; ?>
</div>

<?php if($sale['description']): ?>
<div class="df-panel" style="margin-top:10px"><b>Açıklama</b><p style="margin:6px 0 0"><?=nl2br(h($sale['description']))?></p></div>
<?php endif; ?>

<div style="display:flex;gap:8px;margin-top:14px;flex-wrap:wrap">
<?php if($sale['contact_id']): ?><a class="df-btn df-btn--secondary" href="contact_view.php?id=<?=(int)$sale['contact_id']?>">Cari Profil</a><?php endif; ?>
<?php if($elig['editable']): ?><a class="df-btn df-btn--secondary" href="sales.php?edit_id=<?=$id?>">✏️ Düzenle</a><?php endif; ?>
</div>

<?php if(can_edit_delete()): ?>
<form method="post" style="margin-top:10px" onsubmit="return confirm('Bu satış geri alınacak: stok geri eklenir, cari borcu tersleşir. Emin misiniz?')">
<input type="hidden" name="delete_sale" value="1">
<button class="df-btn df-btn--danger" type="submit" style="width:100%">↩️ Satışı Geri Al</button>
</form>
<?php endif; ?>
<?php botx(); ?>
</content>
