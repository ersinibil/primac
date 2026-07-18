<?php
/* CPA MİKTARSAL TAHSİS (P0 SON KAPANIŞ, 2026-07-18, Product Owner kararı) — cpa_lib.php'deki
 * "tercih edilen tedarikçi" hafızası (045_cpa_preferences) yeterli değildi. Asıl talep: satın
 * alınan miktarın belirli bir kısmının belirli bir müşteriye/satışa AYRILMASI (tahsis), kalanının
 * "serbest stok" olarak görünmesi. Örnek: 2.500 adet satın alındı, 1.000'i Sakarya Üniversitesi'ne
 * tahsisli, 1.500'ü serbest. Şema: database/migrations/046_cpa_allocations.sql.
 *
 * KURAL (Product Owner): "Tahsis fiziksel stoktan ayrı mantıkta izlenmeli, mevcut stok ve finans
 * matematiğini bozma." — bu dosya stock_items/stock_movements/finance_movements tablolarına TEK
 * BİR YAZMA işlemi yapmaz (sadece SELECT ile okur), stock_lib.php'ye hiç dokunulmadı. Tüm tahsis
 * muhasebesi cpa_allocations tablosunda ayrı yürür; "serbest stok" HER ZAMAN türetilir (saklanmaz):
 *   serbest = stock_items.quantity - SUM(aktif tahsislerin kalanı, tüm alışlar toplamı)
 * Bu formül satış sırasına bakmaksızın doğru sonuç verir: tahsisli müşteriye satış yapılıp tahsis
 * tüketildiğinde HEM fiziksel stok HEM tahsis-kalanı birlikte düşer (serbest değişmez); serbest
 * stoktan satış yapıldığında SADECE fiziksel stok düşer (serbest de aynı miktarda düşer, tahsis
 * dokunulmaz) — iki durumda da formül kendiliğinden doğru kalır.
 *
 * Kayıtlar SİLİNMEZ — iptal status='İptal' ile yapılır (cpa_preferences ile aynı felsefe), her
 * değişiklik audit_log() ile 'cpa_allocations' tablosuna karşı loglanır. Web + mobil ortak.
 */

require_once __DIR__.'/cpa_lib.php';
require_once __DIR__.'/stock_lib.php'; // stock_qty_fmt() için

// cpa_lib.php'deki yetki kuralının aynısı (Madde 8 — "Satış ve Satın Alma yöneticileri
// düzenleyebilsin, diğerleri sadece görüntüleyebilsin") — bu dosyanın kendi isim ailesiyle
// tutarlı ince sarmalayıcılar, yeni bir yetki kavramı icat edilmedi.
function cpa_alloc_can_edit(){ return cpa_can_edit(); }
function cpa_alloc_can_view(){ return cpa_can_view(); }

function cpa_alloc_install(){
    try{
        db()->exec("CREATE TABLE IF NOT EXISTS cpa_allocations (
          id INT AUTO_INCREMENT PRIMARY KEY,
          purchase_movement_id INT NOT NULL,
          stock_item_id INT NOT NULL,
          customer_id INT NOT NULL,
          allocated_qty DECIMAL(12,3) NOT NULL,
          consumed_qty DECIMAL(12,3) NOT NULL DEFAULT 0,
          status VARCHAR(20) NOT NULL DEFAULT 'Aktif',
          notes TEXT NULL,
          created_by INT NULL,
          created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
          updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          KEY idx_purchase (purchase_movement_id),
          KEY idx_product (stock_item_id),
          KEY idx_customer_product (customer_id, stock_item_id),
          KEY idx_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }catch(Throwable $e){}
}

// Bir tahsisin allocated_qty/consumed_qty'sine göre doğru status'u türetir. İptal edilmiş bir
// tahsis hiçbir hesaplamayla kendiliğinden geri canlanmaz — sadece cpa_alloc_reopen() ile (bugün
// yok, kasıtlı: "iptal" kalıcı bir karar, Product Owner'ın "iptal edilebilsin" maddesi tek yönlü).
function cpa_alloc_status_for($allocatedQty, $consumedQty, $currentStatus){
    if($currentStatus==='İptal') return 'İptal';
    if((float)$consumedQty > 0 && (float)$consumedQty >= (float)$allocatedQty - 0.0000001) return 'Tüketildi';
    return 'Aktif';
}

/**
 * Bir ürün için toplam fiziksel stok / aktif tahsis / serbest stok. Tüm alışlar toplamında
 * (tek bir alışa özel değil) — product_view.php "Serbest Stok" özetinde kullanılır.
 * @return array ['physical'=>float,'allocated'=>float,'free'=>float]
 */
function cpa_alloc_free_stock($pdo, $stockItemId){
    cpa_alloc_install();
    $stockItemId=(int)$stockItemId;
    $physical=0.0;
    try{
        $s=$pdo->prepare("SELECT quantity FROM stock_items WHERE id=?");
        $s->execute([$stockItemId]);
        $physical=(float)($s->fetch()['quantity'] ?? 0);
    }catch(Throwable $e){}
    $allocated=0.0;
    try{
        $a=$pdo->prepare("SELECT COALESCE(SUM(GREATEST(allocated_qty-consumed_qty,0)),0) t FROM cpa_allocations WHERE stock_item_id=? AND status<>'İptal'");
        $a->execute([$stockItemId]);
        $allocated=(float)($a->fetch()['t'] ?? 0);
    }catch(Throwable $e){}
    return ['physical'=>$physical, 'allocated'=>$allocated, 'free'=>$physical-$allocated];
}

/**
 * Bir alışın (finance_movements.id, movement_type='purchase') satır bazlı özeti: bu alışta hangi
 * ürünlerden ne kadar alındı, bu ALIŞTAN ne kadarı zaten tahsis edilmiş, bu alıştan ne kadar daha
 * tahsis edilebilir. cpa_allocation.php'nin "Yeni Tahsis" formunun ürün seçimini besler.
 * @return array [['stock_item_id','product_name','unit','purchased_qty','allocated_from_purchase','free_on_purchase']]
 */
function cpa_alloc_purchase_line_summary($pdo, $purchaseMovementId){
    cpa_alloc_install();
    $purchaseMovementId=(int)$purchaseMovementId;
    try{
        $lines=$pdo->prepare(
            "SELECT sm.stock_item_id, si.name AS product_name, si.unit,
                    SUM(sm.quantity) AS purchased_qty
             FROM stock_movements sm
             LEFT JOIN stock_items si ON si.id=sm.stock_item_id
             WHERE sm.finance_movement_id=? AND sm.direction='in'
             GROUP BY sm.stock_item_id, si.name, si.unit
             ORDER BY si.name"
        );
        $lines->execute([$purchaseMovementId]);
        $rows=$lines->fetchAll();
    }catch(Throwable $e){ return []; }

    $out=[];
    foreach($rows as $r){
        $allocFromThis=0.0;
        try{
            $a=$pdo->prepare("SELECT COALESCE(SUM(GREATEST(allocated_qty-consumed_qty,0)),0) t FROM cpa_allocations WHERE purchase_movement_id=? AND stock_item_id=? AND status<>'İptal'");
            $a->execute([$purchaseMovementId,(int)$r['stock_item_id']]);
            $allocFromThis=(float)($a->fetch()['t'] ?? 0);
        }catch(Throwable $e){}
        $out[]=[
            'stock_item_id'=>(int)$r['stock_item_id'],
            'product_name'=>$r['product_name'],
            'unit'=>$r['unit'],
            'purchased_qty'=>(float)$r['purchased_qty'],
            'allocated_from_purchase'=>$allocFromThis,
            'free_on_purchase'=>(float)$r['purchased_qty']-$allocFromThis,
        ];
    }
    return $out;
}

/** Bir alışa bağlı TÜM tahsisler (iptal dahil, geçmiş görünsün diye) — cpa_allocation.php listesi. */
function cpa_alloc_list_for_purchase($pdo, $purchaseMovementId){
    cpa_alloc_install();
    try{
        $st=$pdo->prepare(
            "SELECT ca.*, si.name AS product_name, si.unit, c.name AS customer_name
             FROM cpa_allocations ca
             LEFT JOIN stock_items si ON si.id=ca.stock_item_id
             LEFT JOIN contacts c ON c.id=ca.customer_id
             WHERE ca.purchase_movement_id=?
             ORDER BY ca.created_at DESC"
        );
        $st->execute([(int)$purchaseMovementId]);
        return $st->fetchAll();
    }catch(Throwable $e){ return []; }
}

/** Bir müşterinin tüm tahsisleri — contact_view.php "Tahsisli Stok" bölümü. */
function cpa_alloc_list_for_customer($pdo, $customerId, $includeInactive=true){
    cpa_alloc_install();
    try{
        $sql="SELECT ca.*, si.name AS product_name, si.unit,
                     fm.movement_date AS purchase_date
              FROM cpa_allocations ca
              LEFT JOIN stock_items si ON si.id=ca.stock_item_id
              LEFT JOIN finance_movements fm ON fm.id=ca.purchase_movement_id
              WHERE ca.customer_id=?".($includeInactive?'':" AND ca.status='Aktif'")."
              ORDER BY ca.created_at DESC";
        $st=$pdo->prepare($sql);
        $st->execute([(int)$customerId]);
        return $st->fetchAll();
    }catch(Throwable $e){ return []; }
}

/** Bir ürünün tüm tahsisleri (hangi müşteriye ne kadar) — product_view.php "Tahsisli Stok" bölümü. */
function cpa_alloc_list_for_product($pdo, $stockItemId, $includeInactive=true){
    cpa_alloc_install();
    try{
        $sql="SELECT ca.*, c.name AS customer_name, fm.movement_date AS purchase_date
              FROM cpa_allocations ca
              LEFT JOIN contacts c ON c.id=ca.customer_id
              LEFT JOIN finance_movements fm ON fm.id=ca.purchase_movement_id
              WHERE ca.stock_item_id=?".($includeInactive?'':" AND ca.status='Aktif'")."
              ORDER BY ca.created_at DESC";
        $st=$pdo->prepare($sql);
        $st->execute([(int)$stockItemId]);
        return $st->fetchAll();
    }catch(Throwable $e){ return []; }
}

/**
 * Yeni tahsis oluşturur. Miktar, bu ALIŞTAN kalan serbest miktarı (purchased_qty - bu alıştan
 * zaten tahsis edilenler) AŞAMAZ — satın alınmamış bir şey tahsis edilemez.
 * @throws Exception yetkisiz erişim, geçersiz miktar veya kapasite aşımı durumunda
 * @return int yeni cpa_allocations.id
 */
function cpa_alloc_create($pdo, $userId, $purchaseMovementId, $stockItemId, $customerId, $qty, $notes=''){
    if(!cpa_can_edit()) throw new Exception('Bu işlem için yönetici yetkisi gerekir.');
    $purchaseMovementId=(int)$purchaseMovementId; $stockItemId=(int)$stockItemId; $customerId=(int)$customerId;
    $qty=(float)$qty;
    if(!$purchaseMovementId || !$stockItemId || !$customerId) throw new Exception('Alış, ürün ve müşteri seçimi zorunlu.');
    if($qty<=0) throw new Exception('Tahsis miktarı sıfırdan büyük olmalı.');
    cpa_alloc_install();

    $pm=$pdo->prepare("SELECT id FROM finance_movements WHERE id=? AND movement_type='purchase'");
    $pm->execute([$purchaseMovementId]);
    if(!$pm->fetch()) throw new Exception('Alış kaydı bulunamadı.');

    $purchasedQty=0.0;
    $pq=$pdo->prepare("SELECT COALESCE(SUM(quantity),0) t FROM stock_movements WHERE finance_movement_id=? AND direction='in' AND stock_item_id=?");
    $pq->execute([$purchaseMovementId,$stockItemId]);
    $purchasedQty=(float)($pq->fetch()['t'] ?? 0);
    if($purchasedQty<=0) throw new Exception('Bu ürün seçilen alışta bulunamadı.');

    $alreadyAlloc=0.0;
    $aa=$pdo->prepare("SELECT COALESCE(SUM(GREATEST(allocated_qty-consumed_qty,0)),0) t FROM cpa_allocations WHERE purchase_movement_id=? AND stock_item_id=? AND status<>'İptal'");
    $aa->execute([$purchaseMovementId,$stockItemId]);
    $alreadyAlloc=(float)($aa->fetch()['t'] ?? 0);

    $freeOnPurchase=$purchasedQty-$alreadyAlloc;
    if($qty>$freeOnPurchase+0.0000001){
        throw new Exception('Bu alıştan en fazla '.stock_qty_fmt($freeOnPurchase).' adet tahsis edilebilir (satın alınan '.stock_qty_fmt($purchasedQty).', zaten tahsisli '.stock_qty_fmt($alreadyAlloc).').');
    }

    $pdo->prepare("INSERT INTO cpa_allocations(purchase_movement_id,stock_item_id,customer_id,allocated_qty,consumed_qty,status,notes,created_by) VALUES(?,?,?,?,0,'Aktif',?,?)")
        ->execute([$purchaseMovementId,$stockItemId,$customerId,$qty,trim((string)$notes),$userId?:null]);
    $newId=(int)$pdo->lastInsertId();
    if(function_exists('audit_log')) audit_log($userId,'create','cpa_allocations',$newId,null,['purchase_movement_id'=>$purchaseMovementId,'stock_item_id'=>$stockItemId,'customer_id'=>$customerId,'allocated_qty'=>$qty]);
    return $newId;
}

/**
 * Tahsis miktarını azaltır/artırır (Product Owner: "tahsis azaltılabilsin"). Yeni miktar zaten
 * tüketilmiş kısmın (consumed_qty) altına düşürülemez — tüketilen bir şey geri alınamaz, önce o
 * kısım zaten satışla gerçekleşmiştir. Artırmak isterse çağıran taraf cpa_alloc_create()'in
 * kapasite kontrolünü BURADA da uygular (aynı alıştan fazlası tahsis edilemez).
 * @throws Exception yetkisiz erişim, kayıt yoksa veya geçersiz miktar
 */
function cpa_alloc_reduce($pdo, $userId, $id, $newQty){
    if(!cpa_can_edit()) throw new Exception('Bu işlem için yönetici yetkisi gerekir.');
    $id=(int)$id; $newQty=(float)$newQty;
    cpa_alloc_install();
    $cur=$pdo->prepare("SELECT * FROM cpa_allocations WHERE id=?"); $cur->execute([$id]); $old=$cur->fetch();
    if(!$old) throw new Exception('Tahsis kaydı bulunamadı.');
    if($old['status']==='İptal') throw new Exception('İptal edilmiş bir tahsis düzenlenemez.');
    if($newQty < (float)$old['consumed_qty'] - 0.0000001) throw new Exception('Yeni miktar, zaten tüketilen '.stock_qty_fmt($old['consumed_qty']).' adedin altına düşürülemez.');
    if($newQty > (float)$old['allocated_qty'] + 0.0000001){
        $extra=$newQty-(float)$old['allocated_qty'];
        $aa=$pdo->prepare("SELECT COALESCE(SUM(GREATEST(allocated_qty-consumed_qty,0)),0) t FROM cpa_allocations WHERE purchase_movement_id=? AND stock_item_id=? AND status<>'İptal' AND id<>?");
        $aa->execute([$old['purchase_movement_id'],$old['stock_item_id'],$id]);
        $othersAlloc=(float)($aa->fetch()['t'] ?? 0);
        $pq=$pdo->prepare("SELECT COALESCE(SUM(quantity),0) t FROM stock_movements WHERE finance_movement_id=? AND direction='in' AND stock_item_id=?");
        $pq->execute([$old['purchase_movement_id'],$old['stock_item_id']]);
        $purchasedQty=(float)($pq->fetch()['t'] ?? 0);
        if($newQty>$purchasedQty-$othersAlloc+0.0000001){
            throw new Exception('Bu alıştan en fazla '.stock_qty_fmt($purchasedQty-$othersAlloc).' adet tahsis edilebilir.');
        }
    }
    $newStatus=cpa_alloc_status_for($newQty,$old['consumed_qty'],$old['status']);
    $pdo->prepare("UPDATE cpa_allocations SET allocated_qty=?, status=? WHERE id=?")->execute([$newQty,$newStatus,$id]);
    if(function_exists('audit_log')) audit_log($userId,'update','cpa_allocations',$id,['allocated_qty'=>$old['allocated_qty'],'status'=>$old['status']],['allocated_qty'=>$newQty,'status'=>$newStatus]);
}

/** Tahsisi iptal eder (Product Owner: "iptal edilebilsin") — kalan miktar serbest stoğa döner,
 * kayıt SİLİNMEZ (geçmiş korunur, cpa_preferences ile aynı felsefe).
 * @throws Exception yetkisiz erişim veya kayıt yoksa */
function cpa_alloc_cancel($pdo, $userId, $id){
    if(!cpa_can_edit()) throw new Exception('Bu işlem için yönetici yetkisi gerekir.');
    $id=(int)$id;
    cpa_alloc_install();
    $cur=$pdo->prepare("SELECT * FROM cpa_allocations WHERE id=?"); $cur->execute([$id]); $old=$cur->fetch();
    if(!$old) throw new Exception('Tahsis kaydı bulunamadı.');
    if($old['status']==='İptal') return; // zaten iptal — no-op
    $pdo->prepare("UPDATE cpa_allocations SET status='İptal' WHERE id=?")->execute([$id]);
    if(function_exists('audit_log')) audit_log($userId,'update','cpa_allocations',$id,['status'=>$old['status']],['status'=>'İptal']);
}

/**
 * Bir tahsisin HENÜZ TÜKETİLMEMİŞ kalan kısmını başka bir müşteriye aktarır (Product Owner:
 * "başka satışa aktarım kontrollü olsun"). Kaynak tahsisten $qty kadar düşülür (tüketilen kısım
 * dokunulmaz), aynı miktarla YENİ bir tahsis satırı açılır (aynı alış/ürün, yeni müşteri) — geçmiş
 * bölünerek korunur, üzerine yazılmaz.
 * @throws Exception yetkisiz erişim, kayıt yoksa veya miktar kalanı aşıyorsa
 * @return int yeni tahsisin id'si
 */
function cpa_alloc_transfer($pdo, $userId, $id, $newCustomerId, $qty){
    if(!cpa_can_edit()) throw new Exception('Bu işlem için yönetici yetkisi gerekir.');
    $id=(int)$id; $newCustomerId=(int)$newCustomerId; $qty=(float)$qty;
    if(!$newCustomerId) throw new Exception('Aktarılacak müşteri seçilmedi.');
    if($qty<=0) throw new Exception('Aktarım miktarı sıfırdan büyük olmalı.');
    cpa_alloc_install();
    $cur=$pdo->prepare("SELECT * FROM cpa_allocations WHERE id=?"); $cur->execute([$id]); $old=$cur->fetch();
    if(!$old) throw new Exception('Tahsis kaydı bulunamadı.');
    if($old['status']==='İptal') throw new Exception('İptal edilmiş bir tahsis aktarılamaz.');
    $remaining=(float)$old['allocated_qty']-(float)$old['consumed_qty'];
    if($qty>$remaining+0.0000001) throw new Exception('Bu tahsisin kalanı ('.stock_qty_fmt($remaining).') aktarım miktarından az.');

    $ownsTx=!$pdo->inTransaction();
    if($ownsTx) $pdo->beginTransaction();
    try{
        $newAllocatedQty=(float)$old['allocated_qty']-$qty;
        $newStatus=cpa_alloc_status_for($newAllocatedQty,$old['consumed_qty'],$old['status']);
        $pdo->prepare("UPDATE cpa_allocations SET allocated_qty=?, status=? WHERE id=?")->execute([$newAllocatedQty,$newStatus,$id]);
        if(function_exists('audit_log')) audit_log($userId,'update','cpa_allocations',$id,['allocated_qty'=>$old['allocated_qty']],['allocated_qty'=>$newAllocatedQty,'transferred_to_customer_id'=>$newCustomerId,'transferred_qty'=>$qty]);

        $note='Aktarıldı: #'.$id.' tahsisinden';
        $pdo->prepare("INSERT INTO cpa_allocations(purchase_movement_id,stock_item_id,customer_id,allocated_qty,consumed_qty,status,notes,created_by) VALUES(?,?,?,?,0,'Aktif',?,?)")
            ->execute([$old['purchase_movement_id'],$old['stock_item_id'],$newCustomerId,$qty,$note,$userId?:null]);
        $newId=(int)$pdo->lastInsertId();
        if(function_exists('audit_log')) audit_log($userId,'create','cpa_allocations',$newId,null,['purchase_movement_id'=>$old['purchase_movement_id'],'stock_item_id'=>$old['stock_item_id'],'customer_id'=>$newCustomerId,'allocated_qty'=>$qty,'transferred_from_id'=>$id]);

        if($ownsTx) $pdo->commit();
    }catch(Throwable $e){
        if($ownsTx) $pdo->rollBack();
        throw $e;
    }
    return $newId;
}

/**
 * Satış tamamlandığında ilgili tahsisi tüketir (Product Owner: "satış tamamlandığında ilgili
 * tahsis tüketilsin"). sales.php/mobile/sales.php'de stock_create_sale() BAŞARIYLA döndükten
 * SONRA, satılan her satır için çağrılır — stock_lib.php'nin kendi stok/finans matematiğine hiç
 * karışmaz, SADECE cpa_allocations muhasebesini günceller. FIFO: en eski tahsisten başlar. Satılan
 * miktar müşterinin o üründeki aktif tahsislerinden fazlaysa, fazlası tüketilmeden kalır (fazlası
 * zaten "serbest stoktan satış" anlamına gelir — hata DEĞİL).
 *
 * Hata ASLA fırlatmaz (best-effort) — satış zaten gerçekleşmiş, bu sadece muhasebe kaydı;
 * çağıran taraf ekstra try/catch'e gerek duymadan çağırabilir.
 * @return float bu satıştan gerçekten tahsisten düşülen toplam miktar (0 ise hiç aktif tahsis yoktu)
 */
function cpa_alloc_consume_for_sale($pdo, $userId, $customerId, $stockItemId, $qty){
    try{
        cpa_alloc_install();
        $customerId=(int)$customerId; $stockItemId=(int)$stockItemId; $remaining=(float)$qty;
        if(!$customerId || !$stockItemId || $remaining<=0) return 0.0;

        $st=$pdo->prepare("SELECT * FROM cpa_allocations WHERE customer_id=? AND stock_item_id=? AND status='Aktif' ORDER BY created_at ASC, id ASC");
        $st->execute([$customerId,$stockItemId]);
        $rows=$st->fetchAll();

        $totalConsumed=0.0;
        foreach($rows as $row){
            if($remaining<=0.0000001) break;
            $rowRemaining=(float)$row['allocated_qty']-(float)$row['consumed_qty'];
            if($rowRemaining<=0.0000001) continue;
            $take=min($rowRemaining,$remaining);
            $newConsumed=(float)$row['consumed_qty']+$take;
            $newStatus=cpa_alloc_status_for($row['allocated_qty'],$newConsumed,$row['status']);
            $pdo->prepare("UPDATE cpa_allocations SET consumed_qty=?, status=? WHERE id=?")->execute([$newConsumed,$newStatus,$row['id']]);
            if(function_exists('audit_log')) audit_log($userId,'update','cpa_allocations',$row['id'],['consumed_qty'=>$row['consumed_qty']],['consumed_qty'=>$newConsumed,'consumed_by_sale'=>true]);
            $remaining-=$take;
            $totalConsumed+=$take;
        }
        return $totalConsumed;
    }catch(Throwable $e){
        return 0.0;
    }
}
