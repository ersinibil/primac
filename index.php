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
<style>
body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;background:linear-gradient(135deg,#071326,#10233f);font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Arial,sans-serif;color:#101828}
.login{width:420px;max-width:92vw;background:white;border-radius:28px;padding:30px;box-shadow:0 30px 80px rgba(0,0,0,.25)}
.logo{width:60px;height:60px;border-radius:20px;background:#071326;color:white;display:flex;align-items:center;justify-content:center;font-weight:900;font-size:24px;margin-bottom:16px}
h1{margin:0 0 8px;font-size:30px}.muted{color:#667085;margin-bottom:22px}
label{display:block;font-weight:900;margin:14px 0 6px}
input{width:100%;border:1px solid #d0d5dd;border-radius:14px;padding:13px;font-size:16px}
button{width:100%;border:0;border-radius:14px;background:#111827;color:white;padding:14px;margin-top:18px;font-weight:900;font-size:16px}
.alert{background:#fee2e2;color:#991b1b;padding:12px;border-radius:14px;margin:14px 0}
</style>
</head>
<body>
<div class="login">
<div class="logo" style="background:#fff;overflow:hidden;padding:6px"><img src="<?=h(brand_logo())?>" alt="Logo" style="width:100%;height:100%;object-fit:contain" onerror="this.parentNode.textContent='A'"></div>
<h1><?=h(app_config()['app_name'] ?? 'OTS')?></h1>
<div class="muted">Online Takip ve Yönetim Sistemi</div>
<?php if($error): ?><div class="alert"><?=h($error)?></div><?php endif; ?>
<form method="post">
<?=csrf_field()?>
<label>Kullanıcı Adı</label>
<input name="username" required autofocus placeholder="ersin">
<label>Şifre</label>
<input type="password" name="password" required placeholder="••••••">
<button>Giriş Yap</button>
</form>
<div style="text-align:center;margin-top:16px">
<a href="sifre_sifirla.php" style="color:#6b7280;font-size:13px;text-decoration:none">Şifremi Unuttum</a>
</div>
</div>
</body>
</html>