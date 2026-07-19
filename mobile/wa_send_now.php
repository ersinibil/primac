<?php
require_once 'common.php';
if(!user_can('users')){ header('Location: index.php'); exit; }
require_once __DIR__.'/../share_lib.php';
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
                if($media) $manualNote='Dosya linkle otomatik eklenemez — her kişi için WhatsApp açıldıktan sonra "'.$media['name'].'" dosyasını elle ekleyip gönder.';
            }
        }
    }
}

$personnel=$pdo->query("SELECT name,phone FROM personnel WHERE COALESCE(active,1)=1 AND phone<>'' ORDER BY name")->fetchAll();
$contacts=$pdo->query("SELECT name,phone FROM contacts WHERE phone<>'' ORDER BY name")->fetchAll();

topx('WhatsApp Toplu Gönderim');
ic_tabs('whatsapp');
?>
<?php if($error): ?><?=ds_alert('danger',$error)?><?php endif; ?>
<?php if($manualBulkLinks): ?>
<div class="df-panel" style="background:var(--df-danger-soft, rgba(220,38,38,.12));margin-bottom:10px">
    <p style="margin:0 0 8px"><?=h($manualNote?:'API üzerinden gönderilemedi. Aşağıdaki kişileri tek tek elle açıp gönderebilirsiniz:')?></p>
    <?php foreach($manualBulkLinks as $mb): ?>
    <a href="<?=h($mb['link'])?>" target="_blank" rel="noopener" class="df-btn df-btn--primary" style="background:var(--df-success);margin:0 6px 6px 0"><?=ds_icon('send',14)?> <?=h($mb['phone'])?></a>
    <?php endforeach; ?>
</div>
<?php endif; ?>
<?php if($ok): ?><?=ds_alert('success',$ok)?><?php endif; ?>

<div class="df-panel">
<form method="post" enctype="multipart/form-data" onsubmit="return waConfirmSubmit()">
    <div style="display:flex;justify-content:space-between;align-items:center">
        <label style="color:var(--df-ink-500,#94a3b8);font-size:12px;margin:0">Personel</label>
        <button type="button" class="df-btn df-btn--secondary df-btn--sm" onclick="waToggleAll('wa-bp',this)">Tümünü Kaldır</button>
    </div>
    <div style="max-height:160px;overflow:auto;border:1px solid var(--df-hairline,rgba(255,255,255,.12));border-radius:10px;padding:8px;margin:6px 0 10px">
        <?php foreach($personnel as $p): ?>
        <label style="display:flex;align-items:center;gap:8px;padding:4px 0;margin:0">
            <input type="checkbox" class="wa-bp" name="bulk_personnel[]" value="<?=h($p['phone'])?>" checked style="width:auto">
            <?=h($p['name'])?> <span class="muted" style="font-size:12px"><?=h($p['phone'])?></span>
        </label>
        <?php endforeach; ?>
        <?php if(!$personnel): ?><p class="muted" style="margin:4px 0">Telefonlu personel yok.</p><?php endif; ?>
    </div>

    <div style="display:flex;justify-content:space-between;align-items:center">
        <label style="color:var(--df-ink-500,#94a3b8);font-size:12px;margin:0">Cari</label>
        <button type="button" class="df-btn df-btn--secondary df-btn--sm" onclick="waToggleAll('wa-bc',this)">Tümünü Kaldır</button>
    </div>
    <div style="max-height:160px;overflow:auto;border:1px solid var(--df-hairline,rgba(255,255,255,.12));border-radius:10px;padding:8px;margin-top:6px">
        <?php foreach($contacts as $c): ?>
        <label style="display:flex;align-items:center;gap:8px;padding:4px 0;margin:0">
            <input type="checkbox" class="wa-bc" name="bulk_contacts[]" value="<?=h($c['phone'])?>" checked style="width:auto">
            <?=h($c['name'])?> <span class="muted" style="font-size:12px"><?=h($c['phone'])?></span>
        </label>
        <?php endforeach; ?>
        <?php if(!$contacts): ?><p class="muted" style="margin:4px 0">Telefonlu cari yok.</p><?php endif; ?>
    </div>

    <label style="color:var(--df-ink-500,#94a3b8);font-size:12px">Mesaj</label>
    <textarea id="waMsg" name="message" rows="5" placeholder="Mesajınızı yazın…"><?=h($_POST['message'] ?? '')?></textarea>

    <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin-top:6px">
        <?=emoji_picker_html('waMsg', true)?>
        <label class="df-btn df-btn--secondary" style="cursor:pointer">
            <?=ds_icon('box',14)?> Ek
            <input type="file" name="attach" accept="image/*,video/*,audio/*,application/pdf,.doc,.docx,.xls,.xlsx" style="display:none" onchange="document.getElementById('waFileName').textContent=this.files[0]?this.files[0].name:''">
        </label>
        <span id="waFileName" class="muted" style="font-size:12px"></span>
    </div>

    <button type="submit" class="df-btn df-btn--primary df-btn--lg" style="width:100%;margin-top:10px"><?=ds_icon('send',16)?> Gönder</button>
</form>
</div>
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
<?php botx(); ?>
