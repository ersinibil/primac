<?php
// ACANS OTS — Geciken iş otomatik bildirimi (sorumlu + yöneticiler)
// Saatte bir (dosya kilidi ile) tarar; gün içinde her iş için bir kez bildirir.
function check_overdue_jobs($pdo){
    $dir=__DIR__.'/uploads'; if(!is_dir($dir)) @mkdir($dir,0777,true);
    $lock=$dir.'/.overdue_lock';
    if(is_file($lock) && (time()-@filemtime($lock))<3600) return; // throttle 1 saat
    @touch($lock);
    try{ $pdo->exec("ALTER TABLE jobs ADD COLUMN overdue_notified_at DATE NULL"); }catch(Throwable $e){}
    $today=date('Y-m-d');
    try{
        $rows=$pdo->query("SELECT id,title,job_no,due_date,responsible_personnel_id FROM jobs
            WHERE due_date IS NOT NULL AND due_date<'$today'
            AND status NOT IN ('Tamamlandı','Teslim Edildi','İptal')
            AND (overdue_notified_at IS NULL OR overdue_notified_at<'$today')")->fetchAll();
    }catch(Throwable $e){ return; }
    if(!$rows) return;
    $admins=[]; try{ $admins=$pdo->query("SELECT id FROM app_users WHERE role='admin' AND active=1")->fetchAll(PDO::FETCH_COLUMN); }catch(Throwable $e){}
    $hasPush=file_exists(__DIR__.'/push_lib.php'); if($hasPush) require_once __DIR__.'/push_lib.php';
    $ins=$pdo->prepare("INSERT INTO internal_notifications(title,message,target_user_id,is_read) VALUES(?,?,?,0)");
    $resStmt=$pdo->prepare("SELECT id FROM app_users WHERE personnel_id=? AND active=1 LIMIT 1");
    $upd=$pdo->prepare("UPDATE jobs SET overdue_notified_at=? WHERE id=?");
    foreach($rows as $j){
        $title='⚠️ Geciken İş';
        $msg=$j['title'].($j['job_no']?' ('.$j['job_no'].')':'').' — termin '.$j['due_date'].' geçti';
        $targets=[];
        if($j['responsible_personnel_id']){ try{ $resStmt->execute([$j['responsible_personnel_id']]); $r=$resStmt->fetch(); if($r) $targets[(int)$r['id']]=(int)$r['id']; }catch(Throwable $e){} }
        foreach($admins as $a){ $a=(int)$a; if($a) $targets[$a]=$a; }
        foreach($targets as $tu){
            try{ $ins->execute([$title,$msg,$tu]); }catch(Throwable $e){}
            if($hasPush){ try{ push_to_user($tu,$title,$msg,'job_view.php?id='.$j['id']); }catch(Throwable $e){} }
        }
        try{ $upd->execute([$today,$j['id']]); }catch(Throwable $e){}
    }
}
