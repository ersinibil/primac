<?php
require_once 'common.php';
require_once dirname(__DIR__).'/finance_lib.php';
$pdo=db(); $id=(int)($_GET['id']??0);

/* Hesap bilgisi düzenle — topx'tan ÖNCE (PRG) */
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['edit_account'])){
    if(!can_edit_delete()){
        $_SESSION['acc_err']='Bu işlem için yetkiniz yok.';
        header('Location: account_view.php?id='.$id); exit;
    }
    try{
        finance_account_update($pdo,$id,$_POST);
        header('Location: account_view.php?id='.$id.'&ok=1'); exit;
    }catch(Throwable $e){
        $_SESSION['acc_err']=$e->getMessage();
        header('Location: account_view.php?id='.$id); exit;
    }
}
/* Hesap sil — admin veya 'edit_delete' yetkili personel, hareketi olan hesaplar pasife alınır (soft-delete) */
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['delete_account'])){
    if(can_edit_delete()){
        try{
            $res=finance_account_delete($pdo,$id);
            if($res['ok'] && !$res['soft']){ header('Location: kasa.php?deleted=1'); exit; }
            if($res['ok']){ $_SESSION['acc_ok']=$res['msg']; header('Location: account_view.php?id='.$id); exit; }
            $_SESSION['acc_err']=$res['msg'];
            header('Location: account_view.php?id='.$id); exit;
        }catch(Throwable $e){
            $_SESSION['acc_err']=$e->getMessage();
            header('Location: account_view.php?id='.$id); exit;
        }
    }
    header('Location: account_view.php?id='.$id); exit;
}

topx('Hesap');
if(!empty($_GET['ok'])) echo ds_alert('success','Hesap güncellendi.');
if(!empty($_GET['deleted'])) echo ds_alert('success','Finans hareketi silindi, hesap bakiyesi güncellendi.');
if(!empty($_SESSION['acc_ok'])){ echo ds_alert('success',$_SESSION['acc_ok']); unset($_SESSION['acc_ok']); }
if(!empty($_SESSION['acc_err'])){ echo ds_alert('danger',$_SESSION['acc_err']); unset($_SESSION['acc_err']); }
try{
    $a=$pdo->prepare("SELECT * FROM finance_accounts WHERE id=?"); $a->execute([$id]); $acc=$a->fetch();
    if(!$acc) throw new Exception('Hesap bulunamadı.');
    $ic=$acc['account_type']==='Banka'?'🏦':($acc['account_type']==='Kredi Kartı'?'💳':($acc['account_type']==='POS'?'🧾':'💵'));
    // bu hesabın dönem giriş/çıkış
    $sm=$pdo->prepare("SELECT COALESCE(SUM(CASE WHEN direction='in' THEN amount END),0) tin, COALESCE(SUM(CASE WHEN direction='out' THEN amount END),0) tout FROM finance_movements WHERE account_id=?");
    $sm->execute([$id]); $t=$sm->fetch();
?>
<div class="df-panel">
  <h2 style="margin:0 0 4px"><?=$ic?> <?=h($acc['name'])?></h2>
  <div class="muted"><?=h($acc['account_type'].($acc['bank_name']?' · '.$acc['bank_name']:'').($acc['iban']?' · '.$acc['iban']:''))?></div>
  <div style="font-size:30px;font-weight:900;margin-top:12px;color:<?=(float)$acc['current_balance']<0?'#f87171':'#22c55e'?>"><?=mm($acc['current_balance']??0)?></div>
  <div style="display:flex;gap:14px;margin-top:6px">
    <small style="color:#4ade80">↓ Giriş: <?=mm($t['tin'])?></small>
    <small style="color:#f87171">↑ Çıkış: <?=mm($t['tout'])?></small>
  </div>
  <?php if(can_edit_delete()): ?>
  <div style="display:flex;gap:8px;margin-top:12px;flex-wrap:wrap">
    <form method="post" style="margin:0" onsubmit="return confirm('Bu hesabı silmek istediğinize emin misiniz? Hareketi olan hesaplar kalıcı silinmez, pasife alınır.')">
      <input type="hidden" name="delete_account" value="1">
      <button class="df-btn df-btn--danger"><?=ds_icon('trash',16)?> Sil</button>
    </form>
  </div>
  <?php endif; ?>
</div>

<?php if(can_edit_delete()): ?>
<details class="df-panel">
  <summary style="font-weight:900;cursor:pointer"><?=ds_icon('edit',16)?> Hesap Bilgilerini Düzenle</summary>
  <form method="post" style="margin-top:10px">
    <label>Hesap Adı</label><input name="name" value="<?=h($acc['name'])?>" required>
    <label>Hesap Tipi</label>
    <select name="account_type">
      <?php foreach(finance_account_types() as $ft): ?><option <?=$acc['account_type']===$ft?'selected':''?>><?=h($ft)?></option><?php endforeach; ?>
    </select>
    <label>Banka Adı</label><input name="bank_name" value="<?=h($acc['bank_name']??'')?>">
    <label>IBAN</label><input name="iban" value="<?=h($acc['iban']??'')?>">
    <label>Kart Son 4 Hane</label><input name="card_last4" maxlength="4" value="<?=h($acc['card_last4']??'')?>">
    <label>Para Birimi</label>
    <select name="currency">
      <?php foreach(['TRY','USD','EUR'] as $fc): ?><option <?=$acc['currency']===$fc?'selected':''?>><?=$fc?></option><?php endforeach; ?>
    </select>
    <label>Not</label><textarea name="notes" rows="2"><?=h($acc['notes']??'')?></textarea>
    <label style="display:flex;align-items:center;gap:8px"><input type="checkbox" name="active" <?=$acc['active']?'checked':''?> style="width:auto"> Aktif hesap</label>
    <button class="df-btn df-btn--primary df-btn--lg" name="edit_account" value="1" style="width:100%;margin-top:8px"><?=ds_icon('check',16)?> Kaydet</button>
  </form>
</details>
<?php endif; ?>

<div class="df-panel"><b><?=ds_icon('info',16)?> Hesap Hareketleri</b>
<?php
  $mv=$pdo->prepare("SELECT f.*, c.name cari FROM finance_movements f LEFT JOIN contacts c ON c.id=f.contact_id WHERE f.account_id=? ORDER BY f.id DESC LIMIT 100");
  $mv->execute([$id]); $rows=$mv->fetchAll();
  if(!$rows) echo '<p class="muted" style="margin:10px 0 0">Bu hesapta hareket yok.</p>';
  // FINANCE CRUD UX PATCH 001 (2026-07-12): aynı karar mekanizması web (contact_view.php/
  // finance_account_view.php) ile birebir — manuel hareketler mevcut mobil düzenleme ekranına
  // (movement_view.php) bağlanıyor, yeni bir CRUD YAZILMADI. Tip etiketi finance_movement_type_label()
  // ile diğer ekranlarla tutarlı (satış/alış/belge kaynaklı satırlar artık "Tahsilat"/"Ödeme" değil).
  foreach($rows as $m){
    $in=$m['direction']==='in';
    $actions=finance_movement_actions($m);
    $canEdit=$actions['editable'] && can_edit_delete();
    $srcUrl=$actions['source_url'];
    if($srcUrl && in_array($actions['source_type'],['document','settlement'],true)) $srcUrl='../'.$srcUrl; // trade_document_view.php/finance.php sadece kökte var
    $__title='<span style="color:'.($in?'#4ade80':'#f87171').'">'.h(finance_movement_type_label($m)).'</span> '.mm($m['amount']);
    $__desc=h(($m['cari']?:'-').' · '.($m['description']??''));
    $__meta='<span class="df-list-row-due">'.h($m['movement_date']??'').'</span>';
    if($canEdit){
        $__meta.='<a class="df-btn df-btn--secondary df-btn--sm" href="movement_view.php?id='.(int)$m['id'].'&return_context=account&return_ref='.$id.'">'.ds_icon('edit',14).'</a>';
    }elseif($srcUrl){
        $__meta.='<a class="df-btn df-btn--secondary df-btn--sm" href="'.h($srcUrl).'">'.ds_icon('chevron-right',14).'</a>';
    }
    ds_list_item($__title, null, $__desc, $__meta, false);
  }
?>
</div>
<?php
}catch(Throwable $e){ echo ds_alert('danger',$e->getMessage()); }
botx();
