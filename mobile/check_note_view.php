<?php
require_once 'common.php';
require_once dirname(__DIR__).'/checks_notes_lib.php';
$pdo=db(); $id=(int)($_GET['id']??0);

/* Çek/senet düzenle — topx'tan ÖNCE (PRG) */
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['edit_cn'])){
    if(!can_edit_delete()){
        $_SESSION['cn_err']='Bu işlem için yetkiniz yok.';
        header('Location: check_note_view.php?id='.$id); exit;
    }
    try{
        checks_notes_update($pdo,$id,$_POST);
        header('Location: check_note_view.php?id='.$id.'&ok=1'); exit;
    }catch(Throwable $e){
        $_SESSION['cn_err']=$e->getMessage();
        header('Location: check_note_view.php?id='.$id); exit;
    }
}
/* Çek/senet sil — admin veya 'edit_delete' yetkili personel */
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
if(!empty($_GET['ok'])) echo '<div class="ok">Kayıt güncellendi.</div>';
if(!empty($_SESSION['cn_err'])){ echo '<div class="err">'.htmlspecialchars($_SESSION['cn_err']).'</div>'; unset($_SESSION['cn_err']); }

$typeOpts=checks_notes_types();
$dirOpts=checks_notes_directions();
try{
    $r=checks_notes_get($pdo,$id);
    if(!$r) throw new Exception('Kayıt bulunamadı.');
    $rDir = $r['direction'] ?? 'alinan';
    $statusOpts=checks_notes_statuses($rDir);
    $today=date('Y-m-d');
    $overdue = $r['status']==='portfoyde' && $r['due_date'] && $r['due_date']<$today;
    $ic = $r['type']==='senet' ? '📝' : '🧾';
    $contacts=[];
    try{ $contacts=$pdo->query("SELECT id,name FROM contacts ORDER BY name")->fetchAll(); }catch(Throwable $e){}
?>
<div class="panel">
  <h2 style="margin:0 0 4px"><?=$ic?> <?=htmlspecialchars($typeOpts[$r['type']]??$r['type'])?> <?=htmlspecialchars($r['number']?:'')?></h2>
  <div class="muted"><?=htmlspecialchars(($r['contact_name']?:'Cari seçilmedi').($r['bank_name']?' · '.$r['bank_name']:''))?></div>
  <div style="font-size:28px;font-weight:900;margin-top:10px"><?=mm($r['amount'])?></div>
  <div style="display:flex;gap:14px;margin-top:6px;flex-wrap:wrap">
    <small class="muted">Yön: <?=htmlspecialchars($dirOpts[$rDir]??$rDir)?></small>
    <small class="muted">Vade: <?=htmlspecialchars($r['due_date']?:'Vadesiz')?><?=$overdue?' ⚠️ Vadesi geçti':''?></small>
    <small class="muted">Durum: <?=htmlspecialchars($statusOpts[$r['status']]??$r['status'])?></small>
  </div>
  <?php if($r['notes']): ?><div style="margin-top:8px"><?=nl2br(htmlspecialchars($r['notes']))?></div><?php endif; ?>
  <?php if(!empty($r['attachment'])): ?><div style="margin-top:8px"><a href="<?=htmlspecialchars(base_url().$r['attachment'])?>" target="_blank">📎 Dosyayı Gör</a></div><?php endif; ?>
  <?php if(can_edit_delete()): ?>
  <div style="display:flex;gap:8px;margin-top:12px;flex-wrap:wrap">
    <form method="post" style="margin:0" onsubmit="return confirm('Bu kaydı silmek istediğinize emin misiniz?')">
      <input type="hidden" name="delete_cn" value="1">
      <button class="btn" style="background:#dc2626;color:#fff;padding:9px 16px;font-size:14px">🗑 Sil</button>
    </form>
  </div>
  <?php endif; ?>
</div>

<?php if(can_edit_delete()): ?>
<details class="panel">
  <summary style="font-weight:900;cursor:pointer">✏️ Kaydı Düzenle</summary>
  <form method="post" style="margin-top:10px" enctype="multipart/form-data">
    <label>Yön</label>
    <select name="direction" id="cn-dir-edit" onchange="updateCnStatusLabels(this)"><?php foreach($dirOpts as $dk=>$dl): ?><option value="<?=$dk?>" <?=$rDir===$dk?'selected':''?>><?=htmlspecialchars($dl)?></option><?php endforeach; ?></select>
    <label>Tür</label>
    <select name="type"><?php foreach($typeOpts as $tk=>$tl): ?><option value="<?=$tk?>" <?=$r['type']===$tk?'selected':''?>><?=htmlspecialchars($tl)?></option><?php endforeach; ?></select>
    <label>Numara</label>
    <input name="number" value="<?=htmlspecialchars($r['number']??'')?>">
    <label>Tutar</label>
    <input type="number" step="0.01" name="amount" value="<?=htmlspecialchars($r['amount'])?>" required>
    <label>Vade Tarihi</label>
    <input type="date" name="due_date" value="<?=htmlspecialchars($r['due_date']??'')?>">
    <label>Cari <small class="muted">(opsiyonel)</small></label>
    <select name="contact_id"><option value="">— Cari seçilmedi —</option>
    <?php foreach($contacts as $c): ?><option value="<?=$c['id']?>" <?=(int)$r['contact_id']===(int)$c['id']?'selected':''?>><?=htmlspecialchars($c['name'])?></option><?php endforeach; ?></select>
    <label>Banka Adı <small class="muted">(çek ise)</small></label>
    <input name="bank_name" value="<?=htmlspecialchars($r['bank_name']??'')?>">
    <label>Durum</label>
    <select name="status" id="cn-status-edit"><?php foreach($statusOpts as $sk=>$sl): ?><option value="<?=$sk?>" <?=$r['status']===$sk?'selected':''?>><?=htmlspecialchars($sl)?></option><?php endforeach; ?></select>
    <label>Not</label>
    <textarea name="notes" rows="2"><?=htmlspecialchars($r['notes']??'')?></textarea>
    <label>Fotoğraf / Dosya <small class="muted">(yeni seçilirse eskisinin yerine geçer, boş bırakılırsa korunur)</small></label>
    <input type="file" name="attachment" accept=".jpg,.jpeg,.png,.webp,.gif,.pdf">
    <button class="btn dark" name="edit_cn" value="1" style="width:100%;padding:13px;margin-top:8px">💾 Kaydet</button>
  </form>
</details>
<?php endif; ?>
<script>
var CN_STATUS_LABELS = {
  portfoyde: {alinan:'Portföyde', verilen:'Verildi (Bekliyor)'},
  tahsil_edildi: {alinan:'Tahsil Edildi', verilen:'Ödendi'},
  ciro_edildi: {alinan:'Ciro Edildi', verilen:'Ciro Edildi'},
  karsiliksiz: {alinan:'Karşılıksız', verilen:'Karşılıksız Döndü'},
  iptal: {alinan:'İptal', verilen:'İptal'}
};
function updateCnStatusLabels(dirSel){
    var form = dirSel.closest('form');
    var statusSel = form ? form.querySelector('select[name="status"]') : null;
    if(!statusSel) return;
    Array.prototype.forEach.call(statusSel.options, function(opt){
        var lbl = CN_STATUS_LABELS[opt.value];
        if(lbl) opt.textContent = lbl[dirSel.value] || lbl.alinan;
    });
}
</script>
<?php
}catch(Throwable $e){ echo '<div class="err">'.htmlspecialchars($e->getMessage()).'</div>'; }
botx();
