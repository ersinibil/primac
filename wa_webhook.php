<?php
// UltraMsg gelen mesaj (inbound) webhook'u — public, session/login GEREKTİRMEZ (UltraMsg'in
// sunucusu çağırır, tarayıcı/mobil oturumu yok). Güvenlik: sabit bir anahtar yerine (bkz.
// KNOWN_BUGS.md "sabit migration/temizlik anahtarı") wa_settings.php'de üretilen, DB'de saklanan
// rastgele anahtar ?key= ile bekleniyor — bkz. boot.php $__mpub listesi (mobil-yönlendirme dışı).
require_once __DIR__.'/boot.php';
require_once __DIR__.'/share_lib.php';

header('Content-Type: application/json');

$expectedKey = get_setting('wa_webhook_key','');
if(!$expectedKey || ($_GET['key'] ?? '') !== $expectedKey){
    http_response_code(403);
    echo json_encode(['ok'=>false,'error'=>'invalid key']);
    exit;
}

try{
    $raw = file_get_contents('php://input');
    $event = json_decode($raw, true);
    $data = $event['data'] ?? null;

    if(is_array($data) && !empty($data['from']) && empty($data['fromMe'])){
        // Sadece gerçek GELEN (fromMe=false) mesajlar işlenir — kendi gönderdiğimiz mesajların
        // webhook üzerinden "yankısı" (fromMe=true) zaten wa_send_logged() ile gönderim anında
        // loglandığı için burada tekrar yazılmıyor (çift kayıt olmasın diye).
        $from = $data['from'];
        $body = (string)($data['body'] ?? '');
        $type = $data['type'] ?? 'chat';
        $providerMsgId = $data['id'] ?? null;
        wa_message_log($from, 'inbound', $body, 'webhook', null, ($type!=='chat'?$type:null), $providerMsgId, null);
    }
    echo json_encode(['ok'=>true]);
}catch(Throwable $e){
    // Provider'ın yeniden denemesini engellememek için hata durumunda da 200 dönülüyor —
    // sadece log/DB yazımı başarısız olur, HTTP seviyesinde retry-fırtınası yaratılmaz.
    echo json_encode(['ok'=>false]);
}
