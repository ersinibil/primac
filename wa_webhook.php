<?php
// UltraMsg gelen mesaj (inbound) webhook'u — public, session/login GEREKTİRMEZ (UltraMsg'in
// sunucusu çağırır, tarayıcı/mobil oturumu yok). Güvenlik: sabit bir anahtar yerine (bkz.
// KNOWN_BUGS.md "sabit migration/temizlik anahtarı") wa_settings.php'de üretilen, DB'de saklanan
// rastgele anahtar ?key= ile bekleniyor — bkz. boot.php $__mpub listesi (mobil-yönlendirme dışı).
require_once __DIR__.'/boot.php';
require_once __DIR__.'/share_lib.php';

header('Content-Type: application/json');

// --- GEÇİCİ TEŞHİS: karar noktaları wa_debug.log'a yazılıyor, DEBUG_WA=false'ta no-op, davranış
// AŞAĞIDAKİ ORİJİNAL KOŞULLAR birebir korunuyor, sadece etraflarına log eklendi (REOPEN-003). ---
wa_debug_log('WEBHOOK START', [
    'ip'=>$_SERVER['REMOTE_ADDR'] ?? null,
    'headers'=>function_exists('getallheaders') ? getallheaders() : [],
]);

$expectedKey = get_setting('wa_webhook_key','');
if(!$expectedKey || ($_GET['key'] ?? '') !== $expectedKey){
    // Gerçek/denenen key değeri LOGLANMIYOR (kullanıcı talebi) — sadece uzunluk bilgisi.
    wa_debug_log('REJECTED', 'SEBEP: invalid key (denenen uzunluk='.strlen((string)($_GET['key'] ?? '')).')');
    http_response_code(403);
    echo json_encode(['ok'=>false,'error'=>'invalid key']);
    exit;
}

try{
    $raw = file_get_contents('php://input');
    wa_debug_log('RAW BODY', $raw);
    $event = json_decode($raw, true);
    wa_debug_log(json_last_error()===JSON_ERROR_NONE ? 'JSON OK' : 'JSON FAIL', json_last_error()===JSON_ERROR_NONE ? $event : json_last_error_msg());
    $data = $event['data'] ?? null;

    if(is_array($data) && !empty($data['from']) && empty($data['fromMe'])){
        // Sadece gerçek GELEN (fromMe=false) mesajlar işlenir — kendi gönderdiğimiz mesajların
        // webhook üzerinden "yankısı" (fromMe=true) zaten wa_send_logged() ile gönderim anında
        // loglandığı için burada tekrar yazılmıyor (çift kayıt olmasın diye).
        wa_debug_log('FROM OK', $data['from']);
        wa_debug_log('FROMME FALSE', ['raw'=>$data['fromMe'] ?? null,'php_type'=>gettype($data['fromMe'] ?? null)]);
        $from = $data['from'];
        $body = (string)($data['body'] ?? '');
        $type = $data['type'] ?? 'chat';
        $providerMsgId = $data['id'] ?? null;
        wa_debug_log('MESSAGE ACCEPTED', ['from'=>$from,'body'=>$body,'type'=>$type,'provider_message_id'=>$providerMsgId,'media'=>$data['media'] ?? ($data['mediaUrl'] ?? null)]);
        $logResult = wa_message_log($from, 'inbound', $body, 'webhook', null, ($type!=='chat'?$type:null), $providerMsgId, null);
        wa_debug_log($logResult ? 'MESSAGE INSERTED (webhook)' : 'MESSAGE LOG FAILED (webhook)', ['result'=>$logResult]);
    } else {
        $sebep = !is_array($data) ? 'data array değil (JSON parse başarısız olabilir)'
            : (empty($data['from']) ? 'from alanı eksik/boş'
            : 'fromMe truthy — raw='.json_encode($data['fromMe'] ?? null).' type='.gettype($data['fromMe'] ?? null));
        wa_debug_log('MESSAGE REJECTED', 'SEBEP: '.$sebep);
    }
    wa_debug_log('RETURN 200', null);
    echo json_encode(['ok'=>true]);
}catch(Throwable $e){
    // Provider'ın yeniden denemesini engellememek için hata durumunda da 200 dönülüyor —
    // sadece log/DB yazımı başarısız olur, HTTP seviyesinde retry-fırtınası yaratılmaz.
    wa_debug_log('EXCEPTION', $e->getMessage());
    echo json_encode(['ok'=>false]);
}
