<?php
// Görev Detayı (web) — "İşlerim" Detay ekranı. Mobil paritesi: mobile/task_view.php.
// GET bilinçli olarak KORUMASIZ (job_view.php ile aynı desen, boot.php page_module_map yorumu) —
// personel kendine atanan görevi bildirimden/mesajdan açabilsin diye. Yazma işlemleri (durum/
// düzenle/sil/yorum/dosya) task_can_edit()/task_can_delete() ile sahiplik kontrollüdür.
require_once __DIR__.'/boot.php';
require_login();
require_once __DIR__.'/tasks_lib.php';
$pdo=db();
$me=(int)($_SESSION['user']['id']??0);
$pid=task_my_personnel_id($pdo,$me);
$id=(int)($_GET['id']??0);

if($id<1){ require_once __DIR__.'/layout_top.php'; echo '<div class="alert">Görev bulunamadı.</div>'; require_once __DIR__.'/layout_bottom.php'; exit; }

$task=task_fetch($pdo,$id);
if(!$task){ require_once __DIR__.'/layout_top.php'; echo '<div class="alert">Görev bulunamadı.</div>'; require_once __DIR__.'/layout_bottom.php'; exit; }

$canEdit = task_can_edit($task,$me,$pid);
$canDelete = task_can_delete($task,$me,$pid);
$canReassign = task_can_reassign($task,$me);

// POST — layout_top.php (header/redirect) ÖNCESİ tamamlanmalı (PRG deseni, mobil ile aynı kural).
if($_SERVER['REQUEST_METHOD']==='POST'){
    try{
        if(isset($_POST['task_status']) && $canEdit){
            $st=$_POST['task_status'];
            if(in_array($st,['Devam Ediyor','Tamamlandı'],true)) task_set_status($pdo,$id,$st,$me);
        }
        if(isset($_POST['edit_task']) && $canEdit){
            task_apply_edit($pdo,$id,$_POST,$me,$canReassign);
        }
        if(isset($_POST['delete_task']) && $canDelete){
            task_soft_delete($pdo,$id,$me);
            header('Location: mytasks.php'); exit;
        }
        if(isset($_POST['comment_add']) && $canEdit){
            task_comment_add($pdo,$id,$me,$_POST['comment']??'');
        }
        if(isset($_POST['file_upload']) && $canEdit && isset($_FILES['task_file'])){
            task_file_upload($pdo,$id,$me,$_FILES['task_file']);
        }
        if(isset($_POST['file_delete']) && $canEdit){
            task_file_delete($pdo,$id,(int)$_POST['file_delete'],$me);
        }
    }catch(Throwable $e){ $_SESSION['task_err']=$e->getMessage(); }
    header('Location: task_view.php?id='.$id); exit;
}

require_once __DIR__.'/layout_top.php';
$gec = !empty($task['due_date']) && $task['due_date']<date('Y-m-d') && !in_array($task['status'],['Tamamlandı','İptal']);
?>
<div class="panel-head"><h1><?=h($task['title'])?></h1></div>
<?php if(!empty($_SESSION['task_err'])): ?><div class="alert"><?=h($_SESSION['task_err'])?></div><?php unset($_SESSION['task_err']); endif; ?>

<section class="panel">
  <?php if($task['description']): ?><p class="muted"><?=nl2br(h($task['description']))?></p><?php endif; ?>
  <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin:12px 0;font-size:14px">
    <div><span class="muted">Durum</span><br><?=badge($task['status'],status_tone($task['status']))?></div>
    <div><span class="muted">Öncelik</span><br><?=h($task['priority'])?></div>
    <div><span class="muted">Personel</span><br><?=h($task['pname']??'-')?></div>
    <div><span class="muted">Termin</span><br><span style="color:<?=($gec?'#dc2626':'inherit')?>"><?=h($task['due_date']??'-').($gec?' ⏰ GECİKMİŞ':'')?></span></div>
    <?php if($task['job_no']): ?><div><span class="muted">İş No</span><br><a href="job_view.php?id=<?=(int)$task['job_real']?>"><?=h($task['job_no'])?></a></div><?php endif; ?>
    <div><span class="muted">Oluşturan</span><br><?=h($task['creator_name']?:$task['creator_username']?:'-')?></div>
    <?php if($task['updated_by']): ?><div><span class="muted">Son Güncelleyen</span><br><?=h($task['updater_name']?:$task['updater_username']?:'-')?></div><?php endif; ?>
  </div>
  <div class="actions">
    <?php if($canEdit && $task['status']!=='Devam Ediyor' && $task['status']!=='Tamamlandı'): ?>
      <form method="post" style="display:inline"><button class="btn" name="task_status" value="Devam Ediyor">▶ Başla</button></form>
    <?php endif; ?>
    <?php if($canEdit && $task['status']!=='Tamamlandı'): ?>
      <form method="post" style="display:inline"><button class="btn" name="task_status" value="Tamamlandı" style="background:#16a34a;color:#fff">✓ Tamamla</button></form>
    <?php endif; ?>
    <a class="btn ghost" href="mytasks.php">← İşlerim</a>
  </div>
</section>

<?php if($canEdit): ?>
<section class="panel">
  <details>
    <summary style="cursor:pointer;font-weight:800">✏️ Düzenle</summary>
    <form method="post" class="form-grid" style="margin-top:12px">
      <label class="full">Başlık *<input type="text" name="title" value="<?=h($task['title'])?>" required></label>
      <label class="full">Açıklama<textarea name="description" rows="3"><?=h($task['description']??'')?></textarea></label>
      <label>Termin Tarihi<input type="date" name="due_date" value="<?=h($task['due_date']??'')?>"></label>
      <label>Öncelik
        <select name="priority">
          <option<?=$task['priority']==='Normal'?' selected':''?>>Normal</option>
          <option<?=$task['priority']==='Yüksek'?' selected':''?>>Yüksek</option>
          <option<?=$task['priority']==='Acil'?' selected':''?>>Acil</option>
        </select>
      </label>
      <label>Durum
        <select name="status">
          <option<?=$task['status']==='Atandı'?' selected':''?>>Atandı</option>
          <option<?=$task['status']==='Devam Ediyor'?' selected':''?>>Devam Ediyor</option>
          <option<?=$task['status']==='Tamamlandı'?' selected':''?>>Tamamlandı</option>
          <option<?=$task['status']==='İptal'?' selected':''?>>İptal</option>
        </select>
      </label>
      <?php if($canReassign): ?>
      <label>Atanan Personel
        <select name="personnel_id">
          <option value="">— Personel —</option>
          <?php
          try{
            $pl=$pdo->query("SELECT id,name FROM personnel WHERE COALESCE(active,1)=1 ORDER BY name")->fetchAll();
            foreach($pl as $p) echo '<option value="'.(int)$p['id'].'"'.($task['personnel_id']==(int)$p['id']?' selected':'').'>'.h($p['name']).'</option>';
          }catch(Throwable $e){}
          ?>
        </select>
      </label>
      <?php endif; ?>
      <div class="full"><button class="btn dark" name="edit_task" style="width:100%">💾 Kaydet</button></div>
    </form>
  </details>
</section>
<?php endif; ?>

<?php if($canDelete): ?>
<section class="panel">
  <details>
    <summary style="cursor:pointer;font-weight:800;color:#dc2626">🗑 Sil</summary>
    <p class="muted" style="margin:12px 0">Görev silinecek (kalıcı olarak kaldırılmaz, listelerden gizlenir).</p>
    <form method="post" onsubmit="return confirm('Görev silinsin mi?')">
      <button class="btn" name="delete_task" style="background:#7f1d1d;color:#fff">🗑 Sil</button>
    </form>
  </details>
</section>
<?php endif; ?>

<section class="panel">
  <h3>💬 Yorumlar</h3>
  <?php if($canEdit): ?>
  <form method="post" class="form-grid">
    <textarea class="full" name="comment" rows="2" placeholder="Yorum ekle..." required></textarea>
    <div class="full"><button class="btn dark" name="comment_add">Ekle</button></div>
  </form>
  <?php endif; ?>
  <?php
  $comments=task_comments_list($pdo,$id);
  if(!$comments) echo '<p class="muted">Henüz yorum yok.</p>';
  foreach($comments as $c){
    echo '<div style="margin-top:10px;border-top:1px solid #eef2f6;padding-top:8px">';
    echo '<small class="muted">'.h($c['full_name']?:$c['username']?:'Sistem').' · '.h(date('d.m.Y H:i',strtotime($c['created_at']))).'</small>';
    echo '<div>'.nl2br(h($c['comment'])).'</div>';
    echo '</div>';
  }
  ?>
</section>

<section class="panel">
  <h3>📎 Dosyalar</h3>
  <?php if($canEdit): ?>
  <form method="post" enctype="multipart/form-data" class="form-grid">
    <input class="full" type="file" name="task_file" required>
    <div class="full"><button class="btn dark" name="file_upload">Yükle</button></div>
  </form>
  <?php endif; ?>
  <?php
  $files=task_files_list($pdo,$id);
  if(!$files) echo '<p class="muted">Henüz dosya eklenmemiş.</p>';
  foreach($files as $f){
    echo '<div style="display:flex;align-items:center;gap:8px;margin-top:10px;border-top:1px solid #eef2f6;padding-top:8px">';
    echo '<a href="'.h($f['file_path']).'" target="_blank" rel="noopener" style="flex:1">📄 '.h($f['original_name']).'</a>';
    if($canEdit) echo '<form method="post" style="margin:0" onsubmit="return confirm(\'Dosya silinsin mi?\')"><input type="hidden" name="file_delete" value="'.(int)$f['id'].'"><button class="btn ghost" style="color:#dc2626">🗑</button></form>';
    echo '</div>';
  }
  ?>
</section>

<section class="panel">
  <h3>📜 Geçmiş / Hareket Kayıtları</h3>
  <?php
  try{
    $logs = function_exists('activity_recent') ? activity_recent(50,'task',$id) : [];
  }catch(Throwable $e){ $logs=[]; }
  if(!$logs) echo '<p class="muted">Henüz kayıt yok.</p>';
  foreach($logs as $l){
    echo '<div style="display:flex;gap:8px;align-items:flex-start;margin-top:10px;border-top:1px solid #eef2f6;padding-top:8px">';
    echo '<span>'.h($l['icon']?:'•').'</span>';
    echo '<div style="flex:1;min-width:0"><b>'.h($l['action']).'</b> <small class="muted">'.h($l['user_name']?:'Sistem').'</small>';
    if($l['title']) echo '<br><small class="muted">'.h($l['title']).'</small>';
    echo '</div><small class="muted" style="white-space:nowrap">'.h(date('d.m H:i',strtotime($l['created_at']))).'</small>';
    echo '</div>';
  }
  ?>
</section>

<?php require_once __DIR__.'/layout_bottom.php'; ?>
