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
