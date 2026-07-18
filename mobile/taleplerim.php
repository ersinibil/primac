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

<a class="df-btn df-btn--primary df-btn--lg" href="request_new.php" style="width:100%;justify-content:center;margin:10px 0">+ Yeni Talep</a>
<?php if($isAdmin): ?><a class="df-btn df-btn--secondary df-btn--lg" href="requests.php" style="width:100%;justify-content:center;margin-bottom:10px">Tüm Talepler (Yönetim)</a><?php endif; ?>

<?php
try{
  $stmt=$pdo->prepare("SELECT r.*, j.job_no, j.title job_title FROM management_requests r
    LEFT JOIN jobs j ON j.id=r.related_job_id WHERE r.created_by=?
    ORDER BY FIELD(r.status,'Yeni','İnceleniyor','Onaylandı','Reddedildi','Tamamlandı'), r.id DESC");
  $stmt->execute([$ME]);
  $rows=$stmt->fetchAll();
}catch(Throwable $e){ $rows=[]; }

if(!$rows): ?><?=ds_empty_state('Henüz talep göndermediniz.', null, ds_icon('inbox',32))?><?php endif;

foreach($rows as $r): ?>
<div class="df-panel" style="margin-top:10px">
  <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:8px">
    <div>
      <div class="df-list-row-title"><?=h($r['title'])?></div>
      <div class="muted" style="font-size:12px"><?=h($r['category'])?> · <?=h($r['request_no'])?></div>
    </div>
    <?=ds_badge($r['status'])?>
  </div>
  <?php if($r['description']): ?><p style="margin:8px 0 0;font-size:13px"><?=nl2br(h($r['description']))?></p><?php endif; ?>
  <?php if($r['job_no']): ?><div class="muted" style="font-size:12px;margin-top:6px">İlgili İş: <?=h($r['job_no'].' - '.$r['job_title'])?></div><?php endif; ?>
  <?php if($r['response_note']): ?><div style="margin-top:8px;background:var(--df-surface-sunken);border-radius:var(--df-radius-md);padding:8px;font-size:13px"><b>Yönetici Notu:</b> <?=h($r['response_note'])?></div><?php endif; ?>
</div>
<?php endforeach; ?>
<?php botx(); ?>
