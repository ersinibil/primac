<?php
/* Komuta Merkezi kişiselleştirme kaydetme — AJAX endpoint
 * WEB UI ALIGNMENT & NAVIGATION SPRINT 001 — Faz A.
 * İki BAĞIMSIZ sıralama seviyesi var, order_type ile ayrılır:
 *   - tiles:    Ana Modül Kartları'nın İÇ sırası    → dashboard_tile_order    (virgüllü liste)
 *   - sections: Komuta Merkezi sayfasının ana bölüm sırası → dashboard_section_order (JSON dizi)
 * POST: order_type=tiles|sections, order=... (reset=1 ise order gerekmez, tercih silinir)
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

    $orderType = $_POST['order_type'] ?? 'tiles';
    if (!in_array($orderType, ['tiles', 'sections'], true)) {
        throw new Exception('Geçersiz order_type.');
    }

    $userId = (int)(current_user()['id'] ?? 0);
    if ($userId <= 0) {
        throw new Exception('Oturum bulunamadı.');
    }

    $prefKey = $orderType === 'tiles' ? 'dashboard_tile_order' : 'dashboard_section_order';

    // Sıfırlama: sadece ilgili tercihi siler, diğer seviyeye dokunmaz.
    if (!empty($_POST['reset'])) {
        user_pref_delete(db(), $userId, $prefKey);
        echo json_encode(['ok' => true, 'message' => 'Varsayılana döndürüldü.']);
        exit;
    }

    if ($orderType === 'tiles') {
        $order = trim($_POST['order'] ?? '');
        if ($order === '') {
            throw new Exception('Sıra bilgisi boş.');
        }
        if (!preg_match('/^[a-zA-Z0-9_]+(,[a-zA-Z0-9_]+)*$/', $order)) {
            throw new Exception('Geçersiz sıra formatı.');
        }
        $keys = explode(',', $order);
        if (count($keys) !== count(array_unique($keys))) {
            throw new Exception('Tekrarlanan kart anahtarı.');
        }
        user_pref_set(db(), $userId, $prefKey, $order);
        echo json_encode(['ok' => true, 'message' => 'Kart sırası kaydedildi.']);
        exit;
    }

    // sections
    $raw = $_POST['order'] ?? '';
    $keys = json_decode($raw, true);
    if (!is_array($keys) || !$keys) {
        throw new Exception('Geçersiz sıra formatı.');
    }
    $allowedSections = dashboard_section_keys();
    foreach ($keys as $k) {
        if (!is_string($k) || !in_array($k, $allowedSections, true)) {
            throw new Exception('Bilinmeyen bölüm anahtarı.');
        }
    }
    if (count($keys) !== count(array_unique($keys))) {
        throw new Exception('Tekrarlanan bölüm anahtarı.');
    }
    user_pref_set(db(), $userId, $prefKey, json_encode(array_values($keys)));
    echo json_encode(['ok' => true, 'message' => 'Sayfa düzeni kaydedildi.']);
    exit;

} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => $e->getMessage()]);
}
