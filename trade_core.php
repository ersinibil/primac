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

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * P0 KAPANIŞ (2026-07-18, Product Owner kararı 3. madde) — ALIŞ/SATIŞ BELGESİ DÜZENLE/SİL
 * ═══════════════════════════════════════════════════════════════════════════════════════════
 * trade_documents kayıtları önceden oluşturulabiliyor ama hiçbir yerden düzenlenemiyor/silinemiyordu
 * — üstelik stock_lib.php'nin stock_can_edit_purchase()/stock_reverse_purchase()/stock_update_purchase()
 * (ve satış eşdeğerleri) document_id dolu kayıtları BİLEREK reddedip "belge görüntüleme ekranından
 * kontrol edin" diyordu — o ekran (burası) hiç yoktu. Bu bölüm o eksik ekranın iş mantığıdır.
 *
 * MİMARİ KARAR: yeni bir tersleme/uygulama matematiği İCAT EDİLMEDİ. stock_lib.php'nin zaten var
 * olan, test edilmiş primitives'i ($viaDocument=true parametresiyle, aynı avg_cost/CPA/negatif-stok
 * kapılarından geçerek) doğrudan çağrılır — stock_can_edit_purchase()/stock_reverse_purchase()/
 * stock_update_purchase()/stock_can_edit_sale()/stock_reverse_sale()/stock_update_sale(). Bu altı
 * fonksiyon artık $pdo->inTransaction() farkındalığına sahip (Flow Unification 001 ile AYNI desen,
 * trade_apply_document()'ın stock_create_purchase()/stock_create_sale()'i kullanma şekliyle simetrik)
 * — burada açılan TEK dış transaction'a katılırlar, kendi transaction'larını açmazlar.
 *
 * KONTROLLÜ İPTAL FELSEFESİ (Product Owner kararı): finansal olarak finalize edilmiş bir belge
 * fiziksel DELETE edilmez — trade_documents.status='İptal' olur (checks_notes'un durum makinesiyle
 * AYNI felsefe), trade_document_items (belgenin ne içerdiğinin kalıcı kaydı) SİLİNMEZ. Sadece bağlı
 * finance_movements/stock_movements/CPA etkisi (canlı finansal/stok etkisi) stock_lib.php'nin ortak
 * tersleme fonksiyonlarıyla TAM geri alınır — hiçbir yerde orphan kayıt bırakılmaz.
 */

/** Bir belgenin bağlı olduğu TEK finance_movements satırını bulur (document_id + doğru movement_type). */
function trade_document_movement($pdo, $documentId, $documentType){
    $mt = $documentType==='purchase' ? 'purchase' : 'sale';
    $s = $pdo->prepare("SELECT * FROM finance_movements WHERE document_id=? AND movement_type=?");
    $s->execute([(int)$documentId, $mt]);
    return $s->fetch() ?: null;
}

/**
 * Bir belgenin "Düzenle" ekranında güvenle açılıp açılamayacağını belirler. stock_can_edit_purchase()/
 * stock_can_edit_sale() ile AYNI kapılardan ($viaDocument=true) geçer — avg_cost/CPA/fiyat-türetme
 * mantığı TEKRARLANMADI, sadece document_id-dolu-reddi kapısı burada bilerek açılıyor.
 * @return array ['editable'=>bool,'reason'=>string|null,'doc'=>array|null,'movement_id'=>int,'lines'=>array]
 */
function trade_document_can_edit($pdo, $documentId){
    $ds=$pdo->prepare("SELECT * FROM trade_documents WHERE id=?");
    $ds->execute([(int)$documentId]);
    $doc=$ds->fetch();
    if(!$doc) return ['editable'=>false,'reason'=>'Belge bulunamadı.','doc'=>null,'movement_id'=>0,'lines'=>[]];
    if($doc['status']==='İptal') return ['editable'=>false,'reason'=>'Bu belge iptal edilmiş, düzenlenemez.','doc'=>$doc,'movement_id'=>0,'lines'=>[]];

    $mv = trade_document_movement($pdo, $documentId, $doc['document_type']);
    if(!$mv) return ['editable'=>false,'reason'=>'Belgeye bağlı finans hareketi bulunamadı.','doc'=>$doc,'movement_id'=>0,'lines'=>[]];

    if($doc['document_type']==='purchase'){
        $elig = stock_can_edit_purchase($pdo, $mv['id'], true);
    }else{
        $elig = stock_can_edit_sale($pdo, $mv['id'], true);
    }
    return ['editable'=>$elig['editable'],'reason'=>$elig['reason'],'doc'=>$doc,'movement_id'=>(int)$mv['id'],'lines'=>$elig['lines']];
}

/**
 * Belge düzenleme: trade_document_new.php'nin POST işleme mantığıyla BİREBİR aynı satır hazırlama
 * (trade_ensure_product() ortak fonksiyonu — kopya YOK), sonra stock_update_purchase()/
 * stock_update_sale() ($viaDocument=true) ile eski stok/CPA/finans etkisini TAM geri alıp yeniyi
 * uygular, en son trade_documents/trade_document_items (belgenin kendi kaydı) senkronize edilir.
 * Çağıran taraf ÖNCE trade_document_can_edit() ile uygunluğu kontrol etmeli.
 * @throws StockShortageException satış belgesinde onaysız yetersiz stok durumunda
 * @throws Exception diğer hata durumlarında
 */
function trade_document_update($pdo, $userId, $documentId, $contactId, $docDate, $description, $names, $stockIds, $units, $qtys, $prices, $vats, $confirmed=false){
    $can = trade_document_can_edit($pdo, $documentId);
    if(!$can['editable']) throw new Exception($can['reason']);
    $doc = $can['doc'];

    $subtotal=0; $vatTotal=0; $grandTotal=0; $prepared=[];
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
            list($stockId,$auto)=trade_ensure_product($name,$unit,$price,$doc['document_type']);
        }

        $line=$qty*$price;
        $lineVat=$line*$vat/100;
        $lineGrand=$line+$lineVat;
        $subtotal+=$line; $vatTotal+=$lineVat; $grandTotal+=$lineGrand;

        $prepared[]=[
            'stock_item_id'=>$stockId,'item_name'=>$name,'unit'=>$unit,'quantity'=>$qty,
            'unit_price'=>$price,'vat_rate'=>$vat,'line_total'=>$line,'line_vat'=>$lineVat,
            'line_grand'=>$lineGrand,'auto_created_product'=>$auto?1:0,
        ];
    }
    if(!$prepared) throw new Exception('En az bir ürün/hizmet satırı girilmelidir.');

    $ids=[]; $qtysArr=[]; $pricesArr=[]; $vatsArr=[];
    foreach($prepared as $it){ $ids[]=$it['stock_item_id']; $qtysArr[]=$it['quantity']; $pricesArr[]=$it['unit_price']; $vatsArr[]=$it['vat_rate']; }

    $pdo->beginTransaction();
    try{
        if($doc['document_type']==='purchase'){
            $res = stock_update_purchase($pdo, $can['movement_id'], $contactId, $ids, $qtysArr, $pricesArr, $vatsArr, 'Alış', true);
        }else{
            $res = stock_update_sale($pdo, $can['movement_id'], $contactId, $ids, $qtysArr, $pricesArr, $vatsArr, 'Satış', $confirmed, true);
        }
        if(!$res['ok']) throw new Exception($res['message']);

        $pdo->prepare("UPDATE trade_documents SET contact_id=?,document_date=?,subtotal=?,vat_total=?,grand_total=?,description=? WHERE id=?")
            ->execute([$contactId,$docDate,$subtotal,$vatTotal,$grandTotal,trim($description),$documentId]);

        $pdo->prepare("DELETE FROM trade_document_items WHERE document_id=?")->execute([$documentId]);
        $ins=$pdo->prepare("INSERT INTO trade_document_items(document_id,stock_item_id,item_name,unit,quantity,unit_price,vat_rate,line_total,line_vat,line_grand,auto_created_product)
            VALUES(?,?,?,?,?,?,?,?,?,?,?)");
        foreach($prepared as $it){
            $ins->execute([$documentId,$it['stock_item_id'],$it['item_name'],$it['unit'],$it['quantity'],$it['unit_price'],$it['vat_rate'],$it['line_total'],$it['line_vat'],$it['line_grand'],$it['auto_created_product']]);
        }

        // inTransaction() korumalı commit — activity_log()'un DDL implicit-commit riski, bkz.
        // trade_apply_document() üstündeki AYNI not.
        if($pdo->inTransaction()) $pdo->commit();
    }catch(Throwable $e){
        if($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }

    try{
        if(function_exists('activity_log')){
            $mod=$doc['document_type']==='purchase'?'Alış':'Satış';
            activity_log('Cari',$mod,$mod.' belgesi güncellendi',$doc['document_no'].' · '.trade_money($grandTotal),'trade_document',$documentId,base_url().'trade_document_view.php?id='.$documentId,'✏️');
        }
    }catch(Throwable $e){}

    return true;
}

/**
 * Belge iptali: stock_reverse_purchase()/stock_reverse_sale() ($viaDocument=true) ile bağlı
 * finance_movements/stock_movements/CPA etkisini TAM geri alır (aynı avg_cost/CPA güvenlik
 * kapılarından geçerek — aktif tahsis varsa veya avg_cost güvenle terslenemiyorsa reddedilir).
 * trade_documents SİLİNMEZ — status='İptal' işaretlenir, trade_document_items (ne satılmış/
 * alınmıştı) kalıcı kayıt olarak durur. @throws Exception uygun değilse
 */
function trade_document_cancel($pdo, $userId, $documentId){
    $ds=$pdo->prepare("SELECT * FROM trade_documents WHERE id=?");
    $ds->execute([(int)$documentId]);
    $doc=$ds->fetch();
    if(!$doc) throw new Exception('Belge bulunamadı.');
    if($doc['status']==='İptal') throw new Exception('Bu belge zaten iptal edilmiş.');

    $mv = trade_document_movement($pdo, $documentId, $doc['document_type']);
    if(!$mv) throw new Exception('Belgeye bağlı finans hareketi bulunamadı.');

    $pdo->beginTransaction();
    try{
        if($doc['document_type']==='purchase'){
            $res = stock_reverse_purchase($pdo, $mv['id'], true);
        }else{
            $res = stock_reverse_sale($pdo, $mv['id'], true);
        }
        if(!$res['ok']) throw new Exception($res['message']);

        $pdo->prepare("UPDATE trade_documents SET status='İptal' WHERE id=?")->execute([(int)$documentId]);

        if($pdo->inTransaction()) $pdo->commit();
    }catch(Throwable $e){
        if($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }

    try{
        if(function_exists('activity_log')){
            $mod=$doc['document_type']==='purchase'?'Alış':'Satış';
            activity_log('Cari',$mod,$mod.' belgesi iptal edildi',$doc['document_no'].' · '.trade_money($doc['grand_total']),'trade_document',$documentId,base_url().'trade_document_view.php?id='.$documentId,'🗑');
        }
    }catch(Throwable $e){}

    return true;
}
?>