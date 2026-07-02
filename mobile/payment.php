<?php
require_once 'common.php';
require_once __DIR__.'/../accounting_lib.php';
$pdo=db();
$cid=(int)($_GET['contact_id'] ?? 0);

// payment_channel → account_type eşlemesi (collection.php ile aynı mantık)
function pay_acc_for_pm($pdo,$pm){
    $map=['Nakit'=>'Kasa','Banka'=>'Banka','Kredi Kartı'=>'Kredi Kartı','POS'=>'POS','Çek'=>'Diğer','Senet'=>'Diğer'];
    $type=$map[$pm] ?? 'Kasa';
    try{ $s=$pdo->prepare("SELECT id FROM finance_accounts WHERE account_type=? AND COALESCE(active,1)=1 ORDER BY id LIMIT 1"); $s->execute([$type]); $r=$s->fetch(); return $r?(int)$r['id']:null; }catch(Throwable $e){ return null; }
}

// POST işlemini topx'tan ÖNCE yap → header redirect (PRG)
if($_SERVER['REQUEST_METHOD']==='POST'){
    $err='';
    try{
        $contact=(int)($_POST['contact_id']??0); // opsiyonel
        $catId=(int)($_POST['category_id']??0) ?: null; // opsiyonel — cari yerine/yanında
        $amount=(float)str_replace(',','.',$_POST['amount']??'0');
        $pm=$_POST['payment_channel'] ?? 'Nakit';
        if($amount<=0) throw new Exception('Tutar geçersiz.');

        // Hesap seçimi: form'dan gelirse onu kullan, yoksa yönteme göre bul
        $accId=(int)($_POST['account_id']??0);
        if(!$accId) $accId=pay_acc_for_pm($pdo,$pm);

        $pdo->prepare("INSERT INTO finance_movements(contact_id,category_id,direction,amount,payment_channel,account_id,status,movement_date,description,movement_type)
            VALUES(?,?,?,?,?,?,?,?,?,'mobile')")
            ->execute([$contact?:null,$catId,'out',$amount,$pm,$accId,'Ödendi',date('Y-m-d'),trim($_POST['description'] ?? '')]);

        if($accId){ try{ $pdo->prepare("UPDATE finance_accounts SET current_balance=current_balance-? WHERE id=?")->execute([$amount,$accId]); }catch(Throwable $e){} }

        $cname='Cari seçilmedi';
        if($contact){ try{ $cn=$pdo->prepare("SELECT name FROM contacts WHERE id=?"); $cn->execute([$contact]); $cname=$cn->fetch()['name']??$cname; }catch(Throwable $e){} }
        try{ if(function_exists('activity_log')) activity_log('Finans','Ödeme',$cname.' · '.mm($amount),$pm,'finance',$contact?:0,'mobile/kasa.php','💸'); }catch(Throwable $e){}
    }catch(Throwable $e){ $err=$e->getMessage(); }
    if($err===''){ header('Location: kasa.php?ok=payment'); exit; }
    $_SESSION['payment_err']=$err;
    header('Location: payment.php'.($cid?'?contact_id='.$cid:'')); exit;
}

topx('Ödeme / Gider');
if(!empty($_SESSION['payment_err'])){ echo '<div class="err">'.htmlspecialchars($_SESSION['payment_err']).'</div>'; unset($_SESSION['payment_err']); }

$cs=[]; $accounts=[]; $gcats=[];
try{ $cs=$pdo->query("SELECT id,name FROM contacts ORDER BY name")->fetchAll(); }catch(Throwable $e){}
try{ $accounts=$pdo->query("SELECT * FROM finance_accounts WHERE COALESCE(active,1)=1 ORDER BY account_type,name")->fetchAll(); }catch(Throwable $e){}
$gcats=acc_categories($pdo,'gider');
?>
<div class="panel" style="display:flex;gap:8px"><a class="btn dark" href="kasa.php" style="flex:1;text-align:center">🏦 Kasa Durumu</a></div>
<div class="panel">
<form method="post">
  <label>Cari <small class="muted">(opsiyonel)</small></label>
  <select name="contact_id"><option value="">— Cari seçilmedi —</option>
  <?php foreach($cs as $c): ?><option value="<?=$c['id']?>" <?=$cid===(int)$c['id']?'selected':''?>><?=htmlspecialchars($c['name'])?></option><?php endforeach; ?></select>

  <label>Kategori <small class="muted">(opsiyonel — cari yerine/yanında: personel yol gideri, yakıt, vergi, telefon vb.)</small></label>
  <select name="category_id"><option value="">— Kategori seçilmedi —</option>
  <?php foreach($gcats as $c): ?><option value="<?=(int)$c['id']?>">[<?=htmlspecialchars($c['group_name'])?>] <?=htmlspecialchars($c['name'])?></option><?php endforeach; ?></select>

  <label>Hesap / Kasa / Kart</label>
  <select name="account_id"><option value="">Yönteme göre otomatik</option>
  <?php foreach($accounts as $a): ?><option value="<?=$a['id']?>"><?=htmlspecialchars($a['account_type'].' - '.$a['name'].' / '.mm($a['current_balance']??0))?></option><?php endforeach; ?></select>

  <label>Tutar</label><input type="number" step="0.01" name="amount" required>
  <label>Ödeme Yöntemi</label>
  <select name="payment_channel"><option>Nakit</option><option>Banka</option><option>Kredi Kartı</option><option>POS</option><option>Çek</option><option>Senet</option></select>
  <label>Açıklama</label><textarea name="description" rows="2" placeholder="Gider / ödeme açıklaması"></textarea>
  <button class="btn dark" style="width:100%;padding:14px;margin-top:8px">💸 Ödemeyi Kaydet</button>
</form>
</div>
<div class="panel"><b>Son Ödemeler</b>
<?php
try{
  $recent=$pdo->prepare("SELECT f.*, c.name cari, ac.name kat FROM finance_movements f LEFT JOIN contacts c ON c.id=f.contact_id LEFT JOIN accounting_categories ac ON ac.id=f.category_id WHERE f.direction='out' AND f.movement_type IN ('normal','mobile') ORDER BY f.id DESC LIMIT 10");
  $recent->execute();
  $rrows=$recent->fetchAll();
  if(!$rrows) echo '<p class="muted" style="margin:10px 0 0">Henüz ödeme yok.</p>';
  foreach($rrows as $m){
    $tag=$m['cari'] ?: ($m['kat'] ?: '-');
    echo '<a class="item" href="movement_view.php?id='.(int)$m['id'].'" style="display:block"><b style="color:#f87171">'.mm($m['amount']).'</b><br><small>'.htmlspecialchars($tag.' · '.($m['payment_channel']?:'').' · '.($m['movement_date']??'')).'</small></a>';
  }
}catch(Throwable $e){ echo '<div class="err">'.htmlspecialchars($e->getMessage()).'</div>'; }
?>
</div>
<?php botx(); ?>
