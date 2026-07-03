<?php
// Mobil canlı bildirim yoklama endpoint'i (JSON, hafif).
// common.php'deki notifier her birkaç saniyede sorar; yeni mesaj/bildirim varsa ses + ekran bildirimi.
require_once __DIR__.'/../boot.php';
if(is_file(__DIR__.'/../notifications_lib.php')) require_once __DIR__.'/../notifications_lib.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if(empty($_SESSION['user'])){ echo json_encode(['auth'=>false]); exit; }
$me=(int)($_SESSION['user']['id'] ?? 0);
$pdo=db();

$sinceMsg=(int)($_GET['since_msg'] ?? 0);
$sinceNotif=(int)($_GET['since_notif'] ?? 0);
$conv=(int)($_GET['conv'] ?? 0); // açık sohbetteki kişi (varsa o sohbetin yeni mesajları)

$out=['auth'=>true,'msg_unread'=>0,'notif_unread'=>0,'new'=>[],'conv_new'=>[],'last_msg_id'=>0,'last_notif_id'=>0];

// Çevrimiçi: her poll'da son görülme güncelle (kolon yoksa sessiz geç)
try{ $pdo->prepare("UPDATE app_users SET last_seen=NOW() WHERE id=?")->execute([$me]); }catch(Throwable $e){}

// "yazıyor..." sinyali: composer yazınca poll'a typing=1&to=X gelir
if(($_GET['typing'] ?? '')==='1' && (int)($_GET['to'] ?? 0)){
    try{ $pdo->prepare("INSERT INTO chat_typing(from_id,to_id,at) VALUES(?,?,NOW()) ON DUPLICATE KEY UPDATE at=NOW()")->execute([$me,(int)$_GET['to']]); }catch(Throwable $e){}
}

try{
    // Sayaçlar
    $s=$pdo->prepare("SELECT COUNT(*) c FROM internal_messages WHERE receiver_user_id=? AND is_read=0 AND sender_user_id IS NOT NULL");
    $s->execute([$me]); $out['msg_unread']=(int)$s->fetch()['c'];
    try{ $out['notif_unread']=function_exists('notif_unread_count')?notif_unread_count($pdo,$me):0; }catch(Throwable $e){}

    // En son id'ler (istemci takip eder) — bildirimde de kullanıcı filtresi
    $out['last_msg_id']=(int)($pdo->query("SELECT COALESCE(MAX(id),0) m FROM internal_messages WHERE receiver_user_id=$me")->fetch()['m'] ?? 0);
    try{ $out['last_notif_id']=(int)($pdo->query("SELECT COALESCE(MAX(id),0) m FROM internal_notifications WHERE target_user_id IS NULL OR target_user_id=$me")->fetch()['m'] ?? 0); }catch(Throwable $e){}

    // Yeni gelen mesajlar (bana, okunmamış, since'den sonra) — ses/bildirim için
    if($sinceMsg>0){
        $q=$pdo->prepare("SELECT m.id, m.message, m.sender_user_id, u.full_name, u.username
            FROM internal_messages m LEFT JOIN app_users u ON u.id=m.sender_user_id
            WHERE m.id>? AND m.receiver_user_id=? AND m.is_read=0 AND m.sender_user_id IS NOT NULL ORDER BY m.id ASC LIMIT 15");
        $q->execute([$sinceMsg,$me]);
        foreach($q->fetchAll() as $r){
            $out['new'][]=['type'=>'msg','id'=>(int)$r['id'],'from'=>($r['full_name'] ?: $r['username'] ?: 'Mesaj'),
                'body'=>mb_substr($r['message'],0,120),'with'=>(int)$r['sender_user_id']];
        }
    }

    // Açık sohbetteki yeni mesajlar (canlı akış için) — hem benden hem karşıdan
    if($conv>0 && $sinceMsg>=0){
        $cq=$pdo->prepare("SELECT id, message, sender_user_id, created_at FROM internal_messages
            WHERE id>? AND ((sender_user_id=? AND receiver_user_id=?) OR (sender_user_id=? AND receiver_user_id=?))
            ORDER BY id ASC LIMIT 50");
        $cq->execute([(int)($_GET['conv_since'] ?? 0),$me,$conv,$conv,$me]);
        foreach($cq->fetchAll() as $r){
            $out['conv_new'][]=['id'=>(int)$r['id'],'mine'=>((int)$r['sender_user_id']===$me),
                'body'=>$r['message'],'at'=>date('d.m H:i',strtotime($r['created_at']))];
        }
        // Bu sohbet açıkken karşıdan geleni okundu yap
        $pdo->prepare("UPDATE internal_messages SET is_read=1 WHERE receiver_user_id=? AND sender_user_id=?")->execute([$me,$conv]);

        // Karşı tarafın durumu: yazıyor mu + çevrimiçi/son görülme
        try{
            $tp=$pdo->prepare("SELECT 1 FROM chat_typing WHERE from_id=? AND to_id=? AND at > (NOW() - INTERVAL 6 SECOND)");
            $tp->execute([$conv,$me]); $out['conv_typing']=(bool)$tp->fetch();
        }catch(Throwable $e){}
        try{
            $ls=$pdo->prepare("SELECT last_seen, (last_seen > (NOW() - INTERVAL 35 SECOND)) onl FROM app_users WHERE id=?");
            $ls->execute([$conv]); $r=$ls->fetch();
            if($r){ $out['conv_online']=(bool)$r['onl']; $out['conv_last_seen']=$r['last_seen']?date('d.m H:i',strtotime($r['last_seen'])):null; }
        }catch(Throwable $e){}
    }

    // Açık GRUP/İŞ/CARİ sohbeti (thread) canlı akış
    $convThread=(int)($_GET['conv_thread'] ?? 0);
    if($convThread>0){
        try{
            // üye mi
            $ok2=$pdo->prepare("SELECT 1 FROM chat_thread_members WHERE thread_id=? AND user_id=?"); $ok2->execute([$convThread,$me]);
            if($ok2->fetch()){
                $cq=$pdo->prepare("SELECT m.id,m.message,m.sender_user_id,m.created_at,u.full_name,u.username
                    FROM internal_messages m LEFT JOIN app_users u ON u.id=m.sender_user_id
                    WHERE m.id>? AND m.thread_id=? ORDER BY m.id ASC LIMIT 50");
                $cq->execute([(int)($_GET['conv_since'] ?? 0),$convThread]);
                foreach($cq->fetchAll() as $r){
                    $out['conv_new'][]=['id'=>(int)$r['id'],'mine'=>((int)$r['sender_user_id']===$me),
                        'from'=>($r['full_name']?:$r['username']?:''),'body'=>$r['message'],'at'=>date('d.m H:i',strtotime($r['created_at']))];
                }
                $mx=(int)($pdo->query("SELECT COALESCE(MAX(id),0) m FROM internal_messages WHERE thread_id=$convThread")->fetch()['m']??0);
                $pdo->prepare("UPDATE chat_thread_members SET last_read_id=? WHERE thread_id=? AND user_id=?")->execute([$mx,$convThread,$me]);
            }
        }catch(Throwable $e){}
    }
}catch(Throwable $e){ $out['error']=$e->getMessage(); }

echo json_encode($out,JSON_UNESCAPED_UNICODE);
