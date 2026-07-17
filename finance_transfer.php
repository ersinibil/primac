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

<?php
ds_page_header('Hesaplar Arası Transfer', ds_icon('wallet',24), '', ds_button('Hesaplar','finance_accounts.php','secondary','','',true), false, true);
?>

<?php if($error): ?><?=ds_alert('danger',$error)?><?php endif; ?>

<section class="df-card">
<form method="post" class="df-form-grid-2">

<?php
$__fromOpts='<option value="">Seçiniz</option>';
foreach($accounts as $a){ $__fromOpts.='<option value="'.$a['id'].'">'.h($a['account_type'].' - '.$a['name'].' / '.money($a['current_balance'])).'</option>'; }
ds_form_field('Kaynak Hesap', '<select name="from_account_id" required>'.$__fromOpts.'</select>');

$__toOpts='<option value="">Seçiniz</option>';
foreach($accounts as $a){ $__toOpts.='<option value="'.$a['id'].'" '.((int)($_GET['to']??0)===(int)$a['id']?'selected':'').'>'.h($a['account_type'].' - '.$a['name'].' / '.money($a['current_balance'])).'</option>'; }
ds_form_field('Hedef Hesap', '<select name="to_account_id" required>'.$__toOpts.'</select>');

ds_form_field('Tutar', '<input type="number" step="0.01" name="amount" required>');
ds_form_field('Tarih', '<input type="date" name="movement_date" value="'.date('Y-m-d').'">');
?>

<div class="df-form-span-2"><?php ds_form_field('Açıklama', '<textarea name="description" rows="3"></textarea>'); ?></div>

<div class="df-form-span-2"><button class="df-btn df-btn--primary">Transfer Yap</button></div>
</form>
</section>

<style>
body.nav-compact .df-form-grid-2{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:0 var(--df-space-4)}
body.nav-compact .df-form-span-2{grid-column:1 / -1}
@media(max-width:640px){body.nav-compact .df-form-grid-2{grid-template-columns:1fr}}
</style>

<?php require_once __DIR__.'/layout_bottom.php'; ?>
