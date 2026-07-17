<?php
// WEB Üretim Panosu — mobil ile aynı job_stages_lib (parite)
require_once __DIR__.'/boot.php'; require_login();
require_once __DIR__.'/job_stages_lib.php';
$pdo=db();
require_once __DIR__.'/layout_top.php';
ds_page_header('Üretim Panosu', ds_icon('box',24), '', ds_button('+ Yeni Üretim İşi','job_new.php?type=3d_imalat','primary','','',true), false, true);
?>
<section class="df-card">
<div class="df-table-wrap"><table class="df-table">
<thead><tr><th>İş No</th><th>İş</th><th>Müşteri</th><th>Sorumlu</th><th>Aşama / İlerleme</th><th>Termin</th></tr></thead>
<tbody>
<?php
try{
  $rows=$pdo->query("SELECT j.id,j.job_no,j.title,j.due_date,c.name customer,p.name responsible FROM jobs j LEFT JOIN contacts c ON c.id=j.customer_id LEFT JOIN personnel p ON p.id=j.responsible_personnel_id WHERE j.status NOT IN ('Tamamlandı','Teslim Edildi','İptal') ORDER BY j.id DESC LIMIT 200")->fetchAll();
  foreach($rows as $r){
    $stages=get_stages($pdo,$r['id']); list($d,$t,$pct,$cur)=stage_progress($stages);
    $col=$pct>=100?'var(--df-success)':($pct>0?'var(--df-warning)':'var(--df-ink-500)');
    $bar = $t>0
      ? "<div style='display:flex;align-items:center;gap:8px'><div style='flex:1;height:10px;background:var(--df-surface-sunken);border-radius:6px;overflow:hidden;min-width:90px'><div style='height:100%;width:{$pct}%;background:{$col}'></div></div><span style='font-size:12px;color:{$col};font-weight:700;white-space:nowrap'>".h($cur)." %{$pct}</span></div>"
      : "<a class='df-btn df-btn--ghost df-btn--sm' href='job_view.php?id=".(int)$r['id']."'>Aşama oluştur</a>";
    echo "<tr><td><a href='job_view.php?id=".(int)$r['id']."'>".h($r['job_no'])."</a></td><td>".h($r['title'])."</td><td>".h($r['customer'])."</td><td>".h($r['responsible'])."</td><td style='min-width:200px'>".$bar."</td><td>".h($r['due_date'])."</td></tr>";
  }
  if(!$rows) echo "<tr><td colspan='6' style='color:var(--df-ink-500)'>Aktif üretim işi yok.</td></tr>";
}catch(Throwable $e){ echo "<tr><td colspan='6'>".ds_alert('danger',$e->getMessage())."</td></tr>"; }
?>
</tbody></table></div>
</section>
<?php require_once __DIR__.'/layout_bottom.php'; ?>
