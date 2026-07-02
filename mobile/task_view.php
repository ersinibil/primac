<?php
require_once 'common.php';
$pdo=db();
$id=(int)($_GET['id']??0);
if($id<1){ topx('Hata'); echo '<div class="err">Görev bulunamadı.</div>'; botx(); exit; }

$t=$pdo->prepare("SELECT t.*, j.job_no, j.id job_real, p.name pname FROM tasks t LEFT JOIN jobs j ON j.id=t.job_id LEFT JOIN personnel p ON p.id=t.personnel_id WHERE t.id=?");
$t->execute([$id]); $task=$t->fetch();
if(!$task){ topx('Hata'); echo '<div class="err">Görev bulunamadı.</div>'; botx(); exit; }

// POST: durum değiştir / düzenle / sil
if($_SERVER['REQUEST_METHOD']==='POST'){
    try{
        if(isset($_POST['task_status'])){
            $st=$_POST['task_status'];
            if(in_array($st,['Devam Ediyor','Tamamlandı'])){
                $pdo->prepare("UPDATE tasks SET status=?, completed_at=IF(?='Tamamlandı',NOW(),completed_at), started_at=IF(?='Devam Ediyor' AND started_at IS NULL,NOW(),started_at) WHERE id=?")
                    ->execute([$st,$st,$st,$id]);
            }
        }
        if(isset($_POST['edit_task']) && can_edit_delete()){
            $pdo->prepare("UPDATE tasks SET title=?, description=?, due_date=?, priority=?, personnel_id=? WHERE id=?")
                ->execute([trim($_POST['title']), trim($_POST['description']??''), $_POST['due_date']?:null, $_POST['priority']??'Normal', (int)($_POST['personnel_id']??0)?:null, $id]);
        }
        if(isset($_POST['delete_task']) && can_edit_delete()){
            $pdo->prepare("DELETE FROM tasks WHERE id=?")->execute([$id]);
            header('Location: mytasks.php'); exit;
        }
    }catch(Throwable $e){}
    header('Location: task_view.php?id='.$id); exit;
}

topx($task['title']);
$geç = !empty($task['due_date']) && $task['due_date']<date('Y-m-d') && !in_array($task['status'],['Tamamlandı','İptal']);
?>
<div class="panel" style="padding:12px">
  <h2><?=htmlspecialchars($task['title'])?></h2>
  <?php if($task['description']): ?><p class="muted"><?=htmlspecialchars($task['description'])?></p><?php endif; ?>
  <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin:12px 0;font-size:13px">
    <div><span class="muted">Durum</span><br><?=htmlspecialchars($task['status'])?></div>
    <div><span class="muted">Öncelik</span><br><?=htmlspecialchars($task['priority'])?></div>
    <div><span class="muted">Personel</span><br><?=htmlspecialchars($task['pname']??'-')?></div>
    <div><span class="muted">Termin</span><br><span style="color:<?=($geç?'#f87171':'inherit')?>;"><?=htmlspecialchars($task['due_date']??'-').($geç?' ⏰':'')?></span></div>
    <?php if($task['job_no']): ?><div><span class="muted">İş No</span><br><?=htmlspecialchars($task['job_no'])?></div><?php endif; ?>
  </div>

  <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:12px">
    <?php if($task['job_real']): ?><a class="btn" href="job_view.php?id=<?=(int)$task['job_real']?>" style="background:#334155;color:#fff;flex:1">📋 İş Detayı</a><?php endif; ?>
    <?php if($task['status']!=='Tamamlandı'): ?>
      <form method="post" style="flex:1;margin:0">
        <button class="btn dark" name="task_status" value="Tamamlandı" style="width:100%">✓ Tamamla</button>
      </form>
    <?php endif; ?>
  </div>
</div>

<?php if(can_edit_delete()): ?>
<details class="panel" style="padding:12px">
  <summary style="cursor:pointer;font-weight:900;user-select:none">✏️ Düzenle</summary>
  <form method="post" style="display:flex;flex-direction:column;gap:10px;margin-top:12px">
    <input type="text" name="title" value="<?=htmlspecialchars($task['title'])?>" placeholder="Başlık" required>
    <textarea name="description" placeholder="Açıklama" rows="3"><?=htmlspecialchars($task['description']??'')?></textarea>
    <input type="date" name="due_date" value="<?=htmlspecialchars($task['due_date']??'')?>">
    <select name="priority">
      <option<?=($task['priority']==='Normal'?' selected':'')?>​>Normal</option>
      <option<?=($task['priority']==='Yüksek'?' selected':'')?>​>Yüksek</option>
      <option<?=($task['priority']==='Acil'?' selected':'')?>​>Acil</option>
    </select>
    <select name="personnel_id">
      <option value="">— Personel —</option>
      <?php
      try{
        $pl=$pdo->query("SELECT id,name FROM personnel WHERE COALESCE(active,1)=1 ORDER BY name")->fetchAll();
        foreach($pl as $p) echo '<option value="'.(int)$p['id'].'"'.($task['personnel_id']==(int)$p['id']?' selected':'').'>'.htmlspecialchars($p['name']).'</option>';
      }catch(Throwable $e){}
      ?>
    </select>
    <button class="btn dark" name="edit_task" style="width:100%">💾 Kaydet</button>
  </form>
</details>

<details class="panel" style="padding:12px;border-color:#f87171">
  <summary style="cursor:pointer;font-weight:900;user-select:none;color:#f87171">🗑 Sil</summary>
  <p class="muted" style="margin:12px 0">Bu görev kalıcı olarak silinecektir.</p>
  <form method="post" onsubmit="return confirm('Görev silinsin mi?')">
    <button class="btn danger" name="delete_task" style="width:100%">🗑 Kalıcı Olarak Sil</button>
  </form>
</details>
<?php endif; ?>

<?php botx(); ?>
