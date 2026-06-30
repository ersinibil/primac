<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Oturumu uzun ömürlü yap — telefonda/PWA'da kısa sürede atmasın (30 gün)
$__life = 60*60*24*30;
@ini_set('session.gc_maxlifetime', $__life);
@ini_set('session.cookie_lifetime', $__life);
if (session_status() === PHP_SESSION_NONE) {
    @session_set_cookie_params($__life, '/');
    session_start();
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
        'stock'=>'Stok / Ürün / Satın Alma','report'=>'Raporlar','personnel'=>'Personel','users'=>'Kullanıcı / Yetki',
    ];
}
function module_label($key){ $m=module_list(); return $m[$key] ?? $key; }

// Web sayfası → gerekli modül yetkisi. boot.php sonunda otomatik uygulanır (tek merkezden koruma).
function page_module_map(){
    return [
        'jobs.php'=>'jobs','job_new.php'=>'jobs','job_view.php'=>'jobs','job_edit.php'=>'jobs',
        'takvim.php'=>'jobs','production.php'=>'jobs','assembly.php'=>'jobs','external.php'=>'jobs',
        'approval_waiting.php'=>'jobs','work_center.php'=>'jobs',
        'tasks.php'=>'tasks',
        'contacts.php'=>'contacts','contact_new.php'=>'contacts','contact_view.php'=>'contacts','contacts_report.php'=>'contacts',
        'teklif.php'=>'teklif',
        'finance.php'=>'finance','finance_new.php'=>'finance','finance_accounts.php'=>'finance','finance_transfer.php'=>'finance','finance_account_view.php'=>'finance',
        'stock.php'=>'stock','product_new.php'=>'stock','stock_movement_new.php'=>'stock',
        'product_categories.php'=>'stock','product_taxonomy.php'=>'stock','purchase.php'=>'stock',
        'report.php'=>'report',
        'personnel.php'=>'personnel','personnel_new.php'=>'personnel','personnel_view.php'=>'personnel',
        'users.php'=>'users',
    ];
}

function user_can($permission){
    $u=current_user();
    if(!$u) return false;
    if(is_admin()) return true;

    $perms=$u['permissions'] ?? [];
    if(is_string($perms)){
        $decoded=json_decode($perms,true);
        $perms=is_array($decoded)?$decoded:[];
    }
    return in_array($permission,$perms,true);
}

function require_permission($permission){
    require_login();
    if(!user_can($permission)){
        http_response_code(403);
        echo "<h1>Yetkisiz Alan</h1><p>Bu ekran için yetkiniz yok.</p><p><a href='dashboard.php'>Ana ekrana dön</a></p>";
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

// ---- Otomatik sayfa koruması: çalışan web sayfası korumalı modüldeyse yetki zorla (admin her şeye yetkili) ----
$__page = basename($_SERVER['SCRIPT_NAME'] ?? ($_SERVER['PHP_SELF'] ?? ''));
$__pmap = page_module_map();
if($__page !== '' && isset($__pmap[$__page])){
    require_permission($__pmap[$__page]);
}
?>