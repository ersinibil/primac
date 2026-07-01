<?php
require_once 'common.php';
$pdo=db(); $ok=''; $er='';

// Eksik kolon güvencesi
try{ $pdo->exec("ALTER TABLE contacts ADD COLUMN phone2 varchar(60) DEFAULT NULL AFTER phone"); }catch(Throwable $e){}
try{ $pdo->exec("ALTER TABLE contacts ADD COLUMN website varchar(255) DEFAULT NULL AFTER email"); }catch(Throwable $e){}
try{ $pdo->exec("ALTER TABLE contacts ADD COLUMN postal_code varchar(20) DEFAULT NULL AFTER district"); }catch(Throwable $e){}
try{ $pdo->exec("ALTER TABLE contacts ADD COLUMN iban varchar(60) DEFAULT NULL AFTER postal_code"); }catch(Throwable $e){}

if($_SERVER['REQUEST_METHOD']==='POST'){
    try{
        $name=trim($_POST['name'] ?? '');
        if($name==='') throw new Exception('Cari adı girin.');
        $pdo->prepare("INSERT INTO contacts(
            name,type,authorized_person,
            phone,phone2,email,website,
            tax_office,tax_number,
            city,district,postal_code,address,
            iban,opening_balance,notes,
            created_at
        ) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())")
            ->execute([
                $name,
                $_POST['type'] ?? 'Müşteri',
                trim($_POST['authorized_person'] ?? ''),
                trim($_POST['phone'] ?? ''),
                trim($_POST['phone2'] ?? ''),
                trim($_POST['email'] ?? ''),
                trim($_POST['website'] ?? ''),
                trim($_POST['tax_office'] ?? ''),
                trim($_POST['tax_number'] ?? ''),
                trim($_POST['city'] ?? ''),
                trim($_POST['district'] ?? ''),
                trim($_POST['postal_code'] ?? ''),
                trim($_POST['address'] ?? ''),
                trim($_POST['iban'] ?? ''),
                (float)($_POST['opening_balance'] ?? 0),
                trim($_POST['notes'] ?? '')
            ]);
        $nid=(int)$pdo->lastInsertId();
        try{ if(function_exists('activity_log')) activity_log('Cari','Yeni',$name,$_POST['type']??'','contact',$nid,'mobile/contact_view.php?id='.$nid,'👥'); }catch(Throwable $e){}
        $ok=$name.' eklendi.';
    }catch(Throwable $e){ $er=$e->getMessage(); }
}
topx('Yeni Cari');
?>
<?php if($ok): ?><div class="notice"><?=htmlspecialchars($ok)?> · <a href="contacts.php" style="color:#fff;text-decoration:underline">Cariler</a></div><?php endif; ?>
<?php if($er): ?><div class="err"><?=htmlspecialchars($er)?></div><?php endif; ?>
<div class="panel">
<form method="post">
  <label>Cari Adı</label><input name="name" required placeholder="Müşteri / tedarikçi adı">
  <label>Tip</label>
  <select name="type"><option>Müşteri</option><option>Tedarikçi</option><option>Her İkisi</option></select>
  <label>Yetkili Kişi</label><input name="authorized_person" placeholder="Ad Soyad">
  <label>Telefon</label><input name="phone" type="tel" placeholder="05xx...">
  <label>2. Telefon</label><input name="phone2" type="tel" placeholder="05xx...">
  <label>E-posta</label><input name="email" type="email">
  <label>Web Sitesi</label><input name="website" type="url" placeholder="https://">
  <label>Vergi Dairesi</label><input name="tax_office">
  <label>Vergi / TC No</label><input name="tax_number">
  <label>İl</label><input name="city">
  <label>İlçe</label><input name="district">
  <label>Posta Kodu</label><input name="postal_code" maxlength="10">
  <label>Adres</label><textarea name="address" rows="2"></textarea>
  <label>IBAN</label><input name="iban" maxlength="32" placeholder="TR00 0000 0000 0000 0000 0000 00">
  <label>Açılış Bakiyesi (₺)</label><input type="number" step="0.01" name="opening_balance" value="0">
  <label>Notlar</label><textarea name="notes" rows="3"></textarea>
  <button class="btn dark" style="width:100%;padding:14px;margin-top:8px">➕ Cariyi Kaydet</button>
</form>
</div>
<?php botx(); ?>
