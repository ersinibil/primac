<?php
require_once 'common.php';
require_once __DIR__.'/../stock_lib.php';
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
        if(isset($_POST['delete_sale'])){
            // Satış silme (PRG: POST → işlem → redirect)
            if(!can_edit_delete()) throw new Exception('Silme için yetkiniz yok.');
            $saleId=(int)$_POST['id'];
            $res=stock_reverse_sale($pdo,$saleId);
            if($res['ok']){
                $_SESSION['sales_ok']=$res['message'];
            }else{
                $_SESSION['sales_er']=$res['message'];
            }
            redirect('sales.php');
        }else{
            // Yeni satış kaydı — bir cariye BİRDEN FAZLA ürün satırı (sepet) eklenebilir
            // (2026-07-03 kullanıcı isteği, web ile aynı mantık — bkz. ../sales.php).
            $contact=(int)$_POST['contact_id'];
            $method=$_POST['payment_method'] ?? 'Peşin';
            $ids=$_POST['stock_item_id'] ?? [];
            $qtys=$_POST['quantity'] ?? [];
            $prices=$_POST['unit_price'] ?? [];
            $vatRates=$_POST['vat_rate'] ?? [];

            if(!$contact) throw new Exception('Cari seçin.');
            if(!is_array($ids) || !count($ids)) throw new Exception('En az bir ürün satırı ekleyin.');

            $lines=[];
            foreach($ids as $i=>$pid){
                $pid=(int)$pid;
                $qty=(float)($qtys[$i] ?? 0);
                $price=(float)($prices[$i] ?? 0);
                $vatRate=(float)($vatRates[$i] ?? 0);
                if(!$pid || $qty<=0) continue;
                if($price<0) throw new Exception('Fiyat geçersiz.');

                $p=$pdo->prepare("SELECT * FROM stock_items WHERE id=?"); $p->execute([$pid]); $item=$p->fetch();
                if(!$item) continue;

                $subtotal=$qty*$price;
                $vatAmount=$vatRate>0?round($subtotal*$vatRate/100,2):0;
                $lineTotal=round($subtotal+$vatAmount,2);
                $cost=(float)($item['avg_cost'] ?: $item['purchase_price'] ?: 0);

                $lines[]=[
                    'item'=>$item,'qty'=>$qty,'price'=>$price,'vat_rate'=>$vatRate,
                    'vat_amount'=>$vatAmount,'line_total'=>$lineTotal,'profit'=>($price-$cost)*$qty,
                ];
            }
            if(!$lines) throw new Exception('En az bir geçerli ürün satırı ekleyin.');

            $grandTotal=0; $grandVat=0; $profitTotal=0; $descParts=[];
            foreach($lines as $l){
                $grandTotal+=$l['line_total'];
                $grandVat+=$l['vat_amount'];
                $profitTotal+=$l['profit'];
                $descParts[]=$l['item']['name'].' x'.qty_fmt($l['qty']);
            }
            $desc=implode(', ',$descParts).' satış';

            // Sepetteki BÜTÜN satırlar tek transaction içinde işlenir (2026-07-03 düzeltmesi —
            // web sales.php ile aynı gerekçe: çoklu ürüne geçilince yarım-işlem riski doğdu).
            $pdo->beginTransaction();
            try{
                // 1) Stok düş (her satır için)
                foreach($lines as $l){
                    $pdo->prepare("UPDATE stock_items SET quantity=quantity-? WHERE id=?")->execute([$l['qty'],$l['item']['id']]);
                }

                // 2) Kasa kaydı (finans hareketi) ÖNCE oluşturulur (sepetin TOPLAMI ile) — id'si
                // stok hareketlerine kesin referans olarak yazılacak.
                $accId=acc_for_method($pdo,$method);
                $pdo->prepare("INSERT INTO finance_movements(contact_id,direction,amount,vat_rate,vat_amount,payment_channel,account_id,status,movement_date,description,movement_type)
                    VALUES(?,?,?,?,?,?,?,?,?,?,'mobile_sale')")
                    ->execute([$contact,'in',$grandTotal,count($lines)===1?($lines[0]['vat_rate']?:null):null,$grandVat,$method,$accId,'Tahsil Edildi',date('Y-m-d'),$desc]);
                $financeMovementId=(int)$pdo->lastInsertId();

                // 3) Her satır için stok hareketi — hepsi aynı finance_movement_id ile
                foreach($lines as $l){
                    $pdo->prepare("INSERT INTO stock_movements(stock_item_id,finance_movement_id,direction,quantity,reason,note,created_at) VALUES(?,?,?,?,?,?,NOW())")
                        ->execute([$l['item']['id'],$financeMovementId,'out',$l['qty'],'Satış','Mobil satış ('.$method.')']);
                }
                // 4) Kasa bakiyesi güncelle (KDV dahil gerçek tutar)
                if($accId){ $pdo->prepare("UPDATE finance_accounts SET current_balance=current_balance+? WHERE id=?")->execute([$grandTotal,$accId]); }

                $pdo->commit();
            }catch(Throwable $e){
                $pdo->rollBack();
                throw $e;
            }

            // 5) Log
            try{ if(function_exists('activity_log')) activity_log('Satış','Mobil',$desc.' '.mm($grandTotal).' (kâr '.mm($profitTotal).')',$method,'sale',$lines[0]['item']['id'],'mobile/sales.php','🧾'); }catch(Throwable $e){}

            $kz = $profitTotal>=0 ? ('Kâr: '.mm($profitTotal)) : ('Zarar: '.mm(-$profitTotal));
            $ok=implode(', ',$descParts).' satıldı: '.mm($grandTotal).($grandVat>0?' (KDV: '.mm($grandVat).')':'').' ('.$method.') · '.$kz;
            $cid=$contact;
        }
    }catch(Throwable $e){ $er=$e->getMessage(); }
}

// Session'dan mesajları oku (PRG dari silme sonrası)
if(!empty($_SESSION['sales_ok'])){
    $ok=$_SESSION['sales_ok'];
    unset($_SESSION['sales_ok']);
}
if(!empty($_SESSION['sales_er'])){
    $er=$_SESSION['sales_er'];
    unset($_SESSION['sales_er']);
}

topx('Satış Yap');
?>
<?php if($ok): ?><div class="notice"><?=htmlspecialchars($ok)?></div><?php endif; ?>
<?php if($er): ?><div class="err"><?=htmlspecialchars($er)?></div><?php endif; ?>

<?php
$cs=$pdo->query("SELECT id,name FROM contacts ORDER BY name")->fetchAll();
$ps=$pdo->query("SELECT id,name,quantity,unit,sale_price,vat_rate FROM stock_items WHERE COALESCE(active,1)=1 ORDER BY name")->fetchAll();
?>

<div class="panel">
<form method="post">
  <label>Cari (Müşteri)</label>
  <select name="contact_id" id="contactSel" required onchange="onContactChange()">
    <option value="">— Seç —</option>
    <?php foreach($cs as $c): ?><option value="<?=$c['id']?>" <?=$cid===(int)$c['id']?'selected':''?>><?=htmlspecialchars($c['name'])?></option><?php endforeach; ?>
    <option value="__new__">➕ Listede yok — Yeni Cari Ekle…</option>
  </select>
  <div id="newContactBox" style="display:none;background:rgba(37,99,235,.12);border-radius:12px;padding:10px;margin:6px 0 12px">
    <input type="text" id="qsContactName" placeholder="Müşteri adı">
    <button type="button" class="btn dark" style="width:100%" onclick="quickContactSales(document.getElementById('qsContactName').value, 'Müşteri')">✓ Ekle ve Seç</button>
  </div>

  <label>Ödeme Yöntemi</label>
  <select name="payment_method" required>
    <option>Peşin</option>
    <option>Kredi Kartı</option>
    <option>Banka Havalesi</option>
  </select>

  <label style="margin-top:10px;font-weight:800">Ürünler</label>
  <div id="itemsBody"></div>
  <button type="button" class="btn" style="width:100%;margin:8px 0;background:rgba(37,99,235,.15)" onclick="addItemRow()">➕ Satır Ekle</button>

  <div class="panel" style="background:rgba(37,99,235,.18);margin:14px 0;padding:12px 14px">
    <div style="display:flex;justify-content:space-between;padding:2px 0"><small class="muted">Ara Toplam</small><b id="salesSubtotal">0,00 ₺</b></div>
    <div style="display:flex;justify-content:space-between;padding:2px 0"><small class="muted">KDV</small><b id="salesVat">0,00 ₺</b></div>
    <div style="display:flex;justify-content:space-between;padding:6px 0 0;border-top:1px solid rgba(255,255,255,.15);margin-top:4px">
      <span style="font-weight:800">Genel Toplam</span><span id="tot" style="font-size:24px;font-weight:900">0,00 ₺</span>
    </div>
  </div>

  <button class="btn dark" style="width:100%;padding:15px" type="submit">🧾 Satışı Tamamla</button>
</form>
</div>

<script>
var PRODUCTS = <?= json_encode(array_map(function($p){
    return ['id'=>(int)$p['id'],'name'=>$p['name'],'unit'=>$p['unit'],'quantity'=>$p['quantity'],'price'=>$p['sale_price'],'vat'=>$p['vat_rate']!==null?$p['vat_rate']:20];
}, $ps), JSON_UNESCAPED_UNICODE) ?>;
var rowIndex = 0;

function esc(s){ return String(s).replace(/[&<>"']/g, function(c){ return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]; }); }

function productOptionsHtml(){
    var html = '<option value="">— Seç —</option>';
    PRODUCTS.forEach(function(p){
        html += '<option value="'+p.id+'" data-price="'+(p.price||0)+'" data-vat="'+(p.vat||0)+'" data-unit="'+esc(p.unit||'')+'">'
            + esc(p.name)+' (Stok: '+(p.quantity||0)+' '+esc(p.unit||'')+')</option>';
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
        '<select class="row-prod" onchange="onRowProductChange(this)" style="margin-bottom:6px">'+productOptionsHtml()+'</select>'
        + '<div class="new-prod-box" style="display:none;background:rgba(37,99,235,.12);border-radius:10px;padding:8px;margin-bottom:6px">'
        + '<input type="text" class="np-name" placeholder="Ürün adı">'
        + '<input type="text" class="np-unit" placeholder="adet" value="adet">'
        + '<button type="button" class="btn dark" style="width:100%" onclick="quickAddProductRow(this)">✓ Ekle ve Seç</button>'
        + '</div>'
        + '<div style="display:flex;gap:8px">'
        + '<div style="flex:1"><small class="muted">Miktar</small><input type="number" step="0.01" min="0.01" class="row-qty" value="1" oninput="calcAll()"></div>'
        + '<div style="flex:1"><small class="muted">Birim Fiyat</small><input type="number" step="0.01" min="0" class="row-price" oninput="calcAll()"></div>'
        + '<div style="flex:1"><small class="muted">KDV %</small><input type="number" step="0.01" min="0" class="row-vat" value="20" oninput="calcAll()"></div>'
        + '</div>'
        + '<div style="display:flex;justify-content:space-between;align-items:center;margin-top:6px">'
        + '<span class="row-sub" style="font-weight:800">0,00 ₺</span>'
        + '<button type="button" class="btn" style="background:rgba(220,38,38,.2)" onclick="removeRow(this)">🗑 Satırı Sil</button>'
        + '</div>';
    document.getElementById('itemsBody').appendChild(row);
    syncHiddenInputs();
    calcAll();
}

function removeRow(btn){
    var row = btn.closest('.panel');
    var rows = document.querySelectorAll('#itemsBody > .panel');
    if(rows.length <= 1){
        row.querySelector('.row-prod').value = '';
        row.querySelector('.row-qty').value = 1;
        row.querySelector('.row-price').value = '';
        row.querySelector('.row-vat').value = 20;
    } else {
        row.remove();
    }
    syncHiddenInputs();
    calcAll();
}

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
        row.querySelector('.row-price').value = opt.dataset.price;
        row.querySelector('.row-vat').value = opt.dataset.vat || 20;
    }
    syncHiddenInputs();
    calcAll();
}

function quickAddProductRow(btn){
    var row = btn.closest('.panel');
    var name = row.querySelector('.np-name').value;
    var unit = row.querySelector('.np-unit').value || 'adet';
    if(!name){ alert('Ürün adı girin'); return; }
    var fd = new FormData();
    fd.append('t', 'product');
    fd.append('name', name);
    fd.append('unit', unit);
    fetch('../ajax_quick_add.php', {method: 'POST', body: fd})
        .then(r => r.json())
        .then(data => {
            if(data.ok){
                PRODUCTS.push({id: data.id, name: data.name, unit: unit, quantity: 0, price: 0, vat: 20});
                document.querySelectorAll('.row-prod').forEach(function(sel){
                    var o = document.createElement('option');
                    o.value = data.id; o.dataset.price = 0; o.dataset.vat = 20; o.dataset.unit = unit;
                    o.textContent = data.name + ' (Stok: 0 ' + unit + ')';
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

// Mobil ortak css'i (common.php) select/input'lara ad koymadan otomatik genişlik/stil veriyor;
// ancak POST'ta dizi (name="x[]") gerektiği için asıl input'lar formdan AYRI tutulup submit
// anında gizli hidden alanlar olarak senkronize edilir (satır DOM yapısı basit tutulsun diye).
function syncHiddenInputs(){
    var form = document.querySelector('form');
    form.querySelectorAll('.hidden-sync').forEach(function(h){ h.remove(); });
    document.querySelectorAll('#itemsBody > .panel').forEach(function(row){
        ['stock_item_id','quantity','unit_price','vat_rate'].forEach(function(field, i){
            var srcClass = ['row-prod','row-qty','row-price','row-vat'][i];
            var input = document.createElement('input');
            input.type = 'hidden';
            input.className = 'hidden-sync';
            input.name = field + '[]';
            input.value = row.querySelector('.'+srcClass).value;
            form.appendChild(input);
        });
    });
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
    document.getElementById('salesSubtotal').textContent = subtotalAll.toLocaleString('tr-TR', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ' ₺';
    document.getElementById('salesVat').textContent = vatAll.toLocaleString('tr-TR', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ' ₺';
    document.getElementById('tot').textContent = (subtotalAll + vatAll).toLocaleString('tr-TR', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ' ₺';
}

document.querySelector('form').addEventListener('submit', syncHiddenInputs);
addItemRow();

function onContactChange(){
    var sel=document.getElementById('contactSel');
    var box=document.getElementById('newContactBox');
    if(sel.value==='__new__'){ box.style.display='block'; sel.value=''; document.getElementById('qsContactName').focus(); }
    else box.style.display='none';
}

function quickContactSales(name, type) {
    if (!name) { alert('Ad girin'); return; }
    const fd = new FormData();
    fd.append('t', 'contact');
    fd.append('name', name);
    fd.append('contact_type', type || 'Müşteri');
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
                document.getElementById('qsContactName').value = '';
                document.getElementById('newContactBox').style.display='none';
            } else alert('Hata: ' + data.message);
        })
        .catch(e => alert('Hata: ' + e));
}
</script>

<?php botx(); ?>
