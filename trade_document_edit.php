<?php
/* P0 KAPANIŞ (2026-07-18, Product Owner kararı 3. madde) — Alış/Satış Belgesi düzenleme ekranı.
 * trade_document_new.php ile aynı satır-hazırlama mantığı, iş mantığı trade_core.php::
 * trade_document_update()'de (kopya matematik YOK — stock_lib.php'nin $viaDocument=true primitives'i
 * kullanılır). Sadece can_edit_delete() yetkisi olanlar açabilir — trade_document_view.php'deki
 * "✏️ Düzenle" bağlantısından gelinir.
 */
require_once __DIR__.'/boot.php';
require_login();
require_once __DIR__.'/trade_core.php';

$pdo=db();
$id=(int)($_GET['id'] ?? 0);
$error='';
$stockShortage=null;

if(!can_edit_delete()){
    require_once __DIR__.'/layout_top.php';
    ds_page_header('Belgeyi Düzenle', ds_icon('tag',24), '', '', false, true);
    echo ds_alert('danger','Düzenleme için yetkiniz yok.');
    require __DIR__.'/layout_bottom.php';
    exit;
}

if($_SERVER['REQUEST_METHOD']==='POST'){
    try{
        $contactId=(int)($_POST['contact_id'] ?? 0) ?: null;
        $docDate=$_POST['document_date'] ?: date('Y-m-d');
        trade_document_update(
            $pdo, $_SESSION['user']['id'] ?? 0, $id, $contactId, $docDate, $_POST['description'] ?? '',
            $_POST['item_name'] ?? [], $_POST['stock_item_id'] ?? [], $_POST['unit'] ?? [], $_POST['quantity'] ?? [],
            $_POST['unit_price'] ?? [], $_POST['vat_rate'] ?? [], !empty($_POST['allow_negative_stock'])
        );
        header('Location: trade_document_view.php?id='.$id);
        exit;
    }catch(StockShortageException $e){
        $stockShortage = $e->shortages;
    }catch(Throwable $e){
        $error = $e->getMessage();
    }
}

$can = trade_document_can_edit($pdo, $id);
$doc = $can['doc'];

require_once __DIR__.'/layout_top.php';

if(!$doc){
    ds_page_header('Belgeyi Düzenle', ds_icon('tag',24), '', '', false, true);
    echo ds_alert('danger','Belge bulunamadı.');
    require __DIR__.'/layout_bottom.php';
    exit;
}

// $stockShortage/$error POST'tan geldiyse (kullanıcı az önce bu formu gönderdi) düzenleme akışına
// devam edilir (formu ham POST değerleriyle yeniden doldur) — eligibility engeli sadece SAYFA İLK
// AÇILIŞINDA (GET) uygulanır, aksi halde "onayla ve devam et" ikinci POST'u burada engellenirdi.
if(!$can['editable'] && !$stockShortage && !$error){
    $__actions = ds_button('Belgeyi Aç', 'trade_document_view.php?id='.$id, 'secondary', '', '', true);
    ds_page_header('Belgeyi Düzenle — '.$doc['document_no'], ds_icon('tag',24), '', $__actions, false, true);
    echo ds_alert('danger', $can['reason'] ?: 'Belge düzenlenemez.');
    require __DIR__.'/layout_bottom.php';
    exit;
}

$pf = ($stockShortage || $error) ? $_POST : null;

$contacts=$pdo->query("SELECT id,name,type FROM contacts ORDER BY name")->fetchAll();
$products=$pdo->query("SELECT id,name,product_code,unit,purchase_price,sale_price FROM stock_items ORDER BY name")->fetchAll();

$existingItems=[];
if(!$pf){
    $ii=$pdo->prepare("SELECT * FROM trade_document_items WHERE document_id=? ORDER BY id");
    $ii->execute([$id]);
    $existingItems=$ii->fetchAll();
}

$__actions = ds_button('Belgeyi Aç', 'trade_document_view.php?id='.$id, 'secondary', '', '', true);
ds_page_header('✏️ '.($doc['document_type']==='purchase'?'Alış':'Satış').' Belgesini Düzenle — '.$doc['document_no'], ds_icon('tag',24), '', $__actions, false, true);
?>

<?php if($error): ?><?=ds_alert('danger',$error)?><?php endif; ?>

<section class="df-card">
<div class="notice" style="margin-bottom:12px;background:#eef4ff;color:#1e3a8a">
Kaydedince eski stok/CPA/cari etkisi tam olarak geri alınır, yeni değerler aynı hareket üzerinde uygulanır.
</div>
<form method="post" id="tradeEditForm">

<?php if($stockShortage): ?>
<div class="alert" style="background:#fffbeb;border:1px solid #fde68a;color:#92400e;margin-bottom:12px">
  <b>⚠️ Mevcut stok bu satış belgesi için yetersiz.</b><br>
  İşlem tamamlanırsa aşağıdaki ürün(ler)de stok negatife düşecek — KONTROLLÜ NEGATİF STOK
  POLİTİKASI gereği, devam etmek için onayınız gerekiyor:
  <ul style="margin:8px 0 8px 20px;padding:0">
    <?php foreach($stockShortage as $s): ?>
    <li><b><?=h($s['name'])?></b> — mevcut <?=h(stock_qty_fmt($s['available_stock']))?> <?=h($s['unit'])?>,
        satış <?=h(stock_qty_fmt($s['requested_qty']))?> <?=h($s['unit'])?>,
        işlem sonrası <b style="color:#b91c1c"><?=h(stock_qty_fmt($s['resulting_stock']))?> <?=h($s['unit'])?></b></li>
    <?php endforeach; ?>
  </ul>
  <label style="display:block;background:#fef3c7;border-radius:10px;padding:10px;margin-top:8px">
    <input type="checkbox" name="allow_negative_stock" value="1" style="width:auto;display:inline-block;margin-right:6px">
    Stok yetersiz olsa da bu belgeye devam etmek istiyorum.
  </label>
</div>
<?php endif; ?>

<div class="df-form-grid-2">

<?php ds_form_field('Belge No', '<input value="'.h($doc['document_no']).'" disabled>'); ?>

<?php
$__contactOpts='<option value="">Cari seçiniz</option>';
$__selContact = $pf ? (int)($pf['contact_id'] ?? 0) : (int)$doc['contact_id'];
foreach($contacts as $c){
    $__contactOpts.='<option value="'.(int)$c['id'].'" '.($__selContact===(int)$c['id']?'selected':'').'>'.h($c['name'].' / '.$c['type']).'</option>';
}
ds_form_field('Cari', '<select name="contact_id" required>'.$__contactOpts.'</select>');
?>

<?php ds_form_field('Tarih', '<input type="date" name="document_date" value="'.h($pf['document_date'] ?? $doc['document_date']).'">'); ?>

<div class="df-form-span-2">
<?php ds_form_field('Açıklama', '<textarea name="description" rows="2">'.h($pf['description'] ?? $doc['description']).'</textarea>'); ?>
</div>

</div>

<div style="margin-top:var(--df-space-4)">
<h2 style="font-size:var(--df-type-section-size);font-weight:var(--df-type-section-weight);color:var(--df-ink-900);margin:0 0 6px">Ürün / Hizmet Satırları</h2>
<p style="color:var(--df-ink-500)">Ürün seçebilir veya olmayan ürünü elle yazabilirsiniz.</p>

<div class="df-table-wrap" style="overflow-x:auto">
<table id="itemsTable" class="df-table" style="min-width:760px">
<thead><tr><th>Mevcut Ürün</th><th>Ürün/Hizmet Adı</th><th>Birim</th><th>Miktar</th><th>Birim Fiyat</th><th>KDV %</th><th style="text-align:right">Ara Toplam</th><th style="width:36px"></th></tr></thead>
<tbody id="itemsBody"></tbody>
</table>
</div>
<button type="button" class="df-btn df-btn--secondary df-btn--sm" style="margin-top:var(--df-space-2)" onclick="addItemRow()">➕ Satır Ekle</button>
</div>

<div class="df-card" style="background:var(--df-accent-soft);border-color:transparent;margin:16px 0;padding:16px 18px">
<div style="display:flex;justify-content:space-between;padding:3px 0"><span class="df-muted">Ara Toplam</span><b id="tradeSubtotal">0,00 ₺</b></div>
<div style="display:flex;justify-content:space-between;padding:3px 0"><span class="df-muted">KDV</span><b id="tradeVat">0,00 ₺</b></div>
<div style="display:flex;justify-content:space-between;padding:8px 0 0;border-top:1px solid var(--df-hairline);margin-top:6px">
<span style="font-size:15px;font-weight:800">Genel Toplam</span>
<span id="tradeTotal" style="font-size:26px;font-weight:900;color:var(--df-accent)">0,00 ₺</span>
</div>
</div>

<button class="df-btn df-btn--primary"><?php if($stockShortage): ?>⚠️ Onaylıyorum, Devam Et<?php else: ?>💾 Değişiklikleri Kaydet<?php endif; ?></button>
<a href="trade_document_view.php?id=<?=$id?>" class="df-btn df-btn--secondary" style="margin-top:8px">✕ Vazgeç</a>

</form>
</section>

<style>
body.nav-compact .df-form-grid-2{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:0 var(--df-space-4)}
body.nav-compact .df-form-span-2{grid-column:1 / -1}
@media(max-width:640px){body.nav-compact .df-form-grid-2{grid-template-columns:1fr}}
</style>

<script>
var PRODUCTS = <?= json_encode(array_map(function($p){
    return ['id'=>(int)$p['id'],'name'=>$p['name'],'code'=>$p['product_code']];
}, $products), JSON_UNESCAPED_UNICODE) ?>;

var PREFILL_LINES = <?php
$__lines=[];
if($pf){
    $names = $pf['item_name'] ?? [];
    foreach($names as $i=>$n){
        if(trim($n)==='') continue;
        $__lines[]=['id'=>(int)($pf['stock_item_id'][$i] ?? 0),'name'=>$n,'unit'=>$pf['unit'][$i] ?? 'adet',
            'qty'=>$pf['quantity'][$i] ?? 1,'price'=>$pf['unit_price'][$i] ?? 0,'vat'=>$pf['vat_rate'][$i] ?? 20];
    }
}else{
    foreach($existingItems as $it){
        $__lines[]=['id'=>(int)$it['stock_item_id'],'name'=>$it['item_name'],'unit'=>$it['unit'],
            'qty'=>(float)$it['quantity'],'price'=>(float)$it['unit_price'],'vat'=>(float)$it['vat_rate']];
    }
}
echo json_encode($__lines, JSON_UNESCAPED_UNICODE);
?>;

function escTde(s){ return String(s).replace(/[&<>"']/g, function(c){ return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]; }); }

function tdeProductOptions(selectedId){
    var html = '<option value="">Yeni / Hizmet</option>';
    PRODUCTS.forEach(function(p){
        html += '<option value="'+p.id+'"'+(selectedId===p.id?' selected':'')+'>'+escTde((p.code?p.code+' - ':'')+p.name)+'</option>';
    });
    return html;
}

function addItemRow(prefill){
    var tr = document.createElement('tr');
    tr.innerHTML =
        '<td><select name="stock_item_id[]">'+tdeProductOptions(prefill?prefill.id:0)+'</select></td>'
        + '<td><input name="item_name[]" placeholder="Örn: PLA Siyah 1 KG veya Montaj Hizmeti" value="'+(prefill?escTde(prefill.name):'')+'"></td>'
        + '<td><input name="unit[]" value="'+(prefill?escTde(prefill.unit):'adet')+'"></td>'
        + '<td><input type="number" step="0.001" name="quantity[]" value="'+(prefill?prefill.qty:1)+'" class="row-qty" oninput="calcAll()"></td>'
        + '<td><input type="number" step="0.01" name="unit_price[]" value="'+(prefill?prefill.price:0)+'" class="row-price" oninput="calcAll()"></td>'
        + '<td><input type="number" step="0.01" name="vat_rate[]" value="'+(prefill?prefill.vat:20)+'" class="row-vat" oninput="calcAll()"></td>'
        + '<td class="row-sub" style="text-align:right;font-weight:800">0,00 ₺</td>'
        + '<td><button type="button" class="df-btn df-btn--danger df-btn--sm" onclick="removeItemRow(this)">🗑</button></td>';
    document.getElementById('itemsBody').appendChild(tr);
    calcAll();
}

function removeItemRow(btn){
    var tbody = document.getElementById('itemsBody');
    if(tbody.rows.length<=1) return;
    btn.closest('tr').remove();
    calcAll();
}

function calcAll(){
    var subtotalAll = 0, vatAll = 0;
    document.querySelectorAll('#itemsTable tbody tr').forEach(function(tr){
        var q = parseFloat(tr.querySelector('.row-qty').value) || 0;
        var p = parseFloat(tr.querySelector('.row-price').value) || 0;
        var v = parseFloat(tr.querySelector('.row-vat').value) || 0;
        var sub = q * p;
        var vatAmt = sub * v / 100;
        tr.querySelector('.row-sub').textContent = (sub + vatAmt).toLocaleString('tr-TR', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ' ₺';
        subtotalAll += sub;
        vatAll += vatAmt;
    });
    document.getElementById('tradeSubtotal').textContent = subtotalAll.toLocaleString('tr-TR', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ' ₺';
    document.getElementById('tradeVat').textContent = vatAll.toLocaleString('tr-TR', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ' ₺';
    document.getElementById('tradeTotal').textContent = (subtotalAll + vatAll).toLocaleString('tr-TR', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ' ₺';
}

if(PREFILL_LINES.length){
    PREFILL_LINES.forEach(function(l){ addItemRow(l); });
}else{
    addItemRow();
}
</script>

<?php require_once __DIR__.'/layout_bottom.php'; ?>
