<?php
/* ÇEK / SENET — GERÇEK FİNANSAL YAŞAM DÖNGÜSÜ (2026-07-18) — web check_note_view.php ile AYNI
 * durum makinesi (checks_notes_lib.php), sadece mobil sunum. */
require_once 'common.php';
require_once dirname(__DIR__).'/checks_notes_lib.php';
$pdo=db(); $id=(int)($_GET['id']??0); $userId=$u['id']??0;

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
        $_SESSION['cn_ok']='Karşılıksız işaretlendi, cari borç yeniden açıldı.';
    }catch(Throwable $e){ $_SESSION['cn_err']=$e->getMessage(); }
    header('Location: check_note_view.php?id='.$id); exit;
}
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['cancel_cn'])){
    try{
        if(!can_edit_delete()) throw new Exception('Bu işlem için yetkiniz yok.');
        checks_notes_cancel($pdo,$userId,$id,$_POST['reason']??'');
        $_SESSION['cn_ok']='İptal edildi.';
    }catch(Throwable $e){ $_SESSION['cn_err']=$e->getMessage(); }
    header('Location: check_note_view.php?id='.$id); exit;
}
/* İşlemi Geri Al — SADECE tahsil_edildi/ciro_edildi (checks_notes_reopen() kendi de kontrol ediyor) */
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['reopen_cn'])){
    try{
        if(!can_edit_delete()) throw new Exception('Bu işlem için yetkiniz yok.');
        checks_notes_reopen($pdo,$userId,$id,$_POST['reason']??'');
        $_SESSION['cn_ok']='İşlem geri alındı — kayıt tekrar Portföyde/Bekliyor durumunda.';
    }catch(Throwable $e){ $_SESSION['cn_err']=$e->getMessage(); }
    header('Location: check_note_view.php?id='.$id); exit;
}
/* Çek/senet düzenle — SADECE portföyde (checks_notes_update() kendi de kontrol ediyor) */
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['edit_cn'])){
    if(!can_edit_delete()){
        $_SESSION['cn_err']='Bu işlem için yetkiniz yok.';
        header('Location: check_note_view.php?id='.$id); exit;
    }
    try{
        checks_notes_update($pdo,$id,$_POST);
        $_SESSION['cn_ok']='Kayıt güncellendi.';
    }catch(Throwable $e){
        $_SESSION['cn_err']=$e->getMessage();
    }
    header('Location: check_note_view.php?id='.$id); exit;
}
/* Çek/senet sil — SADECE finansal olarak dokunulmamış (checks_notes_delete() kontrol ediyor) */
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['delete_cn'])){
    if(can_edit_delete()){
        $res=checks_notes_delete($pdo,$id);
        if($res['ok']){ header('Location: checks_notes.php?deleted=1'); exit; }
        $_SESSION['cn_err']=$res['msg'];
        header('Location: check_note_view.php?id='.$id); exit;
    }
    header('Location: check_note_view.php?id='.$id); exit;
}

topx('Çek / Senet');
if(!empty($_SESSION['cn_ok'])){ echo ds_alert('success',$_SESSION['cn_ok']); unset($_SESSION['cn_ok']); }
if(!empty($_SESSION['cn_err'])){ echo ds_alert('danger',$_SESSION['cn_err']); unset($_SESSION['cn_err']); }
if(!checks_notes_lifecycle_ready()) echo ds_alert('danger','⚠️ Çek/Senet yaşam döngüsü (Tahsil Et / Öde / Ciro Et / İşlemi Geri Al) bu sunucuda henüz AKTİF DEĞİL — migration 048 çalıştırılmamış. Çözüm: migrate.php çalıştırılmalı.');

$typeOpts=checks_notes_types();
$dirOpts=checks_notes_directions();
try{
    $r=checks_notes_get($pdo,$id);
    if(!$r) throw new Exception('Kayıt bulunamadı.');
    $rDir = $r['direction'] ?? 'alinan';
    $statusOpts=checks_notes_statuses($rDir);
    $actions=checks_notes_available_actions($r);
    $canDelete=checks_notes_can_delete($r);
    $canEdit=can_edit_delete();
    $history=checks_notes_history($pdo,$r);
    $today=date('Y-m-d');
    $overdue = $r['status']==='portfoyde' && $r['due_date'] && $r['due_date']<$today;
    $ic = $r['type']==='senet' ? '📝' : '🧾';
    $accounts=[]; try{ $accounts=$pdo->query("SELECT * FROM finance_accounts WHERE active=1 ORDER BY account_type,name")->fetchAll(); }catch(Throwable $e){}
    $contacts=[]; try{ $contacts=$pdo->query("SELECT id,name FROM contacts WHERE id<>".(int)($r['contact_id']?:0)." ORDER BY name")->fetchAll(); }catch(Throwable $e){}
    $allContacts=[]; try{ $allContacts=$pdo->query("SELECT id,name FROM contacts ORDER BY name")->fetchAll(); }catch(Throwable $e){}
?>
<style>
.df-cn-timeline{display:flex;flex-direction:column;gap:0}
.df-cn-timeline-item{display:flex;gap:10px;padding:8px 0;border-left:2px solid rgba(255,255,255,.14);margin-left:5px;padding-left:14px;position:relative}
.df-cn-timeline-item:last-child{border-left-color:transparent}
.df-cn-timeline-dot{position:absolute;left:-6px;top:12px;width:10px;height:10px;border-radius:50%;background:#94a3b8}
.df-cn-timeline-item--success .df-cn-timeline-dot{background:#16a34a}
.df-cn-timeline-item--danger .df-cn-timeline-dot{background:#ef4444}
.df-cn-timeline-item--info .df-cn-timeline-dot{background:#2563eb}
.df-cn-timeline-label{font-weight:700;font-size:13.5px}
.df-cn-timeline-meta{font-size:12px;color:var(--c-muted,#94a3b8);margin-top:2px}
</style>
<div class="df-panel">
  <h2 style="margin:0 0 4px"><?=$ic?> <?=h($typeOpts[$r['type']]??$r['type'])?> <?=h($r['number']?:'')?></h2>
  <div class="muted"><?=h(($r['contact_name']?:'Cari seçilmedi').($r['bank_name']?' · '.$r['bank_name']:''))?></div>
  <div style="font-size:28px;font-weight:900;margin-top:10px"><?=mm($r['amount'])?></div>
  <div style="display:flex;gap:14px;margin-top:6px;flex-wrap:wrap">
    <small class="muted">Yön: <?=h($dirOpts[$rDir]??$rDir)?></small>
    <small class="muted">Vade: <?=h($r['due_date']?:'Vadesiz')?><?=$overdue?' ⚠️ Vadesi geçti':''?></small>
  </div>
  <div style="margin-top:8px"><?=ds_badge($statusOpts[$r['status']]??$r['status'], checks_notes_status_tone($r['status']))?></div>
  <?php if($r['notes']): ?><div style="margin-top:8px"><?=nl2br(h($r['notes']))?></div><?php endif; ?>
  <?php if(!empty($r['attachment'])): ?><div style="margin-top:8px"><a href="<?=h(base_url().$r['attachment'])?>" target="_blank"><?=ds_icon('box',14)?> Dosyayı Gör</a></div><?php endif; ?>
</div>

<?php if($canEdit && $actions): ?>
<div class="df-panel">
  <b>İşlemler</b>
  <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:8px">
  <?php if(in_array('tahsil',$actions,true)): ?><button type="button" class="df-btn df-btn--primary" onclick="cnToggle('cnCollectBox')">💰 Tahsil Et</button><?php endif; ?>
  <?php if(in_array('ciro',$actions,true)): ?><button type="button" class="df-btn df-btn--secondary" onclick="cnToggle('cnEndorseBox')">🔄 Ciro Et</button><?php endif; ?>
  <?php if(in_array('ode',$actions,true)): ?><button type="button" class="df-btn df-btn--primary" onclick="cnToggle('cnPayBox')">💸 Öde</button><?php endif; ?>
  <?php if(in_array('karsiliksiz',$actions,true)): ?><button type="button" class="df-btn df-btn--danger" onclick="cnToggle('cnBounceBox')">⚠️ Karşılıksız</button><?php endif; ?>
  <?php if(in_array('reopen',$actions,true)): ?><button type="button" class="df-btn df-btn--secondary" onclick="cnToggle('cnReopenBox')">↩️ İşlemi Geri Al</button><?php endif; ?>
  <?php if(in_array('duzenle',$actions,true)): ?><button type="button" class="df-btn df-btn--secondary" onclick="cnToggle('cnEditBox')">✏️ Düzenle</button><?php endif; ?>
  <?php if(in_array('iptal',$actions,true)): ?><button type="button" class="df-btn df-btn--secondary" onclick="cnToggle('cnCancelBox')">✕ İptal</button><?php endif; ?>
  </div>

  <?php if(in_array('tahsil',$actions,true)): ?>
  <div id="cnCollectBox" style="display:none;margin-top:12px;padding-top:12px;border-top:1px solid rgba(255,255,255,.1)">
    <p class="small">Cari zaten kapandı — sadece seçilen hesaba gerçek hareket eklenir.</p>
    <form method="post">
      <input type="hidden" name="collect_cn" value="1">
      <label>Tahsil Edilecek Hesap</label>
      <select name="account_id" required><option value="">— Hesap seç —</option><?php foreach($accounts as $a): ?><option value="<?=(int)$a['id']?>"><?=h($a['name'])?> (<?=h($a['account_type'])?>)</option><?php endforeach; ?></select>
      <label>Tahsil Tarihi</label>
      <input type="date" name="settle_date" value="<?=h($today)?>" required>
      <label>Açıklama <small class="muted">(opsiyonel)</small></label>
      <input name="desc">
      <button class="df-btn df-btn--primary df-btn--lg" style="width:100%;margin-top:8px">💰 Tahsil Et — <?=mm($r['amount'])?></button>
    </form>
  </div>
  <?php endif; ?>

  <?php if(in_array('ode',$actions,true)): ?>
  <div id="cnPayBox" style="display:none;margin-top:12px;padding-top:12px;border-top:1px solid rgba(255,255,255,.1)">
    <p class="small">Cari zaten kapandı — sadece seçilen hesaptan gerçek çıkış eklenir.</p>
    <form method="post">
      <input type="hidden" name="pay_cn" value="1">
      <label>Ödemenin Çıkacağı Hesap</label>
      <select name="account_id" required><option value="">— Hesap seç —</option><?php foreach($accounts as $a): ?><option value="<?=(int)$a['id']?>"><?=h($a['name'])?> (<?=h($a['account_type'])?>)</option><?php endforeach; ?></select>
      <label>Ödeme Tarihi</label>
      <input type="date" name="settle_date" value="<?=h($today)?>" required>
      <label>Açıklama <small class="muted">(opsiyonel)</small></label>
      <input name="desc">
      <button class="df-btn df-btn--primary df-btn--lg" style="width:100%;margin-top:8px">💸 Öde — <?=mm($r['amount'])?></button>
    </form>
  </div>
  <?php endif; ?>

  <?php if(in_array('ciro',$actions,true)): ?>
  <div id="cnEndorseBox" style="display:none;margin-top:12px;padding-top:12px;border-top:1px solid rgba(255,255,255,.1)">
    <p class="small">Kasa/banka hareketi oluşmaz — sadece seçilen tedarikçinin borcu kapanır.</p>
    <form method="post">
      <input type="hidden" name="endorse_cn" value="1">
      <label>Ciro Edilen Cari</label>
      <select name="ciro_contact_id" required><option value="">— Cari seç —</option><?php foreach($contacts as $c): ?><option value="<?=(int)$c['id']?>"><?=h($c['name'])?></option><?php endforeach; ?></select>
      <label>Tarih</label>
      <input type="date" name="settle_date" value="<?=h($today)?>" required>
      <label>Açıklama <small class="muted">(opsiyonel)</small></label>
      <input name="desc">
      <button class="df-btn df-btn--primary df-btn--lg" style="width:100%;margin-top:8px">🔄 Ciro Et — <?=mm($r['amount'])?></button>
    </form>
  </div>
  <?php endif; ?>

  <?php if(in_array('karsiliksiz',$actions,true)): ?>
  <div id="cnBounceBox" style="display:none;margin-top:12px;padding-top:12px;border-top:1px solid rgba(255,255,255,.1)">
    <p class="small" style="color:#f87171">Müşteri borcu YENİDEN AÇILACAK.</p>
    <form method="post" onsubmit="return confirm('Bu çek karşılıksız işaretlenecek ve müşteri borcu yeniden açılacak. Emin misiniz?')">
      <input type="hidden" name="bounce_cn" value="1">
      <label>Not <small class="muted">(opsiyonel)</small></label>
      <input name="reason">
      <button class="df-btn df-btn--danger df-btn--lg" style="width:100%;margin-top:8px">⚠️ Karşılıksız İşaretle</button>
    </form>
  </div>
  <?php endif; ?>

  <?php if(in_array('reopen',$actions,true)): ?>
  <div id="cnReopenBox" style="display:none;margin-top:12px;padding-top:12px;border-top:1px solid rgba(255,255,255,.1)">
    <p class="small"><?=$r['status']==='ciro_edildi'?'Ciro hareketi geri alınır, hedef tedarikçinin borcu yeniden açılır.':'Kasa/banka hareketi geri alınır, hesap bakiyesi düzeltilir.'?> Kayıt tekrar Portföyde/Bekliyor durumuna döner — cariye dokunulmaz.</p>
    <form method="post" onsubmit="return confirm('Bu işlem geri alınacak, kayıt tekrar Portföyde/Bekliyor durumuna dönecek. Emin misiniz?')">
      <input type="hidden" name="reopen_cn" value="1">
      <label>Not <small class="muted">(opsiyonel)</small></label>
      <input name="reason">
      <button class="df-btn df-btn--primary df-btn--lg" style="width:100%;margin-top:8px">↩️ İşlemi Geri Al</button>
    </form>
  </div>
  <?php endif; ?>

  <?php if(in_array('iptal',$actions,true)): ?>
  <div id="cnCancelBox" style="display:none;margin-top:12px;padding-top:12px;border-top:1px solid rgba(255,255,255,.1)">
    <form method="post" onsubmit="return confirm('Bu kayıt iptal edilecek. Emin misiniz?')">
      <input type="hidden" name="cancel_cn" value="1">
      <label>Not <small class="muted">(opsiyonel)</small></label>
      <input name="reason">
      <button class="df-btn df-btn--danger df-btn--lg" style="width:100%;margin-top:8px">✕ İptal Et</button>
    </form>
  </div>
  <?php endif; ?>

  <?php if(in_array('duzenle',$actions,true)): ?>
  <div id="cnEditBox" style="display:none;margin-top:12px;padding-top:12px;border-top:1px solid rgba(255,255,255,.1)">
    <form method="post" enctype="multipart/form-data">
      <input type="hidden" name="edit_cn" value="1">
      <label>Yön</label>
      <select name="direction"><?php foreach($dirOpts as $dk=>$dl): ?><option value="<?=$dk?>" <?=$rDir===$dk?'selected':''?>><?=h($dl)?></option><?php endforeach; ?></select>
      <label>Tür</label>
      <select name="type"><?php foreach($typeOpts as $tk=>$tl): ?><option value="<?=$tk?>" <?=$r['type']===$tk?'selected':''?>><?=h($tl)?></option><?php endforeach; ?></select>
      <label>Numara</label>
      <input name="number" value="<?=h($r['number']??'')?>">
      <label>Tutar</label>
      <input type="number" step="0.01" name="amount" value="<?=h($r['amount'])?>" required>
      <label>Vade Tarihi</label>
      <input type="date" name="due_date" value="<?=h($r['due_date']??'')?>">
      <label>Cari <small class="muted">(opsiyonel)</small></label>
      <select name="contact_id"><option value="">— Cari seçilmedi —</option><?php foreach($allContacts as $c): ?><option value="<?=$c['id']?>" <?=(int)$r['contact_id']===(int)$c['id']?'selected':''?>><?=h($c['name'])?></option><?php endforeach; ?></select>
      <label>Banka Adı</label>
      <input name="bank_name" value="<?=h($r['bank_name']??'')?>">
      <label>Not</label>
      <textarea name="notes" rows="2"><?=h($r['notes']??'')?></textarea>
      <label>Fotoğraf / Dosya <small class="muted">(yeni seçilirse eskisinin yerine geçer)</small></label>
      <input type="file" name="attachment" accept=".jpg,.jpeg,.png,.webp,.gif,.pdf">
      <button class="df-btn df-btn--primary df-btn--lg" style="width:100%;margin-top:8px">💾 Kaydet</button>
    </form>
  </div>
  <?php endif; ?>
</div>
<?php endif; ?>

<div class="df-panel">
  <b><?=ds_icon('info',16)?> Hareket Geçmişi</b>
  <div class="df-cn-timeline" style="margin-top:8px">
  <?php foreach($history as $__h): ?>
  <div class="df-cn-timeline-item df-cn-timeline-item--<?=h($__h['tone'])?>">
    <div class="df-cn-timeline-dot"></div>
    <div>
      <div class="df-cn-timeline-label"><?=h($__h['label'])?></div>
      <div class="df-cn-timeline-meta"><?=h($__h['date'])?><?php if($__h['amount']): ?> · <b><?=h($__h['amount'])?></b><?php endif; ?></div>
    </div>
  </div>
  <?php endforeach; ?>
  </div>
</div>

<?php if($canEdit && $canDelete): ?>
<div class="df-panel">
  <form method="post" onsubmit="return confirm('Bu kaydı silmek istediğinize emin misiniz?')" style="margin:0">
    <button class="df-btn df-btn--danger" name="delete_cn" value="1" style="width:100%"><?=ds_icon('trash',16)?> Kaydı Sil</button>
  </form>
</div>
<?php endif; ?>

<script>
function cnToggle(id){
  ['cnCollectBox','cnPayBox','cnEndorseBox','cnBounceBox','cnReopenBox','cnCancelBox','cnEditBox'].forEach(function(bid){
    if(bid!==id){ var el=document.getElementById(bid); if(el) el.style.display='none'; }
  });
  var box=document.getElementById(id);
  if(box) box.style.display = box.style.display==='none' ? 'block' : 'none';
}
</script>
<?php
}catch(Throwable $e){ echo ds_alert('danger',$e->getMessage()); }
botx();
