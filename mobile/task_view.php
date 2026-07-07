<?php
// Görev Detayı — "İşlerim" Detay ekranı (Mobil UX Standardı: tekil aksiyonlar — Düzenle/Sil —
// sadece burada, liste ekranında (mytasks.php/tasks.php) DEĞİL). Web paritesi: task_view.php.
require_once 'common.php';
require_once __DIR__.'/../tasks_lib.php';
require_once __DIR__.'/../checks_notes_lib.php';
$pdo=db();
$me=(int)($_SESSION['user']['id']??0);
$pid=task_my_personnel_id($pdo,$me);
$id=(int)($_GET['id']??0);
if($id<1){ topx('Hata'); echo '<div class="err">Görev bulunamadı.</div>'; botx(); exit; }

$task=task_fetch($pdo,$id);
if(!$task){ topx('Hata'); echo '<div class="err">Görev bulunamadı.</div>'; botx(); exit; }

$canEdit = task_can_edit($task,$me,$pid);
$canDelete = task_can_delete($task,$me,$pid);
$canReassign = task_can_reassign($task,$me);

// Çek/Senet Bilgileri kartı SADECE 'finance' yetkisi olana gösterilir — web task_view.php ile
// aynı gerekçe (2026-07-02 güvenlik denetimi): mobil task_view.php de bilinçli korumasız.
$canSeeFinance = user_can('finance');
$cn = $canSeeFinance ? checks_notes_get_by_task($pdo,$id) : null;

// POST: durum değiştir / düzenle / sil / yorum / dosya — hepsi topx() ÖNCESİ (PRG deseni).
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

topx($task['title']);
$geç = !empty($task['due_date']) && $task['due_date']<date('Y-m-d') && !in_array($task['status'],['Tamamlandı','İptal']);
?>
<?php if(!empty($_SESSION['task_err'])): ?><div class="err"><?=htmlspecialchars($_SESSION['task_err'])?></div><?php unset($_SESSION['task_err']); endif; ?>

<div class="panel" style="padding:12px">
  <h2 style="margin:0 0 6px"><?=htmlspecialchars($task['title'])?></h2>
  <?php if($task['description']): ?><p class="muted"><?=nl2br(htmlspecialchars($task['description']))?></p><?php endif; ?>
  <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin:12px 0;font-size:13px">
    <div><span class="muted">Durum</span><br><?=htmlspecialchars($task['status'])?></div>
    <div><span class="muted">Öncelik</span><br><?=htmlspecialchars($task['priority'])?></div>
    <div><span class="muted">Personel</span><br><?=htmlspecialchars($task['pname']??'-')?></div>
    <div><span class="muted">Termin</span><br><span style="color:<?=($geç?'#f87171':'inherit')?>"><?=htmlspecialchars($task['due_date']??'-').($geç?' ⏰':'')?></span></div>
    <?php if($task['job_no']): ?><div><span class="muted">İş No</span><br><?=htmlspecialchars($task['job_no'])?></div><?php endif; ?>
    <div><span class="muted">Oluşturan</span><br><?=htmlspecialchars($task['creator_name']?:$task['creator_username']?:'-')?></div>
    <?php if($task['updated_by']): ?><div><span class="muted">Son Güncelleyen</span><br><?=htmlspecialchars($task['updater_name']?:$task['updater_username']?:'-')?></div><?php endif; ?>
  </div>

  <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:12px">
    <?php if($task['job_real']): ?><a class="btn" href="job_view.php?id=<?=(int)$task['job_real']?>" style="background:#334155;color:#fff;flex:1">📋 İş Detayı</a><?php endif; ?>
    <?php if($canEdit && $task['status']!=='Devam Ediyor' && $task['status']!=='Tamamlandı'): ?>
      <form method="post" style="flex:1;margin:0"><button class="btn" name="task_status" value="Devam Ediyor" style="width:100%;background:#2563eb;color:#fff">▶ Başla</button></form>
    <?php endif; ?>
    <?php if($canEdit && $task['status']!=='Tamamlandı'): ?>
      <form method="post" style="flex:1;margin:0"><button class="btn dark" name="task_status" value="Tamamlandı" style="width:100%">✓ Tamamla</button></form>
    <?php endif; ?>
  </div>
</div>

<?php if($canSeeFinance && $cn):
  $cnTypeOpts=checks_notes_types();
  $cnStatusOpts=checks_notes_statuses($cn['direction'] ?? 'alinan');
  $cnIcon = $cn['type']==='senet' ? '📝' : '🧾';
  $cnOverdue = $cn['status']==='portfoyde' && $cn['due_date'] && $cn['due_date']<date('Y-m-d');
?>
<div class="panel" style="padding:12px">
  <h3 style="margin:0 0 6px"><?=$cnIcon?> Çek / Senet Bilgileri</h3>
  <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin:12px 0;font-size:13px">
    <div><span class="muted">Tür</span><br><?=htmlspecialchars($cnTypeOpts[$cn['type']] ?? $cn['type'])?></div>
    <div><span class="muted">Çek/Senet No</span><br><?=htmlspecialchars($cn['number'] ?: '-')?></div>
    <div><span class="muted">Cari</span><br><?=htmlspecialchars($cn['contact_name'] ?: 'Cari seçilmedi')?></div>
    <div><span class="muted">Banka</span><br><?=htmlspecialchars($cn['bank_name'] ?: '-')?></div>
    <div><span class="muted">Tutar</span><br><?=mm($cn['amount'])?></div>
    <div><span class="muted">Vade</span><br><span style="color:<?=($cnOverdue?'#f87171':'inherit')?>"><?=htmlspecialchars($cn['due_date'] ?: 'Vadesiz').($cnOverdue?' ⚠️':'')?></span></div>
    <div class="full" style="grid-column:1/-1"><span class="muted">Portföy Durumu</span><br><?=htmlspecialchars($cnStatusOpts[$cn['status']] ?? $cn['status'])?></div>
  </div>
  <?php if($cn['notes']): ?><p class="muted" style="margin-top:0"><?=nl2br(htmlspecialchars($cn['notes']))?></p><?php endif; ?>
  <?php if(!empty($cn['finance_movement_id'])): ?>
    <a class="btn" href="check_note_view.php?id=<?=(int)$cn['id']?>" style="display:block;text-align:center;background:#334155;color:#fff;margin-top:8px">💰 Finans Kaydına Git</a>
  <?php else: ?>
    <p class="muted" style="margin-top:8px">⚠️ Finans hareketi oluşturulamadı</p>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php if($canEdit): ?>
<details class="panel" style="padding:12px">
  <summary style="cursor:pointer;font-weight:900;user-select:none">✏️ Düzenle</summary>
  <form method="post" style="display:flex;flex-direction:column;gap:10px;margin-top:12px">
    <input type="text" name="title" value="<?=htmlspecialchars($task['title'])?>" placeholder="Başlık" required>
    <textarea name="description" placeholder="Açıklama" rows="3"><?=htmlspecialchars($task['description']??'')?></textarea>
    <input type="date" name="due_date" value="<?=htmlspecialchars($task['due_date']??'')?>">
    <select name="priority">
      <option<?=($task['priority']==='Normal'?' selected':'')?>>Normal</option>
      <option<?=($task['priority']==='Yüksek'?' selected':'')?>>Yüksek</option>
      <option<?=($task['priority']==='Acil'?' selected':'')?>>Acil</option>
    </select>
    <select name="status">
      <option<?=($task['status']==='Atandı'?' selected':'')?>>Atandı</option>
      <option<?=($task['status']==='Devam Ediyor'?' selected':'')?>>Devam Ediyor</option>
      <option<?=($task['status']==='Tamamlandı'?' selected':'')?>>Tamamlandı</option>
      <option<?=($task['status']==='İptal'?' selected':'')?>>İptal</option>
    </select>
    <?php if($canReassign): ?>
    <select name="personnel_id">
      <option value="">— Personel —</option>
      <?php
      try{
        $pl=$pdo->query("SELECT id,name FROM personnel WHERE COALESCE(active,1)=1 ORDER BY name")->fetchAll();
        foreach($pl as $p) echo '<option value="'.(int)$p['id'].'"'.($task['personnel_id']==(int)$p['id']?' selected':'').'>'.htmlspecialchars($p['name']).'</option>';
      }catch(Throwable $e){}
      ?>
    </select>
    <?php endif; ?>
    <button class="btn dark" name="edit_task" style="width:100%">💾 Kaydet</button>
  </form>
</details>
<?php endif; ?>

<?php if($canDelete): ?>
<details class="panel" style="padding:12px;border-color:#f87171">
  <summary style="cursor:pointer;font-weight:900;user-select:none;color:#f87171">🗑 Sil</summary>
  <p class="muted" style="margin:12px 0">Görev silinecek (kalıcı olarak kaldırılmaz, listelerden gizlenir).</p>
  <form method="post" onsubmit="return confirm('Görev silinsin mi?')">
    <button class="btn" name="delete_task" style="width:100%;background:#7f1d1d;color:#fff">🗑 Sil</button>
  </form>
</details>
<?php endif; ?>

<div class="panel" style="padding:12px">
  <b>💬 Yorumlar</b>
  <?php if($canEdit): ?>
  <form method="post" style="margin-top:10px">
    <textarea name="comment" rows="2" placeholder="Yorum ekle..." required></textarea>
    <button class="btn dark" name="comment_add" style="width:100%">Ekle</button>
  </form>
  <?php endif; ?>
  <?php
  $comments=task_comments_list($pdo,$id);
  if(!$comments) echo '<p class="muted" style="margin-top:10px">Henüz yorum yok.</p>';
  foreach($comments as $c){
    echo '<div style="margin-top:10px;border-top:1px solid rgba(255,255,255,.12);padding-top:8px">';
    echo '<small class="muted">'.htmlspecialchars($c['full_name']?:$c['username']?:'Sistem').' · '.htmlspecialchars(date('d.m.Y H:i',strtotime($c['created_at']))).'</small>';
    echo '<div>'.nl2br(htmlspecialchars($c['comment'])).'</div>';
    echo '</div>';
  }
  ?>
</div>

<div class="panel" style="padding:12px">
  <b>📎 Dosyalar</b>
  <?php if($canEdit): ?>
  <form method="post" enctype="multipart/form-data" style="margin-top:10px">
    <input type="file" name="task_file" required>
    <button class="btn dark" name="file_upload" style="width:100%">Yükle</button>
  </form>
  <?php endif; ?>
  <?php
  $files=task_files_list($pdo,$id);
  if(!$files) echo '<p class="muted" style="margin-top:10px">Henüz dosya eklenmemiş.</p>';
  foreach($files as $f){
    echo '<div style="display:flex;align-items:center;gap:8px;margin-top:10px;border-top:1px solid rgba(255,255,255,.12);padding-top:8px">';
    echo '<a href="../'.htmlspecialchars($f['file_path']).'" target="_blank" rel="noopener" style="flex:1;color:#93c5fd">📄 '.htmlspecialchars($f['original_name']).'</a>';
    if($canEdit) echo '<form method="post" style="margin:0" onsubmit="return confirm(\'Dosya silinsin mi?\')"><input type="hidden" name="file_delete" value="'.(int)$f['id'].'"><button class="btn" style="background:#7f1d1d;color:#fff;padding:8px 10px">🗑</button></form>';
    echo '</div>';
  }
  ?>
</div>

<div class="panel" style="padding:12px">
  <b>📜 Geçmiş / Hareket Kayıtları</b>
  <?php
  try{
    $logs = function_exists('activity_recent') ? activity_recent(50,'task',$id) : [];
  }catch(Throwable $e){ $logs=[]; }
  if(!$logs) echo '<p class="muted" style="margin-top:10px">Henüz kayıt yok.</p>';
  foreach($logs as $l){
    echo '<div style="display:flex;gap:8px;align-items:flex-start;margin-top:10px;border-top:1px solid rgba(255,255,255,.12);padding-top:8px">';
    echo '<span style="font-size:15px">'.htmlspecialchars($l['icon']?:'•').'</span>';
    echo '<div style="flex:1;min-width:0"><b>'.htmlspecialchars($l['action']).'</b> <small class="muted">'.htmlspecialchars($l['user_name']?:'Sistem').'</small>';
    if($l['title']) echo '<br><small class="muted">'.htmlspecialchars($l['title']).'</small>';
    echo '</div><small class="muted" style="white-space:nowrap">'.htmlspecialchars(date('d.m H:i',strtotime($l['created_at']))).'</small>';
    echo '</div>';
  }
  ?>
</div>

<?php botx(); ?>
