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
$__mpub = ['public_file.php','quote_approve.php','cron.php','ics.php','icon.php','manifest.php','sw.php','kur.php','migrate.php','logout.php','wa_webhook.php'];
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

// SECURITY SPRINT-004 (2026-07-05) — FAZ-1: merkezi CSRF altyapısı. Bu fazda sadece tanımlanıyor,
// henüz hiçbir sayfada ZORUNLU kontrol açılmadı (csrf_verify() hiçbir yerden çağrılmıyor).
function csrf_token(){
    if(empty($_SESSION['csrf_token'])){
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(){
    return '<input type="hidden" name="csrf_token" value="'.h(csrf_token()).'">';
}

// $_POST['csrf_token'] (klasik form) VEYA X-CSRF-Token header'ı (fetch/AJAX) kabul edilir.
function csrf_verify(){
    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    $valid = !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], (string)$token);
    if(!$valid){
        http_response_code(403);
        echo "<!doctype html><meta charset='utf-8'><meta name='viewport' content='width=device-width,initial-scale=1'>"
            ."<div style='font-family:-apple-system,Arial,sans-serif;max-width:480px;margin:60px auto;text-align:center;padding:24px'>"
            ."<div style='font-size:46px'>🔒</div>"
            ."<h1 style='font-size:19px;margin:10px 0'>Güvenlik doğrulaması başarısız. Lütfen sayfayı yenileyip tekrar deneyin.</h1></div>";
        exit;
    }
}

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
    // Idle timeout kontrolü — 2 saatlik inaktivite sonrası otomatik çıkış
    $__idle_timeout = 60*60*2;  // 2 saat (saniye cinsinden)
    if(!empty($_SESSION['user']) && !empty($_SESSION['last_activity'])){
        if(time() - $_SESSION['last_activity'] > $__idle_timeout){
            // Timeout geçmiş — oturumu kapat. session_destroy() tek başına $_SESSION array'ini
            // TEMİZLEMEZ (PHP'nin bilinen davranışı) — elle boşaltmazsak bu istekte hâlâ dolu
            // görünüp aşağıdaki remember_check()/require_login() kontrollerini yanıltır.
            $_SESSION = [];
            session_destroy();
            // Remember-me çerezi varsa, otomatik giriş dene
            if(!empty($_COOKIE['acans_remember'])){
                remember_check();
            }
            // Hala oturum açılmamışsa login'e yönlendir
            if(empty($_SESSION['user'])){
                $ret = $_SERVER['REQUEST_URI'] ?? '';
                if ($ret && $ret[0] === '/' && substr($ret,0,2) !== '//') {
                    $_SESSION['return_to'] = $ret;
                }
                $dir = rtrim(str_replace('\\','/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
                if (substr($dir, -7) === '/mobile') $dir = substr($dir, 0, -7);
                redirect(($dir === '' ? '' : $dir) . '/index.php');
            }
            return;
        }
    }

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

    // Oturum geçerli (timeout'tan geçti / yeni giriş) — son aktivite zamanını burada güncelle,
    // yukarıdaki timeout kontrolünden SONRA olduğu için artık gerçek bir "fark" ölçülebiliyor.
    $_SESSION['last_activity'] = time();
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
        'edit_delete'=>'Var Olan Kaydı Düzenleme / Silme Yetkisi',
        // SECURITY SPRINT-001 (2026-07-04): "alt yönetici" yetkisi — admin, 'personnel' modül
        // yetkisi olan birine EK olarak bu yetkiyi verirse, o kişi de personel şifre sıfırlama/
        // giriş hesabı oluşturma yapabilir. Bu yetki olmadan sadece admin bu işlemleri yapabilir.
        'personnel_accounts'=>'Personel Şifre/Hesap Yönetimi (Alt Yönetici Yetkisi)',
    ];
}
function module_label($key){ $m=module_list(); return $m[$key] ?? $key; }

// Bir personelin (admin hariç) var olan kayıtları düzenleme/silme hakkı var mı? Modül yetkisi
// (örn. 'finance') sadece görüntüleme/yeni kayıt eklemeyi kapsar — var olan bir kaydı değiştirmek/silmek
// için ayrıca bu genel yetki de gerekir. Admin her zaman true. Kademeli olarak modül modül uygulanıyor
// (bkz. memory/features.md) — henüz bu kontrolü kullanmayan eski ekranlar olabilir.
function can_edit_delete(){
    return is_admin() || user_can('edit_delete');
}

// Web sayfası → gerekli modül yetkisi. boot.php sonunda otomatik uygulanır (tek merkezden koruma).
function page_module_map(){
    return [
        // NOT: job_view.php / task detayları KORUMASIZ — personel kendine atanan işi/görevi
        // bildirimden açabilsin diye. Liste/oluşturma sayfaları yetkiye bağlı.
        'jobs.php'=>'jobs','job_new.php'=>'jobs','job_edit.php'=>'jobs',
        'takvim.php'=>'jobs','calendar.php'=>'jobs','production.php'=>'jobs','assembly.php'=>'jobs','external.php'=>'jobs',
        'approval_waiting.php'=>'jobs','work_center.php'=>'jobs','design.php'=>'jobs',
        'tasks.php'=>'tasks',
        'contacts.php'=>'contacts','contact_new.php'=>'contacts','contact_view.php'=>'contacts','contacts_report.php'=>'contacts',
        'contact_documents.php'=>'contacts',
        'trade_documents.php'=>'contacts','trade_document_new.php'=>'contacts','trade_document_view.php'=>'contacts',
        'teklif.php'=>'teklif',
        'finance.php'=>'finance','finance_new.php'=>'finance','finance_accounts.php'=>'finance','finance_transfer.php'=>'finance','finance_account_view.php'=>'finance',
        'kasa.php'=>'finance','transfer.php'=>'finance','account_view.php'=>'finance','movement_view.php'=>'finance',
        'checks_notes.php'=>'finance','check_note_view.php'=>'finance','collection.php'=>'finance','payment.php'=>'finance',
        'stock.php'=>'stock','product_new.php'=>'stock','stock_movement_new.php'=>'stock',
        'product_categories.php'=>'stock','product_taxonomy.php'=>'stock','purchase.php'=>'stock',
        'sales.php'=>'stock','kpi.php'=>'personnel',
        'report.php'=>'report','gunluk_rapor.php'=>'report',
        'accounting.php'=>'muhasebe','accounting_categories.php'=>'muhasebe',
        'personnel.php'=>'personnel','personnel_new.php'=>'personnel','personnel_view.php'=>'personnel','personnel_edit.php'=>'personnel',
        'users.php'=>'users',
        'brand_settings.php'=>'users',
        'wa_send_now.php'=>'users',
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
// "status-badge" web'in ortak tasarım dili sözlüğüne (WEB UI ALIGNMENT & NAVIGATION SPRINT 001
// Faz C) ek bir isim — kendi CSS kuralı yok, mevcut .badge kurallarını olduğu gibi kullanır.
// badge() hem web hem mobil tarafından çağrıldığı için mobilde de basılıyor ama mobil CSS'te
// .status-badge için bir kural olmadığından zararsız (görsel etkisi yok).
function badge($text, $tone='gray'){ return '<span class="badge status-badge '.$tone.'">'.h($text).'</span>'; }

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

// UX SPRINT 002 — PHASE B3 (2026-07-14): Dashboard Nabız Satırı — durum/eşik mantığı TEK yerde,
// web (dashboard.php) VE mobil (mobile/index.php) ortak kullanır (ikisi de boot.php'yi zaten
// require ediyor — mobile/common.php üzerinden). Eşikler burada sabit, başka yerde tekrarlanmaz.
// Çağıran taraf sorumluluğu: $overdueCount/$criticalStockCount'u KENDİ try/catch'iyle çeksin
// ($ok=false hata durumunu bildirsin — safe_count() hatayı sessizce 0'a çevirdiği için burada
// KULLANILMAZ, aksi halde "veri yok" ile "gerçekten sıfır" ayırt edilemez) ve $showOverdue/
// $showCriticalStock'u kendi yetki kontrolüyle (is_admin()||user_can('jobs') / user_can('stock'))
// belirlesin — bu fonksiyon zaten-filtrelenmiş sayılarla çalışır, yetkisiz veri asla bu fonksiyona
// GERÇEK değeriyle girmemeli (görünmüyorsa 0+show=false geçilir).
function dashboard_pulse_state($ok, $overdueCount, $showOverdue, $criticalStockCount, $showCriticalStock){
    if(!$ok){
        return ['level'=>'neutral','icon'=>'⚪','message'=>'Günlük durum özeti şu anda alınamadı.','hasDetail'=>false];
    }
    if(!$showOverdue && !$showCriticalStock){
        return ['level'=>'neutral','icon'=>'⚪','message'=>'Bugün için özet bilgi bulunmuyor.','hasDetail'=>false];
    }

    $overdue = $showOverdue ? max(0,(int)$overdueCount) : 0;
    $critical = $showCriticalStock ? max(0,(int)$criticalStockCount) : 0;
    $total = $overdue + $critical;

    // Merkezi eşikler — dağınık magic number yok, tek tanım:
    $OVERDUE_RED_THRESHOLD = 3; // tek başına geciken iş sayısı buna ulaşırsa KIRMIZI
    $TOTAL_RED_THRESHOLD = 4;   // toplam kritik konu buna ulaşırsa KIRMIZI

    $parts = [];
    if($overdue > 0) $parts[] = $overdue.' geciken iş';
    if($critical > 0) $parts[] = $critical.' kritik stok';
    $dist = implode(', ', $parts);

    if($total === 0){
        return ['level'=>'green','icon'=>'🟢','message'=>'Bugün kritik durum görünmüyor. Operasyon kontrol altında.','hasDetail'=>false];
    }
    if($overdue >= $OVERDUE_RED_THRESHOLD || $total >= $TOTAL_RED_THRESHOLD){
        return ['level'=>'red','icon'=>'🔴','message'=>'Bugün müdahale gerektiren '.$total.' konu var: '.$dist.'.','hasDetail'=>true];
    }
    return ['level'=>'yellow','icon'=>'🟡','message'=>'Bugün dikkat gerektiren '.$total.' konu var: '.$dist.'.','hasDetail'=>true];
}

// UX SPRINT 002 — PHASE B4 (2026-07-14): Dashboard Hızlı İşlemler — tanım listesi TEK yerde, web
// (dashboard.php) VE mobil (mobile/index.php) ortak kullanır. Sıra = öncelik sırası (önce olan
// daha "önemli" sayılır, primary/overflow ayrımı bu sıraya göre yapılır). Her satır zaten var olan
// bir route'a işaret eder — yeni sayfa/endpoint YOK. 'perm'=>null = herkese açık (request_new.php/
// messages.php'nin kendi sayfa kodunda da hiç yetki kontrolü yok, tutarlı). 'url' web kökü
// içindir; mobil için AYNI dosya adı mobile/ altında da varsa (job_new/sales/purchase/teklif/
// task_new/request_new/messages) relative path zaten doğru sayfaya çözümlenir. Tahsilat/Ödeme
// istisna: web'de finance_new.php?direction=in|out var ama mobile/finance_new.php YOK — mobilde
// bunların karşılığı ayrı, kendi dosyaları olan collection.php/payment.php — bu yüzden
// 'mobileUrl' ile override ediliyor (çağıran taraf mobile/index.php'de $a['mobileUrl']??$a['url']
// kullanmalı).
function dashboard_quick_action_defs(){
    return [
        ['key'=>'job',      'category'=>'OPERASYON', 'label'=>'Yeni İş',       'icon'=>'📋', 'url'=>'job_new.php',                   'perm'=>'jobs'],
        ['key'=>'satis',    'category'=>'TİCARET',    'label'=>'Yeni Satış',    'icon'=>'🧾', 'url'=>'sales.php',                     'perm'=>'stock'],
        ['key'=>'tahsilat', 'category'=>'FİNANS',     'label'=>'Yeni Tahsilat', 'icon'=>'💰', 'url'=>'finance_new.php?direction=in',  'perm'=>'finance', 'mobileUrl'=>'collection.php'],
        ['key'=>'alis',     'category'=>'TİCARET',    'label'=>'Yeni Alış',     'icon'=>'📦', 'url'=>'purchase.php',                  'perm'=>'stock'],
        ['key'=>'odeme',    'category'=>'FİNANS',     'label'=>'Yeni Ödeme',    'icon'=>'💸', 'url'=>'finance_new.php?direction=out', 'perm'=>'finance', 'mobileUrl'=>'payment.php'],
        ['key'=>'gorev',    'category'=>'OPERASYON', 'label'=>'Yeni Görev',    'icon'=>'🎯', 'url'=>'task_new.php',                  'perm'=>'tasks'],
        ['key'=>'talep',    'category'=>'OPERASYON', 'label'=>'Yeni Talep',    'icon'=>'📨', 'url'=>'request_new.php',               'perm'=>null],
        ['key'=>'teklif',   'category'=>'TİCARET',    'label'=>'Yeni Teklif',   'icon'=>'📄', 'url'=>'teklif.php',                    'perm'=>'teklif'],
        ['key'=>'mesaj',    'category'=>'İLETİŞİM',   'label'=>'Yeni Mesaj',    'icon'=>'💬', 'url'=>'messages.php',                  'perm'=>null],
    ];
}

// Yetkili (görünür) aksiyonları önceliğe göre primary (en fazla $cap) ve overflow ("Diğer
// İşlemler") olarak ikiye böler — dashboard zamanla yeni aksiyon eklendikçe kalabalıklaşmasın diye.
// $canSee: function(string $perm): bool — çağıran taraf is_admin()/user_can() ile kendi kontrolünü
// verir (web/mobil admin tanımı farklı olduğu için burada sabitlenmiyor).
function dashboard_quick_actions_split($canSee, $cap=7){
    $visible = [];
    foreach(dashboard_quick_action_defs() as $a){
        if($a['perm']===null || $canSee($a['perm'])) $visible[] = $a;
    }
    if(count($visible) <= $cap){
        return ['primary'=>$visible, 'overflow'=>[]];
    }
    return ['primary'=>array_slice($visible,0,$cap), 'overflow'=>array_slice($visible,$cap)];
}

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
        // SECURITY SPRINT-005 FAZ-4: SameSite=Lax eklendi. PHP 7.2'nin setcookie() imzası SameSite
        // desteklemiyor; yaygın "path hack"i (path'e ';samesite=' eklemek) PHP 7.3+'ta setcookie()'nin
        // path içinde ';' gibi karakterleri ValueError ile reddetmesiyle artık güvenilir değil — bu
        // yüzden ham Set-Cookie header'ı, native setcookie()'nin ürettiğiyle birebir aynı
        // isim/değer/expiry/Max-Age/Path/HttpOnly/Secure'a ek olarak SameSite=Lax ile elle
        // oluşturuluyor. Hem PHP 7.2 hem 8.x'te aynı şekilde çalışır (doğrulandı).
        $__exp = time()+60*60*24*30;
        $__secure = !empty($_SERVER['HTTPS']) ? '; Secure' : '';
        header('Set-Cookie: acans_remember='.urlencode($userId.':'.$token)
            .'; Expires='.gmdate('D, d-M-Y H:i:s', $__exp).' GMT'
            .'; Max-Age='.(60*60*24*30)
            .'; Path=/; HttpOnly'.$__secure.'; SameSite=Lax', false);
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
            // SECURITY SPRINT-005 FAZ-1: remember-me ile otomatik girişte de kimlik doğrulandıktan
            // hemen sonra session id yenilenir (session fixation koruması).
            session_regenerate_id(true);
            // SECURITY SPRINT-005 FAZ-4: token rotasyonu — bu istekte kullanılan remember-me token
            // ARTIK GEÇERSİZ; remember_set() yeni bir token üretip DB+cookie'yi günceller (aynı
            // fonksiyon normal login'de de kullanılıyor, kod tekrarı yok).
            remember_set($u['id']);
        }
    }catch(Throwable $e){}
}
remember_check();

// Idle timeout: oturum açıksa, son aktivite zamanını güncelle. Bu SADECE require_login()'in
// timeout kontrolünden SONRA, sayfa erişiminin GEÇERLİ olduğu onaylandıktan sonra çalışmalı —
// burada koşulsuz çalıştırılırsa require_login() içindeki "time()-last_activity" farkı her
// istekte ~0 olur ve idle-timeout hiçbir zaman tetiklenmez (2026-07-03 denetiminde bulundu).
// require_login() zaten kendi içinde başarılı dönüşte last_activity'yi güncelliyor (aşağıya bak).

// İşlem kaydı (kim ne yaptı) — web genelinde aktif olsun (mobilde common.php yüklüyor)
if(is_file(__DIR__.'/activity_lib.php')) require_once __DIR__.'/activity_lib.php';
// DESIGN SYSTEM SPRINT 001 / PHASE A (2026-07-15) — yeni "ds-" foundation component yardımcıları.
// Saf ek: hiçbir mevcut ekran bu fonksiyonları çağırmıyor, boot.php'ye eklenmesi web+mobil
// (mobile/common.php zaten boot.php'yi require ediyor) her ikisine de erişim sağlar.
if(is_file(__DIR__.'/ds_lib.php')) require_once __DIR__.'/ds_lib.php';
// NAV-001B (2026-07-16) — navigasyon bilgi mimarisi (taksonomi/yetki filtresi), ds_lib.php'den
// bilinçli olarak AYRI (Product Owner kararı: ds_lib.php sadece görsel bileşen kütüphanesi).
if(is_file(__DIR__.'/nav_lib.php')) require_once __DIR__.'/nav_lib.php';
// user_prefs_lib.php önceden sadece dashboard.php/ajax_dashboard_order.php'nin kendi require'ıyla
// yükleniyordu — layout_top.php (compact nav) artık user_pref_get() çağırdığı için TÜM sayfalarda
// garanti yüklü olması gerekiyor, merkezi hale getirildi.
if(is_file(__DIR__.'/user_prefs_lib.php')) require_once __DIR__.'/user_prefs_lib.php';
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

// SECURITY SPRINT-004 FAZ-3A (2026-07-05): CSRF zorunlu kontrolü SADECE pilot grupta açıldı
// (users.php, sil.php — basename eşleşmesi yukarıdaki page_module_map() ile aynı desen, bu yüzden
// mobile/users.php'yi de otomatik kapsar; sil.php'nin mobil karşılığı yok). Sistem geneline HENÜZ
// yayılmadı — bkz. ilerideki FAZ'lar.
// FAZ-5A (CRM): contact_new.php, contact_view.php eklendi.
// FAZ-5B (Stok/Ürün): product_new.php, product_view.php, product_categories.php,
// product_taxonomy.php, stock_movement_new.php, brand_settings.php eklendi.
// FAZ-5C (İş/Görev): job_new.php, jobs.php, task_new.php, tasks.php, mytask_new.php, mytasks.php,
// uretim_new.php (mobil-only), group_new.php (mobil-only) eklendi.
// FAZ-5D (Mesajlaşma/Talep): messages.php, notes.php (mobil karşılığı mytasks.php'ye gömülü,
// FAZ-5C'de zaten enforced), request_new.php, requests.php, profile.php eklendi.
// FAZ-5E (Satış/Satın Alma): sales.php, purchase.php eklendi.
// FAZ-5F (Temizlik grubu): accounting_categories.php, check_note_view.php (mobil-only),
// report.php, ajax_quick_add.php (mobil-only karşılığı yok, web+mobil formlardan ortak
// çağrılıyor), wa_settings.php eklendi.
$__csrf_enforced_pages = ['users.php', 'sil.php', 'notifications.php', 'accounting.php', 'finance.php', 'finance_accounts.php', 'checks_notes.php', 'kasa.php', 'finance_new.php', 'finance_transfer.php', 'finance_account_view.php', 'payment.php', 'collection.php', 'transfer.php', 'account_view.php', 'movement_view.php', 'personnel_new.php', 'personnel_edit.php', 'personnel_view.php', 'sifre_sifirla.php', 'temizle_veri.php', 'trade_document_new.php', 'teklif.php', 'quote_approve.php', 'public_file.php', 'wa_send_now.php', 'job_view.php', 'task_view.php', 'work_view.php', 'contact_new.php', 'contact_view.php', 'product_new.php', 'product_view.php', 'product_categories.php', 'product_taxonomy.php', 'stock_movement_new.php', 'brand_settings.php', 'job_new.php', 'jobs.php', 'task_new.php', 'tasks.php', 'mytask_new.php', 'mytasks.php', 'uretim_new.php', 'group_new.php', 'messages.php', 'notes.php', 'request_new.php', 'requests.php', 'profile.php', 'sales.php', 'purchase.php', 'accounting_categories.php', 'check_note_view.php', 'report.php', 'ajax_quick_add.php', 'ajax_dashboard_order.php', 'wa_settings.php', 'wa_conversation_view.php',
    // NAV-001B (2026-07-16): yeni endpoint baştan CSRF-korumalı — ajax_dashboard_order.php'nin
    // eksikliği buraya miras bırakılmadı (Product Owner kararı, bkz. memory/backlog.md PDP/SEC notu).
    'ajax_nav_prefs.php'];
if($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($__page, $__csrf_enforced_pages, true)){
    csrf_verify();
}
?>