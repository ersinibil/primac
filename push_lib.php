<?php
// ACANS OS — Web Push (uygulama kapalıyken bildirim). PHP 7.2 + web-push v6.
require_once __DIR__.'/boot.php';

// VAPID anahtarları — config.php'den okunur (2026-07-03 güvenlik denetiminde bulundu: private key
// daha önce burada düz metin gömülüydü, repo artık GitHub'a bağlı olduğu için taşındı). Aşağıdaki
// sabit değerler SADECE geri uyumluluk için (config.php'de henüz vapid_public/vapid_private
// tanımlanmamış kurulumlarda mevcut abonelikler bozulmasın diye) — config.php'ye eklendikten sonra
// buradaki sabitler kaldırılabilir. YENİ bir kurulumda bu sabitlere GÜVENME, config.php'ye kendi
// anahtarlarını gir (VAPID anahtar çifti üretmek için: web-push kütüphanesinin `vendor/bin/generate-vapid-keys`
// aracı kullanılabilir).
function push_vapid(){
    $c = function_exists('app_config') ? app_config() : [];
    return [
        'subject' => $c['vapid_subject'] ?? 'mailto:admin@acanstr.com',
        'publicKey' => $c['vapid_public'] ?? 'BKEqJl3sOt2lxHVBXjtCu_nFTCgH42b7NVTjE4BsGq5xC81cdwF1llwIiAmXMbDieoC74QLHZOhZ1dSkgQjLP3c',
        'privateKey' => $c['vapid_private'] ?? 'lEr2og5nZs8UfiLd3EJeWAsT0NeSoj9aseWYJtxlusw',
    ];
}

function push_install(){
    try{
        db()->exec("CREATE TABLE IF NOT EXISTS push_subs(
            id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL,
            endpoint VARCHAR(500) NOT NULL, p256dh VARCHAR(255) NOT NULL, auth VARCHAR(255) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_ep(endpoint(191)), INDEX idx_u(user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }catch(Throwable $e){}
}

function push_available(){
    return file_exists(__DIR__.'/vendor/autoload.php')
        && extension_loaded('openssl') && extension_loaded('curl')
        && (extension_loaded('gmp') || extension_loaded('bcmath'));
}

/** Bir kullanıcının tüm cihazlarına push gönder. */
function push_to_user($userId,$title,$body,$url='mobile/index.php'){
    if(!$userId || !push_available()) return false;
    require_once __DIR__.'/vendor/autoload.php';
    push_install();
    try{
        $subs=db()->prepare("SELECT * FROM push_subs WHERE user_id=?");
        $subs->execute([$userId]);
        $rows=$subs->fetchAll();
        if(!$rows) return false;

        $v=push_vapid();
        $webPush=new \Minishlink\WebPush\WebPush(['VAPID'=>$v]);
        $payload=json_encode(['title'=>$title,'body'=>$body,'url'=>$url],JSON_UNESCAPED_UNICODE);

        foreach($rows as $s){
            $sub=\Minishlink\WebPush\Subscription::create([
                'endpoint'=>$s['endpoint'],
                'publicKey'=>$s['p256dh'],
                'authToken'=>$s['auth'],
                'contentEncoding'=>'aes128gcm', // iOS/modern tarayıcı standardı (eski 'aesgcm' iOS'ta çalışmaz)
            ]);
            if(method_exists($webPush,'queueNotification')) $webPush->queueNotification($sub,$payload);
            else $webPush->sendNotification($sub,$payload);
        }
        foreach($webPush->flush() as $report){
            if(!$report->isSuccess()){
                try{ db()->prepare("DELETE FROM push_subs WHERE endpoint=?")->execute([$report->getEndpoint()]); }catch(Throwable $e){}
            }
        }
        return true;
    }catch(Throwable $e){ return false; }
}
