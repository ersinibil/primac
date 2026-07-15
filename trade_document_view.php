<?php
require_once __DIR__.'/boot.php';
require_login();

$id=(int)($_GET['id'] ?? 0);
$pdo=db();

$st=$pdo->prepare("SELECT d.*, c.name contact_name, a.name account_name FROM trade_documents d LEFT JOIN contacts c ON c.id=d.contact_id LEFT JOIN finance_accounts a ON a.id=d.account_id WHERE d.id=?");
$st->execute([$id]);
$d=$st->fetch();

require_once __DIR__.'/layout_top.php';

if(!$d){
    echo "<h1>Belge bulunamadı</h1>";
    require __DIR__.'/layout_bottom.php';
    exit;
}

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
?>

<div class="panel-head">
<h1><?=h($d['document_no'])?></h1>
<div class="actions">
<a class="btn secondary" href="trade_documents.php">Belgeler</a>
<a class="btn secondary" href="contact_view.php?id=<?=$d['contact_id']?>">Cari Profil</a>
</div>
</div>

<div class="cards">
<div class="card"><small>Tip</small><strong><?=h($d['document_type']==='purchase'?'Alış':'Satış')?></strong></div>
<div class="card"><small>Cari</small><strong><?=h($d['contact_name'] ?: '-')?></strong></div>
<div class="card"><small>Genel Toplam</small><strong><?=money($d['grand_total'])?></strong></div>
<div class="card"><small>Cari Bakiyesi</small><strong><?=money($contactBalance)?></strong></div>
</div>

<section class="panel">
<h2>Belge Satırları</h2>
<table>
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
<td><?=$r['auto_created_product']?badge('Otomatik Açıldı','green'):($r['stock_item_id']?badge('Bağlı','blue'):'-')?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</section>

<section class="panel">
<h2>Özet</h2>
<p><b>Ara Toplam:</b> <?=money($d['subtotal'])?></p>
<p><b>KDV:</b> <?=money($d['vat_total'])?></p>
<p><b>Genel Toplam:</b> <?=money($d['grand_total'])?></p>
<p><b>Hesap:</b> <?=h($d['account_name'] ?: '-')?></p>
<p><b>Açıklama:</b> <?=h($d['description'] ?: '-')?></p>
</section>

<?php require_once __DIR__.'/layout_bottom.php'; ?>
