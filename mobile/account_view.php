<?php
require_once 'common.php';
require_once dirname(__DIR__).'/finance_lib.php';
$pdo=db(); $id=(int)($_GET['id']??0);

/* Hesap bilgisi düzenle — topx'tan ÖNCE (PRG) */
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['edit_account'])){
    try{
        finance_account_update($pdo,$id,$_POST);
        header('Location: account_view.php?id='.$id.'&ok=1'); exit;
    }catch(Throwable $e){
        $_SESSION['acc_err']=$e->getMessage();
        header('Location: account_view.php?id='.$id); exit;
    }
}
/* Hesap sil — sadece yönetici, hareketi olan hesaplar pasife alınır (soft-delete) */
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['delete_account'])){
    if($isAdmin){
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
if(!empty($_GET['ok'])) echo '<div class="ok">Hesap güncellendi.</div>';
if(!empty($_SESSION['acc_ok'])){ echo '<div class="ok">'.htmlspecialchars($_SESSION['acc_ok']).'</div>'; unset($_SESSION['acc_ok']); }
if(!empty($_SESSION['acc_err'])){ echo '<div class="err">'.htmlspecialchars($_SESSION['acc_err']).'</div>'; unset($_SESSION['acc_err']); }
try{
    $a=$pdo->prepare("SELECT * FROM finance_accounts WHERE id=?"); $a->execute([$id]); $acc=$a->fetch();
    if(!$acc) throw new Exception('Hesap bulunamadı.');
    $ic=$acc['account_type']==='Banka'?'🏦':($acc['account_type']==='Kredi Kartı'?'💳':($acc['account_type']==='POS'?'🧾':'💵'));
    // bu hesabın dönem giriş/çıkış
    $sm=$pdo->prepare("SELECT COALESCE(SUM(CASE WHEN direction='in' THEN amount END),0) tin, COALESCE(SUM(CASE WHEN direction='out' THEN amount END),0) tout FROM finance_movements WHERE account_id=?");
    $sm->execute([$id]); $t=$sm->fetch();
?>
<div class="panel">
  <h2 style="margin:0 0 4px"><?=$ic?> <?=htmlspecialchars($acc['name'])?></h2>
  <div class="muted"><?=htmlspecialchars($acc['account_type'].($acc['bank_name']?' · '.$acc['bank_name']:'').($acc['iban']?' · '.$acc['iban']:''))?></div>
  <div style="font-size:30px;font-weight:900;margin-top:12px;color:<?=(float)$acc['current_balance']<0?'#f87171':'#22c55e'?>"><?=mm($acc['current_balance']??0)?></div>
  <div style="display:flex;gap:14px;margin-top:6px">
    <small style="color:#4ade80">↓ Giriş: <?=mm($t['tin'])?></small>
    <small style="color:#f87171">↑ Çıkış: <?=mm($t['tout'])?></small>
  </div>
  <?php if($isAdmin): ?>
  <div style="display:flex;gap:8px;margin-top:12px;flex-wrap:wrap">
    <form method="post" style="margin:0" onsubmit="return confirm('Bu hesabı silmek istediğinize emin misiniz? Hareketi olan hesaplar kalıcı silinmez, pasife alınır.')">
      <input type="hidden" name="delete_account" value="1">
      <button class="btn" style="background:#dc2626;color:#fff;padding:9px 16px;font-size:14px">🗑 Sil</button>
    </form>
  </div>
  <?php endif; ?>
</div>

<details class="panel">
  <summary style="font-weight:900;cursor:pointer">✏️ Hesap Bilgilerini Düzenle</summary>
  <form method="post" style="margin-top:10px">
    <label>Hesap Adı</label><input name="name" value="<?=htmlspecialchars($acc['name'])?>" required>
    <label>Hesap Tipi</label>
    <select name="account_type">
      <?php foreach(finance_account_types() as $ft): ?><option <?=$acc['account_type']===$ft?'selected':''?>><?=htmlspecialchars($ft)?></option><?php endforeach; ?>
    </select>
    <label>Banka Adı</label><input name="bank_name" value="<?=htmlspecialchars($acc['bank_name']??'')?>">
    <label>IBAN</label><input name="iban" value="<?=htmlspecialchars($acc['iban']??'')?>">
    <label>Kart Son 4 Hane</label><input name="card_last4" maxlength="4" value="<?=htmlspecialchars($acc['card_last4']??'')?>">
    <label>Para Birimi</label>
    <select name="currency">
      <?php foreach(['TRY','USD','EUR'] as $fc): ?><option <?=$acc['currency']===$fc?'selected':''?>><?=$fc?></option><?php endforeach; ?>
    </select>
    <label>Not</label><textarea name="notes" rows="2"><?=htmlspecialchars($acc['notes']??'')?></textarea>
    <label style="display:flex;align-items:center;gap:8px"><input type="checkbox" name="active" <?=$acc['active']?'checked':''?> style="width:auto"> Aktif hesap</label>
    <button class="btn dark" name="edit_account" value="1" style="width:100%;padding:13px;margin-top:8px">💾 Kaydet</button>
  </form>
</details>

<div class="panel"><b>📜 Hesap Hareketleri</b>
<?php
  $mv=$pdo->prepare("SELECT f.*, c.name cari FROM finance_movements f LEFT JOIN contacts c ON c.id=f.contact_id WHERE f.account_id=? ORDER BY f.id DESC LIMIT 100");
  $mv->execute([$id]); $rows=$mv->fetchAll();
  if(!$rows) echo '<p class="muted" style="margin:10px 0 0">Bu hesapta hareket yok.</p>';
  foreach($rows as $m){ $in=$m['direction']==='in';
    echo '<div class="item" style="display:flex;justify-content:space-between"><span><b style="color:'.($in?'#4ade80':'#f87171').'">'.($in?'Tahsilat':'Ödeme').'</b> '.mm($m['amount']).'<br><small class="muted">'.htmlspecialchars(($m['cari']?:'-').' · '.($m['description']??'')).'</small></span><small class="muted">'.htmlspecialchars($m['movement_date']??'').'</small></div>';
  }
?>
</div>
<?php
}catch(Throwable $e){ echo '<div class="err">'.htmlspecialchars($e->getMessage()).'</div>'; }
botx();
