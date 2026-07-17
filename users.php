<?php
require_once __DIR__.'/boot.php';
require_permission('users');
require_once __DIR__.'/share_lib.php';

$pdo=db();
$error='';
$ok='';
$wa_results='';

$permLabels=module_list();

// personnel_edit.php'den "giriş hesabı yok" linkiyle gelindiyse Yeni Kullanıcı formunu doldur
// (2026-07-03: kullanıcı şikayeti — "düzenle dediğimiz personele ait bilgi direk gelsin").
$prefillPersonnelId = (int)($_GET['personnel_id'] ?? 0);
$prefillFullName = trim($_GET['full_name'] ?? '');
$prefillPhone = trim($_GET['phone'] ?? '');

if($_SERVER['REQUEST_METHOD']==='POST'){
    try{
        // HOTFIX-02 (2026-07-17, ACİL): rol yükseltme açığı — bu sayfa require_permission('users')
        // ile korunuyor ama bu yetki admin ANLAMINA GELMİYOR (granüler modül yetkisi, admin-olmayan
        // birine de verilebilir). Önceden $_POST['role'] hem create hem update'te doğrudan kaydediliyordu
        // — 'users' yetkili admin-olmayan biri kendini/başkasını admin yapabiliyordu. Artık: (1) rol
        // sadece sabit whitelist'ten biri olabilir, (2) rolü FİİLEN değiştirebilmek için oturumdaki
        // kullanıcının is_admin() olması ZORUNLU — admin değilse gönderilen role değeri tamamen
        // yok sayılır (create'te 'personel'e düşer, update'te mevcut DB rolü korunur). Böylece
        // admin-olmayan biri ne başkasını ne KENDİSİNİ yükseltebilir.
        $__validRoles = ['personel','yonetici','admin'];

        if(isset($_POST['create_user'])){
            $perms=$_POST['permissions'] ?? [];
            $hash=password_hash($_POST['password'] ?: '123456', PASSWORD_DEFAULT);
            $__postedRole = $_POST['role'] ?? '';
            $role = (is_admin() && in_array($__postedRole, $__validRoles, true)) ? $__postedRole : 'personel';
            $__linkPid = (int)($_POST['personnel_id'] ?? 0) ?: null;
            // P0-AUTH-01 (2026-07-17, Ece re-review'ında bulunan boşluk): bu form, personnel_edit.php'nin
            // dedike "Giriş Hesabı Oluştur" akışından (personnel_lib.php::personnel_create_login())
            // BAĞIMSIZ olarak, "Personel Bağlantısı" dropdown'undan zaten bağlı bir personel seçilerek
            // ikinci bir hesap açılmasına izin veriyordu — P0-AUTH-01'in kök nedeni olan aynı mükerrer-
            // hesap durumunu farklı bir giriş noktasından yeniden üretebiliyordu. Aynı koruma burada da.
            if($__linkPid){
                $__pr0=$pdo->prepare("SELECT user_id FROM personnel WHERE id=?"); $__pr0->execute([$__linkPid]); $__p0=$__pr0->fetch();
                $__existingUid=(int)($__p0['user_id'] ?? 0);
                $__hasLink=false;
                if($__existingUid){
                    $__chk=$pdo->prepare("SELECT id FROM app_users WHERE id=? AND personnel_id=?"); $__chk->execute([$__existingUid,$__linkPid]);
                    $__hasLink=(bool)$__chk->fetch();
                }
                if(!$__hasLink){
                    $__le=$pdo->prepare("SELECT id FROM app_users WHERE personnel_id=? LIMIT 1"); $__le->execute([$__linkPid]);
                    $__hasLink=(bool)$__le->fetch();
                }
                if($__hasLink) throw new Exception('Bu personelin zaten bağlı bir giriş hesabı var. Yeni hesap oluşturmak yerine mevcut hesabın şifresini sıfırlayın.');
            }
            $pdo->prepare("INSERT INTO app_users(username,full_name,phone,email,password_hash,role,personnel_id,permissions,active) VALUES(?,?,?,?,?,?,?,?,?)")
                ->execute([
                    trim($_POST['username']),
                    trim($_POST['full_name']),
                    trim($_POST['phone']),
                    trim($_POST['email']),
                    $hash,
                    $role,
                    $__linkPid,
                    json_encode($perms,JSON_UNESCAPED_UNICODE),
                    isset($_POST['active'])?1:0
                ]);
            if($__linkPid){
                // personnel.user_id'yi de senkron tut — personnel_create_login() ile aynı davranış,
                // reset_password()'ün deterministik çözümlemesinin bu yoldan açılan hesaplar için de
                // doğru çalışmasını sağlar.
                $__newUid=(int)$pdo->lastInsertId();
                $pdo->prepare("UPDATE personnel SET user_id=?,login_enabled=1 WHERE id=?")->execute([$__newUid,$__linkPid]);
            }
            $ok='Kullanıcı oluşturuldu.';
        }

        if(isset($_POST['update_user'])){
            $uid=(int)$_POST['user_id'];
            $perms=$_POST['permissions'] ?? [];
            $active=isset($_POST['active'])?1:0;
            $__postedRole = $_POST['role'] ?? '';
            if(is_admin() && in_array($__postedRole, $__validRoles, true)){
                $role = $__postedRole;
            } else {
                // Admin değil (veya geçersiz değer gönderildi) — rol alanı DEĞİŞTİRİLMEZ, mevcut
                // DB kaydındaki rol korunur (kendi rolü dahil, hiçbir admin-olmayan kullanıcı rol
                // değiştiremez).
                $__cur = $pdo->prepare("SELECT role FROM app_users WHERE id=?");
                $__cur->execute([$uid]);
                $role = $__cur->fetch()['role'] ?? 'personel';
            }
            // Ana yönetici (ilk kullanıcı, id=1) korunur: pasifleştirilemez, admin rolü düşürülemez.
            if($uid===1){ $role='admin'; $active=1; }
            $pdo->prepare("UPDATE app_users SET username=?, full_name=?, phone=?, email=?, role=?, personnel_id=?, permissions=?, active=? WHERE id=?")
                ->execute([
                    trim($_POST['username']),
                    trim($_POST['full_name']),
                    trim($_POST['phone']),
                    trim($_POST['email']),
                    $role,
                    (int)$_POST['personnel_id'] ?: null,
                    json_encode($perms,JSON_UNESCAPED_UNICODE),
                    $active,
                    $uid
                ]);
            if(!empty($_POST['password'])){
                $pdo->prepare("UPDATE app_users SET password_hash=? WHERE id=?")
                    ->execute([password_hash($_POST['password'],PASSWORD_DEFAULT),$uid]);
            }
            $ok='Kullanıcı güncellendi.';
        }

        // P0-AUTH-02 EK (2026-07-17, kullanıcı dostu yapı): şifre değiştirme artık isteğe bağlı
        // olarak isim/telefon/rol/yetki/aktiflik gibi ALAKASIZ alanları içeren büyük "Güncelle"
        // formundan bağımsız, kendi başına küçük bir işlem olarak da yapılabiliyor — personel
        // taleplerinde admin'in "sadece şifre değiştirmek" isterken farkında olmadan başka bir
        // alanı da (örn. Aktif kutusunu) değiştirip hesabı kilitleme riskini azaltır.
        if(isset($_POST['reset_user_pw'])){
            $uid=(int)$_POST['user_id'];
            $newpw=$_POST['newpw'] ?? '';
            if(strlen($newpw)<4) throw new Exception('Yeni şifre en az 4 karakter olmalı.');
            $pdo->prepare("UPDATE app_users SET password_hash=? WHERE id=?")
                ->execute([password_hash($newpw,PASSWORD_DEFAULT),$uid]);
            $ok='Şifre güncellendi.';
        }

        if(isset($_POST['send_bulk_wa'])){
            $selected=$_POST['selected_users'] ?? [];
            $mode=$_POST['wa_mode'] ?? 'all'; // 'all' veya 'selected'
            $genNewPw=isset($_POST['gen_new_password'])?1:0;

            if($mode==='selected' && empty($selected)){
                $error='Lütfen en az bir kullanıcı seçin.';
            } else {
                $users_to_send=[];
                if($mode==='all'){
                    $users_to_send=$pdo->query("SELECT id,username,phone,full_name FROM app_users WHERE active=1 ORDER BY full_name")->fetchAll();
                } else {
                    foreach($selected as $uid){
                        $uid=(int)$uid;
                        if($uid>0){
                            $s=$pdo->prepare("SELECT id,username,phone,full_name FROM app_users WHERE id=? AND active=1");
                            $s->execute([$uid]);
                            $r=$s->fetch();
                            if($r) $users_to_send[]=$r;
                        }
                    }
                }

                $results=['success'=>0,'no_phone'=>0,'failed'=>0,'details'=>[]];

                foreach($users_to_send as $u){
                    $uid=$u['id'];
                    $username=$u['username'];
                    $phone=$u['phone'] ?? '';
                    $full_name=$u['full_name'];

                    if(!$phone){
                        $results['no_phone']++;
                        $results['details'][]=['name'=>$full_name,'status'=>'Telefon numarası yok','ok'=>false];
                        continue;
                    }

                    try{
                        $password=$_POST['password_'.$uid] ?? '';
                        if($genNewPw) $password=generate_random_password(10);

                        if(!$password){
                            $results['failed']++;
                            $results['details'][]=['name'=>$full_name,'status'=>'Şifre bulunamadı','ok'=>false];
                            continue;
                        }

                        // WhatsApp ile gönder — cred_wa()'nın kurduğu metinle aynı (uygulama adresi dahil)
                        $appName=(app_config()['app_name'] ?? 'OTS');
                        $appUrl=base_url();
                        $txt="🔐 ".$appName." giriş bilgileriniz\nKullanıcı: ".$username."\nŞifre: ".$password.($appUrl?"\nAdres: ".$appUrl:'');
                        $sent=wa_send($phone,$txt);

                        if($sent){
                            // P0-AUTH-02 (2026-07-17, ACİL — gerçek kullanıcı raporu): şifre_hash DAHA
                            // ÖNCE (gönderim denemeden önce) kaydediliyordu — WhatsApp API hatası/yanlış
                            // numara yüzünden gönderim BAŞARISIZ olsa bile yeni rastgele şifre zaten
                            // veritabanına yazılmış oluyordu. Kimse (admin dahil) bu şifreyi görmediği
                            // için hesap fiilen kilitleniyordu (eski şifre de artık geçersiz). Artık DB
                            // yazımı SADECE gönderim gerçekten başarılıysa yapılıyor.
                            if($genNewPw){
                                $pdo->prepare("UPDATE app_users SET password_hash=? WHERE id=?")->execute([password_hash($password,PASSWORD_DEFAULT),$uid]);
                            }
                            $results['success']++;
                            $results['details'][]=['name'=>$full_name,'status'=>'Gönderildi','ok'=>true];
                        } else {
                            $results['failed']++;
                            $results['details'][]=['name'=>$full_name,'status'=>'API hatası — şifre DEĞİŞTİRİLMEDİ, eski şifre geçerli','ok'=>false];
                        }
                    }catch(Throwable $e){
                        $results['failed']++;
                        $results['details'][]=['name'=>$full_name,'status'=>'Hata: '.$e->getMessage().' — şifre değiştirilmedi','ok'=>false];
                    }
                }

                // Sonuçları göster
                $wa_results=json_encode($results,JSON_UNESCAPED_UNICODE);
            }
        }
    }catch(Throwable $e){ $error=$e->getMessage(); }
}

require_once __DIR__.'/layout_top.php';

$personnel=$pdo->query("SELECT * FROM personnel ORDER BY active DESC,name")->fetchAll();
$users=$pdo->query("SELECT u.*, p.name personnel_name FROM app_users u LEFT JOIN personnel p ON p.id=u.personnel_id ORDER BY u.role,u.full_name")->fetchAll();
?>

<?php ds_page_header('Kullanıcılar & Yetkiler', ds_icon('users',24), '', ds_button('Şifremi Değiştir','profile.php','secondary','','',true), false, true); ?>

<?php if($error): ?><?=ds_alert('danger',$error)?><?php endif; ?>
<?php if($ok): ?><?=ds_alert('success',$ok)?><?php endif; ?>

<?php if($wa_results):
    $res=json_decode($wa_results,true);
    if(is_array($res)):
?>
<section class="df-card" style="border-left:4px solid var(--df-success);margin-bottom:var(--df-space-4)">
<h2 style="font-size:var(--df-type-section-size);margin:0 0 var(--df-space-3)">WhatsApp Gönderim Özeti</h2>
<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin:12px 0">
    <div style="background:var(--df-success-soft);border-radius:var(--df-radius-md);padding:12px;text-align:center">
        <div style="font-size:28px;color:var(--df-success-ink);font-weight:bold"><?=$res['success']?></div>
        <div style="font-size:13px;color:var(--df-success-ink)">Başarılı</div>
    </div>
    <div style="background:var(--df-danger-soft);border-radius:var(--df-radius-md);padding:12px;text-align:center">
        <div style="font-size:28px;color:var(--df-danger-ink);font-weight:bold"><?=$res['failed']?></div>
        <div style="font-size:13px;color:var(--df-danger-ink)">Başarısız</div>
    </div>
    <div style="background:var(--df-surface-sunken);border-radius:var(--df-radius-md);padding:12px;text-align:center">
        <div style="font-size:28px;color:var(--df-ink-500);font-weight:bold"><?=$res['no_phone']?></div>
        <div style="font-size:13px;color:var(--df-ink-500)">Telefon Yok</div>
    </div>
</div>
<?php if(!empty($res['details'])): ?>
<div class="df-table-wrap"><table class="df-table">
<thead><tr><th>Kişi</th><th>Durum</th></tr></thead>
<tbody>
<?php foreach($res['details'] as $d): ?>
<tr>
    <td><?=h($d['name'])?></td>
    <td><span class="df-badge df-badge--<?=$d['ok']?'success':'danger'?>"><?=$d['ok']?'✓ ':'✗ '?><?=h($d['status'])?></span></td>
</tr>
<?php endforeach; ?>
</tbody>
</table></div>
<?php endif; ?>
</section>
<?php endif; endif; ?>

<section class="df-card">
<h2 style="font-size:var(--df-type-section-size);margin:0 0 var(--df-space-3)">Yeni Kullanıcı</h2>
<form method="post" class="df-form-grid-3">
<input type="hidden" name="create_user" value="1">

<?php ds_form_field('Ad Soyad', '<input name="full_name" required value="'.h($prefillFullName).'">'); ?>
<?php ds_form_field('Kullanıcı Adı', '<input name="username" required>'); ?>
<?php ds_form_field('Telefon', '<input name="phone" value="'.h($prefillPhone).'">'); ?>
<?php ds_form_field('E-posta', '<input name="email">'); ?>
<?php ds_form_field('Şifre', '<input name="password" type="password" placeholder="Boş kalırsa 123456">'); ?>
<?php ds_form_field('Rol', '<select name="role"><option value="personel">Personel</option><option value="yonetici">Yönetici</option><option value="admin">Admin</option></select>'); ?>

<div class="df-form-span-3">
<?php
$__persOpts='<option value="">Bağlama</option>';
foreach($personnel as $p){ $__persOpts.='<option value="'.$p['id'].'" '.($prefillPersonnelId===(int)$p['id']?'selected':'').'>'.h($p['name'].' / '.($p['role'] ?: '-')).'</option>'; }
ds_form_field('Personel Bağlantısı', '<select name="personnel_id">'.$__persOpts.'</select>');
?>
</div>

<div class="df-form-span-3">
<b style="font-size:13px;color:var(--df-ink-600)">Yetkiler</b>
<div class="df-permission-grid" style="margin-top:8px">
<?php foreach($permLabels as $key=>$label): ?>
<label class="df-permission-chip">
<input type="checkbox" name="permissions[]" value="<?=$key?>" style="width:auto"> <?=h($label)?>
</label>
<?php endforeach; ?>
</div>
</div>

<div class="df-form-span-3">
<label style="display:flex;align-items:center;gap:8px"><input type="checkbox" name="active" checked style="width:auto"> Aktif</label>
</div>
<div class="df-form-span-3"><button class="df-btn df-btn--primary">Kullanıcı Oluştur</button></div>
</form>
</section>

<section class="df-card" style="margin-top:var(--df-space-4)">
<h2 style="font-size:var(--df-type-section-size);margin:0 0 var(--df-space-2)">📲 Toplu WhatsApp Gönderimi</h2>
<p style="color:var(--df-ink-500);font-size:14px;margin:0 0 16px">Seçilen kullanıcılara giriş bilgilerini WhatsApp ile gönderin. İsterseniz yeni rastgele şifreler üretebilirsiniz.</p>
<form method="post">
<input type="hidden" name="send_bulk_wa" value="1">

<div style="margin:0 0 12px">
<label style="margin-bottom:4px"><input type="radio" name="wa_mode" value="all" checked> Tüm Aktif Kullanıcılara</label>
<label style="margin-bottom:4px"><input type="radio" name="wa_mode" value="selected"> Seçilenlere</label>
</div>

<div style="max-height:250px;overflow-y:auto;border:1px solid var(--df-hairline);border-radius:var(--df-radius-sm);padding:10px">
<?php foreach($users as $u): if(!($u['active']??1)) continue; ?>
<label style="display:block;margin:6px 0;padding:8px;background:var(--df-surface-sunken);border-radius:var(--df-radius-sm)">
<input type="checkbox" name="selected_users[]" value="<?=$u['id']?>" style="width:auto;margin-right:8px">
<?=h($u['full_name'] ?: $u['username'])?> (<?=h($u['username'])?>)
<?php if(!($u['phone'] ?? '')): ?><span class="df-badge df-badge--danger" style="margin-left:8px">Telefon yok</span><?php endif; ?>
</label>
<?php endforeach; ?>
</div>

<label style="margin-top:12px;display:block"><input type="checkbox" name="gen_new_password" value="1" checked style="width:auto"> Yeni rastgele şifre üret ve kaydet</label>

<button class="df-btn df-btn--primary" style="width:100%;margin-top:12px;background:var(--df-success)">📲 WhatsApp ile Gönder</button>
</form>
</section>

<section class="df-card" style="margin-top:var(--df-space-4)">
<h2 style="font-size:var(--df-type-section-size);margin:0 0 var(--df-space-3)">Mevcut Kullanıcılar</h2>
<?php foreach($users as $u): 
$perms=json_decode($u['permissions'] ?? '[]',true);
if(!is_array($perms)) $perms=[];
?>
<form method="post" class="df-form-grid-3" style="border-bottom:1px solid var(--df-hairline);padding-bottom:18px;margin-bottom:18px">
<input type="hidden" name="update_user" value="1">
<input type="hidden" name="user_id" value="<?=$u['id']?>">

<?php ds_form_field('Ad Soyad', '<input name="full_name" value="'.h($u['full_name']).'">'); ?>
<?php ds_form_field('Kullanıcı Adı', '<input name="username" value="'.h($u['username']).'">'); ?>
<?php ds_form_field('Telefon', '<input name="phone" value="'.h($u['phone']).'">'); ?>
<?php ds_form_field('E-posta', '<input name="email" value="'.h($u['email']).'">'); ?>
<?php ds_form_field('Yeni Şifre', '<input name="password" type="password" placeholder="Değişmeyecekse boş bırak">'); ?>
<?php
$__roleOpts='';
foreach(['personel'=>'Personel','yonetici'=>'Yönetici','admin'=>'Admin'] as $rk=>$rv){ $__roleOpts.='<option value="'.$rk.'" '.($u['role']===$rk?'selected':'').'>'.$rv.'</option>'; }
ds_form_field('Rol', '<select name="role">'.$__roleOpts.'</select>');
?>

<div class="df-form-span-3">
<?php
$__persOpts2='<option value="">Bağlama</option>';
foreach($personnel as $p){ $__persOpts2.='<option value="'.$p['id'].'" '.($u['personnel_id']==$p['id']?'selected':'').'>'.h($p['name'].' / '.($p['role'] ?: '-')).'</option>'; }
ds_form_field('Personel Bağlantısı', '<select name="personnel_id">'.$__persOpts2.'</select>');
?>
</div>

<div class="df-form-span-3">
<b style="font-size:13px;color:var(--df-ink-600)">Yetkiler</b>
<div class="df-permission-grid" style="margin-top:8px">
<?php foreach($permLabels as $key=>$label): ?>
<label class="df-permission-chip">
<input type="checkbox" name="permissions[]" value="<?=$key?>" <?=in_array($key,$perms,true)?'checked':''?> style="width:auto"> <?=h($label)?>
</label>
<?php endforeach; ?>
</div>
</div>

<div class="df-form-span-3"><label style="display:flex;align-items:center;gap:8px"><input type="checkbox" name="active" <?=$u['active']?'checked':''?> style="width:auto"> Aktif</label></div>
<div class="df-form-span-3"><button class="df-btn df-btn--secondary">Güncelle</button></div>

</form>
<form method="post" style="display:flex;gap:8px;align-items:center;margin:0 0 14px;padding:10px;background:var(--df-surface-sunken);border:1px solid var(--df-hairline);border-radius:var(--df-radius-md)">
<input type="hidden" name="reset_user_pw" value="1">
<input type="hidden" name="user_id" value="<?=$u['id']?>">
<input type="password" name="newpw" placeholder="🔒 Yeni şifre (sadece şifreyi değiştirir)" minlength="4" required style="flex:1;margin:0">
<button class="df-btn df-btn--secondary" style="white-space:nowrap">Şifreyi Değiştir</button>
</form>
<?php if(($u['phone'] ?? '') && ($u['active'] ?? true)): ?>
<!-- P0-AUTH-02 (2026-07-17, ACİL): bu ayrı form ÖNCEDEN yukarıdaki "Güncelle" formunun İÇİNDE
     (nested <form>) idi — HTML'de form iç içe geçemez, tarayıcı içteki form etiketini yok sayıp
     alanlarını dıştaki forma katabiliyordu; bu da "Şifre Sıfırla ve WhatsApp ile Gönder"e
     basıldığında update_user VE send_bulk_wa'nın birlikte, öngörülemez alan değerleriyle tetiklenme
     riski taşıyordu. Artık dıştaki formun DIŞINDA, kendi başına bağımsız bir form. -->
<details style="margin-top:-8px;margin-bottom:18px;padding-top:0">
<summary style="cursor:pointer;font-weight:500;color:var(--df-accent)">📲 Şifre Sıfırla ve WhatsApp ile Gönder</summary>
<form method="post" style="margin-top:10px">
<input type="hidden" name="send_bulk_wa" value="1">
<input type="hidden" name="wa_mode" value="selected">
<input type="hidden" name="selected_users[]" value="<?=$u['id']?>">
<input type="hidden" name="gen_new_password" value="1">
<button class="df-btn df-btn--primary" style="width:100%;background:var(--df-success);margin-top:8px">📲 Şifre Sıfırla ve WhatsApp ile Gönder</button>
</form>
</details>
<?php endif; ?>
<?php endforeach; ?>
</section>

<style>
body.nav-compact .df-form-grid-3{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:0 var(--df-space-4)}
body.nav-compact .df-form-span-3{grid-column:1 / -1}
body.nav-compact .df-permission-grid{display:grid;grid-template-columns:repeat(3,minmax(160px,1fr));gap:var(--df-space-2)}
body.nav-compact .df-permission-chip{background:var(--df-surface-sunken);border:1px solid var(--df-hairline);border-radius:var(--df-radius-md);padding:var(--df-space-2) var(--df-space-3);display:flex;align-items:center;gap:8px;font-size:var(--df-type-body-size);color:var(--df-ink-900)}
@media(max-width:900px){body.nav-compact .df-form-grid-3{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:640px){body.nav-compact .df-form-grid-3,body.nav-compact .df-permission-grid{grid-template-columns:1fr}}
</style>

<?php require_once __DIR__.'/layout_bottom.php'; ?>
