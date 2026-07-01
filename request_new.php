<?php
require_once __DIR__.'/boot.php';
require_login();
require_once __DIR__.'/telegram.php';

$pdo=db();
$error='';

if($_SERVER['REQUEST_METHOD']==='POST'){
    try{
        $reqNo='TLP-'.date('Y').'-'.str_pad((string)random_int(1,999999),6,'0',STR_PAD_LEFT);
        $stmt=$pdo->prepare("INSERT INTO management_requests(
            request_no,personnel_id,related_job_id,category,title,description,priority,status,created_by
        ) VALUES(?,?,?,?,?,?,?,?,?)");
        $stmt->execute([
            $reqNo,
            $_POST['personnel_id'] ?: null,
            $_POST['related_job_id'] ?: null,
            $_POST['category'],
            trim($_POST['title']),
            trim($_POST['description']),
            $_POST['priority'],
            'Yeni',
            $_SESSION['user']['id'] ?? null
        ]);
        try{
            $personnelName = '-';
            if(!empty($_POST['personnel_id'])){
                $ps = $pdo->prepare("SELECT name FROM personnel WHERE id=?");
                $ps->execute([$_POST['personnel_id']]);
                $row = $ps->fetch();
                $personnelName = $row['name'] ?? '-';
            }

            telegram_send(
                "📨 Yeni Yönetim Talebi\n\n".
                $reqNo."\n".
                trim($_POST['title'])."\n\n".
                "👤 Talep Eden: ".$personnelName."\n".
                "📂 Kategori: ".$_POST['category']."\n".
                "⚡ Öncelik: ".$_POST['priority'],
                erp_url('requests.php')
            );
        }catch(Throwable $e){}

        header("Location: requests.php");
        exit;
    }catch(Throwable $e){
        $error=$e->getMessage();
    }
}

require_once __DIR__.'/layout_top.php';

$personnel=$pdo->query("SELECT * FROM personnel WHERE active=1 ORDER BY name")->fetchAll();
$jobs=$pdo->query("SELECT id, job_no, title FROM jobs ORDER BY id DESC LIMIT 100")->fetchAll();
?>

<div class="panel-head">
<h1>Yeni Yönetim Talebi</h1>
<a class="btn secondary" href="requests.php">Talepler</a>
</div>

<?php if($error): ?><div class="alert"><?=h($error)?></div><?php endif; ?>

<section class="panel">
<form method="post" class="form-grid">

<label>Talep Eden Personel
<select name="personnel_id">
<option value="">Seçiniz</option>
<?php foreach($personnel as $p): ?><option value="<?=$p['id']?>"><?=h($p['name'])?> - <?=h($p['role'])?></option><?php endforeach; ?>
</select>
</label>

<label>İlgili İş
<select name="related_job_id">
<option value="">İşle bağlantılı değil</option>
<?php foreach($jobs as $j): ?><option value="<?=$j['id']?>"><?=h($j['job_no'])?> - <?=h($j['title'])?></option><?php endforeach; ?>
</select>
</label>

<label>Talep Kategorisi
<select name="category">
<option>Malzeme Talebi</option>
<option>Satın Alma Talebi</option>
<option>Avans Talebi</option>
<option>İzin Talebi</option>
<option>Mesai Talebi</option>
<option>Arıza / Teknik Sorun</option>
<option>Grafik Revize Talebi</option>
<option>Dış Atölye Talebi</option>
<option>Montaj Talebi</option>
<option>Muhasebe / Evrak Talebi</option>
<option>Ödeme Talebi</option>
<option>Özel İzin/Talep</option>
<option>Genel</option>
</select>
</label>

<label>Öncelik
<select name="priority">
<option>Normal</option>
<option>Acil</option>
<option>Çok Acil</option>
<option>Düşük</option>
</select>
</label>

<label class="full">Talep Başlığı
<input name="title" required placeholder="Örn: Siyah PLA alınması gerekiyor">
</label>

<label class="full">Açıklama
<textarea name="description" rows="5" placeholder="Talebin detayını yazın. Örn: K2 Plus işleri için siyah PLA stokta kalmadı. 10 kg alınmalı."></textarea>
</label>

<button class="btn">Talebi Gönder</button>

</form>
</section>

<?php require_once __DIR__.'/layout_bottom.php'; ?>
