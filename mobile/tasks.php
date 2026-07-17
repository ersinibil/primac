<?php
require_once 'common.php';
require_once __DIR__.'/../tasks_lib.php';
if(!user_can('tasks')){ header('Location: index.php'); exit; }
$pdo=db();
$me=(int)($_SESSION['user']['id']??0);
if($_SERVER['REQUEST_METHOD']==='POST'){
  try{
    if(isset($_POST['task_status']) && (int)($_POST['tid']??0)){
      task_set_status($pdo,(int)$_POST['tid'],$_POST['task_status'],$me);
    }
    if(isset($_POST['edit_task']) && can_edit_delete()){
      task_apply_edit($pdo,(int)$_POST['tid'],$_POST,$me,true);
    }
    if(isset($_POST['delete_task']) && can_edit_delete()){
      task_soft_delete($pdo,(int)$_POST['tid'],$me);
    }
  }catch(Throwable $e){}
  header('Location: tasks.php'.(!empty($_GET['f'])?'?f='.urlencode($_GET['f']):'')); exit;
}

$f=$_GET['f']??'open';
$fp=$_GET['p']??'';
$where='WHERE t.deleted_at IS NULL'; if($f==='open') $where.=" AND t.status NOT IN ('Tamamlandı','İptal')"; elseif($f==='done') $where.=" AND t.status='Tamamlandı'";
if($fp){ $where.=' AND t.personnel_id='.(int)$fp; }

topx('Tüm Görevler');
?>
<div style="display:flex;align-items:center;justify-content:space-between;gap:8px;flex-wrap:wrap;margin-bottom:12px">
  <div class="df-tabs">
    <a class="df-tab<?=$f==='open'?' df-tab--active':''?>" href="tasks.php?f=open">Açık</a>
    <a class="df-tab<?=$f==='done'?' df-tab--active':''?>" href="tasks.php?f=done">Tamamlanan</a>
    <a class="df-tab<?=$f==='all'?' df-tab--active':''?>" href="tasks.php?f=all">Tümü</a>
  </div>
  <?=ds_button(ds_icon('plus',14).' Yeni','task_new.php','primary','df-btn--sm','',true)?>
</div>

<div class="df-panel">
  <label>Personel Filtresi</label>
  <select onchange="if(this.value)location.href='tasks.php?f=<?=h($f)?>&p='+this.value; else location.href='tasks.php?f=<?=h($f)?>'">
    <option value="">— Personel Filtresi —</option>
    <?php
    try{
      $pl=$pdo->query("SELECT DISTINCT t.personnel_id, p.name FROM tasks t LEFT JOIN personnel p ON p.id=t.personnel_id WHERE t.personnel_id IS NOT NULL AND t.deleted_at IS NULL ORDER BY p.name")->fetchAll();
      foreach($pl as $p) echo '<option value="'.(int)$p['personnel_id'].'"'.($fp==(int)$p['personnel_id']?' selected':'').'>'.h($p['name']).'</option>';
    }catch(Throwable $e){}
    ?>
  </select>
</div>

<?php
try{
$rows=$pdo->query("SELECT t.*, j.job_no, j.id job_real_id, p.name personnel_name, p.id personnel_id FROM tasks t LEFT JOIN jobs j ON j.id=t.job_id LEFT JOIN personnel p ON p.id=t.personnel_id $where ORDER BY (t.due_date IS NULL), t.due_date, t.id DESC")->fetchAll();
if(!$rows){
  ds_empty_state('Görev yok.', null, ds_icon('calendar',20));
} else foreach($rows as $r):
  $jl = $r['job_real_id'] ? "job_view.php?id=".(int)$r['job_real_id'] : null;
  $gec = !empty($r['due_date']) && $r['due_date']<date('Y-m-d') && !in_array($r['status'],['Tamamlandı','İptal']);
  $__tone = task_status_tone($r['status']);
?>
<div class="df-panel" style="margin-top:10px">
  <div class="df-list-row-title"><?=h($r['title'])?></div>
  <?php if($r['description']): ?><div class="df-list-row-desc"><?=h(mb_substr($r['description'],0,80))?></div><?php endif; ?>
  <div class="df-list-row-meta" style="margin-top:6px">
    <?php if($jl): ?><a href="<?=h($jl)?>" style="color:inherit;font-weight:600"><?=h($r['job_no']?:'#'.$r['job_id'])?></a><?php endif; ?>
    <span><?=h($r['personnel_name']??'-')?></span>
    <?php if(!empty($r['due_date'])): ?><span class="df-list-row-due"><?=ds_icon('calendar',13)?> <?=h($r['due_date'])?></span><?php endif; ?>
    <?php if($gec): ?><span style="color:var(--df-danger-ink);font-weight:600">Gecikti</span><?php endif; ?>
    <?=ds_priority($r['priority'],$r['priority']!=='Normal'?$r['priority']:null)?>
    <span class="df-badge df-badge--<?=h($__tone)?>"><?=h($r['status'])?></span>
  </div>

  <div style="display:flex;gap:6px;flex-wrap:wrap;margin-top:10px">
    <a class="df-btn df-btn--secondary" href="task_view.php?id=<?=(int)$r['id']?>">Detay</a>
    <?php if($r['status']!=='Devam Ediyor' && $r['status']!=='Tamamlandı'): ?>
    <form method="post" style="margin:0"><input type="hidden" name="tid" value="<?=(int)$r['id']?>"><button type="submit" class="df-btn df-btn--secondary" name="task_status" value="Devam Ediyor">Başla</button></form>
    <?php endif; ?>
    <?php if($r['status']!=='Tamamlandı'): ?>
    <form method="post" style="margin:0"><input type="hidden" name="tid" value="<?=(int)$r['id']?>"><button type="submit" class="df-btn df-btn--primary" name="task_status" value="Tamamlandı"><?=ds_icon('check',14)?> Tamamla</button></form>
    <?php endif; ?>
  </div>

  <?php if(can_edit_delete()): ?>
  <details style="margin-top:10px">
    <summary style="cursor:pointer;font-weight:700;color:var(--df-ink-600)"><?=ds_icon('edit',14)?> Düzenle</summary>
    <form method="post" style="margin-top:10px">
      <input type="hidden" name="tid" value="<?=(int)$r['id']?>">
      <input type="text" name="title" value="<?=h($r['title'])?>" placeholder="Başlık" required>
      <textarea name="description" placeholder="Açıklama" rows="2"><?=h($r['description'])?></textarea>
      <input type="date" name="due_date" value="<?=h($r['due_date']??'')?>">
      <select name="priority">
        <option <?=$r['priority']==='Normal'?'selected':''?>>Normal</option>
        <option <?=$r['priority']==='Yüksek'?'selected':''?>>Yüksek</option>
        <option <?=$r['priority']==='Acil'?'selected':''?>>Acil</option>
      </select>
      <select name="personnel_id">
        <option value="">— Personel —</option>
        <?php try{ $pl2=$pdo->query("SELECT id,name FROM personnel WHERE COALESCE(active,1)=1 ORDER BY name")->fetchAll(); foreach($pl2 as $p2) echo '<option value="'.(int)$p2['id'].'"'.($r['personnel_id']==(int)$p2['id']?' selected':'').'>'.h($p2['name']).'</option>'; }catch(Throwable $e){} ?>
      </select>
      <button type="submit" class="df-btn df-btn--primary" name="edit_task" value="1" style="width:100%;margin-top:6px;justify-content:center"><?=ds_icon('check',14)?> Kaydet</button>
    </form>
  </details>
  <form method="post" onsubmit="return confirm('Görev silinsin mi?')" style="margin-top:8px">
    <input type="hidden" name="tid" value="<?=(int)$r['id']?>">
    <button type="submit" class="df-btn df-btn--danger" name="delete_task" value="1" style="width:100%;justify-content:center"><?=ds_icon('trash',14)?> Sil</button>
  </form>
  <?php endif; ?>
</div>
<?php endforeach;
}catch(Throwable $e){ echo ds_alert('danger',$e->getMessage()); }
?>
<?php botx(); ?>
