<?php
require_once __DIR__.'/boot.php';
require_login();
// Güvenlik: mobile/requests.php ile parite — talep merkezi sadece yönetici (2026-07-03 denetimi).
if(!is_admin()){ http_response_code(403); exit('Bu sayfa için yetkiniz yok.'); }

$pdo=db();
// HOTFIX-03 EK (Selin security-review notu): $error önceden hiç başlatılmıyordu, GET isteğinde
// (POST yolu hiç çalışmadığında) aşağıdaki "if(!empty($error))" E_ALL altında "Undefined variable"
// notice'ı üretebiliyordu.
$error='';

if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['request_id'])){
    try{
        $rid=(int)$_POST['request_id'];
        $newStatus=$_POST['status'];
        // HOTFIX-03 (2026-07-17, ACİL): gerçek şema kolonu response_note (database/migrations/
        // 008_misc.sql) — burada var olmayan manager_note'a yazılıyordu, PDO exception fırlatıyor,
        // aşağıdaki catch $error'ı set ediyor ama hiçbir yerde render edilmediği için güncelleme
        // kullanıcıya sessizce "başarılıymış gibi" görünüyordu. Form alanı adı (name="manager_note",
        // aşağıda) değiştirilmedi — o sadece tarayıcı↔PHP arası kablo adı, DB koloniyle aynı olmak
        // zorunda değil.
        $note=trim($_POST['manager_note']);
        $stmt=$pdo->prepare("UPDATE management_requests SET status=?, response_note=?, updated_at=NOW() WHERE id=?");
        $stmt->execute([$newStatus, $note, $rid]);

        // Talep sahibine bildirim — İLETİŞİM MERKEZİ (2026-07-17, Product Owner kararı): "Talep
        // bildirimleri sohbet listesine düşmeyecek; Bildirimler sekmesinde gösterilecek" —
        // internal_messages'a artık YAZILMIYOR, sadece internal_notifications (+ push).
        try{
            $rq=$pdo->prepare("SELECT title, created_by FROM management_requests WHERE id=?");
            $rq->execute([$rid]); $row=$rq->fetch();
            $owner=(int)($row['created_by']??0);
            $notifTitle='📨 Talebiniz: '.$newStatus;
            $notifMsg=($row['title']??'').($note?' · '.$note:'');
            if($owner){
                // Uygulama içi bildirim
                try{ $pdo->prepare("INSERT INTO internal_notifications(title,message,target_user_id,action_url,is_read) VALUES(?,?,?,?,0)")->execute([$notifTitle,$notifMsg,$owner,'requests.php']); }catch(Throwable $e2){}
                // Web Push (push_lib varsa)
                if(file_exists(__DIR__.'/push_lib.php')){ require_once __DIR__.'/push_lib.php'; try{ push_to_user($owner,$notifTitle,$notifMsg,'requests.php'); }catch(Throwable $e2){} }
            }
        }catch(Throwable $e){}
    }catch(Throwable $e){
        // HOTFIX-03: kullanıcıya ham DB/exception metni gösterilmez (şema detayı sızdırır) — güvenli,
        // sabit bir mesaj gösterilir; teşhis için gerçek mesaj sunucu log'una yazılır.
        error_log('requests.php update_request hatası: '.$e->getMessage());
        $error='Talep güncellenemedi. Lütfen tekrar deneyin, sorun sürerse yöneticinize bildirin.';
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
ORDER BY FIELD(r.status,'Yeni','İnceleniyor','Onaylandı','Reddedildi','Tamamlandı','İptal Edildi'), r.id DESC");
$stmt->execute($params);
$rows=$stmt->fetchAll();
?>

<?php
ds_page_header('Talep Merkezi', ds_icon('send',24), '', ds_button('Yeni Talep','request_new.php','primary','','',true), false, true);
$__reqStatuses=['','Yeni','İnceleniyor','Onaylandı','Reddedildi','Tamamlandı','İptal Edildi'];
$__reqTabItems=[];
foreach($__reqStatuses as $__s){ $__reqTabItems[]=['label'=>$__s===''?'Tümü':$__s,'url'=>$__s===''?'requests.php':'requests.php?status='.urlencode($__s),'active'=>($status===$__s)]; }
ds_tabs($__reqTabItems);
?>

<?php if(!empty($error)): ?><?=ds_alert('danger',$error)?><?php endif; ?>

<section class="df-card" style="margin-top:var(--df-space-4)">
<div class="df-table-wrap"><table class="df-table">
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
<td><?=h($r['request_no'])?><br><span style="color:var(--df-ink-500)"><?=h($r['created_at'])?></span></td>
<td><?=h($r['category'])?></td>
<td><b><?=h($r['title'])?></b><br><?=nl2br(h($r['description']))?></td>
<td><?=h($r['personnel_name'] ?: '-')?></td>
<td><?=h($r['job_no'] ? $r['job_no'].' - '.$r['job_title'] : '-')?></td>
<td><?=ds_badge($r['priority'])?></td>
<td><?=ds_badge($r['status'])?></td>
<td>
<form method="post" style="display:flex;gap:6px;align-items:flex-start;flex-wrap:wrap">
<input type="hidden" name="request_id" value="<?=$r['id']?>">
<select name="status">
<?php foreach(['Yeni','İnceleniyor','Onaylandı','Reddedildi','Tamamlandı'] as $s): ?>
<option <?=$r['status']===$s?'selected':''?>><?=$s?></option>
<?php endforeach; ?>
</select>
<input name="manager_note" placeholder="Yönetim notu" value="<?=h($r['response_note'])?>">
<button class="df-btn df-btn--primary df-btn--sm">Kaydet</button>
</form>
</td>
</tr>
<?php endforeach; ?>
<?php if(!$rows): ?><tr><td colspan="8" style="color:var(--df-ink-500)">Henüz talep yok.</td></tr><?php endif; ?>
</tbody>
</table></div>
</section>

<?php require_once __DIR__.'/layout_bottom.php'; ?>
