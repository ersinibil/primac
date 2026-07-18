<?php
require_once 'common.php';
$pdo=db();
$cid=(int)($_GET['contact_id'] ?? 0);
$ok=''; $er='';

function acc_for_pm($pdo,$pm){
    $map=['Nakit'=>'Kasa','Banka'=>'Banka','Kredi Kartı'=>'Kredi Kartı','POS'=>'POS','Çek'=>'Diğer','Senet'=>'Diğer'];
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
$cs=$pdo->query("SELECT id,name FROM contacts ORDER BY name")->fetchAll();
$preContact=(int)($pre['contact_id'] ?? $cid);
?>
<?php if($ok): ?><?=ds_alert('success',$ok)?><?php endif; ?>
<?php if($er): ?><?=ds_alert('danger',$er)?><?php endif; ?>
<?php if($warning): ?><div class="df-alert df-alert--warning" style="display:block"><b><?=ds_icon('info',14)?> Dikkat:</b> <?=$warning?></div><?php endif; ?>
<div class="df-panel" style="margin-top:12px">
<form method="post">
  <label>Cari</label>
  <select name="contact_id" required><option value="">— Seç —</option>
  <?php foreach($cs as $c): ?><option value="<?=$c['id']?>" <?=$preContact===(int)$c['id']?'selected':''?>><?=h($c['name'])?></option><?php endforeach; ?></select>
  <label>Tutar</label><input type="number" step="0.01" name="amount" value="<?=h($pre['amount'] ?? '')?>" required>
  <label>Tahsilat Yöntemi</label>
  <select name="payment_channel">
  <?php foreach(['Nakit','Banka','Kredi Kartı','POS','Çek','Senet'] as $pm): ?>
  <option <?=($pre['payment_channel'] ?? '')===$pm?'selected':''?>><?=$pm?></option>
  <?php endforeach; ?>
  </select>
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
