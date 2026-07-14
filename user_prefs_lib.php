<?php
/* Kullanıcı bazlı genel amaçlı tercih deposu (key/value).
 * WEB UI ALIGNMENT & NAVIGATION SPRINT 001 — Faz A ile eklendi. 'dashboard_tile_order' (Ana Modül
 * Kartları'nın iç sırası) ve 'dashboard_section_order' (Komuta Merkezi sayfasındaki ana bölümlerin
 * sırası) anahtarları kullanılıyor — ikisi birbirinden bağımsız. */

function user_pref_get($pdo, $userId, $key, $default = null) {
    try {
        $st = $pdo->prepare("SELECT pref_value FROM user_preferences WHERE user_id=? AND pref_key=?");
        $st->execute([$userId, $key]);
        $row = $st->fetch();
        return $row ? $row['pref_value'] : $default;
    } catch (Throwable $e) {
        return $default;
    }
}

function user_pref_set($pdo, $userId, $key, $value) {
    try {
        $st = $pdo->prepare("INSERT INTO user_preferences(user_id,pref_key,pref_value) VALUES(?,?,?)
            ON DUPLICATE KEY UPDATE pref_value=VALUES(pref_value)");
        $st->execute([$userId, $key, $value]);
        return true;
    } catch (Throwable $e) {
        return false;
    }
}

function user_pref_delete($pdo, $userId, $key) {
    try {
        $st = $pdo->prepare("DELETE FROM user_preferences WHERE user_id=? AND pref_key=?");
        $st->execute([$userId, $key]);
        return true;
    } catch (Throwable $e) {
        return false;
    }
}

// Komuta Merkezi'nin gerçek ana bölümleri — tek doğru kaynak (dashboard.php render sırası VE
// ajax_dashboard_order.php'nin whitelist kontrolü buradan okur, iki ayrı yerde tekrarlanıp
// birbirinden sapma riski taşımaz). Sıra = varsayılan (kullanıcı hiç özelleştirmemişse) sıra —
// kullanıcı sürükleyip kendi sırasını kaydettiyse bu sıraya dokunulmaz (bkz. dashboard.php
// $__savedSectionOrder / array_intersect mantığı).
// UX SPRINT 002 — PHASE B2 (2026-07-14): Dashboard Priority Layout — sıra, Product Design
// raporundaki 5 dikkat katmanına göre yeniden düzenlendi: 1) Nabız Satırı (critical_alerts)
// 2) Bugün (today_and_late_lists) 3) Hazır Eylemler (module_tiles) 4) Durum (operation_kpis,
// live_notifications, recent_actions, notes) 5) Analiz (month_comparison, six_month_trend,
// recent_jobs). Sadece sıra değişti — hiçbir anahtar eklenmedi/çıkarılmadı/yeniden adlandırılmadı.
function dashboard_section_keys() {
    return [
        'critical_alerts', 'today_and_late_lists', 'module_tiles',
        'operation_kpis', 'live_notifications', 'recent_actions', 'notes',
        'month_comparison', 'six_month_trend', 'recent_jobs',
    ];
}
