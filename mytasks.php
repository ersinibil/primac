<?php
// Görevlerim — kişisel görev listesi (sadece giriş yapan kullanıcıya atanan işler).
// (2026-07-13 terminoloji kararı: "İşlerim" adı "Görevlerim" olarak değiştirildi, dosya adı/route AYNI.)
// Web paritesi: mobil karşılığı mobile/mytasks.php. 'tasks' yetkisi İSTEMİYOR (page_module_map'te
// yok) — çünkü bir personelin kendine atanan görevi görebilmesi için 'tasks' modül yetkisine sahip
// olması gerekmiyor (tasks.php = TÜM görevler, admin/yetkili görünümü; burası sadece kendi görevi).
// Bildirim/mesaj action_url'i buraya işaret ediyor (bkz. task_new.php) — 'tasks.php' değil, çünkü
// atanan personelin 'tasks' yetkisi olmayabilir.
require_once __DIR__.'/boot.php';
require_once __DIR__.'/tasks_lib.php';
require_login();
$pdo=db();
$me=(int)($_SESSION['user']['id']??0);
$pid=task_my_personnel_id($pdo,$me);

// Durum güncelle — SADECE kendi (personnel_id eşleşen) görevi, başkasınınkini değiştiremesin
// (bu sayfa 'tasks' yetkisi istemediği için kasıtlı olarak kısıtlı).
if($_SERVER['REQUEST_METHOD']==='POST' && (int)($_POST['tid']??0)){
    $tid=(int)$_POST['tid'];
    try{
        $st=$_POST['task_status']??'';
        if(in_array($st,['Devam Ediyor','Tamamlandı']) && $pid){
            $pdo->prepare("UPDATE tasks SET status=?, completed_at=IF(?='Tamamlandı',NOW(),completed_at), started_at=IF(?='Devam Ediyor' AND started_at IS NULL,NOW(),started_at) WHERE id=? AND personnel_id=? AND deleted_at IS NULL")
                ->execute([$st,$st,$st,$tid,$pid]);
            try{ if(function_exists('activity_log')) activity_log('Görev',$st,'#'.$tid,'','task',$tid,'task_view.php?id='.$tid,$st==='Tamamlandı'?'✅':'▶'); }catch(Throwable $e){}
        }
        // Düzenle — sadece kendi görevi (oluşturan/atanan) ya da admin/edit_delete yetkili.
        if(isset($_POST['edit_task'])){
            $task=task_fetch($pdo,$tid);
            if($task && task_can_edit($task,$me,$pid)){
                task_apply_edit($pdo,$tid,$_POST,$me,task_can_reassign($task,$me));
            }
        }
        // Sil (soft delete) — sadece admin/edit_delete yetkili ya da görevi oluşturan/atanan.
        if(isset($_POST['delete_task'])){
            $task=task_fetch($pdo,$tid);
            if($task && task_can_delete($task,$me,$pid)){
                task_soft_delete($pdo,$tid,$me);
            }
        }
    }catch(Throwable $e){}
    header('Location: mytasks.php'.(!empty($_GET['f'])?'?f='.urlencode($_GET['f']):'')); exit;
}

$f=$_GET['f']??'open';
require_once __DIR__.'/layout_top.php';
?>
<?php
// PX-001A Visual Revision (2026-07-16, Product Owner kararı): "Kendime görev eklemek" ile
// "Personele görev atamak" farklı niyetler — biri diğerinin içine gizlenmez, ama ekranın birincil
// odağı "kendime hızlı ekleme" kalır. Admin-only "Personele Ata" (task_new.php, route/yetki AYNI)
// artık header'da küçük ama açık bir ghost buton; normal kullanıcı hiç görmüyor.
$__actions = is_admin() ? ds_button('Personele Ata', 'task_new.php', 'ghost', '', '', true) : '';
ds_page_header('Görevlerim', ds_icon('check',24), '', $__actions);

// Operasyon özeti — KPI kartı DEĞİL, sade sayı+etiket satırı (Bugün/Geciken/Tamamlanan). Salt
// okunur, tıklanamaz — yeni bir filtre/iş akışı eklemiyor, sadece durum farkındalığı.
// task_my_stats()/task_status_tone(): tasks_lib.php'de paylaşılıyor (Ece code-review notu —
// web/mobil aynı sorguyu/eşlemeyi ayrı ayrı yazmıştı).
$__stats = task_my_stats($pdo,$pid);
$__today=$__stats['today']; $__overdue=$__stats['overdue']; $__done=$__stats['done'];
?>
<div class="df-ops-summary">
<div class="df-ops-stat"><strong><?=$__today?></strong><span>Bugün</span></div>
<div class="df-ops-stat"><strong<?=$__overdue?' class="is-danger"':''?>><?=$__overdue?></strong><span>Geciken</span></div>
<div class="df-ops-stat"><strong><?=$__done?></strong><span>Tamamlanan</span></div>
</div>

<form method="post" action="mytask_new.php" class="df-quick-add df-quick-add--compact">
<input type="text" name="title" placeholder="Yeni görev başlığı yaz, Enter'a bas..." required maxlength="190">
<button type="submit" aria-label="Görev ekle"><?=ds_icon('plus',18)?></button>
</form>
<?php if(!empty($_GET['ok'])): ?><div class="ok">İş eklendi.</div><?php endif; ?>

<div class="df-toolbar-row">
<div class="df-tabs">
<a class="df-tab<?=$f==='open'?' df-tab--active':''?>" href="mytasks.php?f=open">Açık</a>
<a class="df-tab<?=$f==='done'?' df-tab--active':''?>" href="mytasks.php?f=done">Tamamlanan</a>
</div>
<div class="df-toolbar-links"><a href="tasks.php">Tüm Görevler</a></div>
</div>

<?php
try{
  if(!$pid && !is_admin()){ echo '<div class="df-panel" style="text-align:center;color:var(--df-ink-500)">Sana bağlı personel kaydı yok.</div>'; }
  $w = $f==='done' ? "t.status='Tamamlandı'" : "t.status NOT IN ('Tamamlandı','İptal')";
  $rows=$pdo->prepare("SELECT t.*, j.id job_real, j.job_no FROM tasks t LEFT JOIN jobs j ON j.id=t.job_id WHERE $w AND t.personnel_id=? AND t.deleted_at IS NULL ORDER BY (t.due_date IS NULL), t.due_date, t.id DESC LIMIT 100");
  $rows->execute([$pid]);
  $tasks=$rows->fetchAll();
  $pers=[];
  try{ $pers=$pdo->query("SELECT id,name FROM personnel WHERE COALESCE(active,1)=1 ORDER BY name")->fetchAll(); }catch(Throwable $e){}
  if(!$tasks):
?>
<div class="df-empty">
<div class="df-empty-icon"><?=ds_icon($f==='done'?'check':'calendar',20)?></div>
<div class="df-empty-title"><?=$f==='done'?'Tamamlanan görev yok.':'Açık görev yok'?></div>
</div>
<?php else: ?>
<div class="df-list">
<?php
  foreach($tasks as $t):
    $gec = ($f!=='done' && !empty($t['due_date']) && $t['due_date']<date('Y-m-d'));
    $canEdit = task_can_edit($t,$me,$pid);
    $canDel = task_can_delete($t,$me,$pid);
    $canReassign = task_can_reassign($t,$me);
    // PRODUCT DESIGN BLUEPRINT / mytasks.php sprinti (2026-07-16): tek bağlamsal birincil aksiyon
    // (Blueprint karar: Web/Tablet'te "duruma göre yalnızca bir" — Atandı→Başla, Devam Ediyor→
    // Tamamla; ikisi birden ASLA gösterilmez). PX-001A: etiketler artık ikonsuz düz metin (birincil
    // buton zaten vurgulu, ikon şart değil — "sessiz" ilke).
    $__primaryLabel = null; $__primaryValue = null; $__primaryTone = 'primary';
    if($f!=='done'){
        if($t['status']!=='Devam Ediyor'){ $__primaryLabel='Başla'; $__primaryValue='Devam Ediyor'; $__primaryTone='primary'; }
        else { $__primaryLabel='Tamamla'; $__primaryValue='Tamamlandı'; $__primaryTone='warn'; }
    }
    ?>
<div class="df-list-row" onclick="if(event.target.closest('a,button,form,details,summary'))return;location.href='task_view.php?id=<?=(int)$t['id']?>'">
<div class="df-list-row-body">
<div class="df-list-row-title"><?=h($t['title'])?></div>
<?php if($t['description']): ?><div class="df-list-row-desc"><?=nl2br(h($t['description']))?></div><?php endif; ?>
<div class="df-list-row-meta">
<?php
// PX-001A Visual Revision: durum artık sessiz metin değil, tonlu bir df-badge (Status Badge —
// Foundation'ın 4 rozet dilinden biri, ilk kez gerçek ekranda kullanılıyor). status_tone() mevcut
// yapıya ekleme yapmadan, sadece bu ekranın kendi küçük eşlemesiyle.
$__statusTone = task_status_tone($t['status']);
?>
<span class="df-badge df-badge--<?=h($__statusTone)?>"><?=h($t['status'])?></span>
<?php if($t['job_no']): ?><span>İş: <?=h($t['job_no'])?></span><?php endif; ?>
<?php if($t['priority'] && $t['priority']!=='Normal'): ?><?=ds_priority($t['priority'],$t['priority'])?><?php endif; ?>
<?php if($t['due_date']): ?><span class="df-list-row-due"><?=ds_icon('calendar',13)?> <?=h($t['due_date'])?></span><?php endif; ?>
<?php if($gec): ?><span style="color:var(--df-danger-ink);font-weight:600">Gecikti</span><?php endif; ?>
</div>
</div>
<div class="df-list-row-actions">
<?php if($__primaryLabel): ?><form method="post" style="display:inline"><input type="hidden" name="tid" value="<?=(int)$t['id']?>"><button type="submit" class="df-btn df-btn--sm df-btn--<?=h($__primaryTone)?>" name="task_status" value="<?=h($__primaryValue)?>"><?=h($__primaryLabel)?></button></form><?php endif; ?>
<?php if($t['job_real'] || $canEdit || $canDel): ?>
<details class="df-menu">
<summary aria-label="Diğer işlemler"><?=ds_icon('menu-dots',18)?></summary>
<div class="df-menu-body">
<a href="task_view.php?id=<?=(int)$t['id']?>">Detay</a>
<?php if($t['job_real']): ?><a href="job_view.php?id=<?=(int)$t['job_real']?>">İş Detayı</a><?php endif; ?>
<?php if($canEdit): ?><button type="button" onclick="this.closest('details').removeAttribute('open');var d=document.getElementById('tedit<?=(int)$t['id']?>');d.style.display=(d.style.display==='block')?'none':'block';">Düzenle</button><?php endif; ?>
<?php if($canDel): ?><form method="post" onsubmit="return confirm('Bu görev silinsin mi?')"><input type="hidden" name="tid" value="<?=(int)$t['id']?>"><button type="submit" name="delete_task" value="1" class="df-menu-danger">Sil</button></form><?php endif; ?>
</div>
</details>
<?php endif; ?>
</div>
</div>
<?php if($canEdit): ?>
<div id="tedit<?=(int)$t['id']?>" style="display:none;padding:var(--df-space-4);border-top:1px solid var(--df-hairline)">
<form method="post" class="form-grid">
<input type="hidden" name="tid" value="<?=(int)$t['id']?>">
<label class="full">Başlık *<input type="text" name="title" value="<?=h($t['title'])?>" required></label>
<label class="full">Açıklama<textarea name="description" rows="2"><?=h($t['description']??'')?></textarea></label>
<label>Termin Tarihi<input type="date" name="due_date" value="<?=h($t['due_date']??'')?>"></label>
<label>Öncelik
<select name="priority">
<option<?=$t['priority']==='Normal'?' selected':''?>>Normal</option>
<option<?=$t['priority']==='Yüksek'?' selected':''?>>Yüksek</option>
<option<?=$t['priority']==='Acil'?' selected':''?>>Acil</option>
</select>
</label>
<label>Durum
<select name="status">
<option<?=$t['status']==='Atandı'?' selected':''?>>Atandı</option>
<option<?=$t['status']==='Devam Ediyor'?' selected':''?>>Devam Ediyor</option>
<option<?=$t['status']==='Tamamlandı'?' selected':''?>>Tamamlandı</option>
<option<?=$t['status']==='İptal'?' selected':''?>>İptal</option>
</select>
</label>
<?php if($canReassign): ?>
<label>Atanan Personel
<select name="personnel_id">
<option value="">— Personel —</option>
<?php foreach($pers as $p): ?><option value="<?=(int)$p['id']?>"<?=$t['personnel_id']==(int)$p['id']?' selected':''?>><?=h($p['name'])?></option><?php endforeach; ?>
</select>
</label>
<?php endif; ?>
<div class="full"><button type="submit" class="df-btn df-btn--primary" name="edit_task" value="1" style="width:100%">Kaydet</button></div>
</form>
</div>
<?php endif; ?>
<?php endforeach; ?>
</div>
<?php endif; ?>
<?php }catch(Throwable $e){ echo '<div class="alert">'.h($e->getMessage()).'</div>'; } ?>

<?php require_once __DIR__.'/layout_bottom.php'; ?>
