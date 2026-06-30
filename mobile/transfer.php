<?php
require_once 'common.php';
block_personel();
$pdo=db();

// POST işlemini topx'tan ÖNCE yap → header redirect (PRG)
if($_SERVER['REQUEST_METHOD']==='POST'){
    $err='';
    try{
        $from=(int)($_POST['from_account_id']??0);
        $to=(int)($_POST['to_account_id']??0);
        $amount=(float)str_replace(',','.',$_POST['amount']??'0');
        if(!$from || !$to) throw new Exception('Kaynak ve hedef hesap seçilmelidir.');
        if($from===$to) throw new Exception('Kaynak ve hedef hesap aynı olamaz.');
        if($amount<=0) throw new Exception('Tutar sıfırdan büyük olmalı.');

        $date=$_POST['movement_date'] ?: date('Y-m-d');
        $desc=trim($_POST['description']??'') ?: 'Hesaplar arası transfer';

        $pdo->prepare("INSERT INTO finance_movements(direction,amount,payment_channel,account_id,target_account_id,status,movement_date,description,movement_type)
            VALUES('out',?,'Transfer',?,?,'Tamamlandı',?,?,'transfer')")
            ->execute([$amount,$from,$to,$date,$desc]);
        $fmId=$pdo->lastInsertId();

        // Kaynaktan düş, hedefe ekle (kredi kartına transfer = karta ödeme: borç azalır)
        $pdo->prepare("UPDATE finance_accounts SET current_balance=current_balance-? WHERE id=?")->execute([$amount,$from]);
        $pdo->prepare("UPDATE finance_accounts SET current_balance=current_balance+? WHERE id=?")->execute([$amount,$to]);

        try{
            $a=$pdo->prepare("SELECT name FROM finance_accounts WHERE id=?");
            $a->execute([$from]); $fromName=$a->fetch()['name'] ?? 'Kaynak';
            $a->execute([$to]); $toName=$a->fetch()['name'] ?? 'Hedef';
            if(function_exists('activity_log')) activity_log('Finans','Transfer','Hesaplar arası transfer',$fromName.' → '.$toName.' · '.mm($amount),'finance',$fmId,'mobile/kasa.php','↔️');
        }catch(Throwable $e){}
    }catch(Throwable $e){ $err=$e->getMessage(); }
    if($err===''){ header('Location: kasa.php?ok=transfer'); exit; }
    $_SESSION['transfer_err']=$err;
    header('Location: transfer.php'); exit;
}

topx('Hesap Transferi');
if(!empty($_SESSION['transfer_err'])){ echo '<div class="err">'.htmlspecialchars($_SESSION['transfer_err']).'</div>'; unset($_SESSION['transfer_err']); }

$accounts=[];
try{ $accounts=$pdo->query("SELECT * FROM finance_accounts WHERE COALESCE(active,1)=1 ORDER BY account_type,name")->fetchAll(); }catch(Throwable $e){}
?>
<div class="panel" style="display:flex;gap:8px"><a class="btn dark" href="kasa.php" style="flex:1;text-align:center">🏦 Kasa Durumu</a></div>

<?php if(!$accounts): ?>
<div class="panel"><p class="muted">Önce Kasa ekranından hesap eklemelisiniz.</p></div>
<?php else: ?>
<div class="panel">
<form method="post">
  <label>Kaynak Hesap</label>
  <select name="from_account_id" required><option value="">— Seç —</option>
  <?php foreach($accounts as $a): ?><option value="<?=$a['id']?>"><?=htmlspecialchars($a['account_type'].' - '.$a['name'].' / '.mm($a['current_balance']??0))?></option><?php endforeach; ?></select>

  <label>Hedef Hesap <small class="muted">(kredi kartı = karta ödeme)</small></label>
  <select name="to_account_id" required><option value="">— Seç —</option>
  <?php foreach($accounts as $a): ?><option value="<?=$a['id']?>"><?=htmlspecialchars($a['account_type'].' - '.$a['name'].' / '.mm($a['current_balance']??0))?></option><?php endforeach; ?></select>

  <label>Tutar</label><input type="number" step="0.01" name="amount" required>
  <label>Tarih</label><input type="date" name="movement_date" value="<?=date('Y-m-d')?>">
  <label>Açıklama</label><textarea name="description" rows="2" placeholder="Hesaplar arası transfer"></textarea>
  <button class="btn dark" style="width:100%;padding:14px;margin-top:8px">↔️ Transferi Yap</button>
</form>
</div>
<?php endif; ?>
<?php botx(); ?>
