<?php
/* İLETİŞİM MERKEZİ — Taleplerim (2026-07-17, Product Owner kararı).
 * Yeni bir talep sistemi İCAT EDİLMEDİ: management_requests tablosu ve request_new.php'nin
 * oluşturma akışı zaten vardı, sadece "kendi gönderdiğim talepler nerede?" sorusuna cevap
 * yoktu (requests.php admin-only, herkesin talebini gösteriyor). Bu sayfa SADECE oturum
 * sahibinin kendi gönderdiği talepleri (created_by=$ME), salt-okunur, aynı requests.php sorgu
 * desenini kullanarak listeler. Onay/red işlemi hâlâ sadece requests.php'de (admin). */
require_once __DIR__.'/boot.php';
require_login();
require_once __DIR__.'/share_lib.php';

$pdo=db();
$ME=(int)($_SESSION['user']['id'] ?? 0);

require_once __DIR__.'/layout_top.php';

$rows=[];
try{
    $stmt=$pdo->prepare("SELECT r.*, j.job_no, j.title job_title
        FROM management_requests r
        LEFT JOIN jobs j ON j.id=r.related_job_id
        WHERE r.created_by=?
        ORDER BY FIELD(r.status,'Yeni','İnceleniyor','Onaylandı','Reddedildi','Tamamlandı'), r.id DESC");
    $stmt->execute([$ME]);
    $rows=$stmt->fetchAll();
}catch(Throwable $e){}
?>

<?php
$__talepActions = ds_button('+ Yeni Talep','request_new.php','primary','','',true);
if(is_admin()) $__talepActions .= ds_button('Tüm Talepler (Yönetim)','requests.php','secondary','','',true);
ds_page_header('İletişim Merkezi', ds_icon('inbox',24), '', $__talepActions, false, true);
?>
<?php ic_tabs('taleplerim'); ?>

<section class="df-card" style="margin-top:var(--df-space-4)">
<div class="df-table-wrap"><table class="df-table">
<thead>
<tr>
<th>Talep No</th>
<th>Kategori</th>
<th>Başlık</th>
<th>İlgili İş</th>
<th>Öncelik</th>
<th>Durum</th>
<th>Yönetici Notu</th>
</tr>
</thead>
<tbody>
<?php foreach($rows as $r): ?>
<tr>
<td><?=h($r['request_no'])?><br><span class="df-muted" style="font-size:12px"><?=h($r['created_at'])?></span></td>
<td><?=h($r['category'])?></td>
<td><b><?=h($r['title'])?></b><br><?=nl2br(h($r['description']))?></td>
<td><?=h($r['job_no'] ? $r['job_no'].' - '.$r['job_title'] : '-')?></td>
<td><?=ds_badge($r['priority'])?></td>
<td><?=ds_badge($r['status'])?></td>
<td><?=h($r['response_note'] ?: '-')?></td>
</tr>
<?php endforeach; ?>
<?php if(!$rows): ?><tr><td colspan="7" style="color:var(--df-ink-500)">Henüz talep göndermediniz.</td></tr><?php endif; ?>
</tbody>
</table></div>
</section>

<?php require_once __DIR__.'/layout_bottom.php'; ?>
