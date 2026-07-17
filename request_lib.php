<?php
/* OTS Talep (management_requests) — paylaşılan hedefleme fonksiyonu (web + mobil).
 * request_new.php / mobile/request_new.php ortak kullanır. */

/**
 * P0-REQ-01 (2026-07-17): talep bildirimi kimin app_users hesabına gidecek, DETERMİNİSTİK ve
 * veriye dayalı çözülür — son açık sohbet/session/sabit kullanıcı/eski recipient_id ASLA
 * kullanılmaz. Tek kaynak: talebin bağlı olduğu işin (related_job_id) sorumlu personeli
 * (jobs.responsible_personnel_id) → o personelin bağlı VE hâlâ ona ait VE aktif app_users hesabı
 * (personnel_lib.php::personnel_reset_password() ile aynı sahiplik doğrulama deseni — P0-AUTH-01).
 * İlgili iş yoksa, sorumlu personeli yoksa veya bağlı geçerli bir hesabı yoksa NULL döner — bu
 * durumda hiç kimseye mesaj gönderilmez (departman/grup ataması henüz yok, gelecekte eklenebilir).
 * @return int|null hedef app_users.id
 */
function request_resolve_recipient($pdo, $relatedJobId){
    $relatedJobId=(int)($relatedJobId ?? 0);
    if(!$relatedJobId) return null;
    $j=$pdo->prepare("SELECT responsible_personnel_id FROM jobs WHERE id=?");
    $j->execute([$relatedJobId]);
    $pid=(int)($j->fetch()['responsible_personnel_id'] ?? 0);
    if(!$pid) return null;
    $p=$pdo->prepare("SELECT user_id FROM personnel WHERE id=?");
    $p->execute([$pid]);
    $uid=(int)($p->fetch()['user_id'] ?? 0);
    if(!$uid) return null;
    $u=$pdo->prepare("SELECT id FROM app_users WHERE id=? AND personnel_id=? AND active=1");
    $u->execute([$uid,$pid]);
    $row=$u->fetch();
    return $row ? (int)$row['id'] : null;
}
