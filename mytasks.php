<?php
// İşlerim — kişisel görev listesi (sadece giriş yapan kullanıcıya atanan işler).
// Web paritesi: mobil karşılığı mobile/mytasks.php. 'tasks' yetkisi İSTEMİYOR (page_module_map'te
// yok) — çünkü bir personelin kendine atanan görevi görebilmesi için 'tasks' modül yetkisine sahip
// olması gerekmiyor (tasks.php = TÜM görevler, admin/yetkili görünümü; burası sadece kendi görevi).
// Bildirim/mesaj action_url'i buraya işaret ediyor (bkz. task_new.php) — 'tasks.php' değil, çünkü
// atanan personelin 'tasks' yetkisi olmayabilir.
require_once __DIR__.'/boot.php';
require_login();
$pdo=db();
$me=(int)($_SESSION['user']['id']??0);
$pid=(int)($_SESSION['user']['personnel_id']??0);
if(!$pid){ try{ $r=$pdo->prepare("SELECT personnel_id FROM app_users WHERE id=?"); $r->execute([$me]); $pid=(int)($r->fetch()['personnel_id']??0); }catch(Throwable $e){} }

// Durum güncelle — SADECE kendi (personnel_id eşleşen) görevi, başkasınınkini değiştiremesin
// (bu sayfa 'tasks' yetkisi istemediği için kasıtlı olarak kısıtlı).
if($_SERVER['REQUEST_METHOD']==='POST' && (int)($_POST['tid']??0)){
    try{
        $st=$_POST['task_status']??'';
        if(in_array($st,['Devam Ediyor','Tamamlandı']) && $pid){
            $pdo->prepare("UPDATE tasks SET status=?, completed_at=IF(?='Tamamlandı',NOW(),completed_at), started_at=IF(?='Devam Ediyor' AND started_at IS NULL,NOW(),started_at) WHERE id=? AND personnel_id=?")
                ->execute([$st,$st,$st,(int)$_POST['tid'],$pid]);
            try{ if(function_exists('activity_log')) activity_log('Görev',$st,'#'.(int)$_POST['tid'],'','task',(int)$_POST['tid'],'mytasks.php',$st==='Tamamlandı'?'✅':'▶'); }catch(Throwable $e){}
        }
    }catch(Throwable $e){}
    header('Location: mytasks.php'.(!empty($_GET['f'])?'?f='.urlencode($_GET['f']):'')); exit;
}

$f=$_GET['f']??'open';
require_once __DIR__.'/layout_top.php';
?>
<div class="panel-head"><h1>✅ İşlerim</h1></div>
<p class="muted">Sana atanan işler — tüm görevler için (yetkiniz varsa) <a href="tasks.php">Görevler</a> sayfasına bakın.</p>
<?php if(!empty($_GET['ok'])): ?><div class="ok">İş eklendi.</div><?php endif; ?>

<div class="filters">
<a href="mytasks.php?f=open" <?=$f==='open'?'style="background:#101828;color:#fff"':''?>>Açık</a>
<a href="mytasks.php?f=done" <?=$f==='done'?'style="background:#101828;color:#fff"':''?>>Tamamlanan</a>
<a href="mytask_new.php" style="margin-left:auto">+ Kendime İş Ekle</a>
<?php if(is_admin()): ?><a href="task_new.php">+ İş Ekle</a><?php endif; ?>
</div>

<?php
try{
  if(!$pid && !is_admin()){ echo '<div class="panel" style="text-align:center;color:#667085">Sana bağlı personel kaydı yok.</div>'; }
  $w = $f==='done' ? "t.status='Tamamlandı'" : "t.status NOT IN ('Tamamlandı','İptal')";
  $rows=$pdo->prepare("SELECT t.*, j.id job_real, j.job_no FROM tasks t LEFT JOIN jobs j ON j.id=t.job_id WHERE $w AND t.personnel_id=? ORDER BY (t.due_date IS NULL), t.due_date, t.id DESC LIMIT 100");
  $rows->execute([$pid]);
  $tasks=$rows->fetchAll();
  if(!$tasks) echo '<div class="panel" style="text-align:center;color:#667085">'.($f==='done'?'Tamamlanan görev yok.':'Açık görev yok 🎉').'</div>';
  foreach($tasks as $t):
    $gec = ($f!=='done' && !empty($t['due_date']) && $t['due_date']<date('Y-m-d'));
?>
<div class="panel" style="margin-bottom:12px">
<b><?=h($t['title'])?></b>
<?php if($t['description']): ?><div class="muted" style="margin-top:4px"><?=nl2br(h($t['description']))?></div><?php endif; ?>
<div class="muted" style="margin-top:4px">
<?php if($t['job_no']): ?>📋 <?=h($t['job_no'])?> · <?php endif; ?>Durum: <?=h($t['status'])?>
<?php if($t['due_date']): ?> · 📅 <?=h($t['due_date'])?><?php if($gec): ?> <span style="color:#dc2626;font-weight:800">GECİKMİŞ</span><?php endif; endif; ?>
</div>
<div class="actions" style="margin-top:10px">
<?php if($t['job_real']): ?><a class="btn small" href="job_view.php?id=<?=(int)$t['job_real']?>">📋 İş Detayı</a><?php endif; ?>
<?php if($f!=='done'): ?>
<?php if($t['status']!=='Devam Ediyor'): ?><form method="post" style="display:inline"><input type="hidden" name="tid" value="<?=(int)$t['id']?>"><button class="btn small" name="task_status" value="Devam Ediyor">▶ Başla</button></form><?php endif; ?>
<form method="post" style="display:inline"><input type="hidden" name="tid" value="<?=(int)$t['id']?>"><button class="btn small" name="task_status" value="Tamamlandı" style="background:#16a34a;color:#fff">✓ Tamamla</button></form>
<?php endif; ?>
</div>
</div>
<?php endforeach; ?>
<?php }catch(Throwable $e){ echo '<div class="alert">'.h($e->getMessage()).'</div>'; } ?>

<?php require_once __DIR__.'/layout_bottom.php'; ?>
