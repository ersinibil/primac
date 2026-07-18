<?php
require_once __DIR__.'/boot.php';
require_once __DIR__.'/share_lib.php';

$pdo=db();
$error='';

// SECURITY SPRINT-005 FAZ-3: login brute-force/rate-limit (IP+kullanıcı adı bazında, dost mesaj —
// bkz. share_lib.php rate_limit_*()). sifre_sifirla.php'nin reset_ratelimit.json'ıyla karışmaz.
define('LOGIN_RL_FILE', __DIR__.'/login_ratelimit.json');
define('LOGIN_RL_MAX_HITS', 8);
define('LOGIN_RL_WINDOW', 600); // 10 dakika

if(!empty($_SESSION['user'])){
    redirect('dashboard.php');
}

if($_SERVER['REQUEST_METHOD']==='POST'){
    try{
        // SECURITY SPRINT-005 FAZ-2: login CSRF koruması. layout_top.php'den geçmediği için
        // otomatik enforced-liste/csrf_verify() (403 sayfası) YERİNE, mevcut try/catch akışına
        // gömülü bu kontrol kullanılıyor — başarısızsa kullanıcı normal login ekranında,
        // dost bir hata mesajıyla kalıyor (aşağıdaki catch bloğu zaten bunu yapıyor).
        $csrfToken = $_POST['csrf_token'] ?? '';
        if(empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], (string)$csrfToken)){
            throw new Exception('Güvenlik doğrulaması başarısız. Lütfen sayfayı yenileyip tekrar deneyin.');
        }

        $username=trim($_POST['username'] ?? '');
        $password=$_POST['password'] ?? '';

        // SECURITY SPRINT-005 FAZ-3: limitteyse şifre doğru olsa bile kimlik doğrulamaya geçilmez.
        $__rlKey = ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0').'|'.strtolower($username);
        if(rate_limit_blocked(LOGIN_RL_FILE, $__rlKey, LOGIN_RL_MAX_HITS, LOGIN_RL_WINDOW)){
            throw new Exception('Çok fazla başarısız giriş denemesi algılandı. Lütfen birkaç dakika sonra tekrar deneyin.');
        }

        $st=$pdo->prepare("SELECT * FROM app_users WHERE username=? AND active=1 LIMIT 1");
        $st->execute([$username]);
        $u=$st->fetch();

        if(!$u || !password_verify($password,$u['password_hash'])){
            rate_limit_hit(LOGIN_RL_FILE, $__rlKey, LOGIN_RL_WINDOW);
            throw new Exception('Kullanıcı adı veya şifre hatalı.');
        }

        rate_limit_clear(LOGIN_RL_FILE, $__rlKey);

        $perms=json_decode($u['permissions'] ?? '[]',true);
        if(!is_array($perms)) $perms=[];

        $_SESSION['user']=[
            'id'=>$u['id'],
            'name'=>$u['full_name'],
            'username'=>$u['username'],
            'role'=>$u['role'],
            'personnel_id'=>$u['personnel_id'],
            'permissions'=>$perms,
            'is_admin'=>$u['role']==='admin'
        ];
        // SECURITY SPRINT-005 FAZ-1: kimlik doğrulandıktan hemen sonra session id yenilenir
        // (session fixation koruması) — session verisi korunur, sadece id değişir.
        session_regenerate_id(true);

        $pdo->prepare("UPDATE app_users SET last_login=NOW() WHERE id=?")->execute([$u['id']]);
        // "Beni hatırla" — oturum atsa bile çerezle otomatik giriş (bildirim için tekrar şifre sormaz)
        if(function_exists('remember_set')) remember_set($u['id']);
        // Mobil'den geldiyse oraya geri dön; yoksa masaüstü panel
        $dest='dashboard.php';
        if(!empty($_SESSION['return_to'])){ $dest=$_SESSION['return_to']; unset($_SESSION['return_to']); }
        redirect($dest);
    }catch(Throwable $e){
        $error=$e->getMessage();
    }
}
?>
<!doctype html>
<html lang="tr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?=h(app_config()['app_name'] ?? 'OTS')?> — Giriş</title>
<?php
$__favicon = brand_icon();
$__faviconExt = strtolower(pathinfo($__favicon, PATHINFO_EXTENSION));
$__faviconType = ['png'=>'image/png','jpg'=>'image/jpeg','jpeg'=>'image/jpeg','webp'=>'image/webp','gif'=>'image/gif'][$__faviconExt] ?? 'image/png';
?>
<link rel="icon" type="<?=$__faviconType?>" href="<?=h($__favicon)?>?v=<?=@filemtime(__DIR__.'/'.$__favicon)?>">
<link rel="apple-touch-icon" href="<?=h($__favicon)?>?v=<?=@filemtime(__DIR__.'/'.$__favicon)?>">
<?php ds_styles(); ?>
<style>
/* Giriş ekranı — oturum yok, layout_top.php'nin Rail/Topbar'ı hiç yüklenmiyor, ama TÜM renkler
   DS token'larından (var(--df-*)) geliyor (P0 Legacy UI Temizliği, 2026-07-18). */
body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;background:linear-gradient(135deg,#071326,#10233f);font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Arial,sans-serif;color:var(--df-ink-900)}
.login{width:420px;max-width:92vw;background:var(--df-surface);border-radius:var(--df-radius-lg);padding:30px;box-shadow:0 30px 80px rgba(0,0,0,.25)}
.login .logo{width:60px;height:60px;border-radius:var(--df-radius-md);background:#fff;overflow:hidden;padding:6px;display:flex;align-items:center;justify-content:center;font-weight:900;font-size:24px;margin-bottom:16px}
.login h1{margin:0 0 8px;font-size:30px}
.login label{display:block;font-weight:700;margin:14px 0 6px;font-size:13px;color:var(--df-ink-900)}
.login input{width:100%;border:1px solid var(--df-hairline);border-radius:var(--df-radius-md);padding:13px;font-size:16px;background:var(--df-surface);color:var(--df-ink-900);box-sizing:border-box}
/* .df-alert/.df-muted'in yapısal CSS'i ds-foundation.css'te body.nav-compact/mob-compact scope'una
   bağlı — bu sayfada (oturum yok, app shell hiç yüklenmiyor) o class hiç yok, o yüzden aynı görünüm
   burada bağımsız tanımlanıyor (renk token'ları aynı, sadece scope farklı). */
.login .df-alert{display:flex;padding:var(--df-space-3) var(--df-space-4);border-radius:var(--df-radius-md);font-size:14px;line-height:1.5;margin:14px 0}
.login .df-alert--danger{background:var(--df-danger-soft);color:var(--df-danger-ink)}
.login .df-muted{color:var(--df-ink-500)}
</style>
</head>
<body>
<div class="login">
<div class="logo"><img src="<?=h(brand_logo())?>" alt="Logo" style="width:100%;height:100%;object-fit:contain" onerror="this.parentNode.textContent='A'"></div>
<h1><?=h(app_config()['app_name'] ?? 'OTS')?></h1>
<div class="df-muted" style="margin-bottom:22px">Online Takip ve Yönetim Sistemi</div>
<?php if($error): ?><?=ds_alert('danger',$error)?><?php endif; ?>
<form method="post">
<?=csrf_field()?>
<label>Kullanıcı Adı</label>
<input name="username" required autofocus placeholder="ersin">
<label>Şifre</label>
<input type="password" name="password" required placeholder="••••••">
<button class="df-btn df-btn--primary df-btn--lg" style="width:100%;margin-top:18px">Giriş Yap</button>
</form>
<div style="text-align:center;margin-top:16px">
<a href="sifre_sifirla.php" style="color:var(--df-ink-500);font-size:13px;text-decoration:none">Şifremi Unuttum</a>
</div>
</div>
</body>
</html>