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
$statusOpts=checks_notes_statuses();
try{
    $r=checks_notes_get($pdo,$id);
    if(!$r) throw new Exception('Kayıt bulunamadı.');
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
    <small class="muted">Vade: <?=htmlspecialchars($r['due_date']?:'Vadesiz')?><?=$overdue?' ⚠️ Vadesi geçti':''?></small>
    <small class="muted">Durum: <?=htmlspecialchars($statusOpts[$r['status']]??$r['status'])?></small>
  </div>
  <?php if($r['notes']): ?><div style="margin-top:8px"><?=nl2br(htmlspecialchars($r['notes']))?></div><?php endif; ?>
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
  <form method="post" style="margin-top:10px">
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
    <select name="status"><?php foreach($statusOpts as $sk=>$sl): ?><option value="<?=$sk?>" <?=$r['status']===$sk?'selected':''?>><?=htmlspecialchars($sl)?></option><?php endforeach; ?></select>
    <label>Not</label>
    <textarea name="notes" rows="2"><?=htmlspecialchars($r['notes']??'')?></textarea>
    <button class="btn dark" name="edit_cn" value="1" style="width:100%;padding:13px;margin-top:8px">💾 Kaydet</button>
  </form>
</details>
<?php endif; ?>
<?php
}catch(Throwable $e){ echo '<div class="err">'.htmlspecialchars($e->getMessage()).'</div>'; }
botx();
