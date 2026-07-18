<?php
require_once __DIR__.'/boot.php';
require_login();
require_once __DIR__.'/trade_core.php';

$id=(int)($_GET['id'] ?? 0);
$pdo=db();

// P0 KAPANIŞ (2026-07-18, Product Owner kararı 3. madde) — belge iptali. PRG deseni (topx()/redirect
// mobil kuralıyla AYNI ruh — POST ÖNCE işlenir, sonra redirect). İş mantığı trade_core.php::
// trade_document_cancel()'de (stock_lib.php'nin $viaDocument=true tersleme primitives'i, kopya YOK).
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['cancel_document'])){
    try{
        if(!can_edit_delete()) throw new Exception('Bu işlem için yetkiniz yok.');
        trade_document_cancel($pdo, $_SESSION['user']['id'] ?? 0, $id);
        $_SESSION['tdoc_ok']='Belge iptal edildi, stok/cari etkisi geri alındı.';
    }catch(Throwable $e){ $_SESSION['tdoc_er']=$e->getMessage(); }
    header('Location: trade_document_view.php?id='.$id); exit;
}

$st=$pdo->prepare("SELECT d.*, c.name contact_name, a.name account_name FROM trade_documents d LEFT JOIN contacts c ON c.id=d.contact_id LEFT JOIN finance_accounts a ON a.id=d.account_id WHERE d.id=?");
$st->execute([$id]);
$d=$st->fetch();

require_once __DIR__.'/layout_top.php';

if(!$d){
    echo ds_alert('danger','Belge bulunamadı');
    require __DIR__.'/layout_bottom.php';
    exit;
}

if(!empty($_SESSION['tdoc_ok'])){ echo ds_alert('success',$_SESSION['tdoc_ok']); unset($_SESSION['tdoc_ok']); }
if(!empty($_SESSION['tdoc_er'])){ echo ds_alert('danger',$_SESSION['tdoc_er']); unset($_SESSION['tdoc_er']); }

$it=$pdo->prepare("SELECT * FROM trade_document_items WHERE document_id=?");
$it->execute([$id]);
$items=$it->fetchAll();

// PDP-001 (2026-07-15): "Ödenen/Tahsil" kartı trade_documents.paid_amount gösteriyordu — bu kolon
// trade_document_new.php'de HER ZAMAN 0 ile INSERT ediliyor ve hiçbir yerde güncellenmiyor (Flow
// Unification 001 kararıyla belge artık ödeme kabul etmiyor, gerçek ödeme akışı finance_movements
// üzerinden cari bazlı işliyor) — yani bu kart yapısal olarak her zaman ₺0 gösteriyordu, gerçek bir
// ölçüm değildi. Aynı ₺0'ı "gerçek zamanlı" gibi tekrar üretmek yerine, carinin zaten doğru
// hesaplanan (contacts_lib.php::contact_balance) gerçek bakiyesi gösteriliyor.
require_once __DIR__.'/contacts_lib.php';
$contactBalance = $d['contact_id'] ? contact_balance($pdo, $d['contact_id']) : 0;

// P0 CPA KULLANICI AKIŞI (2026-07-18, Product Owner kararı) — Alış Belgesi detayında satır bazlı
// "Müşteriye Ayır" durumu. purchase_movement_id, bu belgenin trade_apply_document()/
// stock_create_purchase() ile oluşturduğu finance_movements satırıdır (document_id ile bağlı) —
// cpa_allocation_lib.php TÜM fonksiyonları zaten bu id üzerinden çalışıyor (bkz. o dosyadaki not),
// burada yeni bir tahsis mantığı İCAT EDİLMEDİ, sadece mevcut cpa_allocation_lib.php fonksiyonları
// (purchase.php'nin "Son Alışlar" listesinde zaten kullanılan) bu ekrana da bağlandı.
$__purchaseMovementId = 0;
if($d['document_type']==='purchase'){
    require_once __DIR__.'/cpa_allocation_lib.php';
    $__pmq = $pdo->prepare("SELECT id FROM finance_movements WHERE document_id=? AND movement_type='purchase'");
    $__pmq->execute([$id]);
    $__purchaseMovementId = (int)($__pmq->fetch()['id'] ?? 0);
}

// P0 KAPANIŞ (2026-07-18, Product Owner kararı 3. madde) — Düzenle/İptal aksiyonları. Eligibility
// stock_lib.php'nin AYNI avg_cost/CPA güvenlik kapılarından geçer (trade_document_can_edit() →
// stock_can_edit_purchase()/stock_can_edit_sale() $viaDocument=true) — burada AYRI bir kural
// tekrarlanmadı, sadece sonucuna göre buton gösterilip gösterilmeyeceğine karar verilir.
$__canEdit = can_edit_delete() ? trade_document_can_edit($pdo, $id) : ['editable'=>false,'reason'=>null];
$__actions = ds_button('Belgeler', 'trade_documents.php', 'secondary', '', '', true)
    . ds_button('Cari Profil', 'contact_view.php?id='.$d['contact_id'], 'secondary', '', '', true);
if(can_edit_delete() && $d['status']!=='İptal'){
    $__actions .= ds_button('✏️ Düzenle', 'trade_document_edit.php?id='.$id, 'secondary', '', '', true);
}
ds_page_header($d['document_no'], ds_icon('tag',24), '', $__actions, false, true);
?>

<?php if($d['status']==='İptal'): ?>
<?=ds_alert('danger','Bu belge iptal edildi — stok/cari etkisi geri alındı, düzenlenemez/satır tahsis edilemez.')?>
<?php elseif(!$__canEdit['editable'] && $__canEdit['reason'] && can_edit_delete()): ?>
<?=ds_alert('info', $__canEdit['reason'])?>
<?php endif; ?>

<div class="df-stat-row">
<div class="df-stat"><span>Tip</span><strong><?=h($d['document_type']==='purchase'?'Alış':'Satış')?></strong></div>
<div class="df-stat"><span>Cari</span><strong><?=h($d['contact_name'] ?: '-')?></strong></div>
<div class="df-stat"><span>Genel Toplam</span><strong><?=money($d['grand_total'])?></strong></div>
<div class="df-stat"><span>Cari Bakiyesi</span><strong><?=money($contactBalance)?></strong></div>
<div class="df-stat"><span>Durum</span><strong><?=ds_badge($d['status'], $d['status']==='İptal'?'red':'green')?></strong></div>
</div>

<?php if(can_edit_delete() && $d['status']!=='İptal'): ?>
<div class="df-card" style="margin-top:var(--df-space-4);display:flex;justify-content:flex-end">
<form method="post" onsubmit="return confirm('Bu belge iptal edilecek: bağlı stok/cari/CPA etkisi tam olarak geri alınacak. Belgenin kendisi (satırları) kalıcı kayıt olarak durur, sadece finansal etkisi kalkar. Emin misiniz?')">
<input type="hidden" name="cancel_document" value="1">
<button class="df-btn df-btn--danger df-btn--sm" type="submit">✕ İptal Et / Sil</button>
</form>
</div>
<?php endif; ?>

<style>
body.nav-compact .df-stat-row{display:flex;flex-wrap:wrap;gap:var(--df-space-3);margin:var(--df-space-4) 0}
body.nav-compact .df-stat{flex:1;min-width:120px;background:var(--df-surface);border:1px solid var(--df-hairline);border-radius:var(--df-radius-md);padding:var(--df-space-3);display:flex;flex-direction:column;gap:4px}
body.nav-compact .df-stat span{font-size:var(--df-type-caption-size);color:var(--df-ink-500)}
body.nav-compact .df-stat strong{font-size:var(--df-type-subtitle-size);color:var(--df-ink-900)}
</style>

<section class="df-card" style="margin-top:var(--df-space-4)">
<h2 style="font-size:var(--df-type-section-size);font-weight:var(--df-type-section-weight);color:var(--df-ink-900);margin:0 0 var(--df-space-3)">Belge Satırları</h2>
<div class="df-table-wrap"><table class="df-table">
<thead><tr><th>Ürün/Hizmet</th><th>Birim</th><th>Miktar</th><th>Birim Fiyat</th><th>KDV</th><th>Toplam</th><th>Stok Kartı</th></tr></thead>
<tbody>
<?php foreach($items as $r): ?>
<tr>
<td><?=h($r['item_name'])?></td>
<td><?=h($r['unit'])?></td>
<td><?=h($r['quantity'])?></td>
<td><?=money($r['unit_price'])?></td>
<td><?=money($r['line_vat'])?></td>
<td><?=money($r['line_grand'])?></td>
<td><?=$r['auto_created_product']?ds_badge('Otomatik Açıldı','green'):($r['stock_item_id']?ds_badge('Bağlı','blue'):'-')?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table></div>
</section>

<?php if($__purchaseMovementId && cpa_alloc_can_view()):
    $__allocLineSummary = cpa_alloc_purchase_line_summary($pdo, $__purchaseMovementId);
    $__allocList = cpa_alloc_list_for_purchase($pdo, $__purchaseMovementId);
    $__allocByItem = [];
    foreach($__allocList as $__a){ if($__a['status']==='İptal') continue; $__allocByItem[(int)$__a['stock_item_id']][] = $__a; }
?>
<section class="df-card" style="margin-top:var(--df-space-4)">
<h2 style="font-size:var(--df-type-section-size);font-weight:var(--df-type-section-weight);color:var(--df-ink-900);margin:0 0 var(--df-space-3)">Müşteriye Ayrılan</h2>
<?php if(!$__allocLineSummary): ?>
<?php ds_empty_state('Bu alışa bağlı stok hareketi bulunamadı.'); ?>
<?php else: ?>
<div class="df-table-wrap"><table class="df-table">
<thead><tr><th>Ürün</th><th style="text-align:right">Toplam</th><th style="text-align:right">Müşteriye Ayrılan</th><th style="text-align:right">Serbest</th><?php if(cpa_alloc_can_edit()): ?><th></th><?php endif; ?></tr></thead>
<tbody>
<?php foreach($__allocLineSummary as $__l): $__custs = $__allocByItem[$__l['stock_item_id']] ?? []; ?>
<tr>
<td>
<b><?=h($__l['product_name'])?></b>
<?php if($__custs): ?>
<div class="df-muted" style="font-size:12px;margin-top:4px">
<?php foreach($__custs as $__c): $__rem=(float)$__c['allocated_qty']-(float)$__c['consumed_qty']; ?>
<div><?=h($__c['customer_name'] ?: '—')?> → <b><?=stock_qty_fmt($__rem)?> <?=h($__l['unit'])?></b><?php if($__c['consumed_qty']>0): ?> <span style="color:var(--df-ink-400)">(tüketilen <?=stock_qty_fmt($__c['consumed_qty'])?>)</span><?php endif; ?><?php if(cpa_alloc_can_edit() && $__rem>0.0000001): ?> <a class="df-btn df-btn--primary df-btn--sm" style="padding:2px 10px;font-size:11px" href="sales.php?contact_id=<?=(int)$__c['customer_id']?>&stock_item_id=<?=(int)$__l['stock_item_id']?>&qty=<?=h($__rem)?>">🧾 Sat</a><?php endif; ?></div>
<?php endforeach; ?>
</div>
<?php endif; ?>
</td>
<td style="text-align:right"><?=stock_qty_fmt($__l['purchased_qty'])?> <?=h($__l['unit'])?></td>
<td style="text-align:right;color:var(--df-warning-ink);font-weight:700"><?=stock_qty_fmt($__l['allocated_from_purchase'])?> <?=h($__l['unit'])?></td>
<td style="text-align:right;font-weight:800;color:var(--df-success-ink)"><?=stock_qty_fmt($__l['free_on_purchase'])?> <?=h($__l['unit'])?></td>
<?php if(cpa_alloc_can_edit()): ?>
<td class="nowrap"><a class="df-btn df-btn--secondary df-btn--sm" href="cpa_allocation.php?purchase_id=<?=$__purchaseMovementId?>&stock_item_id=<?=(int)$__l['stock_item_id']?>">🤝 Müşteriye Ayır</a></td>
<?php endif; ?>
</tr>
<?php endforeach; ?>
</tbody>
</table></div>
<?php endif; ?>
</section>
<?php endif; ?>

<section class="df-card" style="margin-top:var(--df-space-4)">
<h2 style="font-size:var(--df-type-section-size);font-weight:var(--df-type-section-weight);color:var(--df-ink-900);margin:0 0 var(--df-space-3)">Özet</h2>
<p><b>Ara Toplam:</b> <?=money($d['subtotal'])?></p>
<p><b>KDV:</b> <?=money($d['vat_total'])?></p>
<p><b>Genel Toplam:</b> <?=money($d['grand_total'])?></p>
<p><b>Hesap:</b> <?=h($d['account_name'] ?: '-')?></p>
<p><b>Açıklama:</b> <?=h($d['description'] ?: '-')?></p>
</section>

<?php require_once __DIR__.'/layout_bottom.php'; ?>
