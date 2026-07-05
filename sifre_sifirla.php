<?php
/**
 * OTS — Şifremi Unuttum / Şifre Sıfırlama
 * Giriş gerektirmez. Adım 1: kimlik → kod gönder. Adım 2: kod + yeni şifre.
 * PHP 7.2 uyumlu.
 */
require_once __DIR__.'/boot.php';
require_once __DIR__.'/share_lib.php';

if(!empty($_SESSION['user'])) redirect('dashboard.php');

$pdo  = db();
$step = 1;          // 1=kimlik gir, 2=kod+şifre
$err  = '';
$ok   = '';

// SECURITY SPRINT-003 (2026-07-05): brute-force + hedef seçimi kısıtsızlığı sertleştirmesi.
define('RESET_TOKEN_TTL',     10*60); // kod geçerlilik süresi (önceden 30 dk idi)
define('RESET_MAX_ATTEMPTS',  5);     // yanlış kod denemesi üst sınırı → aşılınca token iptal
define('RESET_RESEND_WINDOW', 60);    // aynı hesaba tekrar kod gönderiminde min. bekleme (sn)
define('RESET_RL_FILE',       __DIR__.'/reset_ratelimit.json');

// ---- Yardımcı ---------------------------------------------------------------

function _find_user($pdo, $identity){
    // kullanıcı adı, e-posta veya telefon
    $st = $pdo->prepare(
        "SELECT * FROM app_users
         WHERE active=1 AND (username=? OR email=? OR phone=?)
         LIMIT 1"
    );
    $st->execute([$identity, $identity, $identity]);
    return $st->fetch();
}

function _generate_code(){
    // 6 haneli rakam kodu (insan dostu)
    return str_pad((string)random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
}

function _save_token($pdo, $userId, $token){
    $expires = date('Y-m-d H:i:s', time() + RESET_TOKEN_TTL);
    $pdo->prepare("UPDATE app_users SET reset_token=?, reset_expires=? WHERE id=?")
        ->execute([$token, $expires, $userId]);
}

function _clear_token($pdo, $userId){
    $pdo->prepare("UPDATE app_users SET reset_token=NULL, reset_expires=NULL WHERE id=?")
        ->execute([$userId]);
}

// Aynı hesaba RESET_RESEND_WINDOW saniyeden kısa sürede tekrar kod üretilmesini engeller.
// reset_expires zaten (üretim anı + TTL) olduğundan ayrı bir "son gönderim" kolonu gerekmez:
// kalan süre (TTL - RESEND_WINDOW)'dan fazlaysa kod RESEND_WINDOW saniyeden kısa süre önce üretilmiş demektir.
function _recently_sent($user){
    if(empty($user['reset_token']) || empty($user['reset_expires'])) return false;
    $remaining = strtotime($user['reset_expires']) - time();
    return $remaining > (RESET_TOKEN_TTL - RESET_RESEND_WINDOW);
}

// Basit dosya tabanlı IP rate-limit (session'a bağlı değil — saldırgan session/cookie
// temizlese de aynı IP'den kısa sürede çok sayıda istek atamaz). Dosya yoksa/yazılamazsa
// "fail-open" davranır (yerel/geliştirme ortamını kilitlemesin diye), ama durumu loglar.
function _rate_limit_hit($action, $ip, $maxHits, $windowSeconds){
    $fp = @fopen(RESET_RL_FILE, 'c+');
    if(!$fp){ error_log('sifre_sifirla: rate-limit dosyası açılamadı, kontrol atlandı'); return false; }
    if(!flock($fp, LOCK_EX)){ fclose($fp); return false; }

    $now  = time();
    $size = filesize(RESET_RL_FILE);
    $raw  = $size > 0 ? fread($fp, $size) : '';
    $data = $raw ? json_decode($raw, true) : [];
    if(!is_array($data)) $data = [];
    $bucket = isset($data[$action][$ip]) && is_array($data[$action][$ip]) ? $data[$action][$ip] : [];

    $hits = [];
    foreach($bucket as $ts){
        if(($now - (int)$ts) < $windowSeconds) $hits[] = (int)$ts;
    }

    $blocked = count($hits) >= $maxHits;
    if(!$blocked) $hits[] = $now;

    $data[$action][$ip] = $hits;
    ftruncate($fp, 0);
    rewind($fp);
    fwrite($fp, json_encode($data));
    fflush($fp);
    flock($fp, LOCK_UN);
    fclose($fp);

    return $blocked;
}

// ---- POST işlemleri ---------------------------------------------------------

if($_SERVER['REQUEST_METHOD']==='POST'){
    $action  = $_POST['action'] ?? '';
    $__ip    = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

    // --- Adım 1: kimlik gönder ---
    if($action === 'send_code'){
        $identity = trim($_POST['identity'] ?? '');
        // Güvenlik: kullanıcı bulunamasa bile aynı mesajı ver (enumeration engeli)
        $sent_msg = 'Eşleşen bir hesap varsa sıfırlama kodu gönderildi.';

        if($identity === ''){
            $err = 'Lütfen kullanıcı adı, e-posta veya telefon girin.';
        } elseif(_rate_limit_hit('send_code', $__ip, 8, 900)){
            // IP bazlı: 15 dakikada 8 istek üstü — hesap var/yok bilgisi vermeden genel hata.
            $err = 'Çok fazla istek yapıldı. Lütfen birkaç dakika sonra tekrar deneyin.';
        } else {
            try{
                $u = _find_user($pdo, $identity);
                if($u){
                    if(_recently_sent($u)){
                        // Az önce kod üretildi — YENİ kod üretme/gönderme (spam+brute-force önleme),
                        // ama kullanıcı zaten sahip olduğu kodu girebilsin diye adım 2'ye geçmeye devam et.
                        $_SESSION['reset_uid'] = $u['id'];
                    } else {
                        $code = _generate_code();
                        _save_token($pdo, $u['id'], $code);

                        $msg  = 'OTS - Şifre sıfırlama kodunuz: '.$code.' ('.(RESET_TOKEN_TTL/60).' dakika geçerli)';
                        $sent = false;

                        // WhatsApp
                        if(!empty($u['phone'])){
                            $sent = wa_send($u['phone'], $msg);
                        }
                        // E-posta (mail_link varsa basit mailto — gerçek SMTP için ek lib gerekir)
                        // Burada mail() ile gönderim denenebilir; başarısızsa sessiz geç
                        if(!$sent && !empty($u['email'])){
                            $sent = @mail(
                                $u['email'],
                                'OTS Şifre Sıfırlama',
                                $msg,
                                'From: noreply@'.($_SERVER['HTTP_HOST'] ?? 'ots')
                            );
                        }

                        // WA ve mail ayarlı değilse kod session'a koy; yönetici uyarısı göster
                        if(!$sent){
                            // Session üzerinden güvenli taşı (sayfa değiştikçe temizlenir)
                            $_SESSION['reset_show_code'] = $code;
                            $_SESSION['reset_uid']       = $u['id'];
                        } else {
                            $_SESSION['reset_uid'] = $u['id'];
                            unset($_SESSION['reset_show_code']);
                        }
                        $_SESSION['reset_attempts'] = 0;
                    }
                }
                $ok   = $sent_msg;
                $step = 2;
            }catch(Throwable $e){
                $err = 'Bir hata oluştu. Lütfen tekrar deneyin.';
            }
        }
    }

    // --- Adım 2: kodu ve yeni şifreyi doğrula ---
    elseif($action === 'reset_pass'){
        $step = 2;
        $code  = trim($_POST['code'] ?? '');
        $pass1 = $_POST['new_password']  ?? '';
        $pass2 = $_POST['new_password2'] ?? '';

        try{
            if(_rate_limit_hit('reset_pass', $__ip, 15, 900)){
                throw new Exception('Çok fazla istek yapıldı. Lütfen birkaç dakika sonra tekrar deneyin.');
            }

            $uid = (int)($_SESSION['reset_uid'] ?? 0);
            if(!$uid) throw new Exception('Oturum süresi doldu. Baştan başlayın.');
            if($code === '') throw new Exception('Kod boş bırakılamaz.');
            if(strlen($pass1) < 6) throw new Exception('Yeni şifre en az 6 karakter olmalı.');
            if($pass1 !== $pass2) throw new Exception('Şifreler uyuşmuyor.');

            $st = $pdo->prepare("SELECT reset_token, reset_expires FROM app_users WHERE id=? AND active=1 LIMIT 1");
            $st->execute([$uid]);
            $row = $st->fetch();

            if(!$row || $row['reset_token'] === null){
                throw new Exception('Geçersiz istek. Baştan başlayın.');
            }
            if(!hash_equals((string)$row['reset_token'], $code)){
                // Yanlış kod: deneme sayacını artır, sınıra ulaşınca token'ı tamamen iptal et.
                $attempts = (int)($_SESSION['reset_attempts'] ?? 0) + 1;
                $_SESSION['reset_attempts'] = $attempts;
                if($attempts >= RESET_MAX_ATTEMPTS){
                    _clear_token($pdo, $uid);
                    unset($_SESSION['reset_uid'], $_SESSION['reset_show_code'], $_SESSION['reset_attempts']);
                    $step = 1;
                    throw new Exception('Çok fazla yanlış deneme yapıldı. Güvenlik nedeniyle kod iptal edildi, lütfen yeni kod isteyin.');
                }
                $kalan = RESET_MAX_ATTEMPTS - $attempts;
                throw new Exception('Kod hatalı. Kalan deneme hakkı: '.$kalan.'.');
            }
            if(strtotime($row['reset_expires']) < time()){
                _clear_token($pdo, $uid);
                throw new Exception('Kodun süresi dolmuş. Tekrar talep edin.');
            }

            $pdo->prepare("UPDATE app_users SET password_hash=? WHERE id=?")
                ->execute([password_hash($pass1, PASSWORD_DEFAULT), $uid]);

            _clear_token($pdo, $uid);
            unset($_SESSION['reset_uid'], $_SESSION['reset_show_code'], $_SESSION['reset_attempts']);

            $ok   = 'Şifreniz başarıyla güncellendi. Giriş yapabilirsiniz.';
            $step = 3; // tamamlandı
        }catch(Throwable $e){
            $err = $e->getMessage();
        }
    }

    // --- "Başa dön" linki ---
    elseif($action === 'restart'){
        unset($_SESSION['reset_uid'], $_SESSION['reset_show_code'], $_SESSION['reset_attempts']);
        $step = 1;
    }
}

// Eğer session'da uid varsa adım 2'de kal
if($step===1 && !empty($_SESSION['reset_uid'])) $step = 2;

$cfg       = app_config();
$app_name  = $cfg['app_name'] ?? 'OTS';
$show_code = $_SESSION['reset_show_code'] ?? null; // yalnızca WA/mail yoksa
?>
<!doctype html>
<html lang="tr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?=h($app_name)?> — Şifremi Unuttum</title>
<style>
*{box-sizing:border-box}
body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;
     background:linear-gradient(135deg,#071326,#10233f);
     font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Arial,sans-serif;color:#101828}
.card{width:440px;max-width:94vw;background:#fff;border-radius:24px;padding:32px;
      box-shadow:0 30px 80px rgba(0,0,0,.28)}
.logo{width:56px;height:56px;border-radius:16px;background:#fff;overflow:hidden;
      padding:6px;display:flex;align-items:center;justify-content:center;margin-bottom:14px}
.logo img{width:100%;height:100%;object-fit:contain}
h1{margin:0 0 6px;font-size:26px}
.muted{color:#667085;margin-bottom:24px;font-size:14px}
label{display:block;font-weight:700;margin:14px 0 6px;font-size:14px}
input[type=text],input[type=email],input[type=password]{
  width:100%;border:1.5px solid #d0d5dd;border-radius:12px;
  padding:12px 14px;font-size:15px;outline:none}
input:focus{border-color:#4f46e5}
.btn{display:block;width:100%;border:0;border-radius:12px;
     background:#111827;color:#fff;padding:13px;margin-top:16px;
     font-weight:700;font-size:15px;cursor:pointer}
.btn:hover{background:#1f2937}
.btn-link{background:none;border:none;color:#4f46e5;cursor:pointer;
          font-size:13px;padding:0;text-decoration:underline;margin-top:12px}
.alert{background:#fee2e2;color:#991b1b;padding:12px 16px;border-radius:10px;margin:14px 0;font-size:14px}
.notice{background:#dcfce7;color:#14532d;padding:12px 16px;border-radius:10px;margin:14px 0;font-size:14px}
.warn{background:#fef9c3;color:#713f12;padding:12px 16px;border-radius:10px;margin:14px 0;font-size:14px}
.back{display:inline-block;margin-top:16px;font-size:13px;color:#4f46e5;text-decoration:none}
.step{font-size:12px;color:#9ca3af;margin-bottom:4px;letter-spacing:.5px;text-transform:uppercase}
hr{border:none;border-top:1px solid #f1f5f9;margin:18px 0}
</style>
</head>
<body>
<div class="card">
  <div class="logo"><img src="<?=h(brand_logo())?>" alt="Logo" onerror="this.parentNode.textContent='<?=h(mb_strtoupper(mb_substr($app_name,0,1)))?>'"></div>
  <h1><?=h($app_name)?></h1>
  <div class="muted">Şifre Sıfırlama</div>

<?php if($err): ?>
<div class="alert"><?=h($err)?></div>
<?php endif; ?>
<?php if($ok && $step!==3): ?>
<div class="notice"><?=h($ok)?></div>
<?php endif; ?>

<?php if($step===3): ?>
<!-- Tamamlandı -->
<div class="notice"><b>Şifreniz güncellendi.</b> Yeni şifrenizle giriş yapabilirsiniz.</div>
<a class="btn" href="index.php" style="text-align:center;text-decoration:none">Giriş Ekranına Dön</a>

<?php elseif($step===2): ?>
<!-- Adım 2: kod + yeni şifre -->
<div class="step">Adım 2 / 2</div>
<p style="font-size:14px;color:#374151;margin:4px 0 0">Telefonunuza veya e-postanıza gönderilen 6 haneli kodu girin.</p>

<?php if($show_code): ?>
<div class="warn">
  <b>WhatsApp ve e-posta ayarlı değil.</b><br>
  Yöneticinizle iletişime geçin ya da aşağıdaki geçici kodu kullanın:<br>
  <span style="font-size:22px;font-weight:900;letter-spacing:4px"><?=h($show_code)?></span>
</div>
<?php endif; ?>

<form method="post">
<input type="hidden" name="action" value="reset_pass">
<label>Doğrulama Kodu (6 hane)</label>
<input type="text" name="code" maxlength="6" inputmode="numeric" autocomplete="one-time-code" placeholder="123456" required autofocus>
<label>Yeni Şifre</label>
<input type="password" name="new_password" minlength="6" placeholder="En az 6 karakter" required>
<label>Yeni Şifre Tekrar</label>
<input type="password" name="new_password2" minlength="6" placeholder="Şifreyi tekrar girin" required>
<button type="submit" class="btn">Şifremi Güncelle</button>
</form>
<hr>
<form method="post" style="text-align:center">
<input type="hidden" name="action" value="restart">
<button type="submit" class="btn-link">&#8592; Başa dön / farklı hesap</button>
</form>

<?php else: ?>
<!-- Adım 1: kimlik -->
<div class="step">Adım 1 / 2</div>
<p style="font-size:14px;color:#374151;margin:4px 0 0">Kayıtlı kullanıcı adınızı, e-postanızı veya telefon numaranızı girin.</p>
<form method="post">
<input type="hidden" name="action" value="send_code">
<label>Kullanıcı Adı / E-posta / Telefon</label>
<input type="text" name="identity" placeholder="ersin, ersin@firma.com veya 05XX..." required autofocus autocomplete="username">
<button type="submit" class="btn">Kod Gönder</button>
</form>

<?php endif; ?>

<hr>
<a class="back" href="index.php">&#8592; Giriş ekranına dön</a>
</div>
</body>
</html>
