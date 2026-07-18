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
        elseif(isset($_POST['note_update'])) personal_note_update($pdo,(int)$_POST['note_id'],$me,trim($_POST['note_title']??''),trim($_POST['note_body']??''),$_POST['note_due']?:null);
    }catch(Throwable $e){}
    header('Location: notes.php'.(!empty($_GET['f'])?'?f='.urlencode($_GET['f']):'')); exit;
}

$f=$_GET['f']??'open';
// REOPEN-001: takvimden bir not öğesine tıklanınca ?date=YYYY-MM-DD taşınır — sadece o günün
// notları gösterilir. Hatalı/eksik formatta sessizce yok sayılır, normal (tüm notlar) davranışa
// düşer (sistemi bozmaz). Menüden gelen normal "Notlarım" erişiminde date hiç yok, davranış aynı.
$date=(!empty($_GET['date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/',$_GET['date'])) ? $_GET['date'] : null;
$myNotes=personal_notes_list($pdo,$me,$f,$date);
require_once __DIR__.'/share_lib.php';
$myPhone=my_phone($pdo,$me);
require_once __DIR__.'/layout_top.php';
$__notesActions = $date ? ds_button('✕ Tüm Notlar','notes.php','secondary','','',true) : '';
ds_page_header('Notlarım'.($date?' — '.h(date('d.m.Y',strtotime($date))):''), ds_icon('edit',24), 'Bu alan sadece sana özel — personel göremez.', $__notesActions, false, true);
?>

<?php if(!empty($_GET['ok'])): ?><?=ds_alert('success','Not eklendi, bildirim gönderildi.')?><?php endif; ?>
<?php if($err): ?><?=ds_alert('danger',$err)?><?php endif; ?>

<section class="df-card" style="margin-top:var(--df-space-4)">
<form method="post" class="df-form-grid-2">
<input type="hidden" name="note_add" value="1">
<div class="df-form-span-2"><?php ds_form_field('Başlık', '<input name="note_title" required>'); ?></div>
<div class="df-form-span-2"><?php ds_form_field('Detay (opsiyonel)', '<textarea name="note_body" rows="3"></textarea>'); ?></div>
<?php ds_form_field('Termin (opsiyonel — takvime işlenir)', '<input type="date" name="note_due">'); ?>
<div class="df-form-span-2">
<button class="df-btn df-btn--primary" type="submit"><?=ds_icon('plus',16)?> Not Ekle</button>
</div>
</form>
</section>

<?php
ds_tabs([
    ['label'=>'Açık','url'=>'notes.php?f=open','active'=>$f==='open'],
    ['label'=>'Tamamlanan','url'=>'notes.php?f=done','active'=>$f==='done'],
]);
?>

<?php if(!$myNotes): ?>
<?=ds_empty_state($f==='done'?'Tamamlanan not yok.':'Açık not yok.', null, ds_icon('edit',32))?>
<?php endif; ?>
<?php foreach($myNotes as $n):
    $waTxt="📝 Not: ".$n['title'].($n['note']?"\n".$n['note']:'').($n['due_date']?"\n📅 ".$n['due_date']:'');
?>
<div class="df-card" style="margin-top:var(--df-space-3);background:var(--df-warning-soft)">
<b><?=h($n['title'])?></b>
<?php if($n['note']): ?><div class="df-muted" style="margin-top:4px"><?=nl2br(h($n['note']))?></div><?php endif; ?>
<?php if($n['due_date']): ?><div class="df-muted" style="margin-top:4px"><?=ds_icon('calendar',14)?> <?=h($n['due_date'])?></div><?php endif; ?>
<div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:10px">
<?php if($myPhone): ?><a class="df-btn df-btn--sm" style="background:var(--df-success);color:#fff" href="<?=h(wa_link($waTxt,$myPhone))?>" target="_blank" rel="noopener"><?=ds_icon('send',14)?> WhatsApp</a><?php endif; ?>
<?php if($f!=='done'): ?>
<button type="button" class="df-btn df-btn--sm" style="background:var(--df-warning);color:#fff" data-edit-id="<?=(int)$n['id']?>" data-edit-title="<?=h($n['title'])?>" data-edit-body="<?=h($n['note'])?>" data-edit-due="<?=h($n['due_date'])?>" onclick="noteEditOpen(this)"><?=ds_icon('edit',14)?> Düzenle</button>
<form method="post" style="display:inline"><input type="hidden" name="note_id" value="<?=(int)$n['id']?>"><button class="df-btn df-btn--sm df-btn--primary" name="note_done" value="1"><?=ds_icon('check',14)?> Tamamla</button></form>
<?php endif; ?>
<form method="post" style="display:inline" onsubmit="return confirm('Not silinsin mi?')"><input type="hidden" name="note_id" value="<?=(int)$n['id']?>"><button class="df-btn df-btn--sm df-btn--danger" name="note_del" value="1"><?=ds_icon('trash',14)?> Sil</button></form>
</div>
</div>
<?php endforeach; ?>

<?php if($f!=='done'): ?>
<!-- Not düzenleme modalı — sayfa değişmeden aynı sayfada açılır/kapanır (kullanıcı isteği:
     "Düzenle'ye basınca AYNI SAYFADA bir modal açılmalı"). Öncelik/Hatırlatma alanları
     personal_notes şemasında (migration 037) yok — bu form sadece var olan kolonları
     (title/note/due_date) düzenler, DB şeması bu iş kapsamında GENİŞLETİLMEDİ. -->
<div id="noteEditOverlay" style="display:none;position:fixed;inset:0;background:rgba(16,24,40,.55);z-index:1000;align-items:center;justify-content:center;padding:16px">
  <div class="df-card" style="max-width:480px;width:100%;margin:0">
    <h2 style="margin:0 0 var(--df-space-3);font-size:18px"><?=ds_icon('edit',18)?> Notu Düzenle</h2>
    <form method="post" class="df-form-grid-2">
      <input type="hidden" name="note_update" value="1">
      <input type="hidden" name="note_id" id="edit_note_id" value="">
      <div class="df-form-span-2"><?php ds_form_field('Başlık', '<input name="note_title" id="edit_note_title" required>'); ?></div>
      <div class="df-form-span-2"><?php ds_form_field('Detay (opsiyonel)', '<textarea name="note_body" id="edit_note_body" rows="3"></textarea>'); ?></div>
      <div class="df-form-span-2"><?php ds_form_field('Termin (opsiyonel)', '<input type="date" name="note_due" id="edit_note_due">'); ?></div>
      <div class="df-form-span-2" style="display:flex;gap:10px">
        <button class="df-btn df-btn--primary" type="submit"><?=ds_icon('check',16)?> Kaydet</button>
        <button class="df-btn df-btn--secondary" type="button" onclick="noteEditClose()">Vazgeç</button>
      </div>
    </form>
  </div>
</div>
<script>
function noteEditOpen(btn){
  document.getElementById('edit_note_id').value = btn.getAttribute('data-edit-id');
  document.getElementById('edit_note_title').value = btn.getAttribute('data-edit-title');
  document.getElementById('edit_note_body').value = btn.getAttribute('data-edit-body');
  document.getElementById('edit_note_due').value = btn.getAttribute('data-edit-due') || '';
  document.getElementById('noteEditOverlay').style.display = 'flex';
}
function noteEditClose(){
  document.getElementById('noteEditOverlay').style.display = 'none';
}
document.getElementById('noteEditOverlay').addEventListener('click', function(e){
  if(e.target === this) noteEditClose();
});
</script>
<?php endif; ?>

<style>
body.nav-compact .df-form-grid-2{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:0 var(--df-space-4)}
body.nav-compact .df-form-span-2{grid-column:1 / -1}
@media(max-width:640px){body.nav-compact .df-form-grid-2{grid-template-columns:1fr}}
</style>

<?php require_once __DIR__.'/layout_bottom.php'; ?>
