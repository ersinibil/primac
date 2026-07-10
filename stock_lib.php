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

/** Miktarı gereksiz ondalık sıfırları olmadan biçimlendirir (örn. 2.50 -> "2.5", 3.00 -> "3"). */
function stock_qty_fmt($v){
    return rtrim(rtrim(number_format((float)$v, 2, '.', ''), '0'), '.');
}

// stock_movements.unit_price/vat_rate migration 043'ü henüz çalışmamış bir kurulumda olabilir
// (ACANS/PRIMAC ayrı DB, "kod dağıt" ve "migrate.php çalıştır" ayrı adımlar) — personnel_lib.php'deki
// personnel_has_cv_column() ile aynı güvenli-kontrol deseni.
function stock_movements_has_line_pricing($pdo){
    static $has=null;
    if($has===null){
        try{
            $chk=$pdo->query("SHOW COLUMNS FROM stock_movements LIKE 'unit_price'");
            $has=(bool)$chk->fetch();
        }catch(Throwable $e){ $has=false; }
    }
    return $has;
}

/**
 * Bir satış satırının stock_movements kaydını oluşturur. Migration 043 henüz çalışmamışsa
 * (kolonlar yok) eski (6 kolonlu, fiyatsız) INSERT'e güvenle düşer — "yeni satış" akışının
 * temel işlevi migration'a bağımlı hale gelmesin (CLAUDE.md kural 6).
 */
function stock_insert_sale_movement($pdo, $stockItemId, $financeMovementId, $qty, $unitPrice, $vatRate, $reason, $note){
    if(stock_movements_has_line_pricing($pdo)){
        $pdo->prepare("INSERT INTO stock_movements(stock_item_id,finance_movement_id,direction,quantity,unit_price,vat_rate,reason,note,created_at) VALUES(?,?,?,?,?,?,?,?,NOW())")
            ->execute([$stockItemId, $financeMovementId, 'out', $qty, $unitPrice, $vatRate, $reason, $note]);
    }else{
        $pdo->prepare("INSERT INTO stock_movements(stock_item_id,finance_movement_id,direction,quantity,reason,note,created_at) VALUES(?,?,?,?,?,?,NOW())")
            ->execute([$stockItemId, $financeMovementId, 'out', $qty, $reason, $note]);
    }
}

/**
 * Satış ödeme yöntemine göre kasa/banka hesabı bulur. Veresiye: henüz tahsil edilmedi,
 * hiçbir hesaba işlenmez (stock_add_purchase_finance ile aynı desen). Web (sales.php) ve
 * mobil (mobile/sales.php) satış ekle/düzenle akışlarının ortak hesap-çözümleme mantığı.
 */
function stock_sale_resolve_account($pdo, $method){
    if($method === 'Veresiye') return null;
    $map = ['Peşin'=>'Kasa', 'Kredi Kartı'=>'Kredi Kartı', 'Banka Havalesi'=>'Banka'];
    $type = isset($map[$method]) ? $map[$method] : 'Kasa';
    try{
        $s = $pdo->prepare("SELECT id FROM finance_accounts WHERE account_type=? AND active=1 ORDER BY id LIMIT 1");
        $s->execute([$type]);
        $r = $s->fetch();
        return $r ? (int)$r['id'] : null;
    }catch(Throwable $e){
        return null;
    }
}

/**
 * POST'tan gelen ürün satırlarını doğrular, ürünleri çeker, satır/genel toplamları hesaplar.
 * Web (sales.php) ve mobil (mobile/sales.php) satış ekle/düzenle akışlarının ortak mantığı
 * (2026-07-10, satış düzenleme özelliğiyle birlikte tekrarlanan kod ortaklaştırıldı).
 * @throws Exception geçersiz girdi durumunda
 * @return array ['lines'=>[...], 'grand_total'=>float, 'grand_vat'=>float, 'profit_total'=>float, 'desc'=>string, 'desc_parts'=>array]
 */
function stock_sale_build_lines($pdo, $ids, $qtys, $prices, $vatRates){
    if(!is_array($ids) || !count($ids)) throw new Exception('En az bir ürün satırı ekleyin.');

    $lines = [];
    foreach($ids as $i=>$pid){
        $pid = (int)$pid;
        $qty = (float)($qtys[$i] ?? 0);
        $price = (float)($prices[$i] ?? 0);
        $vatRate = (float)($vatRates[$i] ?? 0);
        if(!$pid || $qty<=0) continue;
        if($price<0) throw new Exception('Fiyat geçersiz.');

        $p = $pdo->prepare("SELECT * FROM stock_items WHERE id=?");
        $p->execute([$pid]);
        $item = $p->fetch();
        if(!$item) continue;

        $subtotal = $qty*$price;
        $vatAmount = $vatRate>0 ? round($subtotal*$vatRate/100, 2) : 0;
        $lineTotal = round($subtotal+$vatAmount, 2);
        $cost = (float)(isset($item['avg_cost']) && $item['avg_cost'] ? $item['avg_cost'] : (isset($item['purchase_price']) ? $item['purchase_price'] : 0));

        $lines[] = [
            'item'=>$item, 'qty'=>$qty, 'price'=>$price, 'vat_rate'=>$vatRate,
            'vat_amount'=>$vatAmount, 'line_total'=>$lineTotal, 'profit'=>($price-$cost)*$qty,
        ];
    }
    if(!$lines) throw new Exception('En az bir geçerli ürün satırı ekleyin.');

    $grandTotal=0; $grandVat=0; $profitTotal=0; $descParts=[];
    foreach($lines as $l){
        $grandTotal += $l['line_total'];
        $grandVat   += $l['vat_amount'];
        $profitTotal += $l['profit'];
        $descParts[] = $l['item']['name'] . ' x' . stock_qty_fmt($l['qty']);
    }
    $desc = implode(', ', $descParts) . ' satış';

    return ['lines'=>$lines, 'grand_total'=>$grandTotal, 'grand_vat'=>$grandVat, 'profit_total'=>$profitTotal, 'desc'=>$desc, 'desc_parts'=>$descParts];
}

/**
 * Bir satışın "Düzenle" ekranında GÜVENLE açılıp açılamayacağını belirler (migration 043,
 * stock_movements.unit_price/vat_rate). Satır bazlı fiyatı olmayan (migration öncesi) satışlarda
 * tahmin/eşit bölme YAPILMAZ — sadece tek satırlı eski satışlarda toplamdan güvenle türetilir.
 * @return array ['editable'=>bool, 'reason'=>string|null, 'sale'=>array|null, 'lines'=>array]
 *   lines: [['stock_item_id','name','unit','quantity','unit_price','vat_rate','derived'=>bool]]
 */
function stock_can_edit_sale($pdo, $saleId){
    $saleId = (int)$saleId;
    $s = $pdo->prepare("SELECT * FROM finance_movements WHERE id=? AND (movement_type='sale' OR movement_type='mobile_sale')");
    $s->execute([$saleId]);
    $sale = $s->fetch();
    if(!$sale) return ['editable'=>false, 'reason'=>'Satış kaydı bulunamadı.', 'sale'=>null, 'lines'=>[]];

    $stk = $pdo->prepare("SELECT sm.*, si.name AS item_name, si.unit AS item_unit FROM stock_movements sm LEFT JOIN stock_items si ON si.id=sm.stock_item_id WHERE sm.finance_movement_id=? ORDER BY sm.id");
    $stk->execute([$saleId]);
    $rows = $stk->fetchAll();
    if(!$rows) return ['editable'=>false, 'reason'=>'Bu satışa bağlı stok hareketi bulunamadı.', 'sale'=>$sale, 'lines'=>[]];

    $missingPrice = false;
    foreach($rows as $r){ if(($r['unit_price'] ?? null) === null) $missingPrice = true; }

    if(!$missingPrice){
        $lines=[];
        foreach($rows as $r){
            $lines[] = ['stock_item_id'=>(int)$r['stock_item_id'], 'name'=>$r['item_name'], 'unit'=>$r['item_unit'],
                'quantity'=>(float)$r['quantity'], 'unit_price'=>(float)$r['unit_price'], 'vat_rate'=>(float)$r['vat_rate'], 'derived'=>false];
        }
        return ['editable'=>true, 'reason'=>null, 'sale'=>$sale, 'lines'=>$lines];
    }

    // Fiyatsız (migration öncesi) satır(lar) var — SADECE tek satırlı satışlarda toplamdan
    // güvenle türetilebilir. Çoklu satırlı + fiyatsız satışlarda toplamı satırlara bölmenin
    // doğru bir yolu yok — sessizce tahmin/eşit bölme kesinlikle YAPILMAZ (2026-07-10 kararı).
    if(count($rows) === 1){
        $r = $rows[0];
        $qty = (float)$r['quantity'];
        if($qty <= 0) return ['editable'=>false, 'reason'=>'Geçersiz miktar, güvenle düzenlenemez.', 'sale'=>$sale, 'lines'=>[]];
        $amount = (float)$sale['amount'];
        $vatAmount = (float)$sale['vat_amount'];
        $subtotal = $amount - $vatAmount;
        $unitPrice = round($subtotal/$qty, 2);
        $vatRate = $sale['vat_rate']!==null ? (float)$sale['vat_rate'] : ($subtotal>0 ? round($vatAmount/$subtotal*100, 2) : 0);
        return ['editable'=>true, 'reason'=>null, 'sale'=>$sale, 'lines'=>[[
            'stock_item_id'=>(int)$r['stock_item_id'], 'name'=>$r['item_name'], 'unit'=>$r['item_unit'],
            'quantity'=>$qty, 'unit_price'=>$unitPrice, 'vat_rate'=>$vatRate, 'derived'=>true,
        ]]];
    }

    return ['editable'=>false, 'reason'=>'Bu eski satışta satır bazlı fiyat bilgisi bulunmadığından güvenli şekilde düzenlenemez.', 'sale'=>$sale, 'lines'=>[]];
}

/**
 * Satış düzenleme: eski stok/bakiye etkisini TAM geri alır, yeni satırları AYNI finance_movements
 * kaydı (aynı id — yeni kayıt yaratılmaz) üzerinde uygular. Çağıran taraf ÖNCE stock_can_edit_sale()
 * ile uygunluğu kontrol etmeli. Tek transaction — hata olursa stok ve finans tamamen geri alınır.
 * @param PDO $pdo
 * @param int $saleId finance_movements.id (movement_type='sale' veya 'mobile_sale')
 * @param int $contact yeni contact_id
 * @param string $method yeni ödeme yöntemi (Peşin/Kredi Kartı/Banka Havalesi/Veresiye)
 * @param array $ids stock_item_id[]  @param array $qtys quantity[]
 * @param array $prices unit_price[] (KDV hariç net)  @param array $vatRates vat_rate[]
 * @param string $noteLabel stock_movements.note etiketi ('Web satış' / 'Mobil satış')
 * @return array ['ok'=>bool, 'message'=>string]
 */
function stock_update_sale($pdo, $saleId, $contact, $method, $ids, $qtys, $prices, $vatRates, $noteLabel='Satış'){
    require_once __DIR__.'/finance_lib.php';
    try{
        $saleId = (int)$saleId;
        if($saleId < 1) return ['ok'=>false, 'message'=>'Geçersiz satış kaydı.'];
        if(!$contact) throw new Exception('Cari seçin.');

        $old = $pdo->prepare("SELECT * FROM finance_movements WHERE id=? AND (movement_type='sale' OR movement_type='mobile_sale')");
        $old->execute([$saleId]);
        $oldSale = $old->fetch();
        if(!$oldSale) return ['ok'=>false, 'message'=>'Satış kaydı bulunamadı.'];

        $built = stock_sale_build_lines($pdo, $ids, $qtys, $prices, $vatRates);
        $lines = $built['lines'];

        $oldStk = $pdo->prepare("SELECT * FROM stock_movements WHERE finance_movement_id=?");
        $oldStk->execute([$saleId]);
        $oldMovements = $oldStk->fetchAll();

        $accId = stock_sale_resolve_account($pdo, $method);
        $saleStatus = $method === 'Veresiye' ? 'Bekliyor' : 'Tahsil Edildi';

        $pdo->beginTransaction();
        try{
            // 1) Eski stok etkisini geri al, eski stok hareketlerini sil
            foreach($oldMovements as $m){
                $pdo->prepare("UPDATE stock_items SET quantity=quantity+? WHERE id=?")->execute([$m['quantity'], $m['stock_item_id']]);
            }
            $pdo->prepare("DELETE FROM stock_movements WHERE finance_movement_id=?")->execute([$saleId]);

            // 2) Eski bakiye etkisini geri al (finance_lib.php ortak fonksiyonu)
            finance_movement_reverse_balance($pdo, $oldSale);

            // 3) Yeni satırları uygula — stok düş + satır bazlı fiyat/KDV ile kaydet
            foreach($lines as $l){
                $pdo->prepare("UPDATE stock_items SET quantity=quantity-? WHERE id=?")->execute([$l['qty'], $l['item']['id']]);
                stock_insert_sale_movement($pdo, $l['item']['id'], $saleId, $l['qty'], $l['price'], $l['vat_rate'], 'Satış', $noteLabel.' ('.$method.', düzenlendi)');
            }

            // 4) finance_movements'ı GÜNCELLE (aynı id — geçmiş bağlantılar/linkler bozulmaz)
            $pdo->prepare("UPDATE finance_movements SET contact_id=?,amount=?,vat_rate=?,vat_amount=?,payment_channel=?,account_id=?,status=?,description=? WHERE id=?")
                ->execute([
                    $contact, $built['grand_total'], count($lines)===1 ? ($lines[0]['vat_rate'] ?: null) : null, $built['grand_vat'],
                    $method, $accId, $saleStatus, $built['desc'], $saleId
                ]);

            // 5) Yeni bakiye etkisini uygula (finance_lib.php ortak fonksiyonu)
            finance_movement_apply_balance($pdo, 'in', $accId, $built['grand_total']);

            $pdo->commit();
        }catch(Throwable $e){
            $pdo->rollBack();
            throw $e;
        }

        return ['ok'=>true, 'message'=>implode(', ', $built['desc_parts']).' güncellendi: '.money($built['grand_total'])];
    }catch(Throwable $e){
        return ['ok'=>false, 'message'=>'Satış güncelleme hatası: '.$e->getMessage()];
    }
}
?>
