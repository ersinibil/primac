<?php
require_once 'common.php';
require_once __DIR__.'/../checks_notes_lib.php';
$pdo=db();
$cid=(int)($_GET['contact_id'] ?? 0);
$ok=''; $er='';

function acc_for_pm($pdo,$pm){
    $map=['Nakit'=>'Kasa','Banka'=>'Banka','Kredi Kartı'=>'Kredi Kartı','POS'=>'POS'];
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

        // P0 FİNANS UX + ÇEK/SENET ENTEGRASYONU (2026-07-18, Product Owner kararı) — Yöntem=Çek/
        // Senet artık burada da (web finance_new.php ile AYNI mantık) checks_notes_lib.php'nin TEK
        // kaynağına (checks_notes_create()) gider — kasa/banka hareketi OLUŞMAZ, önceden burada
        // "Diğer" hesabına sessizce yazılan YANLIŞ davranış kaldırıldı.
        if(in_array($pm, ['Çek','Senet'], true)){
            checks_notes_create($pdo, [
                'type'=>($pm==='Senet'?'senet':'cek'), 'direction'=>'alinan', 'amount'=>$amount,
                'due_date'=>trim($_POST['cn_due_date'] ?? ''), 'contact_id'=>$contact,
                'bank_name'=>trim($_POST['cn_bank_name'] ?? ''), 'number'=>trim($_POST['cn_number'] ?? ''),
                'notes'=>trim($_POST['description'] ?? ''),
            ], $ME);
            $cn=$pdo->prepare("SELECT name FROM contacts WHERE id=?"); $cn->execute([$contact]); $cname=$cn->fetch()['name']??'';
            try{ if(function_exists('activity_log')) activity_log('Finans','Tahsilat',$cname.' · '.mm($amount).' ('.$pm.', portföyde)','','finance',$contact,'contact_view.php?id='.$contact,'🧾'); }catch(Throwable $e){}
            $_SESSION['collection_ok']=$cname.' — '.$pm.' portföye alındı: '.mm($amount);
            header('Location: collection.php?contact_id='.$contact); exit;
        }

        // Migration'sız kısmi önlem (2026-07-09, Finance Core Stabilization İş 3): aynı ekonomik
        // olayın çift kaydını ENGELLEMEZ, sadece en yaygın tetikleyiciye (aynı cari + aynı tutarda
        // zaten "Bekliyor" bir kayıt varken habersizce ikinci bir tahsilat girilmesi) karşı
        // yumuşak bir uyarı gösterir — kullanıcı bilerek devam edebilir.
        if(empty($_POST['confirm_duplicate'])){
            try{
                $dupQ=$pdo->prepare("SELECT id,movement_date FROM finance_movements
                    WHERE contact_id=? AND direction='in' AND amount=? AND status='Bekliyor'
                    ORDER BY id DESC LIMIT 1");
                $dupQ->execute([$contact,$amount]);
                $dupRow=$dupQ->fetch();
                if($dupRow){
                    $_SESSION['collection_warning']='Bu caride aynı tutarda ('.number_format($amount,2,',','.').' ₺) '
                        .htmlspecialchars($dupRow['movement_date']).' tarihli, hâlâ "Bekliyor" durumunda bir kayıt var. '
                        .'Bu tahsilat onun karşılığıysa aynı tutarı iki kez girmiş olabilirsiniz. Gerçekten ayrı bir '
                        .'işlemse aşağıdaki kutuyu işaretleyip tekrar kaydedin.';
                    $_SESSION['collection_prefill']=['contact_id'=>$contact,'amount'=>$amount,'payment_channel'=>$pm,'description'=>trim($_POST['description'] ?? '')];
                    header('Location: collection.php?contact_id='.$contact); exit;
                }
            }catch(Throwable $e){}
        }

        $accId=acc_for_pm($pdo,$pm);
        $pdo->prepare("INSERT INTO finance_movements(contact_id,direction,amount,payment_channel,account_id,status,movement_date,description,movement_type)
            VALUES(?,?,?,?,?,?,?,?,'mobile')")
            ->execute([$contact,'in',$amount,$pm,$accId,'Tahsil Edildi',date('Y-m-d'),trim($_POST['description'] ?? '')]);
        if($accId){ try{ $pdo->prepare("UPDATE finance_accounts SET current_balance=current_balance+? WHERE id=?")->execute([$amount,$accId]); }catch(Throwable $e){} }
        $cn=$pdo->prepare("SELECT name FROM contacts WHERE id=?"); $cn->execute([$contact]); $cname=$cn->fetch()['name']??'';
        try{ if(function_exists('activity_log')) activity_log('Finans','Tahsilat',$cname.' · '.mm($amount),$pm,'finance',$contact,'contact_view.php?id='.$contact,'💰'); }catch(Throwable $e){}
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
$warning=''; $pre=[];
if(!empty($_SESSION['collection_warning'])){ $warning=$_SESSION['collection_warning']; unset($_SESSION['collection_warning']); }
if(!empty($_SESSION['collection_prefill'])){ $pre=$_SESSION['collection_prefill']; unset($_SESSION['collection_prefill']); }
$cs=$pdo->query("SELECT id,name,type FROM contacts ORDER BY name")->fetchAll();
$preContact=(int)($pre['contact_id'] ?? $cid);
?>
<?php if($ok): ?><?=ds_alert('success',$ok)?><?php endif; ?>
<?php if($er): ?><?=ds_alert('danger',$er)?><?php endif; ?>
<?php if($warning): ?><div class="df-alert df-alert--warning" style="display:block"><b><?=ds_icon('info',14)?> Dikkat:</b> <?=$warning?></div><?php endif; ?>
<div class="df-panel" style="margin-top:12px">
<form method="post" enctype="multipart/form-data">
  <label>Cari</label>
  <select name="contact_id" id="colContactSel" required><option value="">— Seç —</option>
  <?php foreach($cs as $c): ?><option value="<?=$c['id']?>" data-ctype="<?=h($c['type'])?>" <?=$preContact===(int)$c['id']?'selected':''?>><?=h($c['name'])?></option><?php endforeach; ?></select>
  <!-- P0 FİNANS UX (2026-07-18, Product Owner kararı 1. madde): varsayılan Müşteriler, "Tüm
       Cariler" bir tık uzakta — cari modeli değişmedi, sadece liste filtreleniyor. -->
  <div class="df-tabs" id="colScope" style="margin:0 0 12px">
    <button type="button" class="df-tab df-tab--active" data-scope="filtered" onclick="colSetScope(this,'filtered')">Müşteriler</button>
    <button type="button" class="df-tab" data-scope="all" onclick="colSetScope(this,'all')">Tüm Cariler</button>
  </div>
  <label>Tutar</label><input type="number" step="0.01" name="amount" value="<?=h($pre['amount'] ?? '')?>" required>
  <label>Tahsilat Yöntemi</label>
  <select name="payment_channel" id="colChannel" onchange="colToggleCek()">
  <?php foreach(['Nakit','Banka','Kredi Kartı','POS','Çek','Senet'] as $pm): ?>
  <option <?=($pre['payment_channel'] ?? '')===$pm?'selected':''?>><?=$pm?></option>
  <?php endforeach; ?>
  </select>
  <!-- P0 FİNANS UX + ÇEK/SENET ENTEGRASYONU (2026-07-18, Product Owner kararı 2. madde) — Çek/
       Senet seçilince kasa/banka hesabı hiç sorulmaz (bu ekranda zaten görünür bir hesap alanı
       yok, otomatik eşleniyordu — o eşleme çek/senet için tamamen kaldırıldı). -->
  <div id="colCekBlock" style="display:none">
    <label>Numara</label><input name="cn_number" placeholder="Çek/senet no">
    <label>Vade Tarihi</label><input type="date" name="cn_due_date">
    <div id="colBankWrap"><label>Banka Adı</label><input name="cn_bank_name" placeholder="Sadece çek için"></div>
    <label>Dosya / Fotoğraf <small class="muted">(opsiyonel)</small></label><input type="file" name="attachment" accept="image/*,application/pdf">
  </div>
  <label>Açıklama</label><textarea name="description" rows="2"><?=h($pre['description'] ?? '')?></textarea>
  <?php if($warning): ?>
  <label style="background:rgba(234,179,8,.12);border-radius:10px;padding:10px;display:block;margin-top:8px">
    <input type="checkbox" name="confirm_duplicate" value="1" style="width:auto;display:inline-block;margin-right:6px">
    Bunun ayrı/yeni bir işlem olduğundan eminim, yine de kaydet.
  </label>
  <?php endif; ?>
  <button class="df-btn df-btn--primary df-btn--lg" style="width:100%;margin-top:8px"><?=$warning?'Yine de Kaydet':ds_icon('check',16).' Tahsilatı Kaydet'?></button>
</form>
</div>
<script>
var COL_SCOPE='filtered';
function colSetScope(btn,scope){
  document.querySelectorAll('#colScope .df-tab').forEach(function(b){ b.classList.toggle('df-tab--active', b.dataset.scope===scope); });
  COL_SCOPE=scope; colApplyScope();
}
function colApplyScope(){
  var sel=document.getElementById('colContactSel');
  Array.prototype.forEach.call(sel.options, function(o){
    if(!o.value){ o.style.display=''; return; }
    o.style.display=(COL_SCOPE==='all' || o.dataset.ctype==='Müşteri' || o.dataset.ctype==='Her İkisi') ? '' : 'none';
  });
}
function colToggleCek(){
  var pm=document.getElementById('colChannel').value;
  var isCek=(pm==='Çek'||pm==='Senet');
  document.getElementById('colCekBlock').style.display=isCek?'':'none';
  document.getElementById('colBankWrap').style.display=(pm==='Çek')?'':'none';
}
colApplyScope();
colToggleCek();
</script>
<div class="df-panel" style="margin-top:12px"><b><?=ds_icon('box',16)?> Son Tahsilatlar</b>
<?php
try{
  $recent=$pdo->prepare("SELECT f.*, c.name cari FROM finance_movements f LEFT JOIN contacts c ON c.id=f.contact_id WHERE f.direction='in' AND f.movement_type IN ('normal','mobile') ORDER BY f.id DESC LIMIT 10");
  $recent->execute();
  $rrows=$recent->fetchAll();
  if(!$rrows) ds_empty_state('Henüz tahsilat yok.');
  foreach($rrows as $m){
    ds_list_item(
      '<b style="color:var(--df-success-ink)">'.mm($m['amount']).'</b>',
      'movement_view.php?id='.(int)$m['id'],
      h(($m['cari']?:'-').' · '.($m['payment_channel']?:'').' · '.($m['movement_date']??''))
    );
  }
}catch(Throwable $e){ echo ds_alert('danger',$e->getMessage()); }
?>
</div>
<?php botx(); ?>
