<?php require_once __DIR__.'/layout_top.php'; ?>
<?php ds_page_header('Dış İşler', ds_icon('briefcase',24), '', ds_button('+ Yeni Dış İş', 'job_new.php?type=dis_atolye', 'primary', '', '', true), false, true); ?>

<section class="df-card">
<div class="df-table-wrap"><table class="df-table">
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
<td>".ds_badge($r['status'])."</td>
</tr>";
}
if(!$rows) echo "<tr><td colspan='5' style='color:var(--df-ink-500)'>Henüz kayıt yok.</td></tr>";
}catch(Throwable $e){
echo "<tr><td colspan='5'>".ds_alert('danger',$e->getMessage())."</td></tr>";
}
?>
</tbody>
</table></div>
</section>
<?php require_once __DIR__.'/layout_bottom.php'; ?>
