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

<style>
.command-grid{display:grid;grid-template-columns:repeat(4,minmax(160px,1fr));gap:14px;margin:14px 0 20px}
.command-card{display:block;text-decoration:none;background:#fff;border-radius:20px;padding:18px;box-shadow:0 8px 28px rgba(16,24,40,.06);border:1px solid #eef2f6;color:#101828;transition:transform .12s ease,box-shadow .12s ease}
.command-card:hover{transform:translateY(-2px);box-shadow:0 14px 36px rgba(16,24,40,.11)}
.command-card small{display:block;color:#667085;font-weight:800;font-size:11px;text-transform:uppercase;letter-spacing:.06em}
.command-card strong{display:block;font-size:30px;margin:8px 0;line-height:1}
.command-card span{color:#667085;font-size:13px}
.command-card.blue{border-left:6px solid #3b82f6}
.command-card.purple{border-left:6px solid #8b5cf6}
.command-card.green{border-left:6px solid #22c55e}
.command-card.orange{border-left:6px solid #f97316}
@media(max-width:960px){.command-grid{grid-template-columns:1fr}}
</style>

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
<a class="command-card orange" href="contacts.php"><small>Cari</small><strong><?=safe_count("SELECT COUNT(DISTINCT contact_id) c FROM trade_documents WHERE contact_id IS NOT NULL")?></strong><span>Belgesi olan cari sayısı</span></a>
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
