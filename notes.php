<?php
// Notlarım — kişisel görev/not alanı (sadece giriş yapan kullanıcı görür). Web paritesi:
// mobil karşılığı mobile/mytasks.php içine gömülü, web'de ayrı bir sayfa (dashboard.php'de
// kompakt önizleme + buraya link var).
require_once __DIR__.'/boot.php';
require_login();
require_once __DIR__.'/notes_lib.php';

$pdo=db();
$me=(int)($_SESSION['user']['id']??0);
$ok=''; $err='';

if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['note_add'])){
    try{
        personal_note_create($pdo,$me,trim($_POST['note_title']??''),trim($_POST['note_body']??''),$_POST['note_due']?:null);
        header('Location: notes.php?ok=1'); exit;
    }catch(Throwable $e){ $err=$e->getMessage(); }
}
if($_SERVER['REQUEST_METHOD']==='POST' && (int)($_POST['note_id']??0)){
    try{
        if(isset($_POST['note_done'])) personal_note_set_status($pdo,(int)$_POST['note_id'],$me,'Tamamlandı');
        elseif(isset($_POST['note_del'])) personal_note_delete($pdo,(int)$_POST['note_id'],$me);
    }catch(Throwable $e){}
    header('Location: notes.php'.(!empty($_GET['f'])?'?f='.urlencode($_GET['f']):'')); exit;
}

$f=$_GET['f']??'open';
$myNotes=personal_notes_list($pdo,$me,$f);
require_once __DIR__.'/share_lib.php';
$myPhone=my_phone($pdo,$me);
require_once __DIR__.'/layout_top.php';
?>
<div class="panel-head"><h1>📝 Notlarım</h1></div>
<p class="muted">Bu alan sadece sana özel — personel göremez.</p>

<?php if(!empty($_GET['ok'])): ?><div class="ok">Not eklendi, bildirim gönderildi.</div><?php endif; ?>
<?php if($err): ?><div class="alert"><?=h($err)?></div><?php endif; ?>

<section class="panel">
<form method="post" class="form-grid">
<input type="hidden" name="note_add" value="1">
<label class="full">Başlık
<input name="note_title" required>
</label>
<label class="full">Detay (opsiyonel)
<textarea name="note_body" rows="3"></textarea>
</label>
<label>Termin (opsiyonel — takvime işlenir)
<input type="date" name="note_due">
</label>
<div class="full">
<button class="btn" type="submit" style="margin-top:6px">📝 Not Ekle</button>
</div>
</form>
</section>

<div class="filters">
<a href="notes.php?f=open" <?=$f==='open'?'style="background:#101828;color:#fff"':''?>>Açık</a>
<a href="notes.php?f=done" <?=$f==='done'?'style="background:#101828;color:#fff"':''?>>Tamamlanan</a>
</div>

<?php if(!$myNotes): ?>
<div class="panel" style="text-align:center;color:#667085"><?=$f==='done'?'Tamamlanan not yok.':'Açık not yok.'?></div>
<?php endif; ?>
<?php foreach($myNotes as $n):
    $waTxt="📝 Not: ".$n['title'].($n['note']?"\n".$n['note']:'').($n['due_date']?"\n📅 ".$n['due_date']:'');
?>
<div class="panel" style="margin-bottom:12px;background:#fffbeb">
<b><?=h($n['title'])?></b>
<?php if($n['note']): ?><div class="muted" style="margin-top:4px"><?=nl2br(h($n['note']))?></div><?php endif; ?>
<?php if($n['due_date']): ?><div class="muted" style="margin-top:4px">📅 <?=h($n['due_date'])?></div><?php endif; ?>
<div class="actions" style="margin-top:10px">
<?php if($myPhone): ?><a class="btn small" style="background:#16a34a;color:#fff" href="<?=h(wa_link($waTxt,$myPhone))?>" target="_blank" rel="noopener">📲 WhatsApp</a><?php endif; ?>
<?php if($f!=='done'): ?>
<form method="post" style="display:inline"><input type="hidden" name="note_id" value="<?=(int)$n['id']?>"><button class="btn small" name="note_done" value="1" style="background:#2563eb;color:#fff">✓ Tamamla</button></form>
<?php endif; ?>
<form method="post" style="display:inline" onsubmit="return confirm('Not silinsin mi?')"><input type="hidden" name="note_id" value="<?=(int)$n['id']?>"><button class="btn small danger" name="note_del" value="1">🗑 Sil</button></form>
</div>
</div>
<?php endforeach; ?>

<?php require_once __DIR__.'/layout_bottom.php'; ?>
