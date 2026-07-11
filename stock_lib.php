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
 * FİNANS ÇEKİRDEK DÜZELTMESİ (2026-07-10): Alış ekranı ödeme YAPMAZ — her zaman tedarikçiye
 * açık borç oluşturur, durum her zaman "Bekliyor", kasa/banka/kart hiçbir zaman etkilenmez
 * (ödemenin kendisi SADECE Ödeme ekranından yapılır). "Veresiye" artık özel bir ödeme yöntemi
 * değil, tek başlangıç durumudur — bu yüzden eski stock_add_purchase()/stock_add_purchase_finance()
 * (ödeme yöntemi parametresi alan, bazen kasayı doğrudan etkileyen) TAMAMEN kaldırıldı, yerine
 * sales.php'nin satır-bazlı deseniyle simetrik yeni fonksiyonlar geldi (aşağıda).
 */

/**
 * POST'tan gelen alış satırlarını doğrular, ürünleri çeker, satır/genel toplamları hesaplar.
 * Stoğa DOKUNMAZ — sadece hesaplama (stock_sale_build_lines ile birebir aynı mantık, alış
 * tarafı). Ürünler her zaman ÖNCEDEN VAR olan stock_item_id ile gelir (2026-07-04'ten beri
 * dropdown/AJAX hızlı-ekle deseni) — birim (unit) bu yüzden burada parametre DEĞİL, ürünün
 * kendi stock_items.unit alanından okunur.
 * @throws Exception geçersiz girdi durumunda
 * @return array ['lines'=>[...], 'grand_total'=>float, 'grand_vat'=>float, 'desc'=>string, 'desc_parts'=>array]
 */
function stock_purchase_build_lines($pdo, $ids, $qtys, $prices, $vatRates){
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

        $lines[] = ['item'=>$item, 'qty'=>$qty, 'price'=>$price, 'vat_rate'=>$vatRate, 'vat_amount'=>$vatAmount, 'line_total'=>$lineTotal];
    }
    if(!$lines) throw new Exception('En az bir geçerli ürün satırı ekleyin.');

    $grandTotal=0; $grandVat=0; $descParts=[];
    foreach($lines as $l){
        $grandTotal += $l['line_total'];
        $grandVat   += $l['vat_amount'];
        $descParts[] = $l['item']['name'] . ' x' . stock_qty_fmt($l['qty']);
    }
    $desc = implode(', ', $descParts) . ' alış';

    return ['lines'=>$lines, 'grand_total'=>$grandTotal, 'grand_vat'=>$grandVat, 'desc'=>$desc, 'desc_parts'=>$descParts];
}

/**
 * Bir alış satırının stok kartını (miktar + ağırlıklı ortalama maliyet) günceller ve
 * stock_movements kaydeder. $itemId'den TAZE okur (bir sepette aynı ürün birden fazla satırda
 * geçerse ortalama doğru zincirlensin diye — build_lines'daki olası bayat $item'a güvenilmez).
 */
function stock_apply_purchase_line($pdo, $itemId, $qty, $unitPrice, $vatRate, $financeMovementId, $note){
    $s = $pdo->prepare("SELECT * FROM stock_items WHERE id=?");
    $s->execute([$itemId]);
    $item = $s->fetch();
    if(!$item) return;

    $pid = (int)$item['id'];
    $oldQty = (float)$item['quantity'];
    $oldAvg = (float)($item['avg_cost'] ?: $item['purchase_price']);
    $newQty = $oldQty + $qty;
    $newAvg = $newQty>0 ? (($oldQty*$oldAvg)+($qty*$unitPrice))/$newQty : $unitPrice;

    $pdo->prepare("UPDATE stock_items SET quantity=?, avg_cost=?, purchase_price=?, last_purchase_price=? WHERE id=?")
        ->execute([$newQty, $newAvg, $unitPrice, $unitPrice, $pid]);

    stock_insert_purchase_movement($pdo, $pid, $financeMovementId, $qty, $unitPrice, $vatRate, 'Alış', $note);
}

/**
 * Yeni alış kaydı oluşturur. Kural: ödeme yapmaz — finans hareketi ÖNCE oluşturulur (account_id
 * NULL, status 'Bekliyor'), id'si stok hareketlerine kesin referans olarak yazılır, sonra her
 * satır için stok kartı güncellenir (sales.php'nin "finans önce, stok sonra, aynı id" deseniyle
 * simetrik — bkz. sales.php POST handler).
 * @return array ['ok'=>bool, 'message'=>string, 'purchase_id'=>int|null]
 */
function stock_create_purchase($pdo, $supplier, $ids, $qtys, $prices, $vatRates, $noteLabel="Alış"){
    try{
        if(!$supplier) throw new Exception('Tedarikçi seçin.');
        $built = stock_purchase_build_lines($pdo, $ids, $qtys, $prices, $vatRates);
        $lines = $built['lines'];

        $pdo->beginTransaction();
        try{
            $pdo->prepare(
                "INSERT INTO finance_movements(contact_id,direction,amount,vat_rate,vat_amount,payment_channel,account_id,status,movement_date,description,movement_type)
                 VALUES(?,'out',?,?,?,NULL,NULL,'Bekliyor',?,?,'purchase')"
            )->execute([
                $supplier, $built['grand_total'], count($lines)===1 ? ($lines[0]['vat_rate'] ?: null) : null, $built['grand_vat'],
                date('Y-m-d'), $built['desc']
            ]);
            $financeMovementId = (int)$pdo->lastInsertId();

            foreach($lines as $l){
                stock_apply_purchase_line($pdo, $l['item']['id'], $l['qty'], $l['price'], $l['vat_rate'], $financeMovementId, $noteLabel);
            }

            $pdo->commit();
        }catch(Throwable $e){
            $pdo->rollBack();
            throw $e;
        }

        return ['ok'=>true, 'message'=>implode(', ', $built['desc_parts']).' alındı: '.money($built['grand_total']).' — tedarikçiye açık borç (Bekliyor)', 'purchase_id'=>$financeMovementId];
    }catch(Throwable $e){
        return ['ok'=>false, 'message'=>$e->getMessage(), 'purchase_id'=>null];
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

        // TEK transaction (2026-07-11 düzeltmesi — Kerem/audit): önceden her satırın stok geri
        // ekleme adımı KENDİ try/catch'i içinde sessizce yutuluyordu ve stock_movements satırı
        // bu geri ekleme başarısız olsa BİLE siliniyordu — teorik olarak sessiz stok kaybına yol
        // açabilirdi. Artık hata olursa TÜM işlem (stok + finans) rollback edilir.
        $pdo->beginTransaction();
        try{
            foreach($movements as $movement){
                $pdo->prepare("UPDATE stock_items SET quantity=quantity+? WHERE id=?")->execute([$movement['quantity'], $movement['stock_item_id']]);
                $pdo->prepare("DELETE FROM stock_movements WHERE id=?")->execute([$movement['id']]);
            }

            // Finans hareketini geri al — finance_movement_delete() 'sale'/'mobile_sale' tipini kasıtlı
            // olarak reddediyor (normal finans düzenleme ekranından dokunulmasın diye), bu yüzden burada
            // bakiye geri alma + silme DOĞRUDAN yapılıyor (finance_movement_reverse_balance() ortak fonksiyonu).
            finance_movement_reverse_balance($pdo, $sale);
            $pdo->prepare("DELETE FROM finance_movements WHERE id=?")->execute([$saleId]);

            $pdo->commit();
        }catch(Throwable $e){
            $pdo->rollBack();
            throw $e;
        }

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

/** Bir alış satırının stock_movements kaydını oluşturur — stock_insert_sale_movement ile aynı
 * şema-dayanıklılık deseni, yön 'in' (stok girişi). */
function stock_insert_purchase_movement($pdo, $stockItemId, $financeMovementId, $qty, $unitPrice, $vatRate, $reason, $note){
    if(stock_movements_has_line_pricing($pdo)){
        $pdo->prepare("INSERT INTO stock_movements(stock_item_id,finance_movement_id,direction,quantity,unit_price,vat_rate,reason,note,created_at) VALUES(?,?,?,?,?,?,?,?,NOW())")
            ->execute([$stockItemId, $financeMovementId, 'in', $qty, $unitPrice, $vatRate, $reason, $note]);
    }else{
        $pdo->prepare("INSERT INTO stock_movements(stock_item_id,finance_movement_id,direction,quantity,reason,note,created_at) VALUES(?,?,?,?,?,?,NOW())")
            ->execute([$stockItemId, $financeMovementId, 'in', $qty, $reason, $note]);
    }
}

/**
 * POST'tan gelen ürün satırlarını doğrular, ürünleri çeker, satır/genel toplamları hesaplar.
 * Web (sales.php) ve mobil (mobile/sales.php) satış ekle/düzenle akışlarının ortak mantığı
 * (2026-07-10, satış düzenleme özelliğiyle birlikte tekrarlanan kod ortaklaştırıldı).
 *
 * BİLİNÇLİ OLARAK negatif stok engeli YOK (2026-07-11, kullanıcı kararı — DEV testinde
 * gözlemlenip onaylandı): satın alımdan ÖNCE satış siparişi girmek gerekebilir (ürün henüz
 * depoya girmeden satılmış olabilir), bu yüzden stok eksiye düşebilmesi bilinçli olarak
 * engellenmiyor. Bu satırı yeniden eklemeden önce kullanıcıyla teyitleşin.
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
 * FİNANS ÇEKİRDEK DÜZELTMESİ (2026-07-10): Satış hiçbir zaman kasa/banka etkilemez — bu yüzden
 * ödeme yöntemi parametresi tamamen kaldırıldı. Düzenlemede YENİ taraf her zaman account_id=NULL,
 * status='Bekliyor' olur; ESKİ tarafın etkisi (varsa, legacy Peşin satışlardan kalma bir hesap
 * etkisi) yine de TAM geri alınır — "Peşin → Veresiye" dönüşümünde eski kasa bakiyesi eski haline
 * döner (bkz. finance_movement_reverse_balance).
 * @param PDO $pdo
 * @param int $saleId finance_movements.id (movement_type='sale' veya 'mobile_sale')
 * @param int $contact yeni contact_id
 * @param array $ids stock_item_id[]  @param array $qtys quantity[]
 * @param array $prices unit_price[] (KDV hariç net)  @param array $vatRates vat_rate[]
 * @param string $noteLabel stock_movements.note etiketi ('Web satış' / 'Mobil satış')
 * @return array ['ok'=>bool, 'message'=>string]
 */
function stock_update_sale($pdo, $saleId, $contact, $ids, $qtys, $prices, $vatRates, $noteLabel='Satış'){
    require_once __DIR__.'/finance_lib.php';
    try{
        $saleId = (int)$saleId;
        if($saleId < 1) return ['ok'=>false, 'message'=>'Geçersiz satış kaydı.'];
        if(!$contact) throw new Exception('Cari seçin.');

        $old = $pdo->prepare("SELECT * FROM finance_movements WHERE id=? AND (movement_type='sale' OR movement_type='mobile_sale')");
        $old->execute([$saleId]);
        $oldSale = $old->fetch();
        if(!$oldSale) return ['ok'=>false, 'message'=>'Satış kaydı bulunamadı.'];

        $oldStk = $pdo->prepare("SELECT * FROM stock_movements WHERE finance_movement_id=?");
        $oldStk->execute([$saleId]);
        $oldMovements = $oldStk->fetchAll();

        $built = stock_sale_build_lines($pdo, $ids, $qtys, $prices, $vatRates);
        $lines = $built['lines'];

        $pdo->beginTransaction();
        try{
            // 1) Eski stok etkisini geri al, eski stok hareketlerini sil
            foreach($oldMovements as $m){
                $pdo->prepare("UPDATE stock_items SET quantity=quantity+? WHERE id=?")->execute([$m['quantity'], $m['stock_item_id']]);
            }
            $pdo->prepare("DELETE FROM stock_movements WHERE finance_movement_id=?")->execute([$saleId]);

            // 2) Eski bakiye etkisini geri al (varsa — legacy Peşin satış olabilir; finance_lib.php ortak fonksiyonu)
            finance_movement_reverse_balance($pdo, $oldSale);

            // 3) Yeni satırları uygula — stok düş + satır bazlı fiyat/KDV ile kaydet
            foreach($lines as $l){
                $pdo->prepare("UPDATE stock_items SET quantity=quantity-? WHERE id=?")->execute([$l['qty'], $l['item']['id']]);
                stock_insert_sale_movement($pdo, $l['item']['id'], $saleId, $l['qty'], $l['price'], $l['vat_rate'], 'Satış', $noteLabel.' (düzenlendi)');
            }

            // 4) finance_movements'ı GÜNCELLE (aynı id) — kural: satış kasa/banka etkilemez,
            // her zaman Bekliyor/hesapsız kalır. Yeni tarafta bakiye UYGULANMAZ (adım yok, kasten).
            $pdo->prepare("UPDATE finance_movements SET contact_id=?,amount=?,vat_rate=?,vat_amount=?,payment_channel=NULL,account_id=NULL,status='Bekliyor',description=? WHERE id=?")
                ->execute([
                    $contact, $built['grand_total'], count($lines)===1 ? ($lines[0]['vat_rate'] ?: null) : null, $built['grand_vat'],
                    $built['desc'], $saleId
                ]);

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

/**
 * Bir alışa bağlı TÜM stock_movements satırlarının stok etkisini (miktar + ağırlıklı ortalama
 * maliyet) tam olarak geri alır ve o satırları siler. stock_reverse_purchase() (silme) ve
 * stock_update_purchase() (düzenleme) tarafından ORTAK kullanılır — paralel bir tersleme mantığı
 * YAZILMADI, tek kaynak burası.
 *
 * Ortalama maliyet ağırlıklı ortalama olduğu için, bu alıştan SONRA aynı üründe başka bir stok
 * hareketi (alış/satış) olmuşsa tersleme matematiksel olarak artık kesin değildir — bu yüzden
 * çağıran taraf (stock_can_edit_purchase) böyle durumları ÖNCEDEN reddeder, burada sessizce
 * yanlış bir tahmine düşülmez.
 */
function stock_purchase_reverse_lines($pdo, $purchaseId){
    $stk = $pdo->prepare("SELECT * FROM stock_movements WHERE finance_movement_id=? AND direction='in'");
    $stk->execute([$purchaseId]);
    $movements = $stk->fetchAll();

    foreach($movements as $m){
        $itemId = (int)$m['stock_item_id'];
        $qty = (float)$m['quantity'];
        $unitPrice = ($m['unit_price'] ?? null) !== null ? (float)$m['unit_price'] : null;

        $s = $pdo->prepare("SELECT * FROM stock_items WHERE id=?");
        $s->execute([$itemId]);
        $item = $s->fetch();
        if($item){
            $curQty = (float)$item['quantity'];
            $curAvg = (float)($item['avg_cost'] ?: $item['purchase_price']);
            $newQty = $curQty - $qty;
            if($unitPrice !== null && $newQty > 0.0000001){
                $newAvg = (($curAvg*$curQty) - ($qty*$unitPrice)) / $newQty;
                if($newAvg < 0) $newAvg = $curAvg; // güvenlik: negatif çıkarsa dokunma
            }else{
                $newAvg = $curAvg;
            }
            $pdo->prepare("UPDATE stock_items SET quantity=?, avg_cost=? WHERE id=?")->execute([$newQty, $newAvg, $itemId]);
        }
        $pdo->prepare("DELETE FROM stock_movements WHERE id=?")->execute([$m['id']]);
    }
}

/**
 * Bir alıştaki HERHANGİ bir ürün için, bu alıştan SONRA (id daha büyük) başka bir stok hareketi
 * (alış/satış) oluşmuş mu kontrol eder — ağırlıklı ortalama maliyet (avg_cost) güvenliği. HEM
 * düzenleme (stock_can_edit_purchase) HEM silme (stock_reverse_purchase) tarafından ortak
 * kullanılır: ikisi de aynı stock_purchase_reverse_lines() tersleme matematiğine dayanıyor, bu
 * yüzden ikisi de aynı güvenlik kapısından geçmeli — sadece edit'i reddedip delete'i sessizce
 * geçirmek avg_cost'u arka kapıdan bozardı (2026-07-10, Selin/ots-security-auditor denetiminde
 * bulundu).
 * @return bool true ise bu alış güvenle terslenebilir (ne edit ne delete avg_cost'u bozar)
 */
function stock_purchase_avg_cost_safe($pdo, $purchaseId){
    $stk = $pdo->prepare("SELECT id, stock_item_id FROM stock_movements WHERE finance_movement_id=? AND direction='in'");
    $stk->execute([$purchaseId]);
    foreach($stk->fetchAll() as $r){
        $chk = $pdo->prepare("SELECT COUNT(*) c FROM stock_movements WHERE stock_item_id=? AND id>?");
        $chk->execute([$r['stock_item_id'], $r['id']]);
        if((int)$chk->fetch()['c'] > 0) return false;
    }
    return true;
}

/**
 * Alış silme: stok + ortalama maliyet etkisini geri alır, finans hareketini geri alır/siler.
 * Silmeden ÖNCE aynı avg_cost güvenlik kapısından geçer (stock_purchase_avg_cost_safe) — güvenle
 * terslenemeyecek bir alış sessizce silinip avg_cost'u bozmaz, açık mesajla reddedilir.
 * @return array ['ok'=>bool, 'message'=>string]
 */
function stock_reverse_purchase($pdo, $purchaseId){
    require_once __DIR__.'/finance_lib.php';
    try{
        $purchaseId = (int)$purchaseId;
        if($purchaseId < 1) return ['ok'=>false, 'message'=>'Geçersiz alış kaydı.'];

        $s = $pdo->prepare("SELECT * FROM finance_movements WHERE id=? AND movement_type='purchase'");
        $s->execute([$purchaseId]);
        $purchase = $s->fetch();
        if(!$purchase) return ['ok'=>false, 'message'=>'Alış kaydı bulunamadı.'];

        if(!stock_purchase_avg_cost_safe($pdo, $purchaseId)){
            return ['ok'=>false, 'message'=>'Bu alıştaki bir veya daha fazla ürün için, bu alıştan SONRA başka bir stok hareketi (alış/satış) oluşmuş. Ortalama maliyet güvenle geri alınamayacağı için bu kayıt silinemez.'];
        }

        $pdo->beginTransaction();
        try{
            stock_purchase_reverse_lines($pdo, $purchaseId);
            finance_movement_reverse_balance($pdo, $purchase);
            $pdo->prepare("DELETE FROM finance_movements WHERE id=?")->execute([$purchaseId]);
            $pdo->commit();
        }catch(Throwable $e){
            $pdo->rollBack();
            throw $e;
        }

        return ['ok'=>true, 'message'=>'Alış silindi, stok ve bakiyeler geri alındı.'];
    }catch(Throwable $e){
        return ['ok'=>false, 'message'=>'Alış silme hatası: '.$e->getMessage()];
    }
}

/**
 * Bir alışın "Düzenle" ekranında GÜVENLE açılıp açılamayacağını belirler. İki bağımsız kapı var:
 * (1) satır bazlı fiyat bilgisi (migration 043) — stock_can_edit_sale ile aynı mantık;
 * (2) ortalama maliyet güvenliği (stock_purchase_avg_cost_safe) — bu alıştan SONRA aynı üründe
 * başka bir stok hareketi olduysa avg_cost artık kesin geri alınamaz, düzenleme reddedilir
 * (tahmin YAPILMAZ — stock_can_edit_sale ile aynı "don't guess" felsefesi, 2026-07-10 kararı).
 * @return array ['editable'=>bool, 'reason'=>string|null, 'purchase'=>array|null, 'lines'=>array]
 */
function stock_can_edit_purchase($pdo, $purchaseId){
    $purchaseId = (int)$purchaseId;
    $s = $pdo->prepare("SELECT * FROM finance_movements WHERE id=? AND movement_type='purchase'");
    $s->execute([$purchaseId]);
    $purchase = $s->fetch();
    if(!$purchase) return ['editable'=>false, 'reason'=>'Alış kaydı bulunamadı.', 'purchase'=>null, 'lines'=>[]];

    $stk = $pdo->prepare("SELECT sm.*, si.name AS item_name, si.unit AS item_unit FROM stock_movements sm LEFT JOIN stock_items si ON si.id=sm.stock_item_id WHERE sm.finance_movement_id=? AND sm.direction='in' ORDER BY sm.id");
    $stk->execute([$purchaseId]);
    $rows = $stk->fetchAll();
    if(!$rows) return ['editable'=>false, 'reason'=>'Bu alışa bağlı stok hareketi bulunamadı.', 'purchase'=>$purchase, 'lines'=>[]];

    if(!stock_purchase_avg_cost_safe($pdo, $purchaseId)){
        return ['editable'=>false,
            'reason'=>'Bu alıştaki bir veya daha fazla ürün için, bu alıştan SONRA başka bir stok hareketi (alış/satış) oluşmuş. Ortalama maliyet güvenle geri alınamayacağı için bu kayıt düzenlenemez.',
            'purchase'=>$purchase, 'lines'=>[]];
    }

    $missingPrice = false;
    foreach($rows as $r){ if(($r['unit_price'] ?? null) === null) $missingPrice = true; }

    if(!$missingPrice){
        $lines=[];
        foreach($rows as $r){
            $lines[] = ['stock_item_id'=>(int)$r['stock_item_id'], 'name'=>$r['item_name'], 'unit'=>$r['item_unit'],
                'quantity'=>(float)$r['quantity'], 'unit_price'=>(float)$r['unit_price'], 'vat_rate'=>(float)$r['vat_rate'], 'derived'=>false];
        }
        return ['editable'=>true, 'reason'=>null, 'purchase'=>$purchase, 'lines'=>$lines];
    }

    if(count($rows) === 1){
        $r = $rows[0];
        $qty = (float)$r['quantity'];
        if($qty <= 0) return ['editable'=>false, 'reason'=>'Geçersiz miktar, güvenle düzenlenemez.', 'purchase'=>$purchase, 'lines'=>[]];
        $amount = (float)$purchase['amount'];
        $vatAmount = (float)$purchase['vat_amount'];
        $subtotal = $amount - $vatAmount;
        $unitPrice = round($subtotal/$qty, 2);
        $vatRate = $purchase['vat_rate']!==null ? (float)$purchase['vat_rate'] : ($subtotal>0 ? round($vatAmount/$subtotal*100, 2) : 0);
        return ['editable'=>true, 'reason'=>null, 'purchase'=>$purchase, 'lines'=>[[
            'stock_item_id'=>(int)$r['stock_item_id'], 'name'=>$r['item_name'], 'unit'=>$r['item_unit'],
            'quantity'=>$qty, 'unit_price'=>$unitPrice, 'vat_rate'=>$vatRate, 'derived'=>true,
        ]]];
    }

    return ['editable'=>false, 'reason'=>'Bu eski alışta satır bazlı fiyat bilgisi bulunmadığından güvenli şekilde düzenlenemez.', 'purchase'=>$purchase, 'lines'=>[]];
}

/**
 * Alış düzenleme: eski stok/ortalama-maliyet/bakiye etkisini TAM geri alır, yeni satırları AYNI
 * finance_movements kaydı üzerinde uygular. Çağıran taraf ÖNCE stock_can_edit_purchase() ile
 * uygunluğu kontrol etmeli. Tek transaction. Kural: alış hiçbir zaman kasa/banka etkilemez —
 * account_id her zaman NULL, status her zaman 'Bekliyor' kalır.
 * @return array ['ok'=>bool, 'message'=>string]
 */
function stock_update_purchase($pdo, $purchaseId, $supplier, $ids, $qtys, $prices, $vatRates, $noteLabel="Alış"){
    require_once __DIR__.'/finance_lib.php';
    try{
        $purchaseId = (int)$purchaseId;
        if($purchaseId < 1) return ['ok'=>false, 'message'=>'Geçersiz alış kaydı.'];
        if(!$supplier) throw new Exception('Tedarikçi seçin.');

        $old = $pdo->prepare("SELECT * FROM finance_movements WHERE id=? AND movement_type='purchase'");
        $old->execute([$purchaseId]);
        $oldPurchase = $old->fetch();
        if(!$oldPurchase) return ['ok'=>false, 'message'=>'Alış kaydı bulunamadı.'];

        $built = stock_purchase_build_lines($pdo, $ids, $qtys, $prices, $vatRates);
        $lines = $built['lines'];

        $pdo->beginTransaction();
        try{
            // 1) Eski stok/ortalama-maliyet etkisini TAM geri al (ortak fonksiyon)
            stock_purchase_reverse_lines($pdo, $purchaseId);

            // 2) Eski bakiye etkisini geri al (varsa — legacy Peşin alış olabilir)
            finance_movement_reverse_balance($pdo, $oldPurchase);

            // 3) Yeni satırları uygula
            foreach($lines as $l){
                stock_apply_purchase_line($pdo, $l['item']['id'], $l['qty'], $l['price'], $l['vat_rate'], $purchaseId, $noteLabel.' (düzenlendi)');
            }

            // 4) finance_movements'ı GÜNCELLE — kural: alış kasa/banka etkilemez, her zaman Bekliyor
            $pdo->prepare("UPDATE finance_movements SET contact_id=?,amount=?,vat_rate=?,vat_amount=?,payment_channel=NULL,account_id=NULL,status='Bekliyor',description=? WHERE id=?")
                ->execute([
                    $supplier, $built['grand_total'], count($lines)===1 ? ($lines[0]['vat_rate'] ?: null) : null, $built['grand_vat'],
                    $built['desc'], $purchaseId
                ]);

            $pdo->commit();
        }catch(Throwable $e){
            $pdo->rollBack();
            throw $e;
        }

        return ['ok'=>true, 'message'=>implode(', ', $built['desc_parts']).' güncellendi: '.money($built['grand_total'])];
    }catch(Throwable $e){
        return ['ok'=>false, 'message'=>'Alış güncelleme hatası: '.$e->getMessage()];
    }
}
?>
