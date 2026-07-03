<?php
// notifications_lib.php — Bildirim okuma/silme mantığı (web + mobil ortak).
// Kişisel bildirim (target_user_id dolu): tek sahibi var, internal_notifications.is_read
// doğrudan kullanılır, sahiplik kontrollü fiziksel silme serbesttir.
// Genel bildirim (target_user_id NULL, birden fazla kullanıcı paylaşıyor): asla fiziksel
// silinmez — her kullanıcının kendi okunma/gizleme durumu user_notification_status'ta
// (migration 039) ayrı tutulur, bir kullanıcının "sil" işlemi başka kullanıcıyı etkilemez.
// SADECE admin bir genel bildirimi tümüyle (herkes için) silebilir — notif_admin_delete_global().

function notif_status_has_table($pdo){
    static $ok=null;
    if($ok!==null) return $ok;
    try{ $pdo->query("SELECT 1 FROM user_notification_status LIMIT 1"); $ok=true; }
    catch(Throwable $e){ $ok=false; }
    return $ok;
}

// Kullanıcıya görünen bildirim listesi (kişisel + gizlenmemiş genel), etkin okunma durumuyla.
function notif_list_for_user($pdo, $uid, $limit=100){
    $limit=(int)$limit;
    if(!notif_status_has_table($pdo)){
        // Tablo henüz yoksa (migration çalışmamış) eski davranışa düş: genel bildirimler herkese
        // aynı is_read ile görünür — kırılmaz, sadece kişi-bazlı ayrım devreye girmez.
        $st=$pdo->prepare("SELECT n.*, n.is_read AS effective_is_read FROM internal_notifications n
            WHERE n.target_user_id IS NULL OR n.target_user_id=?
            ORDER BY n.is_read ASC, n.id DESC LIMIT $limit");
        $st->execute([$uid]);
        return $st->fetchAll();
    }
    $st=$pdo->prepare("SELECT n.*,
            CASE WHEN n.target_user_id IS NOT NULL THEN n.is_read ELSE COALESCE(s.is_read,0) END AS effective_is_read
        FROM internal_notifications n
        LEFT JOIN user_notification_status s ON s.notification_id=n.id AND s.user_id=?
        WHERE n.target_user_id=? OR (n.target_user_id IS NULL AND COALESCE(s.is_hidden,0)=0)
        ORDER BY effective_is_read ASC, n.id DESC LIMIT $limit");
    $st->execute([$uid,$uid]);
    return $st->fetchAll();
}

// Tek sayaç — mobile/common.php::unread_notif(), layout_top.php $notifCount, mobile/poll.php
// notif_unread'in ortak kaynağı (2026-07-03 Sprint-001 öncesi 3 yerde ayrı ayrı kopyalanmıştı).
function notif_unread_count($pdo, $uid){
    try{
        if(!notif_status_has_table($pdo)){
            $st=$pdo->prepare("SELECT COUNT(*) c FROM internal_notifications WHERE is_read=0 AND (target_user_id IS NULL OR target_user_id=?)");
            $st->execute([$uid]);
            return (int)($st->fetch()['c'] ?? 0);
        }
        $st=$pdo->prepare("SELECT COUNT(*) c FROM internal_notifications n
            LEFT JOIN user_notification_status s ON s.notification_id=n.id AND s.user_id=?
            WHERE (n.target_user_id=? AND n.is_read=0)
               OR (n.target_user_id IS NULL AND COALESCE(s.is_read,0)=0 AND COALESCE(s.is_hidden,0)=0)");
        $st->execute([$uid,$uid]);
        return (int)($st->fetch()['c'] ?? 0);
    }catch(Throwable $e){ return 0; }
}

function notif_mark_read($pdo, $uid, $notifId){
    try{
        $chk=$pdo->prepare("SELECT target_user_id FROM internal_notifications WHERE id=?");
        $chk->execute([(int)$notifId]);
        $row=$chk->fetch();
        if(!$row) return;
        if($row['target_user_id']!==null){
            if((int)$row['target_user_id']!==(int)$uid) return; // sahiplik kontrolü
            $pdo->prepare("UPDATE internal_notifications SET is_read=1 WHERE id=?")->execute([(int)$notifId]);
        } elseif(notif_status_has_table($pdo)){
            $pdo->prepare("INSERT INTO user_notification_status(notification_id,user_id,is_read) VALUES(?,?,1)
                ON DUPLICATE KEY UPDATE is_read=1")->execute([(int)$notifId,$uid]);
        }
    }catch(Throwable $e){}
}

function notif_mark_all_read($pdo, $uid){
    try{
        $pdo->prepare("UPDATE internal_notifications SET is_read=1 WHERE target_user_id=? AND is_read=0")->execute([$uid]);
        if(!notif_status_has_table($pdo)) return;
        $ids=$pdo->prepare("SELECT n.id FROM internal_notifications n
            LEFT JOIN user_notification_status s ON s.notification_id=n.id AND s.user_id=?
            WHERE n.target_user_id IS NULL AND COALESCE(s.is_read,0)=0 AND COALESCE(s.is_hidden,0)=0");
        $ids->execute([$uid]);
        $upsert=$pdo->prepare("INSERT INTO user_notification_status(notification_id,user_id,is_read) VALUES(?,?,1) ON DUPLICATE KEY UPDATE is_read=1");
        foreach($ids->fetchAll(PDO::FETCH_COLUMN) as $nid){ $upsert->execute([$nid,$uid]); }
    }catch(Throwable $e){}
}

// Tekil "sil" — kişisel ise sahiplik kontrollü fiziksel DELETE, genel ise SADECE bu kullanıcı
// için gizle (satır kalır, başkası hâlâ görür).
function notif_dismiss($pdo, $uid, $notifId){
    try{
        $chk=$pdo->prepare("SELECT target_user_id FROM internal_notifications WHERE id=?");
        $chk->execute([(int)$notifId]);
        $row=$chk->fetch();
        if(!$row) return;
        if($row['target_user_id']!==null){
            if((int)$row['target_user_id']!==(int)$uid) return; // başkasının kişisel bildirimini silemez
            $pdo->prepare("DELETE FROM internal_notifications WHERE id=?")->execute([(int)$notifId]);
        } elseif(notif_status_has_table($pdo)){
            $pdo->prepare("INSERT INTO user_notification_status(notification_id,user_id,is_hidden) VALUES(?,?,1)
                ON DUPLICATE KEY UPDATE is_hidden=1")->execute([(int)$notifId,$uid]);
        }
    }catch(Throwable $e){}
}

// "Okunanları Sil" — kişisel okunanlar fiziksel silinir, genel okunanlar sadece bu kullanıcı için gizlenir.
function notif_dismiss_all_read($pdo, $uid){
    try{
        $pdo->prepare("DELETE FROM internal_notifications WHERE target_user_id=? AND is_read=1")->execute([$uid]);
        if(!notif_status_has_table($pdo)) return;
        $ids=$pdo->prepare("SELECT n.id FROM internal_notifications n
            JOIN user_notification_status s ON s.notification_id=n.id AND s.user_id=?
            WHERE n.target_user_id IS NULL AND s.is_read=1 AND COALESCE(s.is_hidden,0)=0");
        $ids->execute([$uid]);
        $upd=$pdo->prepare("UPDATE user_notification_status SET is_hidden=1 WHERE notification_id=? AND user_id=?");
        foreach($ids->fetchAll(PDO::FETCH_COLUMN) as $nid){ $upd->execute([$nid,$uid]); }
    }catch(Throwable $e){}
}

// "Tümünü Sil" — kişisel olanların hepsi fiziksel silinir, genel olanlar SADECE bu kullanıcı
// için gizlenir (BAŞKA KULLANICI ETKİLENMEZ — eski davranış tüm sistemi siliyordu, kapatıldı).
function notif_dismiss_all($pdo, $uid){
    try{
        $pdo->prepare("DELETE FROM internal_notifications WHERE target_user_id=?")->execute([$uid]);
        if(!notif_status_has_table($pdo)) return;
        $ids=$pdo->prepare("SELECT n.id FROM internal_notifications n
            LEFT JOIN user_notification_status s ON s.notification_id=n.id AND s.user_id=?
            WHERE n.target_user_id IS NULL AND COALESCE(s.is_hidden,0)=0");
        $ids->execute([$uid]);
        $upsert=$pdo->prepare("INSERT INTO user_notification_status(notification_id,user_id,is_hidden) VALUES(?,?,1) ON DUPLICATE KEY UPDATE is_hidden=1");
        foreach($ids->fetchAll(PDO::FETCH_COLUMN) as $nid){ $upsert->execute([$nid,$uid]); }
    }catch(Throwable $e){}
}

// Admin-only: genel bir bildirimi HERKES için kalıcı sil. UI'ya bağlanması Sprint-001 kapsamı
// dışında bırakıldı (bkz. plan) — fonksiyon hazır, ileride tek satır buton eklenebilir.
function notif_admin_delete_global($pdo, $notifId){
    try{
        $chk=$pdo->prepare("SELECT target_user_id FROM internal_notifications WHERE id=?");
        $chk->execute([(int)$notifId]);
        $row=$chk->fetch();
        if($row && $row['target_user_id']===null){
            if(notif_status_has_table($pdo)){
                $pdo->prepare("DELETE FROM user_notification_status WHERE notification_id=?")->execute([(int)$notifId]);
            }
            $pdo->prepare("DELETE FROM internal_notifications WHERE id=?")->execute([(int)$notifId]);
        }
    }catch(Throwable $e){}
}
