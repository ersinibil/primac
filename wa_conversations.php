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
/* WhatsApp Konuşmaları/Detayı ortak shell — İletişim Merkezi'nin bir parçası (2026-07-18, Product
   Owner kararı: "WhatsApp'ı İletişim Merkezi'nde al"). Renkler DS token'larına taşındı, wa_conversation_
   view.php ile BİREBİR aynı (paylaşılan .wa-* class'ları, wa_conversation_list_html()/
   wa_new_conversation_picker_html() üzerinden ortak kullanılıyor). */
.wa-shell{display:flex;border:1px solid var(--df-hairline);border-radius:var(--df-radius-lg);overflow:hidden;min-height:60vh}
.wa-list-col{width:320px;flex:0 0 auto;border-right:1px solid var(--df-hairline);overflow-y:auto;max-height:75vh;background:var(--df-surface)}
.wa-list-search{padding:10px;border-bottom:1px solid var(--df-hairline);position:sticky;top:0;background:var(--df-surface)}
.wa-list-search input{margin:0}
.wa-list-item{display:flex;justify-content:space-between;align-items:center;gap:10px;padding:12px;border-bottom:1px solid var(--df-hairline);text-decoration:none;color:inherit}
.wa-list-item:hover{background:var(--df-surface-sunken)}
.wa-list-item.active{background:var(--df-accent-soft)}
.wa-badge{background:var(--df-danger);color:#fff;border-radius:10px;padding:2px 8px;font-size:11px;font-weight:800}
.wa-thread-col{flex:1;min-width:0;display:flex;align-items:center;justify-content:center;color:var(--df-ink-500);padding:40px}
@media(max-width:900px){.wa-shell{flex-direction:column}.wa-list-col{width:100%;max-height:none;border-right:0}.wa-thread-col{display:none}}
</style>

<?php ds_page_header('WhatsApp Konuşmaları', ds_icon('chat',24), '', ds_button('📲 Toplu Gönderim','wa_send_now.php','secondary','','',true), false, true); ?>
<?php ic_tabs('whatsapp'); ?>

<section class="df-card" style="padding:0;margin-top:var(--df-space-4)">
<div class="wa-shell">
  <div class="wa-list-col">
    <?=wa_new_conversation_picker_html($pdo)?>
    <form class="wa-list-search" method="get">
      <input type="text" name="q" placeholder="İsim veya telefon ara…" value="<?=h($q)?>" onchange="this.form.submit()">
    </form>
    <?=wa_conversation_list_html($rows)?>
  </div>
  <div class="wa-thread-col"><span>Bir konuşma seçin</span></div>
</div>
</section>

<?php require_once __DIR__.'/layout_bottom.php'; ?>
