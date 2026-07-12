<?php
/* Kullanıcı bazlı genel amaçlı tercih deposu (key/value).
 * WEB UI ALIGNMENT & NAVIGATION SPRINT 001 — Faz A ile eklendi, bu sprintte SADECE
 * 'dashboard_tile_order' anahtarı için kullanılıyor. */

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
