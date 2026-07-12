<?php
/* Komuta Merkezi kart sırası kaydetme — AJAX endpoint
 * WEB UI ALIGNMENT & NAVIGATION SPRINT 001 — Faz A (kullanıcı bazlı sürükle-bırak kart sırası).
 * POST: order=key1,key2,key3,...
 * Dönüş: JSON {ok:bool, message:string}
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__.'/boot.php';
require_login();
require_once __DIR__.'/user_prefs_lib.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Yalnızca POST desteklenir.');
    }

    $order = trim($_POST['order'] ?? '');
    if ($order === '') {
        throw new Exception('Sıra bilgisi boş.');
    }
    if (!preg_match('/^[a-zA-Z0-9_]+(,[a-zA-Z0-9_]+)*$/', $order)) {
        throw new Exception('Geçersiz sıra formatı.');
    }

    $userId = (int)(current_user()['id'] ?? 0);
    if ($userId <= 0) {
        throw new Exception('Oturum bulunamadı.');
    }

    user_pref_set(db(), $userId, 'dashboard_tile_order', $order);
    echo json_encode(['ok' => true, 'message' => 'Kart sırası kaydedildi.']);

} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => $e->getMessage()]);
}
