<?php
require_once 'common.php';
require_once __DIR__.'/../stock_lib.php';
$pdo=db();
$ok=''; $er='';

// FİNANS ÇEKİRDEK DÜZELTMESİ (2026-07-10): Alış ekranı ödeme YAPMAZ — web purchase.php ile aynı
// mantık (bkz. ../purchase.php).
if($_SERVER['REQUEST_METHOD']==='POST'){
    try{
        if(isset($_POST['delete_purchase'])){
            if(!can_edit_delete()) throw new Exception('Silme için yetkiniz yok.');
            $purchaseId=(int)$_POST['id'];
            $res=stock_reverse_purchase($pdo,$purchaseId);
            if($res['ok']){ $_SESSION['purchase_ok']=$res['message']; }else{ $_SESSION['purchase_er']=$res['message']; }
            redirect('purchase.php');
        }elseif(isset($_POST['edit_id'])){
            if(!can_edit_delete()) throw new Exception('Düzenleme için yetkiniz yok.');
            $editId=(int)$_POST['edit_id'];
            $elig=stock_can_edit_purchase($pdo,$editId);
            if(!$elig['editable']) throw new Exception($elig['reason']);
            $res=stock_update_purchase(
                $pdo, $editId, (int)$_POST['contact_id'],
                $_POST['stock_item_id'] ?? [], $_POST['quantity'] ?? [], $_POST['unit_price'] ?? [], $_POST['vat_rate'] ?? [],
                'Alış'
            );
            if($res['ok']){ $ok=$res['message']; }else{ $er=$res['message']; }
        }else{
            $supplier=(int)$_POST['contact_id'];
            $ids=$_POST['stock_item_id'] ?? [];
            $qtys=$_POST['quantity'] ?? [];
            $prices=$_POST['unit_price'] ?? [];
            $vatRates=$_POST['vat_rate'] ?? [];

            if(!$supplier) throw new Exception('Tedarikçi seçin.');

            $res=stock_create_purchase($pdo, $supplier, $ids, $qtys, $prices, $vatRates, 'Alış');
            if(!$res['ok']) throw new Exception($res['message']);

            try{
                if(function_exists('activity_log')){
                    $sn=$pdo->prepare("SELECT name FROM contacts WHERE id=?");
                    $sn->execute([$supplier]);
                    $sname=$sn->fetch()['name']??'';
                    activity_log('Satın Alma','Mobil',$sname.' · '.$res['message'],'Açık borç','purchase',$res['purchase_id'],'purchase.php','🛒');
                }
            }catch(Throwable $e){}

            $ok=$res['message'];
        }
    }catch(Throwable $e){ $er=$e->getMessage(); }

    if($ok) $_SESSION['purchase_ok']=$ok; else if($er) $_SESSION['purchase_er']=$er;
    header('Location: purchase.php');
    exit;
}

if(!empty($_SESSION['purchase_ok'])){ $ok=$_SESSION['purchase_ok']; unset($_SESSION['purchase_ok']); }
if(!empty($_SESSION['purchase_er'])){ $er=$_SESSION['purchase_er']; unset($_SESSION['purchase_er']); }

// Düzenleme modu: ?edit_id=N (web purchase.php ile aynı mantık).
$editId=(int)($_GET['edit_id'] ?? 0);
$editMode=null;
if($editId && can_edit_delete()){
    $elig=stock_can_edit_purchase($pdo,$editId);
    if($elig['editable']){
        $editMode=['id'=>$editId,'purchase'=>$elig['purchase'],'lines'=>$elig['lines']];
    }elseif($er===''){
        $er=$elig['reason'];
    }
}

topx($editMode ? 'Alışı Düzenle' : 'Satın Alma');
$cs=$pdo->query("SELECT id,name FROM contacts WHERE type IN ('Tedarikçi','Her İkisi') OR type IS NULL ORDER BY name")->fetchAll();
if(!$cs) $cs=$pdo->query("SELECT id,name FROM contacts ORDER BY name")->fetchAll();
$ps=$pdo->query("SELECT id,name,unit,purchase_price,vat_rate FROM stock_items WHERE COALESCE(active,1)=1 ORDER BY name")->fetchAll();
?>
<?php if($ok): ?><div class="notice"><?=htmlspecialchars($ok)?></div><?php endif; ?>
<?php if($er): ?><div class="err"><?=htmlspecialchars($er)?></div><?php endif; ?>
<div class="panel">
<div class="notice" style="background:rgba(37,99,235,.15)">Bu ekran ödeme yapmaz — alış tedarikçiye açık borç (Bekliyor) olarak kaydedilir. Ödeme "Ödeme" ekranından ayrıca girilir.</div>
<?php if($editMode): ?>
<div class="notice">Bu alışı düzenliyorsunuz. Kaydettiğinizde stok otomatik yeniden hesaplanır.</div>
<?php endif; ?>
<form method="post" id="purchForm">
  <?php if($editMode): ?><input type="hidden" name="edit_id" value="<?=(int)$editMode['id']?>"><?php endif; ?>
  <label>Tedarikçi</label>
  <select name="contact_id" id="contactSel" required onchange="onSupplierChange()">
    <option value="">— Seç —</option>
    <?php foreach($cs as $c): ?><option value="<?=$c['id']?>" <?=$editMode && (int)$editMode['purchase']['contact_id']===(int)$c['id']?'selected':''?>><?=htmlspecialchars($c['name'])?></option><?php endforeach; ?>
    <option value="__new__">➕ Listede yok — Yeni Tedarikçi Ekle…</option>
  </select>
  <div id="newContactBox" style="display:none;background:rgba(37,99,235,.12);border-radius:12px;padding:10px;margin:6px 0 12px">
    <input type="text" id="qcName" placeholder="Tedarikçi adı">
    <button type="button" class="btn dark" style="width:100%" onclick="quickContactMob(document.getElementById('qcName').value, 'Tedarikçi')">✓ Ekle ve Seç</button>
  </div>

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

  <button class="btn dark" style="width:100%;padding:14px"><?=$editMode?'💾 Değişiklikleri Kaydet':'🛒 Alışı Kaydet (Açık Borç)'?></button>
  <?php if($editMode): ?><a href="purchase.php" class="btn" style="width:100%;padding:12px;margin-top:8px;text-align:center;display:block">✕ Vazgeç</a><?php endif; ?>
</form>
</div>

<div class="panel">
  <b>Son Alışlar</b>
  <?php
  try{
      $recentP = $pdo->query(
          "SELECT fm.id, fm.movement_date, fm.amount, fm.vat_amount, fm.description, fm.status, fm.document_id, c.name AS cname, td.document_no
           FROM finance_movements fm
           LEFT JOIN contacts c ON c.id=fm.contact_id
           LEFT JOIN trade_documents td ON td.id=fm.document_id
           WHERE fm.movement_type='purchase'
           ORDER BY fm.id DESC LIMIT 10"
      )->fetchAll();
  }catch(Throwable $e){ $recentP=[]; }
  if(!$recentP) echo '<p class="muted" style="margin:10px 0 0">Henüz kayıt yok.</p>';
  foreach($recentP as $row):
      $isDoc = !empty($row['document_id']);
      $rowEditable = !$isDoc && can_edit_delete() && stock_can_edit_purchase($pdo,(int)$row['id'])['editable'];
  ?>
  <div class="item" style="display:flex;justify-content:space-between;align-items:center;gap:8px">
    <div style="flex:1;min-width:0">
      <b style="color:#f87171"><?=mm($row['amount'])?></b> <?=badge($row['status'],status_tone($row['status']))?><br>
      <small class="muted"><?=htmlspecialchars($row['movement_date'] ?? '')?> · <?=htmlspecialchars($row['cname'] ?: '—')?></small><br>
      <small class="muted">
        <?php if($isDoc): ?><b><?=htmlspecialchars($row['document_no'] ?: 'Belge')?></b> · <?php endif; ?>
        <?=htmlspecialchars($row['description'] ?? '')?>
      </small>
    </div>
    <?php if($isDoc): ?>
    <div style="display:flex;gap:6px">
      <a class="btn" style="background:rgba(37,99,235,.18);padding:8px 10px" href="../trade_document_view.php?id=<?=(int)$row['document_id']?>">🧾</a>
    </div>
    <?php elseif(can_edit_delete()): ?>
    <div style="display:flex;gap:6px">
      <?php if($rowEditable): ?>
      <a class="btn" style="background:rgba(37,99,235,.18);padding:8px 10px" href="purchase.php?edit_id=<?=(int)$row['id']?>">✏️</a>
      <?php endif; ?>
      <form method="post" onsubmit="return confirm('Bu alış kaydı ve bağlı verileri KALICI olarak silinecek. Emin misiniz?')" style="margin:0">
        <input type="hidden" name="delete_purchase" value="1">
        <input type="hidden" name="id" value="<?=(int)$row['id']?>">
        <button class="btn" style="background:rgba(220,38,38,.2);padding:8px 10px" type="submit">🗑</button>
      </form>
    </div>
    <?php endif; ?>
  </div>
  <?php endforeach; ?>
</div>
<script>
var PRODUCTS = <?= json_encode(array_map(function($p){
    return ['id'=>(int)$p['id'],'name'=>$p['name'],'unit'=>$p['unit'],'price'=>$p['purchase_price'],'vat'=>$p['vat_rate']!==null?$p['vat_rate']:20];
}, $ps), JSON_UNESCAPED_UNICODE) ?>;
var PREFILL_LINES = <?= json_encode($editMode ? array_map(function($l){
    return ['id'=>$l['stock_item_id'],'qty'=>$l['quantity'],'price'=>$l['unit_price'],'vat'=>$l['vat_rate']];
}, $editMode['lines']) : [], JSON_UNESCAPED_UNICODE) ?>;
var rowIndex = 0;

function escPurchM(s){ return String(s).replace(/[&<>"']/g, function(c){ return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]; }); }

function productOptionsHtmlPurchM(){
    var html = '<option value="">— Seç —</option>';
    PRODUCTS.forEach(function(p){
        html += '<option value="'+p.id+'" data-price="'+(p.price||0)+'" data-vat="'+(p.vat||0)+'" data-unit="'+escPurchM(p.unit||'adet')+'">'
            + escPurchM(p.name)+'</option>';
    });
    html += '<option value="__new__">➕ Listede yok — Yeni Ürün Ekle…</option>';
    return html;
}

function addItemRow(prefill){
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
    if(prefill){
        row.querySelector('.row-prod').value = prefill.id;
        row.querySelector('.new-prod-box').style.display = 'none';
        row.querySelector('.row-qty').value = prefill.qty;
        row.querySelector('.row-price').value = prefill.price;
        row.querySelector('.row-vat').value = prefill.vat;
    }
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
        row.querySelector('.new-prod-box').style.display = 'none';
    } else {
        row.remove();
    }
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
        if(!row.querySelector('.row-price').value) row.querySelector('.row-price').value = opt.dataset.price;
        row.querySelector('.row-vat').value = opt.dataset.vat || 20;
    }
    calcAll();
}

function quickAddProductRow(btn){
    var row = btn.closest('.panel');
    var name = row.querySelector('.np-name').value.trim();
    if(!name){ alert('Ürün adı girin'); return; }
    var fd = new FormData();
    fd.append('t', 'product');
    fd.append('name', name);
    fetch('../ajax_quick_add.php', {method: 'POST', headers: {'X-CSRF-Token': window.CSRF_TOKEN}, body: fd})
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

if(PREFILL_LINES.length){
    PREFILL_LINES.forEach(function(l){ addItemRow(l); });
} else {
    addItemRow();
}

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
    fetch('../ajax_quick_add.php', {method: 'POST', headers: {'X-CSRF-Token': window.CSRF_TOKEN}, body: fd})
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
