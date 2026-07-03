<?php
require_once 'common.php';
require_once __DIR__.'/../share_lib.php';
require_once __DIR__.'/../notes_lib.php';
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

// Kişisel not — sadece giriş yapan kullanıcı görür (personal_notes.user_id ile sıkı filtreli,
// başka hiçbir ekran/personel bu tabloya bakmıyor). Kullanıcı isteği: "kendime görev-not alanı
// olsun, personel görmesin, takvime işlensin, bana WA+iç mesaj bildirimi olsun".
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['note_add'])){
    try{
        personal_note_create($pdo,$me,trim($_POST['note_title']??''),trim($_POST['note_body']??''),$_POST['note_due']?:null);
        $_SESSION['note_ok']='Not eklendi, bildirim gönderildi.';
    }catch(Throwable $e){ $_SESSION['note_err']=$e->getMessage(); }
    header('Location: mytasks.php'.(!empty($_GET['f'])?'?f='.urlencode($_GET['f']):'')); exit;
}
if($_SERVER['REQUEST_METHOD']==='POST' && (int)($_POST['note_id']??0)){
    try{
        if(isset($_POST['note_done'])) personal_note_set_status($pdo,(int)$_POST['note_id'],$me,'Tamamlandı');
        elseif(isset($_POST['note_del'])) personal_note_delete($pdo,(int)$_POST['note_id'],$me);
    }catch(Throwable $e){}
    header('Location: mytasks.php'.(!empty($_GET['f'])?'?f='.urlencode($_GET['f']):'')); exit;
}

$f=$_GET['f']??'open';
topx('Görevlerim');
?>

<div class="panel">
  <b>📝 Notlarım</b> <small class="muted">(sadece sen görürsün)</small>
  <?php if(!empty($_SESSION['note_ok'])): ?><div class="ok" style="margin-top:8px"><?=htmlspecialchars($_SESSION['note_ok'])?></div><?php unset($_SESSION['note_ok']); endif; ?>
  <?php if(!empty($_SESSION['note_err'])): ?><div class="err" style="margin-top:8px"><?=htmlspecialchars($_SESSION['note_err'])?></div><?php unset($_SESSION['note_err']); endif; ?>
  <form method="post" style="margin-top:10px">
    <input type="hidden" name="note_add" value="1">
    <input name="note_title" placeholder="Not başlığı" required>
    <textarea name="note_body" placeholder="Detay (opsiyonel)" rows="2"></textarea>
    <input type="date" name="note_due" placeholder="Termin (opsiyonel, takvime işlenir)">
    <button class="btn dark" style="width:100%;margin-top:6px">📝 Not Ekle</button>
  </form>
</div>
<?php
try{
  $myNotes=personal_notes_list($pdo,$me,'open');
  $myPhone=my_phone($pdo,$me);
  foreach($myNotes as $n){
    $waTxt="📝 Not: ".$n['title'].($n['note']?"\n".$n['note']:'').($n['due_date']?"\n📅 ".$n['due_date']:'');
    echo '<div class="panel" style="padding:12px;background:rgba(250,204,21,.08)">';
    echo '<b>'.htmlspecialchars($n['title']).'</b>';
    if($n['note']) echo '<br><small class="muted">'.htmlspecialchars($n['note']).'</small>';
    if($n['due_date']) echo '<br><small class="muted">📅 '.htmlspecialchars($n['due_date']).'</small>';
    echo '<div style="display:flex;gap:8px;margin-top:10px;flex-wrap:wrap">';
    if($myPhone) echo '<a class="btn" href="'.htmlspecialchars(wa_link($waTxt,$myPhone)).'" target="_blank" rel="noopener" style="background:#16a34a;color:#fff;flex:1;text-align:center">📲 WhatsApp</a>';
    echo '<form method="post" style="flex:1;margin:0"><input type="hidden" name="note_id" value="'.(int)$n['id'].'"><button class="btn" name="note_done" value="1" style="width:100%;background:#2563eb;color:#fff">✓ Tamamla</button></form>';
    echo '<form method="post" style="flex:1;margin:0" onsubmit="return confirm(\'Not silinsin mi?\')"><input type="hidden" name="note_id" value="'.(int)$n['id'].'"><button class="btn" name="note_del" value="1" style="width:100%;background:#7f1d1d;color:#fff">🗑 Sil</button></form>';
    echo '</div></div>';
  }
}catch(Throwable $e){}
?>

<div style="font-weight:900;margin:16px 4px 8px">✅ Görevlerim</div>
<div class="panel" style="display:flex;gap:8px;padding:10px">
  <a class="btn <?=$f==='open'?'dark':''?>" style="flex:1;text-align:center;<?=$f==='open'?'':'background:#334155;color:#fff'?>" href="mytasks.php?f=open">Açık</a>
  <a class="btn <?=$f==='done'?'dark':''?>" style="flex:1;text-align:center;<?=$f==='done'?'':'background:#334155;color:#fff'?>" href="mytasks.php?f=done">Tamamlanan</a>
  <?php if($isAdmin): ?><a class="btn" style="flex:1;text-align:center;background:#334155;color:#fff" href="task_new.php">+ Ata</a><?php endif; ?>
</div>
<?php
try{
  if(!$pid && !$isAdmin){ echo '<div class="panel muted" style="text-align:center">Sana bağlı personel kaydı yok.</div>'; }
  $w = $f==='done' ? "t.status='Tamamlandı'" : "t.status NOT IN ('Tamamlandı','İptal')";
  // "Görevlerim" = sadece sana atanan görevler. Admin dahil herkesin görevleri için tasks.php ("Tüm Görevler") kullanılır.
  $cond = "$w AND t.personnel_id=".(int)$pid;
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
    echo '<a class="btn" href="task_view.php?id='.(int)$t['id'].'" style="background:#667085;color:#fff;flex:1;text-align:center">📝 Detay</a>';
    if($t['job_real']) echo '<a class="btn" href="job_view.php?id='.(int)$t['job_real'].'" style="background:#334155;color:#fff;flex:1;text-align:center">📋 İş Detayı</a>';
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
