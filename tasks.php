<?php
require_once __DIR__.'/boot.php'; require_login();
require_once __DIR__.'/tasks_lib.php';
$pdo=db();
$me=(int)($_SESSION['user']['id']??0);
// İşlem: durum güncelle / düzenle / sil (layout'tan önce → redirect)
if($_SERVER['REQUEST_METHOD']==='POST'){
  try{
    if(isset($_POST['task_status']) && (int)($_POST['tid']??0)){
      task_set_status($pdo,(int)$_POST['tid'],$_POST['task_status'],$me);
    }
    if(isset($_POST['edit_task']) && can_edit_delete()){
      $tid=(int)$_POST['tid'];
      task_apply_edit($pdo,$tid,$_POST,$me,true);
    }
  }catch(Throwable $e){}
  header('Location: tasks.php'.(!empty($_GET['f'])?'?f='.urlencode($_GET['f']):'')); exit;
}
require_once __DIR__.'/layout_top.php';
$f=$_GET['f']??'open';
$fp=$_GET['p']??'';
$where='WHERE t.deleted_at IS NULL'; if($f==='open') $where.=" AND t.status NOT IN ('Tamamlandı','İptal')"; elseif($f==='done') $where.=" AND t.status='Tamamlandı'";
if($fp){ $where.=' AND t.personnel_id='.(int)$fp; }
?>
<?php
$__taskActions=ds_button('İş Ekle','task_new.php','primary','','',true);
ds_page_header('Görevler', ds_icon('check',24), '', $__taskActions, false, true);
$__taskTabs=[['label'=>'Açık','url'=>'tasks.php?f=open','active'=>$f==='open'],['label'=>'Tamamlanan','url'=>'tasks.php?f=done','active'=>$f==='done'],['label'=>'Tümü','url'=>'tasks.php?f=all','active'=>$f==='all']];
ds_tabs($__taskTabs);
?>
<section class="df-card" style="margin:var(--df-space-4) 0;display:flex;gap:8px;align-items:center">
  <select onchange="if(this.value)location.href='tasks.php?f=<?=$f?>&p='+this.value; else location.href='tasks.php?f=<?=$f?>'">
    <option value="">— Personel Filtresi —</option>
    <?php
    try{
      $pl=$pdo->query("SELECT DISTINCT t.personnel_id, p.name FROM tasks t LEFT JOIN personnel p ON p.id=t.personnel_id WHERE t.personnel_id IS NOT NULL AND t.deleted_at IS NULL ORDER BY p.name")->fetchAll();
      foreach($pl as $p) echo '<option value="'.(int)$p['personnel_id'].'"'.($fp==(int)$p['personnel_id']?' selected':'').'>'.h($p['name']).'</option>';
    }catch(Throwable $e){}
    ?>
  </select>
</section>
<section class="df-card">
<div class="df-table-wrap"><table class="df-table"><thead><tr><th>Görev</th><th>İş</th><th>Personel</th><th>Termin</th><th>Öncelik</th><th>Durum</th><th>İşlem</th></tr></thead><tbody>
<?php
try{
$rows=$pdo->query("SELECT t.*, j.job_no, j.id job_real_id, p.name personnel_name, p.id personnel_id FROM tasks t LEFT JOIN jobs j ON j.id=t.job_id LEFT JOIN personnel p ON p.id=t.personnel_id $where ORDER BY (t.due_date IS NULL), t.due_date, t.id DESC")->fetchAll();
foreach($rows as $r){
  $jl = $r['job_real_id'] ? "job_view.php?id=".(int)$r['job_real_id'] : null;
  $gec = !empty($r['due_date']) && $r['due_date']<date('Y-m-d') && !in_array($r['status'],['Tamamlandı','İptal']);
  echo "<tr>";
  echo "<td><b>".h($r['title'])."</b>".($r['description']?"<br><small style='color:var(--df-ink-500)'>".h(mb_substr($r['description'],0,60))."</small>":"")."</td>";
  echo "<td>".($jl?"<a href='$jl'>".h($r['job_no']?:'#'.$r['job_id'])."</a>":($r['job_id']?h($r['job_id']):'-')) ."</td>";
  echo "<td>".h($r['personnel_name']??'-')."</td><td class='".($gec?'is-negative':'')."'>".h($r['due_date']??'-').($gec?' ⏰':'')."</td><td>".h($r['priority'])."</td>";
  echo "<td>".ds_badge($r['status'])."</td>";
  echo "<td style='white-space:nowrap'>";
  echo "<a class='df-btn df-btn--ghost df-btn--sm' href='task_view.php?id=".(int)$r['id']."'>👁 Detay</a> ";
  if($jl) echo "<a class='df-btn df-btn--ghost df-btn--sm' href='$jl'>📋 İş</a> ";
  if($r['status']!=='Devam Ediyor' && $r['status']!=='Tamamlandı') echo "<form method='post' style='display:inline'><input type='hidden' name='tid' value='".(int)$r['id']."'><button class='df-btn df-btn--ghost df-btn--sm' name='task_status' value='Devam Ediyor'>▶ Başla</button></form> ";
  if($r['status']!=='Tamamlandı') echo "<form method='post' style='display:inline'><input type='hidden' name='tid' value='".(int)$r['id']."'><button class='df-btn df-btn--primary df-btn--sm' name='task_status' value='Tamamlandı'>✓ Tamamla</button></form> ";
  if(can_edit_delete()) echo "<details class='df-btn df-btn--ghost df-btn--sm' style='display:inline-flex;align-items:center;cursor:pointer'><summary>✏️ Düzenle</summary>"
    ."<div style='position:absolute;background:var(--df-surface);border:1px solid var(--df-hairline);border-radius:var(--df-radius-md);padding:12px;margin-top:8px;min-width:300px;z-index:100;box-shadow:var(--df-elevation-floating)'><form method='post' style='display:flex;flex-direction:column;gap:8px'>"
    ."<input type='hidden' name='tid' value='".(int)$r['id']."'>"
    ."<input type='text' name='title' value='".h($r['title'])."' placeholder='Başlık' required>"
    ."<textarea name='description' placeholder='Açıklama' rows='2'>".h($r['description'])."</textarea>"
    ."<input type='date' name='due_date' value='".h($r['due_date']??'')."'>"
    ."<select name='priority'><option".($r['priority']==='Normal'?' selected':'').">Normal</option><option".($r['priority']==='Yüksek'?' selected':'').">Yüksek</option><option".($r['priority']==='Acil'?' selected':'').">Acil</option></select>"
    ."<select name='personnel_id'><option value=''>— Personel —</option>";
    try{ $pl=$pdo->query("SELECT id,name FROM personnel WHERE COALESCE(active,1)=1 ORDER BY name")->fetchAll(); foreach($pl as $p) echo '<option value="'.(int)$p['id'].'"'.($r['personnel_id']==(int)$p['id']?' selected':'').'>'.h($p['name']).'</option>'; }catch(Throwable $e){}
    echo "</select><button class='df-btn df-btn--primary' name='edit_task' style='width:100%'>💾 Kaydet</button></form></div></details> ";
  if(can_edit_delete()) echo "<form method='post' action='sil.php' style='display:inline' onsubmit=\"return confirm('Görev silinsin mi?')\"><input type='hidden' name='t' value='task'><input type='hidden' name='id' value='".(int)$r['id']."'><button class='df-btn df-btn--ghost df-btn--sm' type='submit'>🗑</button></form>";
  echo "</td></tr>";
}
if(!$rows) echo "<tr><td colspan='7' style='color:var(--df-ink-500)'>Görev yok.</td></tr>";
}catch(Throwable $e){echo "<tr><td colspan='7'>".ds_alert('danger',$e->getMessage())."</td></tr>";}
?>
</tbody></table></div></section>
<?php require_once __DIR__.'/layout_bottom.php'; ?>
