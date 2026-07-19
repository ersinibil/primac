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

// PİLOT ÖNCESİ KAPANIŞ (2026-07-19): "kendi talebimi göremiyorum ama yönetemiyorum da" — bu sayfa
// salt-okunuydu, kullanıcı kendi açık talebini iptal edemiyordu. Fiziksel DELETE yok — management_
// requests'e başka hiçbir tablo FK ile bağlı değil ama kontrollü bir durum geçişi ("İptal Edildi")
// fiziksel silmeden daha izlenebilir. Sadece SAHİBİ (created_by, IDOR'a kapalı — WHERE created_by=?
// ile doğrulanır) ve henüz sonuçlanmamış (Yeni/İnceleniyor) bir talebi iptal edebilir —
// Onaylandı/Reddedildi/Tamamlandı zaten işleme alınmış/sonuçlanmış, geriye dönük iptal anlamsız.
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['cancel_request'])){
    $__rid=(int)$_POST['cancel_request'];
    try{
        $__chk=$pdo->prepare("SELECT status FROM management_requests WHERE id=? AND created_by=?");
        $__chk->execute([$__rid,$ME]);
        $__row=$__chk->fetch();
        if($__row && in_array($__row['status'],['Yeni','İnceleniyor'],true)){
            $pdo->prepare("UPDATE management_requests SET status='İptal Edildi', updated_at=NOW() WHERE id=? AND created_by=?")->execute([$__rid,$ME]);
        }
    }catch(Throwable $e){}
    header('Location: taleplerim.php'); exit;
}

require_once __DIR__.'/layout_top.php';

$rows=[];
try{
    $stmt=$pdo->prepare("SELECT r.*, j.job_no, j.title job_title
        FROM management_requests r
        LEFT JOIN jobs j ON j.id=r.related_job_id
        WHERE r.created_by=?
        ORDER BY FIELD(r.status,'Yeni','İnceleniyor','Onaylandı','Reddedildi','Tamamlandı','İptal Edildi'), r.id DESC");
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
<th>İşlem</th>
</tr>
</thead>
<tbody>
<?php foreach($rows as $r): $__canCancel=in_array($r['status'],['Yeni','İnceleniyor'],true); ?>
<tr>
<td><?=h($r['request_no'])?><br><span class="df-muted" style="font-size:12px"><?=h($r['created_at'])?></span></td>
<td><?=h($r['category'])?></td>
<td><b><?=h($r['title'])?></b><br><?=nl2br(h($r['description']))?></td>
<td><?=h($r['job_no'] ? $r['job_no'].' - '.$r['job_title'] : '-')?></td>
<td><?=ds_badge($r['priority'])?></td>
<td><?=ds_badge($r['status'])?></td>
<td><?=h($r['response_note'] ?: '-')?></td>
<td><?php if($__canCancel): ?>
<form method="post" style="margin:0" onsubmit="return confirm('Bu talebi iptal etmek istediğinize emin misiniz?')">
<input type="hidden" name="cancel_request" value="<?=(int)$r['id']?>">
<button type="submit" class="df-btn df-btn--secondary df-btn--sm">İptal Et</button>
</form>
<?php else: ?><span style="color:var(--df-ink-500)">—</span><?php endif; ?></td>
</tr>
<?php endforeach; ?>
<?php if(!$rows): ?><tr><td colspan="8" style="color:var(--df-ink-500)">Henüz talep göndermediniz.</td></tr><?php endif; ?>
</tbody>
</table></div>
</section>

<?php require_once __DIR__.'/layout_bottom.php'; ?>
