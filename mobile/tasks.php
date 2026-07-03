<?php
require_once 'common.php';
if(!user_can('tasks')){ header('Location: index.php'); exit; }
$pdo=db();
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
    if(isset($_POST['delete_task']) && can_edit_delete()){
      $pdo->prepare("DELETE FROM tasks WHERE id=?")->execute([(int)$_POST['tid']]);
    }
  }catch(Throwable $e){}
  header('Location: tasks.php'.(!empty($_GET['f'])?'?f='.urlencode($_GET['f']):'')); exit;
}

$f=$_GET['f']??'open';
$fp=$_GET['p']??'';
$where=''; if($f==='open') $where="WHERE t.status NOT IN ('Tamamlandı','İptal')"; elseif($f==='done') $where="WHERE t.status='Tamamlandı'";
if($fp){ $where.=($where?' AND ':' WHERE ').'t.personnel_id='.(int)$fp; }

topx('Tüm Görevler');
?>
<div class="panel" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
  <a class="btn <?=$f==='open'?'dark':''?>" style="<?=$f==='open'?'':'background:rgba(255,255,255,.12);color:#fff'?>" href="tasks.php?f=open">Açık</a>
  <a class="btn <?=$f==='done'?'dark':''?>" style="<?=$f==='done'?'':'background:rgba(255,255,255,.12);color:#fff'?>" href="tasks.php?f=done">Tamamlanan</a>
  <a class="btn <?=$f==='all'?'dark':''?>" style="<?=$f==='all'?'':'background:rgba(255,255,255,.12);color:#fff'?>" href="tasks.php?f=all">Tümü</a>
  <a class="btn dark" href="task_new.php" style="margin-left:auto">+ Yeni</a>
</div>

<div class="panel">
  <label style="color:#94a3b8;font-size:12px">Personel Filtresi</label>
  <select onchange="if(this.value)location.href='tasks.php?f=<?=htmlspecialchars($f)?>&p='+this.value; else location.href='tasks.php?f=<?=htmlspecialchars($f)?>'">
    <option value="">— Personel Filtresi —</option>
    <?php
    try{
      $pl=$pdo->query("SELECT DISTINCT t.personnel_id, p.name FROM tasks t LEFT JOIN personnel p ON p.id=t.personnel_id WHERE t.personnel_id IS NOT NULL ORDER BY p.name")->fetchAll();
      foreach($pl as $p) echo '<option value="'.(int)$p['personnel_id'].'"'.($fp==(int)$p['personnel_id']?' selected':'').'>'.htmlspecialchars($p['name']).'</option>';
    }catch(Throwable $e){}
    ?>
  </select>
</div>

<?php
try{
$rows=$pdo->query("SELECT t.*, j.job_no, j.id job_real_id, p.name personnel_name, p.id personnel_id FROM tasks t LEFT JOIN jobs j ON j.id=t.job_id LEFT JOIN personnel p ON p.id=t.personnel_id $where ORDER BY (t.due_date IS NULL), t.due_date, t.id DESC")->fetchAll();
if(!$rows): ?>
<div class="panel muted" style="text-align:center">Görev yok.</div>
<?php else: foreach($rows as $r):
  $jl = $r['job_real_id'] ? "job_view.php?id=".(int)$r['job_real_id'] : null;
  $gec = !empty($r['due_date']) && $r['due_date']<date('Y-m-d') && !in_array($r['status'],['Tamamlandı','İptal']);
?>
<div class="item">
  <b><?=htmlspecialchars($r['title'])?></b>
  <?php if($r['description']): ?><br><small class="small"><?=htmlspecialchars(mb_substr($r['description'],0,80))?></small><?php endif; ?>
  <br><small class="small">
    <?php if($jl): ?><a href="<?=$jl?>" style="color:#93c5fd"><?=htmlspecialchars($r['job_no']?:'#'.$r['job_id'])?></a> · <?php endif; ?>
    <?=htmlspecialchars($r['personnel_name']??'-')?> ·
    <span style="color:<?=$gec?'#f87171':'#94a3b8'?>"><?=htmlspecialchars($r['due_date']??'-')?><?=$gec?' ⏰':''?></span> ·
    <?=htmlspecialchars($r['priority'])?> ·
    <span style="background:rgba(255,255,255,.12);border-radius:6px;padding:2px 6px"><?=htmlspecialchars($r['status'])?></span>
  </small>

  <div style="display:flex;gap:6px;flex-wrap:wrap;margin-top:8px">
    <?php if($r['status']!=='Devam Ediyor' && $r['status']!=='Tamamlandı'): ?>
    <form method="post"><input type="hidden" name="tid" value="<?=(int)$r['id']?>"><button class="btn" style="background:rgba(255,255,255,.12);color:#fff;padding:8px 12px;font-size:12px" name="task_status" value="Devam Ediyor">▶ Başla</button></form>
    <?php endif; ?>
    <?php if($r['status']!=='Tamamlandı'): ?>
    <form method="post"><input type="hidden" name="tid" value="<?=(int)$r['id']?>"><button class="btn dark" style="padding:8px 12px;font-size:12px" name="task_status" value="Tamamlandı">✓ Tamamla</button></form>
    <?php endif; ?>
  </div>

  <?php if(can_edit_delete()): ?>
  <details style="margin-top:8px">
    <summary style="font-size:12px;color:#60a5fa;cursor:pointer">✏️ Düzenle</summary>
    <form method="post" style="margin-top:8px">
      <input type="hidden" name="tid" value="<?=(int)$r['id']?>">
      <input type="text" name="title" value="<?=htmlspecialchars($r['title'])?>" placeholder="Başlık" required>
      <textarea name="description" placeholder="Açıklama" rows="2"><?=htmlspecialchars($r['description'])?></textarea>
      <input type="date" name="due_date" value="<?=htmlspecialchars($r['due_date']??'')?>">
      <select name="priority">
        <option <?=$r['priority']==='Normal'?'selected':''?>>Normal</option>
        <option <?=$r['priority']==='Yüksek'?'selected':''?>>Yüksek</option>
        <option <?=$r['priority']==='Acil'?'selected':''?>>Acil</option>
      </select>
      <select name="personnel_id">
        <option value="">— Personel —</option>
        <?php try{ $pl2=$pdo->query("SELECT id,name FROM personnel WHERE COALESCE(active,1)=1 ORDER BY name")->fetchAll(); foreach($pl2 as $p2) echo '<option value="'.(int)$p2['id'].'"'.($r['personnel_id']==(int)$p2['id']?' selected':'').'>'.htmlspecialchars($p2['name']).'</option>'; }catch(Throwable $e){} ?>
      </select>
      <button class="btn dark" name="edit_task" value="1" style="width:100%;margin-top:6px">💾 Kaydet</button>
    </form>
  </details>
  <form method="post" onsubmit="return confirm('Görev silinsin mi?')" style="margin-top:6px">
    <input type="hidden" name="tid" value="<?=(int)$r['id']?>">
    <button class="btn" name="delete_task" value="1" style="width:100%;background:rgba(239,68,68,.2);color:#fca5a5;padding:8px">🗑 Sil</button>
  </form>
  <?php endif; ?>
</div>
<?php endforeach; endif;
}catch(Throwable $e){ echo '<div class="err">'.htmlspecialchars($e->getMessage()).'</div>'; }
?>
<?php botx(); ?>
