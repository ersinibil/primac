<?php
require_once __DIR__.'/boot.php';
require_permission('users');
require_once __DIR__.'/share_lib.php';

$pdo=db();
$error=''; $ok=''; $manualBulkLinks=[]; $manualNote='';

// REOPEN-003 (2026-07-07): tekil (1:1) mesajlaşma artık wa_conversation_view.php'nin inline
// compose kutusundan yürüyor — bu sayfa SADECE toplu/broadcast gönderim için kullanılıyor.
if($_SERVER['REQUEST_METHOD']==='POST'){
    $message=trim($_POST['message'] ?? '');
    $picked=array_merge((array)($_POST['bulk_personnel'] ?? []), (array)($_POST['bulk_contacts'] ?? []));
    $picked=array_values(array_unique(array_filter(array_map('trim',$picked))));
    if(!$picked){
        $error='Toplu gönderim için en az bir alıcı seçili olmalı.';
    } elseif(!$message && empty($_FILES['attach']['tmp_name'])){
        $error='Mesaj veya dosya ekinden en az biri girilmeli.';
    } else {
        $media=null; $uploadErr='';
        if(!empty($_FILES['attach']['tmp_name'])){
            $media=wa_upload_media('attach',$uploadErr);
            if(!$media && $uploadErr) $error=$uploadErr;
        }
        if(!$error){
            $sentCount=0; $failed=[];
            foreach($picked as $ph){
                $s = wa_send_logged($ph,$message,'wa_send_now',$media['url']??null,$media['type']??null);
                if($s) $sentCount++; else $failed[]=$ph;
            }
            if($sentCount){
                $ok=$sentCount.' kişiye gönderildi.'.($failed?(' '.count($failed).' numaraya gönderilemedi: '.implode(', ',$failed)):'');
            } else {
                $error='Hiçbir alıcıya API üzerinden gönderilemedi (WhatsApp Ayarları\'nda gateway kurulu/etkin olmayabilir).';
                foreach($picked as $ph){
                    $manualBulkLinks[]=['phone'=>$ph,'link'=>wa_link($message?:($media?'📎 '.$media['name']:''),$ph)];
                }
                if($media) $manualNote='Dosya WhatsApp linkiyle otomatik eklenemez — her kişi için WhatsApp açıldıktan sonra "'.$media['name'].'" dosyasını (uploads/wa_send/ klasöründen) elle ekleyip gönder.';
            }
        }
    }
}

$personnel=$pdo->query("SELECT name,phone FROM personnel WHERE COALESCE(active,1)=1 AND phone<>'' ORDER BY name")->fetchAll();
$contacts=$pdo->query("SELECT name,phone FROM contacts WHERE phone<>'' ORDER BY name")->fetchAll();

require_once __DIR__.'/layout_top.php';
ds_page_header('WhatsApp — Toplu Gönderim', ds_icon('send',24), '', '', false, true);
ic_tabs('whatsapp');
?>

<section class="df-card" style="max-width:640px;margin-top:var(--df-space-4)">
<?php if($error): ?><?=ds_alert('danger',$error)?><?php endif; ?>
<?php if($manualBulkLinks): ?>
    <div class="df-alert df-alert--warning" style="margin-bottom:var(--df-space-3);display:block">
        <p style="margin:0 0 8px"><?=h($manualNote?:'API üzerinden gönderilemedi. Aşağıdaki kişileri tek tek elle açıp gönderebilirsiniz:')?></p>
        <?php foreach($manualBulkLinks as $mb): ?>
        <a href="<?=h($mb['link'])?>" target="_blank" rel="noopener" class="df-btn df-btn--sm" style="background:var(--df-success);color:#fff;margin:0 6px 6px 0"><?=ds_icon('send',14)?> <?=h($mb['phone'])?></a>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
<?php if($ok): ?><?=ds_alert('success',$ok)?><?php endif; ?>

<form method="post" class="df-form-grid-2" enctype="multipart/form-data" onsubmit="return waConfirmSubmit()">

<div class="df-form-span-2" style="display:flex;justify-content:space-between;align-items:center">
    <label class="df-form-label" style="margin:0">Personel</label>
    <button type="button" class="df-btn df-btn--secondary df-btn--sm" onclick="waToggleAll('wa-bp',this)">Tümünü Kaldır</button>
</div>
<div class="df-form-span-2" style="max-height:180px;overflow:auto;border:1px solid var(--df-hairline);border-radius:var(--df-radius-sm);padding:8px;margin:6px 0 var(--df-space-4)">
    <?php foreach($personnel as $p): ?>
    <label style="display:flex;align-items:center;gap:8px;padding:4px 0;font-weight:400">
        <input type="checkbox" class="wa-bp" name="bulk_personnel[]" value="<?=h($p['phone'])?>" checked style="width:auto">
        <?=h($p['name'])?> <span class="df-muted" style="font-size:12px"><?=h($p['phone'])?></span>
    </label>
    <?php endforeach; ?>
    <?php if(!$personnel): ?><p class="df-muted" style="margin:4px 0">Telefonlu personel yok.</p><?php endif; ?>
</div>

<div class="df-form-span-2" style="display:flex;justify-content:space-between;align-items:center">
    <label class="df-form-label" style="margin:0">Cari</label>
    <button type="button" class="df-btn df-btn--secondary df-btn--sm" onclick="waToggleAll('wa-bc',this)">Tümünü Kaldır</button>
</div>
<div class="df-form-span-2" style="max-height:180px;overflow:auto;border:1px solid var(--df-hairline);border-radius:var(--df-radius-sm);padding:8px;margin-top:6px">
    <?php foreach($contacts as $c): ?>
    <label style="display:flex;align-items:center;gap:8px;padding:4px 0;font-weight:400">
        <input type="checkbox" class="wa-bc" name="bulk_contacts[]" value="<?=h($c['phone'])?>" checked style="width:auto">
        <?=h($c['name'])?> <span class="df-muted" style="font-size:12px"><?=h($c['phone'])?></span>
    </label>
    <?php endforeach; ?>
    <?php if(!$contacts): ?><p class="df-muted" style="margin:4px 0">Telefonlu cari yok.</p><?php endif; ?>
</div>

<div class="df-form-span-2"><?php ds_form_field('Mesaj', '<textarea id="waMsg" name="message" rows="5" placeholder="Mesajınızı yazın…">'.h($_POST['message'] ?? '').'</textarea>'); ?></div>

<div class="df-form-span-2" style="display:flex;gap:8px;align-items:center;margin-top:-8px">
    <?=emoji_picker_html('waMsg')?>
    <label class="df-btn df-btn--secondary df-btn--sm" style="margin:0;cursor:pointer">
        <?=ds_icon('plus',14)?> Dosya/Video/Ses Ekle
        <input type="file" name="attach" accept="image/*,video/*,audio/*,application/pdf,.doc,.docx,.xls,.xlsx" style="display:none" onchange="document.getElementById('waFileName').textContent=this.files[0]?this.files[0].name:''">
    </label>
    <span id="waFileName" class="df-muted" style="font-size:13px"></span>
</div>

<div class="df-form-span-2">
<button type="submit" class="df-btn df-btn--primary"><?=ds_icon('send',16)?> Gönder</button>
</div>
</form>
</section>

<style>
body.nav-compact .df-form-grid-2{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:0 var(--df-space-4)}
body.nav-compact .df-form-span-2{grid-column:1 / -1}
@media(max-width:640px){body.nav-compact .df-form-grid-2{grid-template-columns:1fr}}
</style>

<script>
function waToggleAll(cls, btn){
    var boxes = document.querySelectorAll('.'+cls);
    var anyChecked = Array.prototype.some.call(boxes, function(b){ return b.checked; });
    boxes.forEach(function(b){ b.checked = !anyChecked; });
    btn.textContent = anyChecked ? 'Tümünü Seç' : 'Tümünü Kaldır';
}
function waConfirmSubmit(){
    var n = document.querySelectorAll('.wa-bp:checked, .wa-bc:checked').length;
    return confirm('Bu mesaj '+n+' kişiye WhatsApp üzerinden gönderilecek. Emin misiniz?');
}
</script>
