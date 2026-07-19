<?php
require_once __DIR__.'/boot.php';
require_login();
require_once __DIR__.'/trade_core.php';

$pdo=db();
$error='';
$stockShortage=null; // KONTROLLÜ NEGATİF STOK POLİTİKASI — bkz. stock_lib.php (Flow Unification 001, 2026-07-11)
$type=$_GET['type'] ?? 'purchase';
if(!in_array($type,['purchase','sale'])) $type='purchase';

if($_SERVER['REQUEST_METHOD']==='POST'){
    $confirmNegativeStock = !empty($_POST['allow_negative_stock']);
    try{
        $type=$_POST['document_type'];
        if(!in_array($type,['purchase','sale'])) $type='purchase';
        $contactId=(int)$_POST['contact_id'] ?: null;
        $docDate=$_POST['document_date'] ?: date('Y-m-d');
        $docNo=trim($_POST['document_no']) ?: trade_next_no($type);

        $names=$_POST['item_name'] ?? [];
        $stockIds=$_POST['stock_item_id'] ?? [];
        $units=$_POST['unit'] ?? [];
        $qtys=$_POST['quantity'] ?? [];
        $prices=$_POST['unit_price'] ?? [];
        $vats=$_POST['vat_rate'] ?? [];
        // P0 CPA KULLANICI AKIŞI (2026-07-18, Product Owner kararı 3. madde) — opsiyonel, sadece
        // Alış Belgesi'nde anlamlı. $_POST'tan burada, $i hâlâ orijinal satır index'iyken okunup
        // AŞAĞIDA $prepared[] içine gömülüyor — $prepared sıralı push ile oluştuğu için (boş satırlar
        // atlanır) sonradan orijinal index'e göre eşleştirmeye çalışmak yanlış hizalanabilirdi.
        $allocCids=$_POST['alloc_customer_id'] ?? [];
        $allocQtys=$_POST['alloc_qty'] ?? [];

        $subtotal=0;
        $vatTotal=0;
        $grandTotal=0;
        $prepared=[];

        foreach($names as $i=>$name){
            $name=trim($name);
            if($name==='') continue;

            $qty=(float)($qtys[$i] ?? 1);
            $price=(float)($prices[$i] ?? 0);
            $vat=(float)($vats[$i] ?? 20);
            $unit=trim($units[$i] ?? 'adet');
            $stockId=(int)($stockIds[$i] ?? 0);

            if($qty<=0) $qty=1;

            $auto=false;
            if(!$stockId){
                list($stockId,$auto)=trade_ensure_product($name,$unit,$price,$type);
            }

            $line=$qty*$price;
            $lineVat=$line*$vat/100;
            $lineGrand=$line+$lineVat;

            $subtotal+=$line;
            $vatTotal+=$lineVat;
            $grandTotal+=$lineGrand;

            $prepared[]=[
                'stock_item_id'=>$stockId,
                'item_name'=>$name,
                'unit'=>$unit,
                'quantity'=>$qty,
                'unit_price'=>$price,
                'vat_rate'=>$vat,
                'line_total'=>$line,
                'line_vat'=>$lineVat,
                'line_grand'=>$lineGrand,
                'auto_created_product'=>$auto?1:0,
                'alloc_customer_id'=>(int)($allocCids[$i] ?? 0),
                'alloc_qty'=>(float)($allocQtys[$i] ?? 0),
            ];
        }

        if(!$prepared) throw new Exception('En az bir ürün/hizmet satırı girilmelidir.');

        // KONTROLLÜ NEGATİF STOK POLİTİKASI — hiçbir tabloya yazmadan ÖNCE ön kontrol (Flow
        // Unification 001, 2026-07-11): satış belgesinde stok yetersizse ve kullanıcı onay
        // vermediyse, StockShortageException burada (transaction açılmadan, hiçbir INSERT
        // olmadan) fırlar — sales.php ile aynı davranış.
        if($type==='sale'){
            $ids=[]; $qtysChk=[]; $pricesChk=[]; $vatsChk=[];
            foreach($prepared as $it){
                $ids[]=$it['stock_item_id'];
                $qtysChk[]=$it['quantity'];
                $pricesChk[]=$it['unit_price'];
                $vatsChk[]=$it['vat_rate'];
            }
            stock_sale_build_lines($pdo, $ids, $qtysChk, $pricesChk, $vatsChk, [], $confirmNegativeStock);
        }

        // Belge + satırlar + stok/finans etkisi TEK transaction içinde — transaction sahibi
        // burasıdır, trade_apply_document() kendi transaction'ını açmaz/kapatmaz
        // (stock_create_purchase()/stock_create_sale() $pdo->inTransaction() kontrolüyle bunu
        // bilir). Herhangi bir adım hata verirse TAMAMI rollback edilir.
        $pdo->beginTransaction();
        try{
            $pdo->prepare("INSERT INTO trade_documents(document_no,document_type,contact_id,account_id,document_date,status,subtotal,vat_total,grand_total,paid_amount,description,created_by)
                VALUES(?,?,?,NULL,?,'Kesinleşti',?,?,?,0,?,?)")
                ->execute([$docNo,$type,$contactId,$docDate,$subtotal,$vatTotal,$grandTotal,trim($_POST['description']),$_SESSION['user']['id'] ?? null]);
            $docId=$pdo->lastInsertId();

            $ins=$pdo->prepare("INSERT INTO trade_document_items(document_id,stock_item_id,item_name,unit,quantity,unit_price,vat_rate,line_total,line_vat,line_grand,auto_created_product)
                VALUES(?,?,?,?,?,?,?,?,?,?,?)");

            foreach($prepared as $it){
                $ins->execute([$docId,$it['stock_item_id'],$it['item_name'],$it['unit'],$it['quantity'],$it['unit_price'],$it['vat_rate'],$it['line_total'],$it['line_vat'],$it['line_grand'],$it['auto_created_product']]);
            }

            trade_apply_document($docId, $confirmNegativeStock);

            // P0 CPA KULLANICI AKIŞI (2026-07-18, Product Owner kararı 3. madde) — opsiyonel satır
            // bazlı "Müşteriye Ayır". Sadece Alış Belgesi'nde anlamlı. trade_apply_document() bu
            // belgenin finance_movements(movement_type='purchase') satırını document_id ile OLUŞTURUR
            // (stock_create_purchase() üzerinden) — burada TAZE okunuyor. Tahsis oluşturma opsiyonel
            // bir yan etkidir, best-effort: hata fırlatırsa YUTULUR, belgenin kendisi asla bundan
            // etkilenmez/rollback edilmez (cpa_alloc_consume_for_sale() ile AYNI felsefe).
            if($type==='purchase'){
                $__pmRow = $pdo->prepare("SELECT id FROM finance_movements WHERE document_id=? AND movement_type='purchase'");
                $__pmRow->execute([$docId]);
                $__purchaseMovementId = (int)($__pmRow->fetch()['id'] ?? 0);
                if($__purchaseMovementId){
                    foreach($prepared as $__it){
                        if(!$__it['stock_item_id'] || !$__it['alloc_customer_id']) continue;
                        $__aqty = $__it['alloc_qty'] > 0 ? $__it['alloc_qty'] : $__it['quantity'];
                        try{ cpa_alloc_create($pdo, $_SESSION['user']['id']??0, $__purchaseMovementId, $__it['stock_item_id'], $__it['alloc_customer_id'], $__aqty, 'Belge oluştururken ayrıldı'); }catch(Throwable $e){}
                    }
                }
            }

            // inTransaction() korumalı commit (2026-07-11): activity_log() -> activity_install()
            // "CREATE TABLE IF NOT EXISTS activity_logs" çalıştırıyor — bu bir DDL ifadesi ve MySQL/
            // MariaDB DDL'de İMPLİCİT COMMIT yapar (tablo zaten var olsa bile). trade_apply_document()
            // başarıyla bitip activity_log()'a ulaştığında transaction bu yüzden ERKEN ve SESSİZCE
            // kapanmış olabilir — commit() o noktada zaten kapalı bir transaction'a çağrılırsa
            // PDOException ("There is no active transaction") fırlatır ve BAŞARILI bir işlemi
            // hatalıymış gibi gösterir. inTransaction() hâlâ açıksa normal commit yapılır; DDL
            // yüzünden zaten kapanmışsa (veri zaten commit edilmiş durumda) tekrar commit denenmez.
            if($pdo->inTransaction()) $pdo->commit();
        }catch(Throwable $e){
            if($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }

        header("Location: trade_document_view.php?id=".$docId);
        exit;
    }catch(StockShortageException $e){
        $stockShortage = $e->shortages;
    }catch(Throwable $e){
        $error=$e->getMessage();
    }
}

// Stok yetersiz uyarısı bekliyorsa formu kullanıcının az önce POSTladığı ham değerlerle yeniden
// doldur (sales.php ile aynı desen) — aksi halde varsayılan boş form.
$pf = $stockShortage ? $_POST : null;

require_once __DIR__.'/layout_top.php';

$contacts=$pdo->query("SELECT id,name,type FROM contacts ORDER BY name")->fetchAll();
$products=$pdo->query("SELECT id,name,product_code,unit,purchase_price,sale_price FROM stock_items ORDER BY name")->fetchAll();
// P0 CPA KULLANICI AKIŞI (2026-07-18) — opsiyonel "Müşteriye Ayır" satır alanı, sadece Alış Belgesi'nde.
$__allocCustomers=[];
$__allowAlloc = $type==='purchase' && cpa_alloc_can_edit();
if($__allowAlloc){
    try{ $__allocCustomers=$pdo->query("SELECT id,name FROM contacts WHERE type IN ('Müşteri','Her İkisi') ORDER BY name")->fetchAll(); }catch(Throwable $e){}
}
?>

<?php
$__actions = ds_button('Belgeler', 'trade_documents.php', 'secondary', '', '', true)
    . ds_button('Cariler', 'contacts.php', 'secondary', '', '', true);
ds_page_header($type==='purchase'?'Alış Belgesi':'Satış Belgesi', ds_icon('tag',24), '', $__actions, false, true);
?>

<?php if($error): ?><?=ds_alert('danger',$error)?><?php endif; ?>

<section class="df-card">
<div class="notice" style="margin-bottom:12px;background:#eef4ff;color:#1e3a8a">
Bu ekran tahsilat/ödeme yapmaz — belge cariye açık borç/alacak (Bekliyor) olarak kaydedilir.
Kapanış <a href="finance_new.php">Tahsilat/Ödeme ekranından</a> ayrıca girilir.
</div>
<form method="post" id="tradeForm">

<input type="hidden" name="document_type" value="<?=$type?>">

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
    Stok yetersiz olsa da bu belgeye devam etmek istiyorum (satın alım daha sonra tamamlanacak).
  </label>
</div>
<?php endif; ?>

<div class="df-form-grid-2">

<?php ds_form_field('Belge No', '<input name="document_no" value="'.h($pf['document_no'] ?? trade_next_no($type)).'">'); ?>

<?php
$__contactOpts='<option value="">Cari seçiniz</option>';
foreach($contacts as $c){
    $__contactOpts.='<option value="'.(int)$c['id'].'" '.((isset($pf) && (int)($pf['contact_id'] ?? 0)===(int)$c['id'])?'selected':'').'>'.h($c['name'].' / '.$c['type']).'</option>';
}
ds_form_field('Cari', '<select name="contact_id" required>'.$__contactOpts.'</select>');
?>

<?php ds_form_field('Tarih', '<input type="date" name="document_date" value="'.h($pf['document_date'] ?? date('Y-m-d')).'">'); ?>

<div class="df-form-span-2">
<?php ds_form_field('Açıklama', '<textarea name="description" rows="2">'.h($pf['description'] ?? '').'</textarea>'); ?>
</div>

</div>

<div style="margin-top:var(--df-space-4)">
<h2 style="font-size:var(--df-type-section-size);font-weight:var(--df-type-section-weight);color:var(--df-ink-900);margin:0 0 6px">Ürün / Hizmet Satırları</h2>
<p style="color:var(--df-ink-500)">Ürün seçebilir veya olmayan ürünü elle yazabilirsiniz. Elle yazılan ürün alışta otomatik stok kartı açar.</p>

<div class="df-table-wrap" style="overflow-x:auto">
<table id="itemsTable" class="df-table" style="min-width:720px">
<thead><tr><th>Mevcut Ürün</th><th>Ürün/Hizmet Adı</th><th>Birim</th><th>Miktar</th><th>Birim Fiyat</th><th>KDV %</th><th style="text-align:right">Ara Toplam</th><?php if($__allowAlloc): ?><th>Müşteriye Ayır <small class="df-muted">(opsiyonel)</small></th><?php endif; ?></tr></thead>
<tbody>
<?php for($i=0;$i<5;$i++): ?>
<tr>
<td>
<select name="stock_item_id[]">
<option value="">Yeni / Hizmet</option>
<?php foreach($products as $p): ?>
<option value="<?=$p['id']?>" <?=(isset($pf) && (int)($pf['stock_item_id'][$i] ?? 0)===(int)$p['id'])?'selected':''?>><?=h(($p['product_code']?$p['product_code'].' - ':'').$p['name'])?></option>
<?php endforeach; ?>
</select>
</td>
<td><input name="item_name[]" placeholder="Örn: PLA Siyah 1 KG veya Montaj Hizmeti" value="<?=h($pf['item_name'][$i] ?? '')?>"></td>
<td><input name="unit[]" value="<?=h($pf['unit'][$i] ?? 'adet')?>"></td>
<td><input type="number" step="0.001" name="quantity[]" value="<?=h($pf['quantity'][$i] ?? '1')?>" class="row-qty" oninput="calcAll()"></td>
<td><input type="number" step="0.01" name="unit_price[]" value="<?=h($pf['unit_price'][$i] ?? '0')?>" class="row-price" oninput="calcAll()"></td>
<td><input type="number" step="0.01" name="vat_rate[]" value="<?=h($pf['vat_rate'][$i] ?? '20')?>" class="row-vat" oninput="calcAll()"></td>
<td class="row-sub" style="text-align:right;font-weight:800">0,00 ₺</td>
<?php if($__allowAlloc): ?>
<td>
<select name="alloc_customer_id[]" style="margin-bottom:4px">
<option value="">— Müşteri yok —</option>
<?php foreach($__allocCustomers as $c): ?>
<option value="<?=(int)$c['id']?>" <?=(isset($pf) && (int)($pf['alloc_customer_id'][$i] ?? 0)===(int)$c['id'])?'selected':''?>><?=h($c['name'])?></option>
<?php endforeach; ?>
</select>
<input type="number" step="0.001" min="0" name="alloc_qty[]" placeholder="Miktar (boşsa tümü)" value="<?=h($pf['alloc_qty'][$i] ?? '')?>">
</td>
<?php endif; ?>
</tr>
<?php endfor; ?>
</tbody>
</table>
</div>

<div class="df-card" style="background:var(--df-accent-soft);border-color:transparent;margin:16px 0;padding:16px 18px">
<div style="display:flex;justify-content:space-between;padding:3px 0"><span class="df-muted">Ara Toplam</span><b id="tradeSubtotal">0,00 ₺</b></div>
<div style="display:flex;justify-content:space-between;padding:3px 0"><span class="df-muted">KDV</span><b id="tradeVat">0,00 ₺</b></div>
<div style="display:flex;justify-content:space-between;padding:8px 0 0;border-top:1px solid var(--df-hairline);margin-top:6px">
<span style="font-size:15px;font-weight:800">Genel Toplam</span>
<span id="tradeTotal" style="font-size:26px;font-weight:900;color:var(--df-accent)">0,00 ₺</span>
</div>
</div>
</div>

<button class="df-btn df-btn--primary"><?php if($stockShortage): ?>⚠️ Onaylıyorum, Devam Et<?php else: ?><?=$type==='purchase'?'Alış Belgesini Kaydet':'Satış Belgesini Kaydet'?><?php endif; ?></button>

</form>
</section>

<style>
body.nav-compact .df-form-grid-2{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:0 var(--df-space-4)}
body.nav-compact .df-form-span-2{grid-column:1 / -1}
@media(max-width:640px){body.nav-compact .df-form-grid-2{grid-template-columns:1fr}}
</style>

<script>
var TRADE_GRAND_TOTAL = 0;

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
    TRADE_GRAND_TOTAL = subtotalAll + vatAll;
    document.getElementById('tradeSubtotal').textContent = subtotalAll.toLocaleString('tr-TR', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ' ₺';
    document.getElementById('tradeVat').textContent = vatAll.toLocaleString('tr-TR', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ' ₺';
    document.getElementById('tradeTotal').textContent = TRADE_GRAND_TOTAL.toLocaleString('tr-TR', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ' ₺';
}

calcAll();
</script>

<?php require_once __DIR__.'/layout_bottom.php'; ?>
