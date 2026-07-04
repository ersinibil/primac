<?php
require_once __DIR__.'/boot.php';
require_login();
require_once __DIR__.'/activity_lib.php';

$pdo=db();
$error='';

if($_SERVER['REQUEST_METHOD']==='POST'){
    try{
        $from=(int)$_POST['from_account_id'];
        $to=(int)$_POST['to_account_id'];
        $amount=(float)$_POST['amount'];
        if(!$from || !$to) throw new Exception('Kaynak ve hedef hesap seçilmelidir.');
        if($from===$to) throw new Exception('Kaynak ve hedef hesap aynı olamaz.');
        if($amount<=0) throw new Exception('Tutar sıfırdan büyük olmalı.');

        $date=$_POST['movement_date'] ?: date('Y-m-d');
        $desc=trim($_POST['description']) ?: 'Hesaplar arası transfer';

        $pdo->prepare("INSERT INTO finance_movements(direction,amount,payment_channel,account_id,target_account_id,status,movement_date,description,movement_type)
            VALUES('out',?,'Transfer',?,?,'Tamamlandı',?,?,'transfer')")
            ->execute([$amount,$from,$to,$date,$desc]);
        $fmId=$pdo->lastInsertId();

        $pdo->prepare("UPDATE finance_accounts SET current_balance=current_balance-? WHERE id=?")->execute([$amount,$from]);
        $pdo->prepare("UPDATE finance_accounts SET current_balance=current_balance+? WHERE id=?")->execute([$amount,$to]);

        $a=db()->prepare("SELECT name FROM finance_accounts WHERE id=?");
        $a->execute([$from]); $fromName=$a->fetch()['name'] ?? 'Kaynak';
        $a->execute([$to]); $toName=$a->fetch()['name'] ?? 'Hedef';

        activity_log('Finans','Transfer','Hesaplar arası transfer yapıldı',$fromName.' → '.$toName.' · '.money($amount),'finance',$fmId,base_url().'finance_accounts.php','↔️');

        header("Location: finance_accounts.php");
        exit;
    }catch(Throwable $e){
        $error=$e->getMessage();
    }
}

require_once __DIR__.'/layout_top.php';
$accounts=$pdo->query("SELECT * FROM finance_accounts WHERE active=1 ORDER BY account_type,name")->fetchAll();
?>

<div class="panel-head">
<h1>Hesaplar Arası Transfer</h1>
<a class="btn secondary" href="finance_accounts.php">Hesaplar</a>
</div>

<?php if($error): ?><div class="alert"><?=h($error)?></div><?php endif; ?>

<section class="panel">
<form method="post" class="form-grid">

<label>Kaynak Hesap
<select name="from_account_id" required>
<option value="">Seçiniz</option>
<?php foreach($accounts as $a): ?>
<option value="<?=$a['id']?>"><?=h($a['account_type'].' - '.$a['name'].' / '.money($a['current_balance']))?></option>
<?php endforeach; ?>
</select>
</label>

<label>Hedef Hesap
<select name="to_account_id" required>
<option value="">Seçiniz</option>
<?php foreach($accounts as $a): ?>
<option value="<?=$a['id']?>" <?=(int)($_GET['to']??0)===(int)$a['id']?'selected':''?>><?=h($a['account_type'].' - '.$a['name'].' / '.money($a['current_balance']))?></option>
<?php endforeach; ?>
</select>
</label>

<label>Tutar
<input type="number" step="0.01" name="amount" required>
</label>

<label>Tarih
<input type="date" name="movement_date" value="<?=date('Y-m-d')?>">
</label>

<label class="full">Açıklama
<textarea name="description" rows="3"></textarea>
</label>

<button class="btn">Transfer Yap</button>
</form>
</section>

<?php require_once __DIR__.'/layout_bottom.php'; ?>
