<?php
require_once 'common.php';
if(!user_can('users')){ header('Location: index.php'); exit; }
require_once __DIR__.'/../share_lib.php';
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
                    $s = $media ? wa_send_media($ph,$media['url'],$media['type'],$message) : wa_send($ph,$message);
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
<?php if($manualBulkLinks): ?>
<div class="panel" style="background:rgba(220,38,38,.12);margin-bottom:10px">
    <p style="margin:0 0 8px"><?=htmlspecialchars($manualNote?:'API üzerinden gönderilemedi. Aşağıdaki kişileri tek tek elle açıp gönderebilirsiniz:')?></p>
    <?php foreach($manualBulkLinks as $mb): ?>
    <a href="<?=htmlspecialchars($mb['link'])?>" target="_blank" rel="noopener" class="btn" style="background:#16a34a;color:#fff;margin:0 6px 6px 0;display:inline-block">📲 <?=htmlspecialchars($mb['phone'])?></a>
    <?php endforeach; ?>
</div>
<?php endif; ?>
<?php if($ok): ?><div class="notice"><?=htmlspecialchars($ok)?></div><?php endif; ?>

<div class="panel">
<form method="post" enctype="multipart/form-data" onsubmit="return waConfirmSubmit()">
    <div style="display:flex;gap:16px;align-items:center;margin-bottom:8px">
        <label style="display:flex;align-items:center;gap:6px;margin:0"><input type="radio" name="send_mode" value="tekil" checked onchange="waToggleMode()" style="width:auto"> Tekil</label>
        <label style="display:flex;align-items:center;gap:6px;margin:0"><input type="radio" name="send_mode" value="toplu" onchange="waToggleMode()" style="width:auto"> Toplu Gönderim</label>
    </div>

    <div id="waTekilBlock">
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
    </div>

    <div id="waTopluBlock" style="display:none">
        <div style="display:flex;justify-content:space-between;align-items:center">
            <label style="color:#94a3b8;font-size:12px;margin:0">Personel</label>
            <button type="button" class="btn" style="background:rgba(37,99,235,.15);padding:6px 10px" onclick="waToggleAll('wa-bp',this)">Tümünü Kaldır</button>
        </div>
        <div style="max-height:160px;overflow:auto;border:1px solid rgba(255,255,255,.12);border-radius:10px;padding:8px;margin:6px 0 10px">
            <?php foreach($personnel as $p): ?>
            <label style="display:flex;align-items:center;gap:8px;padding:4px 0;margin:0">
                <input type="checkbox" class="wa-bp" name="bulk_personnel[]" value="<?=htmlspecialchars($p['phone'])?>" checked style="width:auto">
                <?=htmlspecialchars($p['name'])?> <span class="muted" style="font-size:12px"><?=htmlspecialchars($p['phone'])?></span>
            </label>
            <?php endforeach; ?>
            <?php if(!$personnel): ?><p class="muted" style="margin:4px 0">Telefonlu personel yok.</p><?php endif; ?>
        </div>

        <div style="display:flex;justify-content:space-between;align-items:center">
            <label style="color:#94a3b8;font-size:12px;margin:0">Cari</label>
            <button type="button" class="btn" style="background:rgba(37,99,235,.15);padding:6px 10px" onclick="waToggleAll('wa-bc',this)">Tümünü Kaldır</button>
        </div>
        <div style="max-height:160px;overflow:auto;border:1px solid rgba(255,255,255,.12);border-radius:10px;padding:8px;margin-top:6px">
            <?php foreach($contacts as $c): ?>
            <label style="display:flex;align-items:center;gap:8px;padding:4px 0;margin:0">
                <input type="checkbox" class="wa-bc" name="bulk_contacts[]" value="<?=htmlspecialchars($c['phone'])?>" checked style="width:auto">
                <?=htmlspecialchars($c['name'])?> <span class="muted" style="font-size:12px"><?=htmlspecialchars($c['phone'])?></span>
            </label>
            <?php endforeach; ?>
            <?php if(!$contacts): ?><p class="muted" style="margin:4px 0">Telefonlu cari yok.</p><?php endif; ?>
        </div>
    </div>

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
<script>
function waToggleMode(){
    var toplu = document.querySelector('input[name="send_mode"]:checked').value === 'toplu';
    document.getElementById('waTekilBlock').style.display = toplu ? 'none' : '';
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
<?php botx(); ?>
