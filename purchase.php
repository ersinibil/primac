<?php
require_once __DIR__.'/boot.php';
require_login();
require_once __DIR__.'/stock_lib.php';

$pdo=db();
$ok=''; $er='';
$products=$pdo->query("SELECT id,name,unit,purchase_price,vat_rate FROM stock_items WHERE COALESCE(active,1)=1 ORDER BY name")->fetchAll();

// POST: Satın alma giriş — bir tedarikçiden TEK seferde BİRDEN FAZLA ürün satırı (sepet)
// eklenebilir (2026-07-03: sales.php ile aynı mantık, tutarlılık için). Her satırın kendi
// KDV oranı vardır; stok maliyeti NET (KDV hariç) tutar üzerinden hesaplanır, sadece nakit
// çıkışı (finance_movements) KDV dahil gerçek tutarı yansıtır.
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['action']) && $_POST['action']==='add_purchase'){
    try{
        $supplier=(int)$_POST['contact_id'];
        $pm=$_POST['payment_method'] ?? 'Veresiye';
        $pnames=$_POST['product_name'] ?? [];
        $qtys=$_POST['quantity'] ?? [];
        $units=$_POST['unit'] ?? [];
        $prices=$_POST['unit_price'] ?? [];
        $vatRates=$_POST['vat_rate'] ?? [];

        if(!$supplier) throw new Exception('Tedarikçi seçin.');
        if(!is_array($pnames) || !count($pnames)) throw new Exception('En az bir ürün satırı ekleyin.');

        // Sepetteki BÜTÜN satırlar + finansal kayıt tek transaction içinde (2026-07-03: sales.php
        // ile aynı gerekçe — bir satır başarısız olursa önceki satırların stoku kalıcı sapmasın).
        $totalNet=0; $totalVat=0; $descParts=[]; $firstItemId=null;
        $pdo->beginTransaction();
        try{
            foreach($pnames as $i=>$pname){
                $pname=trim($pname);
                $qty=(float)($qtys[$i] ?? 0);
                $unit=trim($units[$i] ?? '') ?: 'adet';
                $price=(float)($prices[$i] ?? 0);
                $vatRate=(float)($vatRates[$i] ?? 0);
                if($pname==='' || $qty<=0) continue;

                $stockResult=stock_add_purchase($pdo, $supplier, $pname, $qty, $price, $pm, $unit, null);
                if(!$stockResult['ok']) throw new Exception($stockResult['message']);
                if($firstItemId===null) $firstItemId=$stockResult['item_id'];

                $totalNet += $stockResult['total'];
                $totalVat += $vatRate>0 ? round($stockResult['total']*$vatRate/100,2) : 0;
                $descParts[] = $pname.' x'.rtrim(rtrim(number_format($qty,2,'.',''),'0'),'.');
            }
            if($firstItemId===null) throw new Exception('En az bir geçerli ürün satırı ekleyin.');

            // Finansal kaydı yap — TÜM sepetin toplamı (KDV dahil gerçek ödeme) tek harekette
            $avgVatRate = $totalNet>0 ? round($totalVat/$totalNet*100,2) : 0;
            $finResult=stock_add_purchase_finance($pdo, $supplier, $totalNet, $pm, $firstItemId, implode(', ',$descParts), $avgVatRate);

            $pdo->commit();
        }catch(Throwable $e){
            $pdo->rollBack();
            throw $e;
        }

        // Aktivite loğu
        try{
            if(function_exists('activity_log')){
                $sn=$pdo->prepare("SELECT name FROM contacts WHERE id=?");
                $sn->execute([$supplier]);
                $sname=$sn->fetch()['name']??'';
                activity_log('Satın Alma','Alış',$sname.' · '.implode(', ',$descParts).' '.mm($finResult['total']),$pm,'purchase',$firstItemId,'purchase.php','🛒');
            }
        }catch(Throwable $e){}

        $ok=implode(', ',$descParts).' alındı: '.mm($finResult['total']).($finResult['vat_amount']>0?' (KDV: '.mm($finResult['vat_amount']).')':'').' ('.$pm.')';
    }catch(Throwable $e){
        $er=$e->getMessage();
    }

    // Sonra sayfayı yenile (PRG deseni) — mesaj session'a yazılır (2026-07-03 düzeltmesi: eskiden
    // $er/$ok redirect'te hiç taşınmıyordu, hata olduğunda kullanıcı sebebini asla göremiyordu).
    if($ok) $_SESSION['purchase_ok']=$ok; else if($er) $_SESSION['purchase_er']=$er;
    header('Location: purchase.php');
    exit;
}

if(!empty($_SESSION['purchase_ok'])){ $ok=$_SESSION['purchase_ok']; unset($_SESSION['purchase_ok']); }
if(!empty($_SESSION['purchase_er'])){ $er=$_SESSION['purchase_er']; unset($_SESSION['purchase_er']); }

require_once __DIR__.'/layout_top.php';
?>
<style>
.notice{background:#dcfce7;color:#14532d;padding:12px 16px;border-radius:10px;margin:14px 0;font-size:14px}
</style>
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
<form method="post" id="purchForm">
  <input type="hidden" name="action" value="add_purchase">

  <div class="form-grid">
    <div class="full">
      <label>Tedarikçi</label>
      <select name="contact_id" id="contactSel" class="full" required onchange="onSupplierChange()">
        <option value="">— Seç —</option>
        <?php
        try{
          $cs=$pdo->query("SELECT id,name FROM contacts WHERE type IN ('Tedarikçi','Her İkisi') OR type IS NULL ORDER BY name")->fetchAll();
          if(!$cs) $cs=$pdo->query("SELECT id,name FROM contacts ORDER BY name")->fetchAll();
          foreach($cs as $c): ?><option value="<?=$c['id']?>"><?=htmlspecialchars($c['name'])?></option><?php endforeach;
        }catch(Throwable $e){}
        ?>
        <option value="__new__">➕ Listede yok — Yeni Tedarikçi Ekle…</option>
      </select>
      <div id="newContactBox" class="full" style="display:none;background:#eef4ff;border:1px solid #bfdbfe;border-radius:12px;padding:12px;margin-top:8px">
        <input type="text" id="contactNamePurch" placeholder="Tedarikçi adı" style="width:100%;border:1px solid #d0d5dd;border-radius:10px;padding:10px;margin-bottom:8px">
        <button type="button" class="btn" style="width:100%" onclick="quickAddContactPurch(document.getElementById('contactNamePurch').value, 'Tedarikçi')">✓ Ekle ve Seç</button>
      </div>
    </div>

    <div class="full">
      <label>Ödeme</label>
      <select name="payment_method">
        <option>Veresiye</option><option>Peşin</option><option>Banka</option><option>Kredi Kartı</option><option>Çek</option><option>Senet</option>
      </select>
    </div>
  </div><!-- /form-grid -->

  <datalist id="prods">
  <?php foreach($products as $p): ?><option value="<?=htmlspecialchars($p['name'])?>"></option><?php endforeach; ?>
  </datalist>
  <datalist id="vatPresets">
    <option value="0"></option>
    <option value="1"></option>
    <option value="8"></option>
    <option value="10"></option>
    <option value="20"></option>
  </datalist>

  <div class="full" style="margin-top:14px">
    <label style="font-weight:800">Ürünler <small class="muted" style="font-weight:400">(yazınca listede yoksa otomatik yeni ürün olarak eklenir)</small></label>
    <div style="overflow:auto">
    <table class="purch-items-tbl" style="width:100%;border-collapse:collapse">
      <thead>
        <tr style="text-align:left;font-size:12px;color:#667085">
          <th style="padding:4px 6px">Ürün</th>
          <th style="padding:4px 6px;width:90px">Miktar</th>
          <th style="padding:4px 6px;width:90px">Birim</th>
          <th style="padding:4px 6px;width:120px">Birim Alış Fiyatı</th>
          <th style="padding:4px 6px;width:80px">KDV %</th>
          <th style="padding:4px 6px;width:110px;text-align:right">Ara Toplam</th>
          <th style="width:36px"></th>
        </tr>
      </thead>
      <tbody id="itemsBody"></tbody>
    </table>
    </div>
    <button type="button" class="btn secondary small" style="margin-top:8px" onclick="addItemRow()">➕ Satır Ekle</button>
  </div>

  <div class="panel" style="background:rgba(37,99,235,.18);margin:16px 0;padding:16px 18px">
    <div style="display:flex;justify-content:space-between;padding:3px 0"><span class="muted">Ara Toplam (KDV Hariç)</span><b id="purchSubtotal">0,00 ₺</b></div>
    <div style="display:flex;justify-content:space-between;padding:3px 0"><span class="muted">KDV</span><b id="purchVat">0,00 ₺</b></div>
    <div style="display:flex;justify-content:space-between;padding:8px 0 0;border-top:1px solid rgba(37,99,235,.3);margin-top:6px">
      <span style="font-size:15px;font-weight:800">Genel Toplam</span>
      <span id="purchTotal" style="font-size:26px;font-weight:900">0,00 ₺</span>
    </div>
  </div>

  <button type="submit" class="btn dark" style="width:100%;padding:14px;font-size:16px">🛒 Satın Almayı Kaydet</button>
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
var PRODUCTS = <?= json_encode(array_map(function($p){
    return ['name'=>$p['name'],'unit'=>$p['unit'],'price'=>$p['purchase_price'],'vat'=>$p['vat_rate']!==null?$p['vat_rate']:20];
}, $products), JSON_UNESCAPED_UNICODE) ?>;
var rowIndex = 0;

function addItemRow(){
    var idx = rowIndex++;
    var tr = document.createElement('tr');
    tr.dataset.idx = idx;
    tr.innerHTML =
        '<td style="padding:4px 6px"><input type="text" name="product_name[]" list="prods" class="row-name" required placeholder="Ürün adı" oninput="onRowNameInput(this)" style="width:100%"></td>'
        + '<td style="padding:4px 6px"><input type="number" step="0.01" min="0.01" name="quantity[]" class="row-qty" value="1" required oninput="calcAll()" style="width:80px"></td>'
        + '<td style="padding:4px 6px"><input type="text" name="unit[]" class="row-unit" value="adet" style="width:80px"></td>'
        + '<td style="padding:4px 6px"><input type="number" step="0.01" min="0" name="unit_price[]" class="row-price" required oninput="calcAll()" style="width:110px"></td>'
        + '<td style="padding:4px 6px"><input type="text" inputmode="decimal" list="vatPresets" name="vat_rate[]" class="row-vat" value="20" oninput="calcAll()" style="width:70px"></td>'
        + '<td class="row-sub" style="padding:4px 6px;text-align:right;font-weight:800">0,00 ₺</td>'
        + '<td style="padding:4px 6px"><button type="button" class="btn danger small" onclick="removeRow(this)">🗑</button></td>';
    document.getElementById('itemsBody').appendChild(tr);
    calcAll();
}

function removeRow(btn){
    var tr = btn.closest('tr');
    var rows = document.querySelectorAll('#itemsBody tr');
    if(rows.length <= 1){
        tr.querySelector('.row-name').value = '';
        tr.querySelector('.row-qty').value = 1;
        tr.querySelector('.row-unit').value = 'adet';
        tr.querySelector('.row-price').value = '';
        tr.querySelector('.row-vat').value = 20;
    } else {
        tr.remove();
    }
    calcAll();
}

// Ürün adı yazılırken mevcut bir stok kartıyla eşleşirse fiyat/birim/KDV otomatik doldurulur
// (datalist seçimi native "change" olayında dataset taşımadığı için isimden eşleştiriyoruz).
function onRowNameInput(input){
    var tr = input.closest('tr');
    var match = PRODUCTS.find(function(p){ return p.name === input.value; });
    if(match){
        tr.querySelector('.row-unit').value = match.unit || 'adet';
        if(!tr.querySelector('.row-price').value) tr.querySelector('.row-price').value = match.price || '';
        tr.querySelector('.row-vat').value = match.vat || 20;
    }
    calcAll();
}

function calcAll(){
    var subtotalAll = 0, vatAll = 0;
    document.querySelectorAll('#itemsBody tr').forEach(function(tr){
        var q = parseFloat(tr.querySelector('.row-qty').value) || 0;
        var p = parseFloat(tr.querySelector('.row-price').value) || 0;
        var v = parseFloat(tr.querySelector('.row-vat').value) || 0;
        var sub = q * p;
        var vatAmt = sub * v / 100;
        tr.querySelector('.row-sub').textContent = (sub + vatAmt).toLocaleString('tr-TR', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ' ₺';
        subtotalAll += sub;
        vatAll += vatAmt;
    });
    document.getElementById('purchSubtotal').textContent = subtotalAll.toLocaleString('tr-TR', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ' ₺';
    document.getElementById('purchVat').textContent = vatAll.toLocaleString('tr-TR', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ' ₺';
    document.getElementById('purchTotal').textContent = (subtotalAll + vatAll).toLocaleString('tr-TR', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ' ₺';
}

addItemRow();

// Dropdown'da "Listede yok — Yeni Ekle" seçilince kutuyu aç (2026-07-03 kullanıcı isteği)
function onSupplierChange(){
    var sel=document.getElementById('contactSel');
    var box=document.getElementById('newContactBox');
    if(sel.value==='__new__'){ box.style.display='block'; sel.value=''; document.getElementById('contactNamePurch').focus(); }
    else box.style.display='none';
}
function quickAddContactPurch(name, type) {
    if (!name) return;
    const fd = new FormData();
    fd.append('t', 'contact');
    fd.append('name', name);
    fd.append('contact_type', type || 'Tedarikçi');

    fetch('ajax_quick_add.php', {method: 'POST', body: fd})
        .then(r => r.json())
        .then(data => {
            if (data.ok) {
                const sel = document.getElementById('contactSel');
                const opt = document.createElement('option');
                opt.value = data.id;
                opt.textContent = data.name;
                opt.selected = true;
                sel.insertBefore(opt, sel.querySelector('option[value="__new__"]'));
                document.getElementById('contactNamePurch').value = '';
                document.getElementById('newContactBox').style.display='none';
            } else {
                alert('Hata: ' + data.message);
            }
        })
        .catch(e => alert('Bağlantı hatası: ' + e));
}
</script>

<?php require_once __DIR__.'/layout_bottom.php'; ?>