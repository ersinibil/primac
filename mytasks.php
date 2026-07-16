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
// UX-001 (2026-07-16): "+ Kendime İş Ekle" bu ekranın birincil aksiyonu — filtre satırına gömülü
// metin-linkten header'ın birincil butonuna taşındı (fonksiyon/hedef aynı, sadece konum+ağırlık).
$__actions = ds_button('+ Kendime İş Ekle', 'mytask_new.php', 'accent');
if(is_admin()) $__actions .= ds_button('+ İş Ekle', 'task_new.php', 'secondary');
ds_page_header('✅ Görevlerim', '', '', $__actions);
?>
<p class="muted">Bana atanan görevler ve hatırlatmalar — tüm görevler için (yetkiniz varsa) <a href="tasks.php">Görevler</a> sayfasına bakın.</p>
<?php if(!empty($_GET['ok'])): ?><div class="ok">İş eklendi.</div><?php endif; ?>

<div class="filters">
<a href="mytasks.php?f=open" <?=$f==='open'?'style="background:#101828;color:#fff"':''?>>Açık</a>
<a href="mytasks.php?f=done" <?=$f==='done'?'style="background:#101828;color:#fff"':''?>>Tamamlanan</a>
</div>

<?php
try{
  if(!$pid && !is_admin()){ echo '<div class="panel" style="text-align:center;color:#667085">Sana bağlı personel kaydı yok.</div>'; }
  $w = $f==='done' ? "t.status='Tamamlandı'" : "t.status NOT IN ('Tamamlandı','İptal')";
  $rows=$pdo->prepare("SELECT t.*, j.id job_real, j.job_no FROM tasks t LEFT JOIN jobs j ON j.id=t.job_id WHERE $w AND t.personnel_id=? AND t.deleted_at IS NULL ORDER BY (t.due_date IS NULL), t.due_date, t.id DESC LIMIT 100");
  $rows->execute([$pid]);
  $tasks=$rows->fetchAll();
  $pers=[];
  try{ $pers=$pdo->query("SELECT id,name FROM personnel WHERE COALESCE(active,1)=1 ORDER BY name")->fetchAll(); }catch(Throwable $e){}
  if(!$tasks) echo '<div class="panel" style="text-align:center;color:#667085">'.($f==='done'?'Tamamlanan görev yok.':'Açık görev yok 🎉').'</div>';
  foreach($tasks as $t):
    $gec = ($f!=='done' && !empty($t['due_date']) && $t['due_date']<date('Y-m-d'));
    $canEdit = task_can_edit($t,$me,$pid);
    $canDel = task_can_delete($t,$me,$pid);
    $canReassign = task_can_reassign($t,$me);
?>
<div class="panel" style="margin-bottom:12px">
<b><?=h($t['title'])?></b>
<?php if($t['description']): ?><div class="muted" style="margin-top:4px"><?=nl2br(h($t['description']))?></div><?php endif; ?>
<div class="muted" style="margin-top:4px">
<?php if($t['job_no']): ?>📋 <?=h($t['job_no'])?> · <?php endif; ?>Durum: <?=h($t['status'])?>
<?php if($t['due_date']): ?> · 📅 <?=h($t['due_date'])?><?php if($gec): ?> <span style="color:#dc2626;font-weight:800">GECİKMİŞ</span><?php endif; endif; ?>
</div>
<div class="actions" style="margin-top:10px">
<a class="btn small" href="task_view.php?id=<?=(int)$t['id']?>">👁 Detay</a>
<?php if($t['job_real']): ?><a class="btn small" href="job_view.php?id=<?=(int)$t['job_real']?>">📋 İş Detayı</a><?php endif; ?>
<?php if($f!=='done'): ?>
<?php if($t['status']!=='Devam Ediyor'): ?><form method="post" style="display:inline"><input type="hidden" name="tid" value="<?=(int)$t['id']?>"><button class="btn small" name="task_status" value="Devam Ediyor">▶ Başla</button></form><?php endif; ?>
<form method="post" style="display:inline"><input type="hidden" name="tid" value="<?=(int)$t['id']?>"><button class="btn small" name="task_status" value="Tamamlandı" style="background:#16a34a;color:#fff">✓ Tamamla</button></form>
<?php endif; ?>
<?php if($canEdit): ?><button type="button" class="btn small" onclick="var d=document.getElementById('tedit<?=(int)$t['id']?>');d.style.display=(d.style.display==='block')?'none':'block';">✏ Düzenle</button><?php endif; ?>
<?php if($canDel): ?><form method="post" style="display:inline" onsubmit="return confirm('Bu görev silinsin mi?')"><input type="hidden" name="tid" value="<?=(int)$t['id']?>"><button class="btn small" name="delete_task" value="1" style="background:#7f1d1d;color:#fff">🗑 Sil</button></form><?php endif; ?>
</div>
<?php if($canEdit): ?>
<div id="tedit<?=(int)$t['id']?>" style="display:none;margin-top:12px;border-top:1px solid #eef2f6;padding-top:12px">
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
<div class="full"><button class="btn dark" name="edit_task" value="1" style="width:100%">💾 Kaydet</button></div>
</form>
</div>
<?php endif; ?>
</div>
<?php endforeach; ?>
<?php }catch(Throwable $e){ echo '<div class="alert">'.h($e->getMessage()).'</div>'; } ?>

<?php require_once __DIR__.'/layout_bottom.php'; ?>
