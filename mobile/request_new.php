<?php
require_once 'common.php';
require_once __DIR__.'/../request_lib.php';
$pdo=db();
$er='';

// Tablo güvencesi (migrate atlanmışsa)
// HOTFIX-03 EK (2026-07-17, ACİL — Ece code-review bulgusu): requests.php/mobile/requests.php'deki
// aynı manager_note→response_note şema hatasının üçüncü kopyası, buradaki fallback'te de vardı.
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

// Oturum sahibinin personel kaydını bul (talebi otomatik kendine bağla)
$myPid=null;
try{ $r=$pdo->prepare("SELECT personnel_id FROM app_users WHERE id=?"); $r->execute([$ME]); $myPid=(int)($r->fetch()['personnel_id']??0)?:null; }catch(Throwable $e){}

// POST → topx'ten ÖNCE → redirect (PRG)
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['save_request'])){
  try{
    $title=trim($_POST['title']??'');
    if($title==='') throw new Exception('Talep başlığı zorunlu.');
    $category=trim($_POST['category']??'Genel');
    $priority=trim($_POST['priority']??'Normal');
    $desc=trim($_POST['description']??'');
    $jobId=(int)($_POST['related_job_id']??0)?:null;
    $reqNo='TLP-'.date('Y').'-'.str_pad((string)random_int(1,999999),6,'0',STR_PAD_LEFT);

    $pdo->prepare("INSERT INTO management_requests(
      request_no,personnel_id,related_job_id,category,title,description,priority,status,created_by
    ) VALUES(?,?,?,?,?,?,?,?,?)")
      ->execute([$reqNo,$myPid,$jobId,$category,$title,$desc,$priority,'Yeni',$ME?:null]);
    $rid=(int)$pdo->lastInsertId();

    // P0-REQ-01 (2026-07-17, ACİL): önceden burada TÜM adminlere (role='admin') koşulsuz broadcast
    // yapılıyordu — kategori/iş ile hiç ilgisi olmayan talepler de aynı admin(ler)in sohbetine
    // düşüyordu (gerçek kullanıcı raporu: alakasız "Karbon"/"Muhasebe-Evrak" talepleri tek bir
    // yöneticinin — Ahmet Emir İbil'in — sohbetinde birikti). Artık hedef SADECE ilgili işin
    // sorumlu personeline (request_lib.php::request_resolve_recipient()) deterministik olarak
    // çözülüyor; atanmış/geçerli bir hedef yoksa HİÇ KİMSEYE mesaj gönderilmez — talep yine de
    // Talep Merkezi listesinde görünür durur, sadece proaktif bildirim gitmez.
    try{
      $recipientUid=request_resolve_recipient($pdo,$jobId);
      if($recipientUid && $recipientUid!==$ME){
        $notifMsg=$name.' · '.$category.' · '.$priority;
        if(function_exists('notify_user'))
          notify_user($recipientUid,'📨 Yeni talep: '.$title,$notifMsg,'requests.php');
        // Mesajlar ekranında da görünsün
        try{ $pdo->prepare("INSERT INTO internal_messages(sender_user_id,receiver_user_id,message,is_read) VALUES(?,?,?,0)")->execute([$ME?:null,$recipientUid,'📨 Yeni talep: '.$title."\n".$notifMsg]); }catch(Throwable $e2){}
      }
    }catch(Throwable $e){}

    $_SESSION['_flash']='Talebiniz gönderildi. ('.$reqNo.')';
    header('Location: request_new.php?ok=1'); exit;
  }catch(Throwable $e){ $er=$e->getMessage(); }
}

$flash=$_SESSION['_flash']??''; unset($_SESSION['_flash']);

// Bağlanacak iş listesi
$jobs=[];
try{ $jobs=$pdo->query("SELECT id, job_no, title FROM jobs ORDER BY id DESC LIMIT 100")->fetchAll(); }catch(Throwable $e){}

$cats=['Malzeme Talebi','Satın Alma Talebi','Avans Talebi','İzin Talebi','Mesai Talebi','Arıza / Teknik Sorun','Grafik Revize Talebi','Dış Atölye Talebi','Montaj Talebi','Muhasebe / Evrak Talebi','Ödeme Talebi','Özel İzin/Talep','Genel'];
$pris=['Normal','Acil','Çok Acil','Düşük'];

topx('Yeni Talep');
?>
<?php if($flash): ?><div class="ok"><?=htmlspecialchars($flash)?></div><?php endif; ?>
<?php if($er): ?><div class="err"><?=htmlspecialchars($er)?></div><?php endif; ?>

<div class="panel">
<form method="post">
<input type="hidden" name="save_request" value="1">

<label class="muted">Talep Kategorisi</label>
<select name="category">
<?php foreach($cats as $c): ?><option><?=htmlspecialchars($c)?></option><?php endforeach; ?>
</select>

<label class="muted">Öncelik</label>
<select name="priority">
<?php foreach($pris as $p): ?><option><?=htmlspecialchars($p)?></option><?php endforeach; ?>
</select>

<label class="muted">İlgili İş (opsiyonel)</label>
<select name="related_job_id">
<option value="">İşle bağlantılı değil</option>
<?php foreach($jobs as $j): ?><option value="<?=$j['id']?>"><?=htmlspecialchars($j['job_no'])?> - <?=htmlspecialchars($j['title'])?></option><?php endforeach; ?>
</select>

<label class="muted">Talep Başlığı</label>
<input name="title" required placeholder="Örn: Siyah PLA alınması gerekiyor">

<label class="muted">Açıklama</label>
<textarea name="description" rows="5" placeholder="Talebin detayını yazın."></textarea>

<button class="btn dark" type="submit">Talebi Gönder</button>
</form>
</div>

<a class="item" href="requests.php">📋 Tüm Talepler</a>

<?php botx(); ?>
