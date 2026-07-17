<?php
require_once __DIR__.'/layout_top.php';
require_once __DIR__.'/work_engine.php';

$filter=$_GET['filter'] ?? 'open';
$where=[];
$title='İş Motoru';

if($filter==='today'){
    $where[]="j.due_date=CURDATE() AND j.status NOT IN ('Tamamlandı','İptal','Teslim Edildi')";
    $title='Bugün Teslim Edilecek İşler';
}elseif($filter==='late'){
    $where[]="j.due_date IS NOT NULL AND j.due_date<CURDATE() AND j.status NOT IN ('Tamamlandı','İptal','Teslim Edildi')";
    $title='Geciken İşler';
}elseif($filter==='external'){
    $where[]="j.job_type IN ('dis_atolye','tedarikcide_uretim') AND j.status NOT IN ('Tamamlandı','İptal','Teslim Edildi')";
    $title='Dışarıdaki İşler';
}elseif($filter==='approval'){
    $where[]="EXISTS(SELECT 1 FROM job_files f WHERE f.job_id=j.id AND f.approval_status='Müşteri Onayı Bekliyor')";
    $title='Müşteri Onayı Bekleyen İşler';
}elseif($filter==='collection'){
    $where[]="j.collection_status='Bekliyor' AND j.status IN ('Tamamlandı','Teslim Edildi')";
    $title='Tahsilat Bekleyen İşler';
}else{
    $where[]="j.status NOT IN ('Tamamlandı','İptal','Teslim Edildi')";
    $title='Açık İşler';
}

$sqlWhere=$where ? 'WHERE '.implode(' AND ',$where) : '';
?>

<style>
.command-grid{display:grid;grid-template-columns:repeat(4,minmax(160px,1fr));gap:var(--df-space-4);margin:0 0 var(--df-space-5)}
.command-card{display:block;text-decoration:none;background:var(--df-surface);border-radius:var(--df-radius-lg);padding:var(--df-space-4);box-shadow:var(--df-elevation-raised);border:1px solid var(--df-hairline);color:var(--df-ink-900);transition:transform .12s ease,box-shadow .12s ease}
.command-card:hover{transform:translateY(-2px);box-shadow:var(--df-elevation-floating)}
.command-card small{display:block;color:var(--df-ink-500);font-weight:800;font-size:11px;text-transform:uppercase;letter-spacing:.06em}
.command-card strong{display:block;font-size:30px;margin:8px 0;line-height:1}
.command-card span{color:var(--df-ink-500);font-size:13px}
.command-card.blue{border-left:6px solid var(--df-accent)}
.command-card.red{border-left:6px solid var(--df-danger)}
.command-card.yellow{border-left:6px solid var(--df-warning)}
.command-card.purple{border-left:6px solid #8b5cf6}
@media(max-width:960px){.command-grid{grid-template-columns:1fr}}
</style>

<?php
$__actions = ds_button('+ Yeni İş', 'job_new.php', 'primary', '', '', true)
    . ds_button('Komuta Merkezi', 'dashboard.php', 'secondary', '', '', true);
ds_page_header($title, ds_icon('briefcase',24), '', $__actions, false, true);
?>

<section class="command-grid">
    <a class="command-card blue" href="work_center.php?filter=open"><small>Açık İşler</small><strong><?=safe_count("SELECT COUNT(*) c FROM jobs WHERE status NOT IN ('Tamamlandı','İptal','Teslim Edildi')")?></strong><span>Tüm açık işler</span></a>
    <a class="command-card red" href="work_center.php?filter=late"><small>Geciken</small><strong><?=safe_count("SELECT COUNT(*) c FROM jobs WHERE due_date IS NOT NULL AND due_date<CURDATE() AND status NOT IN ('Tamamlandı','İptal','Teslim Edildi')")?></strong><span>Termin geçmiş işler</span></a>
    <a class="command-card yellow" href="work_center.php?filter=today"><small>Bugün Teslim</small><strong><?=safe_count("SELECT COUNT(*) c FROM jobs WHERE due_date=CURDATE() AND status NOT IN ('Tamamlandı','İptal','Teslim Edildi')")?></strong><span>Bugünün işleri</span></a>
    <a class="command-card purple" href="work_center.php?filter=external"><small>Dışarıda</small><strong><?=safe_count("SELECT COUNT(*) c FROM jobs WHERE job_type IN ('dis_atolye','tedarikcide_uretim') AND status NOT IN ('Tamamlandı','İptal','Teslim Edildi')")?></strong><span>Dış tedarik / atölye</span></a>
</section>

<section class="df-card">
<div class="df-table-wrap"><table class="df-table">
<thead>
<tr>
<th>İş</th>
<th>Müşteri</th>
<th>Sorumlu</th>
<th>Termin</th>
<th>Durum</th>
<th>İlerleme</th>
<th>İşlem</th>
</tr>
</thead>
<tbody>
<?php
try{
    $stmt=db()->query("SELECT j.*, c.name customer_name, p.name responsible_name
        FROM jobs j
        LEFT JOIN contacts c ON c.id=j.customer_id
        LEFT JOIN personnel p ON p.id=j.responsible_personnel_id
        $sqlWhere
        ORDER BY j.due_date IS NULL, j.due_date ASC, j.id DESC
        LIMIT 100");
    $rows=$stmt->fetchAll();
    foreach($rows as $r):
        work_engine_seed_checklist($r['id'],$r['job_type']);
        $progress=work_engine_progress($r['id']);
?>
<tr>
<td><b><?=h($r['job_no'])?></b><br><?=h($r['title'])?></td>
<td><?=h($r['customer_name'] ?: '-')?></td>
<td><?=h($r['responsible_name'] ?: '-')?></td>
<td><?=h($r['due_date'] ?: '-')?></td>
<td><?=ds_badge($r['status'])?></td>
<td>
    <div style="background:var(--df-surface-sunken);border-radius:999px;height:10px;overflow:hidden;width:140px">
        <div style="background:var(--df-ink-900);height:10px;width:<?=$progress?>%"></div>
    </div>
    <span style="color:var(--df-ink-500)">%<?=$progress?></span>
</td>
<td><a class="df-btn df-btn--secondary df-btn--sm" href="work_view.php?id=<?=$r['id']?>">İş Motoru</a></td>
</tr>
<?php endforeach; ?>
<?php if(!$rows): ?><tr><td colspan="7" style="color:var(--df-ink-500)">Kayıt yok.</td></tr><?php endif; ?>
<?php }catch(Throwable $e){ ?>
<tr><td colspan="7"><?=ds_alert('danger',$e->getMessage())?></td></tr>
<?php } ?>
</tbody>
</table></div>
</section>

<?php require_once __DIR__.'/layout_bottom.php'; ?>
