<?php
require_once 'common.php';
block_personel();
$pdo=db(); $id=(int)($_GET['id']??0);
topx('Hesap');
try{
    $a=$pdo->prepare("SELECT * FROM finance_accounts WHERE id=?"); $a->execute([$id]); $acc=$a->fetch();
    if(!$acc) throw new Exception('Hesap bulunamadı.');
    $ic=$acc['account_type']==='Banka'?'🏦':($acc['account_type']==='Kredi Kartı'?'💳':($acc['account_type']==='POS'?'🧾':'💵'));
    // bu hesabın dönem giriş/çıkış
    $sm=$pdo->prepare("SELECT COALESCE(SUM(CASE WHEN direction='in' THEN amount END),0) tin, COALESCE(SUM(CASE WHEN direction='out' THEN amount END),0) tout FROM finance_movements WHERE account_id=?");
    $sm->execute([$id]); $t=$sm->fetch();
?>
<div class="panel">
  <h2 style="margin:0 0 4px"><?=$ic?> <?=htmlspecialchars($acc['name'])?></h2>
  <div class="muted"><?=htmlspecialchars($acc['account_type'].($acc['bank_name']?' · '.$acc['bank_name']:'').($acc['iban']?' · '.$acc['iban']:''))?></div>
  <div style="font-size:30px;font-weight:900;margin-top:12px;color:<?=(float)$acc['current_balance']<0?'#f87171':'#22c55e'?>"><?=mm($acc['current_balance']??0)?></div>
  <div style="display:flex;gap:14px;margin-top:6px">
    <small style="color:#4ade80">↓ Giriş: <?=mm($t['tin'])?></small>
    <small style="color:#f87171">↑ Çıkış: <?=mm($t['tout'])?></small>
  </div>
</div>
<div class="panel"><b>📜 Hesap Hareketleri</b>
<?php
  $mv=$pdo->prepare("SELECT f.*, c.name cari FROM finance_movements f LEFT JOIN contacts c ON c.id=f.contact_id WHERE f.account_id=? ORDER BY f.id DESC LIMIT 100");
  $mv->execute([$id]); $rows=$mv->fetchAll();
  if(!$rows) echo '<p class="muted" style="margin:10px 0 0">Bu hesapta hareket yok.</p>';
  foreach($rows as $m){ $in=$m['direction']==='in';
    echo '<div class="item" style="display:flex;justify-content:space-between"><span><b style="color:'.($in?'#4ade80':'#f87171').'">'.($in?'Tahsilat':'Ödeme').'</b> '.mm($m['amount']).'<br><small class="muted">'.htmlspecialchars(($m['cari']?:'-').' · '.($m['description']??'')).'</small></span><small class="muted">'.htmlspecialchars($m['movement_date']??'').'</small></div>';
  }
?>
</div>
<?php
}catch(Throwable $e){ echo '<div class="err">'.htmlspecialchars($e->getMessage()).'</div>'; }
botx();
