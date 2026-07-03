<?php
require_once 'common.php';
if(!user_can('stock')){ header('Location: index.php'); exit; }
require_once __DIR__.'/../activity_lib.php';
require_once __DIR__.'/../stock_lib.php';
$pdo=db();
$error='';
$type=$_GET['type'] ?? 'in';
if(!in_array($type,['in','out','sale','use'])) $type='in';
$productId=(int)($_GET['product_id'] ?? 0);

if($_SERVER['REQUEST_METHOD']==='POST'){
    try{
        $productId=(int)$_POST['stock_item_id'];
        $movementType=$_POST['movement_type'];
        if(!in_array($movementType,['in','out','sale','use'],true)) $movementType='in';
        $qty=(float)$_POST['quantity'];
        $unitCost=(float)$_POST['unit_cost'];
        $unitSale=(float)$_POST['unit_sale'];
        if(!$productId) throw new Exception('Ürün seçilmelidir.');
        if($qty<=0) throw new Exception('Miktar sıfırdan büyük olmalı.');

        $st=$pdo->prepare("SELECT * FROM stock_items WHERE id=?");
        $st->execute([$productId]);
        $p=$st->fetch();
        if(!$p) throw new Exception('Ürün bulunamadı.');

        if($unitCost<=0) $unitCost=(float)($p['avg_cost'] ?: $p['purchase_price']);
        if($unitSale<=0) $unitSale=(float)$p['sale_price'];

        $totalCost=$qty*$unitCost;
        $totalSale=($movementType==='out' || $movementType==='sale') ? $qty*$unitSale : 0;
        $direction=$movementType==='in' ? 'in' : 'out';

        // Gerçek stock_movements şeması movement_type/unit_cost/unit_sale/total_cost/total_sale/
        // contact_id/supplier_id/movement_date/description kolonlarını İÇERMİYOR (2026-07-03 schema
        // drift düzeltmesi) — bu bilgiler reason/note metnine gömülür, stok kartı hâlâ doğru güncellenir.
        $reasonMap=['in'=>'Alış / Stok Giriş','out'=>'Satış / Stok Çıkış','sale'=>'Satış','use'=>'İşte Kullanım'];
        $reason=$reasonMap[$movementType] ?? $movementType;

        $contactName=null;
        if(!empty($_POST['contact_id'])){
            $cs=$pdo->prepare("SELECT name FROM contacts WHERE id=?"); $cs->execute([(int)$_POST['contact_id']]);
            $contactName=$cs->fetchColumn() ?: null;
        }
        $supplierName=null;
        if(!empty($_POST['supplier_id'])){
            $ss=$pdo->prepare("SELECT name FROM contacts WHERE id=?"); $ss->execute([(int)$_POST['supplier_id']]);
            $supplierName=$ss->fetchColumn() ?: null;
        }
        $jobId=(int)$_POST['job_id'] ?: null;

        $noteParts=[];
        $desc=trim($_POST['description'] ?? '');
        if($desc!=='') $noteParts[]=$desc;
        $noteParts[]='Birim maliyet: '.mm($unitCost).' · Birim satış: '.mm($unitSale);
        if($contactName) $noteParts[]='Cari: '.$contactName;
        if($supplierName) $noteParts[]='Tedarikçi: '.$supplierName;
        $moveDate=$_POST['movement_date'] ?: date('Y-m-d');
        if($moveDate!==date('Y-m-d')) $noteParts[]='Tarih: '.$moveDate;
        $note=implode(' · ',$noteParts);

        // Stok miktarı güncellemesi ile hareket kaydı tek transaction içinde — biri başarısız
        // olursa diğeri de kaydedilmez (mantıksal tutarlılık).
        $pdo->beginTransaction();
        try{
            if($movementType==='in'){
                $oldQty=(float)$p['quantity'];
                $oldAvg=(float)($p['avg_cost'] ?: $p['purchase_price']);
                $newQty=$oldQty+$qty;
                $newAvg=$newQty>0 ? (($oldQty*$oldAvg)+($qty*$unitCost))/$newQty : $unitCost;
                $pdo->prepare("UPDATE stock_items SET quantity=?, avg_cost=?, last_purchase_price=?, purchase_price=? WHERE id=?")
                    ->execute([$newQty,$newAvg,$unitCost,$unitCost,$productId]);
            }else{
                $pdo->prepare("UPDATE stock_items SET quantity=quantity-? WHERE id=?")->execute([$qty,$productId]);
            }

            $moveId=stock_record_movement($pdo,$productId,$direction,$qty,$reason,$note,null,$jobId);

            $pdo->commit();
        }catch(Throwable $e){
            $pdo->rollBack();
            throw $e;
        }

        if($movementType==='in'){
            activity_log('Stok','Stok Giriş','Stok girişi yapıldı',$p['name'].' · +'.$qty.' '.$p['unit'].' · '.money($unitCost),'stock',$moveId,'product_view.php?id='.$productId,'📦');
        }else{
            activity_log('Stok','Stok Çıkış','Stok çıkışı yapıldı',$p['name'].' · -'.$qty.' '.$p['unit'].' · Kâr: '.money($totalSale-$totalCost),'stock',$moveId,'product_view.php?id='.$productId,'📤');
        }

        header("Location: product_view.php?id=".$productId);
        exit;
    }catch(Throwable $e){ $error=$e->getMessage(); }
}

$products=$pdo->query("SELECT * FROM stock_items WHERE active=1 ORDER BY name")->fetchAll();
$contacts=$pdo->query("SELECT * FROM contacts ORDER BY name")->fetchAll();
$suppliers=$pdo->query("SELECT * FROM contacts WHERE type IN ('Tedarikçi','Her İkisi') ORDER BY name")->fetchAll();
$jobs=$pdo->query("SELECT id,job_no,title FROM jobs ORDER BY id DESC LIMIT 100")->fetchAll();

topx($type==='in'?'Stok Giriş / Alış':'Stok Çıkış / Satış');
?>
<?php if($error): ?><div class="err"><?=htmlspecialchars($error)?></div><?php endif; ?>
<div class="panel">
<form method="post">
  <label style="color:#94a3b8;font-size:12px">Hareket Tipi</label>
  <select name="movement_type">
    <option value="in" <?=$type==='in'?'selected':''?>>Alış / Stok Giriş</option>
    <option value="out" <?=$type==='out'?'selected':''?>>Satış / Stok Çıkış</option>
    <option value="use" <?=$type==='use'?'selected':''?>>İşte Kullanım</option>
  </select>

  <label style="color:#94a3b8;font-size:12px">Ürün</label>
  <select name="stock_item_id" required>
    <option value="">Seçiniz</option>
    <?php foreach($products as $p): ?>
    <option value="<?=$p['id']?>" <?=$productId===(int)$p['id']?'selected':''?>><?=htmlspecialchars(($p['product_code']?$p['product_code'].' - ':'').$p['name'].' / Stok: '.$p['quantity'].' '.$p['unit'])?></option>
    <?php endforeach; ?>
  </select>

  <label style="color:#94a3b8;font-size:12px">Miktar</label>
  <input type="number" step="0.001" name="quantity" required>

  <label style="color:#94a3b8;font-size:12px">Birim Maliyet / Alış</label>
  <input type="number" step="0.01" name="unit_cost" value="0">

  <label style="color:#94a3b8;font-size:12px">Birim Satış</label>
  <input type="number" step="0.01" name="unit_sale" value="0">

  <label style="color:#94a3b8;font-size:12px">Tarih</label>
  <input type="date" name="movement_date" value="<?=date('Y-m-d')?>">

  <label style="color:#94a3b8;font-size:12px">Tedarikçi</label>
  <select name="supplier_id">
    <option value="">Seçiniz</option>
    <?php foreach($suppliers as $s): ?><option value="<?=$s['id']?>"><?=htmlspecialchars($s['name'])?></option><?php endforeach; ?>
  </select>

  <label style="color:#94a3b8;font-size:12px">Müşteri / Cari</label>
  <select name="contact_id">
    <option value="">Seçiniz</option>
    <?php foreach($contacts as $c): ?><option value="<?=$c['id']?>"><?=htmlspecialchars($c['name'].' / '.$c['type'])?></option><?php endforeach; ?>
  </select>

  <label style="color:#94a3b8;font-size:12px">İş Bağlantısı</label>
  <select name="job_id">
    <option value="">Seçiniz</option>
    <?php foreach($jobs as $j): ?><option value="<?=$j['id']?>"><?=htmlspecialchars($j['job_no'].' - '.$j['title'])?></option><?php endforeach; ?>
  </select>

  <label style="color:#94a3b8;font-size:12px">Açıklama</label>
  <textarea name="description" rows="3"></textarea>

  <button class="btn dark" style="width:100%;padding:13px;margin-top:8px">Hareketi Kaydet</button>
</form>
</div>
<?php botx(); ?>
