<?php
require_once __DIR__.'/boot.php';
require_login();
require_once __DIR__.'/activity_lib.php';
require_once __DIR__.'/accounting_lib.php';
require_once __DIR__.'/finance_lib.php';
require_once __DIR__.'/checks_notes_lib.php';

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
        // P0 FİNANS UX + ÇEK/SENET ENTEGRASYONU (2026-07-18, Product Owner kararı) — Yöntem=Çek/
        // Senet seçildiğinde bu ekran artık kasa/banka hareketi OLUŞTURMAZ (çek alındığı/verildiği
        // anda fiziken kasada/bankada değildir) — checks_notes_lib.php'nin TEK kaynağına yönlendirir.
        $paymentChannel = $_POST['payment_channel'] ?? '';
        $isCekSenet = in_array($paymentChannel, ['Çek','Senet'], true);
        if($amount<=0) throw new Exception('Tutar sıfırdan büyük olmalı.');
        if(!$accountId && !$isCekSenet) throw new Exception('Hesap seçilmelidir.');

        if(!$editId && $isCekSenet){
            $cnType = $paymentChannel==='Senet' ? 'senet' : 'cek';
            $cnContactId = (int)($_POST['contact_id'] ?? 0);
            if(!$cnContactId) throw new Exception('Cari seçilmelidir.');
            $cnDate = $_POST['movement_date'] ?: date('Y-m-d');
            $cnDesc = trim($_POST['description'] ?? '');

            if($direction==='out' && ($_POST['cn_mode'] ?? 'own')==='endorse'){
                // B) Portföydeki (alınan) bir müşteri çekini/senedini hedef tedarikçiye ciro et —
                // mevcut checks_notes_endorse() TEK akışı: kasa/banka hareketi yok, ciro edilen
                // tarafın borcu Ödeme mantığıyla kapanır, kaynak müşteri İKİNCİ KEZ etkilenmez.
                $cnId=(int)($_POST['cn_endorse_id'] ?? 0);
                if(!$cnId) throw new Exception('Ciro edilecek çek/senet seçilmelidir.');
                checks_notes_endorse($pdo, $_SESSION['user']['id'] ?? 0, $cnId, $cnContactId, $cnDate, $cnDesc);
                $cnTitle='Çek/senet ciro edildi';
            }else{
                // Tahsilat+Çek/Senet (HER ZAMAN "alınan") veya Ödeme+Çek/Senet A) kendi çekimizi/
                // senedimizi ver ("verilen") — checks_notes_create() TEK oluşturma akışı: kayıt
                // 'portfoyde' statüsünde açılır, cari checks_notes_sync_finance() ile TEK SEFER
                // etkilenir (contact_balance_case_sql() P0 düzeltmesiyle Tahsilat/Ödeme ile AYNI
                // yönde), kasa/banka hareketi OLUŞMAZ.
                $cnDirection = $direction==='in' ? 'alinan' : 'verilen';
                checks_notes_create($pdo, [
                    'type'=>$cnType, 'direction'=>$cnDirection, 'amount'=>$amount,
                    'due_date'=>trim($_POST['cn_due_date'] ?? ''), 'contact_id'=>$cnContactId,
                    'bank_name'=>trim($_POST['cn_bank_name'] ?? ''), 'number'=>trim($_POST['cn_number'] ?? ''),
                    'notes'=>$cnDesc,
                ], $_SESSION['user']['id'] ?? 0);
                $cnTitle = $direction==='in' ? 'Çek/senet alındı (portföyde)' : 'Çek/senet verildi (portföyde)';
            }

            try{
                $cs=$pdo->prepare("SELECT name FROM contacts WHERE id=?"); $cs->execute([$cnContactId]);
                $contactName=$cs->fetch()['name'] ?? '';
                activity_log('Finans',$direction==='in'?'Tahsilat':'Ödeme',$cnTitle,$contactName.' · '.number_format($amount,2,',','.').' ₺','finance',0,base_url().'checks_notes.php','🧾');
            }catch(Throwable $e){}

            header("Location: ".finance_return_url($returnContext,$returnRef));
            exit;
        }

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

// FİNANS UX REGRESYON DÜZELTMESİ (2026-07-19) — TÜM contacts tablosunu DOM'a döken tam liste
// sorgusu kaldırıldı; sadece formda ÖNCEDEN seçili olan (edit modu / uyarı sonrası yeniden
// doldurma / cari bağlamıyla gelme) tek carinin adı, aranabilir seçicinin başlangıç değeri için
// çekiliyor — arama artık contact_search_ajax.php üzerinden AJAX ile yapılıyor.
$__contactInitial = null;
if($contactId){
    try{
        $__ciq=$pdo->prepare("SELECT id,name,type FROM contacts WHERE id=?");
        $__ciq->execute([$contactId]);
        $__contactInitial = $__ciq->fetch() ?: null;
    }catch(Throwable $e){}
}
$accounts=$pdo->query("SELECT * FROM finance_accounts WHERE active=1 ORDER BY account_type,name")->fetchAll();
// P0 FİNANS UX + ÇEK/SENET ENTEGRASYONU (2026-07-18) — Ödeme+Çek/Senet'in B) seçeneği (portföydeki
// müşteri çekini ciro et) için aday liste: sadece PORTFÖYDE + ALINAN kayıtlar cirolanabilir
// (checks_notes_endorse() zaten aynı kuralı sunucu tarafında da uyguluyor — burası sadece UI listesi).
$__cekPortfoy = checks_notes_lifecycle_ready() ? checks_notes_list($pdo, null, 'portfoyde', 'alinan') : [];
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

<?php
$__finActions = ds_button('Finans','finance.php','secondary','','',true) . ds_button('Hesaplar','finance_accounts.php','secondary','','',true);
ds_page_header($editId?'Hareketi Düzenle':(($direction==='in'?'Tahsilat':'Ödeme').' Kaydı'), ds_icon('wallet',24), '', $__finActions, false, true);
?>

<?php if($error): ?><?=ds_alert('danger',$error)?><?php endif; ?>
<?php if($warning): ?><div class="df-alert df-alert--warning">⚠️ Dikkat: <?=$warning?></div><?php endif; ?>

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
<section class="df-card">
<form method="post" class="df-form-grid-2" enctype="multipart/form-data">
<?php if($editId): ?><input type="hidden" name="id" value="<?=$editId?>"><?php endif; ?>
<?php if($returnContext): ?><input type="hidden" name="return_context" value="<?=h($returnContext)?>"><input type="hidden" name="return_ref" value="<?=h($returnRef)?>"><?php endif; ?>

<?php ds_form_field('İşlem Tipi', '<select name="direction" id="fnDirection" onchange="fnFilterCats();fnToggleWizard()"><option value="in" '.($direction==='in'?'selected':'').'>Tahsilat</option><option value="out" '.($direction==='out'?'selected':'').'>Ödeme</option></select>'); ?>

<div id="fnWizardLabel" class="df-form-span-2" style="<?=$direction==='out'?'':'display:none'?>">
<?php
$__stepOpts='';
foreach($stepOpts as $key=>$o){ $__stepOpts.='<option value="'.$key.'" '.($initialStep===$key?'selected':'').'>'.$o['icon'].' '.h($o['label']).'</option>'; }
ds_form_field('Ne kaydediyorsun?', '<select name="record_step" id="fnStep" onchange="fnApplyStep()">'.$__stepOpts.'</select>');
?>
</div>

<div id="fnField_contact_id">
<?php
// P0 FİNANS UX (2026-07-18, Product Owner kararı 1. madde): Tahsilat'ta varsayılan sadece
// Müşteri, Ödeme'de varsayılan sadece Tedarikçi. FİNANS UX REGRESYON DÜZELTMESİ (2026-07-19):
// cari modeli/opening_balance/contact_balance_case_sql() DEĞİŞMEDİ, sadece TÜM cariyi DOM'a döken
// <select> yerine contact_search_ajax.php'ye arayan aranabilir seçici (dfInitContactPicker()).
ds_form_field('Cari', '
<div class="df-contact-picker">
  <input type="text" id="fnContactQuery" autocomplete="off" placeholder="İsim veya telefon ile ara…" value="'.h($__contactInitial['name'] ?? '').'">
  <input type="hidden" name="contact_id" id="fnContactSel" value="'.($contactId ?: '').'" data-selected-name="'.h($__contactInitial['name'] ?? '').'">
  <div class="df-contact-picker-results" id="fnContactResults" hidden></div>
</div>');
?>
<div class="df-tabs" id="fnContactScope" style="margin:-4px 0 10px">
  <button type="button" class="df-tab df-tab--active" data-scope="filtered" onclick="fnSetContactScope(this,'filtered')"><span id="fnScopeFilteredLabel">Müşteriler</span></button>
  <button type="button" class="df-tab" data-scope="all" onclick="fnSetContactScope(this,'all')">Tüm Cariler</button>
</div>
</div>

<div id="fnField_personnel_id" style="display:none">
<?php
$__persOpts='<option value="">Personel seçilmedi</option>';
foreach($personnelList as $p){ $__persOpts.='<option value="'.(int)$p['id'].'" '.((int)($editRow['personnel_id']??0)===(int)$p['id']?'selected':'').'>'.h($p['name']).'</option>'; }
ds_form_field('Personel', '<select name="personnel_id">'.$__persOpts.'</select>');
?>
</div>

<!-- GİDER TÜRÜ CONTEXT-AWARE (2026-07-04): payment_type-bağlı yeni "Gider Türü" — seçenekleri
     fnApplyStep() ile adıma özel yeniden oluşturulur, Ödeme (out) tarafında görünür. -->
<div id="fnField_payment_type" style="display:none">
<?php ds_form_field('Gider Türü', '<select name="payment_type" id="fnTurSel" data-current="'.h($fPaymentType).'"><option value="">— Seç —</option></select>'); ?>
</div>

<div id="fnField_category_id">
<?php
$__catOpts='<option value="">— Seç —</option>';
foreach($gelirCats as $c){ $__catOpts.='<option value="'.(int)$c['id'].'" data-type="in" '.($fCatId===(int)$c['id']?'selected':'').'>['.h($c['group_name']).'] '.h($c['name']).'</option>'; }
ds_form_field('Kategori', '<select name="category_id" id="fnCatSel">'.$__catOpts.'</select>');
?>
</div>

<div id="fnField_account_id">
<?php
$__accOpts='<option value="">Seçiniz</option>';
foreach($accounts as $a){ $__accOpts.='<option value="'.$a['id'].'" '.($fAccId===(int)$a['id']?'selected':'').'>'.h($a['account_type'].' - '.$a['name'].' / '.money($a['current_balance'])).'</option>'; }
ds_form_field('Hesap / Banka / Kasa / Kart', '<select name="account_id" id="fnAccSel" required>'.$__accOpts.'</select>');
?>
</div>

<?php
$__chOpts='';
foreach($channelOpts as $co){ $__chOpts.='<option '.($fChannel===$co?'selected':'').'>'.h($co).'</option>'; }
ds_form_field('Yöntem', '<select name="payment_channel" id="fnChannel" onchange="fnToggleCek()">'.$__chOpts.'</select>');
?>

<?php ds_form_field('Tutar', '<input type="number" step="0.01" name="amount" value="'.h($fAmount).'" required>'); ?>
<?php ds_form_field('Tarih', '<input type="date" name="movement_date" value="'.h($fDate).'">'); ?>
<div id="fnField_reference_no"><?php ds_form_field('Referans No', '<input name="reference_no" placeholder="Dekont, fiş, işlem no" value="'.h($fRef).'">'); ?></div>
<div class="df-form-span-2"><?php ds_form_field('Açıklama', '<textarea name="description" id="fnDesc" rows="3">'.h($fDesc).'</textarea>'); ?></div>

<!-- P0 FİNANS UX + ÇEK/SENET ENTEGRASYONU (2026-07-18, Product Owner kararı 2-4. madde) — Yöntem
     Çek/Senet olunca Hesap alanı GİZLENİR (kasa/banka hareketi oluşmaz), bunun yerine bu blok
     görünür. fnToggleCek() ile açılır/kapanır — kopya mantık YOK, kayıt checks_notes_lib.php'nin
     TEK kaynağına (checks_notes_create()/checks_notes_endorse()) gider. -->
<div id="fnCekBlock" class="df-form-span-2" style="display:none">
  <div class="df-form-span-2" id="fnCekModeWrap" style="display:none;margin-bottom:var(--df-space-3)">
    <div class="df-tabs" id="fnCekMode">
      <button type="button" class="df-tab df-tab--active" data-mode="own" onclick="fnSetCekMode(this,'own')">Kendi Çekimizi/Senedimizi Ver</button>
      <button type="button" class="df-tab" data-mode="endorse" onclick="fnSetCekMode(this,'endorse')">Portföydeki Çeki Ciro Et</button>
    </div>
  </div>
  <input type="hidden" name="cn_mode" id="fnCnMode" value="own">

  <div class="df-form-grid-2" id="fnCekOwnFields">
    <?php ds_form_field('Numara', '<input name="cn_number" placeholder="Çek/senet no">'); ?>
    <?php ds_form_field('Vade Tarihi', '<input type="date" name="cn_due_date">'); ?>
    <div id="fnCekBankWrap"><?php ds_form_field('Banka Adı', '<input name="cn_bank_name" placeholder="Sadece çek için">'); ?></div>
    <?php ds_form_field('Dosya / Fotoğraf', '<input type="file" name="attachment" accept="image/*,application/pdf">'); ?>
  </div>

  <div id="fnCekEndorseFields" style="display:none">
    <?php
    $__cekOpts='<option value="">— Seç —</option>';
    foreach($__cekPortfoy as $__cn){
        $__cekOpts.='<option value="'.(int)$__cn['id'].'">'.h(($__cn['type']==='senet'?'Senet':'Çek').' · '.($__cn['number']?:'no yok').' · '.($__cn['contact_name']?:'—').' · '.number_format((float)$__cn['amount'],2,',','.').' ₺'.($__cn['due_date']?' · vade '.$__cn['due_date']:'')).'</option>';
    }
    ds_form_field('Ciro Edilecek Çek/Senet (Portföyde)', '<select name="cn_endorse_id">'.$__cekOpts.'</select>');
    if(!$__cekPortfoy) echo '<p class="df-muted" style="margin-top:4px;font-size:12px">Portföyde ciro edilebilecek alınan çek/senet yok.</p>';
    ?>
  </div>
</div>

<?php if($warning): ?>
<div class="df-form-span-2" style="background:var(--df-warning-soft);border-radius:var(--df-radius-md);padding:10px 12px">
<label style="display:flex;align-items:center;gap:8px;margin:0;color:var(--df-warning-ink)">
<input type="checkbox" name="confirm_duplicate" value="1" style="width:auto">
Bunun ayrı/yeni bir işlem olduğundan eminim, yine de kaydet.
</label>
</div>
<?php endif; ?>

<div class="df-form-span-2"><button class="df-btn df-btn--primary"><?=$editId?'💾 Güncelle':($warning?'Yine de Kaydet':'Kaydet')?></button></div>
</form>
</section>

<style>
body.nav-compact .df-form-grid-2{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:0 var(--df-space-4)}
body.nav-compact .df-form-span-2{grid-column:1 / -1}
@media(max-width:640px){body.nav-compact .df-form-grid-2{grid-template-columns:1fr}}
</style>
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
    document.getElementById('fnContactQuery').required=false;
    document.getElementById('fnField_payment_type').style.display='none';
    document.getElementById('fnTurSel').required=false;
    document.getElementById('fnDesc').required=false;
  } else { fnApplyStep(); }
  // P0 FİNANS UX + ÇEK/SENET ENTEGRASYONU (2026-07-18): cari-kapsam etiketi (Müşteriler/
  // Tedarikçiler) ve Çek/Senet modu yön değişince YENİDEN uygulanır — fnToggleCek() bu ikisinin
  // "son sözü" (çek/senet seçiliyken wizard/kategori görünürlüğünü ezer).
  fnApplyContactScope();
  fnToggleCek();
}
// Her adım sadece kendi ilgili alanını gösterir (yanlış kayıt ihtimalini azaltma amacı) — Cari
// alanı SADECE "Cari Ödemesi" adımında görünür/zorunlu, Personel SADECE "Personel Ödemesi"nde.
function fnApplyStep(){
  if(document.getElementById('fnDirection').value!=='out') return;
  var step=document.getElementById('fnStep').value;
  var contactBox=document.getElementById('fnField_contact_id');
  contactBox.style.display = (step==='cari') ? '' : 'none';
  document.getElementById('fnContactQuery').required = (step==='cari');
  var persBox=document.getElementById('fnField_personnel_id');
  persBox.style.display = (step==='personel') ? '' : 'none';
  persBox.querySelector('select').required = (step==='personel');
  document.getElementById('fnField_payment_type').style.display='';
  fnBuildTurOptions(step);
  document.getElementById('fnTurSel').required = (FN_TUR_REQUIRED.indexOf(step)!==-1);
  document.getElementById('fnDesc').required = (step==='diger');
}

// P0 FİNANS UX (2026-07-18, Product Owner kararı 1. madde) — cari modeli DEĞİŞMEDİ, sadece arama
// kapsamı işlem niyetine göre (Tahsilat→Müşteri, Ödeme→Tedarikçi) filtrelenir. "Tüm Cariler"
// istisnası her zaman bir tık uzakta. FİNANS UX REGRESYON DÜZELTMESİ (2026-07-19): filtre artık
// istemcide <option> gizleme değil, contact_search_ajax.php'ye giden scope parametresi.
var FN_CONTACT_SCOPE = 'filtered';
function fnSetContactScope(btn, scope){
  document.querySelectorAll('#fnContactScope .df-tab').forEach(function(b){ b.classList.toggle('df-tab--active', b.dataset.scope===scope); });
  FN_CONTACT_SCOPE = scope;
  fnApplyContactScope();
}
function fnContactAjaxScope(){
  if(FN_CONTACT_SCOPE==='all') return 'all';
  return document.getElementById('fnDirection').value==='out' ? 'suppliers' : 'customers';
}
function fnApplyContactScope(){
  var out=document.getElementById('fnDirection').value==='out';
  document.getElementById('fnScopeFilteredLabel').textContent = out ? 'Tedarikçiler' : 'Müşteriler';
  if(window.fnContactPicker) window.fnContactPicker.refresh();
}

// P0 FİNANS UX + ÇEK/SENET ENTEGRASYONU (2026-07-18, Product Owner kararı 2-4. madde) — Yöntem
// Çek/Senet olunca: Hesap alanı GİZLENİR+zorunlu olmaktan çıkar (kasa/banka hareketi oluşmaz),
// cari HER ZAMAN görünür/zorunlu olur (checks_notes_create()/endorse() her zaman contact_id ister,
// wizard adımından bağımsız), Ödeme tarafında "Kendi Çekimizi Ver / Portföydeki Çeki Ciro Et"
// alt-seçimi belirir.
function fnToggleCek(){
  var channel=document.getElementById('fnChannel').value;
  var isCek=(channel==='Çek'||channel==='Senet');
  var out=document.getElementById('fnDirection').value==='out';

  document.getElementById('fnCekBlock').style.display = isCek?'':'none';
  document.getElementById('fnField_account_id').style.display = isCek?'none':'';
  document.getElementById('fnAccSel').required = !isCek;
  document.getElementById('fnField_reference_no').style.display = isCek?'none':'';
  document.getElementById('fnCekModeWrap').style.display = (isCek&&out)?'':'none';
  document.getElementById('fnCekBankWrap').style.display = (channel==='Çek')?'':'none';

  if(!isCek || !out) fnSetCekMode(null,'own');

  if(isCek){
    var contactBox=document.getElementById('fnField_contact_id');
    contactBox.style.display='';
    document.getElementById('fnContactQuery').required=true;
    if(out){
      document.getElementById('fnWizardLabel').style.display='none';
      document.getElementById('fnField_personnel_id').style.display='none';
      document.getElementById('fnField_personnel_id').querySelector('select').required=false;
      document.getElementById('fnField_payment_type').style.display='none';
      document.getElementById('fnTurSel').required=false;
      document.getElementById('fnDesc').required=false;
    }
  } else if(out){
    document.getElementById('fnWizardLabel').style.display='';
    fnApplyStep();
  }
}
function fnSetCekMode(btn, mode){
  document.getElementById('fnCnMode').value=mode;
  document.getElementById('fnCekOwnFields').style.display=(mode==='own')?'':'none';
  document.getElementById('fnCekEndorseFields').style.display=(mode==='endorse')?'':'none';
  document.querySelectorAll('#fnCekMode .df-tab').forEach(function(b){ b.classList.toggle('df-tab--active', b.dataset.mode===mode); });
}

window.fnContactPicker = dfInitContactPicker({
  inputId:'fnContactQuery', hiddenId:'fnContactSel', resultsId:'fnContactResults',
  endpoint:'contact_search_ajax.php', getScope: fnContactAjaxScope
});
fnToggleWizard();
</script>

<?php require_once __DIR__.'/layout_bottom.php'; ?>
