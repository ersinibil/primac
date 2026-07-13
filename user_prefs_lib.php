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
// birbirinden sapma riski taşımaz). Sıra = varsayılan (kullanıcı hiç özelleştirmemişse) sıra.
function dashboard_section_keys() {
    return [
        'module_tiles', 'month_comparison', 'six_month_trend', 'critical_alerts',
        'operation_kpis', 'notes', 'recent_actions', 'live_notifications',
        'today_and_late_lists', 'recent_jobs',
    ];
}
