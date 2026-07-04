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
        $itemIds=$_POST['stock_item_id'] ?? [];
        $qtys=$_POST['quantity'] ?? [];
        $units=$_POST['unit'] ?? [];
        $prices=$_POST['unit_price'] ?? [];
        $vatRates=$_POST['vat_rate'] ?? [];

        if(!$supplier) throw new Exception('Tedarikçi seçin.');
        if(!is_array($itemIds) || !count($itemIds)) throw new Exception('En az bir ürün satırı ekleyin.');

        // Sepetteki BÜTÜN satırlar + finansal kayıt tek transaction içinde (2026-07-03: web
        // purchase.php ile aynı gerekçe — bir satır başarısız olursa öncekiler kalıcı sapmasın).
        // 2026-07-04: satır artık serbest metin değil, seçili stok kartı id'si (bkz. "Yeni Ürün
        // Ekle" AJAX akışı, ../purchase.php ile aynı mantık) — isim id üzerinden çözülür.
        $totalNet=0; $totalVat=0; $descParts=[]; $firstItemId=null;
        $pdo->beginTransaction();
        try{
            $nameStmt=$pdo->prepare("SELECT name FROM stock_items WHERE id=?");
            foreach($itemIds as $i=>$pid){
                $pid=(int)$pid;
                $qty=(float)($qtys[$i] ?? 0);
                $unit=trim($units[$i] ?? '') ?: 'adet';
                $price=(float)($prices[$i] ?? 0);
                $vatRate=(float)($vatRates[$i] ?? 0);
                if(!$pid || $qty<=0) continue;

                $nameStmt->execute([$pid]);
                $pname=$nameStmt->fetchColumn();
                if($pname===false) continue; // seçilen ürün bulunamadı, satır atlanır

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

  <datalist id="vatPresets">
    <option value="0"></option>
    <option value="1"></option>
    <option value="8"></option>
    <option value="10"></option>
    <option value="20"></option>
  </datalist>

  <label style="margin-top:10px;font-weight:800">Ürünler <small class="muted">(listede yoksa "Yeni Ürün Ekle" seçeneğini kullanın)</small></label>
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
    return ['id'=>(int)$p['id'],'name'=>$p['name'],'unit'=>$p['unit'],'price'=>$p['purchase_price'],'vat'=>$p['vat_rate']!==null?$p['vat_rate']:20];
}, $products), JSON_UNESCAPED_UNICODE) ?>;
var rowIndex = 0;

function escPurchM(s){ return String(s).replace(/[&<>"']/g, function(c){ return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]; }); }

// Ürün seçim listesi (2026-07-04: serbest metin+datalist yerine web ile aynı select+"Yeni
// Ürün Ekle" deseni — bkz. ../purchase.php).
function productOptionsHtmlPurchM(){
    var html = '<option value="">— Seç —</option>';
    PRODUCTS.forEach(function(p){
        html += '<option value="'+p.id+'" data-price="'+(p.price||0)+'" data-vat="'+(p.vat||0)+'" data-unit="'+escPurchM(p.unit||'adet')+'">'
            + escPurchM(p.name)+'</option>';
    });
    html += '<option value="__new__">➕ Listede yok — Yeni Ürün Ekle…</option>';
    return html;
}

function addItemRow(){
    var idx = rowIndex++;
    var row = document.createElement('div');
    row.className = 'panel';
    row.style.cssText = 'margin-bottom:10px;padding:10px';
    row.dataset.idx = idx;
    row.innerHTML =
        '<select name="stock_item_id[]" class="row-prod" style="margin-bottom:6px" onchange="onRowProductChange(this)">'+productOptionsHtmlPurchM()+'</select>'
        + '<div class="new-prod-box" style="display:none;background:rgba(37,99,235,.12);border-radius:10px;padding:8px;margin-bottom:6px">'
        + '<input type="text" class="np-name" placeholder="Ürün adı" style="margin-bottom:6px">'
        + '<button type="button" class="btn dark" style="width:100%" onclick="quickAddProductRow(this)">✓ Ekle ve Seç</button>'
        + '</div>'
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
        row.querySelector('.row-prod').value = '';
        row.querySelector('.row-qty').value = 1;
        row.querySelector('.row-unit').value = 'adet';
        row.querySelector('.row-price').value = '';
        row.querySelector('.row-vat').value = 20;
        row.querySelector('.new-prod-box').style.display = 'none';
    } else {
        row.remove();
    }
    calcAll();
}

// Var olan bir ürün seçilince birim/fiyat/KDV otomatik dolar; "__new__" seçilince kutu açılır
// (sayfa yenilenmeden AJAX ile ekleme — bkz. quickAddProductRow).
function onRowProductChange(sel){
    var row = sel.closest('.panel');
    var box = row.querySelector('.new-prod-box');
    if(sel.value === '__new__'){
        box.style.display = 'block';
        sel.value = '';
        row.querySelector('.np-name').focus();
        return;
    }
    box.style.display = 'none';
    var opt = sel.selectedOptions[0];
    if(opt && opt.dataset.price !== undefined){
        row.querySelector('.row-unit').value = opt.dataset.unit || 'adet';
        if(!row.querySelector('.row-price').value) row.querySelector('.row-price').value = opt.dataset.price;
        row.querySelector('.row-vat').value = opt.dataset.vat || 20;
    }
    calcAll();
}

// "➕ Yeni Ürün Ekle…" kutusundaki "Ekle ve Seç" — SADECE ürün adı ile stock_items'a hemen
// kaydeder (detaylar sonra ürün kartından tamamlanır) ve satırda otomatik seçili hale getirir,
// sayfa yenilenmez (2026-07-04 kullanıcı isteği).
function quickAddProductRow(btn){
    var row = btn.closest('.panel');
    var name = row.querySelector('.np-name').value.trim();
    if(!name){ alert('Ürün adı girin'); return; }
    var fd = new FormData();
    fd.append('t', 'product');
    fd.append('name', name);
    fetch('../ajax_quick_add.php', {method: 'POST', body: fd})
        .then(r => r.json())
        .then(data => {
            if(data.ok){
                PRODUCTS.push({id: data.id, name: data.name, unit: 'adet', price: 0, vat: 20});
                document.querySelectorAll('.row-prod').forEach(function(sel){
                    var o = document.createElement('option');
                    o.value = data.id; o.dataset.price = 0; o.dataset.vat = 20; o.dataset.unit = 'adet';
                    o.textContent = data.name;
                    sel.insertBefore(o, sel.querySelector('option[value="__new__"]'));
                });
                var sel = row.querySelector('.row-prod');
                sel.value = data.id;
                onRowProductChange(sel);
                row.querySelector('.np-name').value = '';
                row.querySelector('.new-prod-box').style.display = 'none';
            } else {
                alert('Hata: ' + data.message);
            }
        })
        .catch(e => alert('Bağlantı hatası: ' + e));
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
