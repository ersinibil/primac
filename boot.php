<?php
// Güvenlik: hata mesajları ekrana BASILMAZ (DB şifresi/yol sızmasın), log'a yazılır.
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);
ini_set('log_errors', 1);

// Oturumu uzun ömürlü yap — telefonda/PWA'da kısa sürede atmasın (30 gün)
$__life = 60*60*24*30;
@ini_set('session.gc_maxlifetime', $__life);
@ini_set('session.cookie_lifetime', $__life);
if (session_status() === PHP_SESSION_NONE) {
    @session_set_cookie_params([
        'lifetime' => $__life,
        'path'     => '/',
        'secure'   => !empty($_SERVER['HTTPS']),
        'httponly' => true,   // JS ile okunamaz (XSS'te oturum çalınmaz)
        'samesite' => 'Lax',
    ]);
    session_start();
}

// Mobil cihaz → otomatik mobil arayüz (/mobile). Masaüstü görünümü için bir kez ?web=1.
if (isset($_GET['web'])) $_SESSION['force_web'] = 1;
$__mpub = ['public_file.php','cron.php','ics.php','icon.php','manifest.php','sw.php','kur.php','migrate.php','logout.php'];
if (empty($_SESSION['force_web'])
    && !empty($_SESSION['user'])
    && strpos($_SERVER['SCRIPT_NAME'] ?? '', '/mobile/') === false
    && !in_array(basename($_SERVER['SCRIPT_NAME'] ?? ''), $__mpub, true)
    && !empty($_SERVER['HTTP_USER_AGENT'])
    && preg_match('/Mobile|Android|iPhone|iPod|BlackBerry|Windows Phone|webOS/i', $_SERVER['HTTP_USER_AGENT'])
    && stripos($_SERVER['HTTP_USER_AGENT'], 'iPad') === false) {
    header('Location: mobile/index.php');
    exit;
}

function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

function app_config(){
    $file = __DIR__ . '/config.php';
    if(!file_exists($file)){
        die('<h1>PRIMAC OS</h1><p>config.php bulunamadı. config.sample.php dosyasını config.php olarak kopyalayın ve veritabanı şifresini girin.</p>');
    }
    return require $file;
}

function db(){
    static $pdo = null;
    if($pdo) return $pdo;
    $c = app_config();
    $dsn = "mysql:host={$c['db_host']};dbname={$c['db_name']};charset=utf8mb4";
    $opt = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ];
    $pdo = new PDO($dsn, $c['db_user'], $c['db_pass'], $opt);
    // Türkçe garanti: bazı sunucularda DSN charset yok sayılır → bağlantıda zorla
    try{ $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"); }catch(Throwable $e){}
    return $pdo;
}

function redirect($url){ header("Location: ".$url); exit; }

// Uygulamanın kök URL'i (folder adından bağımsız: /OTS/, /dev/ ne olursa). Paylaşım linkleri için.
function base_url(){
    $https=(!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off') || ($_SERVER['SERVER_PORT']??'')==443;
    $host=$_SERVER['HTTP_HOST'] ?? 'localhost';
    $dir=str_replace('\\','/',dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
    if(substr($dir,-7)==='/mobile') $dir=substr($dir,0,-7);
    $dir=rtrim($dir,'/');
    return ($https?'https':'http').'://'.$host.$dir.'/';
}

function require_login(){
    if(empty($_SESSION['user'])){
        // Girişten sonra geri dönmek için, gitmek istenen adresi sakla (sadece yerel yol)
        $ret = $_SERVER['REQUEST_URI'] ?? '';
        if ($ret && $ret[0] === '/' && substr($ret,0,2) !== '//') {
            $_SESSION['return_to'] = $ret;
        }
        // MUTLAK yol kullan; göreli '../index.php' Safari'de hatalı çözülüp döngü yapabiliyor.
        // /dev/mobile/index.php  → /dev/index.php   ·   /dev/dashboard.php → /dev/index.php
        $dir = rtrim(str_replace('\\','/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
        if (substr($dir, -7) === '/mobile') $dir = substr($dir, 0, -7);
        redirect(($dir === '' ? '' : $dir) . '/index.php');
    }
}

function current_user(){
    return $_SESSION['user'] ?? null;
}

function is_admin(){
    $u=current_user();
    return $u && (($u['role'] ?? '')==='admin' || ($u['role'] ?? '')==='yonetici' || !empty($u['is_admin']));
}

// Tüm yetkilendirilebilir modüller — menü, users.php ve sayfa koruması ortak kullanır.
function module_list(){
    return [
        'dashboard'=>'Komuta Merkezi','jobs'=>'İşler (üretim/montaj/dış tedarik/takvim)','tasks'=>'Görevler',
        'contacts'=>'Cari Hesaplar','teklif'=>'Teklifler','finance'=>'Finans',
        'stock'=>'Stok / Ürün / Satın Alma','report'=>'Raporlar','personnel'=>'Personel',
        'muhasebe'=>'Muhasebe (Gider/Gelir/Personel Ödemeleri)','users'=>'Kullanıcı / Yetki',
    ];
}
function module_label($key){ $m=module_list(); return $m[$key] ?? $key; }

// Web sayfası → gerekli modül yetkisi. boot.php sonunda otomatik uygulanır (tek merkezden koruma).
function page_module_map(){
    return [
        // NOT: job_view.php / task detayları KORUMASIZ — personel kendine atanan işi/görevi
        // bildirimden açabilsin diye. Liste/oluşturma sayfaları yetkiye bağlı.
        'jobs.php'=>'jobs','job_new.php'=>'jobs','job_edit.php'=>'jobs',
        'takvim.php'=>'jobs','production.php'=>'jobs','assembly.php'=>'jobs','external.php'=>'jobs',
        'approval_waiting.php'=>'jobs','work_center.php'=>'jobs',
        'tasks.php'=>'tasks',
        'contacts.php'=>'contacts','contact_new.php'=>'contacts','contact_view.php'=>'contacts','contacts_report.php'=>'contacts',
        'teklif.php'=>'teklif',
        'finance.php'=>'finance','finance_new.php'=>'finance','finance_accounts.php'=>'finance','finance_transfer.php'=>'finance','finance_account_view.php'=>'finance',
        'kasa.php'=>'finance','transfer.php'=>'finance','account_view.php'=>'finance','movement_view.php'=>'finance',
        'stock.php'=>'stock','product_new.php'=>'stock','stock_movement_new.php'=>'stock',
        'product_categories.php'=>'stock','product_taxonomy.php'=>'stock','purchase.php'=>'stock',
        'sales.php'=>'stock','kpi.php'=>'personnel',
        'report.php'=>'report','gunluk_rapor.php'=>'report',
        'accounting.php'=>'muhasebe','accounting_categories.php'=>'muhasebe',
        'personnel.php'=>'personnel','personnel_new.php'=>'personnel','personnel_view.php'=>'personnel',
        'users.php'=>'users',
        'brand_settings.php'=>'users',
    ];
}

function user_can($permission){
    $u=current_user();
    if(!$u) return false;
    if(is_admin()) return true;

    // Yönetici bir yetkiyi yeni verdiyse oturum açık kullanıcıda hemen etkili olsun diye
    // permissions HER ÇAĞRIDA DB'den taze okunur (session'daki eski kopyaya güvenilmez).
    $perms=null;
    if(!empty($u['id'])){
        try{
            $row=db()->prepare("SELECT permissions FROM app_users WHERE id=?");
            $row->execute([$u['id']]);
            $r=$row->fetch();
            if($r!==false){
                $decoded=json_decode($r['permissions'] ?? '[]',true);
                $perms=is_array($decoded)?$decoded:[];
                $_SESSION['user']['permissions']=$perms; // session'ı da güncel tut
            }
        }catch(Throwable $e){ $perms=null; }
    }
    if($perms===null){
        // DB'ye erişilemediyse session'daki son bilinen kopyaya düş (tamamen erişimsiz kalmasın)
        $perms=$u['permissions'] ?? [];
        if(is_string($perms)){
            $decoded=json_decode($perms,true);
            $perms=is_array($decoded)?$decoded:[];
        }
    }
    return in_array($permission,$perms,true);
}

function require_permission($permission){
    require_login();
    if(!user_can($permission)){
        http_response_code(403);
        $isMobile = strpos($_SERVER['SCRIPT_NAME'] ?? '', '/mobile/')!==false;
        $home = base_url().($isMobile ? 'mobile/index.php' : 'dashboard.php');
        echo "<!doctype html><meta charset='utf-8'><meta name='viewport' content='width=device-width,initial-scale=1'>"
            ."<div style='font-family:-apple-system,Arial,sans-serif;max-width:520px;margin:60px auto;text-align:center;padding:24px'>"
            ."<div style='font-size:46px'>🔒</div><h1 style='font-size:22px;margin:10px 0'>Bu ekran için yetkiniz yok</h1>"
            ."<p style='color:#667085'>Erişim gerekiyorsa yöneticinizden bu modül için yetki isteyin.</p>"
            ."<p><a href='".h($home)."' style='display:inline-block;background:#2563eb;color:#fff;padding:12px 22px;border-radius:12px;text-decoration:none;font-weight:800'>Ana sayfaya dön</a></p></div>";
        exit;
    }
}

// Admin için "Sil" butonu (POST → sil.php, onaylı). Yetkisizde boş döner.
function delete_button($type,$id,$label='🗑 Sil'){
    if(!is_admin() || !$id) return '';
    return '<form method="post" action="sil.php" style="display:inline-block;margin:0"'
        .' onsubmit="return confirm(\'Bu kayıt ve bağlı verileri KALICI olarak silinecek. Emin misiniz?\')">'
        .'<input type="hidden" name="t" value="'.h($type).'">'
        .'<input type="hidden" name="id" value="'.(int)$id.'">'
        .'<button class="btn danger" type="submit">'.$label.'</button></form>';
}

function locked_link($label,$url,$permission){
    if(user_can($permission)){
        return '<a href="'.h($url).'">'.$label.'</a>';
    }
    return '<a href="#" title="Yetkiniz yok" style="opacity:.45;cursor:not-allowed">🔒 '.$label.'</a>';
}

// Marka logo/ikon fonksiyonları — her yerde merkezi kaynak
function brand_logo(){
    // 1. config'de AÇIKÇA tanımlı logo EN ÖNCELİKLİ (her site kendi config'iyle kesinleşir)
    try{
        $cfg = app_config();
        if(!empty($cfg['logo']) && is_file(__DIR__.'/'.$cfg['logo'])) return $cfg['logo'];
    }catch(Throwable $e){}
    // 2. config'de yoksa admin panelden yüklenen ayar (app_settings)
    if(!function_exists('get_setting') && is_file(__DIR__.'/share_lib.php')){
        require_once __DIR__.'/share_lib.php';
    }
    if(function_exists('get_setting')){
        try{
            $v = get_setting('brand_logo','');
            if($v && is_file(__DIR__.'/'.$v)) return $v;
        }catch(Throwable $e){}
    }
    // 3. Varsayılan
    return 'logo.png';
}
function brand_icon(){
    if(!function_exists('get_setting') && is_file(__DIR__.'/share_lib.php')){
        require_once __DIR__.'/share_lib.php';
    }
    if(function_exists('get_setting')){
        try{
            $v = get_setting('brand_icon','');
            if($v && is_file(__DIR__.'/'.$v)) return $v;
        }catch(Throwable $e){}
    }
    return brand_logo();
}

function money($v){ return number_format((float)$v, 2, ',', '.') . ' ₺'; }
function qty($v){ return rtrim(rtrim(number_format((float)$v, 3, ',', '.'), '0'), ','); }
function badge($text, $tone='gray'){ return '<span class="badge '.$tone.'">'.h($text).'</span>'; }

function status_tone($status){
    return [
        'Yeni'=>'blue','Teklif'=>'gray','Onay Bekliyor'=>'yellow','Planlandı'=>'purple','Devam Ediyor'=>'purple',
        'Dışarıda'=>'orange','Montajda'=>'teal','Teslim Edildi'=>'green','Tamamlandı'=>'green','İptal'=>'red',
        'Açık'=>'blue','Bekliyor'=>'yellow','Ödendi'=>'green','Tahsil Edildi'=>'green'
    ][$status] ?? 'gray';
}

function job_type_label($type){
    return [
        '3d_imalat'=>'3D İmalat','uv_baski'=>'UV Baskı','lazer'=>'Lazer','grafik_tasarim'=>'Grafik Tasarım',
        'dis_atolye'=>'Dış Atölye','tedarikcide_uretim'=>'Tedarikçide Üretim','montaj'=>'Montaj',
        'satin_alma'=>'Satın Alma','muhasebe'=>'Muhasebe İşlemi','karma'=>'Karma İş'
    ][$type] ?? $type;
}

function next_job_no(){ return 'PR-' . date('Y') . '-' . str_pad((string)random_int(1,999999), 6, '0', STR_PAD_LEFT); }

function safe_count($sql){ try { return (int)(db()->query($sql)->fetch()['c'] ?? 0); } catch(Throwable $e){ return 0; } }
function safe_sum($sql){ try { return (float)(db()->query($sql)->fetch()['s'] ?? 0); } catch(Throwable $e){ return 0; } }

/* ---- "Beni Hatırla" — oturum atsa bile çerezle otomatik giriş (telefon/PWA) ---- */
function remember_install(){
    try{ if(!db()->query("SHOW COLUMNS FROM app_users LIKE 'remember_token'")->fetch())
        db()->exec("ALTER TABLE app_users ADD COLUMN remember_token VARCHAR(64) NULL"); }catch(Throwable $e){}
}
function set_session_user($u){
    $perms=json_decode($u['permissions'] ?? '[]',true); if(!is_array($perms)) $perms=[];
    $_SESSION['user']=[
        'id'=>$u['id'],'name'=>$u['full_name'],'username'=>$u['username'],'role'=>$u['role'],
        'personnel_id'=>$u['personnel_id'],'permissions'=>$perms,'is_admin'=>$u['role']==='admin'
    ];
}
function remember_set($userId){
    try{
        remember_install();
        $token=bin2hex(random_bytes(32));
        db()->prepare("UPDATE app_users SET remember_token=? WHERE id=?")->execute([hash('sha256',$token),$userId]);
        setcookie('acans_remember', $userId.':'.$token, time()+60*60*24*30, '/', '', !empty($_SERVER['HTTPS']), true);
    }catch(Throwable $e){}
}
function remember_clear(){
    try{ if(!empty($_SESSION['user']['id'])) db()->prepare("UPDATE app_users SET remember_token=NULL WHERE id=?")->execute([$_SESSION['user']['id']]); }catch(Throwable $e){}
    setcookie('acans_remember','',time()-3600,'/');
}
function remember_check(){
    if(!empty($_SESSION['user'])) return;
    if(empty($_COOKIE['acans_remember'])) return;
    $parts=explode(':',$_COOKIE['acans_remember']);
    if(count($parts)!==2) return;
    list($uid,$token)=$parts;
    if(!ctype_digit($uid) || strlen($token)<32) return;
    try{
        remember_install();
        $s=db()->prepare("SELECT * FROM app_users WHERE id=? AND active=1 LIMIT 1");
        $s->execute([(int)$uid]); $u=$s->fetch();
        if($u && !empty($u['remember_token']) && hash_equals($u['remember_token'], hash('sha256',$token))){
            set_session_user($u);
        }
    }catch(Throwable $e){}
}
remember_check();
// İşlem kaydı (kim ne yaptı) — web genelinde aktif olsun (mobilde common.php yüklüyor)
if(is_file(__DIR__.'/activity_lib.php')) require_once __DIR__.'/activity_lib.php';
// Geciken iş otomatik bildirimi (saatte bir, dosya kilidi ile) — giriş yapılmışsa
if(!empty($_SESSION['user']) && is_file(__DIR__.'/job_overdue_lib.php')){ require_once __DIR__.'/job_overdue_lib.php'; try{ check_overdue_jobs(db()); }catch(Throwable $e){} }
// Sabah hatırlatma (09:30 sonrası ilk girişte, günde 1 kez) — kesin 09:30 için cPanel cron → cron.php?key=acans-cron-2026
if(!empty($_SESSION['user']) && is_file(__DIR__.'/daily_reminder_lib.php')){ require_once __DIR__.'/daily_reminder_lib.php'; try{ check_daily_reminders(db()); }catch(Throwable $e){} }

// ---- Otomatik sayfa koruması: çalışan web sayfası korumalı modüldeyse yetki zorla (admin her şeye yetkili) ----
$__page = basename($_SERVER['SCRIPT_NAME'] ?? ($_SERVER['PHP_SELF'] ?? ''));
$__pmap = page_module_map();
if($__page !== '' && isset($__pmap[$__page])){
    require_permission($__pmap[$__page]);
}
?>