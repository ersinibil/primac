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

    // Talep sahibine bildirim + iç mesaj
    try{
      $r=$pdo->prepare("SELECT title, created_by FROM management_requests WHERE id=?");
      $r->execute([$rid]); $row=$r->fetch();
      $owner=(int)($row['created_by']??0);
      $notifTitle='📨 Talebiniz: '.$status;
      $notifMsg=($row['title']??'').($note?' · '.$note:'');
      if($owner && $owner!==$ME){
        if(function_exists('notify_user')) notify_user($owner,$notifTitle,$notifMsg,'requests.php');
        // Mesajlar ekranında görünsün
        try{ $pdo->prepare("INSERT INTO internal_messages(sender_user_id,receiver_user_id,message,is_read) VALUES(?,?,?,0)")->execute([$ME?:null,$owner,$notifTitle."\n".$notifMsg]); }catch(Throwable $e2){}
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
    ORDER BY FIELD(r.status,'Yeni','İnceleniyor','Onaylandı','Reddedildi','Tamamlandı'), r.id DESC");
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
<?php if($er): ?><div class="err"><?=htmlspecialchars($er)?></div><?php endif; ?>

<a class="btn dark" href="request_new.php" style="display:block;text-align:center;margin-bottom:10px">+ Yeni Talep</a>

<div class="panel" style="display:flex;gap:8px;flex-wrap:wrap;font-size:13px">
<a class="<?=$status===''?'btn':''?>" href="requests.php" style="color:#fff;text-decoration:none;<?=$status===''?'':'opacity:.7'?>">Tümü</a>
<?php foreach($statuses as $s): ?>
<a class="<?=$status===$s?'btn':''?>" href="requests.php?status=<?=urlencode($s)?>" style="color:#fff;text-decoration:none;<?=$status===$s?'':'opacity:.7'?>"><?=htmlspecialchars($s)?></a>
<?php endforeach; ?>
</div>

<?php if(!$rows): ?><div class="panel muted">Henüz talep yok.</div><?php endif; ?>

<?php foreach($rows as $r): ?>
<div class="panel">
  <div style="display:flex;justify-content:space-between;gap:8px;align-items:flex-start">
    <div style="min-width:0">
      <b><?=htmlspecialchars($r['title'])?></b><br>
      <span class="muted" style="font-size:12px"><?=htmlspecialchars($r['request_no'])?> · <?=htmlspecialchars($r['created_at'])?></span>
    </div>
    <span class="card <?=req_tone($r['status'])?>" style="min-height:auto;padding:5px 10px;font-weight:900;font-size:12px;border-radius:12px"><?=htmlspecialchars($r['status'])?></span>
  </div>

  <div style="margin:8px 0;font-size:14px">
    <div class="muted" style="font-size:12px">📂 <?=htmlspecialchars($r['category'])?> · ⚡ <?=htmlspecialchars($r['priority'])?></div>
    <div class="muted" style="font-size:12px">👤 <?=htmlspecialchars($r['personnel_name']?:'-')?></div>
    <?php if(!empty($r['job_no'])): ?><div class="muted" style="font-size:12px">📋 <?=htmlspecialchars($r['job_no'].' - '.$r['job_title'])?></div><?php endif; ?>
    <?php if(trim((string)$r['description'])!==''): ?><div style="margin-top:6px"><?=nl2br(htmlspecialchars($r['description']))?></div><?php endif; ?>
  </div>

  <form method="post">
    <input type="hidden" name="request_id" value="<?=$r['id']?>">
    <label class="muted">Durum</label>
    <select name="status">
      <?php foreach($statuses as $s): ?><option <?=$r['status']===$s?'selected':''?>><?=htmlspecialchars($s)?></option><?php endforeach; ?>
    </select>
    <input name="manager_note" placeholder="Yönetim notu" value="<?=htmlspecialchars((string)$r['response_note'])?>">
    <div style="display:flex;gap:8px">
      <button class="btn" type="submit">Kaydet</button>
      <button class="btn dark" type="submit" onclick="this.form.status.value='Onaylandı'" style="background:#16a34a">✓ Onayla</button>
      <button class="btn dark" type="submit" onclick="this.form.status.value='Reddedildi'" style="background:#dc2626">✕ Reddet</button>
    </div>
  </form>
</div>
<?php endforeach; ?>

<?php botx(); ?>
