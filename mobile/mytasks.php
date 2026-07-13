<?php
require_once 'common.php';
require_once __DIR__.'/../share_lib.php';
require_once __DIR__.'/../notes_lib.php';
require_once __DIR__.'/../tasks_lib.php';
$pdo=db(); $me=(int)($_SESSION['user']['id']??0);
// Giriş yapan kullanıcının personel id'si
$pid=task_my_personnel_id($pdo,$me);

// Durum güncelle — SADECE kendi (personnel_id eşleşen) görevi. Düzenle/Sil bilinçli olarak bu
// listede DEĞİL, task_view.php (Detay) ekranında — Mobil UX Standardı (PROJECT_RULES.md,
// "tekil aksiyonlar sadece Detay ekranında") + bildirimler modülüyle aynı desen.
if($_SERVER['REQUEST_METHOD']==='POST' && (int)($_POST['tid']??0)){
    $tid=(int)$_POST['tid'];
    try{
        $st=$_POST['task_status']??'';
        if(in_array($st,['Devam Ediyor','Tamamlandı']) && $pid){
            $pdo->prepare("UPDATE tasks SET status=?, completed_at=IF(?='Tamamlandı',NOW(),completed_at), started_at=IF(?='Devam Ediyor' AND started_at IS NULL,NOW(),started_at) WHERE id=? AND personnel_id=? AND deleted_at IS NULL")
                ->execute([$st,$st,$st,$tid,$pid]);
            try{ if(function_exists('activity_log')) activity_log('Görev',$st,'#'.$tid,'','task',$tid,'task_view.php?id='.$tid,$st==='Tamamlandı'?'✅':'▶'); }catch(Throwable $e){}
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
        elseif(isset($_POST['note_update'])) personal_note_update($pdo,(int)$_POST['note_id'],$me,trim($_POST['note_title']??''),trim($_POST['note_body']??''),$_POST['note_due']?:null);
    }catch(Throwable $e){}
    header('Location: mytasks.php'.(!empty($_GET['f'])?'?f='.urlencode($_GET['f']):'')); exit;
}

$f=$_GET['f']??'open';
// REOPEN-001: takvimden bir not öğesine tıklanınca ?date=YYYY-MM-DD taşınır — sadece o günün
// notları gösterilir. Hatalı/eksik formatta sessizce yok sayılır, normal davranışa düşer.
$date=(!empty($_GET['date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/',$_GET['date'])) ? $_GET['date'] : null;
topx('Görevlerim');
?>

<div class="panel">
  <b>📝 Notlarım<?=$date?' — '.htmlspecialchars(date('d.m.Y',strtotime($date))):''?></b> <small class="muted">(sadece sen görürsün)</small>
  <?php if($date): ?> <a href="mytasks.php" style="color:#93c5fd;font-size:12px;text-decoration:none">✕ Tümü</a><?php endif; ?>
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
  $myNotes=personal_notes_list($pdo,$me,'open',$date);
  $myPhone=my_phone($pdo,$me);
  foreach($myNotes as $n){
    $waTxt="📝 Not: ".$n['title'].($n['note']?"\n".$n['note']:'').($n['due_date']?"\n📅 ".$n['due_date']:'');
    echo '<div class="panel" style="padding:12px;background:rgba(250,204,21,.08)">';
    echo '<b>'.htmlspecialchars($n['title']).'</b>';
    if($n['note']) echo '<br><small class="muted">'.htmlspecialchars($n['note']).'</small>';
    if($n['due_date']) echo '<br><small class="muted">📅 '.htmlspecialchars($n['due_date']).'</small>';
    echo '<div style="display:flex;gap:8px;margin-top:10px;flex-wrap:wrap">';
    if($myPhone) echo '<a class="btn" href="'.htmlspecialchars(wa_link($waTxt,$myPhone)).'" target="_blank" rel="noopener" style="background:#16a34a;color:#fff;flex:1;text-align:center">📲 WhatsApp</a>';
    echo '<button type="button" class="btn" style="background:#f59e0b;color:#fff;flex:1;text-align:center" onclick="var d=document.getElementById(\'nedit'.(int)$n['id'].'\');d.style.display=(d.style.display===\'block\')?\'none\':\'block\';">✏️ Düzenle</button>';
    echo '<form method="post" style="flex:1;margin:0"><input type="hidden" name="note_id" value="'.(int)$n['id'].'"><button class="btn" name="note_done" value="1" style="width:100%;background:#2563eb;color:#fff">✓ Tamamla</button></form>';
    echo '<form method="post" style="flex:1;margin:0" onsubmit="return confirm(\'Not silinsin mi?\')"><input type="hidden" name="note_id" value="'.(int)$n['id'].'"><button class="btn" name="note_del" value="1" style="width:100%;background:#7f1d1d;color:#fff">🗑 Sil</button></form>';
    echo '</div>';
    // Düzenle formu — mobilde ayrı modal yerine kart içinde açılıp/kapanan panel (platform deseni),
    // Öncelik/Hatırlatma alanları personal_notes şemasında yok, bu iş kapsamında eklenmedi.
    echo '<div id="nedit'.(int)$n['id'].'" style="display:none;margin-top:10px;border-top:1px solid rgba(255,255,255,.12);padding-top:10px">';
    echo '<form method="post" style="margin:0">';
    echo '<input type="hidden" name="note_update" value="1">';
    echo '<input type="hidden" name="note_id" value="'.(int)$n['id'].'">';
    echo '<input name="note_title" value="'.htmlspecialchars($n['title']).'" placeholder="Not başlığı" required>';
    echo '<textarea name="note_body" rows="2" placeholder="Detay (opsiyonel)">'.htmlspecialchars($n['note']).'</textarea>';
    echo '<input type="date" name="note_due" value="'.htmlspecialchars($n['due_date']).'">';
    echo '<button class="btn dark" style="width:100%">💾 Kaydet</button>';
    echo '</form>';
    echo '</div>';
    echo '</div>';
  }
}catch(Throwable $e){}
?>

<div style="font-weight:900;margin:16px 4px 8px">✅ Görevlerim</div>
<div class="panel" style="display:flex;gap:8px;padding:10px;flex-wrap:wrap">
  <a class="btn <?=$f==='open'?'dark':''?>" style="flex:1;text-align:center;<?=$f==='open'?'':'background:#334155;color:#fff'?>" href="mytasks.php?f=open">Açık</a>
  <a class="btn <?=$f==='done'?'dark':''?>" style="flex:1;text-align:center;<?=$f==='done'?'':'background:#334155;color:#fff'?>" href="mytasks.php?f=done">Tamamlanan</a>
  <a class="btn" style="flex:1;text-align:center;background:#334155;color:#fff" href="mytask_new.php">+ Kendime İş Ekle</a>
  <?php if($isAdmin): ?><a class="btn" style="flex:1;text-align:center;background:#334155;color:#fff" href="task_new.php">+ İş Ekle</a><?php endif; ?>
</div>
<?php
try{
  if(!$pid && !$isAdmin){ echo '<div class="panel muted" style="text-align:center">Sana bağlı personel kaydı yok.</div>'; }
  $w = $f==='done' ? "t.status='Tamamlandı'" : "t.status NOT IN ('Tamamlandı','İptal')";
  // "Görevlerim" = sadece sana atanan işler. Admin dahil herkesin işleri için tasks.php ("Tüm Görevler") kullanılır.
  $rows=$pdo->prepare("SELECT t.*, j.id job_real, j.job_no, p.name pname, p.phone pphone FROM tasks t LEFT JOIN jobs j ON j.id=t.job_id LEFT JOIN personnel p ON p.id=t.personnel_id WHERE $w AND t.personnel_id=? AND t.deleted_at IS NULL ORDER BY (t.due_date IS NULL), t.due_date, t.id DESC LIMIT 100");
  $rows->execute([$pid]);
  $rows=$rows->fetchAll();
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
    echo '<a class="btn" href="task_view.php?id='.(int)$t['id'].'" style="background:#667085;color:#fff;flex:1;text-align:center">👁 Detay</a>';
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
