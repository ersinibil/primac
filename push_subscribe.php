<?php
// Tarayıcının push aboneliğini kaydeder/siler. mobile/common.php çağırır.
require_once __DIR__.'/boot.php';
require_once __DIR__.'/push_lib.php';
header('Content-Type: application/json; charset=utf-8');

if(empty($_SESSION['user'])){ echo json_encode(['ok'=>false,'auth'=>false]); exit; }
$uid=(int)$_SESSION['user']['id'];
push_install();

$raw=file_get_contents('php://input');
$d=json_decode($raw,true);

// Public anahtarı ver (GET) — istemci abone olurken kullanır
if(($_GET['key'] ?? '')==='1'){
    $v=push_vapid();
    echo json_encode(['key'=>$v['publicKey'],'available'=>push_available()]);
    exit;
}

if(!$d || empty($d['endpoint'])){ echo json_encode(['ok'=>false,'error'=>'eksik']); exit; }

if(($d['action'] ?? '')==='unsubscribe'){
    db()->prepare("DELETE FROM push_subs WHERE endpoint=?")->execute([$d['endpoint']]);
    echo json_encode(['ok'=>true]); exit;
}

try{
    $st=db()->prepare("INSERT INTO push_subs(user_id,endpoint,p256dh,auth) VALUES(?,?,?,?)
        ON DUPLICATE KEY UPDATE user_id=VALUES(user_id),p256dh=VALUES(p256dh),auth=VALUES(auth)");
    $st->execute([$uid,$d['endpoint'],$d['keys']['p256dh'] ?? '',$d['keys']['auth'] ?? '']);
    echo json_encode(['ok'=>true]);
}catch(Throwable $e){ echo json_encode(['ok'=>false,'error'=>$e->getMessage()]); }
