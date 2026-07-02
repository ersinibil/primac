<?php
require_once __DIR__.'/boot.php';
require_login();
require_once __DIR__.'/stock_lib.php';

$pdo=db();
$ok=''; $er='';

// POST: Satın alma giriş
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['action']) && $_POST['action']==='add_purchase'){
    try{
        $supplier=(int)$_POST['contact_id'];
        $pname=trim($_POST['product_name'] ?? '');
        $qty=(float)$_POST['quantity'];
        $price=(float)$_POST['unit_price'];
        $pm=$_POST['payment_method'] ?? 'Veresiye';
        $unit=trim($_POST['unit'] ?? 'adet');
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
                activity_log('Satın Alma','Alış',$sname.' · '.$pname.' '.mm($stockResult['total']),$pm,'purchase',$stockResult['item_id'],'purchase.php','🛒');
            }
        }catch(Throwable $e){}

        $ok=$stockResult['message'];
    }catch(Throwable $e){
        $er=$e->getMessage();
    }

    // Sonra sayfayı yenile (PRG deseni)
    $redirectUrl='purchase.php';
    if($ok) $redirectUrl.='?ok=1';
    header('Location: '.$redirectUrl);
    exit;
}

// GET parametresinden başarı mesajı göster
if(isset($_GET['ok'])){
    $ok='Satın alma işlemi başarıyla kaydedildi.';
}

require_once __DIR__.'/layout_top.php';
?>
<div class="panel-head">
<h1>Satın Alma</h1>
</div>

<?php if($ok): ?>
<div class="notice"><?=htmlspecialchars($ok)?></div>
<?php endif; ?>
<?php if($er): ?>
<div class="alert"><?=htmlspecialchars($er)?></div>
<?php endif; ?>

<!-- Hızlı Satın Alma Formu -->
<section class="panel">
<h2 style="margin-top:0">Hızlı Satın Alma</h2>
<form method="post">
  <input type="hidden" name="action" value="add_purchase">

  <label>Tedarikçi</label>
  <select name="contact_id" required><option value="">— Seç —</option>
  <?php
  try{
    $cs=$pdo->query("SELECT id,name FROM contacts WHERE type IN ('Tedarikçi','Her İkisi') OR type IS NULL ORDER BY name")->fetchAll();
    if(!$cs) $cs=$pdo->query("SELECT id,name FROM contacts ORDER BY name")->fetchAll();
    foreach($cs as $c): ?><option value="<?=$c['id']?>"><?=htmlspecialchars($c['name'])?></option><?php endforeach;
  }catch(Throwable $e){}
  ?>
  </select>

  <label>Ürün (yoksa otomatik açılır)</label>
  <input type="text" name="product_name" list="prods" required placeholder="Ürün adı">
  <datalist id="prods">
  <?php
  try{
    $ps=$pdo->query("SELECT DISTINCT name FROM stock_items WHERE COALESCE(active,1)=1 ORDER BY name")->fetchAll();
    foreach($ps as $p): ?><option value="<?=htmlspecialchars($p['name'])?>"></option><?php endforeach;
  }catch(Throwable $e){}
  ?>
  </datalist>

  <div style="display:flex;gap:15px;margin-bottom:12px">
    <div style="flex:1">
      <label>Miktar</label>
      <input type="number" step="0.01" name="quantity" class="qty-inp" required value="1">
    </div>
    <div style="flex:1">
      <label>Birim</label>
      <input type="text" name="unit" placeholder="adet" value="adet">
    </div>
    <div style="flex:1">
      <label>Birim Alış Fiyatı</label>
      <input type="number" step="0.01" name="unit_price" class="price-inp" required>
    </div>
  </div>

  <label>Satış Fiyatı (yeni üründe)</label>
  <input type="number" step="0.01" name="sale_price" placeholder="opsiyonel">

  <label>Ödeme</label>
  <select name="payment_method"><option>Veresiye</option><option>Peşin</option><option>Banka</option><option>Kredi Kartı</option><option>Çek</option><option>Senet</option></select>

  <div style="background:rgba(37,99,235,.18);text-align:center;margin:12px 0;padding:12px;border-radius:6px">
    <small class="muted">Toplam</small>
    <div id="total-display" style="font-size:22px;font-weight:bold">0,00 ₺</div>
  </div>

  <button type="submit" class="btn dark" style="width:100%">🛒 Satın Almayı Kaydet</button>
</form>
</section>

<!-- Satın Alma İşleri -->
<section class="panel">
<h2 style="margin-top:0">Satın Alma İşleri</h2>
<table>
<thead>
<tr><th>İş No</th><th>Başlık</th><th>Termin</th><th>Durum</th></tr>
</thead>
<tbody>
<?php
try{
$rows=$pdo->query("SELECT * FROM jobs WHERE job_type IN ('satin_alma') ORDER BY id DESC")->fetchAll();
foreach($rows as $r){
echo "<tr>
<td><a href='job_view.php?id=".h($r['id'])."'>".h($r['job_no'])."</a></td>
<td>".h($r['title'])."</td>
<td>".h($r['due_date'])."</td>
<td>".badge($r['status'],status_tone($r['status']))."</td>
</tr>";
}
if(!$rows) echo "<tr><td colspan='4' class='muted'>Henüz kayıt yok.</td></tr>";
}catch(Throwable $e){
echo "<tr><td colspan='4'><div class='alert'>".h($e->getMessage())."</div></td></tr>";
}
?>
</tbody>
</table>
</section>

<script>
// Hızlı hesaplama: toplam = miktar × fiyat
function updateTotal(){
  var qty=parseFloat(document.querySelector('.qty-inp').value)||0;
  var price=parseFloat(document.querySelector('.price-inp').value)||0;
  var total=qty*price;
  document.getElementById('total-display').textContent=total.toLocaleString('tr-TR',{minimumFractionDigits:2,maximumFractionDigits:2})+' ₺';
}
document.querySelectorAll('.qty-inp,.price-inp').forEach(function(el){
  el.addEventListener('input',updateTotal);
});
updateTotal();
</script>

<?php require_once __DIR__.'/layout_bottom.php'; ?>