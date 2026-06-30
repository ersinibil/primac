<?php
require_once __DIR__.'/boot.php';
require_login();

$pdo=db();

if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['request_id'])){
    try{
        $stmt=$pdo->prepare("UPDATE management_requests SET status=?, manager_note=?, updated_at=NOW() WHERE id=?");
        $stmt->execute([$_POST['status'], trim($_POST['manager_note']), (int)$_POST['request_id']]);
    }catch(Throwable $e){
        $error=$e->getMessage();
    }
}

require_once __DIR__.'/layout_top.php';

$status=$_GET['status'] ?? '';
$where='';
$params=[];
if($status){
    $where='WHERE r.status=?';
    $params[]=$status;
}

$stmt=$pdo->prepare("SELECT r.*, p.name personnel_name, j.job_no, j.title job_title 
FROM management_requests r
LEFT JOIN personnel p ON p.id=r.personnel_id
LEFT JOIN jobs j ON j.id=r.related_job_id
$where
ORDER BY FIELD(r.status,'Yeni','İnceleniyor','Onaylandı','Reddedildi','Tamamlandı'), r.id DESC");
$stmt->execute($params);
$rows=$stmt->fetchAll();
?>

<div class="panel-head">
<h1>Talep Merkezi</h1>
<a class="btn" href="request_new.php">+ Yeni Talep</a>
</div>

<div class="filters">
<a href="requests.php">Tümü</a>
<a href="requests.php?status=Yeni">Yeni</a>
<a href="requests.php?status=İnceleniyor">İnceleniyor</a>
<a href="requests.php?status=Onaylandı">Onaylandı</a>
<a href="requests.php?status=Reddedildi">Reddedildi</a>
<a href="requests.php?status=Tamamlandı">Tamamlandı</a>
</div>

<section class="panel">
<table>
<thead>
<tr>
<th>Talep No</th>
<th>Kategori</th>
<th>Başlık</th>
<th>Personel</th>
<th>İlgili İş</th>
<th>Öncelik</th>
<th>Durum</th>
<th>Yönetim</th>
</tr>
</thead>
<tbody>
<?php foreach($rows as $r): ?>
<tr>
<td><?=h($r['request_no'])?><br><span class="muted"><?=h($r['created_at'])?></span></td>
<td><?=h($r['category'])?></td>
<td><b><?=h($r['title'])?></b><br><?=nl2br(h($r['description']))?></td>
<td><?=h($r['personnel_name'] ?: '-')?></td>
<td><?=h($r['job_no'] ? $r['job_no'].' - '.$r['job_title'] : '-')?></td>
<td><?=badge($r['priority'], status_tone($r['priority']))?></td>
<td><?=badge($r['status'], status_tone($r['status']))?></td>
<td>
<form method="post" class="inline" style="align-items:flex-start">
<input type="hidden" name="request_id" value="<?=$r['id']?>">
<div>
<select name="status">
<?php foreach(['Yeni','İnceleniyor','Onaylandı','Reddedildi','Tamamlandı'] as $s): ?>
<option <?=$r['status']===$s?'selected':''?>><?=$s?></option>
<?php endforeach; ?>
</select>
<input name="manager_note" placeholder="Yönetim notu" value="<?=h($r['manager_note'])?>">
<button class="btn small">Kaydet</button>
</div>
</form>
</td>
</tr>
<?php endforeach; ?>
<?php if(!$rows): ?><tr><td colspan="8" class="muted">Henüz talep yok.</td></tr><?php endif; ?>
</tbody>
</table>
</section>

<?php require_once __DIR__.'/layout_bottom.php'; ?>
