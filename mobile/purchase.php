<?php
require_once 'common.php';
require_once '../stock_lib.php';
$pdo=db();
$ok=''; $er='';
$products=$pdo->query("SELECT id,name,unit,purchase_price,vat_rate FROM stock_items WHERE COALESCE(active,1)=1 ORDER BY name")->fetchAll();

// POST: Satın alma giriş (PRG deseni — topx'ten ÖNCE işle). Bir tedarikçiden TEK seferde
// BİRDEN FAZLA ürün satırı (sepet) eklenebilir (2026-07-03: web ile aynı mantık — bkz. ../purchase.php).
if($_SERVER['REQUEST_METHOD']==='POST'){
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

        // Sepetteki BÜTÜN satırlar + finansal kayıt tek transaction içinde (2026-07-03: web
        // purchase.php ile aynı gerekçe — bir satır başarısız olursa öncekiler kalıcı sapmasın).
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
                activity_log('Satın Alma','Alış',$sname.' · '.implode(', ',$descParts).' '.mm($finResult['total']),$pm,'purchase',$firstItemId,'mobile/purchase.php','🛒');
            }
        }catch(Throwable $e){}

        $ok=implode(', ',$descParts).' alındı: '.mm($finResult['total']).($finResult['vat_amount']>0?' (KDV: '.mm($finResult['vat_amount']).')':'').' ('.$pm.')';
    }catch(Throwable $e){
        $er=$e->getMessage();
    }

    // Session'a yaz + yönlendir (2026-07-03 düzeltmesi: eskiden $er redirect'te hiç taşınmıyordu)
    if($ok) $_SESSION['purchase_ok']=$ok; else if($er) $_SESSION['purchase_er']=$er;
    header('Location: purchase.php');
    exit;
}

if(!empty($_SESSION['purchase_ok'])){ $ok=$_SESSION['purchase_ok']; unset($_SESSION['purchase_ok']); }
if(!empty($_SESSION['purchase_er'])){ $er=$_SESSION['purchase_er']; unset($_SESSION['purchase_er']); }

topx('Satın Alma');
$cs=$pdo->query("SELECT id,name FROM contacts WHERE type IN ('Tedarikçi','Her İkisi') OR type IS NULL ORDER BY name")->fetchAll();
if(!$cs) $cs=$pdo->query("SELECT id,name FROM contacts ORDER BY name")->fetchAll();
?>
<?php if($ok): ?><div class="notice"><?=htmlspecialchars($ok)?></div><?php endif; ?>
<?php if($er): ?><div class="err"><?=htmlspecialchars($er)?></div><?php endif; ?>
<div class="panel">
<form method="post" id="purchForm">
  <label>Tedarikçi</label>
  <select name="contact_id" id="contactSel" required onchange="onSupplierChange()">
    <option value="">— Seç —</option>
    <?php foreach($cs as $c): ?><option value="<?=$c['id']?>"><?=htmlspecialchars($c['name'])?></option><?php endforeach; ?>
    <option value="__new__">➕ Listede yok — Yeni Tedarikçi Ekle…</option>
  </select>
  <div id="newContactBox" style="display:none;background:rgba(37,99,235,.12);border-radius:12px;padding:10px;margin:6px 0 12px">
    <input type="text" id="qcName" placeholder="Tedarikçi adı">
    <button type="button" class="btn dark" style="width:100%" onclick="quickContactMob(document.getElementById('qcName').value, 'Tedarikçi')">✓ Ekle ve Seç</button>
  </div>

  <label>Ödeme</label>
  <select name="payment_method"><option>Veresiye</option><option>Peşin</option><option>Banka</option><option>Kredi Kartı</option><option>Çek</option><option>Senet</option></select>

  <datalist id="prods"><?php foreach($products as $p): ?><option value="<?=htmlspecialchars($p['name'])?>"><?php endforeach; ?></datalist>
  <datalist id="vatPresets">
    <option value="0"></option>
    <option value="1"></option>
    <option value="8"></option>
    <option value="10"></option>
    <option value="20"></option>
  </datalist>

  <label style="margin-top:10px;font-weight:800">Ürünler <small class="muted">(yoksa otomatik yeni ürün eklenir)</small></label>
  <div id="itemsBody"></div>
  <button type="button" class="btn" style="width:100%;margin:8px 0;background:rgba(37,99,235,.15)" onclick="addItemRow()">➕ Satır Ekle</button>

  <div class="panel" style="background:rgba(37,99,235,.18);margin:14px 0;padding:12px 14px">
    <div style="display:flex;justify-content:space-between;padding:2px 0"><small class="muted">Ara Toplam</small><b id="purchSubtotal">0,00 ₺</b></div>
    <div style="display:flex;justify-content:space-between;padding:2px 0"><small class="muted">KDV</small><b id="purchVat">0,00 ₺</b></div>
    <div style="display:flex;justify-content:space-between;padding:6px 0 0;border-top:1px solid rgba(255,255,255,.15);margin-top:4px">
      <span style="font-weight:800">Genel Toplam</span><span id="t" style="font-size:24px;font-weight:900">0,00 ₺</span>
    </div>
  </div>

  <button class="btn dark" style="width:100%;padding:14px">🛒 Alışı Kaydet</button>
</form>
</div>
<script>
var PRODUCTS = <?= json_encode(array_map(function($p){
    return ['name'=>$p['name'],'unit'=>$p['unit'],'price'=>$p['purchase_price'],'vat'=>$p['vat_rate']!==null?$p['vat_rate']:20];
}, $products), JSON_UNESCAPED_UNICODE) ?>;
var rowIndex = 0;

function addItemRow(){
    var idx = rowIndex++;
    var row = document.createElement('div');
    row.className = 'panel';
    row.style.cssText = 'margin-bottom:10px;padding:10px';
    row.dataset.idx = idx;
    row.innerHTML =
        '<input type="text" name="product_name[]" list="prods" class="row-name" placeholder="Ürün adı" oninput="onRowNameInput(this)" style="margin-bottom:6px">'
        + '<div style="display:flex;gap:8px">'
        + '<div style="flex:1"><small class="muted">Miktar</small><input type="number" step="0.01" min="0.01" name="quantity[]" class="row-qty" value="1" oninput="calcAll()"></div>'
        + '<div style="flex:1"><small class="muted">Birim</small><input type="text" name="unit[]" class="row-unit" value="adet"></div>'
        + '</div>'
        + '<div style="display:flex;gap:8px;margin-top:6px">'
        + '<div style="flex:1"><small class="muted">Birim Alış Fiyatı</small><input type="number" step="0.01" min="0" name="unit_price[]" class="row-price" oninput="calcAll()"></div>'
        + '<div style="flex:1"><small class="muted">KDV %</small><input type="text" inputmode="decimal" list="vatPresets" name="vat_rate[]" class="row-vat" value="20" oninput="calcAll()"></div>'
        + '</div>'
        + '<div style="display:flex;justify-content:space-between;align-items:center;margin-top:6px">'
        + '<span class="row-sub" style="font-weight:800">0,00 ₺</span>'
        + '<button type="button" class="btn" style="background:rgba(220,38,38,.2)" onclick="removeRow(this)">🗑 Satırı Sil</button>'
        + '</div>';
    document.getElementById('itemsBody').appendChild(row);
    calcAll();
}

function removeRow(btn){
    var row = btn.closest('.panel');
    var rows = document.querySelectorAll('#itemsBody > .panel');
    if(rows.length <= 1){
        row.querySelector('.row-name').value = '';
        row.querySelector('.row-qty').value = 1;
        row.querySelector('.row-unit').value = 'adet';
        row.querySelector('.row-price').value = '';
        row.querySelector('.row-vat').value = 20;
    } else {
        row.remove();
    }
    calcAll();
}

function onRowNameInput(input){
    var row = input.closest('.panel');
    var match = PRODUCTS.find(function(p){ return p.name === input.value; });
    if(match){
        row.querySelector('.row-unit').value = match.unit || 'adet';
        if(!row.querySelector('.row-price').value) row.querySelector('.row-price').value = match.price || '';
        row.querySelector('.row-vat').value = match.vat || 20;
    }
    calcAll();
}

function calcAll(){
    var subtotalAll = 0, vatAll = 0;
    document.querySelectorAll('#itemsBody > .panel').forEach(function(row){
        var q = parseFloat(row.querySelector('.row-qty').value) || 0;
        var p = parseFloat(row.querySelector('.row-price').value) || 0;
        var v = parseFloat(row.querySelector('.row-vat').value) || 0;
        var sub = q * p;
        var vatAmt = sub * v / 100;
        row.querySelector('.row-sub').textContent = (sub + vatAmt).toLocaleString('tr-TR', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ' ₺';
        subtotalAll += sub;
        vatAll += vatAmt;
    });
    document.getElementById('purchSubtotal').textContent = subtotalAll.toLocaleString('tr-TR', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ' ₺';
    document.getElementById('purchVat').textContent = vatAll.toLocaleString('tr-TR', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ' ₺';
    document.getElementById('t').textContent = (subtotalAll + vatAll).toLocaleString('tr-TR', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ' ₺';
}

addItemRow();

// Dropdown'da "Listede yok — Yeni Ekle" seçilince kutuyu aç (2026-07-03 kullanıcı isteği)
function onSupplierChange(){
    var sel=document.getElementById('contactSel');
    var box=document.getElementById('newContactBox');
    if(sel.value==='__new__'){ box.style.display='block'; sel.value=''; document.getElementById('qcName').focus(); }
    else box.style.display='none';
}
function quickContactMob(name, type) {
    if (!name) { alert('Ad girin'); return; }
    const fd = new FormData();
    fd.append('t', 'contact');
    fd.append('name', name);
    fd.append('contact_type', type || 'Tedarikçi');
    fetch('../ajax_quick_add.php', {method: 'POST', body: fd})
        .then(r => r.json())
        .then(data => {
            if (data.ok) {
                const sel = document.getElementById('contactSel');
                const opt = document.createElement('option');
                opt.value = data.id;
                opt.textContent = data.name;
                opt.selected = true;
                sel.insertBefore(opt, sel.querySelector('option[value="__new__"]'));
                document.getElementById('qcName').value = '';
                document.getElementById('newContactBox').style.display='none';
            } else alert('Hata: ' + data.message);
        })
        .catch(e => alert('Hata: ' + e));
}
</script>
<?php botx(); ?>
