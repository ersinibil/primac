<?php
require_once 'common.php';
block_personel();
$pdo=db();
// Yeni hesap ekle (POST topx'tan ÖNCE → redirect)
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['add_account'])){
    $err='';
    try{
        $name=trim($_POST['name']??''); $atype=$_POST['account_type']??'Kasa';
        if($name==='') throw new Exception('Hesap adı girin.');
        if(!in_array($atype,['Kasa','Banka','Kredi Kartı','POS'],true)) $atype='Kasa';
        $op=(float)str_replace(',','.',$_POST['opening_balance']??'0');
        $pdo->prepare("INSERT INTO finance_accounts(name,account_type,bank_name,opening_balance,current_balance,active) VALUES(?,?,?,?,?,1)")
            ->execute([$name,$atype,trim($_POST['bank_name']??''),$op,$op]);
    }catch(Throwable $e){ $err=$e->getMessage(); }
    if($err==='') { header('Location: kasa.php?ok=1'); exit; }
    $_SESSION['kasa_err']=$err;
}
topx('Kasa Durumu');
if(isset($_GET['ok'])) echo '<div class="ok">Hesap eklendi.</div>';
if(!empty($_SESSION['kasa_err'])){ echo '<div class="err">'.htmlspecialchars($_SESSION['kasa_err']).'</div>'; unset($_SESSION['kasa_err']); }

function acc_sum($pdo,$type){ try{ $s=$pdo->prepare("SELECT COALESCE(SUM(current_balance),0) s FROM finance_accounts WHERE active=1 AND account_type=?"); $s->execute([$type]); return (float)$s->fetch()['s']; }catch(Throwable $e){ return 0; } }
$kasa=acc_sum($pdo,'Kasa'); $banka=acc_sum($pdo,'Banka'); $kart=acc_sum($pdo,'Kredi Kartı'); $pos=acc_sum($pdo,'POS');
$inToday=safe_sum("SELECT COALESCE(SUM(amount),0) s FROM finance_movements WHERE direction='in' AND movement_date=CURDATE()");
$outToday=safe_sum("SELECT COALESCE(SUM(amount),0) s FROM finance_movements WHERE direction='out' AND movement_date=CURDATE()");
?>
<div class="grid">
  <div class="card green"><span>💵</span><b><?=mm($kasa)?></b><small>Kasa</small></div>
  <div class="card blue"><span>🏦</span><b><?=mm($banka)?></b><small>Banka</small></div>
  <div class="card red"><span>💳</span><b><?=mm($kart)?></b><small>Kredi Kartı</small></div>
  <div class="card yellow"><span>🧾</span><b><?=mm($pos)?></b><small>POS</small></div>
</div>
<div class="panel" style="display:flex;justify-content:space-between;align-items:center">
  <div><small class="muted">Bugün Tahsilat</small><div style="font-size:20px;font-weight:900;color:#4ade80"><?=mm($inToday)?></div></div>
  <div style="text-align:right"><small class="muted">Bugün Ödeme</small><div style="font-size:20px;font-weight:900;color:#f87171"><?=mm($outToday)?></div></div>
</div>

<div class="panel" style="display:flex;gap:8px"><a class="btn dark" href="report.php?modul=tahsilat" style="flex:1;text-align:center">📊 Finans Raporu</a></div>

<details class="panel"><summary style="font-weight:900;cursor:pointer">➕ Yeni Hesap Ekle (Banka / Kasa / Kart / POS)</summary>
<form method="post" style="margin-top:10px">
  <select name="account_type"><option>Kasa</option><option>Banka</option><option>Kredi Kartı</option><option>POS</option></select>
  <input name="name" placeholder="Hesap adı (örn. Ziraat Vadesiz / Ana Kasa)" required>
  <input name="bank_name" placeholder="Banka adı (kredi kartı/banka için)">
  <input name="opening_balance" type="number" step="0.01" placeholder="Açılış bakiyesi" value="0">
  <button class="btn dark" name="add_account" value="1" style="width:100%">💾 Hesap Ekle</button>
</form>
</details>

<div class="panel"><b>🏦 Hesaplar</b>
<?php
try{
  $accs=$pdo->query("SELECT * FROM finance_accounts WHERE COALESCE(active,1)=1 ORDER BY account_type,name")->fetchAll();
  if(!$accs) echo '<p class="muted" style="margin:10px 0 0">Henüz hesap yok — yukarıdan ekleyin.</p>';
  foreach($accs as $a){ $ic=$a['account_type']==='Banka'?'🏦':($a['account_type']==='Kredi Kartı'?'💳':($a['account_type']==='POS'?'🧾':'💵'));
    echo '<a class="item" href="account_view.php?id='.(int)$a['id'].'" style="display:flex;justify-content:space-between;align-items:center">'
       .'<span>'.$ic.' <b>'.htmlspecialchars($a['name']).'</b><br><small class="muted">'.htmlspecialchars($a['account_type'].($a['bank_name']?' · '.$a['bank_name']:'')).'</small></span>'
       .'<b style="color:'.((float)$a['current_balance']<0?'#f87171':'#4ade80').'">'.mm($a['current_balance']??0).'</b></a>';
  }
}catch(Throwable $e){ echo '<div class="err">'.htmlspecialchars($e->getMessage()).'</div>'; }
?>
</div>

<div class="panel"><b>Son Hareketler</b>
<?php
try{
  $rows=$pdo->query("SELECT f.*, c.name cari FROM finance_movements f LEFT JOIN contacts c ON c.id=f.contact_id ORDER BY f.id DESC LIMIT 30")->fetchAll();
  if(!$rows) echo '<p class="muted" style="margin:10px 0 0">Hareket yok.</p>';
  foreach($rows as $m){ $in=$m['direction']==='in';
    echo '<div class="item"><b style="color:'.($in?'#4ade80':'#f87171').'">'.($in?'Tahsilat':'Ödeme').': '.mm($m['amount']).'</b><br><small>'.htmlspecialchars(($m['cari']?:'-').' · '.($m['payment_channel']?:'').' · '.($m['movement_date']??'')).'</small></div>';
  }
}catch(Throwable $e){ echo '<div class="err">'.htmlspecialchars($e->getMessage()).'</div>'; }
?>
</div>
<?php botx(); ?>
