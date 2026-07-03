<?php
require_once __DIR__.'/boot.php';
require_login();
require_once __DIR__.'/personnel_lib.php';

$pdo=db();
$error='';
$hasCvCol = personnel_has_cv_column($pdo);

if($_SERVER['REQUEST_METHOD']==='POST'){
    try{
        $cvPath = $hasCvCol ? personnel_handle_cv_upload() : null;

        if($hasCvCol){
            $stmt=$pdo->prepare("INSERT INTO personnel(
                name,role,phone,email,hourly_rate,daily_wage,active,address,start_date,work_type,iban,notes,cv_path
            ) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?)");
            $stmt->execute([
                trim($_POST['name']),
                trim($_POST['role']),
                trim($_POST['phone']),
                trim($_POST['email']),
                (float)$_POST['hourly_rate'],
                (float)$_POST['daily_wage'],
                isset($_POST['active']) ? 1 : 0,
                trim($_POST['address']),
                $_POST['start_date'] ?: null,
                $_POST['work_type'],
                trim($_POST['iban']),
                trim($_POST['notes']),
                $cvPath
            ]);
        }else{
            $stmt=$pdo->prepare("INSERT INTO personnel(
                name,role,phone,email,hourly_rate,daily_wage,active,address,start_date,work_type,iban,notes
            ) VALUES(?,?,?,?,?,?,?,?,?,?,?,?)");
            $stmt->execute([
                trim($_POST['name']),
                trim($_POST['role']),
                trim($_POST['phone']),
                trim($_POST['email']),
                (float)$_POST['hourly_rate'],
                (float)$_POST['daily_wage'],
                isset($_POST['active']) ? 1 : 0,
                trim($_POST['address']),
                $_POST['start_date'] ?: null,
                $_POST['work_type'],
                trim($_POST['iban']),
                trim($_POST['notes'])
            ]);
        }
        $newId = $pdo->lastInsertId();
        try{
            $code = (string)random_int(100000,999999);
            $chk = $pdo->prepare("SHOW COLUMNS FROM personnel LIKE 'telegram_activation_code'");
            $chk->execute();
            if($chk->fetch()){
                $pdo->prepare("UPDATE personnel SET telegram_activation_code=? WHERE id=?")->execute([$code,$newId]);
            }
        }catch(Throwable $e){}
        header("Location: personnel_edit.php?id=".$newId);
        exit;
    }catch(Throwable $e){
        $error=$e->getMessage();
    }
}

require_once __DIR__.'/layout_top.php';
?>

<div class="panel-head">
<h1>Yeni Personel</h1>
<a class="btn secondary" href="personnel.php">Personel Listesi</a>
</div>

<?php if($error): ?><div class="alert"><?=h($error)?></div><?php endif; ?>

<section class="panel">
<form method="post" class="form-grid" enctype="multipart/form-data">

<label>Ad Soyad
<input name="name" required placeholder="Örn: Faruk Gündoğdu">
</label>

<label>Rol / Görev
<input name="role" placeholder="Üretim, montaj, satın alma, grafik...">
</label>

<label>Telefon
<input name="phone">
</label>

<label>E-posta
<input name="email">
</label>

<label>Çalışma Tipi
<select name="work_type">
<option>Tam Zamanlı</option>
<option>Yarı Zamanlı</option>
<option>Günlük</option>
<option>Dış Paydaş</option>
<option>Stajyer</option>
</select>
</label>

<label>İşe Giriş Tarihi
<input type="date" name="start_date">
</label>

<label>Saatlik Ücret
<input type="number" step="0.01" name="hourly_rate" value="0">
</label>

<label>Günlük / Maaş
<input type="number" step="0.01" name="daily_wage" value="0">
</label>

<label class="full">IBAN
<input name="iban">
</label>

<label class="full">Adres
<textarea name="address" rows="2"></textarea>
</label>

<label class="full">Notlar
<textarea name="notes" rows="4"></textarea>
</label>

<?php if($hasCvCol): ?>
<label class="full">CV / Özgeçmiş <small style="font-weight:400;color:#667085">(opsiyonel — pdf/doc/docx/jpg/jpeg/png, en fazla 15 MB)</small>
<input type="file" name="cv" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
</label>
<?php endif; ?>

<label class="full">
<input type="checkbox" name="active" checked style="width:auto"> Aktif personel
</label>

<button class="btn">Personeli Kaydet</button>

</form>
</section>

<?php require_once __DIR__.'/layout_bottom.php'; ?>
