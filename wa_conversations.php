<?php
require_once __DIR__.'/boot.php';
require_login();
require_permission('users');
require_once __DIR__.'/share_lib.php';
wa_install();

$pdo=db();
$q=trim($_GET['q'] ?? '');
$sql="SELECT c.*, ct.name contact_name FROM wa_conversations c LEFT JOIN contacts ct ON ct.id=c.contact_id";
$params=[];
if($q!==''){
    $sql.=" WHERE ct.name LIKE ? OR c.phone LIKE ?";
    $params=['%'.$q.'%','%'.$q.'%'];
}
$sql.=" ORDER BY c.last_message_at DESC";
$st=$pdo->prepare($sql); $st->execute($params);
$rows=$st->fetchAll();

require_once __DIR__.'/layout_top.php';
?>
<style>
/* İLETİŞİM MERKEZİ — SON UI BİRLİĞİ (2026-07-18, Product Owner kararı): "WhatsApp ekranını mevcut
   OTS Sohbetler ekranıyla aynı Design System diline getir." Bu blok messages.php'nin .msg-wrap/
   .msg-list/.msg-row/.chat-panel/.chat-head/.chat-body/.bubble/.composer/.no-peer bloğuyla
   BİREBİR AYNI (sayfa-yerel kopya deseni — df-form-grid-2 ile aynı proje kuralı, tek kaynağa
   çıkarılmadı çünkü DS'in kendi ilkesi de "genel bileşenlere zorla oturtma" değil "aynı görsel
   dil" istiyor). WhatsApp gönderim/iş mantığına HİÇ dokunulmadı, sadece render katmanı. */
.msg-wrap{display:grid;grid-template-columns:320px 1fr;gap:16px;align-items:start}
.msg-list{background:var(--df-surface);border-radius:var(--df-radius-lg);box-shadow:var(--df-elevation-raised);padding:12px;max-height:74vh;overflow:auto;min-width:0}
.msg-list .lbl{font-size:11px;color:var(--df-ink-500);letter-spacing:.06em;font-weight:900;margin:6px 8px;text-transform:uppercase}
.msg-row{display:flex;align-items:center;gap:11px;padding:11px;border-radius:var(--df-radius-md);text-decoration:none;color:var(--df-ink-900)}
.msg-row:hover{background:var(--df-surface-sunken)}
.msg-row.active{background:var(--df-accent-soft)}
.msg-row .av{width:42px;height:42px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:900;color:#fff;flex:0 0 auto}
.msg-row .meta{flex:1;min-width:0}
.msg-row .meta b{display:block;font-size:14px}
.msg-row .meta small{display:block;color:var(--df-ink-500);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.msg-row .badge.green{background:var(--df-success);color:#06281a;min-width:22px;justify-content:center}
.chat-panel{background:var(--df-surface);border-radius:var(--df-radius-lg);box-shadow:var(--df-elevation-raised);display:flex;flex-direction:column;min-height:74vh;max-height:74vh;min-width:0}
.chat-head{display:flex;align-items:center;gap:12px;padding:14px 18px;border-bottom:1px solid var(--df-hairline)}
.chat-head .av{width:44px;height:44px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:900;color:#fff;flex:0 0 auto}
.chat-body{flex:1;overflow:auto;padding:18px;display:flex;flex-direction:column;gap:8px;background:var(--df-surface-sunken)}
.bubble{max-width:72%;padding:10px 13px;border-radius:16px;font-size:14px;line-height:1.4;word-wrap:break-word;white-space:pre-wrap}
.bubble small{display:block;font-size:10px;opacity:.65;margin-top:4px;text-align:right}
.bubble.mine{align-self:flex-end;background:var(--df-accent);color:var(--df-accent-ink);border-bottom-right-radius:5px}
.bubble.theirs{align-self:flex-start;background:var(--df-surface);border:1px solid var(--df-hairline);color:var(--df-ink-900);border-bottom-left-radius:5px}
.chat-empty{flex:1;display:flex;align-items:center;justify-content:center;color:var(--df-ink-500);text-align:center;padding:24px}
.composer{display:flex;gap:10px;padding:14px;border-top:1px solid var(--df-hairline)}
.composer textarea{flex:1;border:1px solid var(--df-hairline);border-radius:var(--df-radius-md);padding:11px;resize:none;font-family:inherit;font-size:14px;min-height:46px;max-height:140px;background:var(--df-surface);color:var(--df-ink-900)}
.composer button{flex:0 0 auto}
.no-peer{background:var(--df-surface);border-radius:var(--df-radius-lg);box-shadow:var(--df-elevation-raised);padding:40px;text-align:center;color:var(--df-ink-500);min-width:0}
@media(max-width:960px){ .msg-wrap{grid-template-columns:1fr} .chat-panel,.msg-list{max-height:none} }
</style>

<?php
// "Toplu Gönderim" İletişim Merkezi/WhatsApp sekmesinin bir aksiyonu olarak kalsın — ayrı modül
// hissi vermesin diye başlıkta ikincil buton olarak duruyor (nav'da bağımsız bir hedef DEĞİL,
// bkz. nav_lib.php: wa_send_now.php category=null).
ds_page_header('WhatsApp', ds_icon('chat',24), 'İletişim Merkezi', ds_button('📲 Toplu Gönderim','wa_send_now.php','secondary','','',true), false, true);
ic_tabs('whatsapp');
?>

<div class="msg-wrap" style="margin-top:16px">
  <div class="msg-list">
    <?=wa_new_conversation_picker_html($pdo)?>
    <form method="get" style="margin:0 6px 10px">
      <input type="text" name="q" placeholder="İsim veya telefon ara…" value="<?=h($q)?>" onchange="this.form.submit()">
    </form>
    <div class="lbl">Konuşmalar</div>
    <?=wa_conversation_list_html($rows)?>
  </div>
  <div class="no-peer">
    <div style="font-size:46px">📲</div>
    <h2 style="margin:12px 0 6px">Bir konuşma seçin</h2>
    <p>Soldaki listeden bir kişiye tıklayarak WhatsApp geçmişini görüntüleyin.</p>
  </div>
</div>

<?php require_once __DIR__.'/layout_bottom.php'; ?>
