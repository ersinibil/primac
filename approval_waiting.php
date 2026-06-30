<?php require_once __DIR__.'/layout_top.php'; ?>
<div class="panel-head">
<h1>Müşteri Onayı Bekleyen Dosyalar</h1>
<a class="btn secondary" href="dashboard.php">Komuta Merkezi</a>
</div>

<section class="panel">
<table>
<thead><tr><th>İş</th><th>Dosya</th><th>Tür</th><th>Durum</th><th>Tarih</th><th>İşlem</th></tr></thead>
<tbody>
<?php
try{
$rows=db()->query("SELECT f.*, j.job_no, j.title job_title FROM job_files f LEFT JOIN jobs j ON j.id=f.job_id WHERE f.approval_status='Müşteri Onayı Bekliyor' ORDER BY f.id DESC")->fetchAll();
foreach($rows as $r){
$share='http://acanstr.com/erp/public_file.php?token='.$r['share_token'];
echo "<tr>
<td><a href='job_view.php?id=".h($r['job_id'])."'>".h($r['job_no'])."<br>".h($r['job_title'])."</a></td>
<td>".h($r['original_name'])."</td>
<td>".h($r['file_type'])."</td>
<td>".badge($r['approval_status'],'yellow')."</td>
<td>".h($r['created_at'])."</td>
<td><a class='btn small' target='_blank' href='".h($share)."'>Müşteri Linki</a></td>
</tr>";
}
if(!$rows) echo "<tr><td colspan='6' class='muted'>Onay bekleyen dosya yok.</td></tr>";
}catch(Throwable $e){ echo "<tr><td colspan='6'><div class='alert'>".h($e->getMessage())."</div></td></tr>";}
?>
</tbody>
</table>
</section>
<?php require_once __DIR__.'/layout_bottom.php'; ?>
