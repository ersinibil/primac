<?php
/* NAV-001B (2026-07-16) — Navigasyon kişiselleştirme kaydetme (pin/sıra/layout modu).
 * ajax_dashboard_order.php'nin desenini birebir izler, TEK farkla: CSRF ZORUNLU (Product Owner
 * kararı — "mevcut ajax_dashboard_order.php'de yok gerekçesiyle korumasız bırakılmayacaktır").
 * POST: action=pin|unpin|reset_pins|set_mode, platform=web|mobile (pin işlemlerinde), key=...,
 *       mode=compact|legacy (set_mode'da). Dönüş: JSON {ok:bool, message:string}
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__.'/boot.php';
require_login();
require_once __DIR__.'/user_prefs_lib.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Yalnızca POST desteklenir.');
    }
    csrf_verify();

    $userId = (int)(current_user()['id'] ?? 0);
    if ($userId <= 0) {
        throw new Exception('Oturum bulunamadı.');
    }
    $isAdmin = is_admin();
    $canSee = function($perm) use ($isAdmin){ return $isAdmin || user_can($perm); };

    $action = $_POST['action'] ?? '';
    $platform = $_POST['platform'] ?? 'web';
    if (!in_array($platform, ['web', 'mobile'], true)) {
        throw new Exception('Geçersiz platform.');
    }
    $prefKey = $platform === 'web' ? 'nav_pinned_web' : 'nav_pinned_mobile';

    if ($action === 'set_mode') {
        $mode = $_POST['mode'] ?? '';
        if (!in_array($mode, ['compact', 'legacy'], true)) {
            throw new Exception('Geçersiz mod.');
        }
        user_pref_set(db(), $userId, 'nav_layout_mode', $mode);
        echo json_encode(['ok' => true, 'message' => 'Navigasyon modu kaydedildi.']);
        exit;
    }

    // P0 MOBİL SHELL USER TEST REGRESYONU (2026-07-18, Product Owner kararı 5. madde) — Mobil Tema:
    // Sistem/Açık/Koyu. Saat bazlı otomatik tema YOK — "Sistem" prefers-color-scheme'i takip eder,
    // kullanıcı manuel seçerse (Açık/Koyu) kalıcı olur. ajax_nav_prefs.php'nin AYNI pref-kaydetme
    // deseni (user_pref_set) kullanıldı, ikinci bir kaydetme ucu İCAT EDİLMEDİ.
    if ($action === 'set_theme') {
        $theme = $_POST['theme'] ?? '';
        if (!in_array($theme, ['system', 'light', 'dark'], true)) {
            throw new Exception('Geçersiz tema.');
        }
        user_pref_set(db(), $userId, 'mobile_theme', $theme);
        echo json_encode(['ok' => true, 'message' => 'Tema kaydedildi.']);
        exit;
    }

    if ($action === 'reset_pins') {
        user_pref_delete(db(), $userId, $prefKey);
        echo json_encode(['ok' => true, 'message' => 'Sabitlemeler varsayılana döndürüldü.']);
        exit;
    }

    if ($action === 'pin' || $action === 'unpin') {
        $key = trim($_POST['key'] ?? '');
        if ($key === '' || !preg_match('/^[a-z0-9_]+$/', $key)) {
            throw new Exception('Geçersiz modül anahtarı.');
        }
        // Anahtar nav_taxonomy() üyesi VE kullanıcı yetkili mi — yetkisiz/bilinmeyen modül pinlenemez.
        $item = nav_module_by_key($key);
        if (!$item) {
            throw new Exception('Bilinmeyen modül anahtarı.');
        }
        if (!empty($item['adminOnly']) && !$isAdmin) {
            throw new Exception('Bu modül için yetkiniz yok.');
        }
        if ($item['perm'] !== null && !$canSee($item['perm'])) {
            throw new Exception('Bu modül için yetkiniz yok.');
        }
        // Elif'in NAV-001 v3 parite incelemesinde bulduğu boşluk: platforma özel, DAİMA görünür
        // (mobilde bottom-nav'ın sabit "Cari" ikonu gibi) hedefler ayrıca sabitlenemez — aksi halde
        // Command Launcher'ın "Sabitlenenler" bölümünde aynı hedef ikinci kez görünür.
        if ($action === 'pin' && in_array($key, nav_platform_fixed_keys($platform), true)) {
            throw new Exception('Bu modül zaten sabit menüde, ayrıca sabitlenemez.');
        }
        // 2026-07-17 FAIL düzeltmesi — mobilde hiç karşılığı olmayan (mobileHide) bir modül
        // mobil tarafta sabitlenirse Sabitlenenler'de 404 satırı olarak kalır.
        if ($action === 'pin' && $platform === 'mobile' && !empty($item['mobileHide'])) {
            throw new Exception('Bu modülün mobil karşılığı yok, mobilde sabitlenemez.');
        }

        $current = user_pref_get(db(), $userId, $prefKey, '');
        $keys = array_filter(array_map('trim', explode(',', $current)));

        if ($action === 'pin') {
            if (!in_array($key, $keys, true)) $keys[] = $key;
        } else {
            $keys = array_filter($keys, function($k) use ($key){ return $k !== $key; });
        }

        $keys = array_values(array_unique($keys));
        user_pref_set(db(), $userId, $prefKey, implode(',', $keys));
        echo json_encode(['ok' => true, 'message' => $action === 'pin' ? 'Sabitlendi.' : 'Sabitleme kaldırıldı.']);
        exit;
    }

    throw new Exception('Geçersiz işlem.');

} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => $e->getMessage()]);
}
