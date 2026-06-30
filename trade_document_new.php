<?php
require_once __DIR__.'/boot.php';
require_login();
require_once __DIR__.'/trade_core.php';

$pdo=db();
$error='';
$type=$_GET['type'] ?? 'purchase';
if(!in_array($type,['purchase','sale'])) $type='purchase';

if($_SERVER['REQUEST_METHOD']==='POST'){
    try{
        $type=$_POST['document_type'];
        $contactId=(int)$_POST['contact_id'] ?: null;
        $accountId=(int)$_POST['account_id'] ?: null;
        $docDate=$_POST['document_date'] ?: date('Y-m-d');
        $docNo=trim($_POST['document_no']) ?: trade_next_no($type);

        $names=$_POST['item_name'] ?? [];
        $stockIds=$_POST['stock_item_id'] ?? [];
        $units=$_POST['unit'] ?? [];
        $qtys=$_POST['quantity'] ?? [];
        $prices=$_POST['unit_price'] ?? [];
        $vats=$_POST['vat_rate'] ?? [];

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
                'auto_created_product'=>$auto?1:0
            ];
        }

        if(!$prepared) throw new Exception('En az bir ürün/hizmet satırı girilmelidir.');

        $paid=(float)($_POST['paid_amount'] ?? 0);

        $pdo->prepare("INSERT INTO trade_documents(document_no,document_type,contact_id,account_id,document_date,status,subtotal,vat_total,grand_total,paid_amount,description,created_by)
            VALUES(?,?,?,?,?,'Kesinleşti',?,?,?,?,?,?)")
            ->execute([$docNo,$type,$contactId,$accountId,$docDate,$subtotal,$vatTotal,$grandTotal,$paid,trim($_POST['description']),$_SESSION['user']['id'] ?? null]);
        $docId=$pdo->lastInsertId();

        $ins=$pdo->prepare("INSERT INTO trade_document_items(document_id,stock_item_id,item_name,unit,quantity,unit_price,vat_rate,line_total,line_vat,line_grand,auto_created_product)
            VALUES(?,?,?,?,?,?,?,?,?,?,?)");

        foreach($prepared as $it){
            $ins->execute([$docId,$it['stock_item_id'],$it['item_name'],$it['unit'],$it['quantity'],$it['unit_price'],$it['vat_rate'],$it['line_total'],$it['line_vat'],$it['line_grand'],$it['auto_created_product']]);
        }

        trade_apply_document($docId);

        header("Location: trade_document_view.php?id=".$docId);
        exit;
    }catch(Throwable $e){
        $error=$e->getMessage();
    }
}

require_once __DIR__.'/layout_top.php';

$contacts=$pdo->query("SELECT id,name,type FROM contacts ORDER BY name")->fetchAll();
$products=$pdo->query("SELECT id,name,product_code,unit,purchase_price,sale_price FROM stock_items ORDER BY name")->fetchAll();
$accounts=$pdo->query("SELECT id,name,account_type,current_balance FROM finance_accounts WHERE active=1 ORDER BY account_type,name")->fetchAll();
?>

<div class="panel-head">
<h1><?=$type==='purchase'?'Alış Belgesi':'Satış Belgesi'?></h1>
<div class="actions">
<a class="btn secondary" href="trade_documents.php">Belgeler</a>
<a class="btn secondary" href="contacts.php">Cariler</a>
</div>
</div>

<?php if($error): ?><div class="alert"><?=h($error)?></div><?php endif; ?>

<section class="panel">
<form method="post" class="form-grid" id="tradeForm">

<input type="hidden" name="document_type" value="<?=$type?>">

<label>Belge No
<input name="document_no" value="<?=h(trade_next_no($type))?>">
</label>

<label>Cari
<select name="contact_id" required>
<option value="">Cari seçiniz</option>
<?php foreach($contacts as $c): ?>
<option value="<?=$c['id']?>"><?=h($c['name'].' / '.$c['type'])?></option>
<?php endforeach; ?>
</select>
</label>

<label>Tarih
<input type="date" name="document_date" value="<?=date('Y-m-d')?>">
</label>

<label>Ödeme / Tahsilat Hesabı
<select name="account_id">
<option value="">Ödeme/Tahsilat yok</option>
<?php foreach($accounts as $a): ?>
<option value="<?=$a['id']?>"><?=h($a['account_type'].' - '.$a['name'].' / '.money($a['current_balance']))?></option>
<?php endforeach; ?>
</select>
</label>

<label>Ödenen / Tahsil Edilen Tutar
<input type="number" step="0.01" name="paid_amount" value="0">
</label>

<label class="full">Açıklama
<textarea name="description" rows="2"></textarea>
</label>

<div class="full">
<h2>Ürün / Hizmet Satırları</h2>
<p class="muted">Ürün seçebilir veya olmayan ürünü elle yazabilirsiniz. Elle yazılan ürün alışta otomatik stok kartı açar.</p>

<table id="itemsTable">
<thead><tr><th>Mevcut Ürün</th><th>Ürün/Hizmet Adı</th><th>Birim</th><th>Miktar</th><th>Birim Fiyat</th><th>KDV %</th></tr></thead>
<tbody>
<?php for($i=0;$i<5;$i++): ?>
<tr>
<td>
<select name="stock_item_id[]">
<option value="">Yeni / Hizmet</option>
<?php foreach($products as $p): ?>
<option value="<?=$p['id']?>"><?=h(($p['product_code']?$p['product_code'].' - ':'').$p['name'])?></option>
<?php endforeach; ?>
</select>
</td>
<td><input name="item_name[]" placeholder="Örn: PLA Siyah 1 KG veya Montaj Hizmeti"></td>
<td><input name="unit[]" value="adet"></td>
<td><input type="number" step="0.001" name="quantity[]" value="1"></td>
<td><input type="number" step="0.01" name="unit_price[]" value="0"></td>
<td><input type="number" step="0.01" name="vat_rate[]" value="20"></td>
</tr>
<?php endfor; ?>
</tbody>
</table>
</div>

<button class="btn"><?=$type==='purchase'?'Alış Belgesini Kaydet':'Satış Belgesini Kaydet'?></button>

</form>
</section>

<?php require_once __DIR__.'/layout_bottom.php'; ?>
