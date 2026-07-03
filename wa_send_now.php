<?php
require_once __DIR__.'/boot.php';
require_permission('users');
require_once __DIR__.'/share_lib.php';

$pdo=db();
$error=''; $ok=''; $manualLink=''; $manualNote='';

if($_SERVER['REQUEST_METHOD']==='POST'){
    $phone=trim($_POST['phone'] ?? '');
    $message=trim($_POST['message'] ?? '');
    if(!$phone || (!$message && empty($_FILES['attach']['tmp_name']))){
        $error='Telefon zorunlu; mesaj veya dosya ekinden en az biri girilmeli.';
    } else {
        $media=null; $uploadErr='';
        if(!empty($_FILES['attach']['tmp_name'])){
            $media=wa_upload_media('attach',$uploadErr);
            if(!$media && $uploadErr) $error=$uploadErr;
        }
        if(!$error){
            if($media){
                $sent=wa_send_media($phone,$media['url'],$media['type'],$message);
            } else {
                $sent=wa_send($phone,$message);
            }
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
<?php if($ok): ?><div class="ok"><?=h($ok)?></div><?php endif; ?>

<form method="post" class="form-grid" enctype="multipart/form-data">

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
<input type="text" id="phone" name="phone" placeholder="05XX XXX XX XX" value="<?=h($_POST['phone'] ?? '')?>" required>
</label>

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
