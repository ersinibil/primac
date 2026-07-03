<?php
require_once 'common.php';
if(!user_can('users')){ header('Location: index.php'); exit; }
require_once __DIR__.'/../share_lib.php';
$pdo=db();
$error=''; $ok=''; $manualLink='';

if($_SERVER['REQUEST_METHOD']==='POST'){
    $phone=trim($_POST['phone'] ?? '');
    $message=trim($_POST['message'] ?? '');
    if(!$phone || !$message){
        $error='Telefon ve mesaj zorunlu.';
    } else {
        $sent=wa_send($phone,$message);
        if($sent){
            $ok='Mesaj gönderildi.';
        } else {
            $error='API üzerinden gönderilemedi (WhatsApp Ayarları\'nda gateway kurulu/etkin olmayabilir). Aşağıdaki linkle manuel gönderebilirsin.';
            $manualLink=wa_link($message,$phone);
        }
    }
}

$personnel=$pdo->query("SELECT name,phone FROM personnel WHERE COALESCE(active,1)=1 AND phone<>'' ORDER BY name")->fetchAll();
$contacts=$pdo->query("SELECT name,phone FROM contacts WHERE phone<>'' ORDER BY name")->fetchAll();

topx('WhatsApp Gönder');
?>
<?php if($error): ?><div class="err"><?=htmlspecialchars($error)?></div><?php endif; ?>
<?php if($manualLink): ?>
<a href="<?=htmlspecialchars($manualLink)?>" target="_blank" rel="noopener" class="btn dark" style="width:100%;padding:13px;background:#16a34a;color:#fff;margin-bottom:10px;text-align:center;display:block">📲 WhatsApp'ta Aç ve Gönder</a>
<?php endif; ?>
<?php if($ok): ?><div class="notice"><?=htmlspecialchars($ok)?></div><?php endif; ?>

<div class="panel">
<form method="post">
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
    <textarea name="message" rows="5" placeholder="Mesajınızı yazın…" required><?=htmlspecialchars($_POST['message'] ?? '')?></textarea>

    <button type="submit" class="btn dark" style="width:100%;padding:13px;margin-top:8px">📤 Gönder</button>
</form>
</div>
<?php botx(); ?>
