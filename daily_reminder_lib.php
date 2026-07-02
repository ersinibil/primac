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
    $insN=$pdo->prepare("INSERT INTO internal_notifications(title,message,target_user_id,action_url,is_read) VALUES(?,?,?,?,0)");

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

        try{ $insN->execute(['🌅 Bugün bekleyen işlerin',$msg,$uid,'mytasks.php']); }catch(Throwable $e){}
        if($hasPush){ try{ push_to_user($uid,'🌅 Bugün bekleyen işlerin',count($jobs).' iş · '.count($tasks).' görev','mytasks.php'); }catch(Throwable $e){} }
        if(!empty($u['phone']) && function_exists('wa_send')){ try{ wa_send($u['phone'],$msg); }catch(Throwable $e){} }
    }

    // --- Yöneticilere: TÜM personelin bugünkü işleri (tek özet rapor) ---
    try{ $admins=$pdo->query("SELECT id,email FROM app_users WHERE role IN('admin','yonetici','yönetici') AND active=1")->fetchAll(); }
    catch(Throwable $e){ $admins=[]; }
    if($admins){
        try{ $pers=$pdo->query("SELECT id,name FROM personnel WHERE COALESCE(active,1)=1 ORDER BY name")->fetchAll(); }
        catch(Throwable $e){ $pers=[]; }
        $jc=$pdo->prepare("SELECT COUNT(*) c FROM jobs WHERE responsible_personnel_id=? AND status NOT IN('Tamamlandı','Teslim Edildi','İptal')");
        $tc=$pdo->prepare("SELECT COUNT(*) c FROM tasks WHERE personnel_id=? AND status NOT IN('Tamamlandı','İptal')");

        // Geciken işler
        $geciken=0;
        try{ $geciken=(int)$pdo->query("SELECT COUNT(*) c FROM jobs WHERE due_date<CURDATE() AND status NOT IN('Tamamlandı','Teslim Edildi','İptal')")->fetch()['c']; }catch(Throwable $e){}

        $persRows=[]; $anyWork=false; $topIs=0; $topGov=0;
        foreach($pers as $p){
            try{ $jc->execute([$p['id']]); $nj=(int)$jc->fetch()['c']; }catch(Throwable $e){ $nj=0; }
            try{ $tc->execute([$p['id']]); $nt=(int)$tc->fetch()['c']; }catch(Throwable $e){ $nt=0; }
            if($nj || $nt){ $persRows[]=[$p['name'],$nj,$nt]; $anyWork=true; $topIs+=$nj; $topGov+=$nt; }
        }

        // Metin raporu (iç mesaj + WA)
        $rep=['📊 GÜNLÜK İŞ RAPORU — '.date('d.m.Y'),''];
        foreach($persRows as $r){ $rep[]='• '.$r[0].': '.$r[1].' iş · '.$r[2].' görev'; }
        if(!$anyWork) $rep[]='Bugün bekleyen iş/görev yok. 🎉';
        if($geciken) $rep[]='⚠️ Geciken iş: '.$geciken;
        $rep[]=''; $rep[]='📄 Detaylı rapor: '.base_url().'gunluk_rapor.php';
        $rmsg=implode("\n",$rep);

        $adminIds=array_column($admins,'id');
        foreach($adminIds as $aid){ $aid=(int)$aid;
            try{ $insN->execute(['📊 Günlük iş raporu',$rmsg,$aid,'gunluk_rapor.php']); }catch(Throwable $e){}
            if($hasPush){ try{ push_to_user($aid,'📊 Günlük iş raporu','Tüm personelin bugünkü işleri','gunluk_rapor.php'); }catch(Throwable $e){} }
        }

        // Yönetici WhatsApp
        try{
            $aph=$pdo->query("SELECT p.phone FROM app_users u JOIN personnel p ON p.id=u.personnel_id WHERE u.role IN('admin','yonetici','yönetici') AND u.active=1 AND p.phone<>''")->fetchAll(PDO::FETCH_COLUMN);
            if(function_exists('wa_send')) foreach($aph as $ph){ try{ wa_send($ph,$rmsg); }catch(Throwable $e){} }
        }catch(Throwable $e){}

        // Yönetici E-posta — HTML rapor (mail() ile, SMTP kurulu değilse sessiz geçer)
        $appName='';
        try{ $cfg=app_config(); $appName=$cfg['app_name'] ?? 'OTS'; }catch(Throwable $e){}
        $reportUrl=base_url().'gunluk_rapor.php';
        $tarih=date('d.m.Y');
        $tableRows='';
        foreach($persRows as $r){
            $tableRows.='<tr><td style="padding:8px 12px;border-bottom:1px solid #f1f5f9">'.htmlspecialchars($r[0]).'</td>'
                .'<td style="padding:8px 12px;border-bottom:1px solid #f1f5f9;text-align:center;font-weight:700;color:#2563eb">'.$r[1].'</td>'
                .'<td style="padding:8px 12px;border-bottom:1px solid #f1f5f9;text-align:center;font-weight:700;color:#7c3aed">'.$r[2].'</td></tr>';
        }
        if(!$tableRows) $tableRows='<tr><td colspan="3" style="padding:12px;text-align:center;color:#16a34a">Bugün bekleyen iş/görev yok 🎉</td></tr>';
        $gecikenHtml=$geciken?"<p style='margin:0 0 16px;background:#fee2e2;color:#991b1b;border-radius:8px;padding:10px 14px'>⚠️ <b>{$geciken} geciken iş</b> — <a href='{$reportUrl}' style='color:#991b1b'>raporda gör</a></p>":'';
        $html='<!doctype html><html><head><meta charset="utf-8"></head><body style="margin:0;background:#f5f7fb;font-family:-apple-system,Arial,sans-serif">
<div style="max-width:580px;margin:24px auto;background:#fff;border-radius:14px;overflow:hidden;box-shadow:0 2px 12px rgba(0,0,0,.08)">
<div style="background:#071326;color:#fff;padding:20px 24px">
  <div style="font-size:22px;font-weight:900">📊 Günlük İş Raporu</div>
  <div style="color:#94a3b8;font-size:14px;margin-top:4px">'.htmlspecialchars($appName).' · '.$tarih.'</div>
</div>
<div style="padding:20px 24px">
  '.$gecikenHtml.'
  <div style="display:flex;gap:12px;margin-bottom:20px">
    <div style="flex:1;background:#eff6ff;border-radius:10px;padding:14px;text-align:center"><div style="font-size:28px;font-weight:900;color:#2563eb">'.$topIs.'</div><div style="font-size:12px;color:#6b7280;margin-top:2px">Bekleyen İş</div></div>
    <div style="flex:1;background:#f5f3ff;border-radius:10px;padding:14px;text-align:center"><div style="font-size:28px;font-weight:900;color:#7c3aed">'.$topGov.'</div><div style="font-size:12px;color:#6b7280;margin-top:2px">Bekleyen Görev</div></div>
    '.($geciken?'<div style="flex:1;background:#fef2f2;border-radius:10px;padding:14px;text-align:center"><div style="font-size:28px;font-weight:900;color:#dc2626">'.$geciken.'</div><div style="font-size:12px;color:#6b7280;margin-top:2px">Geciken</div></div>':'').'
  </div>
  <table style="width:100%;border-collapse:collapse;font-size:14px">
    <thead><tr style="background:#f8fafc"><th style="padding:8px 12px;text-align:left;font-size:12px;color:#6b7280;font-weight:700">PERSONEL</th><th style="padding:8px 12px;font-size:12px;color:#6b7280;font-weight:700">İŞ</th><th style="padding:8px 12px;font-size:12px;color:#6b7280;font-weight:700">GÖREV</th></tr></thead>
    <tbody>'.$tableRows.'</tbody>
  </table>
  <div style="margin-top:20px;text-align:center">
    <a href="'.htmlspecialchars($reportUrl).'" style="display:inline-block;background:#2563eb;color:#fff;text-decoration:none;border-radius:10px;padding:12px 24px;font-weight:700">📄 Detaylı Raporu Aç</a>
  </div>
</div>
<div style="background:#f8fafc;padding:12px 24px;font-size:11px;color:#94a3b8;text-align:center">'.htmlspecialchars($appName).' — Otomatik rapor · Yanıtlamayın</div>
</div></body></html>';

        $subject='=?UTF-8?B?'.base64_encode('📊 Günlük İş Raporu — '.$tarih.' ('.$appName.')').'?=';
        $headers="MIME-Version: 1.0\r\nContent-Type: text/html; charset=UTF-8\r\n"
            ."From: =?UTF-8?B?".base64_encode($appName)."?= <noreply@".(isset($_SERVER['HTTP_HOST'])?$_SERVER['HTTP_HOST']:'ots').">\r\n";
        foreach($admins as $adm){
            if(empty($adm['email'])) continue;
            try{ @mail($adm['email'],$subject,$html,$headers); }catch(Throwable $e){}
        }
    }
}
