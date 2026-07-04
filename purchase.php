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
        $itemIds=$_POST['stock_item_id'] ?? [];
        $qtys=$_POST['quantity'] ?? [];
        $units=$_POST['unit'] ?? [];
        $prices=$_POST['unit_price'] ?? [];
        $vatRates=$_POST['vat_rate'] ?? [];

        if(!$supplier) throw new Exception('Tedarikçi seçin.');
        if(!is_array($itemIds) || !count($itemIds)) throw new Exception('En az bir ürün satırı ekleyin.');

        // Sepetteki BÜTÜN satırlar + finansal kayıt tek transaction içinde (2026-07-03: sales.php
        // ile aynı gerekçe — bir satır başarısız olursa önceki satırların stoku kalıcı sapmasın).
        // 2026-07-04: satır artık serbest metin değil, seçili stok kartı id'si (bkz. "Yeni Ürün
        // Ekle" AJAX akışı) — isim id üzerinden çözülüp mevcut stock_add_purchase() aynen kullanılır.
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
                activity_log('Satın Alma','Alış',$sname.' · '.implode(', ',$descParts).' '.money($finResult['total']),$pm,'purchase',$firstItemId,'purchase.php','🛒');
            }
        }catch(Throwable $e){}

        $ok=implode(', ',$descParts).' alındı: '.money($finResult['total']).($finResult['vat_amount']>0?' (KDV: '.money($finResult['vat_amount']).')':'').' ('.$pm.')';
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

  <datalist id="vatPresets">
    <option value="0"></option>
    <option value="1"></option>
    <option value="8"></option>
    <option value="10"></option>
    <option value="20"></option>
  </datalist>

  <div class="full" style="margin-top:14px">
    <label style="font-weight:800">Ürünler <small class="muted" style="font-weight:400">(listede yoksa "➕ Yeni Ürün Ekle…" seçeneğini kullanın)</small></label>
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
    return ['id'=>(int)$p['id'],'name'=>$p['name'],'unit'=>$p['unit'],'price'=>$p['purchase_price'],'vat'=>$p['vat_rate']!==null?$p['vat_rate']:20];
}, $products), JSON_UNESCAPED_UNICODE) ?>;
var rowIndex = 0;

function escPurch(s){ return String(s).replace(/[&<>"']/g, function(c){ return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]; }); }

// Ürün seçim listesi (2026-07-04: serbest metin+datalist yerine sales.php ile aynı
// select+"Yeni Ürün Ekle" deseni — bkz. ../sales.php productOptionsHtml/quickAddProductRow).
function productOptionsHtmlPurch(){
    var html = '<option value="">— Seç —</option>';
    PRODUCTS.forEach(function(p){
        html += '<option value="'+p.id+'" data-price="'+(p.price||0)+'" data-vat="'+(p.vat||0)+'" data-unit="'+escPurch(p.unit||'adet')+'">'
            + escPurch(p.name)+'</option>';
    });
    html += '<option value="__new__">➕ Listede yok — Yeni Ürün Ekle…</option>';
    return html;
}

function addItemRow(){
    var idx = rowIndex++;
    var tr = document.createElement('tr');
    tr.dataset.idx = idx;
    tr.innerHTML =
        '<td style="padding:4px 6px">'
        + '<select name="stock_item_id[]" class="row-prod" required onchange="onRowProductChange(this)">'+productOptionsHtmlPurch()+'</select>'
        + '<div class="new-prod-box" style="display:none;background:#eef4ff;border:1px solid #bfdbfe;border-radius:10px;padding:8px;margin-top:6px">'
        + '<input type="text" class="np-name" placeholder="Ürün adı" style="width:100%;border:1px solid #d0d5dd;border-radius:8px;padding:6px;margin-bottom:6px">'
        + '<button type="button" class="btn small" style="width:100%" onclick="quickAddProductRow(this)">✓ Ekle ve Seç</button>'
        + '</div></td>'
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
        tr.querySelector('.row-prod').value = '';
        tr.querySelector('.row-qty').value = 1;
        tr.querySelector('.row-unit').value = 'adet';
        tr.querySelector('.row-price').value = '';
        tr.querySelector('.row-vat').value = 20;
        tr.querySelector('.new-prod-box').style.display = 'none';
    } else {
        tr.remove();
    }
    calcAll();
}

// Var olan bir ürün seçilince birim/fiyat/KDV otomatik dolar; "__new__" seçilince
// satır-içi kutu açılır (sayfa yenilenmeden AJAX ile ekleme — bkz. quickAddProductRow).
function onRowProductChange(sel){
    var tr = sel.closest('tr');
    var box = tr.querySelector('.new-prod-box');
    if(sel.value === '__new__'){
        box.style.display = 'block';
        sel.value = '';
        tr.querySelector('.np-name').focus();
        return;
    }
    box.style.display = 'none';
    var opt = sel.selectedOptions[0];
    if(opt && opt.dataset.price !== undefined){
        tr.querySelector('.row-unit').value = opt.dataset.unit || 'adet';
        if(!tr.querySelector('.row-price').value) tr.querySelector('.row-price').value = opt.dataset.price;
        tr.querySelector('.row-vat').value = opt.dataset.vat || 20;
    }
    calcAll();
}

// "➕ Yeni Ürün Ekle…" kutusundaki "Ekle ve Seç" — SADECE ürün adı ile stock_items'a hemen
// kaydeder (detaylar sonra ürün kartından tamamlanır) ve satırda otomatik seçili hale getirir,
// sayfa yenilenmez (2026-07-04 kullanıcı isteği).
function quickAddProductRow(btn){
    var tr = btn.closest('tr');
    var name = tr.querySelector('.np-name').value.trim();
    if(!name){ alert('Ürün adı girin'); return; }
    var fd = new FormData();
    fd.append('t', 'product');
    fd.append('name', name);
    fetch('ajax_quick_add.php', {method: 'POST', body: fd})
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
                var sel = tr.querySelector('.row-prod');
                sel.value = data.id;
                onRowProductChange(sel);
                tr.querySelector('.np-name').value = '';
                tr.querySelector('.new-prod-box').style.display = 'none';
            } else {
                alert('Hata: ' + data.message);
            }
        })
        .catch(e => alert('Bağlantı hatası: ' + e));
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