<?php
// Cari (contact) paylaşılan yardımcı fonksiyonlar — sil.php (web) + mobile/contact_view.php ortak.

// Bir cariye bağlı finans/iş/belge/teklif/whatsapp kaydı var mı kontrol eder; varsa kalıcı silmek
// yerine pasife alır (contacts.active=0) — 2026-07-08, gerçek bir olayda cari silinince
// finance_movements/trade_documents gibi tablolarda "yetim" (contact_id artık hiçbir cariye işaret
// etmeyen) satırlar kaldığı tespit edildi (önceki kod FOREIGN_KEY_CHECKS=0 ile hiçbir kontrol
// yapmadan siliyordu). Her tablo kendi try/catch'i ile kontrol edilir — biri bu ortamda yoksa
// (şema farkı) işlemi durdurmaz, sadece o kontrolü atlar.
function contact_delete_or_deactivate($pdo, $id){
    $id=(int)$id;
    if($id<1) return ['ok'=>false,'deactivated'=>false,'msg'=>'Geçersiz cari.'];

    $refs=[
        'finance_movements' =>'contact_id',
        'jobs'              =>'customer_id',
        'trade_documents'   =>'contact_id',
        'checks_notes'      =>'contact_id',
        'quotes'            =>'customer_id',
        'wa_conversations'  =>'contact_id',
    ];
    $used=false;
    foreach($refs as $rt=>$rc){
        try{
            $cs=$pdo->prepare("SELECT COUNT(*) c FROM `$rt` WHERE `$rc`=?");
            $cs->execute([$id]);
            if((int)$cs->fetch()['c']>0){ $used=true; break; }
        }catch(Throwable $e){}
    }

    if($used){
        $pdo->prepare("UPDATE contacts SET active=0 WHERE id=?")->execute([$id]);
        return ['ok'=>true,'deactivated'=>true,'msg'=>'Bu cariye bağlı finans/iş/belge/teklif kaydı olduğu için kalıcı silinemedi, pasife alındı.'];
    }

    $pdo->prepare("DELETE FROM contact_representatives WHERE contact_id=?")->execute([$id]);
    $pdo->prepare("DELETE FROM contacts WHERE id=?")->execute([$id]);
    return ['ok'=>true,'deactivated'=>false,'msg'=>'Cari silindi.'];
}

/**
 * FİNANS ÇEKİRDEK DÜZELTMESİ (2026-07-10) — cari açık bakiye SQL ifadesi.
 *
 * Satış/Alış/Belge (movement_type: sale/mobile_sale/purchase/document) hareketleri KENDİ
 * direction'ı ile sayılır (satış cariye +borç, alış tedarikçiye +borç → -yönde). Tahsilat/Ödeme
 * (movement_type: normal/mobile) ise bu borcu KAPATAN olaylardır — işaretleri kasten TERS
 * çevrilir. Aksi halde "satış (Bekliyor, +1800) + o satışın kendi tahsilatı (+1800)" aynı yönde
 * toplanıp ÇİFT SAYILIR — bu session'ın başından beri süregelen "cari bakiye double-counting"
 * probleminin kök nedeni tam olarak buydu. Satış/alış kaydının kendisi HİÇ değiştirilmez (durumu
 * hep "Bekliyor" kalır) — kapanma sadece bu formülün Tahsilat/Ödeme'yi ters saymasıyla olur.
 *
 * @param string $alias sorgudaki finance_movements tablo/takma adı (örn. 'f' ya da tam ad)
 * @return string ham SQL CASE ifadesi (SUM(...) içine gömülmek üzere)
 */
function contact_balance_case_sql($alias='finance_movements'){
    // P0 KAPANIŞ DÜZELTMESİ (2026-07-18, Product Owner onayı): 'cek_senet' (kabul anı — çek/senet
    // ALINDIĞINDA/VERİLDİĞİNDE oluşan cari kapama hareketi, bkz. checks_notes_lib.php::
    // checks_notes_sync_finance()) ve 'cek_senet_ciro' (Ciro Et — checks_notes_endorse()) bu
    // formülün ELSE dalına (satış/alış "yeni borç" işareti) düşüyordu; oysa ikisi de niyet olarak
    // Tahsilat/Ödeme'nin BİREBİR eşdeğeri (checks_notes_sync_finance()'in kendi yorumu: "Tahsilat/
    // Ödeme ekranıyla BİREBİR aynı mantık") — yani normal/mobile ile AYNI ters-işaret dalına girmesi
    // gerekiyordu. Somut örnek: müşteriden 1.000 TL çek ALINDIĞINDA (direction='in') önceden +1.000
    // ekleniyordu (borcu artırıyormuş gibi), artık -1.000 ekleniyor (borç azalıyor) — normal bir
    // Tahsilat ile aynı sonuç. 'cek_senet_tahsil' (Tahsil Et/Öde) BURAYA dahil EDİLMEDİ çünkü o
    // hareketlerin contact_id'si HER ZAMAN NULL'dur (cariye ikinci kez dokunmaz, bkz.
    // checks_notes_collect()/pay() docblock'u) — hiçbir cari sorgusuyla eşleşmez, işareti sonucu
    // etkilemez. Bu formül HER ZAMAN canlı SUM() ile türetildiği (hiçbir yerde saklanmadığı) için bu
    // düzeltme geçmiş kayıtları AYRICA toplu güncellemeye gerek kalmadan otomatik olarak doğru
    // yansıtır — hiçbir finance_movements satırı değiştirilmedi, sadece yorumlama formülü düzeldi.
    return "CASE
        WHEN $alias.movement_type IN ('normal','mobile','cek_senet','cek_senet_ciro') THEN (CASE WHEN $alias.direction='in' THEN -$alias.amount ELSE $alias.amount END)
        ELSE (CASE WHEN $alias.direction='in' THEN $alias.amount ELSE -$alias.amount END)
    END";
}

/** Tek bir carinin açık bakiyesini hesaplar: opening_balance + düzeltilmiş net toplam. */
function contact_balance($pdo, $contactId){
    $contactId=(int)$contactId;
    $expr = contact_balance_case_sql('finance_movements');
    $s=$pdo->prepare("SELECT COALESCE(SUM($expr),0) s FROM finance_movements WHERE contact_id=?");
    $s->execute([$contactId]);
    $net=(float)$s->fetch()['s'];
    $ob=$pdo->prepare("SELECT COALESCE(opening_balance,0) ob FROM contacts WHERE id=?");
    $ob->execute([$contactId]);
    $opening=(float)($ob->fetch()['ob'] ?? 0);
    return $opening + $net;
}

// CARİ TEK MERKEZ (2026-07-19, Product Owner kararı — "kronolojik SADECE O CARİYE ait birleşik
// hareket akışı"). Yeni bir mimari/tablo İCAT EDİLMEDİ — finance_movements (Satış/Alış/Tahsilat/
// Ödeme/Çek-Senet/Transfer/Muhasebe, hepsi zaten contact_id taşıyor), jobs (customer_id) ve quotes
// (customer_id) 3 var olan kaynak WHERE contact_id/customer_id=? ile ayrı ayrı çekilip PHP'de
// tarihe göre birleştirilir (merge-sort) — SQL UNION yerine bu yöntem seçildi çünkü 3 tablonun
// kolon şekli çok farklı, UNION için hepsini aynı kalıba zorlamak daha kırılgan olurdu. Her sorgu
// KESİN olarak contact_id/customer_id=? ile filtreli — başka cariye ait satır asla karışamaz.
function contact_ledger_rows($pdo, $contactId, $limit=60){
    $contactId=(int)$contactId;
    $limit=max(1,(int)$limit);
    if($contactId<1) return [];
    $merged=[];

    try{
        $st=$pdo->prepare("SELECT fm.*, td.document_no FROM finance_movements fm
            LEFT JOIN trade_documents td ON td.id=fm.document_id
            WHERE fm.contact_id=? ORDER BY fm.id DESC LIMIT 300");
        $st->execute([$contactId]);
        foreach($st->fetchAll() as $r){
            $ts=strtotime(($r['movement_date'] ?: $r['created_at'] ?: 'now').' 00:00:00') ?: time();
            $merged[]=['kind'=>'finance','ts'=>$ts,'id'=>(int)$r['id'],'row'=>$r];
        }
    }catch(Throwable $e){}

    try{
        $st=$pdo->prepare("SELECT id,job_no,title,status,due_date,created_at FROM jobs WHERE customer_id=? ORDER BY id DESC LIMIT 300");
        $st->execute([$contactId]);
        foreach($st->fetchAll() as $r){
            $ts=strtotime($r['created_at'] ?: ($r['due_date'].' 00:00:00') ?: 'now') ?: time();
            $merged[]=['kind'=>'job','ts'=>$ts,'id'=>(int)$r['id'],'row'=>$r];
        }
    }catch(Throwable $e){}

    try{
        $st=$pdo->prepare("SELECT id,quote_no,total,status,quote_date,created_at FROM quotes WHERE customer_id=? ORDER BY id DESC LIMIT 300");
        $st->execute([$contactId]);
        foreach($st->fetchAll() as $r){
            $ts=strtotime($r['created_at'] ?: (($r['quote_date'] ?: '').' 00:00:00') ?: 'now') ?: time();
            $merged[]=['kind'=>'quote','ts'=>$ts,'id'=>(int)$r['id'],'row'=>$r];
        }
    }catch(Throwable $e){}

    usort($merged, function($a,$b){
        if($a['ts']!==$b['ts']) return $b['ts']<=>$a['ts'];
        return $b['id']<=>$a['id'];
    });

    return array_slice($merged,0,$limit);
}

// Yukarıdaki ham (kind/row) satırını ekrana basılacak normalize alanlara çevirir — web ve mobil
// contact_view.php AYNI bu fonksiyonu kullanır (tek yerden bakım). $pdo, finans satırlarında çek/
// senet/belge kaynağına drill-down için finance_movement_actions()'a geçiliyor (mevcut mekanizma,
// bu turda YENİDEN yazılmadı — sadece çağrıldı).
function contact_ledger_row_view($item, $pdo){
    $r=$item['row'];
    if($item['kind']==='finance'){
        $actions = function_exists('finance_movement_actions') ? finance_movement_actions($r,$pdo) : ['editable'=>false,'source_url'=>null,'source_label'=>null,'block_reason'=>null];
        $isDoc=!empty($r['document_id']);
        $mtype=$r['movement_type'];
        $openUrl=null;
        if($isDoc){ $openUrl='trade_document_view.php?id='.(int)$r['document_id']; }
        elseif(in_array($mtype,['sale','mobile_sale'],true)){ $openUrl='sale_view.php?id='.(int)$r['id']; }
        elseif($mtype==='purchase'){ $openUrl='purchase_view.php?id='.(int)$r['id']; }
        return [
            'kind'=>'finance','id'=>(int)$r['id'],
            'date'=>$r['movement_date'],
            'type'=>function_exists('finance_movement_type_label') ? finance_movement_type_label($r) : $mtype,
            'desc'=>($isDoc ? '['.($r['document_no'] ?: 'Belge').'] ' : '').(string)$r['description'],
            'amount'=>(float)$r['amount'],
            'sign'=>$r['direction']==='in' ? '+' : '-',
            'status'=>$r['status'],
            'open_url'=>$openUrl,
            'edit_url'=>($actions['editable'] ?? false) ? 'finance_new.php?id='.(int)$r['id'].'&return_context=contact&return_ref='.(int)($r['contact_id'] ?? 0) : null,
            'deletable'=>($actions['editable'] ?? false),
            'source_url'=>$actions['source_url'] ?? null,
            'source_label'=>$actions['source_label'] ?? null,
        ];
    }
    if($item['kind']==='job'){
        return ['kind'=>'job','id'=>(int)$r['id'],'date'=>$r['created_at'] ? substr($r['created_at'],0,10) : ($r['due_date'] ?: ''),
            'type'=>'İş Emri','desc'=>trim($r['job_no'].' — '.$r['title']),'amount'=>null,'sign'=>'','status'=>$r['status'],
            'open_url'=>'job_view.php?id='.(int)$r['id'],'edit_url'=>null,'deletable'=>false,'source_url'=>null,'source_label'=>null];
    }
    if($item['kind']==='quote'){
        return ['kind'=>'quote','id'=>(int)$r['id'],'date'=>$r['created_at'] ? substr($r['created_at'],0,10) : ($r['quote_date'] ?: ''),
            'type'=>'Teklif','desc'=>(string)$r['quote_no'],'amount'=>(float)$r['total'],'sign'=>'','status'=>$r['status'],
            'open_url'=>'teklif.php?id='.(int)$r['id'],'edit_url'=>null,'deletable'=>false,'source_url'=>null,'source_label'=>null];
    }
    return null;
}
