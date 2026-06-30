<?php
require_once __DIR__.'/boot.php';
require_login();
require_once __DIR__.'/activity_lib.php';

$pdo=db();
$error='';
$type=$_GET['type'] ?? 'Müşteri';
if(!in_array($type,['Müşteri','Tedarikçi','Her İkisi'])) $type='Müşteri';

if($_SERVER['REQUEST_METHOD']==='POST'){
    try{
        $stmt=$pdo->prepare("INSERT INTO contacts(
            name,type,authorized_person,phone,email,tax_info,tax_office,tax_number,city,district,address,opening_balance,notes
        ) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?)");
        $stmt->execute([
            trim($_POST['name']),
            $_POST['type'],
            trim($_POST['authorized_person']),
            trim($_POST['phone']),
            trim($_POST['email']),
            trim($_POST['tax_info']),
            trim($_POST['tax_office']),
            trim($_POST['tax_number']),
            trim($_POST['city']),
            trim($_POST['district']),
            trim($_POST['address']),
            (float)$_POST['opening_balance'],
            trim($_POST['notes'])
        ]);
        $id=$pdo->lastInsertId();
        activity_log('Cari','Yeni Cari','Yeni cari açıldı',trim($_POST['name']).' · '.$_POST['type'],'contact',$id,'contact_view.php?id='.$id,'👥');
        header("Location: contact_view.php?id=".$id);
        exit;
    }catch(Throwable $e){
        $error=$e->getMessage();
    }
}

require_once __DIR__.'/layout_top.php';
?>

<div class="panel-head">
<h1>Yeni Cari</h1>
<a class="btn secondary" href="contacts.php">Cari Listesi</a>
</div>

<?php if($error): ?><div class="alert"><?=h($error)?></div><?php endif; ?>

<section class="panel">
<form method="post" class="form-grid">

<label class="full">Cari Tipi
<select name="type">
<?php foreach(['Müşteri','Tedarikçi','Her İkisi'] as $t): ?>
<option <?=$type===$t?'selected':''?>><?=$t?></option>
<?php endforeach; ?>
</select>
</label>

<label>Firma / Cari Adı
<input name="name" required>
</label>

<label>Yetkili Kişi
<input name="authorized_person">
</label>

<label>Telefon
<input name="phone">
</label>

<label>E-posta
<input name="email">
</label>

<label>Vergi Bilgisi
<input name="tax_info">
</label>

<label>Vergi Dairesi
<input name="tax_office">
</label>

<label>Vergi / TCKN No
<input name="tax_number">
</label>

<label>İl
<input name="city">
</label>

<label>İlçe
<input name="district">
</label>

<label>Açılış Bakiyesi
<input type="number" step="0.01" name="opening_balance" value="0">
</label>

<label class="full">Adres
<textarea name="address" rows="3"></textarea>
</label>

<label class="full">Notlar
<textarea name="notes" rows="4"></textarea>
</label>

<button class="btn">Cari Kaydet</button>

</form>
</section>

<?php require_once __DIR__.'/layout_bottom.php'; ?>
