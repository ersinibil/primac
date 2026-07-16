<?php
/* PX-001B (2026-07-16) — İş Detay v1 gerçek veri katmanı. Web (job_view.php) ve mobil
 * (mobile/job_view.php) pilot dalının ORTAK kaynağı. Onaylanan mockup'taki "İş Özeti (adet)" ve
 * "Neden?/Sonra ne olacak?" genişletmesi bu tura dahil EDİLMEDİ — Product Owner mockup turunu
 * "artık ekran çizmeyi bırakın, gerçek veriyle çalıştırın" kararıyla kapattı; bu yüzden gerçek koda
 * geçirilen taban, o son revizyondan ÖNCEKİ onaylı 7 bölümlü job_detail_v1 mockup'ıdır.
 *
 * "İlgili Kişiler" mockup'ta 4 rol (Müşteri/Temsilci/Üretim/Montaj) gösteriyordu — şema
 * araştırması bunun karşılığı olmadığını ortaya çıkardı: `jobs` tablosunda TEK bir
 * `responsible_personnel_id` var, ayrı üretim/montaj/temsilci kolonu yok. Gerçek sürüm bilinçli
 * olarak 2 role indirildi: Müşteri + Sorumlu Personel.
 */

// Sonraki Adım — v1 kasıtlı basit kural zinciri (home_lib.php'nin puanlama felsefesiyle tutarlı,
// karmaşık bir karar motoru KURULMADI). Tam olarak TEK sonuç döner ya da null (dominant adım yok).
function job_detail_next_step($j, $pendingApprovalCount){
    $today = date('Y-m-d');
    $terminal = ['Tamamlandı','İptal','Teslim Edildi'];
    $isTerminal = in_array($j['status'] ?? '', $terminal, true);

    if(!$isTerminal && !empty($j['due_date']) && $j['due_date'] < $today){
        $days = (int)round((strtotime($today) - strtotime($j['due_date'])) / 86400);
        return ['icon'=>'call', 'title'=>'Müşteriyi Ara', 'sub'=>'Teslimat '.$days.' gün gecikti — haber ver'];
    }
    if($isTerminal && ($j['collection_status'] ?? '') === 'Bekliyor'){
        return ['icon'=>'money', 'title'=>'Tahsilatı Al', 'sub'=>'İş tamamlandı, ödeme bekleniyor'];
    }
    if($pendingApprovalCount > 0){
        return ['icon'=>'check', 'title'=>'Müşteriden Onay Takip Et', 'sub'=>$pendingApprovalCount.' dosya onay bekliyor'];
    }
    if(!$isTerminal && !empty($j['due_date'])){
        $daysLeft = (int)round((strtotime($j['due_date']) - strtotime($today)) / 86400);
        if($daysLeft >= 0 && $daysLeft <= 3){
            return ['icon'=>'clock', 'title'=>'Üretimi Tamamla', 'sub'=>'Termine '.$daysLeft.' gün kaldı'];
        }
    }
    return null;
}

// Zaman Akışı — activity_logs (entity_type='job') TEK kaynak seçildi (job_logs DEĞİL — backlog
// PX-001B-ÖN notuna bkz: web eskiden job_logs, mobil activity_logs kullanıyordu, iki ayrı kaynak
// birbirini görmüyordu; pilot ekran için activity_logs seçildi çünkü activity_recent() zaten hazır
// genel altyapı). Eski job_view.php/mobile/job_view.php'nin kendi log yazma davranışı DEĞİŞMEDİ.
function job_detail_timeline($jobId, $limit = 4){
    if(!function_exists('activity_recent')) return [];
    try{ return activity_recent($limit, 'job', (int)$jobId); }catch(Throwable $e){ return []; }
}

function job_detail_pending_approvals($pdo, $jobId){
    try{
        $s = $pdo->prepare("SELECT COUNT(*) c FROM job_files WHERE job_id=? AND approval_status='Müşteri Onayı Bekliyor'");
        $s->execute([$jobId]);
        return (int)($s->fetch()['c'] ?? 0);
    }catch(Throwable $e){ return 0; }
}
