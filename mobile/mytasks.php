<?php
require_once 'common.php';
require_once __DIR__.'/../share_lib.php';
$pdo=db(); $me=(int)($_SESSION['user']['id']??0);
// Giriş yapan kullanıcının personel id'si
$pid=(int)($_SESSION['user']['personnel_id']??0);
if(!$pid){ try{ $r=$pdo->prepare("SELECT personnel_id FROM app_users WHERE id=?"); $r->execute([$me]); $pid=(int)($r->fetch()['personnel_id']??0); }catch(Throwable $e){} }

// Durum güncelle
if($_SERVER['REQUEST_METHOD']==='POST' && (int)($_POST['tid']??0)){
    try{
        $st=$_POST['task_status']??'';
        if(in_array($st,['Devam Ediyor','Tamamlandı'])){
            $pdo->prepare("UPDATE tasks SET status=?, completed_at=IF(?='Tamamlandı',NOW(),completed_at), started_at=IF(?='Devam Ediyor' AND started_at IS NULL,NOW(),started_at) WHERE id=?")
                ->execute([$st,$st,$st,(int)$_POST['tid']]);
            try{ if(function_exists('activity_log')) activity_log('Görev',$st,'#'.(int)$_POST['tid'],'','task',(int)$_POST['tid'],'mytasks.php',$st==='Tamamlandı'?'✅':'▶'); }catch(Throwable $e){}
        }
    }catch(Throwable $e){}
    header('Location: mytasks.php'.(!empty($_GET['f'])?'?f='.urlencode($_GET['f']):'')); exit;
}

$f=$_GET['f']??'open';
topx('Görevlerim');
?>
<div class="panel" style="display:flex;gap:8px;padding:10px">
  <a class="btn <?=$f==='open'?'dark':''?>" style="flex:1;text-align:center;<?=$f==='open'?'':'background:#334155;color:#fff'?>" href="mytasks.php?f=open">Açık</a>
  <a class="btn <?=$f==='done'?'dark':''?>" style="flex:1;text-align:center;<?=$f==='done'?'':'background:#334155;color:#fff'?>" href="mytasks.php?f=done">Tamamlanan</a>
  <?php if($isAdmin): ?><a class="btn" style="flex:1;text-align:center;background:#334155;color:#fff" href="task_new.php">+ Ata</a><?php endif; ?>
</div>
<?php
try{
  if(!$pid && !$isAdmin){ echo '<div class="panel muted" style="text-align:center">Sana bağlı personel kaydı yok.</div>'; }
  $w = $f==='done' ? "t.status='Tamamlandı'" : "t.status NOT IN ('Tamamlandı','İptal')";
  // Personel: kendi görevleri · Admin: herkesinki
  $cond = $isAdmin ? $w : "$w AND t.personnel_id=".(int)$pid;
  $rows=$pdo->query("SELECT t.*, j.id job_real, j.job_no, p.name pname, p.phone pphone FROM tasks t LEFT JOIN jobs j ON j.id=t.job_id LEFT JOIN personnel p ON p.id=t.personnel_id WHERE $cond ORDER BY (t.due_date IS NULL), t.due_date, t.id DESC LIMIT 100")->fetchAll();
  if(!$rows) echo '<div class="panel muted" style="text-align:center">'.($f==='done'?'Tamamlanan görev yok.':'Açık görev yok 🎉').'</div>';
  foreach($rows as $t){
    $geç = ($f!=='done' && !empty($t['due_date']) && $t['due_date']<date('Y-m-d'));
    echo '<div class="panel" style="padding:12px">';
    echo '<b>'.htmlspecialchars($t['title']).'</b>';
    if($isAdmin && $t['pname']) echo ' <span class="muted">· '.htmlspecialchars($t['pname']).'</span>';
    if($t['description']) echo '<br><small class="muted">'.htmlspecialchars($t['description']).'</small>';
    echo '<br><small class="muted">'.($t['job_no']?'📋 '.htmlspecialchars($t['job_no']).' · ':'').'Durum: '.htmlspecialchars($t['status']);
    if($t['due_date']) echo ' · 📅 '.htmlspecialchars($t['due_date']).($geç?' <span style="color:#f87171;font-weight:900">GECİKMİŞ</span>':'');
    echo '</small>';
    echo '<div style="display:flex;gap:8px;margin-top:10px;flex-wrap:wrap">';
    if($t['job_real']) echo '<a class="btn" href="job_view.php?id='.(int)$t['job_real'].'" style="background:#334155;color:#fff;flex:1;text-align:center">İş Detayı</a>';
    $tTxt="📝 Görev: ".$t['title'].($t['job_no']?"\nİş: ".$t['job_no']:'')."\nDurum: ".$t['status'].($t['due_date']?"\nTermin: ".$t['due_date']:'').($t['description']?"\n".$t['description']:'');
    echo '<a class="btn" href="'.htmlspecialchars(wa_link($tTxt,$t['pphone']??'')).'" target="_blank" rel="noopener" style="background:#16a34a;color:#fff;flex:1;text-align:center">📲 Gönder</a>';
    if($f!=='done'){
      if($t['status']!=='Devam Ediyor') echo '<form method="post" style="flex:1;margin:0"><input type="hidden" name="tid" value="'.(int)$t['id'].'"><button class="btn" name="task_status" value="Devam Ediyor" style="width:100%;background:#2563eb;color:#fff">▶ Başla</button></form>';
      echo '<form method="post" style="flex:1;margin:0"><input type="hidden" name="tid" value="'.(int)$t['id'].'"><button class="btn" name="task_status" value="Tamamlandı" style="width:100%;background:#16a34a;color:#fff">✓ Tamamla</button></form>';
    }
    echo '</div></div>';
  }
}catch(Throwable $e){ echo '<div class="err">'.htmlspecialchars($e->getMessage()).'</div>'; }
botx();
