<?php
require_once 'common.php';
require_once __DIR__.'/../accounting_lib.php';
require_once __DIR__.'/../finance_lib.php';
require_once __DIR__.'/../checks_notes_lib.php';
$pdo=db();
$cid=(int)($_GET['contact_id'] ?? 0);

// payment_channel → account_type eşlemesi (collection.php ile aynı mantık)
function pay_acc_for_pm($pdo,$pm){
    $map=['Nakit'=>'Kasa','Banka'=>'Banka','Kredi Kartı'=>'Kredi Kartı','POS'=>'POS'];
    $type=$map[$pm] ?? 'Kasa';
    try{ $s=$pdo->prepare("SELECT id FROM finance_accounts WHERE account_type=? AND COALESCE(active,1)=1 ORDER BY id LIMIT 1"); $s->execute([$type]); $r=$s->fetch(); return $r?(int)$r['id']:null; }catch(Throwable $e){ return null; }
}

// POST işlemini topx'tan ÖNCE yap → header redirect (PRG)
if($_SERVER['REQUEST_METHOD']==='POST'){
    $err='';
    try{
        $contact=(int)($_POST['contact_id']??0); // opsiyonel (çek/senet HARİÇ — orada zorunlu)
        $personnelId=(int)($_POST['personnel_id']??0) ?: null;
        $paymentType=trim($_POST['payment_type']??'') ?: null;
        $amount=(float)str_replace(',','.',$_POST['amount']??'0');
        $pm=$_POST['payment_channel'] ?? 'Nakit';
        if($amount<=0) throw new Exception('Tutar geçersiz.');

        // P0 FİNANS UX + ÇEK/SENET ENTEGRASYONU (2026-07-18, Product Owner kararı) — Yöntem=Çek/
        // Senet artık burada da (web finance_new.php ile AYNI mantık) checks_notes_lib.php'nin TEK
        // kaynağına gider — "kendi çekimizi ver" (verilen, Bekliyor) veya "portföydeki çeki ciro
        // et" (checks_notes_endorse()). Kasa/banka hareketi OLUŞMAZ; önceden burada "Diğer"
        // hesabına sessizce yazılan YANLIŞ davranış kaldırıldı.
        if(in_array($pm, ['Çek','Senet'], true)){
            if(!$contact) throw new Exception('Cari seçilmelidir.');
            $cnDate=date('Y-m-d'); $cnDesc=trim($_POST['description'] ?? '');
            if(($_POST['cn_mode'] ?? 'own')==='endorse'){
                $cnId=(int)($_POST['cn_endorse_id'] ?? 0);
                if(!$cnId) throw new Exception('Ciro edilecek çek/senet seçilmelidir.');
                checks_notes_endorse($pdo, $ME, $cnId, $contact, $cnDate, $cnDesc);
                $cnTitle='Çek/senet ciro edildi';
            }else{
                checks_notes_create($pdo, [
                    'type'=>($pm==='Senet'?'senet':'cek'), 'direction'=>'verilen', 'amount'=>$amount,
                    'due_date'=>trim($_POST['cn_due_date'] ?? ''), 'contact_id'=>$contact,
                    'bank_name'=>trim($_POST['cn_bank_name'] ?? ''), 'number'=>trim($_POST['cn_number'] ?? ''),
                    'notes'=>$cnDesc,
                ], $ME);
                $cnTitle='Çek/senet verildi (portföyde)';
            }
            $cn=$pdo->prepare("SELECT name FROM contacts WHERE id=?"); $cn->execute([$contact]); $cname=$cn->fetch()['name']??'';
            try{ if(function_exists('activity_log')) activity_log('Finans','Ödeme',$cnTitle,$cname.' · '.mm($amount),'finance',$contact,base_url().'checks_notes.php','🧾'); }catch(Throwable $e){}
            header('Location: kasa.php?ok=payment'); exit;
        }

        // FINANCE UX REFACTOR (2026-07-04): "Ne kaydediyorsun?" sihirbazının "Personel Ödemesi"
        // adımı için — kolon zaten var (migration 035), bu ekrana ilk kez ekleniyor.
        $step=$_POST['record_step'] ?? 'diger';
        $stepOpts=finance_record_type_options();
        if($step==='cari' && !$contact) throw new Exception('Cari Ödemesi için cari seçilmelidir.');
        if($step==='personel' && !$personnelId) throw new Exception('Personel Ödemesi için personel seçilmelidir.');
        if($step==='diger' && trim($_POST['description'] ?? '')==='') throw new Exception('Diğer seçildiğinde açıklama zorunludur.');
        if(in_array($step,finance_expense_type_required_steps(),true) && !$paymentType){
            $stepLabels=['isletme'=>'İşletme Gideri','vergi'=>'Vergi / SGK','arac'=>'Araç Gideri'];
            throw new Exception(($stepLabels[$step]??'Bu adım').' için Gider Türü seçilmelidir.');
        }

        // Hesap seçimi: form'dan gelirse onu kullan, yoksa yönteme göre bul
        $accId=(int)($_POST['account_id']??0);
        if(!$accId) $accId=pay_acc_for_pm($pdo,$pm);

        $pdo->prepare("INSERT INTO finance_movements(contact_id,personnel_id,direction,amount,payment_channel,payment_type,account_id,status,movement_date,description,movement_type)
            VALUES(?,?,?,?,?,?,?,?,?,?,'mobile')")
            ->execute([$contact?:null,$personnelId,'out',$amount,$pm,$paymentType,$accId,'Ödendi',date('Y-m-d'),trim($_POST['description'] ?? '')]);

        if($accId){ try{ $pdo->prepare("UPDATE finance_accounts SET current_balance=current_balance-? WHERE id=?")->execute([$amount,$accId]); }catch(Throwable $e){} }

        $cname='Cari seçilmedi';
        if($contact){ try{ $cn=$pdo->prepare("SELECT name FROM contacts WHERE id=?"); $cn->execute([$contact]); $cname=$cn->fetch()['name']??$cname; }catch(Throwable $e){} }
        try{ if(function_exists('activity_log')) activity_log('Finans','Ödeme',$cname.' · '.mm($amount),$pm,'finance',$contact?:0,base_url().'finance.php','💸'); }catch(Throwable $e){}
    }catch(Throwable $e){ $err=$e->getMessage(); }
    if($err===''){ header('Location: kasa.php?ok=payment'); exit; }
    $_SESSION['payment_err']=$err;
    header('Location: payment.php'.($cid?'?contact_id='.$cid:'')); exit;
}

topx('Ödeme / Gider');
if(!empty($_SESSION['payment_err'])){ echo ds_alert('danger',$_SESSION['payment_err']); unset($_SESSION['payment_err']); }

// FİNANS UX REGRESYON DÜZELTMESİ (2026-07-19) — TÜM contacts tablosunu DOM'a döken tam liste
// sorgusu kaldırıldı; arama artık ../contact_search_ajax.php üzerinden AJAX ile yapılıyor.
$accounts=[]; $personnel=[]; $cekPortfoy=[];
$__contactInitial=null;
if($cid){
    try{
        $__ciq=$pdo->prepare("SELECT id,name,type FROM contacts WHERE id=?");
        $__ciq->execute([$cid]);
        $__contactInitial=$__ciq->fetch() ?: null;
    }catch(Throwable $e){}
}
try{ $accounts=$pdo->query("SELECT * FROM finance_accounts WHERE COALESCE(active,1)=1 ORDER BY account_type,name")->fetchAll(); }catch(Throwable $e){}
try{ $personnel=$pdo->query("SELECT id,name FROM personnel WHERE COALESCE(active,1)=1 ORDER BY name")->fetchAll(); }catch(Throwable $e){}
if(checks_notes_lifecycle_ready()) $cekPortfoy=checks_notes_list($pdo, null, 'portfoyde', 'alinan');
$stepOpts=finance_record_type_options();
?>
<div class="df-panel" style="display:flex;gap:8px"><a class="df-btn df-btn--primary" href="kasa.php" style="flex:1;justify-content:center"><?=ds_icon('wallet',14)?> Kasa Durumu</a></div>
<div class="df-panel" style="margin-top:12px">
<form method="post" id="paymentForm" enctype="multipart/form-data">
  <!-- FİNANS UX REGRESYON — ÖDEME YAP EKRANINI AYIR (2026-07-19, Product Owner kararı) — ekran
       artık üstte "Cari / Tedarikçi Ödemesi" / "Genel Gider" seçimiyle iki akışa ayrılıyor.
       Varsayılan HER ZAMAN cari (bu ekranın edit modu yok, her açılış yeni kayıt). -->
  <div id="pmPayTypeWrap">
  <label>Ödeme Türü</label>
  <div class="df-tabs" id="pmPayType" style="margin:0 0 12px">
    <button type="button" class="df-tab df-tab--active" data-ptype="cari" onclick="pmSetPayType(this,'cari')">Cari / Tedarikçi Ödemesi</button>
    <button type="button" class="df-tab" data-ptype="genel" onclick="pmSetPayType(this,'genel')">Genel Gider</button>
  </div>
  </div>

  <div id="pmWizardWrap" style="display:none">
  <label>Ne kaydediyorsun?</label>
  <select name="record_step" id="pmStep" onchange="pmApplyStep()">
    <?php foreach($stepOpts as $key=>$o): ?><option value="<?=$key?>"<?=$key==='cari'?' style="display:none"':''?>><?=$o['icon']?> <?=h($o['label'])?></option><?php endforeach; ?>
  </select>
  </div>

  <div id="pmField_contact_id">
  <label>Cari</label>
  <div class="df-contact-picker">
    <input type="text" id="pmContactQuery" autocomplete="off" placeholder="İsim veya telefon ile ara…" value="<?=h($__contactInitial['name'] ?? '')?>">
    <input type="hidden" name="contact_id" id="pmContactSel" value="<?=$cid ?: ''?>" data-selected-name="<?=h($__contactInitial['name'] ?? '')?>">
    <div class="df-contact-picker-results" id="pmContactResults" hidden></div>
  </div>
  <!-- P0 FİNANS UX (2026-07-18, Product Owner kararı 1. madde): varsayılan Tedarikçiler.
       FİNANS UX REGRESYON DÜZELTMESİ (2026-07-19): liste artık AJAX scope parametresiyle geliyor. -->
  <div class="df-tabs" id="pmScope" style="margin:8px 0 12px">
    <button type="button" class="df-tab df-tab--active" data-scope="filtered" onclick="pmSetScope(this,'filtered')">Tedarikçiler</button>
    <button type="button" class="df-tab" data-scope="all" onclick="pmSetScope(this,'all')">Tüm Cariler</button>
  </div>
  </div>

  <div id="pmField_personnel_id" style="display:none">
  <label>Personel</label>
  <select name="personnel_id"><option value="">— Personel seçilmedi —</option>
  <?php foreach($personnel as $p): ?><option value="<?=(int)$p['id']?>"><?=h($p['name'])?></option><?php endforeach; ?></select>
  </div>

  <!-- GİDER TÜRÜ CONTEXT-AWARE (2026-07-04): payment_type-bağlı "Gider Türü" — seçenekleri
       pmApplyStep() ile adıma özel yeniden oluşturulur (bkz. finance_expense_type_options()). -->
  <div id="pmField_payment_type">
  <label>Gider Türü</label>
  <select name="payment_type" id="pmTurSel"><option value="">— Seç —</option></select>
  </div>

  <div id="pmField_account_id">
  <label>Hesap / Kasa / Kart</label>
  <select name="account_id"><option value="">Yönteme göre otomatik</option>
  <?php foreach($accounts as $a): ?><option value="<?=$a['id']?>"><?=h($a['account_type'].' - '.$a['name'].' / '.mm($a['current_balance']??0))?></option><?php endforeach; ?></select>
  </div>

  <label>Tutar</label><input type="number" step="0.01" name="amount" required>
  <label>Ödeme Yöntemi</label>
  <select name="payment_channel" id="pmChannel" onchange="pmToggleCek()"><option>Nakit</option><option>Banka</option><option>Kredi Kartı</option><option>POS</option><option>Çek</option><option>Senet</option></select>

  <!-- P0 FİNANS UX + ÇEK/SENET ENTEGRASYONU (2026-07-18, Product Owner kararı 4. madde) — Çek/
       Senet seçilince iki gerçek seçenek: A) kendi çekimizi/senedimizi ver, B) portföydeki müşteri
       çekini ciro et. Hesap alanı ikisinde de HİÇ sorulmaz (kasa/banka hareketi yok). -->
  <div id="pmCekBlock" style="display:none">
    <div class="df-tabs" id="pmCekMode" style="margin:10px 0">
      <button type="button" class="df-tab df-tab--active" data-mode="own" onclick="pmSetCekMode(this,'own')">Kendi Çekimizi Ver</button>
      <button type="button" class="df-tab" data-mode="endorse" onclick="pmSetCekMode(this,'endorse')">Portföydeki Çeki Ciro Et</button>
    </div>
    <input type="hidden" name="cn_mode" id="pmCnMode" value="own">
    <div id="pmCekOwnFields">
      <label>Numara</label><input name="cn_number" placeholder="Çek/senet no">
      <label>Vade Tarihi</label><input type="date" name="cn_due_date">
      <div id="pmBankWrap"><label>Banka Adı</label><input name="cn_bank_name" placeholder="Sadece çek için"></div>
      <label>Dosya / Fotoğraf <small class="muted">(opsiyonel)</small></label><input type="file" name="attachment" accept="image/*,application/pdf">
    </div>
    <div id="pmCekEndorseFields" style="display:none">
      <label>Ciro Edilecek Çek/Senet (Portföyde)</label>
      <select name="cn_endorse_id"><option value="">— Seç —</option>
      <?php foreach($cekPortfoy as $cn): ?>
      <option value="<?=(int)$cn['id']?>"><?=h(($cn['type']==='senet'?'Senet':'Çek').' · '.($cn['number']?:'no yok').' · '.($cn['contact_name']?:'—').' · '.number_format((float)$cn['amount'],2,',','.').' ₺'.($cn['due_date']?' · vade '.$cn['due_date']:''))?></option>
      <?php endforeach; ?>
      </select>
      <?php if(!$cekPortfoy): ?><p class="muted" style="font-size:12px">Portföyde ciro edilebilecek alınan çek/senet yok.</p><?php endif; ?>
    </div>
  </div>

  <label>Açıklama</label><textarea name="description" id="pmDesc" rows="2" placeholder="Gider / ödeme açıklaması"></textarea>
  <button class="df-btn df-btn--primary df-btn--lg" style="width:100%;margin-top:8px"><?=ds_icon('check',16)?> Ödemeyi Kaydet</button>
</form>
</div>
<script>
// GİDER TÜRÜ CONTEXT-AWARE (2026-07-04): finance_lib.php'deki finance_expense_type_options() ile
// BİREBİR aynı katalog (web+mobil tek kaynak).
var PM_TUR_CATALOG = <?=json_encode(finance_expense_type_options())?>;
var PM_TUR_REQUIRED = <?=json_encode(finance_expense_type_required_steps())?>;
function pmBuildTurOptions(step){
  var sel=document.getElementById('pmTurSel');
  var opts=PM_TUR_CATALOG[step] || [];
  sel.innerHTML='<option value="">— Seç —</option>';
  for(var i=0;i<opts.length;i++){
    var el=document.createElement('option');
    el.value=opts[i].v; el.textContent=opts[i].t;
    sel.appendChild(el);
  }
}
// Her adım sadece kendi ilgili alanını gösterir (yanlış kayıt ihtimalini azaltma amacı) — Personel
// SADECE "Personel Ödemesi"nde görünür/zorunlu. Cari artık bu sihirbazın işi değil (pmApplyPayType()).
function pmApplyStep(){
  if(document.getElementById('pmChannel').value==='Çek'||document.getElementById('pmChannel').value==='Senet') return;
  if(PM_PAY_TYPE!=='genel') return;
  var step=document.getElementById('pmStep').value;
  var persBox=document.getElementById('pmField_personnel_id');
  persBox.style.display = (step==='personel') ? '' : 'none';
  persBox.querySelector('select').required = (step==='personel');
  document.getElementById('pmField_payment_type').style.display='';
  pmBuildTurOptions(step);
  document.getElementById('pmTurSel').required = (PM_TUR_REQUIRED.indexOf(step)!==-1);
  document.getElementById('pmDesc').required = (step==='diger');
}

// FİNANS UX REGRESYON — ÖDEME YAP EKRANINI AYIR (2026-07-19, Product Owner kararı) — "Cari /
// Tedarikçi Ödemesi" backend'de AYNEN var olan record_step==='cari' yolunu kullanır (INSERT/bakiye
// mantığı hiç değişmedi) — #pmStep'i JS'ten 'cari' değerine sabitler (DOM'da gizli
// <option value="cari"> zaten var). "Genel Gider" mevcut sihirbazın BİREBİR aynısı.
var PM_PAY_TYPE = 'cari';
function pmSetPayType(btn, ptype){
  PM_PAY_TYPE = ptype;
  pmApplyPayType();
}
function pmApplyPayType(){
  document.querySelectorAll('#pmPayType .df-tab').forEach(function(b){ b.classList.toggle('df-tab--active', b.dataset.ptype===PM_PAY_TYPE); });
  var contactBox=document.getElementById('pmField_contact_id');
  if(PM_PAY_TYPE==='cari'){
    document.getElementById('pmStep').value='cari';
    document.getElementById('pmWizardWrap').style.display='none';
    document.getElementById('pmField_payment_type').style.display='none';
    document.getElementById('pmTurSel').required=false;
    document.getElementById('pmField_personnel_id').style.display='none';
    document.getElementById('pmField_personnel_id').querySelector('select').required=false;
    contactBox.style.display='';
    document.getElementById('pmContactQuery').required=true;
    document.getElementById('pmDesc').required=false;
  }else{
    contactBox.style.display='none';
    document.getElementById('pmContactQuery').required=false;
    if(window.pmContactPicker) window.pmContactPicker.clear();
    document.getElementById('pmWizardWrap').style.display='';
    if(document.getElementById('pmStep').value==='cari') document.getElementById('pmStep').value='diger';
    pmApplyStep();
  }
  if(window.pmContactPicker) window.pmContactPicker.refresh();
}

// P0 FİNANS UX (2026-07-18, Product Owner kararı 1. madde) — cari modeli değişmedi, sadece arama
// kapsamı işlem niyetine göre (Ödeme→Tedarikçi) filtrelenir. FİNANS UX REGRESYON DÜZELTMESİ
// (2026-07-19): liste artık AJAX scope parametresiyle geliyor.
var PM_SCOPE='filtered';
function pmSetScope(btn,scope){
  document.querySelectorAll('#pmScope .df-tab').forEach(function(b){ b.classList.toggle('df-tab--active', b.dataset.scope===scope); });
  PM_SCOPE=scope;
  if(window.pmContactPicker) window.pmContactPicker.refresh();
}

// P0 FİNANS UX + ÇEK/SENET ENTEGRASYONU (2026-07-18, Product Owner kararı 2-4. madde) — Yöntem
// Çek/Senet olunca: sihirbaz/Gider Türü/Personel/Hesap alanları gizlenir, cari HER ZAMAN
// görünür/zorunlu olur, "Kendi Çekimizi Ver / Portföydeki Çeki Ciro Et" alt-seçimi belirir.
function pmToggleCek(){
  var pm=document.getElementById('pmChannel').value;
  var isCek=(pm==='Çek'||pm==='Senet');
  document.getElementById('pmCekBlock').style.display=isCek?'':'none';
  document.getElementById('pmBankWrap').style.display=(pm==='Çek')?'':'none';
  document.getElementById('pmField_account_id').style.display=isCek?'none':'';
  // Çek/Senet'te Ödeme Türü sekmesi devre dışı — checks_notes_lib.php akışı zaten HER ZAMAN cari
  // ister, "Genel Gider" bu yöntemle anlamsız.
  document.getElementById('pmPayTypeWrap').style.display=isCek?'none':'';
  document.getElementById('pmWizardWrap').style.display='none';
  var contactBox=document.getElementById('pmField_contact_id');
  if(isCek){
    contactBox.style.display='';
    document.getElementById('pmContactQuery').required=true;
    document.getElementById('pmField_personnel_id').style.display='none';
    document.getElementById('pmField_personnel_id').querySelector('select').required=false;
    document.getElementById('pmField_payment_type').style.display='none';
    document.getElementById('pmTurSel').required=false;
    document.getElementById('pmDesc').required=false;
  }else{
    document.getElementById('pmContactQuery').required=false;
    pmApplyPayType();
  }
  if(window.pmContactPicker) window.pmContactPicker.refresh();
}
function pmSetCekMode(btn,mode){
  document.getElementById('pmCnMode').value=mode;
  document.getElementById('pmCekOwnFields').style.display=(mode==='own')?'':'none';
  document.getElementById('pmCekEndorseFields').style.display=(mode==='endorse')?'':'none';
  document.querySelectorAll('#pmCekMode .df-tab').forEach(function(b){ b.classList.toggle('df-tab--active', b.dataset.mode===mode); });
}
window.pmContactPicker = dfInitContactPicker({
  inputId:'pmContactQuery', hiddenId:'pmContactSel', resultsId:'pmContactResults',
  endpoint:'../contact_search_ajax.php', getScope: function(){ return PM_SCOPE==='all' ? 'all' : 'suppliers'; }
});
pmApplyPayType();
pmToggleCek();
</script>
<div class="df-panel" style="margin-top:12px"><b><?=ds_icon('box',16)?> Son Ödemeler</b>
<?php
try{
  $recent=$pdo->prepare("SELECT f.*, c.name cari, ac.name kat FROM finance_movements f LEFT JOIN contacts c ON c.id=f.contact_id LEFT JOIN accounting_categories ac ON ac.id=f.category_id WHERE f.direction='out' AND f.movement_type IN ('normal','mobile') ORDER BY f.id DESC LIMIT 10");
  $recent->execute();
  $rrows=$recent->fetchAll();
  if(!$rrows) ds_empty_state('Henüz ödeme yok.');
  foreach($rrows as $m){
    $tag=$m['cari'] ?: ($m['kat'] ?: '-');
    ds_list_item(
      '<b style="color:var(--df-danger-ink)">'.mm($m['amount']).'</b>',
      'movement_view.php?id='.(int)$m['id'],
      h($tag.' · '.($m['payment_channel']?:'').' · '.($m['movement_date']??''))
    );
  }
}catch(Throwable $e){ echo ds_alert('danger',$e->getMessage()); }
?>
</div>
<?php botx(); ?>
