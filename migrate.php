<?php
/* ACANS OS — Migration çalıştırıcı
 * database/migrations/*.sql dosyalarını SIRAYLA, BİR KEZ uygular.
 * schema_migrations tablosunda uygulananları takip eder. Idempotent: tekrar çalıştırmak güvenli.
 * Tüm DDL "IF NOT EXISTS" olduğu için MEVCUT prod veriyi BOZMAZ.
 *
 * Güvenlik: yönetici girişi VEYA ?key=<MIGRATE_KEY>. İş bitince bu dosyayı sil.
 */
require_once __DIR__.'/boot.php';
header('Content-Type:text/html;charset=utf-8');

$MIGRATE_KEY = 'acans-migrate-2026';
$pdo = db();

// Yetki: admin oturumu ya da key. (Taze kurulumda kullanıcı yok → key ile.)
$isAdmin = !empty($_SESSION['user']) && in_array(($_SESSION['user']['role']??''),['admin','yonetici','yönetici'],true);
$keyOk   = (($_GET['key'] ?? '') === $MIGRATE_KEY);
if(!$isAdmin && !$keyOk){
  exit('<meta charset=utf-8><body style="font-family:sans-serif;padding:24px">Yetki yok. <code>?key=...</code> ile çalıştır ya da yönetici gir.</body>');
}

function run_sql_file($pdo,$path,&$err){
  $sql = file_get_contents($path);
  $sql = preg_replace('/^\s*--.*$/m','',$sql);        // yorum satırlarını at
  $stmts = array_filter(array_map('trim', explode(';',$sql)));
  // Idempotent hatalar (zaten var) → yok say: 1050 tablo, 1060 kolon, 1061 index, 1091 yok
  $ignore=['1050','1060','1061','1091'];
  foreach($stmts as $s){
    if($s==='') continue;
    try{ $pdo->exec($s); }
    catch(Throwable $e){
      $code=$e->errorInfo[1] ?? 0;
      if(in_array((string)$code,$ignore,true)) continue; // zaten uygulanmış kolon/tablo
      $err = $e->getMessage().' :: '.mb_substr($s,0,80); return false;
    }
  }
  return true;
}

echo '<meta charset=utf-8><body style="font-family:-apple-system,sans-serif;max-width:720px;margin:24px auto;padding:0 16px;line-height:1.7">';
echo '<h2>🗄️ ACANS OS — Migration</h2>';

// Takip tablosu
$pdo->exec("CREATE TABLE IF NOT EXISTS schema_migrations(
  filename VARCHAR(190) NOT NULL PRIMARY KEY,
  applied_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$applied = [];
foreach($pdo->query("SELECT filename FROM schema_migrations")->fetchAll(PDO::FETCH_COLUMN) as $f) $applied[$f]=1;

$files = glob(__DIR__.'/database/migrations/*.sql');
sort($files);
if(!$files){ echo '<p style="color:#b91c1c">database/migrations/ boş veya yok.</p>'; exit; }

echo '<ul>';
$ran=0; $skip=0; $fail=0;
foreach($files as $path){
  $name = basename($path);
  if(isset($applied[$name])){ echo "<li style='color:#94a3b8'>⏭️ $name — zaten uygulanmış</li>"; $skip++; continue; }
  $err=null;
  if(run_sql_file($pdo,$path,$err)){
    $st=$pdo->prepare("INSERT INTO schema_migrations(filename) VALUES(?)"); $st->execute([$name]);
    echo "<li style='color:#166534'>✅ $name — uygulandı</li>"; $ran++;
  } else {
    echo "<li style='color:#b91c1c'>❌ $name — HATA: ".htmlspecialchars($err)."</li>"; $fail++;
    break; // hata olursa dur (sıralı tutarlılık)
  }
}
echo '</ul>';

// Taze kurulum: hiç kullanıcı yoksa varsayılan admin
$seedMsg='';
try{
  $cnt=(int)$pdo->query("SELECT COUNT(*) c FROM app_users")->fetch()['c'];
  if($cnt===0){
    $hash=password_hash('admin123',PASSWORD_DEFAULT);
    $pdo->prepare("INSERT INTO app_users(username,full_name,password_hash,role,active) VALUES('admin','Yönetici',?,'admin',1)")->execute([$hash]);
    $seedMsg='<p style="background:#fef3c7;color:#92400e;padding:12px;border-radius:12px">👤 Taze kurulum: varsayılan yönetici oluşturuldu → kullanıcı <b>admin</b> / şifre <b>admin123</b> (giriş yapıp HEMEN değiştir).</p>';
  }
}catch(Throwable $e){}

echo "<p style='background:#dcfce7;color:#166534;padding:12px;border-radius:12px'>Bitti. Uygulanan: <b>$ran</b> · atlanan: <b>$skip</b>".($fail?" · <b style='color:#b91c1c'>hata: $fail</b>":"")."</p>";
echo $seedMsg;
// Güvenlik: hata yoksa kendini sil (elle silmeye gerek kalmasın)
$self=false; if($fail===0){ $self=@unlink(__FILE__); }
echo $self
  ? "<p style='color:#166534'>🔒 migrate.php kendini sildi. Sırada: <a href='temizle.php?key=acans-migrate-2026'>temizle.php</a></p>"
  : "<p style='color:#94a3b8'>migrate.php'yi sunucudan elle sil. Sırada: <a href='temizle.php?key=acans-migrate-2026'>temizle.php</a></p>";
echo "</body>";
