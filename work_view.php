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
    echo ds_alert('danger','İş bulunamadı');
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

<?php
$__wvActions = ds_button('İş Motoru','work_center.php','secondary','','',true)
    . ds_button('Klasik İş Kartı','job_view.php?id='.$id,'secondary','','',true);
ds_page_header('📋 '.$j['job_no'].' - '.$j['title'], '', '', $__wvActions, false, true);
?>

<?php if($error): ?><?=ds_alert('danger',$error)?><?php endif; ?>
<?php if($ok): ?><?=ds_alert('success',$ok)?><?php endif; ?>

<div class="df-stat-row">
    <div class="df-stat"><span>Müşteri</span><strong><?=h($j['customer_name'] ?: '-')?></strong></div>
    <div class="df-stat"><span>Sorumlu</span><strong><?=h($j['responsible_name'] ?: '-')?></strong></div>
    <div class="df-stat"><span>Termin</span><strong><?=h($j['due_date'] ?: '-')?></strong></div>
    <div class="df-stat"><span>İlerleme</span><strong>%<?=$progress?></strong></div>
</div>

<section class="df-card" style="margin-top:var(--df-space-4)">
    <h2 class="df-section-title">İş Durumu</h2>
    <form method="post" style="display:flex;gap:8px;flex-wrap:wrap">
        <select name="job_status" style="flex:1;min-width:200px">
            <?php foreach(['Yeni','Planlandı','Devam Ediyor','Dışarıda','Montajda','Onay Bekliyor','Teslim Edildi','Tamamlandı','İptal'] as $s): ?>
                <option <?=$j['status']===$s?'selected':''?>><?=$s?></option>
            <?php endforeach; ?>
        </select>
        <button class="df-btn df-btn--primary">Durumu Kaydet</button>
    </form>
</section>

<section class="df-card" style="margin-top:var(--df-space-4)">
    <h2 class="df-section-title">Görev Akışı</h2>
    <div class="df-table-wrap"><table class="df-table">
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
            <td><?=ds_badge($c['status'],work_engine_status_tone($c['status']))?></td>
            <td><?=h($c['due_date'] ?: '-')?></td>
            <td>
                <form method="post" style="display:flex;gap:6px;align-items:center">
                    <input type="hidden" name="check_id" value="<?=$c['id']?>">
                    <select name="check_status" style="margin:0">
                        <?php foreach(['Bekliyor','Devam Ediyor','Dışarıda','Onay Bekliyor','Tamamlandı'] as $s): ?>
                            <option <?=$c['status']===$s?'selected':''?>><?=$s?></option>
                        <?php endforeach; ?>
                    </select>
                    <button class="df-btn df-btn--secondary df-btn--sm">Kaydet</button>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
</section>

<section class="df-card" style="margin-top:var(--df-space-4)">
    <h2 class="df-section-title">Aktivite Ekle</h2>
    <form method="post" class="df-form-grid-2">
        <input type="hidden" name="new_event" value="1">
        <?php
        $__evOpts='';
        foreach(['Not','Personel','Dış Atölye','Müşteri','Finans','Stok','Teslim'] as $__ev){ $__evOpts.='<option>'.$__ev.'</option>'; }
        ds_form_field('Tip', '<select name="event_type">'.$__evOpts.'</select>');
        ds_form_field('Başlık', '<input name="event_title" required>');
        ?>
        <div class="df-form-span-2"><?php ds_form_field('Açıklama', '<textarea name="event_desc" rows="3"></textarea>'); ?></div>
        <div class="df-form-span-2"><button class="df-btn df-btn--primary">Aktivite Ekle</button></div>
    </form>
</section>

<section class="df-card" style="margin-top:var(--df-space-4)">
    <h2 class="df-section-title">İş Zaman Tüneli</h2>
    <?php
    $ev=$pdo->prepare("SELECT * FROM work_events WHERE job_id=? ORDER BY id DESC LIMIT 50");
    $ev->execute([$id]);
    $events=$ev->fetchAll();
    foreach($events as $e):
    ?>
    <div class="df-card" style="margin-top:var(--df-space-3);background:var(--df-surface-sunken)">
        <b><?=h($e['title'])?></b>
        <?=ds_badge($e['event_type'],'blue')?>
        <br><span class="df-muted" style="font-size:12px"><?=h($e['created_at'])?></span>
        <?php if($e['description']): ?><p style="margin:var(--df-space-2) 0 0"><?=nl2br(h($e['description']))?></p><?php endif; ?>
    </div>
    <?php endforeach; ?>
    <?php if(!$events): ?><p class="df-muted">Henüz aktivite yok.</p><?php endif; ?>
</section>

<style>
body.nav-compact .df-form-grid-2{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:0 var(--df-space-4)}
body.nav-compact .df-form-span-2{grid-column:1 / -1}
body.nav-compact .df-stat-row{display:flex;flex-wrap:wrap;gap:var(--df-space-3);margin:var(--df-space-4) 0}
body.nav-compact .df-stat{flex:1;min-width:120px;background:var(--df-surface);border:1px solid var(--df-hairline);border-radius:var(--df-radius-md);padding:var(--df-space-3);display:flex;flex-direction:column;gap:4px}
body.nav-compact .df-stat span{font-size:var(--df-type-caption-size);color:var(--df-ink-500)}
body.nav-compact .df-stat strong{font-size:var(--df-type-subtitle-size);color:var(--df-ink-900)}
body.nav-compact .df-section-title{font-size:var(--df-type-section-size);font-weight:var(--df-type-section-weight);color:var(--df-ink-900);margin:0 0 var(--df-space-3)}
@media(max-width:640px){body.nav-compact .df-form-grid-2{grid-template-columns:1fr}}
</style>

<?php require_once __DIR__.'/layout_bottom.php'; ?>
