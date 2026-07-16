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
// PRODUCT DESIGN BLUEPRINT / mytasks.php sprinti (2026-07-16, Ece code-review notu): kart artık
// hiçbir aksiyon butonu içermediği için bu handler UI'dan bir daha hiç tetiklenmiyor — durum
// değişikliği artık sadece task_view.php'nin kendi (aynı mantıktaki) handler'ından yapılıyor.
// Kod BİLEREK silinmedi (backend/route/POST hedefleri değişmeyecek kısıtı) — zararsız, ölü kod.
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

<div class="df-panel">
  <div class="df-list-row-title" style="display:flex;align-items:center;justify-content:space-between;gap:8px">
    <span>Notlarım<?=$date?' — '.htmlspecialchars(date('d.m.Y',strtotime($date))):''?> <span class="df-text-caption">(sadece sen görürsün)</span></span>
    <?php if($date): ?><a href="mytasks.php" class="df-text-caption" style="color:var(--df-accent-soft-ink)"><?=ds_icon('close',13)?> Tümü</a><?php endif; ?>
  </div>
  <?php if(!empty($_SESSION['note_ok'])): ?><div class="ok" style="margin-top:8px"><?=htmlspecialchars($_SESSION['note_ok'])?></div><?php unset($_SESSION['note_ok']); endif; ?>
  <?php if(!empty($_SESSION['note_err'])): ?><div class="err" style="margin-top:8px"><?=htmlspecialchars($_SESSION['note_err'])?></div><?php unset($_SESSION['note_err']); endif; ?>
  <form method="post" style="margin-top:10px">
    <input type="hidden" name="note_add" value="1">
    <input name="note_title" placeholder="Not başlığı" required>
    <textarea name="note_body" placeholder="Detay (opsiyonel)" rows="2"></textarea>
    <input type="date" name="note_due" placeholder="Termin (opsiyonel, takvime işlenir)">
    <button type="submit" class="df-btn df-btn--primary" style="width:100%;margin-top:6px">Not Ekle</button>
  </form>
</div>
<?php
try{
  $myNotes=personal_notes_list($pdo,$me,'open',$date);
  $myPhone=my_phone($pdo,$me);
  foreach($myNotes as $n){
    $waTxt="Not: ".$n['title'].($n['note']?"\n".$n['note']:'').($n['due_date']?"\nTermin: ".$n['due_date']:'');
    echo '<div class="df-panel" style="margin-top:10px">';
    echo '<div class="df-list-row-title">'.htmlspecialchars($n['title']).'</div>';
    if($n['note']) echo '<div class="df-list-row-desc">'.htmlspecialchars($n['note']).'</div>';
    if($n['due_date']) echo '<div class="df-list-row-meta">'.ds_icon('calendar',13).' '.htmlspecialchars($n['due_date']).'</div>';
    // PX-001A düzeltme (statik önizleme sırasında bulundu): 4 buton tek satırda flex:1 metinli
    // buton olarak taşıyordu — flex item'ların varsayılan min-width:auto'su (içerik genişliği)
    // 390px'lik telefon ekranında satırı taşırıyordu. Hiyerarşi de kazandı: birincil eylem
    // (Tamamla) artık kendi tam-genişlik satırında, üç ikincil eylem altında sabit-boyutlu
    // ikon-only satırda (aynı 4 fonksiyon, aynı POST hedefleri — sadece yerleşim/görsel).
    echo '<form method="post" style="margin-top:10px"><input type="hidden" name="note_id" value="'.(int)$n['id'].'"><button type="submit" class="df-btn df-btn--primary" name="note_done" value="1" style="width:100%;justify-content:center">'.ds_icon('check',15).' Tamamla</button></form>';
    echo '<div style="display:flex;gap:8px;margin-top:8px">';
    if($myPhone) echo '<a class="df-btn df-btn--secondary" href="'.htmlspecialchars(wa_link($waTxt,$myPhone)).'" target="_blank" rel="noopener" style="width:40px;height:36px;padding:0;flex:0 0 auto" aria-label="WhatsApp\'ta gönder">'.ds_icon('send',15).'</a>';
    echo '<button type="button" class="df-btn df-btn--ghost" style="width:40px;height:36px;padding:0;flex:0 0 auto" aria-label="Düzenle" onclick="var d=document.getElementById(\'nedit'.(int)$n['id'].'\');d.style.display=(d.style.display===\'block\')?\'none\':\'block\';">'.ds_icon('edit',15).'</button>';
    echo '<form method="post" style="margin:0" onsubmit="return confirm(\'Not silinsin mi?\')"><input type="hidden" name="note_id" value="'.(int)$n['id'].'"><button type="submit" class="df-btn df-btn--danger" name="note_del" value="1" style="width:40px;height:36px;padding:0" aria-label="Sil">'.ds_icon('trash',15).'</button></form>';
    echo '</div>';
    // Düzenle formu — mobilde ayrı modal yerine kart içinde açılıp/kapanan panel (platform deseni),
    // Öncelik/Hatırlatma alanları personal_notes şemasında yok, bu iş kapsamında eklenmedi.
    echo '<div id="nedit'.(int)$n['id'].'" style="display:none;margin-top:10px;border-top:1px solid var(--df-hairline);padding-top:10px">';
    echo '<form method="post" style="margin:0">';
    echo '<input type="hidden" name="note_update" value="1">';
    echo '<input type="hidden" name="note_id" value="'.(int)$n['id'].'">';
    echo '<input name="note_title" value="'.htmlspecialchars($n['title']).'" placeholder="Not başlığı" required>';
    echo '<textarea name="note_body" rows="2" placeholder="Detay (opsiyonel)">'.htmlspecialchars($n['note']).'</textarea>';
    echo '<input type="date" name="note_due" value="'.htmlspecialchars($n['due_date']).'">';
    echo '<button type="submit" class="df-btn df-btn--primary" style="width:100%">Kaydet</button>';
    echo '</form>';
    echo '</div>';
    echo '</div>';
  }
}catch(Throwable $e){}
?>

<div class="df-text-section" style="margin:20px 4px 10px;color:var(--df-ink-900)">Görevlerim</div>
<?php
// PX-001A düzeltme (statik önizleme sırasında bulundu): sekmeler flex:1 ile 100% genişliğe
// zorlanınca flex item'ların varsayılan min-width:auto'su ("Tamamlanan" metninin içerik genişliği)
// 390px'lik telefon ekranını taşırıyordu. Sekmeler artık içerik-genişliğinde (web'deki gibi),
// admin "+ İş Ekle" ayrı, sağa yaslı küçük ikincil buton.
?>
<div style="display:flex;align-items:center;justify-content:space-between;gap:8px;flex-wrap:wrap">
  <div class="df-tabs">
    <a class="df-tab<?=$f==='open'?' df-tab--active':''?>" href="mytasks.php?f=open">Açık</a>
    <a class="df-tab<?=$f==='done'?' df-tab--active':''?>" href="mytasks.php?f=done">Tamamlanan</a>
  </div>
  <?php if($isAdmin): ?><?=ds_button('İş Ekle','task_new.php','secondary','df-btn--sm','',true)?><?php endif; ?>
</div>
<?php
try{
  if(!$pid && !$isAdmin){ echo '<div class="df-empty"><div class="df-empty-title">Sana bağlı personel kaydı yok.</div></div>'; }
  $w = $f==='done' ? "t.status='Tamamlandı'" : "t.status NOT IN ('Tamamlandı','İptal')";
  // "Görevlerim" = sadece sana atanan işler. Admin dahil herkesin işleri için tasks.php ("Tüm Görevler") kullanılır.
  $rows=$pdo->prepare("SELECT t.*, j.id job_real, j.job_no, p.name pname, p.phone pphone FROM tasks t LEFT JOIN jobs j ON j.id=t.job_id LEFT JOIN personnel p ON p.id=t.personnel_id WHERE $w AND t.personnel_id=? AND t.deleted_at IS NULL ORDER BY (t.due_date IS NULL), t.due_date, t.id DESC LIMIT 100");
  $rows->execute([$pid]);
  $rows=$rows->fetchAll();
  if(!$rows){
    echo '<div class="df-empty"><div class="df-empty-icon">'.ds_icon($f==='done'?'check':'calendar',20).'</div><div class="df-empty-title">'.($f==='done'?'Tamamlanan görev yok.':'Açık görev yok').'</div></div>';
  } else {
  echo '<div class="df-list" style="margin-top:10px">';
  foreach($rows as $t){
    $geç = ($f!=='done' && !empty($t['due_date']) && $t['due_date']<date('Y-m-d'));
    // PRODUCT DESIGN BLUEPRINT / mytasks.php sprinti (2026-07-16, Product Owner kararı — Mobil UX
    // Standardı PROJECT_RULES.md): kartta kayıt bazlı HİÇBİR aksiyon yok (Detay/İş Detayı/Gönder/
    // Başla/Tamamla/Düzenle/Sil hepsi task_view.php'ye taşındı). Kart sadece karar bilgisi taşır,
    // tamamı tıklanabilir — <a> ile sarmalandı, ayrı bir "Detay" butonuna gerek kalmadı.
    // PX-001A (2026-07-16): sol renkli çubuk kaldırıldı, yerine küçük öncelik noktası (ds_priority()).
    echo '<a class="df-list-row" href="task_view.php?id='.(int)$t['id'].'" style="text-decoration:none;color:inherit">';
    echo '<div class="df-list-row-body">';
    echo '<div class="df-list-row-title">'.htmlspecialchars($t['title']);
    if($isAdmin && $t['pname']) echo ' <span class="df-text-caption">· '.htmlspecialchars($t['pname']).'</span>';
    echo '</div>';
    if($t['description']) echo '<div class="df-list-row-desc">'.htmlspecialchars($t['description']).'</div>';
    echo '<div class="df-list-row-meta">';
    if($t['job_no']) echo '<span>İş: '.htmlspecialchars($t['job_no']).'</span>';
    echo '<span>'.htmlspecialchars($t['status']).'</span>';
    if($t['priority'] && $t['priority']!=='Normal') echo ds_priority($t['priority'],$t['priority']);
    if($t['due_date']) echo '<span>'.ds_icon('calendar',13).' '.htmlspecialchars($t['due_date']).'</span>';
    if($geç) echo '<span style="color:var(--df-danger-ink);font-weight:600">Gecikti</span>';
    echo '</div>';
    echo '</div>';
    echo '</a>';
  }
  echo '</div>';
  }
}catch(Throwable $e){ echo '<div class="err">'.htmlspecialchars($e->getMessage()).'</div>'; }
?>
<a class="df-fab" href="mytask_new.php" aria-label="Kendime iş ekle" style="right:18px;bottom:calc(126px + env(safe-area-inset-bottom))"><?=ds_icon('plus',26)?></a>
<?php
botx();
