<?php
require_once __DIR__.'/boot.php';
require_permission('users');
require_once __DIR__.'/share_lib.php';

$pdo=db();
$error=''; $ok=''; $manualLink=''; $manualNote=''; $manualBulkLinks=[];

if($_SERVER['REQUEST_METHOD']==='POST'){
    $mode=($_POST['send_mode'] ?? 'tekil')==='toplu' ? 'toplu' : 'tekil';
    $message=trim($_POST['message'] ?? '');

    if($mode==='toplu'){
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
    } else {
        $phone=trim($_POST['phone'] ?? '');
        if(!$phone || (!$message && empty($_FILES['attach']['tmp_name']))){
            $error='Telefon zorunlu; mesaj veya dosya ekinden en az biri girilmeli.';
        } else {
            $media=null; $uploadErr='';
            if(!empty($_FILES['attach']['tmp_name'])){
                $media=wa_upload_media('attach',$uploadErr);
                if(!$media && $uploadErr) $error=$uploadErr;
            }
            if(!$error){
                $sent=wa_send_logged($phone,$message,'wa_send_now',$media['url']??null,$media['type']??null);
                if($sent){
                    $ok='Mesaj gönderildi.';
                } else {
                    $error='API üzerinden gönderilemedi (WhatsApp Ayarları\'nda gateway kurulu/etkin olmayabilir).';
                    if($media){
                        $manualLink=wa_link($message?:('📎 '.$media['name']),$phone);
                        $manualNote='Dosya WhatsApp linkiyle otomatik eklenemez — WhatsApp açıldıktan sonra "'.$media['name'].'" dosyasını (uploads/wa_send/ klasöründen) elle ekleyip gönder.';
                    } else {
                        $manualLink=wa_link($message,$phone);
                    }
                }
            }
        }
    }
}

$personnel=$pdo->query("SELECT name,phone FROM personnel WHERE COALESCE(active,1)=1 AND phone<>'' ORDER BY name")->fetchAll();
$contacts=$pdo->query("SELECT name,phone FROM contacts WHERE phone<>'' ORDER BY name")->fetchAll();

require_once __DIR__.'/layout_top.php';
?>
<div class="panel-head">
<h1>WhatsApp — Mesaj Gönder</h1>
</div>

<section class="panel" style="max-width:560px">
<?php if($error): ?><div class="alert"><?=h($error)?></div><?php endif; ?>
<?php if($manualLink): ?>
    <a href="<?=h($manualLink)?>" target="_blank" rel="noopener" class="btn" style="background:#16a34a;color:#fff;margin-bottom:6px;display:inline-flex">📲 WhatsApp'ta Aç ve Gönder</a>
    <?php if($manualNote): ?><p class="muted" style="font-size:13px;margin:0 0 14px"><?=h($manualNote)?></p><?php endif; ?>
<?php endif; ?>
<?php if($manualBulkLinks): ?>
    <div class="alert" style="margin-bottom:10px">
        <p style="margin:0 0 8px"><?=h($manualNote?:'API üzerinden gönderilemedi. Aşağıdaki kişileri tek tek elle açıp gönderebilirsiniz:')?></p>
        <?php foreach($manualBulkLinks as $mb): ?>
        <a href="<?=h($mb['link'])?>" target="_blank" rel="noopener" class="btn small" style="background:#16a34a;color:#fff;margin:0 6px 6px 0;display:inline-flex">📲 <?=h($mb['phone'])?></a>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
<?php if($ok): ?><div class="ok"><?=h($ok)?></div><?php endif; ?>

<form method="post" class="form-grid" enctype="multipart/form-data" onsubmit="return waConfirmSubmit()">

<div class="full" style="display:flex;gap:16px;align-items:center;margin-bottom:2px">
    <label style="display:flex;align-items:center;gap:6px;font-weight:700;margin:0"><input type="radio" name="send_mode" value="tekil" checked onchange="waToggleMode()" style="width:auto"> Tekil</label>
    <label style="display:flex;align-items:center;gap:6px;font-weight:700;margin:0"><input type="radio" name="send_mode" value="toplu" onchange="waToggleMode()" style="width:auto"> Toplu Gönderim</label>
</div>

<div id="waTekilBlock" class="full" style="display:contents">
<label class="full">Kime (listeden seç — opsiyonel)
<select name="phone_pick" onchange="document.getElementById('phone').value=this.value">
    <option value="">— Listeden seç (opsiyonel) —</option>
    <?php if($personnel): ?>
    <optgroup label="Personel">
        <?php foreach($personnel as $p): ?>
        <option value="<?=h($p['phone'])?>"><?=h($p['name'])?> — <?=h($p['phone'])?></option>
        <?php endforeach; ?>
    </optgroup>
    <?php endif; ?>
    <?php if($contacts): ?>
    <optgroup label="Cari">
        <?php foreach($contacts as $c): ?>
        <option value="<?=h($c['phone'])?>"><?=h($c['name'])?> — <?=h($c['phone'])?></option>
        <?php endforeach; ?>
    </optgroup>
    <?php endif; ?>
</select>
</label>

<label class="full">Telefon
<input type="text" id="phone" name="phone" placeholder="05XX XXX XX XX" value="<?=h($_POST['phone'] ?? $_GET['phone'] ?? '')?>" required>
</label>
</div>

<div id="waTopluBlock" class="full" style="display:none">
    <div style="display:flex;justify-content:space-between;align-items:center">
        <label style="font-weight:800;margin:0">Personel</label>
        <button type="button" class="btn secondary small" onclick="waToggleAll('wa-bp',this)">Tümünü Kaldır</button>
    </div>
    <div style="max-height:180px;overflow:auto;border:1px solid #e5e7eb;border-radius:10px;padding:8px;margin:6px 0 12px">
        <?php foreach($personnel as $p): ?>
        <label style="display:flex;align-items:center;gap:8px;padding:4px 0;font-weight:400">
            <input type="checkbox" class="wa-bp" name="bulk_personnel[]" value="<?=h($p['phone'])?>" checked style="width:auto">
            <?=h($p['name'])?> <span class="muted" style="font-size:12px"><?=h($p['phone'])?></span>
        </label>
        <?php endforeach; ?>
        <?php if(!$personnel): ?><p class="muted" style="margin:4px 0">Telefonlu personel yok.</p><?php endif; ?>
    </div>

    <div style="display:flex;justify-content:space-between;align-items:center">
        <label style="font-weight:800;margin:0">Cari</label>
        <button type="button" class="btn secondary small" onclick="waToggleAll('wa-bc',this)">Tümünü Kaldır</button>
    </div>
    <div style="max-height:180px;overflow:auto;border:1px solid #e5e7eb;border-radius:10px;padding:8px;margin-top:6px">
        <?php foreach($contacts as $c): ?>
        <label style="display:flex;align-items:center;gap:8px;padding:4px 0;font-weight:400">
            <input type="checkbox" class="wa-bc" name="bulk_contacts[]" value="<?=h($c['phone'])?>" checked style="width:auto">
            <?=h($c['name'])?> <span class="muted" style="font-size:12px"><?=h($c['phone'])?></span>
        </label>
        <?php endforeach; ?>
        <?php if(!$contacts): ?><p class="muted" style="margin:4px 0">Telefonlu cari yok.</p><?php endif; ?>
    </div>
</div>

<label class="full">Mesaj
<textarea id="waMsg" name="message" rows="5" placeholder="Mesajınızı yazın…"><?=h($_POST['message'] ?? '')?></textarea>
</label>

<div class="full" style="display:flex;gap:8px;align-items:center;margin-top:-8px">
    <?=emoji_picker_html('waMsg')?>
    <label class="btn secondary small" style="margin:0;cursor:pointer">
        📎 Dosya/Video/Ses Ekle
        <input type="file" name="attach" accept="image/*,video/*,audio/*,application/pdf,.doc,.docx,.xls,.xlsx" style="display:none" onchange="document.getElementById('waFileName').textContent=this.files[0]?this.files[0].name:''">
    </label>
    <span id="waFileName" class="muted" style="font-size:13px"></span>
</div>

<div class="full">
<button type="submit" class="btn dark">📤 Gönder</button>
</div>
</form>
</section>

<script>
function waToggleMode(){
    var toplu = document.querySelector('input[name="send_mode"]:checked').value === 'toplu';
    document.getElementById('waTekilBlock').style.display = toplu ? 'none' : 'contents';
    document.getElementById('waTopluBlock').style.display = toplu ? '' : 'none';
    document.getElementById('phone').required = !toplu;
}
function waToggleAll(cls, btn){
    var boxes = document.querySelectorAll('.'+cls);
    var anyChecked = Array.prototype.some.call(boxes, function(b){ return b.checked; });
    boxes.forEach(function(b){ b.checked = !anyChecked; });
    btn.textContent = anyChecked ? 'Tümünü Seç' : 'Tümünü Kaldır';
}
function waConfirmSubmit(){
    var toplu = document.querySelector('input[name="send_mode"]:checked').value === 'toplu';
    if(!toplu) return true;
    var n = document.querySelectorAll('.wa-bp:checked, .wa-bc:checked').length;
    return confirm('Bu mesaj '+n+' kişiye WhatsApp üzerinden gönderilecek. Emin misiniz?');
}
</script>
