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
.command-grid{display:grid;grid-template-columns:repeat(4,minmax(160px,1fr));gap:var(--df-space-4);margin:0 0 var(--df-space-5)}
.command-card{display:block;text-decoration:none;background:var(--df-surface);border-radius:var(--df-radius-lg);padding:var(--df-space-4);box-shadow:var(--df-elevation-raised);border:1px solid var(--df-hairline);color:var(--df-ink-900);transition:transform .12s ease,box-shadow .12s ease}
.command-card:hover{transform:translateY(-2px);box-shadow:var(--df-elevation-floating)}
.command-card small{display:block;color:var(--df-ink-500);font-weight:800;font-size:11px;text-transform:uppercase;letter-spacing:.06em}
.command-card strong{display:block;font-size:30px;margin:8px 0;line-height:1}
.command-card span{color:var(--df-ink-500);font-size:13px}
.command-card.blue{border-left:6px solid var(--df-accent)}
.command-card.purple{border-left:6px solid #8b5cf6}
.command-card.green{border-left:6px solid var(--df-success)}
.command-card.orange{border-left:6px solid var(--df-warning)}
@media(max-width:960px){.command-grid{grid-template-columns:1fr}}
</style>

<?php
$__actions = ds_button('+ Alış', 'trade_document_new.php?type=purchase', 'primary', '', '', true)
    . ds_button('+ Satış', 'trade_document_new.php?type=sale', 'secondary', '', '', true);
ds_page_header('Alış / Satış Belgeleri', ds_icon('tag',24), '', $__actions, false, true);
?>

<section class="command-grid">
<a class="command-card blue" href="trade_documents.php"><small>Tüm Belgeler</small><strong><?=safe_count("SELECT COUNT(*) c FROM trade_documents")?></strong><span>Alış ve satış hareketleri</span></a>
<a class="command-card purple" href="trade_documents.php?type=purchase"><small>Alış</small><strong><?=safe_count("SELECT COUNT(*) c FROM trade_documents WHERE document_type='purchase'")?></strong><span>Tedarikçi alışları</span></a>
<a class="command-card green" href="trade_documents.php?type=sale"><small>Satış</small><strong><?=safe_count("SELECT COUNT(*) c FROM trade_documents WHERE document_type='sale'")?></strong><span>Müşteri satışları</span></a>
<a class="command-card orange" href="contacts.php"><small>Cari</small><strong><?=safe_count("SELECT COUNT(DISTINCT contact_id) c FROM trade_documents WHERE contact_id IS NOT NULL")?></strong><span>Belgesi olan cari sayısı</span></a>
</section>

<section class="df-card">
<div class="df-table-wrap"><table class="df-table">
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
        echo "<td>".ds_badge($r['status'],'green')."</td>";
        echo "<td><a class='df-btn df-btn--secondary df-btn--sm' href='trade_document_view.php?id=".$r['id']."'>Aç</a></td>";
        echo "</tr>";
    }
    if(!$rows) echo "<tr><td colspan='8' style='color:var(--df-ink-500)'>Belge yok.</td></tr>";
}catch(Throwable $e){
    echo "<tr><td colspan='8'>".ds_alert('danger',$e->getMessage())."</td></tr>";
}
?>
</tbody>
</table></div>
</section>

<?php require_once __DIR__.'/layout_bottom.php'; ?>
