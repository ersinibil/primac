<?php
/* ÇEK / SENET — GERÇEK FİNANSAL YAŞAM DÖNGÜSÜ (2026-07-18, Product Owner kararı)
 * Tek kayıt detay + durum makinesi ekranı — checks_notes.php (liste) buradan aksiyon alır.
 * Tüm iş mantığı checks_notes_lib.php'de (collect/pay/endorse/bounce/cancel) — bu dosya sadece
 * form/render. mobile/check_note_view.php ile aynı akış (web+mobil parite).
 */
require_once __DIR__.'/boot.php';
require_login();
require_once __DIR__.'/checks_notes_lib.php';

$pdo=db();
$id=(int)($_GET['id'] ?? 0);
$userId=$_SESSION['user']['id'] ?? 0;

if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['collect_cn'])){
    try{
        if(!can_edit_delete()) throw new Exception('Bu işlem için yetkiniz yok.');
        checks_notes_collect($pdo,$userId,$id,$_POST['account_id']??0,$_POST['settle_date']??'',$_POST['desc']??'');
        $_SESSION['cn_ok']='Çek/senet tahsil edildi.';
    }catch(Throwable $e){ $_SESSION['cn_err']=$e->getMessage(); }
    header('Location: check_note_view.php?id='.$id); exit;
}
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['pay_cn'])){
    try{
        if(!can_edit_delete()) throw new Exception('Bu işlem için yetkiniz yok.');
        checks_notes_pay($pdo,$userId,$id,$_POST['account_id']??0,$_POST['settle_date']??'',$_POST['desc']??'');
        $_SESSION['cn_ok']='Çek/senet ödendi.';
    }catch(Throwable $e){ $_SESSION['cn_err']=$e->getMessage(); }
    header('Location: check_note_view.php?id='.$id); exit;
}
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['endorse_cn'])){
    try{
        if(!can_edit_delete()) throw new Exception('Bu işlem için yetkiniz yok.');
        checks_notes_endorse($pdo,$userId,$id,$_POST['ciro_contact_id']??0,$_POST['settle_date']??'',$_POST['desc']??'');
        $_SESSION['cn_ok']='Çek/senet ciro edildi.';
    }catch(Throwable $e){ $_SESSION['cn_err']=$e->getMessage(); }
    header('Location: check_note_view.php?id='.$id); exit;
}
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['bounce_cn'])){
    try{
        if(!can_edit_delete()) throw new Exception('Bu işlem için yetkiniz yok.');
        checks_notes_bounce($pdo,$userId,$id,$_POST['reason']??'');
        $_SESSION['cn_ok']='Çek/senet karşılıksız işaretlendi, cari borç yeniden açıldı.';
    }catch(Throwable $e){ $_SESSION['cn_err']=$e->getMessage(); }
    header('Location: check_note_view.php?id='.$id); exit;
}
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['cancel_cn'])){
    try{
        if(!can_edit_delete()) throw new Exception('Bu işlem için yetkiniz yok.');
        checks_notes_cancel($pdo,$userId,$id,$_POST['reason']??'');
        $_SESSION['cn_ok']='Çek/senet iptal edildi.';
    }catch(Throwable $e){ $_SESSION['cn_err']=$e->getMessage(); }
    header('Location: check_note_view.php?id='.$id); exit;
}
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['reopen_cn'])){
    try{
        if(!can_edit_delete()) throw new Exception('Bu işlem için yetkiniz yok.');
        checks_notes_reopen($pdo,$userId,$id,$_POST['reason']??'');
        $_SESSION['cn_ok']='İşlem geri alındı — kayıt tekrar Portföyde/Bekliyor durumunda.';
    }catch(Throwable $e){ $_SESSION['cn_err']=$e->getMessage(); }
    header('Location: check_note_view.php?id='.$id); exit;
}
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['edit_cn'])){
    try{
        if(!can_edit_delete()) throw new Exception('Bu işlem için yetkiniz yok.');
        checks_notes_update($pdo,$id,$_POST);
        $_SESSION['cn_ok']='Kayıt güncellendi.';
    }catch(Throwable $e){ $_SESSION['cn_err']=$e->getMessage(); }
    header('Location: check_note_view.php?id='.$id); exit;
}
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['delete_cn'])){
    if(can_edit_delete()){
        $res=checks_notes_delete($pdo,$id);
        if($res['ok']){ header('Location: checks_notes.php?deleted=1'); exit; }
        $_SESSION['cn_err']=$res['msg'];
    }else{
        $_SESSION['cn_err']='Silme için yetkiniz yok.';
    }
    header('Location: check_note_view.php?id='.$id); exit;
}

require_once __DIR__.'/layout_top.php';

$row=checks_notes_get($pdo,$id);
if(!$row){
    ds_page_header('Çek / Senet', ds_icon('tag',24), '', ds_button('Listeye Dön','checks_notes.php','secondary','','',true), false, true);
    echo ds_alert('danger','Kayıt bulunamadı.');
    require __DIR__.'/layout_bottom.php';
    exit;
}
$ok=''; $error='';
if(!empty($_SESSION['cn_ok'])){ $ok=$_SESSION['cn_ok']; unset($_SESSION['cn_ok']); }
if(!empty($_SESSION['cn_err'])){ $error=$_SESSION['cn_err']; unset($_SESSION['cn_err']); }

$typeOpts=checks_notes_types();
$dirOpts=checks_notes_directions();
$rDir=$row['direction'] ?? 'alinan';
$statusOpts=checks_notes_statuses($rDir);
$actions=checks_notes_available_actions($row);
$canDelete=checks_notes_can_delete($row);
$canEdit=can_edit_delete();
$history=checks_notes_history($pdo,$row);
$accounts=[]; try{ $accounts=$pdo->query("SELECT * FROM finance_accounts WHERE active=1 ORDER BY account_type,name")->fetchAll(); }catch(Throwable $e){}
$contacts=[]; try{ $contacts=$pdo->query("SELECT id,name FROM contacts WHERE id<>".(int)($row['contact_id']?:0)." ORDER BY name")->fetchAll(); }catch(Throwable $e){}
$allContacts=[]; try{ $allContacts=$pdo->query("SELECT id,name FROM contacts ORDER BY name")->fetchAll(); }catch(Throwable $e){}
$ic = $row['type']==='senet' ? '📝' : '🧾';
$today=date('Y-m-d');
$overdue = $row['status']==='portfoyde' && $row['due_date'] && $row['due_date']<$today;

ds_page_header($ic.' '.($typeOpts[$row['type']]??$row['type']).' '.h($row['number']?:''), '', h($row['contact_name']?:'Cari seçilmedi'), ds_button('Listeye Dön','checks_notes.php','secondary','','',true), false, true);
?>

<?php if($error): ?><?=ds_alert('danger',$error)?><?php endif; ?>
<?php if($ok): ?><?=ds_alert('success',$ok)?><?php endif; ?>
<?php if(!checks_notes_lifecycle_ready()): ?>
<?=ds_alert('danger','⚠️ Çek/Senet yaşam döngüsü (Tahsil Et / Öde / Ciro Et / İşlemi Geri Al) bu sunucuda henüz AKTİF DEĞİL — migration 048 çalıştırılmamış. Çözüm: migrate.php çalıştırılmalı.')?>
<?php endif; ?>

<section class="df-card">
<div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:12px">
  <div>
    <div style="font-size:32px;font-weight:900"><?=money($row['amount'])?></div>
    <div class="df-muted" style="margin-top:4px">
      Yön: <?=h($dirOpts[$rDir]??$rDir)?> ·
      Vade: <?=h($row['due_date']?:'Vadesiz')?><?=$overdue?' ⚠️ Vadesi geçti':''?><?=$row['bank_name']?' · '.h($row['bank_name']):''?>
    </div>
  </div>
  <?=ds_badge($statusOpts[$row['status']]??$row['status'], checks_notes_status_tone($row['status']))?>
</div>
<?php if($row['notes']): ?><p style="margin-top:var(--df-space-3)"><?=nl2br(h($row['notes']))?></p><?php endif; ?>
<?php if(!empty($row['attachment'])): ?><p style="margin-top:var(--df-space-2)"><a href="<?=h(base_url().$row['attachment'])?>" target="_blank"><?=ds_icon('box',14)?> Dosyayı Gör</a></p><?php endif; ?>
</section>

<?php if($canEdit && $actions): ?>
<section class="df-card" style="margin-top:var(--df-space-4)">
<h2 class="df-section-title">İşlemler</h2>
<div style="display:flex;gap:8px;flex-wrap:wrap">
<?php if(in_array('tahsil',$actions,true)): ?>
<button type="button" class="df-btn df-btn--primary" onclick="cnToggle('cnCollectBox')">💰 Tahsil Et</button>
<?php endif; ?>
<?php if(in_array('ciro',$actions,true)): ?>
<button type="button" class="df-btn df-btn--secondary" onclick="cnToggle('cnEndorseBox')">🔄 Ciro Et</button>
<?php endif; ?>
<?php if(in_array('ode',$actions,true)): ?>
<button type="button" class="df-btn df-btn--primary" onclick="cnToggle('cnPayBox')">💸 Öde</button>
<?php endif; ?>
<?php if(in_array('karsiliksiz',$actions,true)): ?>
<button type="button" class="df-btn df-btn--danger" onclick="cnToggle('cnBounceBox')">⚠️ Karşılıksız</button>
<?php endif; ?>
<?php if(in_array('reopen',$actions,true)): ?>
<button type="button" class="df-btn df-btn--secondary" onclick="cnToggle('cnReopenBox')">↩️ İşlemi Geri Al</button>
<?php endif; ?>
<?php if(in_array('duzenle',$actions,true)): ?>
<button type="button" class="df-btn df-btn--secondary" onclick="cnToggle('cnEditBox')">✏️ Düzenle</button>
<?php endif; ?>
<?php if(in_array('iptal',$actions,true)): ?>
<button type="button" class="df-btn df-btn--secondary" onclick="cnToggle('cnCancelBox')">✕ İptal Et</button>
<?php endif; ?>
</div>

<?php if(in_array('tahsil',$actions,true)): ?>
<div id="cnCollectBox" class="df-card" style="display:none;background:var(--df-accent-soft);border-color:transparent;margin-top:var(--df-space-3)">
<h3 class="df-section-subtitle">Tahsil Et — Portföydeki Çek/Senet → Kasa/Banka</h3>
<p class="df-section-hint">Cari zaten kapandı, bu işlem SADECE seçilen hesaba gerçek bir kasa/banka hareketi ekler.</p>
<form method="post" class="df-form-grid-2">
<input type="hidden" name="collect_cn" value="1">
<?php
$__accOpts='<option value="">— Hesap seç —</option>';
foreach($accounts as $a){ $__accOpts.='<option value="'.(int)$a['id'].'">'.h($a['name']).' ('.h($a['account_type']).')</option>'; }
ds_form_field('Tahsil Edilecek Hesap', '<select name="account_id" required>'.$__accOpts.'</select>');
ds_form_field('Tahsil Tarihi', '<input type="date" name="settle_date" value="'.h($today).'" required>');
?>
<div class="df-form-span-2"><?php ds_form_field('Açıklama', '<input name="desc" placeholder="Opsiyonel">'); ?></div>
<div class="df-form-span-2"><button class="df-btn df-btn--primary">💰 Tahsil Et — <?=money($row['amount'])?></button></div>
</form>
</div>
<?php endif; ?>

<?php if(in_array('ode',$actions,true)): ?>
<div id="cnPayBox" class="df-card" style="display:none;background:var(--df-accent-soft);border-color:transparent;margin-top:var(--df-space-3)">
<h3 class="df-section-subtitle">Öde — Verilen Çek/Senet → Banka/Kasa Çıkışı</h3>
<p class="df-section-hint">Cari zaten kapandı, bu işlem SADECE seçilen hesaptan gerçek bir çıkış hareketi ekler.</p>
<form method="post" class="df-form-grid-2">
<input type="hidden" name="pay_cn" value="1">
<?php
$__accOpts2='<option value="">— Hesap seç —</option>';
foreach($accounts as $a){ $__accOpts2.='<option value="'.(int)$a['id'].'">'.h($a['name']).' ('.h($a['account_type']).')</option>'; }
ds_form_field('Ödemenin Çıkacağı Hesap', '<select name="account_id" required>'.$__accOpts2.'</select>');
ds_form_field('Ödeme Tarihi', '<input type="date" name="settle_date" value="'.h($today).'" required>');
?>
<div class="df-form-span-2"><?php ds_form_field('Açıklama', '<input name="desc" placeholder="Opsiyonel">'); ?></div>
<div class="df-form-span-2"><button class="df-btn df-btn--primary">💸 Öde — <?=money($row['amount'])?></button></div>
</form>
</div>
<?php endif; ?>

<?php if(in_array('ciro',$actions,true)): ?>
<div id="cnEndorseBox" class="df-card" style="display:none;background:var(--df-accent-soft);border-color:transparent;margin-top:var(--df-space-3)">
<h3 class="df-section-subtitle">Ciro Et — Portföydeki Çek/Senet → Tedarikçi Borcunun Kapatılması</h3>
<p class="df-section-hint">Kasa/banka hareketi oluşmaz — sadece seçilen carinin (tedarikçinin) borcu kapanır. Bu çekin kimden alındığı kaybolmaz.</p>
<form method="post" class="df-form-grid-2">
<input type="hidden" name="endorse_cn" value="1">
<?php
$__ciroOpts='<option value="">— Cari seç —</option>';
foreach($contacts as $c){ $__ciroOpts.='<option value="'.(int)$c['id'].'">'.h($c['name']).'</option>'; }
ds_form_field('Ciro Edilen Cari', '<select name="ciro_contact_id" required>'.$__ciroOpts.'</select>');
ds_form_field('Tarih', '<input type="date" name="settle_date" value="'.h($today).'" required>');
?>
<div class="df-form-span-2"><?php ds_form_field('Açıklama', '<input name="desc" placeholder="Opsiyonel">'); ?></div>
<div class="df-form-span-2"><button class="df-btn df-btn--primary">🔄 Ciro Et — <?=money($row['amount'])?></button></div>
</form>
</div>
<?php endif; ?>

<?php if(in_array('karsiliksiz',$actions,true)): ?>
<div id="cnBounceBox" class="df-card" style="display:none;background:var(--df-danger-soft);border-color:transparent;margin-top:var(--df-space-3)">
<h3 class="df-section-subtitle">Karşılıksız — Çek Tahsil Edilemedi</h3>
<p class="df-section-hint">Kabul anında kapanmış olan müşteri borcu YENİDEN AÇILACAK.</p>
<form method="post" onsubmit="return confirm('Bu çek karşılıksız işaretlenecek ve müşteri borcu yeniden açılacak. Emin misiniz?')">
<input type="hidden" name="bounce_cn" value="1">
<?php ds_form_field('Not', '<input name="reason" placeholder="Opsiyonel">'); ?>
<button class="df-btn df-btn--danger">⚠️ Karşılıksız İşaretle</button>
</form>
</div>
<?php endif; ?>

<?php if(in_array('reopen',$actions,true)): ?>
<div id="cnReopenBox" class="df-card" style="display:none;background:var(--df-accent-soft);border-color:transparent;margin-top:var(--df-space-3)">
<h3 class="df-section-subtitle">İşlemi Geri Al</h3>
<p class="df-section-hint"><?=$row['status']==='ciro_edildi'?'Ciro hareketi geri alınır, hedef tedarikçinin borcu yeniden açılır.':'Kasa/banka hareketi geri alınır, hesap bakiyesi düzeltilir.'?> Kayıt tekrar Portföyde/Bekliyor durumuna döner — cariye (ilk kabul hareketi) dokunulmaz, o zaten kapalıydı. Sonra Düzenle/Sil yeniden kullanılabilir.</p>
<form method="post" onsubmit="return confirm('Bu işlem geri alınacak, kayıt tekrar Portföyde/Bekliyor durumuna dönecek. Emin misiniz?')">
<input type="hidden" name="reopen_cn" value="1">
<?php ds_form_field('Not', '<input name="reason" placeholder="Opsiyonel">'); ?>
<button class="df-btn df-btn--primary">↩️ İşlemi Geri Al</button>
</form>
</div>
<?php endif; ?>

<?php if(in_array('iptal',$actions,true)): ?>
<div id="cnCancelBox" class="df-card" style="display:none;background:var(--df-danger-soft);border-color:transparent;margin-top:var(--df-space-3)">
<h3 class="df-section-subtitle">İptal Et</h3>
<p class="df-section-hint">Kabul anında oluşmuş cari hareket varsa geri alınır — kayıt hiç olmamış gibi kalır.</p>
<form method="post" onsubmit="return confirm('Bu kayıt iptal edilecek. Emin misiniz?')">
<input type="hidden" name="cancel_cn" value="1">
<?php ds_form_field('Not', '<input name="reason" placeholder="Opsiyonel">'); ?>
<button class="df-btn df-btn--danger">✕ İptal Et</button>
</form>
</div>
<?php endif; ?>

<?php if(in_array('duzenle',$actions,true)): ?>
<div id="cnEditBox" class="df-card" style="display:none;margin-top:var(--df-space-3)">
<h3 class="df-section-subtitle">Kaydı Düzenle</h3>
<form method="post" class="df-form-grid-2" enctype="multipart/form-data">
<input type="hidden" name="edit_cn" value="1">
<?php
$__dirOptsHtml='';
foreach($dirOpts as $dk=>$dl){ $__dirOptsHtml.='<option value="'.h($dk).'" '.($rDir===$dk?'selected':'').'>'.h($dl).'</option>'; }
ds_form_field('Yön', '<select name="direction">'.$__dirOptsHtml.'</select>');
$__typeOptsHtml='';
foreach($typeOpts as $tk=>$tl){ $__typeOptsHtml.='<option value="'.h($tk).'" '.($row['type']===$tk?'selected':'').'>'.h($tl).'</option>'; }
ds_form_field('Tür', '<select name="type">'.$__typeOptsHtml.'</select>');
ds_form_field('Numara', '<input name="number" value="'.h($row['number']).'">');
ds_form_field('Tutar', '<input type="number" step="0.01" name="amount" value="'.h($row['amount']).'" required>');
ds_form_field('Vade Tarihi', '<input type="date" name="due_date" value="'.h($row['due_date']).'">');
$__contactOptsHtml="<option value=''>Cari seçilmedi</option>";
foreach($allContacts as $c){ $__contactOptsHtml.='<option value="'.$c['id'].'" '.((int)$row['contact_id']===(int)$c['id']?'selected':'').'>'.h($c['name']).'</option>'; }
ds_form_field('Cari', '<select name="contact_id">'.$__contactOptsHtml.'</select>');
ds_form_field('Banka Adı', '<input name="bank_name" value="'.h($row['bank_name']).'">');
?>
<div class="df-form-span-2"><?php ds_form_field('Not', '<textarea name="notes" rows="2">'.h($row['notes']).'</textarea>'); ?></div>
<div class="df-form-span-2"><?php ds_form_field('Fotoğraf / Dosya', '<input type="file" name="attachment" accept=".jpg,.jpeg,.png,.webp,.gif,.pdf">', 'Yeni dosya seçilirse eskisinin yerine geçer'); ?></div>
<div class="df-form-span-2"><button class="df-btn df-btn--primary">💾 Kaydet</button></div>
</form>
</div>
<?php endif; ?>
</section>
<?php endif; ?>

<section class="df-card" style="margin-top:var(--df-space-4)">
<h2 class="df-section-title">Hareket Geçmişi</h2>
<div class="df-cn-timeline">
<?php foreach($history as $__h): ?>
<div class="df-cn-timeline-item df-cn-timeline-item--<?=h($__h['tone'])?>">
  <div class="df-cn-timeline-dot"></div>
  <div class="df-cn-timeline-body">
    <div class="df-cn-timeline-label"><?=h($__h['label'])?></div>
    <div class="df-cn-timeline-meta"><?=h($__h['date'])?><?php if($__h['amount']): ?> · <b><?=h($__h['amount'])?></b><?php endif; ?></div>
  </div>
</div>
<?php endforeach; ?>
</div>
</section>

<?php if($canEdit && $canDelete): ?>
<section class="df-card" style="margin-top:var(--df-space-4)">
<form method="post" onsubmit="return confirm('Bu kaydı silmek istediğinize emin misiniz?')">
<input type="hidden" name="delete_cn" value="1">
<button class="df-btn df-btn--danger"><?=ds_icon('trash',16)?> Kaydı Sil</button>
</form>
</section>
<?php endif; ?>

<script>
function cnToggle(id){
  document.querySelectorAll('.df-card[id^="cn"][id$="Box"]').forEach(function(el){ if(el.id!==id) el.style.display='none'; });
  var box=document.getElementById(id);
  if(box) box.style.display = box.style.display==='none' ? 'block' : 'none';
}
</script>

<style>
body.nav-compact .df-section-title{font-size:var(--df-type-section-size);font-weight:var(--df-type-section-weight);color:var(--df-ink-900);margin:0 0 var(--df-space-3)}
body.nav-compact .df-section-subtitle{font-size:14px;font-weight:700;color:var(--df-ink-900);margin:0 0 var(--df-space-2)}
body.nav-compact .df-section-hint{font-size:var(--df-type-caption-size);color:var(--df-ink-500);margin:0 0 var(--df-space-3)}
body.nav-compact .df-form-grid-2{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:0 var(--df-space-4)}
body.nav-compact .df-form-span-2{grid-column:1 / -1}
@media(max-width:640px){body.nav-compact .df-form-grid-2{grid-template-columns:1fr}}
.df-cn-timeline{display:flex;flex-direction:column;gap:0}
.df-cn-timeline-item{display:flex;gap:12px;padding:10px 0;border-left:2px solid var(--df-hairline);margin-left:5px;padding-left:16px;position:relative}
.df-cn-timeline-item:last-child{border-left-color:transparent}
.df-cn-timeline-dot{position:absolute;left:-7px;top:14px;width:12px;height:12px;border-radius:50%;background:var(--df-ink-500)}
.df-cn-timeline-item--success .df-cn-timeline-dot{background:var(--df-success)}
.df-cn-timeline-item--danger .df-cn-timeline-dot{background:var(--df-danger)}
.df-cn-timeline-item--info .df-cn-timeline-dot{background:var(--df-accent)}
.df-cn-timeline-label{font-weight:700;font-size:14px;color:var(--df-ink-900)}
.df-cn-timeline-meta{font-size:12.5px;color:var(--df-ink-500);margin-top:2px}
</style>

<?php require_once __DIR__.'/layout_bottom.php'; ?>
