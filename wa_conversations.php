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
.wa-shell{display:flex;border:1px solid #eef2f6;border-radius:16px;overflow:hidden;min-height:60vh}
.wa-list-col{width:320px;flex:0 0 auto;border-right:1px solid #eef2f6;overflow-y:auto;max-height:75vh;background:#fff}
.wa-list-search{padding:10px;border-bottom:1px solid #eef2f6;position:sticky;top:0;background:#fff}
.wa-list-search input{margin:0}
.wa-list-item{display:flex;justify-content:space-between;align-items:center;gap:10px;padding:12px;border-bottom:1px solid #eef2f6;text-decoration:none;color:inherit}
.wa-list-item:hover{background:#f8fafc}
.wa-list-item.active{background:#eef4ff}
.wa-badge{background:#dc2626;color:#fff;border-radius:10px;padding:2px 8px;font-size:11px;font-weight:800}
.wa-thread-col{flex:1;min-width:0;display:flex;align-items:center;justify-content:center;color:#94a3b8;padding:40px}
@media(max-width:900px){.wa-shell{flex-direction:column}.wa-list-col{width:100%;max-height:none;border-right:0}.wa-thread-col{display:none}}
</style>

<div class="panel-head">
<h1>💬 WhatsApp Konuşmaları</h1>
</div>

<section class="panel" style="padding:0">
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
