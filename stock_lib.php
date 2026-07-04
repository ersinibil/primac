<?php
// stock_lib.php — ortak stok yönetimi fonksiyonları
// web (purchase.php, sales.php) ve mobil (mobile/purchase.php, mobile/sales.php) tarafından kullanılır

/**
 * stock_movements tablosuna MERKEZİ, doğru şemayla hareket kaydeden fonksiyon.
 * (2026-07-03 Deniz/ots-schema-drift-guard denetiminde bulundu: trade_core.php,
 * stock_movement_new.php ve mobile/stock_movement_new.php üçü de var olmayan kolonlarla
 * (movement_type, unit_cost, unit_sale, total_cost, total_sale, contact_id, supplier_id,
 * movement_date, description) INSERT yapıyordu — gerçek şema sadece: id, stock_item_id,
 * job_id, finance_movement_id, direction, quantity, reason, note, created_at içeriyor
 * (bkz. database/migrations/004_stock_products.sql + 030_stock_movement_finance_ref.sql).
 * Bu fonksiyon hatayı YUTMAZ — çağıran taraf try/catch ile karar versin.)
 *
 * @param PDO $pdo
 * @param int $stockItemId
 * @param string $direction 'in' veya 'out' (başka değer verilirse 'out' kabul edilir)
 * @param float $qty
 * @param string $reason kısa sebep etiketi (örn. 'Alış', 'Satış', 'İşte Kullanım')
 * @param string $note serbest metin not (opsiyonel)
 * @param int|null $financeMovementId finance_movements.id — varsa kesin referans
 * @param int|null $jobId ilişkili iş
 * @return int yeni stock_movements.id
 */
function stock_record_movement($pdo, $stockItemId, $direction, $qty, $reason, $note='', $financeMovementId=null, $jobId=null){
    $direction = ($direction === 'in') ? 'in' : 'out';
    $pdo->prepare("INSERT INTO stock_movements(stock_item_id,job_id,finance_movement_id,direction,quantity,reason,note,created_at) VALUES(?,?,?,?,?,?,?,NOW())")
        ->execute([(int)$stockItemId, $jobId ?: null, $financeMovementId ?: null, $direction, $qty, $reason, $note]);
    return (int)$pdo->lastInsertId();
}

/**
 * Satın alma girişi: stok kartı oluştur/güncelle + hareketi kaydet
 * @param PDO $pdo
 * @param int $supplier contact_id
 * @param string $pname ürün adı
 * @param float $qty miktar
 * @param float $price birim alış fiyatı
 * @param string $paymentMethod ödeme yöntemi (Veresiye, Peşin, Banka, Kredi Kartı, Çek, Senet)
 * @param string $unit birim (default 'adet')
 * @param float|null $salePrice satış fiyatı (yeni üründe)
 * @return array ['ok'=>bool, 'item_id'=>int, 'message'=>string, 'total'=>float, 'is_new'=>bool]
 */
function stock_add_purchase($pdo, $supplier, $pname, $qty, $price, $paymentMethod='Veresiye', $unit='adet', $salePrice=null){
    try{
        if(!$supplier) throw new Exception('Tedarikçi seçin.');
        if($pname==='') throw new Exception('Ürün adı girin.');
        if($qty<=0) throw new Exception('Miktar geçersiz.');

        $total = $qty * $price;

        // Ürün var mı? yoksa otomatik stok kartı aç
        $f = $pdo->prepare("SELECT * FROM stock_items WHERE name=? LIMIT 1");
        $f->execute([$pname]);
        $item = $f->fetch();

        if($item){
            $pid = (int)$item['id'];
            $oldQty = (float)$item['quantity'];
            $oldAvg = (float)($item['avg_cost'] ?: $item['purchase_price']);
            $newQty = $oldQty + $qty;
            $newAvg = $newQty>0 ? (($oldQty*$oldAvg)+($qty*$price))/$newQty : $price;

            $pdo->prepare("UPDATE stock_items SET quantity=?, avg_cost=?, purchase_price=?, last_purchase_price=? WHERE id=?")
                ->execute([$newQty, $newAvg, $price, $price, $pid]);
            $isNew = false;
        }else{
            $code = 'URN-'.date('ymd').'-'.random_int(100,999);
            $pdo->prepare("INSERT INTO stock_items(product_code,name,unit,quantity,critical_level,purchase_price,sale_price,avg_cost,last_purchase_price,active)
                VALUES(?,?,?,?,?,?,?,?,?,1)")
                ->execute([$code, $pname, $unit, $qty, 0, $price, ($salePrice??0), $price, $price]);
            $pid = (int)$pdo->lastInsertId();
            $isNew = true;
        }

        // Stok hareketi (giriş)
        try{
            stock_record_movement($pdo, $pid, 'in', $qty, 'Alış', 'Satın alma ('.$paymentMethod.')');
        }catch(Throwable $e){}

        return [
            'ok' => true,
            'item_id' => $pid,
            'message' => $pname.' alındı: '.money($total).' ('.$paymentMethod.')'.($isNew?' · yeni stok kartı açıldı':''),
            'total' => $total,
            'is_new' => $isNew
        ];
    }catch(Throwable $e){
        return [
            'ok' => false,
            'item_id' => null,
            'message' => $e->getMessage(),
            'total' => 0,
            'is_new' => false
        ];
    }
}

/**
 * Satın alma için finansal kaydı yap (finance_movements + account_balance)
 * @param PDO $pdo
 * @param int $supplier contact_id
 * @param float $netTotal KDV hariç (net) tutar — stok maliyeti bu tutar üzerinden hesaplanmıştı
 * @param string $paymentMethod ödeme yöntemi
 * @param int|null $itemId stok kartı ID'si (not için)
 * @param string $itemName ürün adı (not için)
 * @param float $vatRate KDV oranı (%) — 0 ise KDV hesaplanmaz
 * @return array ['ok'=>bool, 'message'=>string, 'total'=>float gerçek (KDV dahil) ödenen tutar]
 */
function stock_add_purchase_finance($pdo, $supplier, $netTotal, $paymentMethod='Veresiye', $itemId=null, $itemName='', $vatRate=0){
    try{
        $vatRate = (float)$vatRate;
        $vatAmount = $vatRate>0 ? round($netTotal*$vatRate/100, 2) : 0;
        $total = round($netTotal+$vatAmount, 2);

        // Ödeme metodundan hesap bul
        $accId = null;
        if($paymentMethod!=='Veresiye'){
            $map = ['Peşin'=>'Kasa','Banka'=>'Banka','Kredi Kartı'=>'Kredi Kartı','POS'=>'POS','Çek'=>'Diğer','Senet'=>'Diğer'];
            $type = $map[$paymentMethod] ?? 'Kasa';
            try{
                $s = $pdo->prepare("SELECT id FROM finance_accounts WHERE account_type=? AND active=1 ORDER BY id LIMIT 1");
                $s->execute([$type]);
                $r = $s->fetch();
                $accId = $r?(int)$r['id']:null;
            }catch(Throwable $e){}
        }

        // Finance hareketi (gider) — amount her zaman gerçek (KDV dahil) ödenen/borçlanılan tutar
        $pdo->prepare("INSERT INTO finance_movements(contact_id,direction,amount,vat_rate,vat_amount,payment_channel,account_id,status,movement_date,description,movement_type)
            VALUES(?,?,?,?,?,?,?,?,?,?,'purchase')")
            ->execute([$supplier, 'out', $total, $vatRate>0?$vatRate:null, $vatAmount, $paymentMethod, $accId, $paymentMethod==='Veresiye'?'Bekliyor':'Ödendi', date('Y-m-d'),
                $itemName.' '.($itemId?'(kartID:'.$itemId.')':'').' satın alma']);

        // Hesap bakiyesi güncelle (peşin ödeme ise) — KDV dahil gerçek tutar düşülür
        if($accId){
            try{
                $pdo->prepare("UPDATE finance_accounts SET current_balance=current_balance-? WHERE id=?")->execute([$total, $accId]);
            }catch(Throwable $e){}
        }

        return ['ok'=>true, 'message'=>'Finansal kayıt oluşturuldu', 'total'=>$total, 'vat_amount'=>$vatAmount];
    }catch(Throwable $e){
        return ['ok'=>false, 'message'=>'Finansal kayıt başarısız: '.$e->getMessage(), 'total'=>$netTotal, 'vat_amount'=>0];
    }
}

/**
 * Satış silme: stoku geri koy, stok hareketi sil, finans hareketi sil/geri al
 * Bir satışta BİRDEN FAZLA ürün satırı olabilir (sepet) — aynı finance_movement_id'ye
 * bağlı TÜM stock_movements satırları tek tek geri alınır (2026-07-03: tekli üründen
 * çoklu ürün sepetine geçişle birlikte, eski LIMIT 1 varsayımı kaldırıldı).
 * @param PDO $pdo
 * @param int $saleId finance_movements kaydının ID'si (movement_type='sale' veya 'mobile_sale')
 * @return array ['ok'=>bool, 'message'=>string]
 */
function stock_reverse_sale($pdo, $saleId){
    require_once __DIR__.'/finance_lib.php';

    try{
        $saleId = (int)$saleId;
        if($saleId < 1) return ['ok'=>false, 'message'=>'Geçersiz satış kaydı.'];

        // Satış kaydını bul
        $s = $pdo->prepare("SELECT * FROM finance_movements WHERE id=? AND (movement_type='sale' OR movement_type='mobile_sale')");
        $s->execute([$saleId]);
        $sale = $s->fetch();
        if(!$sale) return ['ok'=>false, 'message'=>'Satış kaydı bulunamadı.'];

        // İlişkili TÜM stok hareketlerini KESİN referansla (finance_movement_id) bul — sepette
        // birden fazla ürün satırı varsa hepsi aynı finance_movement_id'yi taşır.
        $stk = $pdo->prepare("SELECT * FROM stock_movements WHERE finance_movement_id=?");
        $stk->execute([$saleId]);
        $movements = $stk->fetchAll();

        foreach($movements as $movement){
            $itemId = $movement['stock_item_id'];
            $qty = $movement['quantity'];

            // Stoku geri koy
            try{
                $pdo->prepare("UPDATE stock_items SET quantity=quantity+? WHERE id=?")->execute([$qty, $itemId]);
            }catch(Throwable $e){}

            // Stok hareketini sil
            try{
                $pdo->prepare("DELETE FROM stock_movements WHERE id=?")->execute([$movement['id']]);
            }catch(Throwable $e){}
        }

        // Finans hareketini geri al — finance_movement_delete() 'sale'/'mobile_sale' tipini kasıtlı
        // olarak reddediyor (normal finans düzenleme ekranından dokunulmasın diye), bu yüzden burada
        // bakiye geri alma + silme DOĞRUDAN yapılıyor (finance_movement_reverse_balance() ortak fonksiyonu).
        finance_movement_reverse_balance($pdo, $sale);
        $pdo->prepare("DELETE FROM finance_movements WHERE id=?")->execute([$saleId]);

        return ['ok'=>true, 'message'=>'Satış silindi, stok ve bakiyeler geri alındı.'];
    }catch(Throwable $e){
        return ['ok'=>false, 'message'=>'Satış silme hatası: '.$e->getMessage()];
    }
}
?>
