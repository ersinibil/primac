<?php
require_once __DIR__.'/boot.php';
// Güvenlik: id GET parametresiyle herhangi bir carinin belgelerini gösteriyordu (IDOR), hiç yetki
// kontrolü yoktu — diğer contacts-modülü sayfalarıyla aynı desen (2026-07-03 denetimi).
require_permission('contacts');
require_once __DIR__.'/layout_top.php';

$id=(int)($_GET['id'] ?? 0);
$st=db()->prepare("SELECT * FROM contacts WHERE id=?");
$st->execute([$id]);
$c=$st->fetch();

if(!$c){
    echo "<h1>Cari bulunamadı</h1>";
    require __DIR__.'/layout_bottom.php';
    exit;
}
?>

<div class="panel-head">
<h1><?=h($c['name'])?> / Belgeler</h1>
<div class="actions">
<a class="btn" href="trade_document_new.php?type=purchase&contact_id=<?=$id?>">+ Alış</a>
<a class="btn secondary" href="trade_document_new.php?type=sale&contact_id=<?=$id?>">+ Satış</a>
<a class="btn secondary" href="contact_view.php?id=<?=$id?>">Cari Profil</a>
</div>
</div>

<section class="panel">
<table>
<thead><tr><th>No</th><th>Tip</th><th>Tarih</th><th>Toplam</th><th>Ödenen</th><th>Durum</th><th>Aç</th></tr></thead>
<tbody>
<?php
$s=db()->prepare("SELECT * FROM trade_documents WHERE contact_id=? ORDER BY id DESC");
$s->execute([$id]);
$rows=$s->fetchAll();
foreach($rows as $r){
    echo "<tr><td><b>".h($r['document_no'])."</b></td><td>".h($r['document_type']==='purchase'?'Alış':'Satış')."</td><td>".h($r['document_date'])."</td><td>".money($r['grand_total'])."</td><td>".money($r['paid_amount'])."</td><td>".h($r['status'])."</td><td><a class='btn small secondary' href='trade_document_view.php?id=".$r['id']."'>Aç</a></td></tr>";
}
if(!$rows) echo "<tr><td colspan='7' class='muted'>Belge yok.</td></tr>";
?>
</tbody>
</table>
</section>

<?php require_once __DIR__.'/layout_bottom.php'; ?>
