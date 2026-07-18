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
if(isset($_GET['ok'])) echo ds_alert('success','Hesap eklendi.');
if(isset($_GET['deleted'])) echo ds_alert('success','Hesap silindi.');
if(!empty($_SESSION['kasa_err'])){ echo ds_alert('danger',$_SESSION['kasa_err']); unset($_SESSION['kasa_err']); }

function acc_sum($pdo,$type){ try{ $s=$pdo->prepare("SELECT COALESCE(SUM(current_balance),0) s FROM finance_accounts WHERE active=1 AND account_type=?"); $s->execute([$type]); return (float)$s->fetch()['s']; }catch(Throwable $e){ return 0; } }
$kasa=acc_sum($pdo,'Kasa'); $banka=acc_sum($pdo,'Banka'); $kart=acc_sum($pdo,'Kredi Kartı'); $pos=acc_sum($pdo,'POS');
// FİNANS ÇEKİRDEK DÜZELTMESİ (2026-07-10): sadece GERÇEK kasa/banka hareketleri sayılır —
// satış/alış (Bekliyor) hiçbir hesabı etkilemediği için account_id NULL kalır, bu yüzden
// "Bugün Tahsilat/Ödeme" bunları saymaz (aksi halde bekleyen bir satış "tahsil edilmiş" gibi görünürdü).
$inToday=safe_sum("SELECT COALESCE(SUM(amount),0) s FROM finance_movements WHERE direction='in' AND account_id IS NOT NULL AND movement_date=CURDATE()");
$outToday=safe_sum("SELECT COALESCE(SUM(amount),0) s FROM finance_movements WHERE direction='out' AND COALESCE(movement_type,'')<>'transfer' AND account_id IS NOT NULL AND movement_date=CURDATE()");
?>
<div class="df-stat-row">
  <div class="df-stat"><span>💵 Kasa</span><strong><?=mm($kasa)?></strong></div>
  <div class="df-stat"><span>🏦 Banka</span><strong><?=mm($banka)?></strong></div>
  <div class="df-stat"><span>💳 Kredi Kartı</span><strong><?=mm($kart)?></strong></div>
  <div class="df-stat"><span>🧾 POS</span><strong><?=mm($pos)?></strong></div>
</div>
<style>
.df-stat-row{display:grid;grid-template-columns:1fr 1fr;gap:10px}
.df-stat{background:var(--df-surface-sunken,rgba(255,255,255,.06));border-radius:var(--df-radius-md,14px);padding:12px;display:flex;flex-direction:column;gap:4px}
.df-stat span{font-size:12px;color:var(--df-ink-500,#94a3b8)}
.df-stat strong{font-size:18px;font-weight:900}
</style>
<div class="df-panel" style="display:flex;justify-content:space-between;align-items:center;margin-top:12px">
  <div><small class="muted">Bugün Tahsilat</small><div style="font-size:20px;font-weight:900;color:var(--df-success-ink)"><?=mm($inToday)?></div></div>
  <div style="text-align:right"><small class="muted">Bugün Ödeme</small><div style="font-size:20px;font-weight:900;color:var(--df-danger-ink)"><?=mm($outToday)?></div></div>
</div>

<div class="df-panel" style="display:flex;gap:8px;margin-top:12px"><a class="df-btn df-btn--primary" href="report.php?modul=tahsilat" style="flex:1;justify-content:center"><?=ds_icon('info',14)?> Finans Raporu</a></div>

<details class="df-panel" style="margin-top:12px"><summary style="font-weight:900;cursor:pointer"><?=ds_icon('plus',14)?> Yeni Hesap Ekle (Banka / Kasa / Kart / POS)</summary>
<form method="post" style="margin-top:10px">
  <select name="account_type"><option>Kasa</option><option>Banka</option><option>Kredi Kartı</option><option>POS</option></select>
  <input name="name" placeholder="Hesap adı (örn. Ziraat Vadesiz / Ana Kasa)" required>
  <input name="bank_name" placeholder="Banka adı (kredi kartı/banka için)">
  <input name="opening_balance" type="number" step="0.01" placeholder="Açılış bakiyesi" value="0">
  <button class="df-btn df-btn--primary df-btn--lg" name="add_account" value="1" style="width:100%"><?=ds_icon('check',16)?> Hesap Ekle</button>
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
<div class="df-tabs" style="overflow:auto;max-width:100%;-webkit-overflow-scrolling:touch;margin:14px 0 8px">
<?php
$mTabs=['' => '💰 Tümü ('.$mTypeCounts['all'].')', 'Kasa'=>'💵 Kasalar ('.$mTypeCounts['Kasa'].')', 'Banka'=>'🏦 Banka Hesapları ('.$mTypeCounts['Banka'].')', 'Kredi Kartı'=>'💳 Kredi Kartları ('.$mTypeCounts['Kredi Kartı'].')', 'Diger'=>'➕ Diğer ('.$mTypeCounts['Diger'].')'];
foreach($mTabs as $tv=>$label):
?>
  <a class="df-tab<?=$mtype===$tv?' df-tab--active':''?>" href="<?=h(kasa_tab_url($tv,$mstatus,$mbank,$mq))?>"><?=h($label)?></a>
<?php endforeach; ?>
</div>

<details class="df-panel"<?=$mHasFilter?' open':''?>><summary style="font-weight:900;cursor:pointer"><?=ds_icon('search',14)?> Filtrele<?=$mHasFilter?' (aktif)':''?></summary>
<form method="get" style="margin-top:10px">
  <?php if($mtype!==''): ?><input type="hidden" name="type" value="<?=h($mtype)?>"><?php endif; ?>
  <select name="status">
    <option value="active" <?=$mstatus==='active'?'selected':''?>>Aktif</option>
    <option value="passive" <?=$mstatus==='passive'?'selected':''?>>Pasif</option>
    <option value="" <?=$mstatus===''?'selected':''?>>Tümü</option>
  </select>
  <select name="bank">
    <option value="">Tüm Bankalar</option>
    <?php foreach($mBankOptions as $b): ?><option value="<?=h($b)?>" <?=$mbank===$b?'selected':''?>><?=h($b)?></option><?php endforeach; ?>
  </select>
  <input type="text" name="q" value="<?=h($mq)?>" placeholder="Hesap, banka, IBAN veya kart ara...">
  <button class="df-btn df-btn--primary df-btn--lg" type="submit" style="width:100%">Uygula</button>
  <?php if($mHasFilter): ?><a href="kasa.php" class="df-btn df-btn--secondary" style="width:100%;margin-top:8px;justify-content:center">✕ Filtreyi Temizle</a><?php endif; ?>
</form>
</details>

<div class="df-panel" style="margin-top:12px"><b><?=ds_icon('wallet',16)?> Hesaplar</b>
<?php
try{
  list($mWhere,$mParams)=finance_account_filter_where($mtype,$mstatus,$mbank,$mq);
  $accs=$pdo->prepare("SELECT * FROM finance_accounts $mWhere ORDER BY account_type,name");
  $accs->execute($mParams);
  $accs=$accs->fetchAll();
  if(!$accs){
    if($mHasFilter) ds_empty_state('Seçili filtrelere uygun hesap bulunamadı.','Filtreleri temizlemek için üstteki "Filtreyi Temizle" bağlantısını kullanın.');
    else ds_empty_state('Henüz hesap yok — yukarıdan ekleyin.');
  }
  foreach($accs as $a){ $ic=$a['account_type']==='Banka'?'🏦':($a['account_type']==='Kredi Kartı'?'💳':($a['account_type']==='POS'?'🧾':'💵'));
    $titleHtml=$ic.' <b>'.h($a['name']).'</b>';
    $descHtml=h($a['account_type'].($a['bank_name']?' · '.$a['bank_name']:'').(!$a['active']?' · Pasif':''));
    $metaHtml='<b style="color:'.((float)$a['current_balance']<0?'var(--df-danger-ink)':'var(--df-success-ink)').'">'.mm($a['current_balance']??0).'</b>';
    ds_list_item($titleHtml,'account_view.php?id='.(int)$a['id'],$descHtml,$metaHtml);
  }
}catch(Throwable $e){ echo ds_alert('danger',$e->getMessage()); }
?>
</div>

<div class="df-panel" style="margin-top:12px"><b><?=ds_icon('info',16)?> Son Hareketler</b>
<?php
try{
  $rows=$pdo->query("SELECT f.*, c.name cari, ac.name kat FROM finance_movements f LEFT JOIN contacts c ON c.id=f.contact_id LEFT JOIN accounting_categories ac ON ac.id=f.category_id ORDER BY f.id DESC LIMIT 30")->fetchAll();
  if(!$rows) ds_empty_state('Hareket yok.');
  foreach($rows as $m){ $in=$m['direction']==='in';
    $tag=$m['cari'] ?: ($m['kat'] ?: '-');
    $titleHtml='<b style="color:'.($in?'var(--df-success-ink)':'var(--df-danger-ink)').'">'.h(finance_movement_type_label($m)).': '.mm($m['amount']).'</b>';
    $descHtml=h($tag.' · '.($m['payment_channel']?:'').' · '.($m['movement_date']??''));
    ds_list_item($titleHtml,'movement_view.php?id='.(int)$m['id'],$descHtml);
  }
}catch(Throwable $e){ echo ds_alert('danger',$e->getMessage()); }
?>
</div>
<?php botx(); ?>
