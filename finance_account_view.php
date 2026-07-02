<?php
/* Hesap ekstresi — tek banka/kasa/kart/POS hesabının hareketleri ve güncel bakiyesi. */
require_once __DIR__.'/boot.php';
require_login();
require_once __DIR__.'/finance_lib.php';
$pdo=db();
$id=(int)($_GET['id'] ?? 0);
$editError='';
$editOk='';

if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['edit_account'])){
    try{
        finance_account_update($pdo, $id, $_POST);
        $editOk='Hesap güncellendi.';
    }catch(Throwable $e){
        $editError=$e->getMessage();
    }
}

$a=null;
try{ $s=$pdo->prepare("SELECT * FROM finance_accounts WHERE id=?"); $s->execute([$id]); $a=$s->fetch(); }catch(Throwable $e){}

require_once __DIR__.'/layout_top.php';
if(!$a){ echo '<div class="alert">Hesap bulunamadı.</div>'; require __DIR__.'/layout_bottom.php'; exit; }

// Bu hesabı etkileyen tüm hareketler (doğrudan ya da transfer hedefi)
$rows=[];
try{
    $q=$pdo->prepare("SELECT m.*, c.name contact_name FROM finance_movements m
        LEFT JOIN contacts c ON c.id=m.contact_id
        WHERE m.account_id=? OR m.target_account_id=?
        ORDER BY m.movement_date DESC, m.id DESC LIMIT 300");
    $q->execute([$id,$id]);
    $rows=$q->fetchAll();
}catch(Throwable $e){}

$isCard = ($a['account_type']==='Kredi Kartı');
?>
<div class="panel-head">
  <h1><?=h($a['name'])?> <span class="muted" style="font-size:14px">· <?=h($a['account_type'])?><?=$a['bank_name']?' · '.h($a['bank_name']):''?></span></h1>
  <div class="actions">
    <a class="btn secondary" href="finance_accounts.php">Hesaplar</a>
    <?php if($isCard): ?><a class="btn" href="finance_transfer.php?to=<?=$id?>">💳 Karta Ödeme</a><?php endif; ?>
    <a class="btn" href="finance_new.php?direction=in&account_id=<?=$id?>">+ Giriş</a>
    <a class="btn secondary" href="finance_new.php?direction=out&account_id=<?=$id?>">+ Çıkış</a>
    <?=delete_button('account',$id)?>
  </div>
</div>

<?php if($editOk): ?><div class="ok"><?=h($editOk)?></div><?php endif; ?>
<?php if($editError): ?><div class="alert"><?=h($editError)?></div><?php endif; ?>

<div class="cards">
  <div class="card"><small>Güncel Bakiye</small><strong><?=money($a['current_balance'])?></strong></div>
  <div class="card"><small>Açılış Bakiyesi</small><strong><?=money($a['opening_balance'])?></strong></div>
  <div class="card"><small><?=$isCard?'Kart No':'IBAN'?></small><strong style="font-size:16px"><?=h($isCard ? ($a['card_last4']?'**** '.$a['card_last4']:'-') : ($a['iban']?:'-'))?></strong></div>
  <div class="card"><small>Hareket Sayısı</small><strong><?=count($rows)?></strong></div>
</div>

<section class="panel">
<details><summary style="font-weight:900;cursor:pointer;font-size:18px">✏️ Hesabı Düzenle</summary>
<form method="post" class="form-grid" style="margin-top:10px">
<label>Hesap Adı<input name="name" required value="<?=h($a['name'])?>"></label>
<label>Hesap Tipi
<select name="account_type">
<?php foreach(finance_account_types() as $t): ?><option <?=$a['account_type']===$t?'selected':''?>><?=h($t)?></option><?php endforeach; ?>
</select>
</label>
<label>Banka Adı<input name="bank_name" value="<?=h($a['bank_name'])?>"></label>
<label>IBAN<input name="iban" value="<?=h($a['iban'])?>"></label>
<label>Kart Son 4 Hane<input name="card_last4" maxlength="4" value="<?=h($a['card_last4'])?>"></label>
<label>Para Birimi
<select name="currency">
<?php foreach(['TRY','USD','EUR'] as $c): ?><option <?=$a['currency']===$c?'selected':''?>><?=h($c)?></option><?php endforeach; ?>
</select>
</label>
<label class="full">Notlar<textarea name="notes" rows="2"><?=h($a['notes'])?></textarea></label>
<label class="full"><input type="checkbox" name="active" <?=$a['active']?'checked':''?> style="width:auto"> Aktif</label>
<button class="btn" name="edit_account" value="1">💾 Kaydet</button>
</form>
</details>
</section>

<?php if($isCard): ?>
<div class="ok" style="background:#eff6ff;color:#1e40af">💳 Bu bir kredi kartı hesabıdır. Karta yapılan ödemeler (kasa/bankadan) <b>Karta Ödeme</b> ile işlenir; harcamalar <b>+ Çıkış</b> olarak girilir.</div>
<?php endif; ?>

<section class="panel">
<h2>Hesap Hareketleri</h2>
<table>
<thead><tr><th>Tarih</th><th>Açıklama</th><th>Cari</th><th>Tür</th><th style="text-align:right">Tutar</th></tr></thead>
<tbody>
<?php foreach($rows as $m):
    // Bu hesaba etkisi: transfer hedefi ise giriş (+); değilse direction'a göre
    $incoming = ((int)$m['target_account_id']===$id) || ($m['direction']==='in' && (int)$m['account_id']===$id);
    $sign = $incoming ? '+' : '−';
    $tone = $incoming ? 'green' : 'red';
    $label = ($m['movement_type']==='transfer') ? ((int)$m['target_account_id']===$id?'Transfer Giriş':'Transfer Çıkış') : ($m['direction']==='in'?'Tahsilat':'Ödeme');
?>
<tr>
  <td><?=h($m['movement_date'])?></td>
  <td><?=h($m['description'] ?: '-')?></td>
  <td><?=h($m['contact_name'] ?: '-')?></td>
  <td><?=badge($label,$tone)?></td>
  <td style="text-align:right;font-weight:700;color:<?=$incoming?'#166534':'#991b1b'?>"><?=$sign.' '.money($m['amount'])?></td>
</tr>
<?php endforeach; ?>
<?php if(!$rows): ?><tr><td colspan="5" class="muted">Bu hesapta henüz hareket yok.</td></tr><?php endif; ?>
</tbody>
</table>
</section>

<?php require_once __DIR__.'/layout_bottom.php'; ?>
