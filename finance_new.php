<?php
require_once __DIR__.'/boot.php';
require_login();
require_once __DIR__.'/activity_lib.php';
require_once __DIR__.'/accounting_lib.php';

$pdo=db();
$error='';
$direction=$_GET['direction'] ?? 'in';
if(!in_array($direction,['in','out'])) $direction='in';
$contactId=(int)($_GET['contact_id'] ?? 0);

if($_SERVER['REQUEST_METHOD']==='POST'){
    try{
        $direction=$_POST['direction'];
        $amount=(float)$_POST['amount'];
        $accountId=(int)$_POST['account_id'];
        $catId=(int)($_POST['category_id']??0) ?: null;
        if($amount<=0) throw new Exception('Tutar sıfırdan büyük olmalı.');
        if(!$accountId) throw new Exception('Hesap seçilmelidir.');

        $stmt=$pdo->prepare("INSERT INTO finance_movements(contact_id,category_id,job_id,direction,amount,payment_channel,account_id,status,movement_date,description,movement_type,reference_no)
            VALUES(?,?,NULL,?,?,?,?,?,?,?,'normal',?)");
        $status=$direction==='in'?'Tahsil Edildi':'Ödendi';
        $stmt->execute([
            (int)$_POST['contact_id'] ?: null,
            $catId,
            $direction,
            $amount,
            $_POST['payment_channel'],
            $accountId,
            $status,
            $_POST['movement_date'] ?: date('Y-m-d'),
            trim($_POST['description']),
            trim($_POST['reference_no'])
        ]);
        $fmId=$pdo->lastInsertId();

        if($direction==='in'){
            $pdo->prepare("UPDATE finance_accounts SET current_balance=current_balance+? WHERE id=?")->execute([$amount,$accountId]);
        }else{
            $pdo->prepare("UPDATE finance_accounts SET current_balance=current_balance-? WHERE id=?")->execute([$amount,$accountId]);
        }

        $contactName='Cari seçilmedi';
        if((int)$_POST['contact_id']){
            $cs=$pdo->prepare("SELECT name FROM contacts WHERE id=?");
            $cs->execute([(int)$_POST['contact_id']]);
            $contactName=$cs->fetch()['name'] ?? $contactName;
        }
        $as=$pdo->prepare("SELECT name FROM finance_accounts WHERE id=?");
        $as->execute([$accountId]);
        $accountName=$as->fetch()['name'] ?? $_POST['payment_channel'];

        $title = ($direction==='in'?'Tahsilat yapıldı':'Ödeme yapıldı');
        $desc = $contactName.' · '.number_format($amount,2,',','.').' ₺ · '.$accountName;
        activity_log('Finans',$direction==='in'?'Tahsilat':'Ödeme',$title,$desc,'finance',$fmId,'finance.php','💰');

        header("Location: finance.php");
        exit;
    }catch(Throwable $e){
        $error=$e->getMessage();
    }
}

require_once __DIR__.'/layout_top.php';

$contacts=$pdo->query("SELECT id,name,type FROM contacts ORDER BY name")->fetchAll();
$accounts=$pdo->query("SELECT * FROM finance_accounts WHERE active=1 ORDER BY account_type,name")->fetchAll();
$giderCats=acc_categories($pdo,'gider');
$gelirCats=acc_categories($pdo,'gelir');
?>

<div class="panel-head">
<h1><?=$direction==='in'?'Tahsilat':'Ödeme'?> Kaydı</h1>
<div class="actions">
<a class="btn secondary" href="finance.php">Finans</a>
<a class="btn secondary" href="finance_accounts.php">Hesaplar</a>
</div>
</div>

<?php if($error): ?><div class="alert"><?=h($error)?></div><?php endif; ?>

<section class="panel">
<form method="post" class="form-grid">

<label>İşlem Tipi
<select name="direction" id="fnDirection" onchange="fnFilterCats()">
<option value="in" <?=$direction==='in'?'selected':''?>>Tahsilat</option>
<option value="out" <?=$direction==='out'?'selected':''?>>Ödeme</option>
</select>
</label>

<label>Cari <small style="font-weight:400;color:#667085">(opsiyonel)</small>
<select name="contact_id">
<option value="">Cari seçilmedi</option>
<?php foreach($contacts as $c): ?>
<option value="<?=$c['id']?>" <?=$contactId===$c['id']?'selected':''?>><?=h($c['name'].' / '.$c['type'])?></option>
<?php endforeach; ?>
</select>
</label>

<label>Kategori <small style="font-weight:400;color:#667085">(cari yerine/yanında — personel yol gideri, yakıt, vergi, telefon vb.)</small>
<select name="category_id" id="fnCatSel">
<option value="">— Seç —</option>
<?php foreach($giderCats as $c): ?>
<option value="<?=(int)$c['id']?>" data-type="out" style="<?=$direction==='out'?'':'display:none'?>">[<?=h($c['group_name'])?>] <?=h($c['name'])?></option>
<?php endforeach; ?>
<?php foreach($gelirCats as $c): ?>
<option value="<?=(int)$c['id']?>" data-type="in" style="<?=$direction==='in'?'':'display:none'?>">[<?=h($c['group_name'])?>] <?=h($c['name'])?></option>
<?php endforeach; ?>
</select>
</label>

<label>Hesap / Banka / Kasa / Kart
<select name="account_id" required>
<option value="">Seçiniz</option>
<?php foreach($accounts as $a): ?>
<option value="<?=$a['id']?>" <?=(int)($_GET['account_id']??0)===(int)$a['id']?'selected':''?>><?=h($a['account_type'].' - '.$a['name'].' / '.money($a['current_balance']))?></option>
<?php endforeach; ?>
</select>
</label>

<label>Yöntem
<select name="payment_channel">
<option>Nakit</option>
<option>Banka / EFT</option>
<option>Kredi Kartı</option>
<option>POS</option>
<option>Çek</option>
<option>Diğer</option>
</select>
</label>

<label>Tutar
<input type="number" step="0.01" name="amount" required>
</label>

<label>Tarih
<input type="date" name="movement_date" value="<?=date('Y-m-d')?>">
</label>

<label>Referans No
<input name="reference_no" placeholder="Dekont, fiş, işlem no">
</label>

<label class="full">Açıklama
<textarea name="description" rows="3"></textarea>
</label>

<button class="btn">Kaydet</button>
</form>
</section>
<script>
function fnFilterCats(){
  var t=document.getElementById('fnDirection').value;
  var opts=document.getElementById('fnCatSel').options;
  for(var i=0;i<opts.length;i++){
    var o=opts[i];
    if(!o.dataset.type){o.style.display='';continue;}
    o.style.display=(o.dataset.type===t)?'':'none';
    if(o.dataset.type!==t&&o.selected) o.selected=false;
  }
}
</script>

<?php require_once __DIR__.'/layout_bottom.php'; ?>
