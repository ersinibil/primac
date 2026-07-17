<?php
require_once __DIR__.'/boot.php';
require_login();
require_once __DIR__.'/activity_lib.php';

$pdo=db();
$error='';
// Tür URL'den GELDİYSE (Müşteri/Tedarikçi listesindeki "+ Yeni" düğmesinden) sabitlenir — formda
// dropdown gösterilmez, kullanıcı "Müşteri" listesinden geldiği halde yanlışlıkla "Tedarikçi" seçip
// kaydedemez (2026-07-03 kullanıcı bildirimi: "mantık hatası var, müşteri açılınca tedarikçi de
// seçilebiliyor"). Tür hiç verilmemişse (örn. ileride eklenecek genel "Yeni Cari Kaydı" girişi) tam
// dropdown gösterilir.
$typeLocked = isset($_GET['type']) && in_array($_GET['type'], ['Müşteri','Tedarikçi'], true);
$type = $typeLocked ? $_GET['type'] : ($_GET['type'] ?? 'Müşteri');
if(!in_array($type,['Müşteri','Tedarikçi','Her İkisi'])) $type='Müşteri';

// Eksik kolon güvencesi
try{ $pdo->exec("ALTER TABLE contacts ADD COLUMN phone2 varchar(60) DEFAULT NULL AFTER phone"); }catch(Throwable $e){}
try{ $pdo->exec("ALTER TABLE contacts ADD COLUMN website varchar(255) DEFAULT NULL AFTER email"); }catch(Throwable $e){}
try{ $pdo->exec("ALTER TABLE contacts ADD COLUMN postal_code varchar(20) DEFAULT NULL AFTER district"); }catch(Throwable $e){}
try{ $pdo->exec("ALTER TABLE contacts ADD COLUMN iban varchar(60) DEFAULT NULL AFTER postal_code"); }catch(Throwable $e){}

if($_SERVER['REQUEST_METHOD']==='POST'){
    try{
        $stmt=$pdo->prepare("INSERT INTO contacts(
            name,type,authorized_person,
            phone,phone2,email,website,
            tax_office,tax_number,
            city,district,postal_code,address,
            iban,opening_balance,notes
        ) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
        $stmt->execute([
            trim($_POST['name']),
            $_POST['type'],
            trim($_POST['authorized_person']),
            trim($_POST['phone']),
            trim($_POST['phone2']),
            trim($_POST['email']),
            trim($_POST['website']),
            trim($_POST['tax_office']),
            trim($_POST['tax_number']),
            trim($_POST['city']),
            trim($_POST['district']),
            trim($_POST['postal_code']),
            trim($_POST['address']),
            trim($_POST['iban']),
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

<?php ds_page_header('Yeni Cari', ds_icon('users',24), '', ds_button('Cari Listesi','contacts.php','secondary','','',true), false, true); ?>

<?php if($error): ?><?=ds_alert('danger',$error)?><?php endif; ?>

<section class="df-card">
<form method="post" class="df-form-grid-2">

<div class="df-form-span-2">
<?php if($typeLocked): ?>
<?php ds_form_field('Cari Tipi', '<input type="text" value="'.h($type).'" disabled style="background:var(--df-surface-sunken);color:var(--df-ink-600);font-weight:700"><input type="hidden" name="type" value="'.h($type).'">'); ?>
<?php else: ?>
<?php
$__typeOpts='';
foreach(['Müşteri','Tedarikçi','Her İkisi'] as $t){ $__typeOpts.='<option '.($type===$t?'selected':'').'>'.$t.'</option>'; }
ds_form_field('Cari Tipi', '<select name="type">'.$__typeOpts.'</select>');
?>
<?php endif; ?>
</div>

<?php ds_form_field('Firma / Cari Adı', '<input name="name" required>'); ?>
<?php ds_form_field('Yetkili Kişi', '<input name="authorized_person">'); ?>
<?php ds_form_field('Telefon', '<input name="phone" type="tel">'); ?>
<?php ds_form_field('2. Telefon', '<input name="phone2" type="tel">'); ?>
<?php ds_form_field('E-posta', '<input name="email" type="email">'); ?>
<?php ds_form_field('Web Sitesi', '<input name="website" type="url" placeholder="https://">'); ?>
<?php ds_form_field('Vergi Dairesi', '<input name="tax_office">'); ?>
<?php ds_form_field('Vergi / TC No', '<input name="tax_number">'); ?>
<?php ds_form_field('İl', '<input name="city">'); ?>
<?php ds_form_field('İlçe', '<input name="district">'); ?>
<?php ds_form_field('Posta Kodu', '<input name="postal_code" maxlength="10">'); ?>
<?php ds_form_field('IBAN', '<input name="iban" maxlength="32" placeholder="TR00 0000 0000 0000 0000 0000 00">'); ?>
<?php ds_form_field('Açılış Bakiyesi', '<input type="number" step="0.01" name="opening_balance" value="0">'); ?>

<div class="df-form-span-2"><?php ds_form_field('Adres', '<textarea name="address" rows="3"></textarea>'); ?></div>
<div class="df-form-span-2"><?php ds_form_field('Notlar', '<textarea name="notes" rows="4"></textarea>'); ?></div>

<div class="df-form-span-2"><button class="df-btn df-btn--primary">Cari Kaydet</button></div>

</form>
</section>

<style>
body.nav-compact .df-form-grid-2{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:0 var(--df-space-4)}
body.nav-compact .df-form-span-2{grid-column:1 / -1}
@media(max-width:640px){body.nav-compact .df-form-grid-2{grid-template-columns:1fr}}
</style>

<?php require_once __DIR__.'/layout_bottom.php'; ?>
