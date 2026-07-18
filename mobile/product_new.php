<?php
require_once 'common.php';
$pdo=db(); $ok=''; $er='';
if($_SERVER['REQUEST_METHOD']==='POST'){
    try{
        $name=trim($_POST['name'] ?? '');
        if($name==='') throw new Exception('Ürün adı girin.');
        $code=trim($_POST['product_code'] ?? '') ?: ('URN-'.date('ymd').'-'.random_int(100,999));
        $pdo->prepare("INSERT INTO stock_items(product_code,name,unit,quantity,critical_level,purchase_price,sale_price,avg_cost,active)
            VALUES(?,?,?,?,?,?,?,?,1)")
            ->execute([$code,$name,trim($_POST['unit'] ?? 'adet'),(float)($_POST['quantity']??0),(float)($_POST['critical_level']??0),
            (float)($_POST['purchase_price']??0),(float)($_POST['sale_price']??0),(float)($_POST['purchase_price']??0)]);
        $newPid=(int)$pdo->lastInsertId();
        try{ if(function_exists('activity_log')) activity_log('Stok','Yeni Ürün',$name,'','product',$newPid,'product_view.php?id='.$newPid,'📦'); }catch(Throwable $e){}
        $ok=$name.' eklendi.';
    }catch(Throwable $e){ $er=$e->getMessage(); }
}
topx('Yeni Ürün');
?>
<?php if($ok): ?><?=ds_alert('success',$ok)?><?php endif; ?>
<?php if($er): ?><?=ds_alert('danger',$er)?><?php endif; ?>
<div class="df-panel">
<form method="post">
  <label>Ürün Adı</label><input name="name" required>
  <label>Ürün Kodu (boş = otomatik)</label><input name="product_code">
  <div style="display:flex;gap:10px">
    <div style="flex:1"><label>Birim</label><input name="unit" value="adet"></div>
    <div style="flex:1"><label>Başlangıç Stok</label><input type="number" step="0.01" name="quantity" value="0"></div>
  </div>
  <div style="display:flex;gap:10px">
    <div style="flex:1"><label>Alış Fiyatı</label><input type="number" step="0.01" name="purchase_price" value="0"></div>
    <div style="flex:1"><label>Satış Fiyatı</label><input type="number" step="0.01" name="sale_price" value="0"></div>
  </div>
  <label>Kritik Seviye</label><input type="number" step="0.01" name="critical_level" value="0">
  <button class="df-btn df-btn--primary df-btn--lg" style="width:100%;margin-top:8px"><?=ds_icon('plus',16)?> Ürünü Kaydet</button>
</form>
</div>
<?php botx(); ?>
