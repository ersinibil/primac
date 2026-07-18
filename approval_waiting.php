<?php require_once __DIR__.'/layout_top.php'; ?>
<?php ds_page_header('Müşteri Onayı Bekleyen Dosyalar', ds_icon('clock',24), '', ds_button('Komuta Merkezi','dashboard.php','secondary','','',true), false, true); ?>

<section class="df-card">
<div class="df-table-wrap"><table class="df-table">
<thead><tr><th>İş</th><th>Dosya</th><th>Tür</th><th>Durum</th><th>Tarih</th><th>İşlem</th></tr></thead>
<tbody>
<?php
try{
$rows=db()->query("SELECT f.*, j.job_no, j.title job_title FROM job_files f LEFT JOIN jobs j ON j.id=f.job_id WHERE f.approval_status='Müşteri Onayı Bekliyor' ORDER BY f.id DESC")->fetchAll();
foreach($rows as $r){
$share=base_url().'public_file.php?token='.$r['share_token'];
echo "<tr>
<td><a href='job_view.php?id=".h($r['job_id'])."'>".h($r['job_no'])."<br>".h($r['job_title'])."</a></td>
<td>".h($r['original_name'])."</td>
<td>".h($r['file_type'])."</td>
<td>".ds_badge($r['approval_status'],'warning')."</td>
<td>".h($r['created_at'])."</td>
<td><a class='df-btn df-btn--secondary df-btn--sm' target='_blank' href='".h($share)."'>Müşteri Linki</a></td>
</tr>";
}
if(!$rows) echo "<tr><td colspan='6' class='df-muted'>Onay bekleyen dosya yok.</td></tr>";
}catch(Throwable $e){ echo "<tr><td colspan='6'>".ds_alert('danger',$e->getMessage())."</td></tr>";}
?>
</tbody>
</table></div>
</section>
<?php require_once __DIR__.'/layout_bottom.php'; ?>
