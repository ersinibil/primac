<?php
require_once __DIR__.'/boot.php'; require_login();
$pdo=db();
// İşlem: durum güncelle / düzenle / sil (layout'tan önce → redirect)
if($_SERVER['REQUEST_METHOD']==='POST'){
  try{
    if(isset($_POST['task_status']) && (int)($_POST['tid']??0)){
      $st=$_POST['task_status'];
      $pdo->prepare("UPDATE tasks SET status=?, completed_at=IF(?='Tamamlandı',NOW(),completed_at), started_at=IF(?='Devam Ediyor' AND started_at IS NULL,NOW(),started_at) WHERE id=?")
          ->execute([$st,$st,$st,(int)$_POST['tid']]);
    }
    if(isset($_POST['edit_task']) && can_edit_delete()){
      $tid=(int)$_POST['tid'];
      $pdo->prepare("UPDATE tasks SET title=?, description=?, due_date=?, priority=?, personnel_id=? WHERE id=?")
          ->execute([trim($_POST['title']), trim($_POST['description']??''), $_POST['due_date']?:null, $_POST['priority']??'Normal', (int)($_POST['personnel_id']??0)?:null, $tid]);
    }
  }catch(Throwable $e){}
  header('Location: tasks.php'.(!empty($_GET['f'])?'?f='.urlencode($_GET['f']):'')); exit;
}
require_once __DIR__.'/layout_top.php';
$f=$_GET['f']??'open';
$fp=$_GET['p']??'';
$where=''; if($f==='open') $where="WHERE t.status NOT IN ('Tamamlandı','İptal')"; elseif($f==='done') $where="WHERE t.status='Tamamlandı'";
if($fp){ $where.=($where?' AND ':' WHERE ').'t.personnel_id='.(int)$fp; }
?>
<h1>Görevler</h1>
<section class="panel" style="margin-bottom:12px;display:flex;gap:8px;flex-wrap:wrap;align-items:center">
  <a class="btn <?=$f==='open'?'':'ghost'?>" href="tasks.php?f=open">Açık</a>
  <a class="btn <?=$f==='done'?'':'ghost'?>" href="tasks.php?f=done">Tamamlanan</a>
  <a class="btn <?=$f==='all'?'':'ghost'?>" href="tasks.php?f=all">Tümü</a>
  <select onchange="if(this.value)location.href='tasks.php?f=<?=$f?>&p='+this.value; else location.href='tasks.php?f=<?=$f?>'" style="padding:8px;border:1px solid #ddd;border-radius:6px">
    <option value="">— Personel Filtresi —</option>
    <?php
    try{
      $pl=$pdo->query("SELECT DISTINCT t.personnel_id, p.name FROM tasks t LEFT JOIN personnel p ON p.id=t.personnel_id WHERE t.personnel_id IS NOT NULL ORDER BY p.name")->fetchAll();
      foreach($pl as $p) echo '<option value="'.(int)$p['personnel_id'].'"'.($fp==(int)$p['personnel_id']?' selected':'').'>'.h($p['name']).'</option>';
    }catch(Throwable $e){}
    ?>
  </select>
  <a class="btn" href="task_new.php" style="margin-left:auto">+ Yeni Görev</a>
</section>
<section class="panel"><table><thead><tr><th>Görev</th><th>İş</th><th>Personel</th><th>Termin</th><th>Öncelik</th><th>Durum</th><th>İşlem</th></tr></thead><tbody>
<?php
try{
$rows=$pdo->query("SELECT t.*, j.job_no, j.id job_real_id, p.name personnel_name, p.id personnel_id FROM tasks t LEFT JOIN jobs j ON j.id=t.job_id LEFT JOIN personnel p ON p.id=t.personnel_id $where ORDER BY (t.due_date IS NULL), t.due_date, t.id DESC")->fetchAll();
foreach($rows as $r){
  $jl = $r['job_real_id'] ? "job_view.php?id=".(int)$r['job_real_id'] : null;
  $gec = !empty($r['due_date']) && $r['due_date']<date('Y-m-d') && !in_array($r['status'],['Tamamlandı','İptal']);
  echo "<tr>";
  echo "<td><b>".h($r['title'])."</b>".($r['description']?"<br><small class='muted'>".h(mb_substr($r['description'],0,60))."</small>":"")."</td>";
  echo "<td>".($jl?"<a href='$jl'>".h($r['job_no']?:'#'.$r['job_id'])."</a>":($r['job_id']?h($r['job_id']):'-')) ."</td>";
  echo "<td>".h($r['personnel_name']??'-')."</td><td style='color:".($gec?'#f87171':'inherit')."'>".h($r['due_date']??'-').($gec?' ⏰':'')."</td><td>".h($r['priority'])."</td>";
  echo "<td>".badge($r['status'],status_tone($r['status']))."</td>";
  echo "<td style='white-space:nowrap'>";
  if($jl) echo "<a class='btn ghost' href='$jl'>Detay</a> ";
  if($r['status']!=='Devam Ediyor' && $r['status']!=='Tamamlandı') echo "<form method='post' style='display:inline'><input type='hidden' name='tid' value='".(int)$r['id']."'><button class='btn ghost' name='task_status' value='Devam Ediyor'>▶ Başla</button></form> ";
  if($r['status']!=='Tamamlandı') echo "<form method='post' style='display:inline'><input type='hidden' name='tid' value='".(int)$r['id']."'><button class='btn' name='task_status' value='Tamamlandı'>✓ Tamamla</button></form> ";
  if(can_edit_delete()) echo "<details class='btn ghost' style='display:inline-flex;align-items:center;padding:6px 12px;cursor:pointer'><summary>✏️ Düzenle</summary>"
    ."<div style='position:absolute;background:white;border:1px solid #ddd;border-radius:8px;padding:12px;margin-top:8px;min-width:300px;z-index:100'><form method='post' style='display:flex;flex-direction:column;gap:8px'>"
    ."<input type='hidden' name='tid' value='".(int)$r['id']."'>"
    ."<input type='text' name='title' value='".h($r['title'])."' placeholder='Başlık' required>"
    ."<textarea name='description' placeholder='Açıklama' rows='2'>".h($r['description'])."</textarea>"
    ."<input type='date' name='due_date' value='".h($r['due_date']??'')."'>"
    ."<select name='priority'><option".($r['priority']==='Normal'?' selected':'').">Normal</option><option".($r['priority']==='Yüksek'?' selected':'').">Yüksek</option><option".($r['priority']==='Acil'?' selected':'').">Acil</option></select>"
    ."<select name='personnel_id'><option value=''>— Personel —</option>";
    try{ $pl=$pdo->query("SELECT id,name FROM personnel WHERE COALESCE(active,1)=1 ORDER BY name")->fetchAll(); foreach($pl as $p) echo '<option value="'.(int)$p['id'].'"'.($r['personnel_id']==(int)$p['id']?' selected':'').'>'.h($p['name']).'</option>'; }catch(Throwable $e){}
    echo "</select><button class='btn dark' name='edit_task' style='width:100%'>💾 Kaydet</button></form></div></details> ";
  if(can_edit_delete()) echo "<form method='post' action='sil.php' style='display:inline' onsubmit=\"return confirm('Görev silinsin mi?')\"><input type='hidden' name='t' value='task'><input type='hidden' name='id' value='".(int)$r['id']."'><button class='btn ghost' type='submit'>🗑</button></form>";
  echo "</td></tr>";
}
if(!$rows) echo "<tr><td colspan='7' class='muted'>Görev yok.</td></tr>";
}catch(Throwable $e){echo "<tr><td colspan='7'><div class='alert'>".h($e->getMessage())."</div></td></tr>";}
?>
</tbody></table></section>
<?php require_once __DIR__.'/layout_bottom.php'; ?>
