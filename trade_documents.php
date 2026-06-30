<?php
require_once __DIR__.'/layout_top.php';

$type=$_GET['type'] ?? '';
$where='';
$params=[];
if(in_array($type,['purchase','sale'])){
    $where='WHERE d.document_type=?';
    $params[]=$type;
}
?>

<div class="panel-head">
<h1>Alış / Satış Belgeleri</h1>
<div class="actions">
<a class="btn" href="trade_document_new.php?type=purchase">+ Alış</a>
<a class="btn secondary" href="trade_document_new.php?type=sale">+ Satış</a>
</div>
</div>

<section class="command-grid">
<a class="command-card blue" href="trade_documents.php"><small>Tüm Belgeler</small><strong><?=safe_count("SELECT COUNT(*) c FROM trade_documents")?></strong><span>Alış ve satış hareketleri</span></a>
<a class="command-card purple" href="trade_documents.php?type=purchase"><small>Alış</small><strong><?=safe_count("SELECT COUNT(*) c FROM trade_documents WHERE document_type='purchase'")?></strong><span>Tedarikçi alışları</span></a>
<a class="command-card green" href="trade_documents.php?type=sale"><small>Satış</small><strong><?=safe_count("SELECT COUNT(*) c FROM trade_documents WHERE document_type='sale'")?></strong><span>Müşteri satışları</span></a>
<a class="command-card orange" href="contacts.php"><small>Cari</small><strong>↔</strong><span>Cari hesaplara yansır</span></a>
</section>

<section class="panel">
<table>
<thead><tr><th>No</th><th>Tip</th><th>Cari</th><th>Tarih</th><th>Toplam</th><th>Ödenen</th><th>Durum</th><th>İşlem</th></tr></thead>
<tbody>
<?php
try{
    $st=db()->prepare("SELECT d.*, c.name contact_name FROM trade_documents d LEFT JOIN contacts c ON c.id=d.contact_id $where ORDER BY d.id DESC LIMIT 200");
    $st->execute($params);
    $rows=$st->fetchAll();
    foreach($rows as $r){
        echo "<tr>";
        echo "<td><b>".h($r['document_no'])."</b></td>";
        echo "<td>".h($r['document_type']==='purchase'?'Alış':'Satış')."</td>";
        echo "<td>".h($r['contact_name'] ?: '-')."</td>";
        echo "<td>".h($r['document_date'])."</td>";
        echo "<td>".money($r['grand_total'])."</td>";
        echo "<td>".money($r['paid_amount'])."</td>";
        echo "<td>".badge($r['status'],'green')."</td>";
        echo "<td><a class='btn small secondary' href='trade_document_view.php?id=".$r['id']."'>Aç</a></td>";
        echo "</tr>";
    }
    if(!$rows) echo "<tr><td colspan='8' class='muted'>Belge yok.</td></tr>";
}catch(Throwable $e){
    echo "<tr><td colspan='8'><div class='alert'>".h($e->getMessage())."</div></td></tr>";
}
?>
</tbody>
</table>
</section>

<?php require_once __DIR__.'/layout_bottom.php'; ?>
