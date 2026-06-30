<?php
require_once 'common.php';
block_personel(); // alış admin işi
$pdo=db();
$ok=''; $er='';

function acc_for_pm($pdo,$pm){
    $map=['Peşin'=>'Kasa','Banka'=>'Banka','Kredi Kartı'=>'Kredi Kartı','POS'=>'POS'];
    $type=$map[$pm] ?? 'Kasa';
    try{ $s=$pdo->prepare("SELECT id FROM finance_accounts WHERE account_type=? AND active=1 ORDER BY id LIMIT 1"); $s->execute([$type]); $r=$s->fetch(); return $r?(int)$r['id']:null; }catch(Throwable $e){ return null; }
}

if($_SERVER['REQUEST_METHOD']==='POST'){
    try{
        $supplier=(int)$_POST['contact_id'];
        $pname=trim($_POST['product_name'] ?? '');
        $qty=(float)$_POST['quantity'];
        $price=(float)$_POST['unit_price'];
        $pm=$_POST['payment_method'] ?? 'Veresiye';
        if(!$supplier) throw new Exception('Tedarikçi seçin.');
        if($pname==='') throw new Exception('Ürün adı girin.');
        if($qty<=0) throw new Exception('Miktar geçersiz.');
        $total=$qty*$price;

        // Ürün var mı? yoksa otomatik stok kartı aç
        $f=$pdo->prepare("SELECT * FROM stock_items WHERE name=? LIMIT 1"); $f->execute([$pname]); $item=$f->fetch();
        if($item){
            $pid=(int)$item['id'];
            $oldQty=(float)$item['quantity']; $oldAvg=(float)($item['avg_cost'] ?: $item['purchase_price']);
            $newQty=$oldQty+$qty; $newAvg=$newQty>0?(($oldQty*$oldAvg)+($qty*$price))/$newQty:$price;
            $pdo->prepare("UPDATE stock_items SET quantity=?, avg_cost=?, purchase_price=?, last_purchase_price=? WHERE id=?")
                ->execute([$newQty,$newAvg,$price,$price,$pid]);
            $yeni=false;
        }else{
            $code='URN-'.date('ymd').'-'.random_int(100,999);
            $pdo->prepare("INSERT INTO stock_items(product_code,name,unit,quantity,critical_level,purchase_price,sale_price,avg_cost,last_purchase_price,active)
                VALUES(?,?,?,?,?,?,?,?,?,1)")
                ->execute([$code,$pname,trim($_POST['unit']??'adet'),$qty,0,$price,(float)($_POST['sale_price']??0),$price,$price]);
            $pid=(int)$pdo->lastInsertId(); $yeni=true;
        }

        // Stok hareketi (giriş)
        try{ $pdo->prepare("INSERT INTO stock_movements(stock_item_id,direction,quantity,reason,note,created_at) VALUES(?,?,?,?,?,NOW())")
            ->execute([$pid,'in',$qty,'Alış','Mobil alış ('.$pm.')']); }catch(Throwable $e){}

        // Tedarikçiye ödeme (kasa kaydı) — Veresiye ise hesaba dokunma, cari borç
        $accId = ($pm==='Veresiye') ? null : acc_for_pm($pdo,$pm);
        $pdo->prepare("INSERT INTO finance_movements(contact_id,direction,amount,payment_channel,account_id,status,movement_date,description,movement_type)
            VALUES(?,?,?,?,?,?,?,?,'mobile_purchase')")
            ->execute([$supplier,'out',$total,$pm,$accId,$pm==='Veresiye'?'Bekliyor':'Ödendi',date('Y-m-d'),$pname.' x'.rtrim(rtrim(number_format($qty,2,'.',''),'0'),'.').' alış']);
        if($accId){ try{ $pdo->prepare("UPDATE finance_accounts SET current_balance=current_balance-? WHERE id=?")->execute([$total,$accId]); }catch(Throwable $e){} }

        $sn=$pdo->prepare("SELECT name FROM contacts WHERE id=?"); $sn->execute([$supplier]); $sname=$sn->fetch()['name']??'';
        try{ if(function_exists('activity_log')) activity_log('Satın Alma','Alış',$sname.' · '.$pname.' '.mm($total),$pm,'purchase',$pid,'mobile/stock.php','🛒'); }catch(Throwable $e){}
        $ok=$pname.' alındı: '.mm($total).' ('.$pm.')'.($yeni?' · yeni stok kartı açıldı':'');
    }catch(Throwable $e){ $er=$e->getMessage(); }
}
topx('Satın Alma');
$cs=$pdo->query("SELECT id,name FROM contacts WHERE type IN ('Tedarikçi','Her İkisi') OR type IS NULL ORDER BY name")->fetchAll();
if(!$cs) $cs=$pdo->query("SELECT id,name FROM contacts ORDER BY name")->fetchAll();
$ps=$pdo->query("SELECT name FROM stock_items WHERE COALESCE(active,1)=1 ORDER BY name")->fetchAll();
?>
<?php if($ok): ?><div class="notice"><?=htmlspecialchars($ok)?></div><?php endif; ?>
<?php if($er): ?><div class="err"><?=htmlspecialchars($er)?></div><?php endif; ?>
<div class="panel">
<form method="post">
  <label>Tedarikçi</label>
  <select name="contact_id" required><option value="">— Seç —</option><?php foreach($cs as $c): ?><option value="<?=$c['id']?>"><?=htmlspecialchars($c['name'])?></option><?php endforeach; ?></select>
  <label>Ürün (yoksa otomatik açılır)</label>
  <input name="product_name" list="prods" required placeholder="Ürün adı yaz veya seç">
  <datalist id="prods"><?php foreach($ps as $p): ?><option value="<?=htmlspecialchars($p['name'])?>"><?php endforeach; ?></datalist>
  <div style="display:flex;gap:10px">
    <div style="flex:1"><label>Miktar</label><input type="number" step="0.01" name="quantity" id="q" value="1" required oninput="c()"></div>
    <div style="flex:1"><label>Birim Alış Fiyatı</label><input type="number" step="0.01" name="unit_price" id="p" required oninput="c()"></div>
  </div>
  <label>Satış Fiyatı (yeni üründe)</label><input type="number" step="0.01" name="sale_price" placeholder="opsiyonel">
  <label>Ödeme</label>
  <select name="payment_method"><option>Veresiye</option><option>Peşin</option><option>Banka</option><option>Kredi Kartı</option></select>
  <div class="panel" style="background:rgba(37,99,235,.18);text-align:center;margin:12px 0"><small class="muted">Toplam</small><div id="t" style="font-size:26px;font-weight:900">0,00 ₺</div></div>
  <button class="btn dark" style="width:100%;padding:14px">🛒 Alışı Kaydet</button>
</form>
</div>
<script>function c(){var q=parseFloat(document.getElementById('q').value)||0,p=parseFloat(document.getElementById('p').value)||0;document.getElementById('t').textContent=(q*p).toLocaleString('tr-TR',{minimumFractionDigits:2,maximumFractionDigits:2})+' ₺';}c();</script>
<?php botx(); ?>
