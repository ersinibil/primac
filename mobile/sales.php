<?php
require_once 'common.php';
$pdo=db();
$cid=(int)($_GET['contact_id'] ?? 0);
$ok=''; $er='';

function qty_fmt($v){ return rtrim(rtrim(number_format((float)$v,2,'.',''),'0'),'.'); }

// Ödeme yöntemi → kasa hesabı tipi
function acc_for_method($pdo,$method){
    $map=['Peşin'=>'Kasa','Kredi Kartı'=>'Kredi Kartı','Banka Havalesi'=>'Banka'];
    $type=$map[$method] ?? 'Kasa';
    try{
        $s=$pdo->prepare("SELECT id FROM finance_accounts WHERE account_type=? AND active=1 ORDER BY id LIMIT 1");
        $s->execute([$type]); $r=$s->fetch();
        return $r?(int)$r['id']:null;
    }catch(Throwable $e){ return null; }
}

if($_SERVER['REQUEST_METHOD']==='POST'){
    try{
        $contact=(int)$_POST['contact_id'];
        $product=(int)$_POST['stock_item_id'];
        $qty=(float)$_POST['quantity'];
        $price=(float)$_POST['unit_price'];
        $method=$_POST['payment_method'] ?? 'Peşin';
        if(!$contact) throw new Exception('Cari seçin.');
        if(!$product) throw new Exception('Ürün seçin.');
        if($qty<=0) throw new Exception('Miktar geçersiz.');
        if($price<0) throw new Exception('Fiyat geçersiz.');

        $p=$pdo->prepare("SELECT * FROM stock_items WHERE id=?"); $p->execute([$product]); $item=$p->fetch();
        if(!$item) throw new Exception('Ürün bulunamadı.');

        $total=$qty*$price;

        // 1) Stok düş
        $pdo->prepare("UPDATE stock_items SET quantity=quantity-? WHERE id=?")->execute([$qty,$product]);
        // 2) Stok hareketi (şema farklı olabilir → try/catch)
        try{ $pdo->prepare("INSERT INTO stock_movements(stock_item_id,direction,quantity,reason,note,created_at) VALUES(?,?,?,?,?,NOW())")
            ->execute([$product,'out',$qty,'Satış','Mobil satış ('.$method.')']); }catch(Throwable $e){}

        // 3) Kasa kaydı (finans hareketi)
        $accId=acc_for_method($pdo,$method);
        $pdo->prepare("INSERT INTO finance_movements(contact_id,direction,amount,payment_channel,account_id,status,movement_date,description,movement_type)
            VALUES(?,?,?,?,?,?,?,?,'mobile_sale')")
            ->execute([$contact,'in',$total,$method,$accId,'Tahsil Edildi',date('Y-m-d'),$item['name'].' x'.qty_fmt($qty).' satış']);
        // 4) Kasa bakiyesi güncelle
        if($accId){ try{ $pdo->prepare("UPDATE finance_accounts SET current_balance=current_balance+? WHERE id=?")->execute([$total,$accId]); }catch(Throwable $e){} }

        // 5) Kâr/zarar hesabı (satış - maliyet)
        $cost=(float)($item['avg_cost'] ?: $item['purchase_price'] ?: 0);
        $profit=($price-$cost)*$qty;

        // 6) Log
        try{ if(function_exists('activity_log')) activity_log('Satış','Mobil',$item['name'].' '.mm($total).' (kâr '.mm($profit).')',$method,'sale',$product,'mobile/sales.php','🧾'); }catch(Throwable $e){}

        $kz = $profit>=0 ? ('Kâr: '.mm($profit)) : ('Zarar: '.mm(-$profit));
        $ok=$item['name'].' satıldı: '.mm($total).' ('.$method.') · '.$kz;
        $cid=$contact;
    }catch(Throwable $e){ $er=$e->getMessage(); }
}

topx('Satış Yap');
?>
<?php if($ok): ?><div class="notice"><?=htmlspecialchars($ok)?></div><?php endif; ?>
<?php if($er): ?><div class="err"><?=htmlspecialchars($er)?></div><?php endif; ?>

<?php
$cs=$pdo->query("SELECT id,name FROM contacts ORDER BY name")->fetchAll();
$ps=$pdo->query("SELECT id,name,quantity,unit,sale_price FROM stock_items WHERE COALESCE(active,1)=1 ORDER BY name")->fetchAll();
?>
<div class="panel">
<form method="post">
  <label>Cari (Müşteri)</label>
  <select name="contact_id" required>
    <option value="">— Seç —</option>
    <?php foreach($cs as $c): ?><option value="<?=$c['id']?>" <?=$cid===(int)$c['id']?'selected':''?>><?=htmlspecialchars($c['name'])?></option><?php endforeach; ?>
  </select>

  <label>Ürün</label>
  <select name="stock_item_id" id="prod" required onchange="setPrice()">
    <option value="">— Seç —</option>
    <?php foreach($ps as $p): ?><option value="<?=$p['id']?>" data-price="<?=htmlspecialchars($p['sale_price']??0)?>"><?=htmlspecialchars($p['name'].' (Stok: '.$p['quantity'].' '.$p['unit'].')')?></option><?php endforeach; ?>
  </select>

  <div style="display:flex;gap:10px">
    <div style="flex:1"><label>Miktar</label><input type="number" step="0.01" name="quantity" id="qty" value="1" required oninput="calc()"></div>
    <div style="flex:1"><label>Birim Fiyat</label><input type="number" step="0.01" name="unit_price" id="price" required oninput="calc()"></div>
  </div>

  <label>Ödeme Yöntemi</label>
  <select name="payment_method" required>
    <option>Peşin</option>
    <option>Kredi Kartı</option>
    <option>Banka Havalesi</option>
  </select>

  <div class="panel" style="background:rgba(37,99,235,.18);text-align:center;margin:14px 0">
    <small class="muted">Toplam</small><div id="tot" style="font-size:28px;font-weight:900">0,00 ₺</div>
  </div>

  <button class="btn dark" style="width:100%;padding:15px" type="submit">🧾 Satışı Tamamla</button>
</form>
</div>

<script>
function setPrice(){var o=document.getElementById('prod').selectedOptions[0];if(o&&o.dataset.price){document.getElementById('price').value=o.dataset.price;}calc();}
function calc(){var q=parseFloat(document.getElementById('qty').value)||0;var p=parseFloat(document.getElementById('price').value)||0;var t=q*p;document.getElementById('tot').textContent=t.toLocaleString('tr-TR',{minimumFractionDigits:2,maximumFractionDigits:2})+' ₺';}
calc();
</script>

<?php botx(); ?>
