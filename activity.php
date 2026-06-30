<?php
require_once __DIR__.'/layout_top.php';
require_once __DIR__.'/activity_lib.php';

$module=$_GET['module'] ?? '';
?>

<div class="panel-head">
<h1>Son İşlemler</h1>
<a class="btn secondary" href="dashboard.php">Ana Sayfa</a>
</div>

<section class="panel">
<div class="panel-head">
<h2>İşlem Akışı</h2>
<div class="actions">
<a class="btn small secondary" href="activity.php">Tümü</a>
<a class="btn small secondary" href="activity.php?module=İşler">İşler</a>
<a class="btn small secondary" href="activity.php?module=Cari">Cari</a>
<a class="btn small secondary" href="activity.php?module=Finans">Finans</a>
<a class="btn small secondary" href="activity.php?module=Stok">Stok</a>
<a class="btn small secondary" href="activity.php?module=Personel">Personel</a>
<a class="btn small secondary" href="activity.php?module=Telegram">Telegram</a>
</div>
</div>

<?php
try{
    activity_install();
    if($module){
        $s=db()->prepare("SELECT * FROM activity_logs WHERE module=? ORDER BY id DESC LIMIT 150");
        $s->execute([$module]);
        $rows=$s->fetchAll();
    }else{
        $rows=activity_recent(150);
    }
    activity_render_list($rows);
}catch(Throwable $e){
    echo "<div class='alert'>".h($e->getMessage())."</div>";
}
?>
</section>

<?php require_once __DIR__.'/layout_bottom.php'; ?>
