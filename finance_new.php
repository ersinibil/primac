<?php
require_once __DIR__.'/boot.php';
require_login();
require_once __DIR__.'/activity_lib.php';
require_once __DIR__.'/accounting_lib.php';
require_once __DIR__.'/finance_lib.php';

$pdo=db();
$error='';
$editId=(int)($_GET['id'] ?? $_POST['id'] ?? 0);
$editRow=null;
if($editId){
    $editRow=finance_movement_get($pdo,$editId);
    if(!$editRow){ header('Location: finance.php'); exit; }
    if(!in_array($editRow['movement_type'],finance_movement_editable_types(),true) || !can_edit_delete()){
        header('Location: finance.php'); exit;
    }
}

$direction=$_GET['direction'] ?? ($editRow['direction'] ?? 'in');
if(!in_array($direction,['in','out'])) $direction='in';
if($editRow) $direction=$editRow['direction'];
$contactId=(int)($_GET['contact_id'] ?? ($editRow['contact_id'] ?? 0));

if($_SERVER['REQUEST_METHOD']==='POST'){
    try{
        $direction=$_POST['direction'];
        $amount=(float)$_POST['amount'];
        $accountId=(int)$_POST['account_id'];
        $catId=(int)($_POST['category_id']??0) ?: null;
        $personnelId=(int)($_POST['personnel_id']??0) ?: null;
        if($amount<=0) throw new Exception('Tutar sıfırdan büyük olmalı.');
        if(!$accountId) throw new Exception('Hesap seçilmelidir.');

        // FINANCE UX REFACTOR (2026-07-04): "Ne kaydediyorsun?" sihirbazı sadece Ödeme (out)
        // tarafında aktif — Tahsilat (in) hiç etkilenmiyor. Sunucu tarafı doğrulama (JS'e ek,
        // savunma katmanlı) — "Diğer" her zaman bir kaçış kapısı.
        if($direction==='out'){
            $step=$_POST['record_step'] ?? 'diger';
            if($step==='cari' && !(int)($_POST['contact_id']??0)) throw new Exception('Cari Ödemesi için cari seçilmelidir.');
            if($step==='isletme' && !$catId) throw new Exception('İşletme Gideri için Gider Türü seçilmelidir.');
            if($step==='personel' && !$personnelId) throw new Exception('Personel Ödemesi için personel seçilmelidir.');
            if($step==='vergi' && !$catId) throw new Exception('Vergi / SGK için Gider Türü seçilmelidir.');
            if($step==='arac' && !$catId) throw new Exception('Araç Gideri için Gider Türü seçilmelidir.');
            if($step==='diger' && trim($_POST['description']??'')==='') throw new Exception('Diğer seçildiğinde açıklama zorunludur.');
        }

        if($editId){
            finance_movement_update($pdo,$editId,$_POST);

            $contactName='Cari seçilmedi';
            if((int)$_POST['contact_id']){
                $cs=$pdo->prepare("SELECT name FROM contacts WHERE id=?");
                $cs->execute([(int)$_POST['contact_id']]);
                $contactName=$cs->fetch()['name'] ?? $contactName;
            }
            $as=$pdo->prepare("SELECT name FROM finance_accounts WHERE id=?");
            $as->execute([$accountId]);
            $accountName=$as->fetch()['name'] ?? $_POST['payment_channel'];
            $title = ($direction==='in'?'Tahsilat güncellendi':'Ödeme güncellendi');
            $desc = $contactName.' · '.number_format($amount,2,',','.').' ₺ · '.$accountName;
            activity_log('Finans',$direction==='in'?'Tahsilat':'Ödeme',$title,$desc,'finance',$editId,'finance.php','✏️');

            header("Location: finance.php");
            exit;
        }

        $stmt=$pdo->prepare("INSERT INTO finance_movements(contact_id,category_id,personnel_id,job_id,direction,amount,payment_channel,account_id,status,movement_date,description,movement_type,reference_no)
            VALUES(?,?,?,NULL,?,?,?,?,?,?,?,'normal',?)");
        $status=$direction==='in'?'Tahsil Edildi':'Ödendi';
        $stmt->execute([
            (int)$_POST['contact_id'] ?: null,
            $catId,
            $personnelId,
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
$personnelList=[];
try{ $personnelList=$pdo->query("SELECT id,name FROM personnel WHERE COALESCE(active,1)=1 ORDER BY name")->fetchAll(); }catch(Throwable $e){}
$giderCats=acc_categories($pdo,'gider');
$gelirCats=acc_categories($pdo,'gelir');
$stepOpts=finance_record_type_options();
// FINANCE UX REFACTOR (2026-07-04): düzenleme modunda mevcut kaydın dolu alanlarına bakarak en
// olası sihirbaz adımını türet (DB'ye yeni kolon eklemeden, notif_type_info() ile aynı desen).
$initialStep='diger';
if($editRow){
    $catGroup=null; $accType=null;
    if(!empty($editRow['category_id'])){
        try{ $cg=$pdo->prepare("SELECT group_name FROM accounting_categories WHERE id=?"); $cg->execute([$editRow['category_id']]); $catGroup=$cg->fetch()['group_name'] ?? null; }catch(Throwable $e){}
    }
    if(!empty($editRow['account_id'])){
        try{ $ag=$pdo->prepare("SELECT account_type FROM finance_accounts WHERE id=?"); $ag->execute([$editRow['account_id']]); $accType=$ag->fetch()['account_type'] ?? null; }catch(Throwable $e){}
    }
    $initialStep=finance_record_type_info($editRow,$catGroup,$accType);
}
?>

<div class="panel-head">
<h1><?=$editId?'Hareketi Düzenle':(($direction==='in'?'Tahsilat':'Ödeme').' Kaydı')?></h1>
<div class="actions">
<a class="btn secondary" href="finance.php">Finans</a>
<a class="btn secondary" href="finance_accounts.php">Hesaplar</a>
</div>
</div>

<?php if($error): ?><div class="alert"><?=h($error)?></div><?php endif; ?>

<?php
$fCatId=(int)($editRow['category_id'] ?? 0);
$fAccId=(int)($editRow['account_id'] ?? ($_GET['account_id'] ?? 0));
$fChannel=$editRow['payment_channel'] ?? '';
$fAmount=$editRow['amount'] ?? '';
$fDate=$editRow['movement_date'] ?? date('Y-m-d');
$fRef=$editRow['reference_no'] ?? '';
$fDesc=$editRow['description'] ?? '';
$channelOpts=['Nakit','Banka / EFT','Kredi Kartı','POS','Çek','Senet','Diğer'];
?>
<section class="panel">
<form method="post" class="form-grid">
<?php if($editId): ?><input type="hidden" name="id" value="<?=$editId?>"><?php endif; ?>

<label>İşlem Tipi
<select name="direction" id="fnDirection" onchange="fnFilterCats();fnToggleWizard()">
<option value="in" <?=$direction==='in'?'selected':''?>>Tahsilat</option>
<option value="out" <?=$direction==='out'?'selected':''?>>Ödeme</option>
</select>
</label>

<label id="fnWizardLabel" class="full" style="<?=$direction==='out'?'':'display:none'?>">Ne kaydediyorsun?
<select name="record_step" id="fnStep" onchange="fnApplyStep()">
<?php foreach($stepOpts as $key=>$o): ?><option value="<?=$key?>" <?=$initialStep===$key?'selected':''?>><?=$o['icon']?> <?=h($o['label'])?></option><?php endforeach; ?>
</select>
</label>

<label id="fnField_contact_id">Cari <small style="font-weight:400;color:#667085">(opsiyonel)</small>
<select name="contact_id">
<option value="">Cari seçilmedi</option>
<?php foreach($contacts as $c): ?>
<option value="<?=$c['id']?>" <?=$contactId===$c['id']?'selected':''?>><?=h($c['name'].' / '.$c['type'])?></option>
<?php endforeach; ?>
</select>
</label>

<label id="fnField_personnel_id" style="display:none">Personel
<select name="personnel_id">
<option value="">Personel seçilmedi</option>
<?php foreach($personnelList as $p): ?>
<option value="<?=(int)$p['id']?>" <?=(int)($editRow['personnel_id']??0)===(int)$p['id']?'selected':''?>><?=h($p['name'])?></option>
<?php endforeach; ?>
</select>
</label>

<label><?=$direction==='out'?'Gider Türü':'Kategori'?> <small style="font-weight:400;color:#667085">(cari yerine/yanında — personel yol gideri, yakıt, vergi, telefon vb.)</small>
<select name="category_id" id="fnCatSel">
<option value="">— Seç —</option>
<?php foreach($giderCats as $c): ?>
<option value="<?=(int)$c['id']?>" data-type="out" style="<?=$direction==='out'?'':'display:none'?>" <?=$fCatId===(int)$c['id']?'selected':''?>>[<?=h($c['group_name'])?>] <?=h($c['name'])?></option>
<?php endforeach; ?>
<?php foreach($gelirCats as $c): ?>
<option value="<?=(int)$c['id']?>" data-type="in" style="<?=$direction==='in'?'':'display:none'?>" <?=$fCatId===(int)$c['id']?'selected':''?>>[<?=h($c['group_name'])?>] <?=h($c['name'])?></option>
<?php endforeach; ?>
</select>
</label>

<label>Hesap / Banka / Kasa / Kart
<select name="account_id" required>
<option value="">Seçiniz</option>
<?php foreach($accounts as $a): ?>
<option value="<?=$a['id']?>" <?=$fAccId===(int)$a['id']?'selected':''?>><?=h($a['account_type'].' - '.$a['name'].' / '.money($a['current_balance']))?></option>
<?php endforeach; ?>
</select>
</label>

<label>Yöntem
<select name="payment_channel">
<?php foreach($channelOpts as $co): ?>
<option <?=$fChannel===$co?'selected':''?>><?=h($co)?></option>
<?php endforeach; ?>
</select>
</label>

<label>Tutar
<input type="number" step="0.01" name="amount" value="<?=h($fAmount)?>" required>
</label>

<label>Tarih
<input type="date" name="movement_date" value="<?=h($fDate)?>">
</label>

<label>Referans No
<input name="reference_no" placeholder="Dekont, fiş, işlem no" value="<?=h($fRef)?>">
</label>

<label class="full">Açıklama
<textarea name="description" id="fnDesc" rows="3"><?=h($fDesc)?></textarea>
</label>

<button class="btn"><?=$editId?'💾 Güncelle':'Kaydet'?></button>
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
// FINANCE UX REFACTOR (2026-07-04): sihirbaz SADECE Ödeme (out) tarafında aktif — Tahsilat/Gelir
// akışı bu ekranda hiç değişmiyor, sihirbaz seçilince gizlenir, alanlar zorunlu olmaz.
function fnToggleWizard(){
  var out=document.getElementById('fnDirection').value==='out';
  document.getElementById('fnWizardLabel').style.display = out?'':'none';
  if(!out){
    document.getElementById('fnField_personnel_id').style.display='none';
    document.getElementById('fnField_personnel_id').querySelector('select').required=false;
    document.getElementById('fnField_contact_id').style.display='';
    document.getElementById('fnDesc').required=false;
  } else { fnApplyStep(); }
}
function fnApplyStep(){
  if(document.getElementById('fnDirection').value!=='out') return;
  var step=document.getElementById('fnStep').value;
  var need={cari:'contact_id',isletme:'category_id',personel:'personnel_id',vergi:'category_id',arac:'category_id',kart:null,diger:null}[step];
  var contactBox=document.getElementById('fnField_contact_id');
  contactBox.style.display = (step==='personel') ? 'none' : '';
  contactBox.querySelector('select').required = (need==='contact_id');
  document.getElementById('fnCatSel').required = (need==='category_id');
  var persBox=document.getElementById('fnField_personnel_id');
  persBox.style.display = (step==='personel') ? '' : 'none';
  persBox.querySelector('select').required = (need==='personnel_id');
  document.getElementById('fnDesc').required = (step==='diger');
}
fnToggleWizard();
</script>

<?php require_once __DIR__.'/layout_bottom.php'; ?>
