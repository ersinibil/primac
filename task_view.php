<?php
// Görev Detayı (web) — "Görevlerim" Detay ekranı. Mobil paritesi: mobile/task_view.php.
// GET bilinçli olarak KORUMASIZ (job_view.php ile aynı desen, boot.php page_module_map yorumu) —
// personel kendine atanan görevi bildirimden/mesajdan açabilsin diye. Yazma işlemleri (durum/
// düzenle/sil/yorum/dosya) task_can_edit()/task_can_delete() ile sahiplik kontrollüdür.
require_once __DIR__.'/boot.php';
require_login();
require_once __DIR__.'/tasks_lib.php';
require_once __DIR__.'/checks_notes_lib.php';
$pdo=db();
$me=(int)($_SESSION['user']['id']??0);
$pid=task_my_personnel_id($pdo,$me);
$id=(int)($_GET['id']??0);

if($id<1){ require_once __DIR__.'/layout_top.php'; echo ds_alert('danger','Görev bulunamadı.'); require_once __DIR__.'/layout_bottom.php'; exit; }

$task=task_fetch($pdo,$id);
if(!$task){ require_once __DIR__.'/layout_top.php'; echo ds_alert('danger','Görev bulunamadı.'); require_once __DIR__.'/layout_bottom.php'; exit; }

$canEdit = task_can_edit($task,$me,$pid);
$canDelete = task_can_delete($task,$me,$pid);
$canReassign = task_can_reassign($task,$me);

// Çek/Senet Bilgileri kartı SADECE 'finance' yetkisi olana gösterilir (2026-07-02 güvenlik
// denetimi ile aynı gerekçe: task_view.php bilinçli olarak page_module_map() dışında/korumasız —
// personel kendine atanan/genel görevi bildirimden açabilsin diye. 'tasks' yetkisi olup 'finance'
// yetkisi olmayan biri bu ekrandan cari/banka/tutar görmemeli).
$canSeeFinance = user_can('finance');
$cn = $canSeeFinance ? checks_notes_get_by_task($pdo,$id) : null;

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
$__tone = task_status_tone($task['status']);
$__showStart = $canEdit && $task['status']!=='Devam Ediyor' && $task['status']!=='Tamamlandı';
$__showComplete = $canEdit && $task['status']!=='Tamamlandı';
?>
<?php
// PX-001B (2026-07-16): header artık sadece navigasyon taşır (Ek Karar 1 — bilgi aksiyondan
// önce gelmeli); Başla/Tamamla bilgi panelinin altındaki .df-task-actions şeridine taşındı.
$__actions = ds_button('← Görevlerim', 'mytasks.php', 'ghost', '', '', true);
ds_page_header($task['title'], ds_icon('check',24), '', $__actions);
?>
<?php if(!empty($_SESSION['task_err'])): ?><?=ds_alert('danger',$_SESSION['task_err'])?><?php unset($_SESSION['task_err']); endif; ?>

<div class="df-task-stack">

<section class="df-panel">
  <?php if($task['description']): ?><p class="df-task-desc"><?=nl2br(h($task['description']))?></p><?php endif; ?>
  <div class="df-task-primary">
    <span class="df-badge df-badge--<?=h($__tone)?>"><?=h($task['status'])?></span>
    <?php if($task['due_date']): ?><span class="df-task-due<?=($gec?' is-overdue':'')?>"><?=ds_icon('calendar',13)?> <?=h($task['due_date'])?><?=($gec?' · Gecikti':'')?></span><?php endif; ?>
  </div>
  <div class="df-task-meta">
    <?php if($task['priority'] && $task['priority']!=='Normal'): ?><span><?=ds_priority($task['priority'],$task['priority'])?></span><?php endif; ?>
    <span>Personel: <?=h($task['pname']??'-')?></span>
    <?php if($task['job_no']): ?><span>İş: <a href="job_view.php?id=<?=(int)$task['job_real']?>"><?=h($task['job_no'])?></a></span><?php endif; ?>
    <span>Oluşturan: <?=h($task['creator_name']?:$task['creator_username']?:'-')?></span>
    <?php if($task['updated_by']): ?><span>Güncelleyen: <?=h($task['updater_name']?:$task['updater_username']?:'-')?></span><?php endif; ?>
  </div>
</section>

<?php if($__showStart || $__showComplete): ?>
<div class="df-task-actions">
  <?php if($__showStart): ?><form method="post"><button type="submit" class="df-btn df-btn--primary" name="task_status" value="Devam Ediyor">Başla</button></form><?php endif; ?>
  <?php if($__showComplete): ?><form method="post"><button type="submit" class="df-btn df-btn--warn" name="task_status" value="Tamamlandı"><?=ds_icon('check',15)?> Tamamla</button></form><?php endif; ?>
</div>
<?php endif; ?>

<?php if($canSeeFinance && $cn):
  $cnTypeOpts=checks_notes_types();
  $cnStatusOpts=checks_notes_statuses($cn['direction'] ?? 'alinan');
  $cnStatusTone=ds_tone_map(checks_notes_status_tone($cn['status']));
?>
<section class="df-panel">
  <h3 class="df-text-subtitle" style="margin:0 0 10px">Çek / Senet Bilgileri</h3>
  <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin-bottom:12px;font-size:14px">
    <div><span class="df-text-caption">Tür</span><br><?=h($cnTypeOpts[$cn['type']] ?? $cn['type'])?></div>
    <div><span class="df-text-caption">Çek/Senet No</span><br><?=h($cn['number'] ?: '-')?></div>
    <div><span class="df-text-caption">Cari</span><br><?=h($cn['contact_name'] ?: 'Cari seçilmedi')?></div>
    <div><span class="df-text-caption">Banka</span><br><?=h($cn['bank_name'] ?: '-')?></div>
    <div><span class="df-text-caption">Tutar</span><br><?=money($cn['amount'])?></div>
    <div><span class="df-text-caption">Vade</span><br><?=h($cn['due_date'] ?: 'Vadesiz')?></div>
    <div><span class="df-text-caption">Portföy Durumu</span><br><span class="df-badge df-badge--<?=h($cnStatusTone)?>"><?=h($cnStatusOpts[$cn['status']] ?? $cn['status'])?></span></div>
  </div>
  <?php if($cn['notes']): ?><p class="df-task-desc"><?=nl2br(h($cn['notes']))?></p><?php endif; ?>
  <?php if(!empty($cn['finance_movement_id'])): ?>
    <a class="df-btn df-btn--ghost" href="checks_notes.php?open=<?=(int)$cn['id']?>">Finans Kaydına Git</a>
  <?php else: ?>
    <span class="df-text-caption">Finans hareketi oluşturulamadı</span>
  <?php endif; ?>
</section>
<?php endif; ?>

<?php if($canEdit): ?>
<section class="df-panel">
  <details>
    <summary style="cursor:pointer;font-weight:700">Düzenle</summary>
    <form method="post" class="df-form-grid-2" style="margin-top:12px">
      <div class="df-form-span-2"><?php ds_form_field('Başlık *', '<input type="text" name="title" value="'.h($task['title']).'" required>'); ?></div>
      <div class="df-form-span-2"><?php ds_form_field('Açıklama', '<textarea name="description" rows="3">'.h($task['description']??'').'</textarea>'); ?></div>
      <?php ds_form_field('Termin Tarihi', '<input type="date" name="due_date" value="'.h($task['due_date']??'').'">'); ?>
      <?php
      $__prOpts='';
      foreach(['Normal','Yüksek','Acil'] as $__pr){ $__prOpts.='<option'.($task['priority']===$__pr?' selected':'').'>'.$__pr.'</option>'; }
      ds_form_field('Öncelik', '<select name="priority">'.$__prOpts.'</select>');
      $__stOpts='';
      foreach(['Atandı','Devam Ediyor','Tamamlandı','İptal'] as $__st){ $__stOpts.='<option'.($task['status']===$__st?' selected':'').'>'.$__st.'</option>'; }
      ds_form_field('Durum', '<select name="status">'.$__stOpts.'</select>');
      ?>
      <?php if($canReassign):
        $__persOpts='<option value="">— Personel —</option>';
        try{
          $pl=$pdo->query("SELECT id,name FROM personnel WHERE COALESCE(active,1)=1 ORDER BY name")->fetchAll();
          foreach($pl as $p) $__persOpts.='<option value="'.(int)$p['id'].'"'.($task['personnel_id']==(int)$p['id']?' selected':'').'>'.h($p['name']).'</option>';
        }catch(Throwable $e){}
        ds_form_field('Atanan Personel', '<select name="personnel_id">'.$__persOpts.'</select>');
      endif; ?>
      <div class="df-form-span-2"><button type="submit" class="df-btn df-btn--primary" name="edit_task" style="width:100%">Kaydet</button></div>
    </form>
  </details>
</section>
<?php endif; ?>

<?php if($canDelete): ?>
<section class="df-panel">
  <details>
    <summary style="cursor:pointer;font-weight:700;color:var(--df-danger-ink)">Sil</summary>
    <p class="df-text-caption" style="margin:12px 0">Görev silinecek (kalıcı olarak kaldırılmaz, listelerden gizlenir).</p>
    <form method="post" onsubmit="return confirm('Görev silinsin mi?')">
      <button type="submit" class="df-btn df-btn--danger"><?=ds_icon('trash',15)?> Sil</button>
    </form>
  </details>
</section>
<?php endif; ?>

<section class="df-panel">
  <h3 class="df-text-subtitle" style="margin:0 0 10px">Yorumlar</h3>
  <?php if($canEdit): ?>
  <form method="post">
    <textarea name="comment" rows="2" placeholder="Yorum ekle..." required></textarea>
    <button type="submit" class="df-btn df-btn--primary" name="comment_add" style="margin-top:8px">Ekle</button>
  </form>
  <?php endif; ?>
  <?php
  $comments=task_comments_list($pdo,$id);
  if(!$comments) echo '<p class="df-text-caption" style="margin-top:10px">Henüz yorum yok.</p>';
  foreach($comments as $c){
    echo '<div style="margin-top:10px;border-top:1px solid var(--df-hairline);padding-top:8px">';
    echo '<small class="df-text-caption">'.h($c['full_name']?:$c['username']?:'Sistem').' · '.h(date('d.m.Y H:i',strtotime($c['created_at']))).'</small>';
    echo '<div>'.nl2br(h($c['comment'])).'</div>';
    echo '</div>';
  }
  ?>
</section>

<section class="df-panel">
  <h3 class="df-text-subtitle" style="margin:0 0 10px">Dosyalar</h3>
  <?php if($canEdit): ?>
  <form method="post" enctype="multipart/form-data">
    <input type="file" name="task_file" required>
    <button type="submit" class="df-btn df-btn--primary" name="file_upload" style="margin-top:8px">Yükle</button>
  </form>
  <?php endif; ?>
  <?php
  $files=task_files_list($pdo,$id);
  if(!$files) echo '<p class="df-text-caption" style="margin-top:10px">Henüz dosya eklenmemiş.</p>';
  foreach($files as $f){
    echo '<div style="display:flex;align-items:center;gap:8px;margin-top:10px;border-top:1px solid var(--df-hairline);padding-top:8px">';
    echo '<a href="'.h($f['file_path']).'" target="_blank" rel="noopener" style="flex:1">'.h($f['original_name']).'</a>';
    if($canEdit) echo '<form method="post" style="margin:0" onsubmit="return confirm(\'Dosya silinsin mi?\')"><input type="hidden" name="file_delete" value="'.(int)$f['id'].'"><button type="submit" class="df-icon-btn" aria-label="Dosyayı sil" style="color:var(--df-danger-ink)">'.ds_icon('trash',15).'</button></form>';
    echo '</div>';
  }
  ?>
</section>

<section class="df-panel">
  <h3 class="df-text-subtitle" style="margin:0 0 10px">Geçmiş / Hareket Kayıtları</h3>
  <?php
  try{
    $logs = function_exists('activity_recent') ? activity_recent(50,'task',$id) : [];
  }catch(Throwable $e){ $logs=[]; }
  if(!$logs) echo '<p class="df-text-caption" style="margin-top:10px">Henüz kayıt yok.</p>';
  foreach($logs as $l){
    echo '<div style="display:flex;gap:8px;align-items:flex-start;margin-top:10px;border-top:1px solid var(--df-hairline);padding-top:8px">';
    echo '<span>'.h($l['icon']?:'•').'</span>';
    echo '<div style="flex:1;min-width:0"><b>'.h($l['action']).'</b> <small class="df-text-caption">'.h($l['user_name']?:'Sistem').'</small>';
    if($l['title']) echo '<br><small class="df-text-caption">'.h($l['title']).'</small>';
    echo '</div><small class="df-text-caption" style="white-space:nowrap">'.h(date('d.m H:i',strtotime($l['created_at']))).'</small>';
    echo '</div>';
  }
  ?>
</section>

</div>
<style>
body.nav-compact .df-form-grid-2{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:0 var(--df-space-4)}
body.nav-compact .df-form-span-2{grid-column:1 / -1}
@media(max-width:640px){body.nav-compact .df-form-grid-2{grid-template-columns:1fr}}
</style>

<?php require_once __DIR__.'/layout_bottom.php'; ?>
