<?php
require_once __DIR__.'/boot.php';
require_login();

$pdo=db();
$ME=(int)(current_user()['id'] ?? 0);

if(isset($_GET['read'])){
    $id=(int)$_GET['read'];
    $stmt=$pdo->prepare("UPDATE internal_notifications SET is_read=1 WHERE id=? AND (target_user_id IS NULL OR target_user_id=?)");
    $stmt->execute([$id,$ME]);
    if(!empty($_GET['go'])){
        $go=$_GET['go'];
        if(preg_match('#^(https?:)?//#i',$go)) $go='dashboard.php'; // open redirect koruması: sadece site-içi göreli path
        // Bazı bildirimler (örn. mobile/task_new.php) mobil-sadece bir sayfaya (mytasks.php gibi)
        // link veriyor — web kökünde böyle bir dosya yoksa 404 veriyordu. Bu, TEK giriş noktası
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
    $stmt=$pdo->prepare("UPDATE internal_notifications SET is_read=1 WHERE is_read=0 AND (target_user_id IS NULL OR target_user_id=?)");
    $stmt->execute([$ME]);
    header("Location: notifications.php");
    exit;
}

require_once __DIR__.'/layout_top.php';

$rows=[];
try{
    $stmt=$pdo->prepare("SELECT * FROM internal_notifications WHERE (target_user_id IS NULL OR target_user_id=?) ORDER BY is_read ASC, id DESC LIMIT 100");
    $stmt->execute([$ME]);
    $rows=$stmt->fetchAll();
}catch(Throwable $e){
    echo "<div class='alert'>".h($e->getMessage())."</div>";
}
?>

<div class="panel-head">
<h1>Bildirim Merkezi</h1>
<a class="btn secondary" href="notifications.php?all_read=1">Tümünü Okundu Yap</a>
</div>

<section class="panel">
<?php foreach($rows as $n): 
$go=$n['action_url'] ?: 'dashboard.php';
// Bazı bildirimler (örn. mobile/task_new.php) mobil-sadece bir sayfaya (mytasks.php gibi) link veriyor —
// web kökünde böyle bir dosya yoksa 404 veriyordu (2026-07-03 kullanıcı bildirimi). Hedef dosya web
// kökünde yoksa ama mobile/ altında varsa oraya yönlendir.
$goFile=explode('?',$go,2)[0];
if($goFile!=='' && strpos($go,'mobile/')!==0 && !file_exists(__DIR__.'/'.$goFile) && file_exists(__DIR__.'/mobile/'.$goFile)){
    $go='mobile/'.$go;
}
$readUrl='notifications.php?read='.$n['id'].'&go='.urlencode($go);
?>
<div class="notice-card <?=$n['is_read']?'':'unread'?>">
    <div class="panel-head">
        <div>
            <b><?=h($n['title'])?></b>
            <?=badge($n['type'], $n['severity']==='high'?'red':($n['severity']==='warning'?'yellow':'blue'))?>
            <?=$n['is_read']?badge('Okundu','gray'):badge('Yeni','orange')?>
            <br>
            <span class="muted"><?=h($n['created_at'])?></span>
        </div>
        <a class="btn small" href="<?=h($readUrl)?>">Aç</a>
    </div>
    <p><?=nl2br(h($n['message']))?></p>
</div>
<?php endforeach; ?>
<?php if(!$rows): ?><p class="muted">Henüz bildirim yok.</p><?php endif; ?>
</section>

<?php require_once __DIR__.'/layout_bottom.php'; ?>
