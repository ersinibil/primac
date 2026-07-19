<?php
require_once 'common.php';
block_personel(); // sadece yönetici — topx'ten ÖNCE
$pdo=db();
$er='';

// Tablo güvencesi
// HOTFIX-03 (2026-07-17, ACİL): gerçek şema kolonu response_note (database/migrations/008_misc.sql)
// — bu fallback yanlışlıkla manager_note ile yaratıyordu; canlı DB'lerde migration 008 zaten
// çalıştığı için bu blok pratikte hiç tetiklenmiyor ama tetiklenirse de artık gerçek şemayla
// tutarlı bir tablo oluşturur.
try{ $pdo->query("SELECT 1 FROM management_requests LIMIT 1"); }
catch(Throwable $e){
  try{
    $pdo->exec("CREATE TABLE IF NOT EXISTS management_requests(
      id INT AUTO_INCREMENT PRIMARY KEY,
      request_no VARCHAR(40),
      personnel_id INT NULL,
      related_job_id INT NULL,
      category VARCHAR(80),
      title VARCHAR(255),
      description TEXT,
      priority VARCHAR(30) DEFAULT 'Normal',
      status VARCHAR(30) DEFAULT 'Yeni',
      response_note TEXT NULL,
      created_by INT NULL,
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      updated_at TIMESTAMP NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  }catch(Throwable $e2){}
}

// POST → durum güncelle / onayla / reddet → topx'ten ÖNCE → redirect (PRG)
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['request_id'])){
  try{
    $rid=(int)$_POST['request_id'];
    $status=trim($_POST['status']??'Yeni');
    $note=trim($_POST['manager_note']??'');
    $pdo->prepare("UPDATE management_requests SET status=?, response_note=?, updated_at=NOW() WHERE id=?")
      ->execute([$status,$note,$rid]);

    // Talep sahibine bildirim — İLETİŞİM MERKEZİ (2026-07-17): "Talep bildirimleri sohbet listesine
    // düşmeyecek; Bildirimler sekmesinde gösterilecek" — internal_messages'a artık YAZILMIYOR.
    try{
      $r=$pdo->prepare("SELECT title, created_by FROM management_requests WHERE id=?");
      $r->execute([$rid]); $row=$r->fetch();
      $owner=(int)($row['created_by']??0);
      $notifTitle='📨 Talebiniz: '.$status;
      $notifMsg=($row['title']??'').($note?' · '.$note:'');
      if($owner && $owner!==$ME){
        if(function_exists('notify_user')) notify_user($owner,$notifTitle,$notifMsg,'requests.php');
      }
    }catch(Throwable $e){}

    $back='requests.php'.(!empty($_GET['status'])?('?status='.urlencode($_GET['status'])):'');
    header('Location: '.$back); exit;
  }catch(Throwable $e){
    // HOTFIX-03: ham DB/exception metni kullanıcıya gösterilmez, teşhis için sunucu log'una yazılır.
    error_log('mobile/requests.php update_request hatası: '.$e->getMessage());
    $er='Talep güncellenemedi. Lütfen tekrar deneyin, sorun sürerse yöneticinize bildirin.';
  }
}

$status=$_GET['status']??'';
$where=''; $params=[];
if($status){ $where='WHERE r.status=?'; $params[]=$status; }

$rows=[];
try{
  $stmt=$pdo->prepare("SELECT r.*, p.name personnel_name, j.job_no, j.title job_title
    FROM management_requests r
    LEFT JOIN personnel p ON p.id=r.personnel_id
    LEFT JOIN jobs j ON j.id=r.related_job_id
    $where
    ORDER BY FIELD(r.status,'Yeni','İnceleniyor','Onaylandı','Reddedildi','Tamamlandı','İptal Edildi'), r.id DESC");
  $stmt->execute($params);
  $rows=$stmt->fetchAll();
}catch(Throwable $e){ $er=$e->getMessage(); }

$statuses=['Yeni','İnceleniyor','Onaylandı','Reddedildi','Tamamlandı'];
function req_tone($s){
  switch($s){
    case 'Yeni': return 'blue';
    case 'İnceleniyor': return 'yellow';
    case 'Onaylandı': return 'green';
    case 'Reddedildi': return 'red';
    case 'Tamamlandı': return 'teal';
    default: return 'gray';
  }
}

topx('Talep Merkezi');
?>
<?php if($er): ?><?=ds_alert('danger',$er)?><?php endif; ?>

<?=ds_button(ds_icon('plus',15).' Yeni Talep','request_new.php','primary','','style="width:100%;justify-content:center;margin-bottom:12px"',true)?>

<div class="df-tabs" style="overflow:auto;max-width:100%;-webkit-overflow-scrolling:touch;margin-bottom:14px">
<a class="df-tab<?=$status===''?' df-tab--active':''?>" href="requests.php">Tümü</a>
<?php foreach($statuses as $s): ?>
<a class="df-tab<?=$status===$s?' df-tab--active':''?>" href="requests.php?status=<?=urlencode($s)?>"><?=h($s)?></a>
<?php endforeach; ?>
</div>

<?php if(!$rows): ?><?php ds_empty_state('Henüz talep yok.', null, ds_icon('info',20)); ?><?php endif; ?>

<?php foreach($rows as $r): ?>
<div class="df-panel" style="margin-top:10px">
  <div style="display:flex;justify-content:space-between;gap:8px;align-items:flex-start">
    <div style="min-width:0">
      <div class="df-list-row-title"><?=h($r['title'])?></div>
      <div class="df-text-caption"><?=h($r['request_no'])?> · <?=h($r['created_at'])?></div>
    </div>
    <?=ds_badge($r['status'], req_tone($r['status']))?>
  </div>

  <div class="df-list-row-meta" style="margin-top:8px">
    <span><?=h($r['category'])?></span>
    <?=ds_priority($r['priority'],$r['priority'])?>
    <span><?=ds_icon('user',13)?> <?=h($r['personnel_name']?:'-')?></span>
    <?php if(!empty($r['job_no'])): ?><span><?=ds_icon('briefcase',13)?> <?=h($r['job_no'].' - '.$r['job_title'])?></span><?php endif; ?>
  </div>
  <?php if(trim((string)$r['description'])!==''): ?><p style="margin:8px 0 0"><?=nl2br(h($r['description']))?></p><?php endif; ?>

  <form method="post" style="margin-top:12px">
    <input type="hidden" name="request_id" value="<?=$r['id']?>">
    <label>Durum</label>
    <select name="status">
      <?php foreach($statuses as $s): ?><option <?=$r['status']===$s?'selected':''?>><?=h($s)?></option><?php endforeach; ?>
    </select>
    <input name="manager_note" placeholder="Yönetim notu" value="<?=h((string)$r['response_note'])?>">
    <div style="display:flex;gap:8px;flex-wrap:wrap">
      <button type="submit" class="df-btn df-btn--secondary" style="flex:1;justify-content:center">Kaydet</button>
      <button type="submit" class="df-btn df-btn--primary" onclick="this.form.status.value='Onaylandı'" style="flex:1;justify-content:center"><?=ds_icon('check',14)?> Onayla</button>
      <button type="submit" class="df-btn df-btn--danger" onclick="this.form.status.value='Reddedildi'" style="flex:1;justify-content:center"><?=ds_icon('close',14)?> Reddet</button>
    </div>
  </form>
</div>
<?php endforeach; ?>

<?php botx(); ?>
