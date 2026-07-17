<?php
/* İLETİŞİM MERKEZİ — Taleplerim (mobil, 2026-07-17). Sadece oturum sahibinin kendi gönderdiği
 * talepler (created_by=$ME), salt-okunur — web taleplerim.php ile aynı desen. Onay/red işlemi
 * hâlâ sadece requests.php'de (admin, block_personel()). */
require_once 'common.php';
require_once __DIR__.'/../share_lib.php';
$pdo=db();

topx('İletişim Merkezi');
?>
<?php ic_tabs('taleplerim'); ?>

<a class="btn dark" href="request_new.php" style="display:block;text-align:center;margin:10px 0">+ Yeni Talep</a>
<?php if($isAdmin): ?><a class="btn" href="requests.php" style="display:block;text-align:center;background:#334155;color:#fff;margin-bottom:10px">Tüm Talepler (Yönetim)</a><?php endif; ?>

<?php
function req_tone_mine($s){
  switch($s){
    case 'Yeni': return 'blue'; case 'İnceleniyor': return 'yellow'; case 'Onaylandı': return 'green';
    case 'Reddedildi': return 'red'; case 'Tamamlandı': return 'teal'; default: return 'gray';
  }
}
try{
  $stmt=$pdo->prepare("SELECT r.*, j.job_no, j.title job_title FROM management_requests r
    LEFT JOIN jobs j ON j.id=r.related_job_id WHERE r.created_by=?
    ORDER BY FIELD(r.status,'Yeni','İnceleniyor','Onaylandı','Reddedildi','Tamamlandı'), r.id DESC");
  $stmt->execute([$ME]);
  $rows=$stmt->fetchAll();
}catch(Throwable $e){ $rows=[]; }

if(!$rows): ?><div class="panel muted">Henüz talep göndermediniz.</div><?php endif;

foreach($rows as $r): ?>
<div class="panel">
  <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:8px">
    <div>
      <b><?=htmlspecialchars($r['title'])?></b>
      <div class="muted" style="font-size:12px"><?=htmlspecialchars($r['category'])?> · <?=htmlspecialchars($r['request_no'])?></div>
    </div>
    <span class="card <?=req_tone_mine($r['status'])?>" style="min-height:auto;padding:5px 10px;font-weight:900;font-size:12px;border-radius:12px"><?=htmlspecialchars($r['status'])?></span>
  </div>
  <?php if($r['description']): ?><p style="margin:8px 0 0;font-size:13px"><?=nl2br(htmlspecialchars($r['description']))?></p><?php endif; ?>
  <?php if($r['job_no']): ?><div class="muted" style="font-size:12px;margin-top:6px">İlgili İş: <?=htmlspecialchars($r['job_no'].' - '.$r['job_title'])?></div><?php endif; ?>
  <?php if($r['response_note']): ?><div style="margin-top:8px;background:rgba(255,255,255,.06);border-radius:10px;padding:8px;font-size:13px"><b>Yönetici Notu:</b> <?=htmlspecialchars($r['response_note'])?></div><?php endif; ?>
</div>
<?php endforeach; ?>
<?php botx(); ?>
