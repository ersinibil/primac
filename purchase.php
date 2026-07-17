<?php
require_once __DIR__.'/boot.php';
require_login();
require_once __DIR__.'/stock_lib.php';

$pdo=db();
$ok=''; $er='';

// FİNANS ÇEKİRDEK DÜZELTMESİ (2026-07-10): Alış ekranı ödeme YAPMAZ. Ödeme yöntemi seçimi
// kaldırıldı — her alış tedarikçiye açık borç oluşturur, durum her zaman "Bekliyor", kasa/banka/
// kart hiçbir zaman etkilenmez. Ödemenin kendisi SADECE Ödeme ekranından yapılır.
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['delete_purchase'])){
    try{
        if(!can_edit_delete()) throw new Exception('Silme için yetkiniz yok.');
        $purchaseId=(int)$_POST['id'];
        $res=stock_reverse_purchase($pdo,$purchaseId);
        if($res['ok']) $_SESSION['purchase_ok']=$res['message']; else $_SESSION['purchase_er']=$res['message'];
    }catch(Throwable $e){
        $_SESSION['purchase_er']=$e->getMessage();
    }
    header('Location: purchase.php');
    exit;
}

if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['edit_id'])){
    try{
        if(!can_edit_delete()) throw new Exception('Düzenleme için yetkiniz yok.');
        $editId=(int)$_POST['edit_id'];
        $elig=stock_can_edit_purchase($pdo,$editId);
        if(!$elig['editable']) throw new Exception($elig['reason']);
        $res=stock_update_purchase(
            $pdo, $editId, (int)$_POST['contact_id'],
            $_POST['stock_item_id'] ?? [], $_POST['quantity'] ?? [], $_POST['unit_price'] ?? [], $_POST['vat_rate'] ?? [],
            'Alış'
        );
        if($res['ok']) $ok=$res['message']; else $er=$res['message'];
    }catch(Throwable $e){ $er=$e->getMessage(); }
}

if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['action']) && $_POST['action']==='add_purchase'){
    try{
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
                activity_log('Satın Alma','Alış',$sname.' · '.$res['message'],'Açık borç','purchase',$res['purchase_id'],'purchase.php','🛒');
            }
        }catch(Throwable $e){}

        $ok=$res['message'];
    }catch(Throwable $e){
        $er=$e->getMessage();
    }

    if($ok) $_SESSION['purchase_ok']=$ok; else if($er) $_SESSION['purchase_er']=$er;
    header('Location: purchase.php');
    exit;
}

if(!empty($_SESSION['purchase_ok'])){ $ok=$_SESSION['purchase_ok']; unset($_SESSION['purchase_ok']); }
if(!empty($_SESSION['purchase_er'])){ $er=$_SESSION['purchase_er']; unset($_SESSION['purchase_er']); }

// Düzenleme modu: ?edit_id=N ile mevcut bir alışı forma doldurup düzenlemeye aç (sales.php ile
// aynı mantık — bkz. sales.php).
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

$products=$pdo->query("SELECT id,name,unit,purchase_price,vat_rate FROM stock_items WHERE COALESCE(active,1)=1 ORDER BY name")->fetchAll();

require_once __DIR__.'/layout_top.php';
?>
<?php
$__title = $editMode ? '✏️ Alışı Düzenle' : 'Satın Alma';
$__actions = '';
if(!$editMode){
    $__actions = ds_button('Satış', 'sales.php', 'secondary', '', '', true)
        . ds_button('Alış Belgesi', 'trade_document_new.php?type=purchase', 'secondary', '', '', true);
}
ds_page_header($__title, ds_icon('box',24), '', $__actions, true, true);
?>

<?php if($ok): ?><?=ds_alert('success',$ok)?><?php endif; ?>
<?php if($er): ?><?=ds_alert('danger',$er)?><?php endif; ?>

<!-- Hızlı Satın Alma Formu (JS ile dinamik satır eklenen kritik akış — .row-prod/#itemsBody vb.
     class'lar JS'e SIKI bağlı, DEĞİŞTİRİLMEDİ; RELEASE 0.9 (2026-07-17) DS migration tamamlandı,
     sales.php ile birebir aynı desen — bkz. o dosyadaki açıklama notu.) -->
<section class="df-card">
<h2 class="df-section-title" style="margin:0 0 var(--df-space-3)"><?=$editMode?'✏️ Alışı Düzenle':'Hızlı Satın Alma'?></h2>
<div class="df-alert df-alert--info" style="margin-bottom:var(--df-space-3)">
  Bu ekran ödeme yapmaz — alış tedarikçiye açık borç (Bekliyor) olarak kaydedilir. Ödeme
  <a href="finance_new.php?direction=out">Ödeme ekranından</a> ayrıca girilir.
</div>
<form method="post" id="purchForm">
  <?php if($editMode): ?>
  <input type="hidden" name="edit_id" value="<?=(int)$editMode['id']?>">
  <?php else: ?>
  <input type="hidden" name="action" value="add_purchase">
  <?php endif; ?>

  <?php
  $__supplierOpts = '';
  try{
    $cs=$pdo->query("SELECT id,name FROM contacts WHERE type IN ('Tedarikçi','Her İkisi') OR type IS NULL ORDER BY name")->fetchAll();
    if(!$cs) $cs=$pdo->query("SELECT id,name FROM contacts ORDER BY name")->fetchAll();
    foreach($cs as $c){ $__supplierOpts .= '<option value="'.$c['id'].'" '.($editMode && (int)$editMode['purchase']['contact_id']===(int)$c['id']?'selected':'').'>'.h($c['name']).'</option>'; }
  }catch(Throwable $e){}
  ds_form_field('Tedarikçi', '<select name="contact_id" id="contactSel" required onchange="onSupplierChange()"><option value="">— Seç —</option>'.$__supplierOpts.'<option value="__new__">➕ Listede yok — Yeni Tedarikçi Ekle…</option></select>');
  ?>
  <div id="newContactBox" class="df-card" style="display:none;background:var(--df-accent-soft);border-color:transparent;margin-top:var(--df-space-2)">
    <input type="text" id="contactNamePurch" placeholder="Tedarikçi adı" style="margin-bottom:var(--df-space-2)">
    <button type="button" class="df-btn df-btn--primary" style="width:100%" onclick="quickAddContactPurch(document.getElementById('contactNamePurch').value, 'Tedarikçi')">✓ Ekle ve Seç</button>
  </div>

  <datalist id="vatPresets">
    <option value="0"></option>
    <option value="1"></option>
    <option value="8"></option>
    <option value="10"></option>
    <option value="20"></option>
  </datalist>

  <div style="margin-top:var(--df-space-4)">
    <label style="font-weight:700;font-size:13px;color:var(--df-ink-900);display:block;margin-bottom:var(--df-space-2)">Ürünler <small class="df-muted" style="font-weight:400">(listede yoksa "➕ Yeni Ürün Ekle…" seçeneğini kullanın)</small></label>
    <div class="df-table-wrap">
    <table class="df-table">
      <thead>
        <tr>
          <th>Ürün</th>
          <th style="width:90px">Miktar</th>
          <th style="width:120px">Birim Alış Fiyatı</th>
          <th style="width:80px">KDV %</th>
          <th style="width:110px;text-align:right">Ara Toplam</th>
          <th style="width:36px"></th>
        </tr>
      </thead>
      <tbody id="itemsBody"></tbody>
    </table>
    </div>
    <button type="button" class="df-btn df-btn--secondary df-btn--sm" style="margin-top:var(--df-space-2)" onclick="addItemRow()">➕ Satır Ekle</button>
  </div>

  <div class="df-card" style="background:var(--df-accent-soft);border-color:transparent;margin:var(--df-space-4) 0">
    <div style="display:flex;justify-content:space-between;padding:3px 0"><span class="df-muted">Ara Toplam (KDV Hariç)</span><b id="purchSubtotal">0,00 ₺</b></div>
    <div style="display:flex;justify-content:space-between;padding:3px 0"><span class="df-muted">KDV</span><b id="purchVat">0,00 ₺</b></div>
    <div style="display:flex;justify-content:space-between;padding:var(--df-space-2) 0 0;border-top:1px solid var(--df-hairline);margin-top:6px">
      <span style="font-size:15px;font-weight:800">Genel Toplam</span>
      <span id="purchTotal" style="font-size:26px;font-weight:900;color:var(--df-accent-soft-ink)">0,00 ₺</span>
    </div>
  </div>

  <button type="submit" class="df-btn df-btn--primary df-btn--lg" style="width:100%"><?=$editMode?'💾 Değişiklikleri Kaydet':'🛒 Satın Almayı Kaydet (Açık Borç)'?></button>
  <?php if($editMode): ?><a href="purchase.php" class="df-btn df-btn--secondary" style="width:100%;margin-top:var(--df-space-2);justify-content:center">✕ Vazgeç</a><?php endif; ?>
</form>
</section>

<!-- Son Alışlar (salt-okunur — JS bağımlılığı yok) -->
<section class="df-card" style="margin-top:var(--df-space-4)">
<h2 style="font-size:15px;margin:0 0 var(--df-space-3)">Son Alışlar</h2>
<?php
try{
    $recent=$pdo->query(
        "SELECT fm.id, fm.movement_date, fm.amount, fm.vat_amount, fm.description, fm.status, fm.document_id, c.name AS cname, td.document_no
         FROM finance_movements fm
         LEFT JOIN contacts c ON c.id=fm.contact_id
         LEFT JOIN trade_documents td ON td.id=fm.document_id
         WHERE fm.movement_type='purchase'
         ORDER BY fm.id DESC LIMIT 10"
    )->fetchAll();
}catch(Throwable $e){ $recent=[]; }
if($recent): ?>
<div class="df-table-wrap">
<table class="df-table" style="min-width:600px">
  <thead><tr><th>Tarih</th><th>Tedarikçi</th><th>Açıklama</th><th style="text-align:right">Tutar</th><th style="text-align:right">KDV</th><th>Durum</th><?php if(can_edit_delete()): ?><th>İşlem</th><?php endif; ?></tr></thead>
  <tbody>
  <?php foreach($recent as $row): ?>
    <?php $isDoc = !empty($row['document_id']); ?>
    <?php $rowEditable = !$isDoc && can_edit_delete() && stock_can_edit_purchase($pdo, (int)$row['id'])['editable']; ?>
    <tr>
      <td class="nowrap"><?=h($row['movement_date'])?></td>
      <td><?=h($row['cname'] ?? '—')?></td>
      <td style="font-size:12px;color:var(--df-ink-500)">
        <?php if($isDoc): ?><span class="df-badge df-badge--info"><?=h($row['document_no'] ?: 'Belge')?></span> <?php endif; ?>
        <?=h($row['description'] ?? '')?>
      </td>
      <td style="text-align:right;font-weight:800;color:var(--df-danger-ink)"><?=money($row['amount'])?></td>
      <td style="text-align:right;color:var(--df-ink-500);font-size:12px"><?=$row['vat_amount']>0?money($row['vat_amount']):'—'?></td>
      <td><?=ds_badge($row['status'])?></td>
      <?php if(can_edit_delete()): ?>
      <td class="nowrap"><div class="row-actions">
        <?php if($isDoc): ?>
        <a class="df-btn df-btn--secondary df-btn--sm" href="trade_document_view.php?id=<?=(int)$row['document_id']?>">🧾 Belgeyi Aç</a>
        <?php else: ?>
        <?php if($rowEditable): ?>
        <a class="df-btn df-btn--secondary df-btn--sm" href="purchase.php?edit_id=<?=(int)$row['id']?>">✏️ Düzenle</a>
        <?php endif; ?>
        <form method="post" style="display:inline-block;margin:0" onsubmit="return confirm('Bu alış kaydı ve bağlı verileri KALICI olarak silinecek. Emin misiniz?')">
          <input type="hidden" name="delete_purchase" value="1">
          <input type="hidden" name="id" value="<?=(int)$row['id']?>">
          <button class="df-btn df-btn--danger df-btn--sm" type="submit">🗑 Sil</button>
        </form>
        <?php endif; ?>
      </div></td>
      <?php endif; ?>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>
<?php else: ?>
  <p style="color:var(--df-ink-500)">Henüz kayıt yok.</p>
<?php endif; ?>
</section>

<!-- Satın Alma İşleri (salt-okunur — JS bağımlılığı yok) -->
<section class="df-card" style="margin-top:var(--df-space-4)">
<h2 style="font-size:15px;margin:0 0 var(--df-space-3)">Satın Alma İşleri</h2>
<?php
try{
    $rows=$pdo->query("SELECT * FROM jobs WHERE job_type IN ('satin_alma') ORDER BY id DESC")->fetchAll();
}catch(Throwable $e){ $rows=[]; $__jobsErr=$e->getMessage(); }
if(!empty($__jobsErr)): ?>
<?=ds_alert('danger',$__jobsErr)?>
<?php elseif($rows): ?>
<div class="df-table-wrap">
<table class="df-table">
<thead><tr><th>İş No</th><th>Başlık</th><th>Termin</th><th>Durum</th></tr></thead>
<tbody>
<?php foreach($rows as $r): ?>
<tr>
<td><a href="job_view.php?id=<?=(int)$r['id']?>"><?=h($r['job_no'])?></a></td>
<td><?=h($r['title'])?></td>
<td><?=h($r['due_date'])?></td>
<td><?=ds_badge($r['status'])?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
<?php else: ?>
<p style="color:var(--df-ink-500)">Henüz kayıt yok.</p>
<?php endif; ?>
</section>

<script>
var PRODUCTS = <?= json_encode(array_map(function($p){
    return ['id'=>(int)$p['id'],'name'=>$p['name'],'unit'=>$p['unit'],'price'=>$p['purchase_price'],'vat'=>$p['vat_rate']!==null?$p['vat_rate']:20];
}, $products), JSON_UNESCAPED_UNICODE) ?>;
// Düzenleme modunda mevcut alış satırlarını forma dolduran veri (sales.php ile aynı mantık).
var PREFILL_LINES = <?= json_encode($editMode ? array_map(function($l){
    return ['id'=>$l['stock_item_id'],'qty'=>$l['quantity'],'price'=>$l['unit_price'],'vat'=>$l['vat_rate']];
}, $editMode['lines']) : [], JSON_UNESCAPED_UNICODE) ?>;
var rowIndex = 0;

function escPurch(s){ return String(s).replace(/[&<>"']/g, function(c){ return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]; }); }

function productOptionsHtmlPurch(){
    var html = '<option value="">— Seç —</option>';
    PRODUCTS.forEach(function(p){
        html += '<option value="'+p.id+'" data-price="'+(p.price||0)+'" data-vat="'+(p.vat||0)+'" data-unit="'+escPurch(p.unit||'adet')+'">'
            + escPurch(p.name)+'</option>';
    });
    html += '<option value="__new__">➕ Listede yok — Yeni Ürün Ekle…</option>';
    return html;
}

function addItemRow(prefill){
    var idx = rowIndex++;
    var tr = document.createElement('tr');
    tr.dataset.idx = idx;
    tr.innerHTML =
        '<td style="padding:4px 6px">'
        + '<select name="stock_item_id[]" class="row-prod" required onchange="onRowProductChange(this)">'+productOptionsHtmlPurch()+'</select>'
        + '<div class="new-prod-box df-card" style="display:none;background:var(--df-accent-soft);border-color:transparent;padding:8px;margin-top:6px">'
        + '<input type="text" class="np-name" placeholder="Ürün adı" style="margin-bottom:6px">'
        + '<button type="button" class="df-btn df-btn--primary df-btn--sm" style="width:100%" onclick="quickAddProductRow(this)">✓ Ekle ve Seç</button>'
        + '</div></td>'
        + '<td style="padding:4px 6px"><input type="number" step="0.01" min="0.01" name="quantity[]" class="row-qty" value="1" required oninput="calcAll()" style="width:80px"></td>'
        + '<td style="padding:4px 6px"><input type="number" step="0.01" min="0" name="unit_price[]" class="row-price" required oninput="calcAll()" style="width:110px"></td>'
        + '<td style="padding:4px 6px"><input type="text" inputmode="decimal" list="vatPresets" name="vat_rate[]" class="row-vat" value="20" oninput="calcAll()" style="width:70px"></td>'
        + '<td class="row-sub" style="padding:4px 6px;text-align:right;font-weight:800">0,00 ₺</td>'
        + '<td style="padding:4px 6px"><button type="button" class="df-btn df-btn--danger df-btn--sm" onclick="removeRow(this)">🗑</button></td>';
    document.getElementById('itemsBody').appendChild(tr);
    if(prefill){
        tr.querySelector('.row-prod').value = prefill.id;
        tr.querySelector('.new-prod-box').style.display = 'none';
        tr.querySelector('.row-qty').value = prefill.qty;
        tr.querySelector('.row-price').value = prefill.price;
        tr.querySelector('.row-vat').value = prefill.vat;
    }
    calcAll();
}

function removeRow(btn){
    var tr = btn.closest('tr');
    var rows = document.querySelectorAll('#itemsBody tr');
    if(rows.length <= 1){
        tr.querySelector('.row-prod').value = '';
        tr.querySelector('.row-qty').value = 1;
        tr.querySelector('.row-price').value = '';
        tr.querySelector('.row-vat').value = 20;
        tr.querySelector('.new-prod-box').style.display = 'none';
    } else {
        tr.remove();
    }
    calcAll();
}

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
        if(!tr.querySelector('.row-price').value) tr.querySelector('.row-price').value = opt.dataset.price;
        tr.querySelector('.row-vat').value = opt.dataset.vat || 20;
    }
    calcAll();
}

function quickAddProductRow(btn){
    var tr = btn.closest('tr');
    var name = tr.querySelector('.np-name').value.trim();
    if(!name){ alert('Ürün adı girin'); return; }
    var fd = new FormData();
    fd.append('t', 'product');
    fd.append('name', name);
    fetch('ajax_quick_add.php', {method: 'POST', headers: {'X-CSRF-Token': window.CSRF_TOKEN}, body: fd})
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

if(PREFILL_LINES.length){
    PREFILL_LINES.forEach(function(l){ addItemRow(l); });
} else {
    addItemRow();
}

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

    fetch('ajax_quick_add.php', {method: 'POST', headers: {'X-CSRF-Token': window.CSRF_TOKEN}, body: fd})
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
