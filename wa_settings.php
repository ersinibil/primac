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

// Gelen mesaj webhook anahtarı — sabit/koddaki bir anahtar yerine (bkz. KNOWN_BUGS.md "sabit
// migration/temizlik anahtarı" notu) DB'de saklanan, ilk kullanımda otomatik üretilen rastgele bir
// anahtar. wa_webhook.php bu anahtarı ?key= ile bekler.
if(!get_setting('wa_webhook_key','')){
    set_setting('wa_webhook_key', bin2hex(random_bytes(16)));
}
if($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='regen_webhook_key'){
    set_setting('wa_webhook_key', bin2hex(random_bytes(16)));
    $msg='Webhook anahtarı yenilendi — UltraMsg panelindeki webhook URL\'ini güncellemeyi unutmayın.'; $msg_type='ok';
}

// Test gönder
$test_result='';
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['action']) && $_POST['action']==='test'){
    $t_phone = trim($_POST['test_phone'] ?? '');
    $t_text  = trim($_POST['test_text']  ?? ((app_config()['app_name'] ?? 'OTS').' — WhatsApp test mesajı.'));
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
$s_webhook_key = get_setting('wa_webhook_key','');
$webhook_url   = base_url().'wa_webhook.php?key='.$s_webhook_key;

$page_title='WhatsApp Ayarları';
require_once __DIR__.'/layout_top.php';
ds_page_header('WhatsApp Otomatik Gönderim Ayarları', ds_icon('settings',24), 'Günlük hatırlatıcılar ve bildirimler bu ayarlarla otomatik WhatsApp mesajı gönderir.', ds_button('💬 Konuşmalar','wa_conversations.php','secondary','','',true), false, true);
?>

<?php if($msg): ?>
<?=ds_alert($msg_type==='ok'?'success':'danger',$msg)?>
<?php endif; ?>

<section class="df-card" style="max-width:680px;margin-top:var(--df-space-4)">
    <h2 class="df-section-title">Bağlantı Ayarları</h2>
    <form method="post">
        <input type="hidden" name="action" value="save">
        <div class="df-form-grid-2">

            <div class="df-form-span-2">
            <label style="display:flex;align-items:center;gap:10px;cursor:pointer;font-size:var(--df-type-body-size);color:var(--df-ink-900)">
                <input type="checkbox" name="wa_enabled" value="1" <?=($s_enabled==='1'?'checked':'')?> style="width:18px;height:18px;margin-top:0">
                <span>WhatsApp Otomatik Gönderimi Aktif</span>
            </label>
            </div>

            <?php ds_form_field('Sağlayıcı', '<select name="wa_provider" id="wa_provider" onchange="toggleProvider()">
                <option value="ultramsg" '.($s_provider==='ultramsg'?'selected':'').'>UltraMsg (önerilen)</option>
                <option value="custom" '.($s_provider==='custom'?'selected':'').'>Özel / Custom Gateway</option>
            </select>'); ?>

            <div id="row_instance"><?php ds_form_field('Instance ID', '<input type="text" name="wa_instance" value="'.h($s_instance).'" placeholder="instance12345">'); ?></div>

            <?php ds_form_field('Token / API Anahtarı', '<input type="text" name="wa_token" value="'.h($s_token).'" placeholder="UltraMsg veya gateway token\'ı">'); ?>

            <div class="df-form-span-2" id="row_url" style="display:none"><?php ds_form_field('Özel Gateway URL', '<input type="url" name="wa_url" value="'.h($s_url).'" placeholder="https://api.example.com/send">'); ?></div>

        </div>
        <div style="margin-top:var(--df-space-4);padding-top:var(--df-space-3);border-top:1px solid var(--df-hairline)">
            <p class="df-muted" style="margin:0 0 10px">
                <strong>UltraMsg kurulumu:</strong>
                <a href="https://ultramsg.com" target="_blank" rel="noopener">ultramsg.com</a>'dan hesap açın → Instance oluşturun → QR okutun →
                Instance ID ve Token'ı yukarıya yapıştırın.
            </p>
            <button type="submit" class="df-btn df-btn--primary"><?=ds_icon('check',16)?> Kaydet</button>
        </div>
    </form>
</section>

<section class="df-card" style="max-width:680px;margin-top:var(--df-space-4)">
    <h2 class="df-section-title">Gelen Mesaj Webhook'u</h2>
    <p class="df-muted" style="margin:0 0 10px">
        Karşı taraftan gelen WhatsApp cevaplarının sisteme düşmesi için bu URL'i UltraMsg panelinde
        (Instance → Settings → Webhook URL) tanımlayın. Anahtar bu sistemde üretildi, kimseyle
        paylaşmayın — sızarsa hemen yenileyin.
    </p>
    <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
        <input type="text" readonly value="<?=h($webhook_url)?>" onclick="this.select()" style="flex:1;min-width:280px;border:1px solid var(--df-hairline);border-radius:var(--df-radius-sm);padding:11px;background:var(--df-surface-sunken);font-family:monospace;font-size:13px">
        <form method="post" onsubmit="return confirm('Anahtar yenilenirse UltraMsg panelindeki eski webhook URL çalışmaz hale gelir. Emin misiniz?')">
            <input type="hidden" name="action" value="regen_webhook_key">
            <button type="submit" class="df-btn df-btn--secondary df-btn--sm"><?=ds_icon('edit',14)?> Anahtarı Yenile</button>
        </form>
    </div>
</section>

<section class="df-card" style="max-width:680px;margin-top:var(--df-space-4)">
    <h2 class="df-section-title">Test Mesajı Gönder</h2>

    <?php if($test_result): ?>
        <?php list($tr_type,$tr_msg)=explode(':',$test_result,2); ?>
        <?=ds_alert($tr_type==='ok'?'success':'danger',$tr_msg)?>
    <?php endif; ?>

    <form method="post">
        <input type="hidden" name="action" value="test">
        <div class="df-form-grid-2">
            <?php ds_form_field('Telefon Numarası', '<input type="text" name="test_phone" placeholder="05321234567 veya +905321234567" value="'.h($_POST['test_phone']??'').'">'); ?>
            <?php ds_form_field('Mesaj', '<input type="text" name="test_text" value="'.h($_POST['test_text']??((app_config()['app_name']??'OTS').' — WhatsApp test mesajı.')).'" placeholder="Test mesajı">'); ?>
        </div>
        <div style="margin-top:var(--df-space-3)">
            <button type="submit" class="df-btn df-btn--secondary"><?=ds_icon('send',16)?> Gönder</button>
            <span class="df-muted" style="margin-left:10px;font-size:12px">Mevcut kaydedilmiş ayarlarla gönderir.</span>
        </div>
    </form>
</section>

<style>
body.nav-compact .df-form-grid-2{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:0 var(--df-space-4)}
body.nav-compact .df-form-span-2{grid-column:1 / -1}
body.nav-compact .df-section-title{font-size:var(--df-type-section-size);font-weight:var(--df-type-section-weight);color:var(--df-ink-900);margin:0 0 var(--df-space-3)}
@media(max-width:640px){body.nav-compact .df-form-grid-2{grid-template-columns:1fr}}
</style>

<script>
function toggleProvider(){
    var p=document.getElementById('wa_provider').value;
    document.getElementById('row_instance').style.display=(p==='ultramsg'?'':'none');
    document.getElementById('row_url').style.display=(p==='custom'?'':'none');
}
toggleProvider();
</script>

<?php require_once __DIR__.'/layout_bottom.php'; ?>
