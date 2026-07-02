<?php
require_once 'common.php';
$pdo=db();
$cid=(int)($_GET['contact_id'] ?? 0);
$ok=''; $er='';

function acc_for_pm($pdo,$pm){
    $map=['Nakit'=>'Kasa','Banka'=>'Banka','Kredi Kartı'=>'Kredi Kartı','POS'=>'POS','Çek'=>'Diğer','Senet'=>'Diğer'];
    $type=$map[$pm] ?? 'Kasa';
    try{ $s=$pdo->prepare("SELECT id FROM finance_accounts WHERE account_type=? AND active=1 ORDER BY id LIMIT 1"); $s->execute([$type]); $r=$s->fetch(); return $r?(int)$r['id']:null; }catch(Throwable $e){ return null; }
}

if($_SERVER['REQUEST_METHOD']==='POST'){
    try{
        $contact=(int)$_POST['contact_id'];
        $amount=(float)$_POST['amount'];
        $pm=$_POST['payment_channel'] ?? 'Nakit';
        if(!$contact) throw new Exception('Cari seçin.');
        if($amount<=0) throw new Exception('Tutar geçersiz.');
        $accId=acc_for_pm($pdo,$pm);
        $pdo->prepare("INSERT INTO finance_movements(contact_id,direction,amount,payment_channel,account_id,status,movement_date,description,movement_type)
            VALUES(?,?,?,?,?,?,?,?,'mobile')")
            ->execute([$contact,'in',$amount,$pm,$accId,'Tahsil Edildi',date('Y-m-d'),trim($_POST['description'] ?? '')]);
        if($accId){ try{ $pdo->prepare("UPDATE finance_accounts SET current_balance=current_balance+? WHERE id=?")->execute([$amount,$accId]); }catch(Throwable $e){} }
        $cn=$pdo->prepare("SELECT name FROM contacts WHERE id=?"); $cn->execute([$contact]); $cname=$cn->fetch()['name']??'';
        try{ if(function_exists('activity_log')) activity_log('Finans','Tahsilat',$cname.' · '.mm($amount),$pm,'finance',$contact,'mobile/contact_view.php?id='.$contact,'💰'); }catch(Throwable $e){}
        $_SESSION['collection_ok']=$cname.' tahsilat: '.mm($amount).' ('.$pm.')';
        header('Location: collection.php?contact_id='.$contact); exit;
    }catch(Throwable $e){
        $_SESSION['collection_err']=$e->getMessage();
        header('Location: collection.php'.($cid?'?contact_id='.$cid:'')); exit;
    }
}
topx('Tahsilat');
if(!empty($_SESSION['collection_ok'])){ $ok=$_SESSION['collection_ok']; unset($_SESSION['collection_ok']); }
if(!empty($_SESSION['collection_err'])){ $er=$_SESSION['collection_err']; unset($_SESSION['collection_err']); }
$cs=$pdo->query("SELECT id,name FROM contacts ORDER BY name")->fetchAll();
?>
<?php if($ok): ?><div class="notice"><?=htmlspecialchars($ok)?></div><?php endif; ?>
<?php if($er): ?><div class="err"><?=htmlspecialchars($er)?></div><?php endif; ?>
<div class="panel">
<form method="post">
  <label>Cari</label>
  <select name="contact_id" required><option value="">— Seç —</option>
  <?php foreach($cs as $c): ?><option value="<?=$c['id']?>" <?=$cid===(int)$c['id']?'selected':''?>><?=htmlspecialchars($c['name'])?></option><?php endforeach; ?></select>
  <label>Tutar</label><input type="number" step="0.01" name="amount" required>
  <label>Tahsilat Yöntemi</label>
  <select name="payment_channel"><option>Nakit</option><option>Banka</option><option>Kredi Kartı</option><option>POS</option><option>Çek</option><option>Senet</option></select>
  <label>Açıklama</label><textarea name="description" rows="2"></textarea>
  <button class="btn dark" style="width:100%;padding:14px;margin-top:8px">💰 Tahsilatı Kaydet</button>
</form>
</div>
<div class="panel"><b>Son Tahsilatlar</b>
<?php
try{
  $recent=$pdo->prepare("SELECT f.*, c.name cari FROM finance_movements f LEFT JOIN contacts c ON c.id=f.contact_id WHERE f.direction='in' AND f.movement_type IN ('normal','mobile') ORDER BY f.id DESC LIMIT 10");
  $recent->execute();
  $rrows=$recent->fetchAll();
  if(!$rrows) echo '<p class="muted" style="margin:10px 0 0">Henüz tahsilat yok.</p>';
  foreach($rrows as $m){
    echo '<a class="item" href="movement_view.php?id='.(int)$m['id'].'" style="display:block"><b style="color:#4ade80">'.mm($m['amount']).'</b><br><small>'.htmlspecialchars(($m['cari']?:'-').' · '.($m['payment_channel']?:'').' · '.($m['movement_date']??'')).'</small></a>';
  }
}catch(Throwable $e){ echo '<div class="err">'.htmlspecialchars($e->getMessage()).'</div>'; }
?>
</div>
<?php botx(); ?>
