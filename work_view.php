<?php
require_once __DIR__.'/layout_top.php';
require_once __DIR__.'/work_engine.php';

$id=(int)($_GET['id'] ?? 0);
$pdo=db();
$error='';
$ok='';

$stmt=$pdo->prepare("SELECT j.*, c.name customer_name, p.name responsible_name
    FROM jobs j
    LEFT JOIN contacts c ON c.id=j.customer_id
    LEFT JOIN personnel p ON p.id=j.responsible_personnel_id
    WHERE j.id=?");
$stmt->execute([$id]);
$j=$stmt->fetch();

if(!$j){
    echo "<h1>İş bulunamadı</h1>";
    require __DIR__.'/layout_bottom.php';
    exit;
}

work_engine_seed_checklist($id,$j['job_type']);

if($_SERVER['REQUEST_METHOD']==='POST'){
    try{
        if(isset($_POST['check_id'])){
            $cid=(int)$_POST['check_id'];
            $status=$_POST['check_status'];
            $pdo->prepare("UPDATE work_checklists SET status=?, completed_at=IF(?='Tamamlandı',NOW(),completed_at) WHERE id=? AND job_id=?")
                ->execute([$status,$status,$cid,$id]);
            work_engine_add_event($id,'Görev durumu güncellendi',$status,'Görev');
            $progress=work_engine_progress($id);
            $pdo->prepare("UPDATE jobs SET work_progress=?, updated_at=NOW() WHERE id=?")->execute([$progress,$id]);
            $ok='Görev güncellendi.';
        }

        if(isset($_POST['new_event'])){
            $title=trim($_POST['event_title']);
            $desc=trim($_POST['event_desc']);
            if($title==='') throw new Exception('Başlık boş olamaz.');
            work_engine_add_event($id,$title,$desc,$_POST['event_type']);
            $ok='Aktivite eklendi.';
        }

        if(isset($_POST['job_status'])){
            $pdo->prepare("UPDATE jobs SET status=?, work_status=?, updated_at=NOW() WHERE id=?")
                ->execute([$_POST['job_status'],$_POST['job_status'],$id]);
            work_engine_add_event($id,'İş durumu güncellendi',$_POST['job_status'],'Durum');
            $ok='İş durumu güncellendi.';
        }

    }catch(Throwable $e){
        $error=$e->getMessage();
    }

    $stmt->execute([$id]);
    $j=$stmt->fetch();
}

$progress=work_engine_progress($id);
?>

<div class="panel-head">
    <h1>📋 <?=h($j['job_no'])?> - <?=h($j['title'])?></h1>
    <div class="actions">
        <a class="btn secondary" href="work_center.php">İş Motoru</a>
        <a class="btn secondary" href="job_view.php?id=<?=$id?>">Klasik İş Kartı</a>
    </div>
</div>

<?php if($error): ?><div class="alert"><?=h($error)?></div><?php endif; ?>
<?php if($ok): ?><div class="ok"><?=h($ok)?></div><?php endif; ?>

<div class="cards">
    <div class="card"><small>Müşteri</small><strong><?=h($j['customer_name'] ?: '-')?></strong></div>
    <div class="card"><small>Sorumlu</small><strong><?=h($j['responsible_name'] ?: '-')?></strong></div>
    <div class="card"><small>Termin</small><strong><?=h($j['due_date'] ?: '-')?></strong></div>
    <div class="card"><small>İlerleme</small><strong>%<?=$progress?></strong></div>
</div>

<section class="panel">
    <div class="panel-head"><h2>İş Durumu</h2></div>
    <form method="post" class="inline">
        <select name="job_status">
            <?php foreach(['Yeni','Planlandı','Devam Ediyor','Dışarıda','Montajda','Onay Bekliyor','Teslim Edildi','Tamamlandı','İptal'] as $s): ?>
                <option <?=$j['status']===$s?'selected':''?>><?=$s?></option>
            <?php endforeach; ?>
        </select>
        <button>Durumu Kaydet</button>
    </form>
</section>

<section class="panel">
    <div class="panel-head"><h2>Görev Akışı</h2></div>
    <table>
        <thead><tr><th>Sıra</th><th>Görev</th><th>Durum</th><th>Termin</th><th>İşlem</th></tr></thead>
        <tbody>
        <?php
        $cs=$pdo->prepare("SELECT * FROM work_checklists WHERE job_id=? ORDER BY sort_order,id");
        $cs->execute([$id]);
        foreach($cs->fetchAll() as $c):
        ?>
        <tr>
            <td><?=h($c['sort_order'])?></td>
            <td><b><?=h($c['title'])?></b></td>
            <td><?=badge($c['status'],work_engine_status_tone($c['status']))?></td>
            <td><?=h($c['due_date'] ?: '-')?></td>
            <td>
                <form method="post" class="inline">
                    <input type="hidden" name="check_id" value="<?=$c['id']?>">
                    <select name="check_status">
                        <?php foreach(['Bekliyor','Devam Ediyor','Dışarıda','Onay Bekliyor','Tamamlandı'] as $s): ?>
                            <option <?=$c['status']===$s?'selected':''?>><?=$s?></option>
                        <?php endforeach; ?>
                    </select>
                    <button class="btn small">Kaydet</button>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</section>

<section class="panel">
    <div class="panel-head"><h2>Aktivite Ekle</h2></div>
    <form method="post" class="form-grid">
        <input type="hidden" name="new_event" value="1">
        <label>Tip
            <select name="event_type">
                <option>Not</option>
                <option>Personel</option>
                <option>Dış Atölye</option>
                <option>Müşteri</option>
                <option>Finans</option>
                <option>Stok</option>
                <option>Teslim</option>
            </select>
        </label>
        <label>Başlık<input name="event_title" required></label>
        <label class="full">Açıklama<textarea name="event_desc" rows="3"></textarea></label>
        <button class="btn">Aktivite Ekle</button>
    </form>
</section>

<section class="panel">
    <div class="panel-head"><h2>İş Zaman Tüneli</h2></div>
    <?php
    $ev=$pdo->prepare("SELECT * FROM work_events WHERE job_id=? ORDER BY id DESC LIMIT 50");
    $ev->execute([$id]);
    $events=$ev->fetchAll();
    foreach($events as $e):
    ?>
    <div class="notice-card">
        <b><?=h($e['title'])?></b>
        <?=badge($e['event_type'],'blue')?>
        <br><span class="muted"><?=h($e['created_at'])?></span>
        <?php if($e['description']): ?><p><?=nl2br(h($e['description']))?></p><?php endif; ?>
    </div>
    <?php endforeach; ?>
    <?php if(!$events): ?><p class="muted">Henüz aktivite yok.</p><?php endif; ?>
</section>

<?php require_once __DIR__.'/layout_bottom.php'; ?>
