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
// FINANCE CRUD UX PATCH 001 (2026-07-12): contact_view.php/finance_account_view.php gibi
// ekranlardan Düzenle'ye tıklandığında, kaydetme sonrası kullanıcı geldiği ekrana dönsün diye
// bağlam GET'ten okunup formun hidden alanlarıyla POST'a taşınır — bkz. finance_return_url().
$returnContext = $_GET['return_context'] ?? $_POST['return_context'] ?? '';
$returnRef = $_GET['return_ref'] ?? $_POST['return_ref'] ?? '';
if($editId){
    $editRow=finance_movement_get($pdo,$editId);
    if(!$editRow){ header('Location: '.finance_return_url($returnContext,$returnRef)); exit; }
    if(!in_array($editRow['movement_type'],finance_movement_editable_types(),true) || !can_edit_delete()){
        header('Location: '.finance_return_url($returnContext,$returnRef)); exit;
    }
}

$direction=$_GET['direction'] ?? ($editRow['direction'] ?? 'in');
if(!in_array($direction,['in','out'])) $direction='in';
if($editRow) $direction=$editRow['direction'];
$contactId=(int)($_GET['contact_id'] ?? ($editRow['contact_id'] ?? 0));
$warning='';

if($_SERVER['REQUEST_METHOD']==='POST'){
    try{
        $direction=$_POST['direction'];
        $amount=(float)$_POST['amount'];
        $accountId=(int)$_POST['account_id'];
        $catId=(int)($_POST['category_id']??0) ?: null;
        $personnelId=(int)($_POST['personnel_id']??0) ?: null;
        // GİDER TÜRÜ CONTEXT-AWARE (2026-07-04): "Gider Türü" artık adıma özel bir katalogdan
        // (finance_expense_type_options()) geliyor, mevcut payment_type kolonuna yazılıyor.
        $paymentType=trim($_POST['payment_type']??'') ?: null;
        if($amount<=0) throw new Exception('Tutar sıfırdan büyük olmalı.');
        if(!$accountId) throw new Exception('Hesap seçilmelidir.');

        // FINANCE UX REFACTOR (2026-07-04): "Ne kaydediyorsun?" sihirbazı sadece Ödeme (out)
        // tarafında aktif — Tahsilat (in) hiç etkilenmiyor. Sunucu tarafı doğrulama (JS'e ek,
        // savunma katmanlı) — "Diğer" her zaman bir kaçış kapısı.
        if($direction==='out'){
            $step=$_POST['record_step'] ?? 'diger';
            if($step==='cari' && !(int)($_POST['contact_id']??0)) throw new Exception('Cari Ödemesi için cari seçilmelidir.');
            if($step==='personel' && !$personnelId) throw new Exception('Personel Ödemesi için personel seçilmelidir.');
            if($step==='diger' && trim($_POST['description']??'')==='') throw new Exception('Diğer seçildiğinde açıklama zorunludur.');
            if(in_array($step,finance_expense_type_required_steps(),true) && !$paymentType){
                $stepLabels=['isletme'=>'İşletme Gideri','vergi'=>'Vergi / SGK','arac'=>'Araç Gideri'];
                throw new Exception(($stepLabels[$step]??'Bu adım').' için Gider Türü seçilmelidir.');
            }
        }

        // Migration'sız kısmi önlem (2026-07-09, Finance Core Stabilization İş 3): aynı ekonomik
        // olayın çift kaydını ENGELLEMEZ (bunun için hangi tahsilatın hangi satışı kapattığını
        // tutan bir kolon gerekir, ayrı migration önerisiyle raporlanacak) — sadece en yaygın
        // tetikleyiciye (aynı cari + aynı yön + aynı tutarda zaten bekleyen bir kayıt varken
        // habersizce ikinci bir kayıt daha girilmesi) karşı yumuşak bir uyarı gösterir. Kullanıcı
        // "Yine de kaydet" ile bilerek devam edebilir.
        if(!$editId && empty($_POST['confirm_duplicate'])){
            $dupContactId=(int)($_POST['contact_id'] ?? 0);
            if($dupContactId){
                try{
                    $dupQ=$pdo->prepare("SELECT id,movement_date FROM finance_movements
                        WHERE contact_id=? AND direction=? AND amount=? AND status='Bekliyor'
                        ORDER BY id DESC LIMIT 1");
                    $dupQ->execute([$dupContactId,$direction,$amount]);
                    $dupRow=$dupQ->fetch();
                    if($dupRow){
                        $warning='Bu caride aynı tutarda ('.number_format($amount,2,',','.').' ₺) '
                            .h($dupRow['movement_date']).' tarihli, hâlâ "Bekliyor" durumunda bir kayıt var. '
                            .'Bu işlem onun karşılığıysa aynı tutarı iki kez girmiş olabilirsiniz — cari bakiyesi '
                            .'çift sayılır. Gerçekten ayrı/yeni bir işlemse aşağıdaki kutuyu işaretleyip tekrar kaydedin.';
                    }
                }catch(Throwable $e){}
            }
        }

        if($warning){
            // Kaydetmiyoruz — formu girilen değerlerle yeniden gösteriyoruz.
        }elseif($editId){
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
            activity_log('Finans',$direction==='in'?'Tahsilat':'Ödeme',$title,$desc,'finance',$editId,base_url().'finance.php','✏️');

            header("Location: ".finance_return_url($returnContext,$returnRef));
            exit;
        }else{
        $stmt=$pdo->prepare("INSERT INTO finance_movements(contact_id,category_id,personnel_id,job_id,direction,amount,payment_channel,payment_type,account_id,status,movement_date,description,movement_type,reference_no)
            VALUES(?,?,?,NULL,?,?,?,?,?,?,?,?,'normal',?)");
        $status=$direction==='in'?'Tahsil Edildi':'Ödendi';
        $stmt->execute([
            (int)$_POST['contact_id'] ?: null,
            $catId,
            $personnelId,
            $direction,
            $amount,
            $_POST['payment_channel'],
            $paymentType,
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
        activity_log('Finans',$direction==='in'?'Tahsilat':'Ödeme',$title,$desc,'finance',$fmId,base_url().'finance.php','💰');

        header("Location: ".finance_return_url($returnContext,$returnRef));
        exit;
        }
    }catch(Throwable $e){
        $error=$e->getMessage();
    }
}

require_once __DIR__.'/layout_top.php';

$contacts=$pdo->query("SELECT id,name,type FROM contacts ORDER BY name")->fetchAll();
$accounts=$pdo->query("SELECT * FROM finance_accounts WHERE active=1 ORDER BY account_type,name")->fetchAll();
$personnelList=[];
try{ $personnelList=$pdo->query("SELECT id,name FROM personnel WHERE COALESCE(active,1)=1 ORDER BY name")->fetchAll(); }catch(Throwable $e){}
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
<?php if($warning): ?><div class="alert" style="background:#fffbeb;border-color:#fcd34d;color:#92400e"><b>⚠️ Dikkat:</b> <?=$warning?></div><?php endif; ?>

<?php
// Uyarı gösterildiğinde formu girilen değerlerle yeniden doldur (kaydedilmedi, kullanıcı
// baştan yazmasın diye) — normal düzenleme (editRow) yoksa bu kaynak kullanılmaz.
$src = $warning ? $_POST : ($editRow ?: []);
if($warning) $contactId=(int)($_POST['contact_id'] ?? 0);
$fCatId=(int)($src['category_id'] ?? 0);
$fAccId=(int)($src['account_id'] ?? ($_GET['account_id'] ?? 0));
$fChannel=$src['payment_channel'] ?? '';
$fAmount=$src['amount'] ?? '';
$fDate=$src['movement_date'] ?? date('Y-m-d');
$fRef=$src['reference_no'] ?? '';
$fDesc=$src['description'] ?? '';
$fPaymentType=$src['payment_type'] ?? '';
$channelOpts=['Nakit','Banka / EFT','Kredi Kartı','POS','Çek','Senet','Diğer'];
?>
<section class="panel">
<form method="post" class="form-grid">
<?php if($editId): ?><input type="hidden" name="id" value="<?=$editId?>"><?php endif; ?>
<?php if($returnContext): ?><input type="hidden" name="return_context" value="<?=h($returnContext)?>"><input type="hidden" name="return_ref" value="<?=h($returnRef)?>"><?php endif; ?>

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

<label id="fnField_contact_id">Cari
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

<!-- GİDER TÜRÜ CONTEXT-AWARE (2026-07-04): payment_type-bağlı yeni "Gider Türü" — seçenekleri
     fnApplyStep() ile adıma özel yeniden oluşturulur, Ödeme (out) tarafında görünür. -->
<label id="fnField_payment_type" style="display:none">Gider Türü
<select name="payment_type" id="fnTurSel" data-current="<?=h($fPaymentType)?>">
<option value="">— Seç —</option>
</select>
</label>

<label id="fnField_category_id">Kategori
<select name="category_id" id="fnCatSel">
<option value="">— Seç —</option>
<?php foreach($gelirCats as $c): ?>
<option value="<?=(int)$c['id']?>" data-type="in" <?=$fCatId===(int)$c['id']?'selected':''?>>[<?=h($c['group_name'])?>] <?=h($c['name'])?></option>
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

<?php if($warning): ?>
<label class="full" style="background:#fffbeb;border-radius:10px;padding:10px 12px">
<input type="checkbox" name="confirm_duplicate" value="1" style="width:auto;display:inline-block;margin-right:6px">
Bunun ayrı/yeni bir işlem olduğundan eminim, yine de kaydet.
</label>
<?php endif; ?>

<button class="btn"><?=$editId?'💾 Güncelle':($warning?'Yine de Kaydet':'Kaydet')?></button>
</form>
</section>
<script>
// GİDER TÜRÜ CONTEXT-AWARE (2026-07-04): adıma özel "Gider Türü" katalogu — finance_lib.php'deki
// finance_expense_type_options() ile BİREBİR aynı (web+mobil tek kaynak, PHP'den JSON'a dökülüyor).
var FN_TUR_CATALOG = <?=json_encode(finance_expense_type_options())?>;
var FN_TUR_REQUIRED = <?=json_encode(finance_expense_type_required_steps())?>;

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
function fnBuildTurOptions(step){
  var sel=document.getElementById('fnTurSel');
  var cur=sel.value || sel.dataset.current || '';
  var opts=FN_TUR_CATALOG[step] || [];
  sel.innerHTML='<option value="">— Seç —</option>';
  for(var i=0;i<opts.length;i++){
    var el=document.createElement('option');
    el.value=opts[i].v; el.textContent=opts[i].t;
    if(opts[i].v===cur) el.selected=true;
    sel.appendChild(el);
  }
  sel.dataset.current='';
}
// FINANCE UX REFACTOR (2026-07-04): sihirbaz SADECE Ödeme (out) tarafında aktif — Tahsilat/Gelir
// akışı bu ekranda hiç değişmiyor, sihirbaz seçilince gizlenir, alanlar zorunlu olmaz.
function fnToggleWizard(){
  var out=document.getElementById('fnDirection').value==='out';
  document.getElementById('fnWizardLabel').style.display = out?'':'none';
  document.getElementById('fnField_category_id').style.display = out?'none':'';
  document.getElementById('fnCatSel').required = false;
  if(!out){
    document.getElementById('fnField_personnel_id').style.display='none';
    document.getElementById('fnField_personnel_id').querySelector('select').required=false;
    document.getElementById('fnField_contact_id').style.display='';
    document.getElementById('fnField_contact_id').querySelector('select').required=false;
    document.getElementById('fnField_payment_type').style.display='none';
    document.getElementById('fnTurSel').required=false;
    document.getElementById('fnDesc').required=false;
  } else { fnApplyStep(); }
}
// Her adım sadece kendi ilgili alanını gösterir (yanlış kayıt ihtimalini azaltma amacı) — Cari
// alanı SADECE "Cari Ödemesi" adımında görünür/zorunlu, Personel SADECE "Personel Ödemesi"nde.
function fnApplyStep(){
  if(document.getElementById('fnDirection').value!=='out') return;
  var step=document.getElementById('fnStep').value;
  var contactBox=document.getElementById('fnField_contact_id');
  contactBox.style.display = (step==='cari') ? '' : 'none';
  contactBox.querySelector('select').required = (step==='cari');
  var persBox=document.getElementById('fnField_personnel_id');
  persBox.style.display = (step==='personel') ? '' : 'none';
  persBox.querySelector('select').required = (step==='personel');
  document.getElementById('fnField_payment_type').style.display='';
  fnBuildTurOptions(step);
  document.getElementById('fnTurSel').required = (FN_TUR_REQUIRED.indexOf(step)!==-1);
  document.getElementById('fnDesc').required = (step==='diger');
}
fnToggleWizard();
</script>

<?php require_once __DIR__.'/layout_bottom.php'; ?>
