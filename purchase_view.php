<?php
/* SATIN ALMA OPERASYON MERKEZİ — Satın Alma Detayı (2026-07-19, P0-3). sale_view.php ile birebir
 * aynı desen (alış tarafı) — bkz. o dosyadaki mimari not. Belgeliyse trade_document_view.php'ye
 * yönlendirir (mükerrer ekran yok). */
require_once __DIR__.'/boot.php';
require_login();
require_once __DIR__.'/stock_lib.php';
require_once __DIR__.'/cpa_allocation_lib.php';
require_once __DIR__.'/contacts_lib.php';

$pdo=db();
$id=(int)($_GET['id'] ?? 0);

if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['delete_purchase'])){
    try{
        if(!can_edit_delete()) throw new Exception('Silme için yetkiniz yok.');
        $res=stock_reverse_purchase($pdo,$id);
        if($res['ok']){ header('Location: purchase_list.php?deleted=1'); exit; }
        $_SESSION['purchase_er']=$res['message'];
    }catch(Throwable $e){ $_SESSION['purchase_er']=$e->getMessage(); }
    header('Location: purchase_view.php?id='.$id); exit;
}

$st=$pdo->prepare("SELECT fm.*, c.name AS cname FROM finance_movements fm LEFT JOIN contacts c ON c.id=fm.contact_id WHERE fm.id=? AND fm.movement_type='purchase'");
$st->execute([$id]);
$purchase=$st->fetch();

if($purchase && !empty($purchase['document_id'])){
    header('Location: trade_document_view.php?id='.(int)$purchase['document_id']); exit;
}

require_once __DIR__.'/layout_top.php';

if(!$purchase){
    echo ds_alert('danger','Alış kaydı bulunamadı — silinmiş/geri alınmış olabilir.');
    echo ds_button('Satın Almalar Listesi','purchase_list.php','secondary','','',true);
    require __DIR__.'/layout_bottom.php'; exit;
}

if(!empty($_SESSION['purchase_er'])){ echo ds_alert('danger',$_SESSION['purchase_er']); unset($_SESSION['purchase_er']); }

$lines=$pdo->prepare("SELECT sm.*, si.name AS item_name, si.unit AS item_unit FROM stock_movements sm LEFT JOIN stock_items si ON si.id=sm.stock_item_id WHERE sm.finance_movement_id=? AND sm.direction='in' ORDER BY sm.id");
$lines->execute([$id]);
$lines=$lines->fetchAll();

$elig = can_edit_delete() ? stock_can_edit_purchase($pdo,$id) : ['editable'=>false,'reason'=>null];
$balance = $purchase['contact_id'] ? contact_balance($pdo,(int)$purchase['contact_id']) : null;

$__actions = ds_button('Satın Almalar','purchase_list.php','secondary','','',true);
if($purchase['contact_id']) $__actions .= ds_button('Cari Profil','contact_view.php?id='.(int)$purchase['contact_id'],'secondary','','',true);
if($elig['editable']) $__actions .= ds_button('✏️ Düzenle','purchase.php?edit_id='.$id,'secondary','','',true);
if(can_edit_delete()) $__actions .= ds_button('🤝 Müşteriye Ayır','cpa_allocation.php?purchase_id='.$id,'secondary','','',true);
ds_page_header('Alış #'.$id, ds_icon('box',24), '', $__actions, false, true);
?>

<?php if(!$elig['editable'] && $elig['reason'] && can_edit_delete()): ?><?=ds_alert('info',$elig['reason'])?><?php endif; ?>

<div class="df-stat-row">
<div class="df-stat"><span>Tedarikçi</span><strong><?=h($purchase['cname'] ?: '—')?></strong></div>
<div class="df-stat"><span>Tarih</span><strong><?=h($purchase['movement_date'])?></strong></div>
<div class="df-stat"><span>Tutar</span><strong><?=money($purchase['amount'])?></strong></div>
<div class="df-stat"><span>Durum</span><strong><?=ds_badge($purchase['status'])?></strong></div>
<?php if($balance!==null): ?><div class="df-stat"><span>Cari Bakiyesi</span><strong><?=money($balance)?></strong></div><?php endif; ?>
</div>

<style>
body.nav-compact .df-stat-row{display:flex;flex-wrap:wrap;gap:var(--df-space-3);margin:var(--df-space-4) 0}
body.nav-compact .df-stat{flex:1;min-width:120px;background:var(--df-surface);border:1px solid var(--df-hairline);border-radius:var(--df-radius-md);padding:var(--df-space-3);display:flex;flex-direction:column;gap:4px}
body.nav-compact .df-stat span{font-size:var(--df-type-caption-size);color:var(--df-ink-500)}
body.nav-compact .df-stat strong{font-size:var(--df-type-subtitle-size);color:var(--df-ink-900)}
</style>

<?php // ds_alert() mesajı h() ile escape ediyor — içine <a> gömülemez (ham HTML sızıyordu, USER TEST
// bulgusu 2026-07-19). purchase.php'nin AYNI amaçlı bilgi kutusuyla aynı desen: elle df-alert div'i. ?>
<div class="df-alert df-alert--info" style="margin-bottom:var(--df-space-3)">
Bu alış ödeme yapmadı — tedarikçiye açık borç olarak kaydedildi (kasa/banka etkilenmedi). Ödeme
<a href="finance_new.php?direction=out&contact_id=<?=(int)$purchase['contact_id']?>">Ödeme ekranından</a> ayrıca girilir.
</div>

<section class="df-card" style="margin-top:var(--df-space-4)">
<h2 style="font-size:var(--df-type-section-size);margin:0 0 var(--df-space-3)">Alış Kalemleri (BU İŞLEMİN ETKİSİ: stok +)</h2>
<div class="df-table-wrap"><table class="df-table">
<thead><tr><th>Ürün</th><th>Miktar</th><th>Birim Fiyat</th><th>KDV %</th></tr></thead>
<tbody>
<?php foreach($lines as $l): ?>
<tr>
<td><a href="product_view.php?id=<?=(int)$l['stock_item_id']?>"><?=h($l['item_name'] ?: '#'.$l['stock_item_id'])?></a></td>
<td><?=stock_qty_fmt($l['quantity'])?> <?=h($l['item_unit'])?></td>
<td><?=$l['unit_price']!==null ? money($l['unit_price']) : '—'?></td>
<td><?=$l['vat_rate']!==null ? h($l['vat_rate']).'%' : '—'?></td>
</tr>
<?php endforeach; ?>
<?php if(!$lines): ?><tr><td colspan="4" style="color:var(--df-ink-500)">Stok satırı bulunamadı.</td></tr><?php endif; ?>
</tbody>
</table></div>
</section>

<?php if($purchase['description']): ?>
<section class="df-card" style="margin-top:var(--df-space-4)"><h2 style="font-size:var(--df-type-section-size);margin:0 0 8px">Açıklama</h2><p><?=nl2br(h($purchase['description']))?></p></section>
<?php endif; ?>

<?php if(can_edit_delete()): ?>
<div class="df-card" style="margin-top:var(--df-space-4);display:flex;justify-content:flex-end">
<form method="post" onsubmit="return confirm('Bu alış geri alınacak: stok geri düşülür, cari borcu tersleşir. Emin misiniz?')">
<input type="hidden" name="delete_purchase" value="1">
<button class="df-btn df-btn--danger df-btn--sm" type="submit">↩️ Alışı Geri Al</button>
</form>
</div>
<?php endif; ?>

<?php require_once __DIR__.'/layout_bottom.php'; ?>
</content>
