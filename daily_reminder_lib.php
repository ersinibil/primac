<?php
// ACANS OTS — Sabah hatırlatma: her personele bugün bekleyen iş + görevleri
// İç mesaj + bildirim + push + (gateway tanımlıysa) WhatsApp. Günde 1 kez, 09:30 sonrası.
function check_daily_reminders($pdo, $force=false){
    if(!$force && date('H:i') < '09:30') return;            // 09:30'dan önce çalışma
    $dir=__DIR__.'/uploads'; if(!is_dir($dir)) @mkdir($dir,0777,true);
    $lock=$dir.'/.daily_reminder_'.date('Ymd');
    if(!$force && is_file($lock)) return;                    // bugün zaten gönderildi
    @touch($lock);

    $hasPush=file_exists(__DIR__.'/push_lib.php'); if($hasPush) require_once __DIR__.'/push_lib.php';
    $hasWa=file_exists(__DIR__.'/share_lib.php');  if($hasWa)  require_once __DIR__.'/share_lib.php';

    // Aktif + personele bağlı kullanıcılar (telefon WhatsApp için)
    try{
        $users=$pdo->query("SELECT u.id, u.personnel_id, p.name, p.phone
            FROM app_users u JOIN personnel p ON p.id=u.personnel_id
            WHERE u.active=1 AND u.personnel_id IS NOT NULL")->fetchAll();
    }catch(Throwable $e){ return; }
    if(!$users) return;

    $jobStmt=$pdo->prepare("SELECT job_no,title,due_date FROM jobs
        WHERE responsible_personnel_id=? AND status NOT IN ('Tamamlandı','Teslim Edildi','İptal')
        ORDER BY (due_date IS NULL), due_date LIMIT 25");
    $taskStmt=$pdo->prepare("SELECT title,due_date FROM tasks
        WHERE personnel_id=? AND status NOT IN ('Tamamlandı','İptal')
        ORDER BY (due_date IS NULL), due_date LIMIT 25");
    $insN=$pdo->prepare("INSERT INTO internal_notifications(title,message,target_user_id,is_read) VALUES(?,?,?,0)");
    $insM=$pdo->prepare("INSERT INTO internal_messages(sender_user_id,receiver_user_id,message,is_read) VALUES(NULL,?,?,0)");

    foreach($users as $u){
        $pid=(int)$u['personnel_id'];
        try{ $jobStmt->execute([$pid]); $jobs=$jobStmt->fetchAll(); }catch(Throwable $e){ $jobs=[]; }
        try{ $taskStmt->execute([$pid]); $tasks=$taskStmt->fetchAll(); }catch(Throwable $e){ $tasks=[]; }
        if(!$jobs && !$tasks) continue;

        $lines=['🌅 Günaydın '.$u['name'].'! Bugün bekleyen işlerin:'];
        foreach($jobs as $j){ $lines[]='• '.$j['title'].($j['job_no']?' ('.$j['job_no'].')':'').($j['due_date']?' — termin '.$j['due_date']:''); }
        foreach($tasks as $t){ $lines[]='◦ Görev: '.$t['title'].($t['due_date']?' — '.$t['due_date']:''); }
        $msg=implode("\n",$lines);
        $uid=(int)$u['id'];

        try{ $insN->execute(['🌅 Bugün bekleyen işlerin',$msg,$uid]); }catch(Throwable $e){}
        try{ $insM->execute([$uid,$msg]); }catch(Throwable $e){}
        if($hasPush){ try{ push_to_user($uid,'🌅 Bugün bekleyen işlerin',count($jobs).' iş · '.count($tasks).' görev','mytasks.php'); }catch(Throwable $e){} }
        if(!empty($u['phone']) && function_exists('wa_send')){ try{ wa_send($u['phone'],$msg); }catch(Throwable $e){} }
    }

    // --- Yöneticilere: TÜM personelin bugünkü işleri (tek özet rapor) ---
    try{ $admins=$pdo->query("SELECT id FROM app_users WHERE role IN('admin','yonetici','yönetici') AND active=1")->fetchAll(PDO::FETCH_COLUMN); }
    catch(Throwable $e){ $admins=[]; }
    if($admins){
        try{ $pers=$pdo->query("SELECT id,name FROM personnel WHERE COALESCE(active,1)=1 ORDER BY name")->fetchAll(); }
        catch(Throwable $e){ $pers=[]; }
        $jc=$pdo->prepare("SELECT COUNT(*) c FROM jobs WHERE responsible_personnel_id=? AND status NOT IN('Tamamlandı','Teslim Edildi','İptal')");
        $tc=$pdo->prepare("SELECT COUNT(*) c FROM tasks WHERE personnel_id=? AND status NOT IN('Tamamlandı','İptal')");
        $rep=['📊 GÜNLÜK İŞ RAPORU — '.date('d.m.Y'),'']; $anyWork=false;
        foreach($pers as $p){
            try{ $jc->execute([$p['id']]); $nj=(int)$jc->fetch()['c']; }catch(Throwable $e){ $nj=0; }
            try{ $tc->execute([$p['id']]); $nt=(int)$tc->fetch()['c']; }catch(Throwable $e){ $nt=0; }
            if($nj || $nt){ $rep[]='• '.$p['name'].': '.$nj.' iş · '.$nt.' görev'; $anyWork=true; }
        }
        if(!$anyWork) $rep[]='Bugün bekleyen iş/görev yok. 🎉';
        $rep[]='';
        $rep[]='📄 Detaylı rapor: gunluk_rapor.php';
        $rmsg=implode("\n",$rep);
        foreach($admins as $aid){ $aid=(int)$aid;
            try{ $insN->execute(['📊 Günlük iş raporu',$rmsg,$aid]); }catch(Throwable $e){}
            try{ $insM->execute([$aid,$rmsg]); }catch(Throwable $e){}
            if($hasPush){ try{ push_to_user($aid,'📊 Günlük iş raporu','Tüm personelin bugünkü işleri','gunluk_rapor.php'); }catch(Throwable $e){} }
        }
        // Yönetici WhatsApp (app_user'ı personele bağlı ve telefonu olanlar)
        try{
            $aph=$pdo->query("SELECT p.phone FROM app_users u JOIN personnel p ON p.id=u.personnel_id WHERE u.role IN('admin','yonetici','yönetici') AND u.active=1 AND p.phone<>''")->fetchAll(PDO::FETCH_COLUMN);
            if(function_exists('wa_send')) foreach($aph as $ph){ try{ wa_send($ph,$rmsg); }catch(Throwable $e){} }
        }catch(Throwable $e){}
    }
}
