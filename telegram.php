<?php
// ACANS OS — Telegram kaldırıldı. Bu, eski çağrıları kırmamak için boş stub'tır.
// Bildirimler artık uygulama içi (internal_notifications) + Web Push ile yapılır.
if(!function_exists('telegram_send')){
    function telegram_send(){ return false; }
}
