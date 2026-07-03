<?php
require_once 'common.php';
$pdo=db();
// Silme / temizleme işlemleri (çıktıdan önce)
if(isset($_GET['del'])){ try{ $pdo->prepare("DELETE FROM internal_notifications WHERE id=?")->execute([(int)$_GET['del']]); }catch(Throwable $e){} header('Location: notifications.php'); exit; }
if(isset($_GET['clear'])){ try{ $pdo->exec("DELETE FROM internal_notifications WHERE is_read=1"); }catch(Throwable $e){} header('Location: notifications.php'); exit; }
if(isset($_GET['clearall'])){ try{ $pdo->exec("DELETE FROM internal_notifications"); }catch(Throwable $e){} header('Location: notifications.php'); exit; }
// Görüntülenince okundu yap (sadece bana ait + genel)
try{ $pdo->prepare("UPDATE internal_notifications SET is_read=1 WHERE is_read=0 AND (target_user_id IS NULL OR target_user_id=?)")->execute([$ME]); }catch(Throwable $e){}

topx('Bildirimler');
?>
<div class="panel" style="display:flex;gap:8px;padding:10px">
  <a class="btn" style="flex:1;background:#334155;color:#fff" href="notifications.php?clear=1">Okunanları Sil</a>
  <a class="btn" style="flex:1;background:#7f1d1d;color:#fff" href="notifications.php?clearall=1" onclick="return confirm('Tüm bildirimler silinsin mi?')">Tümünü Sil</a>
</div>
<?php
try{
  $st=$pdo->prepare("SELECT * FROM internal_notifications WHERE (target_user_id IS NULL OR target_user_id=?) ORDER BY id DESC LIMIT 80"); $st->execute([$ME]); $rows=$st->fetchAll();
  if(!$rows){ echo '<div class="panel muted" style="text-align:center">Bildirim yok 🔕</div>'; }
  foreach($rows as $n){
    $go=!empty($n['action_url']) ? $n['action_url'] : 'index.php';
    // Bazı bildirimler (örn. daily_reminder_lib.php) sadece web kökünde var olan bir sayfaya
    // (gunluk_rapor.php gibi) link veriyor — mobile/ altında aynı isimde dosya yoksa burada
    // olduğu gibi kullanılınca 404 veriyordu (2026-07-03 kullanıcı bildirimi, bkz. 62cd110'un
    // web→mobil karşılığı). Hedef dosya mobile/ altında yoksa ama web kökünde varsa oraya çık.
    $goFile=explode('?',$go,2)[0];
    if($goFile!=='' && strpos($go,'../')!==0 && !file_exists(__DIR__.'/'.$goFile) && file_exists(__DIR__.'/../'.$goFile)){
        $go='../'.$go;
    }
    echo '<div class="item" style="display:flex;justify-content:space-between;align-items:flex-start;gap:10px">';
    echo '<div style="flex:1;min-width:0"><b>'.htmlspecialchars($n['title']).'</b>';
    if(!empty($n['message'])) echo '<br><span class="muted">'.nl2br(htmlspecialchars($n['message'])).'</span>';
    echo '<br><small class="muted">'.htmlspecialchars($n['created_at']??'').'</small></div>';
    echo '<div style="flex:0 0 auto;display:flex;flex-direction:column;align-items:center;gap:8px">';
    echo '<a href="'.htmlspecialchars($go).'" class="btn" style="padding:6px 12px;font-size:13px">Aç</a>';
    echo '<a href="notifications.php?del='.(int)$n['id'].'" style="color:#f87171;font-size:20px;text-decoration:none">🗑️</a>';
    echo '</div>';
    echo '</div>';
  }
}catch(Throwable $e){ echo '<div class="err">'.htmlspecialchars($e->getMessage()).'</div>'; }
botx();
