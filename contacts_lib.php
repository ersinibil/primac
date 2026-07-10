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
    return "CASE
        WHEN $alias.movement_type IN ('normal','mobile') THEN (CASE WHEN $alias.direction='in' THEN -$alias.amount ELSE $alias.amount END)
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
