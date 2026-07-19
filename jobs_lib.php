<?php
// İş Emri paylaşılan yardımcı fonksiyonlar — sil.php (web) + mobile/job_view.php ortak.
//
// İŞ EMRİ YAŞAM DÖNGÜSÜ TAMAMLAMA (2026-07-19, USER TEST bulgusu — "kayıt oluşturulabilir ama
// artık kaldırılamaz" durumu). Önceden İKİ AYRI kör-DELETE yolu vardı: sil.php'nin genel (guard'sız)
// fallback bloğu (web) ve mobile/job_view.php'nin kendi inline delete_job handler'ı (mobil) — ikisi
// de job_id'ye bağlı finance_movements/stock_movements'ı HİÇ kontrol etmiyordu. Bu fonksiyon
// ikisinin yerine geçen TEK ortak nokta — yeni bir paralel silme sistemi DEĞİL, var olan iki
// kör-silme yolunun birleştirilip güvenli hale getirilmiş hali.
function job_delete_or_deactivate($pdo, $userId, $jobId){
    $jobId=(int)$jobId;
    if($jobId<1) return ['ok'=>false,'msg'=>'Geçersiz iş emri.'];

    // Finansal/stok etkisi varsa doğrudan silme YASAK — bunlar kendi kaynak ekranından (Finans
    // Hareketleri / Stok Hareketleri) geri alınmalı, burada tersleme İCAT EDİLMEDİ.
    $blockers=[];
    try{
        $c=$pdo->prepare("SELECT COUNT(*) c FROM finance_movements WHERE job_id=?");
        $c->execute([$jobId]);
        $n=(int)$c->fetch()['c'];
        if($n>0) $blockers[]=$n.' finans hareketi';
    }catch(Throwable $e){}
    try{
        $c=$pdo->prepare("SELECT COUNT(*) c FROM stock_movements WHERE job_id=?");
        $c->execute([$jobId]);
        $n=(int)$c->fetch()['c'];
        if($n>0) $blockers[]=$n.' stok hareketi';
    }catch(Throwable $e){}

    if($blockers){
        return ['ok'=>false,'blocked'=>true,
            'msg'=>'Bu iş emri doğrudan silinemez — bağlı '.implode(' ve ',$blockers).' var. '
                .'Önce ilgili finans hareketini (Finans Hareketleri) veya stok hareketini (Ürün Detayı → Stok Hareketleri) '
                .'kendi ekranından geri alın/iptal edin, sonra tekrar deneyin.'];
    }

    $ownsTx = !$pdo->inTransaction();
    if($ownsTx) $pdo->beginTransaction();
    try{
        // management_requests bu işe referans veriyorsa (management_requests.related_job_id) talep
        // kaydının kendisi SİLİNMEZ — sadece bağlantısı NULL'a çekilir (talep geçmişi/orphan kalmaz).
        try{ $pdo->prepare("UPDATE management_requests SET related_job_id=NULL WHERE related_job_id=?")->execute([$jobId]); }catch(Throwable $e){}

        // İşe özel alt kayıtlar (kendi başlarına ayrı bir iş anlamı taşımıyorlar — aşama/dosya/not/
        // log/görev hepsi bu işin bir parçası) — job_files fiziksel dosyaları silinmiyor (uploads/
        // altında kalabilirler, mevcut sil.php generic akışıyla aynı davranış, kapsam genişletilmedi).
        foreach(['job_stages'=>'job_id','job_files'=>'job_id','job_notes'=>'job_id','job_logs'=>'job_id','tasks'=>'job_id'] as $ct=>$cf){
            try{ $pdo->prepare("DELETE FROM `$ct` WHERE `$cf`=?")->execute([$jobId]); }catch(Throwable $e){}
        }

        $pdo->prepare("DELETE FROM jobs WHERE id=?")->execute([$jobId]);
        if($ownsTx) $pdo->commit();
    }catch(Throwable $e){
        if($ownsTx) $pdo->rollBack();
        return ['ok'=>false,'msg'=>'Silme hatası: '.$e->getMessage()];
    }

    try{ if(function_exists('activity_log')) activity_log('Silme','İş emri silindi','jobs #'.$jobId,'','admin',null,'jobs.php','🗑'); }catch(Throwable $e){}
    return ['ok'=>true,'msg'=>'İş emri silindi.'];
}
