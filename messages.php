<?php
/* ===========================================================================
 * messages.php — Web (masaüstü) iç mesajlaşma ekranı
 * Mobil mobile/messages.php ile aynı internal_messages tablosunu kullanır.
 * 1-1 yazışma (gelen+giden), kişi listesi, yeni mesaj başlatma.
 * POST işlemi layout_top'tan ÖNCE yapılır (PRG deseni → redirect).
 * ========================================================================= */
require_once __DIR__.'/boot.php';
require_once __DIR__.'/share_lib.php';
require_login();
$me  = (int)(current_user()['id'] ?? 0);
$pdo = db();

/* --- Tablo + kolon güvencesi (mobil sürümle aynı şema garantisi) --- */
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS internal_messages(
        id INT AUTO_INCREMENT PRIMARY KEY,
        sender_user_id INT NULL,
        receiver_user_id INT NULL,
        thread_id INT NULL,
        message TEXT,
        attachment VARCHAR(255) NULL,
        attach_type VARCHAR(20) NULL,
        is_read TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch(Throwable $e){}
foreach ([
    'receiver_user_id'=>'INT NULL','thread_id'=>'INT NULL',
    'attachment'=>'VARCHAR(255) NULL','attach_type'=>'VARCHAR(20) NULL',
    'is_read'=>'TINYINT(1) DEFAULT 0'
] as $col=>$def){
    try {
        if(!$pdo->query("SHOW COLUMNS FROM internal_messages LIKE '".$col."'")->fetch())
            $pdo->exec("ALTER TABLE internal_messages ADD COLUMN ".$col." ".$def);
    } catch(Throwable $e){}
}
/* --- Grup tabloları güvencesi (mobil sürümle aynı şema) --- */
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS chat_threads(
        id INT AUTO_INCREMENT PRIMARY KEY,
        type VARCHAR(20) DEFAULT 'group',
        title VARCHAR(190) NULL,
        created_by INT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch(Throwable $e){}
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS chat_thread_members(
        thread_id INT NOT NULL,
        user_id INT NOT NULL,
        last_read_id INT DEFAULT 0,
        PRIMARY KEY(thread_id,user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch(Throwable $e){}
try {
    if(!$pdo->query("SHOW COLUMNS FROM chat_threads LIKE 'type'")->fetch())
        $pdo->exec("ALTER TABLE chat_threads ADD COLUMN type VARCHAR(20) DEFAULT 'group'");
} catch(Throwable $e){}
try {
    if(!$pdo->query("SHOW COLUMNS FROM chat_thread_members LIKE 'last_read_id'")->fetch())
        $pdo->exec("ALTER TABLE chat_thread_members ADD COLUMN last_read_id INT DEFAULT 0");
} catch(Throwable $e){}

$with   = (int)($_GET['u'] ?? 0);        // konuşulan kişi (1-1)
$thread = (int)($_GET['thread'] ?? 0);   // grup sohbeti
$flash  = '';

/* --- POST: mesaj sil (sadece kendi gönderdiği) --- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['del_msg'])) {
    $dmid = (int)$_POST['del_msg'];
    $isAjax = !empty($_POST['ajax']);
    $resp = ['ok'=>false,'error'=>''];
    if ($dmid > 0) {
        // Eki bul ve sil
        try {
            $st2 = $pdo->prepare("SELECT attachment FROM internal_messages WHERE id=? AND sender_user_id=?");
            $st2->execute([$dmid, $me]);
            $attRow = $st2->fetch();
            if ($attRow && !empty($attRow['attachment'])) {
                $fpath = __DIR__.'/'.$attRow['attachment'];
                if (is_file($fpath)) @unlink($fpath);
            }
            $st = $pdo->prepare("DELETE FROM internal_messages WHERE id=? AND sender_user_id=?");
            $st->execute([$dmid, $me]);
            $resp['ok'] = ($st->rowCount() > 0);
            if (!$resp['ok']) $resp['error'] = 'Silinemedi (sahip değilsiniz).';
        } catch(Throwable $e){ $resp['error'] = 'Hata: '.$e->getMessage(); }
    } else { $resp['error'] = 'Geçersiz istek.'; }
    if ($isAjax) { header('Content-Type: application/json'); echo json_encode($resp); exit; }
    $redir = (int)($_POST['with']??0) > 0 ? 'messages.php?u='.(int)$_POST['with'] : 'messages.php?thread='.(int)($_POST['thread']??0);
    redirect($redir);
}

/* --- POST: mesaj düzenle (sadece kendi metin mesajları) --- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_msg'])) {
    $eid = (int)$_POST['edit_msg'];
    $newText = trim((string)($_POST['edit_text'] ?? ''));
    $isAjax = !empty($_POST['ajax']);
    $resp = ['ok'=>false,'error'=>''];
    if ($eid > 0 && $newText !== '') {
        try {
            $st = $pdo->prepare("UPDATE internal_messages SET message=? WHERE id=? AND sender_user_id=? AND (attachment IS NULL OR attachment='')");
            $st->execute([$newText, $eid, $me]);
            $resp['ok'] = ($st->rowCount() > 0);
            if (!$resp['ok']) $resp['error'] = 'Düzenlenemedi (sahip değilsiniz ya da ekli mesaj).';
        } catch(Throwable $e){ $resp['error'] = 'Hata: '.$e->getMessage(); }
    } else { $resp['error'] = 'Geçersiz istek.'; }
    if ($isAjax) { header('Content-Type: application/json'); echo json_encode($resp); exit; }
    $redir = (int)($_POST['with']??0) > 0 ? 'messages.php?u='.(int)$_POST['with'] : 'messages.php?thread='.(int)($_POST['thread']??0);
    redirect($redir);
}

/* --- POST: grup oluştur (çıktıdan ÖNCE → PRG redirect) --- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new_group'])) {
    $tid = 0;
    try {
        $title   = trim((string)($_POST['title'] ?? ''));
        $members = array_map('intval', (array)($_POST['members'] ?? []));
        $members = array_values(array_unique(array_filter($members)));
        if ($title !== '' && count($members) >= 1) {
            $pdo->prepare("INSERT INTO chat_threads(type,title,created_by) VALUES('group',?,?)")
                ->execute([$title, $me]);
            $tid = (int)$pdo->lastInsertId();
            $ins = $pdo->prepare("INSERT IGNORE INTO chat_thread_members(thread_id,user_id) VALUES(?,?)");
            $ins->execute([$tid, $me]); // oluşturan dahil
            foreach ($members as $uid) { if ($uid !== $me) $ins->execute([$tid, $uid]); }
            // Üyelere push (varsa)
            if (file_exists(__DIR__.'/push_lib.php')) {
                require_once __DIR__.'/push_lib.php';
                $sname = current_user()['name'] ?? current_user()['username'] ?? 'Kullanıcı';
                foreach ($members as $uid) {
                    if ($uid !== $me) {
                        try { push_to_user($uid, '👥 '.$title, $sname.' seni gruba ekledi', 'messages.php?thread='.$tid); } catch(Throwable $e){}
                    }
                }
            }
        }
    } catch(Throwable $e){ $tid = 0; }
    redirect($tid > 0 ? 'messages.php?thread='.$tid : 'messages.php');
}

/* --- POST: grup mesajı gönder (çıktıdan ÖNCE → PRG redirect) --- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (int)($_POST['thread_id'] ?? 0) > 0) {
    $tid  = (int)$_POST['thread_id'];
    $body = trim((string)($_POST['message'] ?? ''));
    $att = null; $attType = null;
    if (isset($_FILES['attach']) && $_FILES['attach']['error'] === 0) {
        $af = $_FILES['attach'];
        $ext = strtolower(pathinfo($af['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg','jpeg','png','gif','webp','heic','pdf','mp4','mov','m4a','mp3','wav','webm','ogg','oga','aac','opus','doc','docx','xls','xlsx'];
        if (in_array($ext, $allowed) && $af['size'] <= 25*1024*1024) {
            $dir = __DIR__.'/uploads/job_files';
            if (!is_dir($dir)) @mkdir($dir, 0755, true);
            if (is_writable($dir)) {
                $stored = bin2hex(random_bytes(8)).'.'.$ext;
                $dest = $dir.'/'.$stored;
                if (@move_uploaded_file($af['tmp_name'], $dest)) {
                    @chmod($dest, 0644);
                    $att = 'uploads/job_files/'.$stored;
                    if (in_array($ext, ['jpg','jpeg','png','gif','webp','heic'])) $attType = 'image';
                    elseif (in_array($ext, ['m4a','mp3','wav','ogg','oga','aac','webm','opus'])) $attType = 'audio';
                    elseif (in_array($ext, ['mp4','mov','m4v'])) $attType = 'video';
                    else $attType = 'file';
                }
            }
        }
    }
    if ($body !== '' || $att) {
        try {
            // Üyelik kontrolü
            $chk = $pdo->prepare("SELECT 1 FROM chat_thread_members WHERE thread_id=? AND user_id=?");
            $chk->execute([$tid, $me]);
            if ($chk->fetch()) {
                $pdo->prepare("INSERT INTO internal_messages
                    (sender_user_id, receiver_user_id, thread_id, message, attachment, attach_type, is_read)
                    VALUES(?,NULL,?,?,?,?,0)")->execute([$me, $tid, $body, $att, $attType]);
                // Diğer üyelere push (varsa)
                if (file_exists(__DIR__.'/push_lib.php')) {
                    require_once __DIR__.'/push_lib.php';
                    $sname = current_user()['name'] ?? current_user()['username'] ?? 'Kullanıcı';
                    $tt = $pdo->prepare("SELECT title FROM chat_threads WHERE id=?");
                    $tt->execute([$tid]); $trow = $tt->fetch();
                    $tname = $trow['title'] ?? 'Grup';
                    $preview = $body !== '' ? mb_substr($body,0,90) : ($attType==='image' ? '📷 Fotoğraf' : ($attType==='audio' ? '🎤 Ses' : ($attType==='video' ? '🎬 Video' : '📎 Dosya')));
                    $mem = $pdo->prepare("SELECT user_id FROM chat_thread_members WHERE thread_id=? AND user_id<>?");
                    $mem->execute([$tid, $me]);
                    foreach ($mem->fetchAll() as $mm) {
                        try { push_to_user((int)$mm['user_id'], '👥 '.$tname, $sname.': '.$preview, 'messages.php?thread='.$tid); } catch(Throwable $e){}
                    }
                }
            }
        } catch(Throwable $e){ /* tablo/kolon yoksa sessiz geç */ }
    }
    redirect('messages.php?thread='.$tid);
}

/* --- POST: mesaj gönder (1-1, çıktıdan ÖNCE → PRG redirect) --- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $to   = (int)($_POST['receiver_user_id'] ?? 0);
    $body = trim((string)($_POST['message'] ?? ''));
    $att = null; $attType = null;
    // Dosya eki
    if (isset($_FILES['attach']) && $_FILES['attach']['error'] === 0) {
        $af = $_FILES['attach'];
        $ext = strtolower(pathinfo($af['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg','jpeg','png','gif','webp','heic','pdf','mp4','mov','m4a','mp3','wav','webm','ogg','oga','aac','opus','doc','docx','xls','xlsx'];
        if (in_array($ext, $allowed) && $af['size'] <= 25*1024*1024) {
            $dir = __DIR__.'/uploads/job_files';
            if (!is_dir($dir)) @mkdir($dir, 0755, true);
            if (is_writable($dir)) {
                $stored = bin2hex(random_bytes(8)).'.'.$ext;
                $dest = $dir.'/'.$stored;
                if (@move_uploaded_file($af['tmp_name'], $dest)) {
                    @chmod($dest, 0644);
                    $att = 'uploads/job_files/'.$stored;
                    if (in_array($ext, ['jpg','jpeg','png','gif','webp','heic'])) $attType = 'image';
                    elseif (in_array($ext, ['m4a','mp3','wav','ogg','oga','aac','webm','opus'])) $attType = 'audio';
                    elseif (in_array($ext, ['mp4','mov','m4v'])) $attType = 'video';
                    else $attType = 'file';
                }
            }
        }
    }
    if ($to > 0 && ($body !== '' || $att)) {
        try {
            $pdo->prepare("INSERT INTO internal_messages
                (sender_user_id, receiver_user_id, message, attachment, attach_type, is_read)
                VALUES(?,?,?,?,?,0)")->execute([$me, $to, $body, $att, $attType]);
            // Kapalıyken push bildirimi (varsa) — zorunlu değil
            if (file_exists(__DIR__.'/push_lib.php')) {
                require_once __DIR__.'/push_lib.php';
                $sname = current_user()['name'] ?? current_user()['username'] ?? 'Kullanıcı';
                $preview = $body !== '' ? mb_substr($body,0,90) : ($attType === 'image' ? '📷 Fotoğraf' : ($attType === 'audio' ? '🎤 Ses' : ($attType === 'video' ? '🎬 Video' : '📎 Dosya')));
                try { push_to_user($to, '💬 '.$sname, $preview, 'messages.php?u='.$me); } catch(Throwable $e){}
            }
        } catch(Throwable $e){ /* tablo/kolon yoksa sessiz geç */ }
    }
    // PRG: aynı sohbete geri dön
    redirect('messages.php?u='.$to);
}

/* --- Sohbet açıldıysa karşı taraftan gelen mesajları okundu yap --- */
if ($with > 0) {
    try {
        $pdo->prepare("UPDATE internal_messages SET is_read=1
            WHERE receiver_user_id=? AND sender_user_id=?")->execute([$me, $with]);
    } catch(Throwable $e){}
}

/* --- Üyesi olduğum gruplar (sol listede gösterilir) --- */
$threads = [];
try {
    $thr = $pdo->prepare("
        SELECT t.id, t.title, t.type,
          (SELECT m.message FROM internal_messages m
             WHERE m.thread_id=t.id ORDER BY m.id DESC LIMIT 1) AS last_msg,
          (SELECT MAX(id) FROM internal_messages m WHERE m.thread_id=t.id) AS last_id,
          cm.last_read_id
        FROM chat_threads t
        JOIN chat_thread_members cm ON cm.thread_id=t.id AND cm.user_id=?
        ORDER BY COALESCE((SELECT MAX(id) FROM internal_messages m WHERE m.thread_id=t.id),0) DESC, t.id DESC");
    $thr->execute([$me]);
    $threads = $thr->fetchAll();
} catch(Throwable $e){ $threads = []; }

/* --- Seçili grup + mesajları + üye sayısı --- */
$tgroup = null; $tmsgs = []; $tmembers = 0;
if ($thread > 0) {
    try {
        $tg = $pdo->prepare("SELECT t.id, t.title, t.type
            FROM chat_threads t
            JOIN chat_thread_members cm ON cm.thread_id=t.id AND cm.user_id=?
            WHERE t.id=?");
        $tg->execute([$me, $thread]); $tgroup = $tg->fetch();
    } catch(Throwable $e){ $tgroup = null; }
    if ($tgroup) {
        try {
            $cm = $pdo->prepare("SELECT COUNT(*) c FROM chat_thread_members WHERE thread_id=?");
            $cm->execute([$thread]); $tmembers = (int)($cm->fetch()['c'] ?? 0);
        } catch(Throwable $e){ $tmembers = 0; }
        try {
            $tm = $pdo->prepare("SELECT m.*, u.full_name, u.username
                FROM internal_messages m
                LEFT JOIN app_users u ON u.id=m.sender_user_id
                WHERE m.thread_id=? ORDER BY m.id ASC LIMIT 500");
            $tm->execute([$thread]); $tmsgs = $tm->fetchAll();
        } catch(Throwable $e){ $tmsgs = []; }
        // okundu işaretle (last_read_id)
        try {
            $mx = 0; foreach ($tmsgs as $tmm) { $mx = max($mx, (int)$tmm['id']); }
            $pdo->prepare("UPDATE chat_thread_members SET last_read_id=? WHERE thread_id=? AND user_id=?")
                ->execute([$mx, $thread, $me]);
        } catch(Throwable $e){}
    }
}

/* --- "Yeni Grup" formu için seçilebilir kullanıcılar --- */
$allUsers = [];
try {
    $au = $pdo->prepare("SELECT id, full_name, username FROM app_users
        WHERE id<>? AND active=1 ORDER BY full_name, username");
    $au->execute([$me]); $allUsers = $au->fetchAll();
} catch(Throwable $e){ $allUsers = []; }

/* --- Sol liste: konuşulan kişiler + (yeni mesaj için) tüm kullanıcılar --- */
$rows = [];
try {
    $st = $pdo->prepare("
        SELECT u.id, u.full_name, u.username, u.role,
          (SELECT m.message FROM internal_messages m
             WHERE (m.sender_user_id=u.id AND m.receiver_user_id=?)
                OR (m.sender_user_id=? AND m.receiver_user_id=u.id)
             ORDER BY m.id DESC LIMIT 1) AS last_msg,
          (SELECT m.created_at FROM internal_messages m
             WHERE (m.sender_user_id=u.id AND m.receiver_user_id=?)
                OR (m.sender_user_id=? AND m.receiver_user_id=u.id)
             ORDER BY m.id DESC LIMIT 1) AS last_at,
          (SELECT COUNT(*) FROM internal_messages m
             WHERE m.sender_user_id=u.id AND m.receiver_user_id=? AND m.is_read=0) AS unread
        FROM app_users u
        WHERE u.id<>? AND u.active=1
        ORDER BY (last_at IS NULL), last_at DESC, u.full_name");
    $st->execute([$me,$me,$me,$me,$me,$me]);
    $rows = $st->fetchAll();
} catch(Throwable $e){ $rows = []; }

/* --- Seçili kişi + mesajları --- */
$peer = null; $msgs = [];
if ($with > 0) {
    try {
        $p = $pdo->prepare("SELECT id, full_name, username, role FROM app_users WHERE id=?");
        $p->execute([$with]); $peer = $p->fetch();
    } catch(Throwable $e){ $peer = null; }
    if ($peer) {
        try {
            $ms = $pdo->prepare("SELECT * FROM internal_messages
                WHERE (sender_user_id=? AND receiver_user_id=?)
                   OR (sender_user_id=? AND receiver_user_id=?)
                ORDER BY id ASC LIMIT 500");
            $ms->execute([$me,$with,$with,$me]); $msgs = $ms->fetchAll();
        } catch(Throwable $e){ $msgs = []; }
    }
}

require_once __DIR__.'/layout_top.php';
?>
<style>
.msg-wrap{display:grid;grid-template-columns:320px 1fr;gap:16px;align-items:start}
.msg-list{background:#fff;border-radius:20px;box-shadow:0 8px 28px rgba(16,24,40,.06);padding:12px;max-height:74vh;overflow:auto}
.msg-list .lbl{font-size:11px;color:#7f95b2;letter-spacing:.06em;font-weight:900;margin:6px 8px;text-transform:uppercase}
.msg-row{display:flex;align-items:center;gap:11px;padding:11px;border-radius:14px;text-decoration:none;color:#101828}
.msg-row:hover{background:#f2f4f7}
.msg-row.active{background:#eef2ff}
.msg-row .av{width:42px;height:42px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:900;color:#fff;flex:0 0 auto}
.msg-row .meta{flex:1;min-width:0}
.msg-row .meta b{display:block;font-size:14px}
.msg-row .meta small{display:block;color:#667085;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.msg-row .badge.green{background:#22c55e;color:#06281a;min-width:22px;justify-content:center}
.chat-panel{background:#fff;border-radius:20px;box-shadow:0 8px 28px rgba(16,24,40,.06);display:flex;flex-direction:column;min-height:74vh;max-height:74vh}
.chat-head{display:flex;align-items:center;gap:12px;padding:14px 18px;border-bottom:1px solid #eef2f6}
.chat-head .av{width:44px;height:44px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:900;color:#fff;flex:0 0 auto}
.chat-body{flex:1;overflow:auto;padding:18px;display:flex;flex-direction:column;gap:8px;background:#f8fafc}
.bubble{max-width:72%;padding:10px 13px;border-radius:16px;font-size:14px;line-height:1.4;word-wrap:break-word;white-space:pre-wrap}
.bubble small{display:block;font-size:10px;opacity:.65;margin-top:4px;text-align:right}
.bubble.mine{align-self:flex-end;background:#2563eb;color:#fff;border-bottom-right-radius:5px}
.bubble.theirs{align-self:flex-start;background:#fff;border:1px solid #eef2f6;color:#101828;border-bottom-left-radius:5px}
.chat-empty{flex:1;display:flex;align-items:center;justify-content:center;color:#98a2b3;text-align:center;padding:24px}
.composer{display:flex;gap:10px;padding:14px;border-top:1px solid #eef2f6}
.composer textarea{flex:1;border:1px solid #d0d5dd;border-radius:14px;padding:11px;resize:none;font-family:inherit;font-size:14px;min-height:46px;max-height:140px}
.composer button{flex:0 0 auto}
.no-peer{background:#fff;border-radius:20px;box-shadow:0 8px 28px rgba(16,24,40,.06);padding:40px;text-align:center;color:#667085}
@media(max-width:960px){ .msg-wrap{grid-template-columns:1fr} .chat-panel,.msg-list{max-height:none} }
</style>
<?php
function msg_av_color($id){
    $c=['#3b82f6','#22c55e','#f97316','#8b5cf6','#ef4444','#14b8a6','#eab308','#ec4899'];
    return $c[((int)$id) % count($c)];
}
?>
<h1>💬 Mesajlar</h1>

<div class="msg-wrap">

    <!-- SOL: gruplar + kişi listesi -->
    <div class="msg-list">
        <a class="btn" href="messages.php?new=1" style="display:block;text-align:center;margin:4px 6px 10px;text-decoration:none">👥 Yeni Grup</a>

        <?php if($threads): ?>
            <div class="lbl">Gruplar</div>
            <?php foreach($threads as $t):
                $tactive = ($thread === (int)$t['id']);
                $tunread = ((int)$t['last_id'] > (int)$t['last_read_id']);
                $ticon = ($t['type']==='job') ? '📋' : (($t['type']==='cari') ? '🏢' : '👥');
            ?>
                <a class="msg-row <?=$tactive?'active':''?>" href="messages.php?thread=<?=(int)$t['id']?>">
                    <div class="av" style="background:<?=msg_av_color((int)$t['id']+99)?>"><?=$ticon?></div>
                    <div class="meta">
                        <b><?=h($t['title'])?></b>
                        <small><?=$t['last_msg'] ? h(mb_substr($t['last_msg'],0,40)) : '<span style="opacity:.6">Yeni grup</span>'?></small>
                    </div>
                    <?php if($tunread): ?><span class="badge green">●</span><?php endif; ?>
                </a>
            <?php endforeach; ?>
        <?php endif; ?>

        <div class="lbl">Kişiler</div>
        <?php if(!$rows): ?>
            <div style="padding:20px;text-align:center;color:#98a2b3">Mesajlaşılacak kullanıcı yok.</div>
        <?php else: foreach($rows as $r):
            $nm = $r['full_name'] ?: $r['username'];
            $active = ($with === (int)$r['id']);
        ?>
            <a class="msg-row <?=$active?'active':''?>" href="messages.php?u=<?=(int)$r['id']?>">
                <div class="av" style="background:<?=msg_av_color($r['id'])?>"><?=h(mb_strtoupper(mb_substr($nm,0,1)))?></div>
                <div class="meta">
                    <b><?=h($nm)?></b>
                    <small><?=$r['last_msg'] ? h(mb_substr($r['last_msg'],0,40)) : '<span style="opacity:.6">'.h($r['role'] ?: 'Yeni sohbet').'</span>'?></small>
                </div>
                <?php if((int)$r['unread'] > 0): ?>
                    <span class="badge green"><?=(int)$r['unread']?></span>
                <?php endif; ?>
            </a>
        <?php endforeach; endif; ?>
    </div>

    <!-- SAĞ: sohbet -->
    <?php if(isset($_GET['new'])): ?>
        <!-- YENİ GRUP OLUŞTURMA FORMU -->
        <div class="chat-panel" style="padding:22px;overflow:auto;display:block">
            <h2 style="margin:0 0 16px">👥 Yeni Grup</h2>
            <form method="post">
                <input type="hidden" name="new_group" value="1">
                <label style="font-weight:700;display:block;margin-bottom:6px">Grup Adı</label>
                <input name="title" required placeholder="örn. Üretim Ekibi"
                       style="width:100%;border:1px solid #d0d5dd;border-radius:12px;padding:11px;margin-bottom:16px;font-size:14px">
                <label style="font-weight:700;display:block;margin-bottom:8px">Üyeler</label>
                <div style="display:flex;flex-direction:column;gap:6px;max-height:48vh;overflow:auto">
                    <?php if(!$allUsers): ?>
                        <div style="color:#98a2b3">Eklenecek kullanıcı yok.</div>
                    <?php else: foreach($allUsers as $u): $nm=$u['full_name'] ?: $u['username']; ?>
                        <label style="display:flex;align-items:center;gap:10px;background:#f2f4f7;border-radius:12px;padding:10px;margin:0;cursor:pointer">
                            <input type="checkbox" name="members[]" value="<?=(int)$u['id']?>" style="width:auto;margin:0">
                            <span><?=h($nm)?></span>
                        </label>
                    <?php endforeach; endif; ?>
                </div>
                <div style="margin-top:16px;display:flex;gap:10px">
                    <button class="btn" type="submit">👥 Grubu Oluştur</button>
                    <a class="btn" href="messages.php" style="background:#eef2f6;color:#344054;text-decoration:none">Vazgeç</a>
                </div>
            </form>
        </div>
    <?php elseif($thread > 0): ?>
        <?php if(!$tgroup): ?>
            <div class="no-peer">
                <div style="font-size:46px">⚠️</div>
                <h2 style="margin:12px 0 6px">Grup bulunamadı</h2>
                <p>Grup yok ya da üyesi değilsiniz. <a href="messages.php">Listeye dön</a></p>
            </div>
        <?php else:
            $gicon = ($tgroup['type']==='job') ? '📋' : (($tgroup['type']==='cari') ? '🏢' : '👥');
        ?>
            <div class="chat-panel">
                <div class="chat-head">
                    <div class="av" style="background:<?=msg_av_color((int)$tgroup['id']+99)?>"><?=$gicon?></div>
                    <div style="flex:1">
                        <b style="font-size:16px"><?=h($tgroup['title'])?></b>
                        <div class="muted"><?=(int)$tmembers?> üye · <?=$tgroup['type']==='job'?'İş sohbeti':($tgroup['type']==='cari'?'Cari sohbeti':'Grup')?></div>
                    </div>
                </div>

                <div class="chat-body" id="chatBody">
                    <?php if(!$tmsgs): ?>
                        <div class="chat-empty">Henüz mesaj yok.<br>İlk mesajı siz yazın 👇</div>
                    <?php else: foreach($tmsgs as $m):
                        $mine = ((int)$m['sender_user_id'] === $me);
                        $snm  = $m['full_name'] ?: ($m['username'] ?: '?');
                        $aext_t = !empty($m['attachment']) ? strtolower(pathinfo($m['attachment'],PATHINFO_EXTENSION)) : '';
                        $attT_t = $m['attach_type'] ?? 'file';
                        $isAud_t = ($attT_t==='audio' || in_array($aext_t,['m4a','mp3','wav','ogg','oga','aac','webm','opus']));
                        $isVid_t = ($attT_t==='video' || in_array($aext_t,['mp4','mov','m4v']));
                    ?>
                        <div class="bubble <?=$mine?'mine':'theirs'?>" id="msg<?=(int)$m['id']?>">
                            <?php if(!$mine): ?>
                                <small style="display:block;color:#2563eb;font-weight:700;opacity:1;text-align:left;margin:0 0 2px"><?=h($snm)?></small>
                            <?php endif; ?>
                            <?php if(!empty($m['attachment'])): $apath=h($m['attachment']); ?>
                                <?php if($attT_t==='image'): ?>
                                    <img src="<?=$apath?>" style="max-width:260px;width:100%;border-radius:10px;display:block;margin-bottom:4px;cursor:pointer" onclick="window.open('<?=$apath?>','_blank')">
                                <?php elseif($isAud_t): ?>
                                    <audio controls preload="none" src="<?=$apath?>" style="max-width:280px;width:100%;display:block;margin-bottom:4px"></audio>
                                <?php elseif($isVid_t): ?>
                                    <video controls preload="none" src="<?=$apath?>" style="max-width:300px;width:100%;border-radius:10px;display:block;margin-bottom:4px"></video>
                                <?php else: ?>
                                    <a href="<?=$apath?>" target="_blank" style="color:inherit;text-decoration:underline">📎 Ek dosya</a><br>
                                <?php endif; ?>
                            <?php endif; ?>
                            <span id="msgtxt<?=(int)$m['id']?>"><?=nl2br(h($m['message']))?></span>
                            <small>
                              <?=h(date('d.m.Y H:i', strtotime($m['created_at'])))?>
                              <?php if($mine): ?>
                                <?php if(empty($m['attachment'])): ?><a href="javascript:void(0)" onclick="editMsgT(<?=(int)$m['id']?>)" style="opacity:.6;margin-left:6px;color:inherit;text-decoration:none" title="Düzenle">✏️</a><?php endif; ?>
                                <a href="javascript:void(0)" onclick="delMsgT(<?=(int)$m['id']?>)" style="opacity:.6;margin-left:4px;color:inherit;text-decoration:none" title="Sil">🗑</a>
                              <?php endif; ?>
                            </small>
                        </div>
                    <?php endforeach; endif; ?>
                </div>

                <!-- Grup düzenleme modalı (web) -->
                <div id="editModalT" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,.4);align-items:center;justify-content:center">
                  <div style="background:#fff;border-radius:16px;padding:20px;width:440px;max-width:94vw;box-shadow:0 8px 32px rgba(0,0,0,.18)">
                    <div style="font-weight:700;margin-bottom:10px;font-size:15px">Mesajı Düzenle</div>
                    <textarea id="editTextT" rows="4" style="width:100%;border:1px solid #d0d5dd;border-radius:10px;padding:10px;font-size:14px;resize:vertical"></textarea>
                    <div style="display:flex;gap:10px;margin-top:12px">
                      <button onclick="saveEditT()" class="btn" style="flex:1">Kaydet</button>
                      <button onclick="document.getElementById('editModalT').style.display='none'" class="btn" style="flex:1;background:#eef2f6;color:#344054">İptal</button>
                    </div>
                  </div>
                </div>

                <form method="post" class="composer" enctype="multipart/form-data">
                    <input type="hidden" name="thread_id" value="<?=(int)$thread?>">
                    <label title="Dosya ekle" style="cursor:pointer;font-size:22px;align-self:center;opacity:.7">
                        📎<input type="file" name="attach" accept="image/*,audio/*,video/*,application/pdf,.doc,.docx,.xls,.xlsx" style="display:none">
                    </label>
                    <?=emoji_picker_html('msgComposerT')?>
                    <textarea id="msgComposerT" name="message" placeholder="Gruba yazın…"
                        onkeydown="if(event.key==='Enter'&&!event.shiftKey){event.preventDefault();this.form.submit();}"></textarea>
                    <button class="btn" type="submit">➤ Gönder</button>
                </form>
                <script>
var _THREAD=<?=(int)$thread?>;
var _editIdT=0;
function editMsgT(id){
  var sp=document.getElementById('msgtxt'+id); if(!sp)return;
  _editIdT=id; document.getElementById('editTextT').value=sp.innerText||sp.textContent;
  document.getElementById('editModalT').style.display='flex';
}
function saveEditT(){
  var txt=document.getElementById('editTextT').value.trim(); if(!txt)return;
  var fd=new FormData(); fd.append('edit_msg',_editIdT); fd.append('edit_text',txt); fd.append('thread',_THREAD); fd.append('ajax','1');
  fetch('messages.php?thread='+_THREAD,{method:'POST',body:fd,credentials:'same-origin'})
    .then(function(r){return r.json();}).then(function(d){
      if(d&&d.ok){ var sp=document.getElementById('msgtxt'+_editIdT); if(sp)sp.innerHTML=txt.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/\n/g,'<br>'); document.getElementById('editModalT').style.display='none'; }
      else alert(d&&d.error?d.error:'Düzenlenemedi.');
    }).catch(function(){ alert('Bağlantı hatası.'); });
}
function delMsgT(id){
  if(!confirm('Bu mesaj silinsin mi?')) return;
  var fd=new FormData(); fd.append('del_msg',id); fd.append('thread',_THREAD); fd.append('ajax','1');
  fetch('messages.php?thread='+_THREAD,{method:'POST',body:fd,credentials:'same-origin'})
    .then(function(r){return r.json();}).then(function(d){
      if(d&&d.ok){ var b=document.getElementById('msg'+id); if(b)b.remove(); }
      else alert(d&&d.error?d.error:'Silinemedi.');
    }).catch(function(){ alert('Bağlantı hatası.'); });
}
                </script>
            </div>
        <?php endif; ?>
    <?php elseif(!$with): ?>
        <div class="no-peer">
            <div style="font-size:46px">💬</div>
            <h2 style="margin:12px 0 6px">Bir kişi veya grup seçin</h2>
            <p>Soldaki listeden bir kullanıcıya ya da gruba tıklayarak yazışmaya başlayın.</p>
        </div>
    <?php elseif(!$peer): ?>
        <div class="no-peer">
            <div style="font-size:46px">⚠️</div>
            <h2 style="margin:12px 0 6px">Kullanıcı bulunamadı</h2>
            <p><a href="messages.php">Listeye dön</a></p>
        </div>
    <?php else:
        $pname = $peer['full_name'] ?: $peer['username'];
    ?>
        <div class="chat-panel">
            <div class="chat-head">
                <div class="av" style="background:<?=msg_av_color($peer['id'])?>"><?=h(mb_strtoupper(mb_substr($pname,0,1)))?></div>
                <div style="flex:1">
                    <b style="font-size:16px"><?=h($pname)?></b>
                    <div class="muted"><?=h($peer['role'] ?: 'Kullanıcı')?></div>
                </div>
            </div>

            <div class="chat-body" id="chatBody">
                <?php if(!$msgs): ?>
                    <div class="chat-empty">Henüz mesaj yok.<br>İlk mesajı siz yazın 👇</div>
                <?php else: foreach($msgs as $m):
                    $mine = ((int)$m['sender_user_id'] === $me);
                    $aext_w = !empty($m['attachment']) ? strtolower(pathinfo($m['attachment'],PATHINFO_EXTENSION)) : '';
                    $attT_w = $m['attach_type'] ?? 'file';
                    $isAud_w = ($attT_w==='audio' || in_array($aext_w,['m4a','mp3','wav','ogg','oga','aac','webm','opus']));
                    $isVid_w = ($attT_w==='video' || in_array($aext_w,['mp4','mov','m4v']));
                ?>
                    <div class="bubble <?=$mine?'mine':'theirs'?>" id="msg<?=(int)$m['id']?>">
                        <?php if(!empty($m['attachment'])): $apath=h($m['attachment']); ?>
                            <?php if($attT_w==='image'): ?>
                                <img src="<?=$apath?>" style="max-width:260px;width:100%;border-radius:10px;display:block;margin-bottom:4px;cursor:pointer" onclick="window.open('<?=$apath?>','_blank')">
                            <?php elseif($isAud_w): ?>
                                <audio controls preload="none" src="<?=$apath?>" style="max-width:280px;width:100%;display:block;margin-bottom:4px"></audio>
                            <?php elseif($isVid_w): ?>
                                <video controls preload="none" src="<?=$apath?>" style="max-width:300px;width:100%;border-radius:10px;display:block;margin-bottom:4px"></video>
                            <?php else: ?>
                                <a href="<?=$apath?>" target="_blank" style="color:inherit;text-decoration:underline">📎 Ek dosya</a><br>
                            <?php endif; ?>
                        <?php endif; ?>
                        <span id="msgtxt<?=(int)$m['id']?>"><?=nl2br(h($m['message']))?></span>
                        <small>
                          <?=h(date('d.m.Y H:i', strtotime($m['created_at'])))?><?=$mine ? ((int)$m['is_read'] ? ' ✓✓' : ' ✓') : ''?>
                          <?php if($mine): ?>
                            <?php if(empty($m['attachment'])): ?><a href="javascript:void(0)" onclick="editMsg(<?=(int)$m['id']?>)" title="Düzenle" style="opacity:.6;margin-left:6px;color:inherit;text-decoration:none">✏️</a><?php endif; ?>
                            <a href="javascript:void(0)" onclick="delMsg(<?=(int)$m['id']?>)" title="Sil" style="opacity:.6;margin-left:4px;color:inherit;text-decoration:none">🗑</a>
                          <?php endif; ?>
                        </small>
                    </div>
                <?php endforeach; endif; ?>
            </div>

            <!-- Düzenleme modalı (web) -->
            <div id="editModal" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,.4);align-items:center;justify-content:center">
              <div style="background:#fff;border-radius:16px;padding:20px;width:440px;max-width:94vw;box-shadow:0 8px 32px rgba(0,0,0,.18)">
                <div style="font-weight:700;margin-bottom:10px;font-size:15px">Mesajı Düzenle</div>
                <textarea id="editText" rows="4" style="width:100%;border:1px solid #d0d5dd;border-radius:10px;padding:10px;font-size:14px;resize:vertical"></textarea>
                <div style="display:flex;gap:10px;margin-top:12px">
                  <button onclick="saveEdit()" class="btn" style="flex:1">Kaydet</button>
                  <button onclick="document.getElementById('editModal').style.display='none'" class="btn" style="flex:1;background:#eef2f6;color:#344054">İptal</button>
                </div>
              </div>
            </div>

            <form method="post" class="composer" enctype="multipart/form-data">
                <input type="hidden" name="receiver_user_id" value="<?=(int)$with?>">
                <label title="Dosya ekle" style="cursor:pointer;font-size:22px;align-self:center;opacity:.7" title="Dosya ekle">
                    📎<input type="file" name="attach" accept="image/*,audio/*,video/*,application/pdf,.doc,.docx,.xls,.xlsx" style="display:none">
                </label>
                <?=emoji_picker_html('msgComposer')?>
                <textarea id="msgComposer" name="message" placeholder="Mesaj yazın…"
                    onkeydown="if(event.key==='Enter'&&!event.shiftKey){event.preventDefault();this.form.submit();}"></textarea>
                <button class="btn" type="submit">➤ Gönder</button>
            </form>
        </div>
    <?php endif; ?>

<script>
var _WITH=<?=(int)$with?>;
var _editId=0;
function editMsg(id){
  var sp=document.getElementById('msgtxt'+id); if(!sp)return;
  _editId=id; document.getElementById('editText').value=sp.innerText||sp.textContent;
  document.getElementById('editModal').style.display='flex';
}
function saveEdit(){
  var txt=document.getElementById('editText').value.trim(); if(!txt)return;
  var fd=new FormData(); fd.append('edit_msg',_editId); fd.append('edit_text',txt); fd.append('with',_WITH); fd.append('ajax','1');
  fetch('messages.php?u='+_WITH,{method:'POST',body:fd,credentials:'same-origin'})
    .then(function(r){return r.json();}).then(function(d){
      if(d&&d.ok){ var sp=document.getElementById('msgtxt'+_editId); if(sp)sp.innerHTML=txt.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/\n/g,'<br>'); document.getElementById('editModal').style.display='none'; }
      else alert(d&&d.error?d.error:'Düzenlenemedi.');
    }).catch(function(){ alert('Bağlantı hatası.'); });
}
function delMsg(id){
  if(!confirm('Bu mesaj silinsin mi?')) return;
  var fd=new FormData(); fd.append('del_msg',id); fd.append('with',_WITH); fd.append('ajax','1');
  fetch('messages.php?u='+_WITH,{method:'POST',body:fd,credentials:'same-origin'})
    .then(function(r){return r.json();}).then(function(d){
      if(d&&d.ok){ var b=document.getElementById('msg'+id); if(b)b.remove(); }
      else alert(d&&d.error?d.error:'Silinemedi.');
    }).catch(function(){ alert('Bağlantı hatası.'); });
}
</script>

</div>

<script>
// Sohbet açıkken en alta kaydır
(function(){
    var b=document.getElementById('chatBody');
    if(b){ b.scrollTop=b.scrollHeight; }
})();
</script>
<?php require_once __DIR__.'/layout_bottom.php'; ?>
