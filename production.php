<?php
// WEB Üretim Panosu — mobil ile aynı job_stages_lib (parite)
require_once __DIR__.'/boot.php'; require_login();
require_once __DIR__.'/job_stages_lib.php';
$pdo=db();
require_once __DIR__.'/layout_top.php';
?>
<div class="panel-head"><h1>🏭 Üretim Panosu</h1><a class="btn" href="job_new.php?type=3d_imalat">+ Yeni Üretim İşi</a></div>
<section class="panel">
<table>
<thead><tr><th>İş No</th><th>İş</th><th>Müşteri</th><th>Sorumlu</th><th>Aşama / İlerleme</th><th>Termin</th></tr></thead>
<tbody>
<?php
try{
  $rows=$pdo->query("SELECT j.id,j.job_no,j.title,j.due_date,c.name customer,p.name responsible FROM jobs j LEFT JOIN contacts c ON c.id=j.customer_id LEFT JOIN personnel p ON p.id=j.responsible_personnel_id WHERE j.status NOT IN ('Tamamlandı','Teslim Edildi','İptal') ORDER BY j.id DESC LIMIT 200")->fetchAll();
  foreach($rows as $r){
    $stages=get_stages($pdo,$r['id']); list($d,$t,$pct,$cur)=stage_progress($stages);
    $col=$pct>=100?'#16a34a':($pct>0?'#d97706':'#64748b');
    $bar = $t>0
      ? "<div style='display:flex;align-items:center;gap:8px'><div style='flex:1;height:10px;background:#e2e8f0;border-radius:6px;overflow:hidden;min-width:90px'><div style='height:100%;width:{$pct}%;background:{$col}'></div></div><span style='font-size:12px;color:{$col};font-weight:700;white-space:nowrap'>".h($cur)." %{$pct}</span></div>"
      : "<a class='btn small' href='job_view.php?id=".(int)$r['id']."'>Aşama oluştur</a>";
    echo "<tr><td><a href='job_view.php?id=".(int)$r['id']."'>".h($r['job_no'])."</a></td><td>".h($r['title'])."</td><td>".h($r['customer'])."</td><td>".h($r['responsible'])."</td><td style='min-width:200px'>".$bar."</td><td>".h($r['due_date'])."</td></tr>";
  }
  if(!$rows) echo "<tr><td colspan='6' class='muted'>Aktif üretim işi yok.</td></tr>";
}catch(Throwable $e){ echo "<tr><td colspan='6'><div class='alert'>".h($e->getMessage())."</div></td></tr>"; }
?>
</tbody></table>
</section>
<?php require_once __DIR__.'/layout_bottom.php'; ?>
