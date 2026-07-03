<?php
require_once 'common.php';
if(!user_can('users')){ header('Location: index.php'); exit; }
require_once __DIR__.'/../share_lib.php';
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
                    $manualNote='Dosya linkle otomatik eklenemez — WhatsApp açıldıktan sonra "'.$media['name'].'" dosyasını elle ekleyip gönder.';
                } else {
                    $manualLink=wa_link($message,$phone);
                }
            }
        }
    }
}

$personnel=$pdo->query("SELECT name,phone FROM personnel WHERE COALESCE(active,1)=1 AND phone<>'' ORDER BY name")->fetchAll();
$contacts=$pdo->query("SELECT name,phone FROM contacts WHERE phone<>'' ORDER BY name")->fetchAll();

topx('WhatsApp Gönder');
?>
<?php if($error): ?><div class="err"><?=htmlspecialchars($error)?></div><?php endif; ?>
<?php if($manualLink): ?>
<a href="<?=htmlspecialchars($manualLink)?>" target="_blank" rel="noopener" class="btn dark" style="width:100%;padding:13px;background:#16a34a;color:#fff;margin-bottom:6px;text-align:center;display:block">📲 WhatsApp'ta Aç ve Gönder</a>
<?php if($manualNote): ?><p class="muted" style="font-size:13px;margin:0 0 10px"><?=htmlspecialchars($manualNote)?></p><?php endif; ?>
<?php endif; ?>
<?php if($ok): ?><div class="notice"><?=htmlspecialchars($ok)?></div><?php endif; ?>

<div class="panel">
<form method="post" enctype="multipart/form-data">
    <label style="color:#94a3b8;font-size:12px">Kime (listeden seç — opsiyonel)</label>
    <select onchange="document.getElementById('phone').value=this.value">
        <option value="">— Listeden seç (opsiyonel) —</option>
        <?php if($personnel): ?>
        <optgroup label="Personel">
            <?php foreach($personnel as $p): ?>
            <option value="<?=htmlspecialchars($p['phone'])?>"><?=htmlspecialchars($p['name'])?> — <?=htmlspecialchars($p['phone'])?></option>
            <?php endforeach; ?>
        </optgroup>
        <?php endif; ?>
        <?php if($contacts): ?>
        <optgroup label="Cari">
            <?php foreach($contacts as $c): ?>
            <option value="<?=htmlspecialchars($c['phone'])?>"><?=htmlspecialchars($c['name'])?> — <?=htmlspecialchars($c['phone'])?></option>
            <?php endforeach; ?>
        </optgroup>
        <?php endif; ?>
    </select>

    <label style="color:#94a3b8;font-size:12px">Telefon</label>
    <input type="text" id="phone" name="phone" placeholder="05XX XXX XX XX" value="<?=htmlspecialchars($_POST['phone'] ?? '')?>" required>

    <label style="color:#94a3b8;font-size:12px">Mesaj</label>
    <textarea id="waMsg" name="message" rows="5" placeholder="Mesajınızı yazın…"><?=htmlspecialchars($_POST['message'] ?? '')?></textarea>

    <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin-top:6px">
        <?=emoji_picker_html('waMsg', true)?>
        <label class="btn" style="margin:0;cursor:pointer;background:rgba(37,99,235,.15)">
            📎 Ek
            <input type="file" name="attach" accept="image/*,video/*,audio/*,application/pdf,.doc,.docx,.xls,.xlsx" style="display:none" onchange="document.getElementById('waFileName').textContent=this.files[0]?this.files[0].name:''">
        </label>
        <span id="waFileName" class="muted" style="font-size:12px"></span>
    </div>

    <button type="submit" class="btn dark" style="width:100%;padding:13px;margin-top:10px">📤 Gönder</button>
</form>
</div>
<?php botx(); ?>
