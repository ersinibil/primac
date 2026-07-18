<?php
// ACANS OS v19 Trade Helper

if(file_exists(__DIR__.'/activity_lib.php')) require_once __DIR__.'/activity_lib.php';
require_once __DIR__.'/stock_lib.php';
require_once __DIR__.'/cpa_allocation_lib.php';

function trade_next_no($type){
    $prefix=$type==='purchase'?'ALI':'SAT';
    return $prefix.'-'.date('Ymd').'-'.random_int(1000,9999);
}

function trade_money($v){
    return number_format((float)$v,2,',','.').' ₺';
}

// Finans yetkisi olmayan kullanıcıya gerçek hesap adı/bakiyesi göstermemek için: form'dan gelen
// değer ya doğrudan bir hesap id'si (finance yetkili kullanıcı, dropdown'da gerçek hesapları
// görüyor) ya da bir account_type etiketi (finance yetkisiz kullanıcı, sales.php'deki soyut
// ödeme yöntemi deseniyle aynı) olabilir — ikinci durumda o tipteki İLK aktif hesabı bulup
// id'sini döner. $restrictToType=true iken (çağıran user_can('finance') değilse) sayısal girdi
// bile DOĞRUDAN kabul edilmez — sadece account_type metnine göre çözülür; aksi halde finans
// yetkisi olmayan biri, dropdown'da hesap adı/bakiye görmese de DevTools/POST ile bilinen bir
// account_id'yi doğrudan gönderip belirli bir hesabı seçmeye zorlayabilirdi (2026-07-09, Selin'in
// güvenlik denetiminde bulundu).
function trade_account_resolve($pdo, $raw, $restrictToType=false){
    $raw = trim((string)$raw);
    if($raw === '') return null;
    if(!$restrictToType && ctype_digit($raw)) return (int)$raw;
    try{
        $s=$pdo->prepare("SELECT id FROM finance_accounts WHERE account_type=? AND active=1 ORDER BY id LIMIT 1");
        $s->execute([$raw]);
        $r=$s->fetch();
        return $r ? (int)$r['id'] : null;
    }catch(Throwable $e){ return null; }
}

function trade_ensure_product($name,$unit,$unitPrice,$type){
    $pdo=db();

    $s=$pdo->prepare("SELECT * FROM stock_items WHERE name=? LIMIT 1");
    $s->execute([trim($name)]);
    $existing=$s->fetch();
    if($existing) return [(int)$existing['id'], false];

    $code='URN-'.date('ymd').'-'.random_int(100,999);

    $purchasePrice=$type==='purchase'?(float)$unitPrice:0;
    $salePrice=$type==='sale'?(float)$unitPrice:0;

    $stmt=$pdo->prepare("INSERT INTO stock_items(
        product_code,name,unit,quantity,critical_level,purchase_price,sale_price,avg_cost,last_purchase_price,active
    ) VALUES(?,?,?,?,?,?,?,?,?,1)");
    $stmt->execute([
        $code,
        trim($name),
        trim($unit) ?: 'adet',
        0,
        0,
        $purchasePrice,
        $salePrice,
        $purchasePrice,
        $purchasePrice
    ]);

    return [(int)$pdo->lastInsertId(), true];
}

/**
 * Belge onaylandıktan sonra stok+finans etkisini uygular. Flow Unification 001 (2026-07-11):
 * artık kendi inline stok/avg_cost/finance_movements mantığını YAZMAZ — stock_lib.php'nin ortak
 * çekirdeğine (stock_create_purchase()/stock_create_sale()) devreder. Sonuç: movement_type artık
 * 'purchase'/'sale' olur (eski 'document' tipi YENİ kayıtlarda bir daha oluşmaz), document_id ile
 * belgeye bağlanır, account_id her zaman NULL, status her zaman 'Bekliyor' — belge oluşturma
 * kasa/banka/current_balance'ı hiçbir zaman etkilemez (Finance Core Stabilization ile aynı kural).
 *
 * Bu fonksiyon TRANSACTION YÖNETMEZ — transaction sahibi çağırandır (trade_document_new.php).
 * Burada atılan her exception (StockShortageException dahil) yutulmadan doğrudan yukarı taşınır;
 * dış transaction'ı rollback etmek çağıranın sorumluluğudur.
 * @param bool $confirmed satış belgesinde Kontrollü Negatif Stok Politikası onayı (alışta kullanılmaz)
 * @throws StockShortageException satışta onaysız yetersiz stok durumunda
 * @throws Exception diğer hata durumlarında (belge bulunamadı, geçersiz satır, vb.)
 */
function trade_apply_document($documentId, $confirmed=false){
    $pdo=db();

    $ds=$pdo->prepare("SELECT d.*, c.name contact_name FROM trade_documents d LEFT JOIN contacts c ON c.id=d.contact_id WHERE d.id=?");
    $ds->execute([$documentId]);
    $doc=$ds->fetch();
    if(!$doc) throw new Exception('Belge bulunamadı.');

    $items=$pdo->prepare("SELECT * FROM trade_document_items WHERE document_id=?");
    $items->execute([$documentId]);
    $rows=$items->fetchAll();

    $ids=[]; $qtys=[]; $prices=[]; $vats=[];
    foreach($rows as $it){
        if(!$it['stock_item_id']) continue;
        $ids[]=$it['stock_item_id'];
        $qtys[]=$it['quantity'];
        $prices[]=$it['unit_price'];
        $vats[]=$it['vat_rate'];
    }

    if($ids){
        $mod = $doc['document_type']==='purchase' ? 'Alış' : 'Satış';
        $noteLabel = $mod.' belgesi: '.$doc['document_no'];
        if($doc['document_type']==='purchase'){
            stock_create_purchase($pdo, $doc['contact_id'], $ids, $qtys, $prices, $vats, $noteLabel, $documentId);
        }else{
            $__saleRes = stock_create_sale($pdo, $doc['contact_id'], $ids, $qtys, $prices, $vats, $noteLabel, $confirmed, $documentId);
            // P0 SON KAPANIŞ (2026-07-18) — sales.php'nin "Hızlı Satış" yoluyla aynı otomatik CPA
            // tahsis tüketimi; Satış Belgesi (trade_document_new.php) da bu TEK ortak fonksiyonu
            // kullandığı için buraya eklendi (iki ayrı yerde tekrarlamak yerine). Hata fırlatmaz.
            foreach($ids as $__i=>$__pid){
                cpa_alloc_consume_for_sale($pdo, $_SESSION['user']['id'] ?? 0, $__saleRes['sale_id'], $doc['contact_id'], (int)$__pid, (float)($qtys[$__i] ?? 0));
            }
        }
    }

    // try/catch ile korunuyor (Flow Unification 001, 2026-07-11): bu fonksiyon artık çağıranın
    // (trade_document_new.php) transaction'ı içinde çalışıyor — activity_log() burada patlarsa
    // korumasız bırakılırsa aksi halde TAMAMEN başarılı olan belge+stok+finans işlemi sırf bir
    // loglama hatası yüzünden rollback edilirdi. Diğer tüm activity_log çağrı yerleriyle (sales.php/
    // purchase.php) aynı savunmacı desen.
    try{
        if(function_exists('activity_log')){
            $mod=$doc['document_type']==='purchase'?'Alış':'Satış';
            $icon=$doc['document_type']==='purchase'?'🛒':'🧾';
            activity_log('Cari',$mod,$mod.' belgesi oluşturuldu',($doc['contact_name'] ?: 'Cari yok').' · '.$doc['document_no'].' · '.trade_money($doc['grand_total']),'trade_document',$documentId,base_url().'trade_document_view.php?id='.$documentId,$icon);
        }
    }catch(Throwable $e){}
}
?>