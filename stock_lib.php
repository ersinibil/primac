<?php
// stock_lib.php — ortak stok yönetimi fonksiyonları
// web (purchase.php, sales.php) ve mobil (mobile/purchase.php, mobile/sales.php) tarafından kullanılır

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
            $pdo->prepare("INSERT INTO stock_movements(stock_item_id,direction,quantity,reason,note,created_at) VALUES(?,?,?,?,?,NOW())")
                ->execute([$pid, 'in', $qty, 'Alış', 'Satın alma ('.$paymentMethod.')']);
        }catch(Throwable $e){}

        return [
            'ok' => true,
            'item_id' => $pid,
            'message' => $pname.' alındı: '.mm($total).' ('.$paymentMethod.')'.($isNew?' · yeni stok kartı açıldı':''),
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
 * @param float $total tutar
 * @param string $paymentMethod ödeme yöntemi
 * @param int|null $itemId stok kartı ID'si (not için)
 * @param string $itemName ürün adı (not için)
 * @return array ['ok'=>bool, 'message'=>string]
 */
function stock_add_purchase_finance($pdo, $supplier, $total, $paymentMethod='Veresiye', $itemId=null, $itemName=''){
    try{
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

        // Finance hareketi (gider)
        $pdo->prepare("INSERT INTO finance_movements(contact_id,direction,amount,payment_channel,account_id,status,movement_date,description,movement_type)
            VALUES(?,?,?,?,?,?,?,?,'purchase')")
            ->execute([$supplier, 'out', $total, $paymentMethod, $accId, $paymentMethod==='Veresiye'?'Bekliyor':'Ödendi', date('Y-m-d'),
                $itemName.' '.($itemId?'(kartID:'.$itemId.')':'').' satın alma']);

        // Hesap bakiyesi güncelle (peşin ödeme ise)
        if($accId){
            try{
                $pdo->prepare("UPDATE finance_accounts SET current_balance=current_balance-? WHERE id=?")->execute([$total, $accId]);
            }catch(Throwable $e){}
        }

        return ['ok'=>true, 'message'=>'Finansal kayıt oluşturuldu'];
    }catch(Throwable $e){
        return ['ok'=>false, 'message'=>'Finansal kayıt başarısız: '.$e->getMessage()];
    }
}
?>
