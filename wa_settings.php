<?php
require_once __DIR__.'/boot.php';
require_once __DIR__.'/share_lib.php';
require_login();
if(!is_admin()) { header('Location: dashboard.php'); exit; }

$msg=''; $msg_type='';

// Kaydet
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['action']) && $_POST['action']==='save'){
    $enabled  = isset($_POST['wa_enabled']) ? '1' : '0';
    $provider = in_array($_POST['wa_provider'],['ultramsg','custom']) ? $_POST['wa_provider'] : 'ultramsg';
    $instance = trim($_POST['wa_instance'] ?? '');
    $token    = trim($_POST['wa_token'] ?? '');
    $wa_url   = trim($_POST['wa_url'] ?? '');

    set_setting('wa_enabled',  $enabled);
    set_setting('wa_provider', $provider);
    set_setting('wa_instance', $instance);
    set_setting('wa_token',    $token);
    set_setting('wa_url',      $wa_url);

    $msg='Ayarlar kaydedildi.'; $msg_type='ok';
}

// Test gönder
$test_result='';
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['action']) && $_POST['action']==='test'){
    $t_phone = trim($_POST['test_phone'] ?? '');
    $t_text  = trim($_POST['test_text']  ?? 'ACANS OTS — WhatsApp test mesajı.');
    if($t_phone){
        $ok = wa_send($t_phone, $t_text);
        $test_result = $ok ? 'ok:Mesaj gönderildi (API yanıtı alındı).' : 'err:Gönderilemedi — ayarları ve telefon numarasını kontrol edin.';
    } else {
        $test_result='err:Telefon numarası boş.';
    }
}

// Mevcut değerler
$s_enabled  = get_setting('wa_enabled','0');
$s_provider = get_setting('wa_provider','ultramsg');
$s_instance = get_setting('wa_instance','');
$s_token    = get_setting('wa_token','');
$s_url      = get_setting('wa_url','');

$page_title='WhatsApp Ayarları';
require_once __DIR__.'/layout_top.php';
?>

<h1>📱 WhatsApp Otomatik Gönderim Ayarları</h1>
<p class="muted">Günlük hatırlatıcılar ve bildirimler bu ayarlarla otomatik WhatsApp mesajı gönderir.</p>

<?php if($msg): ?>
<div class="<?=h($msg_type)?>"><?=h($msg)?></div>
<?php endif; ?>

<div class="panel" style="max-width:680px">
    <div class="panel-head"><h2>Bağlantı Ayarları</h2></div>
    <form method="post">
        <input type="hidden" name="action" value="save">
        <div class="form-grid">

            <label class="full" style="display:flex;align-items:center;gap:10px;cursor:pointer">
                <input type="checkbox" name="wa_enabled" value="1" <?=($s_enabled==='1'?'checked':'')?> style="width:18px;height:18px;margin-top:0">
                <span>WhatsApp Otomatik Gönderimi Aktif</span>
            </label>

            <label>Sağlayıcı
                <select name="wa_provider" id="wa_provider" onchange="toggleProvider()" style="width:100%;border:1px solid #d0d5dd;border-radius:12px;padding:11px;margin-top:6px;background:#fff">
                    <option value="ultramsg" <?=($s_provider==='ultramsg'?'selected':'')?>>UltraMsg (önerilen)</option>
                    <option value="custom"   <?=($s_provider==='custom'?'selected':'')?>>Özel / Custom Gateway</option>
                </select>
            </label>

            <label id="row_instance">Instance ID
                <input type="text" name="wa_instance" value="<?=h($s_instance)?>" placeholder="instance12345">
            </label>

            <label>Token / API Anahtarı
                <input type="text" name="wa_token" value="<?=h($s_token)?>" placeholder="UltraMsg veya gateway token'ı">
            </label>

            <label class="full" id="row_url" style="display:none">Özel Gateway URL
                <input type="url" name="wa_url" value="<?=h($s_url)?>" placeholder="https://api.example.com/send">
            </label>

        </div>
        <div style="margin-top:16px;padding-top:12px;border-top:1px solid #eef2f6">
            <p class="muted" style="margin:0 0 10px">
                <strong>UltraMsg kurulumu:</strong>
                <a href="https://ultramsg.com" target="_blank" rel="noopener">ultramsg.com</a>'dan hesap açın → Instance oluşturun → QR okutun →
                Instance ID ve Token'ı yukarıya yapıştırın.
            </p>
            <button type="submit" class="btn">💾 Kaydet</button>
        </div>
    </form>
</div>

<div class="panel" style="max-width:680px;margin-top:20px">
    <div class="panel-head"><h2>Test Mesajı Gönder</h2></div>

    <?php if($test_result): ?>
        <?php list($tr_type,$tr_msg)=explode(':',$test_result,2); ?>
        <div class="<?=($tr_type==='ok'?'ok':'alert')?>"><?=h($tr_msg)?></div>
    <?php endif; ?>

    <form method="post">
        <input type="hidden" name="action" value="test">
        <div class="form-grid">
            <label>Telefon Numarası
                <input type="text" name="test_phone" placeholder="05321234567 veya +905321234567" value="<?=h($_POST['test_phone']??'')?>">
            </label>
            <label>Mesaj
                <input type="text" name="test_text" value="<?=h($_POST['test_text']??'ACANS OTS — WhatsApp test mesajı.')?>" placeholder="Test mesajı">
            </label>
        </div>
        <div style="margin-top:14px">
            <button type="submit" class="btn secondary">📤 Gönder</button>
            <span class="muted" style="margin-left:10px;font-size:12px">Mevcut kaydedilmiş ayarlarla gönderir.</span>
        </div>
    </form>
</div>

<script>
function toggleProvider(){
    var p=document.getElementById('wa_provider').value;
    document.getElementById('row_instance').style.display=(p==='ultramsg'?'':'none');
    document.getElementById('row_url').style.display=(p==='custom'?'':'none');
}
toggleProvider();
</script>

<?php require_once __DIR__.'/layout_bottom.php'; ?>
