<?php
require_once __DIR__.'/boot.php';
require_login();
require_once __DIR__.'/personnel_lib.php';

$pdo=db();
$error='';
$hasCvCol = personnel_has_cv_column($pdo);
// RELEASE 0.9 — Kullanıcı/Yetki Akışı Birleştirme (2026-07-17, Product Owner kararı: "Yeni personel
// oluşturulduğunda aynı işlem içinde OTS kullanıcısı oluşturulabilmelidir"). mobile/personnel_new.php'de
// zaten var olan aynı desen (opsiyonel "make_login" checkbox'ı) web'e de eklendi — aynı yetki kuralı
// (PDP-001: admin veya 'personnel_accounts' yetkili alt yönetici).
$canManageAccounts = is_admin() || user_can('personnel_accounts');

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

        // Opsiyonel: aynı işlemde giriş hesabı da oluştur (personnel_lib.php::personnel_create_login()
        // — mükerrer hesap koruması dahil, P0-AUTH-01 ile aynı güvenli fonksiyon; yeni personelin henüz
        // hiçbir hesabı olamayacağı için o koruma burada devreye girmez, sadece tutarlılık için aynı yol).
        if($canManageAccounts && !empty($_POST['make_login']) && trim($_POST['username']??'')!=='' && trim($_POST['password']??'')!==''){
            try{
                $res=personnel_create_login($pdo, $newId, $_POST['username'], $_POST['password']);
                if(function_exists('cred_wa')) $_SESSION['_new_personnel_wa']=cred_wa($res['phone'], trim($_POST['username']), $_POST['password']);
            }catch(Throwable $e){
                $_SESSION['_new_personnel_login_err']='Personel eklendi ama giriş hesabı oluşturulamadı: '.$e->getMessage();
            }
        }
        header("Location: personnel_edit.php?id=".$newId);
        exit;
    }catch(Throwable $e){
        $error=$e->getMessage();
    }
}

require_once __DIR__.'/layout_top.php';

// RELEASE 0.9 — Personel Ekranları DS Migration (2026-07-17): ds_form_field() bugüne kadar hiç
// canlı kullanılmamıştı (grep ile doğrulandı) — ama gerçek stil (input/select/textarea rengi,
// border, radius) zaten AYRI, genel body.nav-compact seçicisinden geliyor; ds_form_field sadece
// tutarlı label+boşluk sarmalayıcısı ekliyor, yeni bir görsel risk taşımıyor.
ds_page_header('Yeni Personel', ds_icon('user',24), '', ds_button('Personel Listesi','personnel.php','secondary','','',true), false, true);
?>

<?php if($error): ?><?=ds_alert('danger',$error)?><?php endif; ?>

<section class="df-card">
<form method="post" class="df-form-grid-2" enctype="multipart/form-data">

<?php ds_form_field('Ad Soyad', '<input name="name" required placeholder="Örn: Faruk Gündoğdu">'); ?>
<?php ds_form_field('Rol / Görev', '<input name="role" placeholder="Üretim, montaj, satın alma, grafik...">'); ?>
<?php ds_form_field('Telefon', '<input name="phone">'); ?>
<?php ds_form_field('E-posta', '<input name="email">'); ?>

<?php ds_form_field('Çalışma Tipi', '<select name="work_type">
<option>Tam Zamanlı</option>
<option>Yarı Zamanlı</option>
<option>Günlük</option>
<option>Dış Paydaş</option>
<option>Stajyer</option>
</select>'); ?>

<?php ds_form_field('İşe Giriş Tarihi', '<input type="date" name="start_date">'); ?>
<?php ds_form_field('Saatlik Ücret', '<input type="number" step="0.01" name="hourly_rate" value="0">'); ?>
<?php ds_form_field('Günlük / Maaş', '<input type="number" step="0.01" name="daily_wage" value="0">'); ?>

<div class="df-form-span-2">
<?php ds_form_field('IBAN', '<input name="iban">'); ?>
</div>
<div class="df-form-span-2">
<?php ds_form_field('Adres', '<textarea name="address" rows="2"></textarea>'); ?>
</div>
<div class="df-form-span-2">
<?php ds_form_field('Notlar', '<textarea name="notes" rows="4"></textarea>'); ?>
</div>

<?php if($hasCvCol): ?>
<div class="df-form-span-2">
<?php ds_form_field('CV / Özgeçmiş', '<input type="file" name="cv" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">', 'Opsiyonel — pdf/doc/docx/jpg/jpeg/png, en fazla 15 MB'); ?>
</div>
<?php endif; ?>

<div class="df-form-span-2">
<label style="display:flex;align-items:center;gap:8px;font-size:var(--df-type-body-size);color:var(--df-ink-900)">
<input type="checkbox" name="active" checked style="width:auto"> Aktif personel
</label>
</div>

<?php if($canManageAccounts): ?>
<div class="df-form-span-2 df-card" style="background:var(--df-accent-soft);border-color:transparent">
<label style="display:flex;align-items:center;gap:8px;margin:0;font-weight:700;color:var(--df-ink-900)">
<input type="checkbox" name="make_login" value="1" style="width:auto" onchange="document.getElementById('loginFields').style.display=this.checked?'grid':'none'">
<?=ds_icon('user',16)?> Aynı işlemde uygulamaya giriş hesabı da oluştur
</label>
<div id="loginFields" class="df-form-grid-2" style="display:none;margin-top:var(--df-space-3)">
<?php ds_form_field('Kullanıcı Adı', '<input name="username">'); ?>
<?php ds_form_field('Şifre', '<input type="password" name="password">'); ?>
</div>
<p style="margin:var(--df-space-2) 0 0;font-size:var(--df-type-caption-size);color:var(--df-ink-500)">Personel bu bilgilerle giriş yapıp mesaj/iş/görev görür (yetkileri kısıtlı, daha sonra "Giriş Hesabı" sekmesinden düzenlenebilir).</p>
</div>
<?php endif; ?>

<div class="df-form-span-2">
<button class="df-btn df-btn--primary">Personeli Kaydet</button>
</div>

</form>
</section>

<style>
body.nav-compact .df-form-grid-2{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:0 var(--df-space-4)}
body.nav-compact .df-form-span-2{grid-column:1 / -1}
@media(max-width:640px){body.nav-compact .df-form-grid-2{grid-template-columns:1fr}}
</style>

<?php require_once __DIR__.'/layout_bottom.php'; ?>
