<?php
require_once __DIR__.'/boot.php';
require_login();

$pdo=db();
$error='';
$ok='';
$u=current_user();

// NAV-001B (2026-07-16) — "Yeni sade navigasyonu dene" / "Eski geniş menüye dön" tercihi.
// PRG deseni (CLAUDE.md kural 3): önce işle, sonra redirect — layout_top.php'ye (compact/legacy
// hesaplaması orada) hiç girmeden.
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['nav_mode_toggle'])){
    $__newMode = $_POST['nav_mode_toggle']==='compact' ? 'compact' : 'legacy';
    user_pref_set($pdo, $u['id'], 'nav_layout_mode', $__newMode);
    header('Location: profile.php?nav_ok=1'); exit;
}

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

<?php ds_page_header('Profilim', ds_icon('user',24), '', ds_button('Ana Ekran','dashboard.php','secondary','','',true), false, true); ?>

<?php if($error): ?><?=ds_alert('danger',$error)?><?php endif; ?>
<?php if($ok): ?><?=ds_alert('success',$ok)?><?php endif; ?>
<?php if(!empty($_GET['nav_ok'])): ?><?=ds_alert('success','Navigasyon tercihiniz kaydedildi.')?><?php endif; ?>

<section class="df-card">
<form method="post" class="df-form-grid-2">

<?php ds_form_field('Ad Soyad', '<input name="full_name" value="'.h($me['full_name']).'">'); ?>
<?php ds_form_field('Kullanıcı Adı', '<input value="'.h($me['username']).'" disabled>'); ?>
<?php ds_form_field('Telefon', '<input name="phone" value="'.h($me['phone']).'">'); ?>
<?php ds_form_field('E-posta', '<input name="email" value="'.h($me['email']).'">'); ?>
<?php ds_form_field('Mevcut Şifre', '<input type="password" name="current_password" required>'); ?>
<?php ds_form_field('Yeni Şifre', '<input type="password" name="new_password" required>'); ?>
<?php ds_form_field('Yeni Şifre Tekrar', '<input type="password" name="new_password2" required>'); ?>

<div class="df-form-span-2"><button class="df-btn df-btn--primary">Güncelle</button></div>
</form>
</section>

<style>
body.nav-compact .df-form-grid-2{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:0 var(--df-space-4)}
body.nav-compact .df-form-span-2{grid-column:1 / -1}
@media(max-width:640px){body.nav-compact .df-form-grid-2{grid-template-columns:1fr}}
</style>

<?php require_once __DIR__.'/layout_bottom.php'; ?>
