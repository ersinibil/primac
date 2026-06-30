<?php
require_once __DIR__.'/boot.php'; require_login();
$pdo=db();
// İşlem: durum güncelle / sil (layout'tan önce → redirect)
if($_SERVER['REQUEST_METHOD']==='POST'){
  try{
    if(isset($_POST['task_status']) && (int)($_POST['tid']??0)){
      $st=$_POST['task_status'];
      $pdo->prepare("UPDATE tasks SET status=?, completed_at=IF(?='Tamamlandı',NOW(),completed_at), started_at=IF(?='Devam Ediyor' AND started_at IS NULL,NOW(),started_at) WHERE id=?")
          ->execute([$st,$st,$st,(int)$_POST['tid']]);
    }
    if(isset($_POST['del_task'])){ $pdo->prepare("DELETE FROM tasks WHERE id=?")->execute([(int)$_POST['del_task']]); }
  }catch(Throwable $e){}
  header('Location: tasks.php'.(!empty($_GET['f'])?'?f='.urlencode($_GET['f']):'')); exit;
}
require_once __DIR__.'/layout_top.php';
$f=$_GET['f']??'open';
$where=''; if($f==='open') $where="WHERE t.status NOT IN ('Tamamlandı','İptal')"; elseif($f==='done') $where="WHERE t.status='Tamamlandı'";
?>
<h1>Görevler</h1>
<section class="panel" style="margin-bottom:12px">
  <a class="btn <?=$f==='open'?'':'ghost'?>" href="tasks.php?f=open">Açık</a>
  <a class="btn <?=$f==='done'?'':'ghost'?>" href="tasks.php?f=done">Tamamlanan</a>
  <a class="btn <?=$f==='all'?'':'ghost'?>" href="tasks.php?f=all">Tümü</a>
  <a class="btn" href="task_new.php" style="float:right">+ Yeni Görev</a>
</section>
<section class="panel"><table><thead><tr><th>Görev</th><th>İş</th><th>Personel</th><th>Termin</th><th>Öncelik</th><th>Durum</th><th>İşlem</th></tr></thead><tbody>
<?php
try{
$rows=db()->query("SELECT t.*, j.job_no, j.id job_real_id, p.name personnel_name FROM tasks t LEFT JOIN jobs j ON j.id=t.job_id LEFT JOIN personnel p ON p.id=t.personnel_id $where ORDER BY t.id DESC")->fetchAll();
foreach($rows as $r){
  $jl = $r['job_real_id'] ? "job_view.php?id=".(int)$r['job_real_id'] : null;
  echo "<tr>";
  echo "<td><b>".h($r['title'])."</b>".($r['description']?"<br><small class='muted'>".h(mb_substr($r['description'],0,60))."</small>":"")."</td>";
  echo "<td>".($jl?"<a href='$jl'>".h($r['job_no']?:'#'.$r['job_id'])."</a>":h($r['job_no'])) ."</td>";
  echo "<td>".h($r['personnel_name'])."</td><td>".h($r['due_date'])."</td><td>".h($r['priority'])."</td>";
  echo "<td>".badge($r['status'],status_tone($r['status']))."</td>";
  echo "<td style='white-space:nowrap'>";
  if($jl) echo "<a class='btn ghost' href='$jl'>Detay</a> ";
  if($r['status']!=='Devam Ediyor' && $r['status']!=='Tamamlandı') echo "<form method='post' style='display:inline'><input type='hidden' name='tid' value='".(int)$r['id']."'><button class='btn ghost' name='task_status' value='Devam Ediyor'>▶ Başla</button></form> ";
  if($r['status']!=='Tamamlandı') echo "<form method='post' style='display:inline'><input type='hidden' name='tid' value='".(int)$r['id']."'><button class='btn' name='task_status' value='Tamamlandı'>✓ Tamamla</button></form> ";
  echo "<form method='post' style='display:inline' onsubmit=\"return confirm('Görev silinsin mi?')\"><button class='btn ghost' name='del_task' value='".(int)$r['id']."'>🗑</button></form>";
  echo "</td></tr>";
}
if(!$rows) echo "<tr><td colspan='7' class='muted'>Görev yok.</td></tr>";
}catch(Throwable $e){echo "<tr><td colspan='7'><div class='alert'>".h($e->getMessage())."</div></td></tr>";}
?>
</tbody></table></section>
<?php require_once __DIR__.'/layout_bottom.php'; ?>
