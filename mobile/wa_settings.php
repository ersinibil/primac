<?php
require_once 'common.php';
if(!$isAdmin){ header('Location: index.php'); exit; }
require_once __DIR__.'/../share_lib.php';

$msg=''; $msg_type='';

if($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action'] ?? '')==='save'){
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

    $msg='Ayarlar kaydedildi.'; $msg_type='notice';
}

$test_result='';
if($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action'] ?? '')==='test'){
    $t_phone = trim($_POST['test_phone'] ?? '');
    $t_text  = trim($_POST['test_text']  ?? ((app_config()['app_name'] ?? 'OTS').' — WhatsApp test mesajı.'));
    if($t_phone){
        $sent = wa_send($t_phone, $t_text);
        $test_result = $sent ? 'ok:Mesaj gönderildi (API yanıtı alındı).' : 'err:Gönderilemedi — ayarları ve telefon numarasını kontrol edin.';
    } else {
        $test_result='err:Telefon numarası boş.';
    }
}

$s_enabled  = get_setting('wa_enabled','0');
$s_provider = get_setting('wa_provider','ultramsg');
$s_instance = get_setting('wa_instance','');
$s_token    = get_setting('wa_token','');
$s_url      = get_setting('wa_url','');

topx('WhatsApp Ayarları');
?>
<p class="small">Günlük hatırlatıcılar ve bildirimler bu ayarlarla otomatik WhatsApp mesajı gönderir.</p>

<?php if($msg): ?><?=ds_alert($msg_type==='notice'?'success':'info',$msg)?><?php endif; ?>

<div class="df-panel">
  <b><?=ds_icon('settings',16)?> Bağlantı Ayarları</b>
  <form method="post" style="margin-top:10px">
    <input type="hidden" name="action" value="save">

    <label style="display:flex;align-items:center;gap:10px;margin:8px 0">
      <input type="checkbox" name="wa_enabled" value="1" <?=($s_enabled==='1'?'checked':'')?> style="width:auto">
      <span>WhatsApp Otomatik Gönderimi Aktif</span>
    </label>

    <label style="color:var(--df-ink-500,#94a3b8);font-size:12px">Sağlayıcı</label>
    <select name="wa_provider" id="wa_provider" onchange="toggleProvider()">
        <option value="ultramsg" <?=($s_provider==='ultramsg'?'selected':'')?>>UltraMsg (önerilen)</option>
        <option value="custom"   <?=($s_provider==='custom'?'selected':'')?>>Özel / Custom Gateway</option>
    </select>

    <div id="row_instance">
      <label style="color:var(--df-ink-500,#94a3b8);font-size:12px">Instance ID</label>
      <input type="text" name="wa_instance" value="<?=h($s_instance)?>" placeholder="instance12345">
    </div>

    <label style="color:var(--df-ink-500,#94a3b8);font-size:12px">Token / API Anahtarı</label>
    <input type="text" name="wa_token" value="<?=h($s_token)?>" placeholder="UltraMsg veya gateway token'ı">

    <div id="row_url" style="display:none">
      <label style="color:var(--df-ink-500,#94a3b8);font-size:12px">Özel Gateway URL</label>
      <input type="url" name="wa_url" value="<?=h($s_url)?>" placeholder="https://api.example.com/send">
    </div>

    <p class="small" style="margin:10px 0">
      <b>UltraMsg kurulumu:</b> <a href="https://ultramsg.com" target="_blank" rel="noopener" style="color:#93c5fd">ultramsg.com</a>'dan hesap açın → Instance oluşturun → QR okutun → Instance ID ve Token'ı yukarıya yapıştırın.
    </p>
    <button type="submit" class="df-btn df-btn--primary df-btn--lg" style="width:100%"><?=ds_icon('check',16)?> Kaydet</button>
  </form>
</div>

<div class="df-panel">
  <b><?=ds_icon('send',16)?> Test Mesajı Gönder</b>
  <?php if($test_result): list($tr_type,$tr_msg)=explode(':',$test_result,2); ?>
  <div style="margin-top:8px"><?=ds_alert($tr_type==='ok'?'success':'danger',$tr_msg)?></div>
  <?php endif; ?>
  <form method="post" style="margin-top:10px">
    <input type="hidden" name="action" value="test">
    <label style="color:var(--df-ink-500,#94a3b8);font-size:12px">Telefon Numarası</label>
    <input type="text" name="test_phone" placeholder="05321234567" value="<?=h($_POST['test_phone']??'')?>">
    <label style="color:var(--df-ink-500,#94a3b8);font-size:12px">Mesaj</label>
    <input type="text" name="test_text" value="<?=h($_POST['test_text']??((app_config()['app_name']??'OTS').' — WhatsApp test mesajı.'))?>">
    <button type="submit" class="df-btn df-btn--primary" style="width:100%;margin-top:8px"><?=ds_icon('send',16)?> Gönder</button>
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
<?php botx(); ?>
