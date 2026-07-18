<?php
require_once 'common.php';
require_once __DIR__.'/../stock_lib.php';
require_once __DIR__.'/../cpa_allocation_lib.php';
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

            // P0 CPA KULLANICI AKIŞI (2026-07-18) — web purchase.php ile AYNI opsiyonel satır
            // mantığı, bkz. oradaki açıklama notu.
            $__allocCids = $_POST['alloc_customer_id'] ?? [];
            $__allocQtys = $_POST['alloc_qty'] ?? [];
            foreach($ids as $__ai=>$__apid){
                $__apid=(int)$__apid; $__aqtyLine=(float)($qtys[$__ai] ?? 0);
                if(!$__apid || $__aqtyLine<=0) continue;
                $__acid=(int)($__allocCids[$__ai] ?? 0);
                if(!$__acid) continue;
                $__aqty=(float)($__allocQtys[$__ai] ?? 0);
                if($__aqty<=0) $__aqty=$__aqtyLine;
                try{ cpa_alloc_create($pdo, $u['id']??0, $res['purchase_id'], $__apid, $__acid, $__aqty, 'Alış girişinde ayrıldı'); }catch(Throwable $e){}
            }

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
$__allocCustomers=[];
try{ $__allocCustomers=$pdo->query("SELECT id,name FROM contacts WHERE type IN ('Müşteri','Her İkisi') ORDER BY name")->fetchAll(); }catch(Throwable $e){}
?>
<?php if($ok): ?><?=ds_alert('success',$ok)?><?php endif; ?>
<?php if($er): ?><?=ds_alert('danger',$er)?><?php endif; ?>

<!-- JS ile dinamik satır eklenen kritik akış — #itemsBody/.row-prod/.row-qty/.row-price/.row-vat/
     .row-sub/.new-prod-box/.np-name class'ları JS'e SIKI bağlı (aşağıdaki <script> bloğu hiç
     değişmedi) — sadece görsel katman (df-panel/df-btn) taşındı, JS selector'larına dokunulmadı. -->
<div class="df-panel">
<?=ds_alert('info','Bu ekran ödeme yapmaz — alış tedarikçiye açık borç (Bekliyor) olarak kaydedilir. Ödeme "Ödeme" ekranından ayrıca girilir.')?>
<?php if($editMode): ?>
<div style="margin-top:10px"><?=ds_alert('info','Bu alışı düzenliyorsunuz. Kaydettiğinizde stok otomatik yeniden hesaplanır.')?></div>
<?php endif; ?>
<form method="post" id="purchForm" style="margin-top:10px">
  <?php if($editMode): ?><input type="hidden" name="edit_id" value="<?=(int)$editMode['id']?>"><?php endif; ?>
  <label>Tedarikçi</label>
  <select name="contact_id" id="contactSel" required onchange="onSupplierChange()">
    <option value="">— Seç —</option>
    <?php foreach($cs as $c): ?><option value="<?=$c['id']?>" <?=$editMode && (int)$editMode['purchase']['contact_id']===(int)$c['id']?'selected':''?>><?=h($c['name'])?></option><?php endforeach; ?>
    <option value="__new__">➕ Listede yok — Yeni Tedarikçi Ekle…</option>
  </select>
  <div id="newContactBox" class="df-panel" style="display:none;background:rgba(37,99,235,.12);margin:6px 0 12px">
    <input type="text" id="qcName" placeholder="Tedarikçi adı">
    <button type="button" class="df-btn df-btn--primary" style="width:100%" onclick="quickContactMob(document.getElementById('qcName').value, 'Tedarikçi')"><?=ds_icon('check',14)?> Ekle ve Seç</button>
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
  <button type="button" class="df-btn df-btn--secondary" style="width:100%;margin:8px 0" onclick="addItemRow()"><?=ds_icon('plus',14)?> Satır Ekle</button>

  <div class="df-panel" style="background:rgba(37,99,235,.18);margin:14px 0">
    <div style="display:flex;justify-content:space-between;padding:2px 0"><small class="muted">Ara Toplam</small><b id="purchSubtotal">0,00 ₺</b></div>
    <div style="display:flex;justify-content:space-between;padding:2px 0"><small class="muted">KDV</small><b id="purchVat">0,00 ₺</b></div>
    <div style="display:flex;justify-content:space-between;padding:6px 0 0;border-top:1px solid rgba(255,255,255,.15);margin-top:4px">
      <span style="font-weight:800">Genel Toplam</span><span id="t" style="font-size:24px;font-weight:900">0,00 ₺</span>
    </div>
  </div>

  <button class="df-btn df-btn--primary df-btn--lg" style="width:100%"><?=$editMode?'💾 Değişiklikleri Kaydet':'🛒 Alışı Kaydet (Açık Borç)'?></button>
  <?php if($editMode): ?><a href="purchase.php" class="df-btn df-btn--secondary" style="width:100%;margin-top:8px;justify-content:center">✕ Vazgeç</a><?php endif; ?>
</form>
</div>

<div class="df-panel" style="margin-top:14px">
  <b><?=ds_icon('box',16)?> Son Alışlar</b>
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
  if(!$recentP) ds_empty_state('Henüz kayıt yok.');
  foreach($recentP as $row):
      $isDoc = !empty($row['document_id']);
      $rowEditable = !$isDoc && can_edit_delete() && stock_can_edit_purchase($pdo,(int)$row['id'])['editable'];
  ?>
  <div class="df-panel" style="margin-top:10px">
    <div style="display:flex;justify-content:space-between;gap:8px;align-items:flex-start">
      <div class="df-list-row-title" style="color:var(--df-danger-ink)"><?=mm($row['amount'])?></div>
      <?=ds_badge($row['status'])?>
    </div>
    <div class="df-list-row-meta" style="margin-top:6px">
      <span><?=h($row['movement_date'] ?? '')?></span>
      <span><?=h($row['cname'] ?: '—')?></span>
    </div>
    <?php if($isDoc || $row['description']): ?>
    <div class="df-list-row-desc" style="margin-top:4px">
      <?php if($isDoc): ?><b><?=h($row['document_no'] ?: 'Belge')?></b> · <?php endif; ?>
      <?=h($row['description'] ?? '')?>
    </div>
    <?php endif; ?>
    <?php if($isDoc): ?>
    <div style="display:flex;gap:6px;margin-top:10px">
      <!-- P0 KAPANIŞ (2026-07-18): web=1 olmadan boot.php'nin mobil-otomatik-yönlendirmesi bu
           /mobile/ dışı sayfaya sessizce takılıp mobile/index.php'ye geri atardı. -->
      <a class="df-btn df-btn--secondary df-btn--sm" href="../trade_document_view.php?id=<?=(int)$row['document_id']?>&web=1"><?=ds_icon('box',14)?> Belge</a>
    </div>
    <?php elseif(can_edit_delete()): ?>
    <div style="display:flex;gap:6px;margin-top:10px">
      <?php if($rowEditable): ?>
      <a class="df-btn df-btn--secondary df-btn--sm" href="purchase.php?edit_id=<?=(int)$row['id']?>"><?=ds_icon('edit',14)?> Düzenle</a>
      <?php endif; ?>
      <form method="post" onsubmit="return confirm('Bu alış kaydı ve bağlı verileri KALICI olarak silinecek. Emin misiniz?')" style="margin:0">
        <input type="hidden" name="delete_purchase" value="1">
        <input type="hidden" name="id" value="<?=(int)$row['id']?>">
        <button class="df-btn df-btn--danger df-btn--sm" type="submit"><?=ds_icon('trash',14)?></button>
      </form>
    </div>
    <?php endif; ?>
    <?php if(can_edit_delete()): ?>
    <div style="display:flex;gap:6px;margin-top:6px">
      <a class="df-btn df-btn--secondary df-btn--sm" href="cpa_allocation.php?purchase_id=<?=(int)$row['id']?>">🤝 Müşteriye Ayır</a>
    </div>
    <?php endif; ?>
  </div>
  <?php endforeach; ?>
</div>
<script>
var PRODUCTS = <?= json_encode(array_map(function($p){
    return ['id'=>(int)$p['id'],'name'=>$p['name'],'unit'=>$p['unit'],'price'=>$p['purchase_price'],'vat'=>$p['vat_rate']!==null?$p['vat_rate']:20];
}, $ps), JSON_UNESCAPED_UNICODE) ?>;
var CUSTOMERS = <?= json_encode(array_map(function($c){
    return ['id'=>(int)$c['id'],'name'=>$c['name']];
}, $__allocCustomers), JSON_UNESCAPED_UNICODE) ?>;
var ALLOW_ALLOC = <?= (!$editMode && cpa_alloc_can_edit()) ? 'true' : 'false' ?>;
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

// P0 CPA KULLANICI AKIŞI (2026-07-18) — web purchase.php ile AYNI opsiyonel satır alanı.
function allocBoxHtmlPurchM(){
    if(!ALLOW_ALLOC) return '';
    var custOpts = '<option value="">— Müşteri seç —</option>';
    CUSTOMERS.forEach(function(c){ custOpts += '<option value="'+c.id+'">'+escPurchM(c.name)+'</option>'; });
    return '<label style="display:flex;align-items:center;gap:6px;margin:6px 0;font-size:12px;font-weight:700">'
        + '<input type="checkbox" class="row-alloc-check" style="width:auto" onchange="toggleAllocBoxPurchM(this)"> Bu ürün belirli bir müşteri için mi alındı?'
        + '</label>'
        + '<div class="row-alloc-box" style="display:none;margin-bottom:6px">'
        + '<select name="alloc_customer_id[]" class="row-alloc-customer" style="margin-bottom:6px">'+custOpts+'</select>'
        + '<input type="number" step="0.001" min="0" name="alloc_qty[]" class="row-alloc-qty" placeholder="Ayrılacak miktar (boşsa tümü)">'
        + '</div>';
}
function toggleAllocBoxPurchM(chk){
    var row = chk.closest('.df-panel');
    var box = row.querySelector('.row-alloc-box');
    box.style.display = chk.checked ? 'block' : 'none';
    if(!chk.checked){
        box.querySelector('.row-alloc-customer').value = '';
        box.querySelector('.row-alloc-qty').value = '';
    }
}

function addItemRow(prefill){
    var idx = rowIndex++;
    var row = document.createElement('div');
    row.className = 'df-panel';
    row.style.cssText = 'margin-bottom:10px;padding:10px';
    row.dataset.idx = idx;
    row.innerHTML =
        '<select name="stock_item_id[]" class="row-prod" style="margin-bottom:6px" onchange="onRowProductChange(this)">'+productOptionsHtmlPurchM()+'</select>'
        + '<div class="new-prod-box" style="display:none;background:rgba(37,99,235,.12);border-radius:10px;padding:8px;margin-bottom:6px">'
        + '<input type="text" class="np-name" placeholder="Ürün adı" style="margin-bottom:6px">'
        + '<button type="button" class="df-btn df-btn--primary" style="width:100%" onclick="quickAddProductRow(this)">✓ Ekle ve Seç</button>'
        + '</div>'
        + '<div class="cpa-hint" style="display:none;margin-bottom:6px;font-size:12px;background:rgba(37,99,235,.12);border-radius:8px;padding:6px 8px"></div>'
        + allocBoxHtmlPurchM()
        + '<div style="display:flex;gap:8px">'
        + '<div style="flex:1"><small class="muted">Miktar</small><input type="number" step="0.01" min="0.01" name="quantity[]" class="row-qty" value="1" oninput="calcAll()"></div>'
        + '</div>'
        + '<div style="display:flex;gap:8px;margin-top:6px">'
        + '<div style="flex:1"><small class="muted">Birim Alış Fiyatı</small><input type="number" step="0.01" min="0" name="unit_price[]" class="row-price" oninput="calcAll()"></div>'
        + '<div style="flex:1"><small class="muted">KDV %</small><input type="text" inputmode="decimal" list="vatPresets" name="vat_rate[]" class="row-vat" value="20" oninput="calcAll()"></div>'
        + '</div>'
        + '<div style="display:flex;justify-content:space-between;align-items:center;margin-top:6px">'
        + '<span class="row-sub" style="font-weight:800">0,00 ₺</span>'
        + '<button type="button" class="df-btn df-btn--danger" onclick="removeRow(this)">🗑 Satırı Sil</button>'
        + '</div>';
    document.getElementById('itemsBody').appendChild(row);
    if(prefill){
        row.querySelector('.row-prod').value = prefill.id;
        row.querySelector('.new-prod-box').style.display = 'none';
        row.querySelector('.row-qty').value = prefill.qty;
        row.querySelector('.row-price').value = prefill.price;
        row.querySelector('.row-vat').value = prefill.vat;
        loadCpaHintM(row, prefill.id);
    }
    calcAll();
}

function removeRow(btn){
    var row = btn.closest('.df-panel');
    var rows = document.querySelectorAll('#itemsBody > .df-panel');
    if(rows.length <= 1){
        row.querySelector('.row-prod').value = '';
        row.querySelector('.row-qty').value = 1;
        row.querySelector('.row-price').value = '';
        row.querySelector('.row-vat').value = 20;
        row.querySelector('.new-prod-box').style.display = 'none';
        var __allocChkM = row.querySelector('.row-alloc-check');
        if(__allocChkM){ __allocChkM.checked = false; toggleAllocBoxPurchM(__allocChkM); }
    } else {
        row.remove();
    }
    calcAll();
}

function onRowProductChange(sel){
    var row = sel.closest('.df-panel');
    var box = row.querySelector('.new-prod-box');
    var hint = row.querySelector('.cpa-hint');
    if(sel.value === '__new__'){
        box.style.display = 'block';
        if(hint) hint.style.display = 'none';
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
    loadCpaHintM(row, sel.value);
    calcAll();
}

// P1 — CPA (2026-07-18): web purchase.php ile aynı bilgilendirici öneri, hiçbir alanı otomatik
// doldurmaz (bkz. web cpa_suggest_ajax.php ve oradaki gerekçe notu).
function loadCpaHintM(row, stockItemId){
    var hint = row.querySelector('.cpa-hint');
    if(!hint || !stockItemId){ if(hint) hint.style.display='none'; return; }
    fetch('../cpa_suggest_ajax.php?stock_item_id='+encodeURIComponent(stockItemId))
        .then(function(r){ return r.json(); })
        .then(function(data){
            if(!data.ok || !data.suggestions || !data.suggestions.length){ hint.style.display='none'; return; }
            var txt = '💡 Tercih edilen tedarikçiler: ' + data.suggestions.map(function(s){
                return escPurchM(s.customer_name) + ' → ' + escPurchM(s.supplier_name) + (s.is_default?' (varsayılan)':'');
            }).join(' · ');
            hint.innerHTML = txt;
            hint.style.display = 'block';
        })
        .catch(function(){ hint.style.display='none'; });
}

function quickAddProductRow(btn){
    var row = btn.closest('.df-panel');
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
    document.querySelectorAll('#itemsBody > .df-panel').forEach(function(row){
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
