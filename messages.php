<?php
/* ===========================================================================
 * messages.php — Web (masaüstü) iç mesajlaşma ekranı
 * Mobil mobile/messages.php ile aynı internal_messages tablosunu kullanır.
 * 1-1 yazışma (gelen+giden), kişi listesi, yeni mesaj başlatma.
 * POST işlemi layout_top'tan ÖNCE yapılır (PRG deseni → redirect).
 * ========================================================================= */
require_once __DIR__.'/boot.php';
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

$with  = (int)($_GET['u'] ?? 0);   // konuşulan kişi
$flash = '';

/* --- POST: mesaj gönder (çıktıdan ÖNCE → PRG redirect) --- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $to   = (int)($_POST['receiver_user_id'] ?? 0);
    $body = trim((string)($_POST['message'] ?? ''));
    if ($to > 0 && $body !== '') {
        try {
            $pdo->prepare("INSERT INTO internal_messages
                (sender_user_id, receiver_user_id, message, is_read)
                VALUES(?,?,?,0)")->execute([$me, $to, $body]);
            // Kapalıyken push bildirimi (varsa) — zorunlu değil
            if (file_exists(__DIR__.'/push_lib.php')) {
                require_once __DIR__.'/push_lib.php';
                $sname = current_user()['name'] ?? current_user()['username'] ?? 'Kullanıcı';
                try { push_to_user($to, '💬 '.$sname, mb_substr($body,0,90), 'messages.php?u='.$me); } catch(Throwable $e){}
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

    <!-- SOL: kişi listesi -->
    <div class="msg-list">
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
    <?php if(!$with): ?>
        <div class="no-peer">
            <div style="font-size:46px">💬</div>
            <h2 style="margin:12px 0 6px">Bir kişi seçin</h2>
            <p>Soldaki listeden bir kullanıcıya tıklayarak yazışmaya başlayın.</p>
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
                ?>
                    <div class="bubble <?=$mine?'mine':'theirs'?>">
                        <?php if(!empty($m['attachment'])):
                            $apath = h($m['attachment']);
                        ?>
                            <a href="<?=$apath?>" target="_blank" style="color:inherit;text-decoration:underline">📎 Ek dosya</a><br>
                        <?php endif; ?>
                        <?=nl2br(h($m['message']))?>
                        <small><?=h(date('d.m.Y H:i', strtotime($m['created_at'])))?><?=$mine ? ((int)$m['is_read'] ? ' ✓✓' : ' ✓') : ''?></small>
                    </div>
                <?php endforeach; endif; ?>
            </div>

            <form method="post" class="composer">
                <input type="hidden" name="receiver_user_id" value="<?=(int)$with?>">
                <textarea name="message" placeholder="Mesaj yazın…" required
                    onkeydown="if(event.key==='Enter'&&!event.shiftKey){event.preventDefault();this.form.submit();}"></textarea>
                <button class="btn" type="submit">➤ Gönder</button>
            </form>
        </div>
    <?php endif; ?>

</div>

<script>
// Sohbet açıkken en alta kaydır
(function(){
    var b=document.getElementById('chatBody');
    if(b){ b.scrollTop=b.scrollHeight; }
})();
</script>
<?php require_once __DIR__.'/layout_bottom.php'; ?>
