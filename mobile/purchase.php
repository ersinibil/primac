<?php
require_once 'common.php';
require_once '../stock_lib.php';
$pdo=db();
$ok=''; $er='';

// POST: Satın alma giriş (PRG deseni — topx'ten ÖNCE işle)
if($_SERVER['REQUEST_METHOD']==='POST'){
    try{
        $supplier=(int)$_POST['contact_id'];
        $pname=trim($_POST['product_name'] ?? '');
        $qty=(float)$_POST['quantity'];
        $price=(float)$_POST['unit_price'];
        $pm=$_POST['payment_method'] ?? 'Veresiye';
        $unit=trim($_POST['unit']??'adet');
        $salePrice=(float)($_POST['sale_price'] ?? 0);

        // Stok kartı ekle/güncelle + hareketi kaydet
        $stockResult=stock_add_purchase($pdo, $supplier, $pname, $qty, $price, $pm, $unit, $salePrice>0?$salePrice:null);
        if(!$stockResult['ok']) throw new Exception($stockResult['message']);

        // Finansal kaydı yap
        $finResult=stock_add_purchase_finance($pdo, $supplier, $stockResult['total'], $pm, $stockResult['item_id'], $pname);

        // Aktivite loğu
        try{
            if(function_exists('activity_log')){
                $sn=$pdo->prepare("SELECT name FROM contacts WHERE id=?");
                $sn->execute([$supplier]);
                $sname=$sn->fetch()['name']??'';
                activity_log('Satın Alma','Alış',$sname.' · '.$pname.' '.mm($stockResult['total']),$pm,'purchase',$stockResult['item_id'],'mobile/purchase.php','🛒');
            }
        }catch(Throwable $e){}

        $ok=$stockResult['message'];
    }catch(Throwable $e){
        $er=$e->getMessage();
    }

    // Sonra topx() çağrısından önce yönlendir (PRG)
    if($ok){
        header('Location: purchase.php');
        exit;
    }
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
  <select name="payment_method"><option>Veresiye</option><option>Peşin</option><option>Banka</option><option>Kredi Kartı</option><option>Çek</option><option>Senet</option></select>
  <div class="panel" style="background:rgba(37,99,235,.18);text-align:center;margin:12px 0"><small class="muted">Toplam</small><div id="t" style="font-size:26px;font-weight:900">0,00 ₺</div></div>
  <button class="btn dark" style="width:100%;padding:14px">🛒 Alışı Kaydet</button>
</form>
</div>
<script>function c(){var q=parseFloat(document.getElementById('q').value)||0,p=parseFloat(document.getElementById('p').value)||0;document.getElementById('t').textContent=(q*p).toLocaleString('tr-TR',{minimumFractionDigits:2,maximumFractionDigits:2})+' ₺';}c();</script>
<?php botx(); ?>
