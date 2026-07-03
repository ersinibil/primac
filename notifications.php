<?php
require_once __DIR__.'/boot.php';
require_login();
require_once __DIR__.'/notifications_lib.php';

$pdo=db();
$ME=(int)(current_user()['id'] ?? 0);

// Sil/temizle işlemleri (parite, 2026-07-03 Sprint-001'de mobil ile eşitlendi) — SADECE oturum
// sahibinin kendi görünümünü etkiler: kişisel bildirim sahiplik kontrollü fiziksel silinir, genel
// (target_user_id=NULL) bildirim asla fiziksel silinmez, sadece BU kullanıcı için gizlenir
// (bkz. notifications_lib.php).
if(isset($_GET['del'])){ notif_dismiss($pdo,$ME,(int)$_GET['del']); header('Location: notifications.php'); exit; }
if(isset($_GET['clear'])){ notif_dismiss_all_read($pdo,$ME); header('Location: notifications.php'); exit; }
if(isset($_GET['clearall'])){ notif_dismiss_all($pdo,$ME); header('Location: notifications.php'); exit; }

if(isset($_GET['read'])){
    $id=(int)$_GET['read'];
    notif_mark_read($pdo,$ME,$id);
    if(!empty($_GET['go'])){
        $go=$_GET['go'];
        if(preg_match('#^(https?:)?//#i',$go)) $go='dashboard.php'; // open redirect koruması: sadece site-içi göreli path
        // Bazı bildirimler mobil-sadece bir sayfaya link verebilir (mytasks.php artık web'de de
        // var, ama gelecekte benzer mobil-özel sayfalar olabilir) — web kökünde böyle bir dosya
        // yoksa 404 veriyordu. Bu, TEK giriş noktası
        // (herkes buradan geçiyor: dashboard.php'nin "Detay" butonu dahil) olduğu için düzeltme
        // burada merkezi olarak yapılıyor (2026-07-03: dashboard.php'nin kendi kopyası bu kontrolü
        // hiç yapmıyordu, aynı 404 orada da tekrarlanıyordu).
        $goFile=explode('?',$go,2)[0];
        if($goFile!=='' && strpos($go,'mobile/')!==0 && !file_exists(__DIR__.'/'.$goFile) && file_exists(__DIR__.'/mobile/'.$goFile)){
            $go='mobile/'.$go;
        }
        header("Location: ".$go);
        exit;
    }
}

if(isset($_GET['all_read'])){
    notif_mark_all_read($pdo,$ME);
    header("Location: notifications.php");
    exit;
}

require_once __DIR__.'/layout_top.php';

$rows=[];
try{
    $rows=notif_list_for_user($pdo,$ME,100);
}catch(Throwable $e){
    echo "<div class='alert'>".h($e->getMessage())."</div>";
}
?>

<div class="panel-head">
<h1>Bildirim Merkezi</h1>
<div style="display:flex;gap:8px;flex-wrap:wrap">
<a class="btn secondary" href="notifications.php?all_read=1">Tümünü Okundu Yap</a>
<a class="btn secondary" href="notifications.php?clear=1">Okunanları Sil</a>
<a class="btn secondary" href="notifications.php?clearall=1" onclick="return confirm('Tüm bildirimlerin görünümden kaldırılmasını istediğine emin misin?')">Tümünü Sil</a>
</div>
</div>

<section class="panel">
<?php foreach($rows as $n):
$go=$n['action_url'] ?: 'dashboard.php';
// Bazı bildirimler mobil-sadece bir sayfaya link verebilir (mytasks.php artık web'de de var, ama
// gelecekte benzer mobil-özel sayfalar olabilir) — web kökünde böyle bir dosya yoksa 404 veriyordu
// (2026-07-03 kullanıcı bildirimi). Hedef dosya web kökünde yoksa ama mobile/ altında varsa oraya yönlendir.
$goFile=explode('?',$go,2)[0];
if($goFile!=='' && strpos($go,'mobile/')!==0 && !file_exists(__DIR__.'/'.$goFile) && file_exists(__DIR__.'/mobile/'.$goFile)){
    $go='mobile/'.$go;
}
$readUrl='notifications.php?read='.$n['id'].'&go='.urlencode($go);
?>
<div class="notice-card <?=$n['effective_is_read']?'':'unread'?>">
    <div class="panel-head">
        <div>
            <b><?=h($n['title'])?></b>
            <?=$n['effective_is_read']?badge('Okundu','gray'):badge('Yeni','orange')?>
            <br>
            <span class="muted"><?=h($n['created_at'])?></span>
        </div>
        <div style="display:flex;gap:6px">
            <a class="btn small" href="<?=h($readUrl)?>">Aç</a>
            <a class="btn small secondary" href="notifications.php?del=<?=(int)$n['id']?>" title="Sil">🗑️</a>
        </div>
    </div>
    <p><?=nl2br(h($n['message']))?></p>
</div>
<?php endforeach; ?>
<?php if(!$rows): ?><p class="muted">Henüz bildirim yok.</p><?php endif; ?>
</section>

<?php require_once __DIR__.'/layout_bottom.php'; ?>
