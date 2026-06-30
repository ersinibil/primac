<?php
require_once __DIR__.'/boot.php';
require_login();

$pdo=db();
$error='';
$ok='';
$u=current_user();

if($_SERVER['REQUEST_METHOD']==='POST'){
    try{
        $st=$pdo->prepare("SELECT * FROM app_users WHERE id=?");
        $st->execute([$u['id']]);
        $dbu=$st->fetch();
        if(!$dbu) throw new Exception('Kullanıcı bulunamadı.');

        if(!password_verify($_POST['current_password'] ?? '', $dbu['password_hash'])){
            throw new Exception('Mevcut şifre hatalı.');
        }
        if(strlen($_POST['new_password'] ?? '')<6){
            throw new Exception('Yeni şifre en az 6 karakter olmalı.');
        }
        if($_POST['new_password'] !== $_POST['new_password2']){
            throw new Exception('Yeni şifreler uyuşmuyor.');
        }

        $pdo->prepare("UPDATE app_users SET password_hash=?, phone=?, email=? WHERE id=?")
            ->execute([password_hash($_POST['new_password'],PASSWORD_DEFAULT),trim($_POST['phone']),trim($_POST['email']),$u['id']]);

        $_SESSION['user']['name']=trim($_POST['full_name']) ?: $_SESSION['user']['name'];
        $pdo->prepare("UPDATE app_users SET full_name=? WHERE id=?")->execute([$_SESSION['user']['name'],$u['id']]);

        $ok='Profil ve şifre güncellendi.';
    }catch(Throwable $e){ $error=$e->getMessage(); }
}

$st=$pdo->prepare("SELECT * FROM app_users WHERE id=?");
$st->execute([$u['id']]);
$me=$st->fetch();

require_once __DIR__.'/layout_top.php';
?>

<div class="panel-head">
<h1>Profilim</h1>
<a class="btn secondary" href="dashboard.php">Ana Ekran</a>
</div>

<?php if($error): ?><div class="alert"><?=h($error)?></div><?php endif; ?>
<?php if($ok): ?><div class="ok"><?=h($ok)?></div><?php endif; ?>

<section class="panel">
<form method="post" class="form-grid">

<label>Ad Soyad
<input name="full_name" value="<?=h($me['full_name'])?>">
</label>

<label>Kullanıcı Adı
<input value="<?=h($me['username'])?>" disabled>
</label>

<label>Telefon
<input name="phone" value="<?=h($me['phone'])?>">
</label>

<label>E-posta
<input name="email" value="<?=h($me['email'])?>">
</label>

<label>Mevcut Şifre
<input type="password" name="current_password" required>
</label>

<label>Yeni Şifre
<input type="password" name="new_password" required>
</label>

<label>Yeni Şifre Tekrar
<input type="password" name="new_password2" required>
</label>

<button class="btn">Güncelle</button>
</form>
</section>

<?php require_once __DIR__.'/layout_bottom.php'; ?>
