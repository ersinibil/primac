<?php
require_once 'common.php';
require_once __DIR__.'/../finance_lib.php';
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
if(isset($_GET['deleted'])) echo '<div class="ok">Hesap silindi.</div>';
if(!empty($_SESSION['kasa_err'])){ echo '<div class="err">'.htmlspecialchars($_SESSION['kasa_err']).'</div>'; unset($_SESSION['kasa_err']); }

function acc_sum($pdo,$type){ try{ $s=$pdo->prepare("SELECT COALESCE(SUM(current_balance),0) s FROM finance_accounts WHERE active=1 AND account_type=?"); $s->execute([$type]); return (float)$s->fetch()['s']; }catch(Throwable $e){ return 0; } }
$kasa=acc_sum($pdo,'Kasa'); $banka=acc_sum($pdo,'Banka'); $kart=acc_sum($pdo,'Kredi Kartı'); $pos=acc_sum($pdo,'POS');
// FİNANS ÇEKİRDEK DÜZELTMESİ (2026-07-10): sadece GERÇEK kasa/banka hareketleri sayılır —
// satış/alış (Bekliyor) hiçbir hesabı etkilemediği için account_id NULL kalır, bu yüzden
// "Bugün Tahsilat/Ödeme" bunları saymaz (aksi halde bekleyen bir satış "tahsil edilmiş" gibi görünürdü).
$inToday=safe_sum("SELECT COALESCE(SUM(amount),0) s FROM finance_movements WHERE direction='in' AND account_id IS NOT NULL AND movement_date=CURDATE()");
$outToday=safe_sum("SELECT COALESCE(SUM(amount),0) s FROM finance_movements WHERE direction='out' AND COALESCE(movement_type,'')<>'transfer' AND account_id IS NOT NULL AND movement_date=CURDATE()");
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

<?php
// FINANCE ACCOUNT LIST FILTER UX (2026-07-14) — web (finance_accounts.php) ile aynı whitelist
// mantığı finance_lib.php'den paylaşılıyor. Mobilin ESKİ varsayılanı "sadece aktif" idi (pasif
// hiç görünmüyordu) — bu davranış status='active' varsayılanıyla KORUNDU, kullanıcı isterse
// Durum filtresinden Pasif/Tümü'ne geçebilir (öncesinde bu seçenek hiç yoktu).
$mtype=$_GET['type'] ?? '';
$mstatus=$_GET['status'] ?? 'active';
$mbank=trim($_GET['bank'] ?? '');
$mq=trim($_GET['q'] ?? '');
$mHasFilter=($mtype!=='' || $mstatus!=='active' || $mbank!=='' || $mq!=='');
$mTypeCounts=finance_account_type_counts($pdo, $mstatus);
$mBankOptions=finance_account_bank_options($pdo);
function kasa_tab_url($typeVal,$status,$bank,$q){
    $qs=[];
    if($typeVal!=='') $qs['type']=$typeVal;
    if($status!=='active') $qs['status']=$status;
    if($bank!=='') $qs['bank']=$bank;
    if($q!=='') $qs['q']=$q;
    return 'kasa.php'.($qs ? '?'.http_build_query($qs) : '');
}
?>
<div style="display:flex;gap:6px;overflow:auto;margin:12px 0 8px;-webkit-overflow-scrolling:touch">
<?php
$mTabs=['' => '💰 Tümü ('.$mTypeCounts['all'].')', 'Kasa'=>'💵 Kasalar ('.$mTypeCounts['Kasa'].')', 'Banka'=>'🏦 Banka Hesapları ('.$mTypeCounts['Banka'].')', 'Kredi Kartı'=>'💳 Kredi Kartları ('.$mTypeCounts['Kredi Kartı'].')', 'Diger'=>'➕ Diğer ('.$mTypeCounts['Diger'].')'];
foreach($mTabs as $tv=>$label):
?>
  <a class="btn" style="white-space:nowrap;padding:8px 13px;<?=$mtype===$tv?'background:#2563eb;color:#fff':'background:#334155;color:#cbd5e1'?>" href="<?=htmlspecialchars(kasa_tab_url($tv,$mstatus,$mbank,$mq))?>"><?=htmlspecialchars($label)?></a>
<?php endforeach; ?>
</div>

<details class="panel"<?=$mHasFilter?' open':''?>><summary style="font-weight:900;cursor:pointer">🔎 Filtrele<?=$mHasFilter?' (aktif)':''?></summary>
<form method="get" style="margin-top:10px">
  <?php if($mtype!==''): ?><input type="hidden" name="type" value="<?=htmlspecialchars($mtype)?>"><?php endif; ?>
  <select name="status">
    <option value="active" <?=$mstatus==='active'?'selected':''?>>Aktif</option>
    <option value="passive" <?=$mstatus==='passive'?'selected':''?>>Pasif</option>
    <option value="" <?=$mstatus===''?'selected':''?>>Tümü</option>
  </select>
  <select name="bank">
    <option value="">Tüm Bankalar</option>
    <?php foreach($mBankOptions as $b): ?><option value="<?=htmlspecialchars($b)?>" <?=$mbank===$b?'selected':''?>><?=htmlspecialchars($b)?></option><?php endforeach; ?>
  </select>
  <input type="text" name="q" value="<?=htmlspecialchars($mq)?>" placeholder="Hesap, banka, IBAN veya kart ara...">
  <button class="btn dark" type="submit" style="width:100%">Uygula</button>
  <?php if($mHasFilter): ?><a href="kasa.php" class="btn" style="width:100%;text-align:center;display:block;margin-top:8px;background:#334155;color:#fff">✕ Filtreyi Temizle</a><?php endif; ?>
</form>
</details>

<div class="panel"><b>🏦 Hesaplar</b>
<?php
try{
  list($mWhere,$mParams)=finance_account_filter_where($mtype,$mstatus,$mbank,$mq);
  $accs=$pdo->prepare("SELECT * FROM finance_accounts $mWhere ORDER BY account_type,name");
  $accs->execute($mParams);
  $accs=$accs->fetchAll();
  if(!$accs){
    if($mHasFilter) echo '<p class="muted" style="margin:10px 0 0">Seçili filtrelere uygun hesap bulunamadı.<br><a href="kasa.php" style="color:#93c5fd">Filtreleri Temizle</a></p>';
    else echo '<p class="muted" style="margin:10px 0 0">Henüz hesap yok — yukarıdan ekleyin.</p>';
  }
  foreach($accs as $a){ $ic=$a['account_type']==='Banka'?'🏦':($a['account_type']==='Kredi Kartı'?'💳':($a['account_type']==='POS'?'🧾':'💵'));
    echo '<a class="item" href="account_view.php?id='.(int)$a['id'].'" style="display:flex;justify-content:space-between;align-items:center">'
       .'<span>'.$ic.' <b>'.htmlspecialchars($a['name']).'</b><br><small class="muted">'.htmlspecialchars($a['account_type'].($a['bank_name']?' · '.$a['bank_name']:'').(!$a['active']?' · Pasif':'')).'</small></span>'
       .'<b style="color:'.((float)$a['current_balance']<0?'#f87171':'#4ade80').'">'.mm($a['current_balance']??0).'</b></a>';
  }
}catch(Throwable $e){ echo '<div class="err">'.htmlspecialchars($e->getMessage()).'</div>'; }
?>
</div>

<div class="panel"><b>Son Hareketler</b>
<?php
try{
  $rows=$pdo->query("SELECT f.*, c.name cari, ac.name kat FROM finance_movements f LEFT JOIN contacts c ON c.id=f.contact_id LEFT JOIN accounting_categories ac ON ac.id=f.category_id ORDER BY f.id DESC LIMIT 30")->fetchAll();
  if(!$rows) echo '<p class="muted" style="margin:10px 0 0">Hareket yok.</p>';
  foreach($rows as $m){ $in=$m['direction']==='in';
    $tag=$m['cari'] ?: ($m['kat'] ?: '-');
    echo '<a class="item" href="movement_view.php?id='.(int)$m['id'].'" style="display:block"><b style="color:'.($in?'#4ade80':'#f87171').'">'.htmlspecialchars(finance_movement_type_label($m)).': '.mm($m['amount']).'</b><br><small>'.htmlspecialchars($tag.' · '.($m['payment_channel']?:'').' · '.($m['movement_date']??'')).'</small></a>';
  }
}catch(Throwable $e){ echo '<div class="err">'.htmlspecialchars($e->getMessage()).'</div>'; }
?>
</div>
<?php botx(); ?>
