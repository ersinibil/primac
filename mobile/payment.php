<?php
require_once 'common.php';
require_once __DIR__.'/../accounting_lib.php';
require_once __DIR__.'/../finance_lib.php';
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
        // FINANCE UX REFACTOR (2026-07-04): "Ne kaydediyorsun?" sihirbazının "Personel Ödemesi"
        // adımı için — kolon zaten var (migration 035), bu ekrana ilk kez ekleniyor.
        $personnelId=(int)($_POST['personnel_id']??0) ?: null;
        // GİDER TÜRÜ CONTEXT-AWARE (2026-07-04): "Gider Türü" artık adıma özel bir katalogdan
        // (finance_expense_type_options()) geliyor, mevcut payment_type kolonuna yazılıyor.
        $paymentType=trim($_POST['payment_type']??'') ?: null;
        $amount=(float)str_replace(',','.',$_POST['amount']??'0');
        $pm=$_POST['payment_channel'] ?? 'Nakit';
        if($amount<=0) throw new Exception('Tutar geçersiz.');
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
        try{ if(function_exists('activity_log')) activity_log('Finans','Ödeme',$cname.' · '.mm($amount),$pm,'finance',$contact?:0,'mobile/kasa.php','💸'); }catch(Throwable $e){}
    }catch(Throwable $e){ $err=$e->getMessage(); }
    if($err===''){ header('Location: kasa.php?ok=payment'); exit; }
    $_SESSION['payment_err']=$err;
    header('Location: payment.php'.($cid?'?contact_id='.$cid:'')); exit;
}

topx('Ödeme / Gider');
if(!empty($_SESSION['payment_err'])){ echo '<div class="err">'.htmlspecialchars($_SESSION['payment_err']).'</div>'; unset($_SESSION['payment_err']); }

$cs=[]; $accounts=[]; $personnel=[];
try{ $cs=$pdo->query("SELECT id,name FROM contacts ORDER BY name")->fetchAll(); }catch(Throwable $e){}
try{ $accounts=$pdo->query("SELECT * FROM finance_accounts WHERE COALESCE(active,1)=1 ORDER BY account_type,name")->fetchAll(); }catch(Throwable $e){}
try{ $personnel=$pdo->query("SELECT id,name FROM personnel WHERE COALESCE(active,1)=1 ORDER BY name")->fetchAll(); }catch(Throwable $e){}
$stepOpts=finance_record_type_options();
?>
<div class="panel" style="display:flex;gap:8px"><a class="btn dark" href="kasa.php" style="flex:1;text-align:center">🏦 Kasa Durumu</a></div>
<div class="panel">
<form method="post" id="paymentForm">
  <label>Ne kaydediyorsun?</label>
  <select name="record_step" id="pmStep" onchange="pmApplyStep()">
    <?php foreach($stepOpts as $key=>$o): ?><option value="<?=$key?>" <?=$cid&&$key==='cari'?'selected':''?>><?=$o['icon']?> <?=htmlspecialchars($o['label'])?></option><?php endforeach; ?>
  </select>

  <div id="pmField_contact_id">
  <label>Cari</label>
  <select name="contact_id"><option value="">— Cari seçilmedi —</option>
  <?php foreach($cs as $c): ?><option value="<?=$c['id']?>" <?=$cid===(int)$c['id']?'selected':''?>><?=htmlspecialchars($c['name'])?></option><?php endforeach; ?></select>
  </div>

  <div id="pmField_personnel_id" style="display:none">
  <label>Personel</label>
  <select name="personnel_id"><option value="">— Personel seçilmedi —</option>
  <?php foreach($personnel as $p): ?><option value="<?=(int)$p['id']?>"><?=htmlspecialchars($p['name'])?></option><?php endforeach; ?></select>
  </div>

  <!-- GİDER TÜRÜ CONTEXT-AWARE (2026-07-04): payment_type-bağlı "Gider Türü" — seçenekleri
       pmApplyStep() ile adıma özel yeniden oluşturulur (bkz. finance_expense_type_options()). -->
  <div id="pmField_payment_type">
  <label>Gider Türü</label>
  <select name="payment_type" id="pmTurSel"><option value="">— Seç —</option></select>
  </div>

  <label>Hesap / Kasa / Kart</label>
  <select name="account_id"><option value="">Yönteme göre otomatik</option>
  <?php foreach($accounts as $a): ?><option value="<?=$a['id']?>"><?=htmlspecialchars($a['account_type'].' - '.$a['name'].' / '.mm($a['current_balance']??0))?></option><?php endforeach; ?></select>

  <label>Tutar</label><input type="number" step="0.01" name="amount" required>
  <label>Ödeme Yöntemi</label>
  <select name="payment_channel"><option>Nakit</option><option>Banka</option><option>Kredi Kartı</option><option>POS</option><option>Çek</option><option>Senet</option></select>
  <label>Açıklama</label><textarea name="description" id="pmDesc" rows="2" placeholder="Gider / ödeme açıklaması"></textarea>
  <button class="btn dark" style="width:100%;padding:14px;margin-top:8px">💸 Ödemeyi Kaydet</button>
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
// Her adım sadece kendi ilgili alanını gösterir (yanlış kayıt ihtimalini azaltma amacı) — Cari
// alanı SADECE "Cari Ödemesi" adımında, Personel SADECE "Personel Ödemesi" adımında görünür.
function pmApplyStep(){
  var step=document.getElementById('pmStep').value;
  var contactBox=document.getElementById('pmField_contact_id');
  contactBox.style.display = (step==='cari') ? '' : 'none';
  contactBox.querySelector('select').required = (step==='cari');
  var persBox=document.getElementById('pmField_personnel_id');
  persBox.style.display = (step==='personel') ? '' : 'none';
  persBox.querySelector('select').required = (step==='personel');
  pmBuildTurOptions(step);
  document.getElementById('pmTurSel').required = (PM_TUR_REQUIRED.indexOf(step)!==-1);
  document.getElementById('pmDesc').required = (step==='diger');
}
pmApplyStep();
</script>
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
