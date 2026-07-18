<?php
require_once 'common.php';
$pdo=db(); $me=(int)($_SESSION['user']['id'] ?? 0);
$ok=''; $er='';
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['save_profile'])){
    try{
        $fn=trim($_POST['full_name'] ?? '');
        $ph=trim($_POST['phone'] ?? '');
        $em=trim($_POST['email'] ?? '');
        $pdo->prepare("UPDATE app_users SET full_name=?,phone=?,email=? WHERE id=?")->execute([$fn,$ph,$em,$me]);
        if($fn) $_SESSION['user']['name']=$fn;
        $ok='Profil güncellendi.';
    }catch(Throwable $e){ $er=$e->getMessage(); }
}
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['change_pass'])){
    try{
        $cur=$_POST['cur_pass'] ?? '';
        $new=$_POST['new_pass'] ?? '';
        $new2=$_POST['new_pass2'] ?? '';
        $row=$pdo->prepare("SELECT password_hash FROM app_users WHERE id=? LIMIT 1");
        $row->execute([$me]);
        $dbu=$row->fetch();
        if(!$dbu || !password_verify($cur,$dbu['password_hash'])) throw new Exception('Mevcut şifre hatalı.');
        if(strlen($new)<6) throw new Exception('Şifre en az 6 karakter olmalı.');
        if($new!==$new2) throw new Exception('Şifreler uyuşmuyor.');
        $pdo->prepare("UPDATE app_users SET password_hash=? WHERE id=?")->execute([password_hash($new,PASSWORD_DEFAULT),$me]);
        $ok='Şifre güncellendi.';
    }catch(Throwable $e){ $er=$e->getMessage(); }
}
topx('Profilim');
$u=$pdo->prepare("SELECT * FROM app_users WHERE id=?"); $u->execute([$me]); $user=$u->fetch();
$ad=$user['full_name'] ?? ($_SESSION['user']['name'] ?? 'Kullanıcı');
// Kişisel istatistik (personel ise)
$pid=(int)($_SESSION['user']['personnel_id'] ?? 0);
$st=['is'=>0,'gorev'=>0];
if($pid){
  $st['is']=safe_count("SELECT COUNT(*) c FROM jobs WHERE responsible_personnel_id=$pid AND status NOT IN ('Tamamlandı','İptal','Teslim Edildi')");
  $st['gorev']=safe_count("SELECT COUNT(*) c FROM tasks WHERE personnel_id=$pid AND status NOT IN ('Tamamlandı','İptal')");
}
?>
<?php if($ok): ?><?=ds_alert('success',$ok)?><?php endif; ?>
<?php if($er): ?><?=ds_alert('danger',$er)?><?php endif; ?>
<div class="df-panel" style="text-align:center">
  <div style="width:72px;height:72px;border-radius:50%;background:#3b82f6;display:flex;align-items:center;justify-content:center;font-weight:900;font-size:30px;margin:0 auto 8px"><?=h(mb_strtoupper(mb_substr($ad,0,1)))?></div>
  <h2 style="margin:0"><?=h($ad)?></h2>
  <small class="muted"><?=h($user['username'] ?? '')?> · <?=$isAdmin?'Yönetici':'Personel'?></small>
</div>

<?php if($pid): ?>
<div style="display:flex;gap:8px;margin:10px 0;flex-wrap:wrap">
  <span class="df-badge df-badge--info"><?=ds_icon('briefcase',13)?> Açık iş: <b><?=$st['is']?></b></span>
  <span class="df-badge df-badge--success"><?=ds_icon('check',13)?> Açık görev: <b><?=$st['gorev']?></b></span>
</div>
<?php endif; ?>

<div class="df-panel">
  <b><?=ds_icon('user',16)?> Profil Bilgileri</b>
  <form method="post" style="margin-top:8px">
    <label style="color:#94a3b8;font-size:12px">Ad Soyad</label>
    <input name="full_name" value="<?=h($user['full_name'] ?? '')?>" placeholder="Ad Soyad">
    <label style="color:#94a3b8;font-size:12px">Telefon</label>
    <input name="phone" type="tel" value="<?=h($user['phone'] ?? '')?>" placeholder="Telefon">
    <label style="color:#94a3b8;font-size:12px">E-posta</label>
    <input name="email" type="email" value="<?=h($user['email'] ?? '')?>" placeholder="E-posta">
    <button class="df-btn df-btn--primary df-btn--lg" name="save_profile" value="1" style="width:100%"><?=ds_icon('check',16)?> Kaydet</button>
  </form>
</div>

<div class="df-panel">
  <b><?=ds_icon('settings',16)?> Şifre Değiştir</b>
  <form method="post" style="margin-top:8px">
    <input type="password" name="cur_pass" placeholder="Mevcut şifre" required>
    <input type="password" name="new_pass" placeholder="Yeni şifre (min 6 karakter)" minlength="6" required>
    <input type="password" name="new_pass2" placeholder="Yeni şifre tekrar" minlength="6" required>
    <button class="df-btn df-btn--primary df-btn--lg" name="change_pass" value="1" style="width:100%">Güncelle</button>
  </form>
</div>

<?php if($isAdmin): ?>
<a class="df-btn df-btn--secondary" style="width:100%;margin-bottom:10px" href="../dashboard.php?web=1"><?=ds_icon('home',16)?> Masaüstü Sürüme Geç</a>
<?php endif; ?>
<a class="df-btn df-btn--danger" style="width:100%" href="../logout.php"><?=ds_icon('logout',16)?> Çıkış Yap</a>

<?php botx(); ?>
