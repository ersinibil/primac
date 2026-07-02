<?php
/* ACANS OS — Sunucu temizliği
 * Eski install_*.php, teşhis ve çöp dosyalarını siler (güvenlik + düzen).
 * Migration sistemi (database/migrations + migrate.php) bunların yerine geçti.
 * Güvenlik: yönetici girişi VEYA ?key=acans-migrate-2026. İş bitince bu dosyayı da sil.
 */
require_once __DIR__.'/boot.php';
header('Content-Type:text/html;charset=utf-8');
$KEY='acans-migrate-2026';
$isAdmin = !empty($_SESSION['user']) && in_array(($_SESSION['user']['role']??''),['admin','yonetici','yönetici'],true);
if(!$isAdmin && (($_GET['key']??'')!==$KEY)) exit('Yetki yok. ?key=... ekle.');

$d=__DIR__;
echo '<meta charset=utf-8><body style="font-family:-apple-system,sans-serif;max-width:720px;margin:24px auto;padding:0 16px;line-height:1.8"><h2>🧹 Sunucu Temizliği</h2>';

// Silinecek bilinen çöpler
$targets=[];
foreach(glob($d.'/install_*.php') as $f) $targets[]=$f;          // tüm install dosyaları
$single=['push_test.php','kontrol.php','iz.php','bak.php','kaynak.php',
 'fix_login.php','ac_extract.php','dev_check.php','ac.php','guncelleme.zip','assets','migrate.php',
 'layout_top_patch_note.txt','v9_not.txt','v11_not.txt','v16_not.txt','v19_not.txt','RAPOR.md'];
foreach($single as $s){ if(file_exists($d.'/'.$s)) $targets[]=$d.'/'.$s; }
// saatli ac kopyaları + eski zip kopyaları
foreach(glob($d.'/ac *.php') as $f) $targets[]=$f;
foreach(glob($d.'/guncelleme*.zip') as $f) $targets[]=$f;

$del=0;$fail=0;
echo '<ul>';
foreach(array_unique($targets) as $f){
  $n=str_replace($d.'/','',$f);
  if(@unlink($f)){ echo "<li style='color:#166534'>🗑️ silindi: ".htmlspecialchars($n)."</li>"; $del++; }
  else { echo "<li style='color:#b91c1c'>❌ silinemedi: ".htmlspecialchars($n)."</li>"; $fail++; }
}
if(!$targets) echo '<li style="color:#94a3b8">Silinecek çöp bulunamadı (zaten temiz).</li>';
echo '</ul>';
echo "<p style='background:#dcfce7;color:#166534;padding:12px;border-radius:12px'>Silinen: <b>$del</b>".($fail?" · hata: $fail":"")."</p>";
// Kendini de sil → elle uğraşma kalmasın
$self=@unlink(__FILE__);
echo $self
  ? "<p style='color:#166534;font-weight:700'>🔒 temizle.php kendini sildi. Sunucu tertemiz — başka bir şey silmene gerek yok. <a href='mobile/index.php'>Uygulamayı aç</a></p>"
  : "<p style='color:#b91c1c'>temizle.php'yi elle sil.</p>";
echo "</body>";
