<?php require_once __DIR__.'/layout_top.php'; ?>
<?php ds_page_header('Dış İşler', '', '', '<a class="btn" href="job_new.php?type=dis_atolye">+ Yeni Dış İş</a>'); ?>

<section class="panel">
<table>
<thead>
<tr><th>İş No</th><th>Başlık</th><th>Tip</th><th>Termin</th><th>Durum</th></tr>
</thead>
<tbody>
<?php
try{
$rows=db()->query("SELECT * FROM jobs WHERE job_type IN ('dis_atolye','tedarikcide_uretim') ORDER BY id DESC")->fetchAll();
foreach($rows as $r){
echo "<tr>
<td><a href='job_view.php?id=".h($r['id'])."'>".h($r['job_no'])."</a></td>
<td>".h($r['title'])."</td>
<td>".h(job_type_label($r['job_type']))."</td>
<td>".h($r['due_date'])."</td>
<td>".badge($r['status'],status_tone($r['status']))."</td>
</tr>";
}
if(!$rows) echo "<tr><td colspan='5' class='muted'>Henüz kayıt yok.</td></tr>";
}catch(Throwable $e){
echo "<tr><td colspan='5'><div class='alert'>".h($e->getMessage())."</div></td></tr>";
}
?>
</tbody>
</table>
</section>
<?php require_once __DIR__.'/layout_bottom.php'; ?>