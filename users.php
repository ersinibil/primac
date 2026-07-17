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

                        if($genNewPw){
                            // Yeni rastgele şifre üret ve kaydet
                            $password=generate_random_password(10);
                            $pdo->prepare("UPDATE app_users SET password_hash=? WHERE id=?")->execute([password_hash($password,PASSWORD_DEFAULT),$uid]);
                        }

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
                            $results['success']++;
                            $results['details'][]=['name'=>$full_name,'status'=>'Gönderildi','ok'=>true];
                        } else {
                            $results['failed']++;
                            $results['details'][]=['name'=>$full_name,'status'=>'API hatası','ok'=>false];
                        }
                    }catch(Throwable $e){
                        $results['failed']++;
                        $results['details'][]=['name'=>$full_name,'status'=>'Hata: '.$e->getMessage(),'ok'=>false];
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

<div class="panel-head">
<h1>Kullanıcılar & Yetkiler</h1>
<a class="btn secondary" href="profile.php">Şifremi Değiştir</a>
</div>

<?php if($error): ?><div class="alert"><?=h($error)?></div><?php endif; ?>
<?php if($ok): ?><div class="ok"><?=h($ok)?></div><?php endif; ?>

<?php if($wa_results):
    $res=json_decode($wa_results,true);
    if(is_array($res)):
?>
<section class="panel" style="border-left:4px solid #16a34a">
<h2>WhatsApp Gönderim Özeti</h2>
<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin:12px 0">
    <div style="background:rgba(22,163,74,.15);border-radius:12px;padding:12px;text-align:center">
        <div style="font-size:28px;color:#16a34a;font-weight:bold"><?=$res['success']?></div>
        <div style="font-size:13px;color:#16a34a">Başarılı</div>
    </div>
    <div style="background:rgba(239,68,68,.15);border-radius:12px;padding:12px;text-align:center">
        <div style="font-size:28px;color:#ef4444;font-weight:bold"><?=$res['failed']?></div>
        <div style="font-size:13px;color:#ef4444">Başarısız</div>
    </div>
    <div style="background:rgba(107,114,128,.15);border-radius:12px;padding:12px;text-align:center">
        <div style="font-size:28px;color:#6b7280;font-weight:bold"><?=$res['no_phone']?></div>
        <div style="font-size:13px;color:#6b7280">Telefon Yok</div>
    </div>
</div>
<?php if(!empty($res['details'])): ?>
<table style="width:100%;border-collapse:collapse;font-size:13px;margin-top:12px">
<thead><tr style="border-bottom:1px solid #e5e7eb"><th style="text-align:left;padding:8px">Kişi</th><th style="text-align:left;padding:8px">Durum</th></tr></thead>
<tbody>
<?php foreach($res['details'] as $d): ?>
<tr style="border-bottom:1px solid #e5e7eb">
    <td style="padding:8px"><?=h($d['name'])?></td>
    <td style="padding:8px">
        <span style="display:inline-block;background:<?=$d['ok']?'rgba(22,163,74,.2)':'rgba(239,68,68,.2)'?>;color:<?=$d['ok']?'#16a34a':'#ef4444'?>;border-radius:6px;padding:3px 8px;font-size:12px">
        <?=$d['ok']?'✓ ':'✗ '?><?=h($d['status'])?></span>
    </td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
<?php endif; ?>
</section>
<?php endif; endif; ?>

<section class="panel">
<h2>Yeni Kullanıcı</h2>
<form method="post" class="form-grid">
<input type="hidden" name="create_user" value="1">

<label>Ad Soyad
<input name="full_name" required value="<?=h($prefillFullName)?>">
</label>

<label>Kullanıcı Adı
<input name="username" required>
</label>

<label>Telefon
<input name="phone" value="<?=h($prefillPhone)?>">
</label>

<label>E-posta
<input name="email">
</label>

<label>Şifre
<input name="password" type="password" placeholder="Boş kalırsa 123456">
</label>

<label>Rol
<select name="role">
<option value="personel">Personel</option>
<option value="yonetici">Yönetici</option>
<option value="admin">Admin</option>
</select>
</label>

<label class="full">Personel Bağlantısı
<select name="personnel_id">
<option value="">Bağlama</option>
<?php foreach($personnel as $p): ?>
<option value="<?=$p['id']?>" <?=$prefillPersonnelId===(int)$p['id']?'selected':''?>><?=h($p['name'].' / '.($p['role'] ?: '-'))?></option>
<?php endforeach; ?>
</select>
</label>

<div class="full">
<h3>Yetkiler</h3>
<div style="display:grid;grid-template-columns:repeat(3,minmax(160px,1fr));gap:10px">
<?php foreach($permLabels as $key=>$label): ?>
<label style="background:#f8fafc;border:1px solid #e5e7eb;border-radius:14px;padding:10px">
<input type="checkbox" name="permissions[]" value="<?=$key?>" style="width:auto"> <?=h($label)?>
</label>
<?php endforeach; ?>
</div>
</div>

<label class="full"><input type="checkbox" name="active" checked style="width:auto"> Aktif</label>
<button class="btn">Kullanıcı Oluştur</button>
</form>
</section>

<section class="panel">
<h2>📲 Toplu WhatsApp Gönderimi</h2>
<p style="color:#666;font-size:14px;margin:0 0 16px">Seçilen kullanıcılara giriş bilgilerini WhatsApp ile gönderin. İsterseniz yeni rastgele şifreler üretebilirsiniz.</p>
<form method="post" class="form-grid">
<input type="hidden" name="send_bulk_wa" value="1">

<div style="margin:0 0 12px">
<label style="margin-bottom:4px"><input type="radio" name="wa_mode" value="all" checked> Tüm Aktif Kullanıcılara</label>
<label style="margin-bottom:4px"><input type="radio" name="wa_mode" value="selected"> Seçilenlere</label>
</div>

<div style="max-height:250px;overflow-y:auto;border:1px solid #e5e7eb;border-radius:8px;padding:10px">
<?php foreach($users as $u): if(!($u['active']??1)) continue; ?>
<label style="display:block;margin:6px 0;padding:8px;background:#f8fafc;border-radius:6px">
<input type="checkbox" name="selected_users[]" value="<?=$u['id']?>" style="width:auto;margin-right:8px">
<?=h($u['full_name'] ?: $u['username'])?> (<?=h($u['username'])?>)
<?php if(!($u['phone'] ?? '')): ?><span style="color:#ef4444;font-size:12px;margin-left:8px">Telefon yok</span><?php endif; ?>
</label>
<?php endforeach; ?>
</div>

<label style="margin-top:12px"><input type="checkbox" name="gen_new_password" value="1" checked> Yeni rastgele şifre üret ve kaydet</label>

<button class="btn" style="width:100%;padding:12px;margin-top:12px;background:#16a34a;color:white">📲 WhatsApp ile Gönder</button>
</form>
</section>

<section class="panel">
<h2>Mevcut Kullanıcılar</h2>
<?php foreach($users as $u): 
$perms=json_decode($u['permissions'] ?? '[]',true);
if(!is_array($perms)) $perms=[];
?>
<form method="post" class="form-grid" style="border-bottom:1px solid #eef2f6;padding-bottom:18px;margin-bottom:18px">
<input type="hidden" name="update_user" value="1">
<input type="hidden" name="user_id" value="<?=$u['id']?>">

<label>Ad Soyad
<input name="full_name" value="<?=h($u['full_name'])?>">
</label>

<label>Kullanıcı Adı
<input name="username" value="<?=h($u['username'])?>">
</label>

<label>Telefon
<input name="phone" value="<?=h($u['phone'])?>">
</label>

<label>E-posta
<input name="email" value="<?=h($u['email'])?>">
</label>

<label>Yeni Şifre
<input name="password" type="password" placeholder="Değişmeyecekse boş bırak">
</label>

<label>Rol
<select name="role">
<?php foreach(['personel'=>'Personel','yonetici'=>'Yönetici','admin'=>'Admin'] as $rk=>$rv): ?>
<option value="<?=$rk?>" <?=$u['role']===$rk?'selected':''?>><?=$rv?></option>
<?php endforeach; ?>
</select>
</label>

<label class="full">Personel Bağlantısı
<select name="personnel_id">
<option value="">Bağlama</option>
<?php foreach($personnel as $p): ?>
<option value="<?=$p['id']?>" <?=$u['personnel_id']==$p['id']?'selected':''?>><?=h($p['name'].' / '.($p['role'] ?: '-'))?></option>
<?php endforeach; ?>
</select>
</label>

<div class="full">
<b>Yetkiler</b>
<div style="display:grid;grid-template-columns:repeat(3,minmax(160px,1fr));gap:10px;margin-top:8px">
<?php foreach($permLabels as $key=>$label): ?>
<label style="background:#f8fafc;border:1px solid #e5e7eb;border-radius:14px;padding:10px">
<input type="checkbox" name="permissions[]" value="<?=$key?>" <?=in_array($key,$perms,true)?'checked':''?> style="width:auto"> <?=h($label)?>
</label>
<?php endforeach; ?>
</div>
</div>

<label class="full"><input type="checkbox" name="active" <?=$u['active']?'checked':''?> style="width:auto"> Aktif</label>
<button class="btn secondary">Güncelle</button>

<?php if(($u['phone'] ?? '') && ($u['active'] ?? true)): ?>
<details style="margin-top:12px;padding-top:12px;border-top:1px solid #e5e7eb">
<summary style="cursor:pointer;font-weight:500;color:#2563eb">📲 Şifre Sıfırla ve WhatsApp ile Gönder</summary>
<form method="post" style="margin-top:10px">
<input type="hidden" name="send_bulk_wa" value="1">
<input type="hidden" name="wa_mode" value="selected">
<input type="hidden" name="selected_users[]" value="<?=$u['id']?>">
<input type="hidden" name="gen_new_password" value="1">
<button class="btn" style="width:100%;padding:11px;background:#16a34a;color:white;margin-top:8px">📲 Şifre Sıfırla ve WhatsApp ile Gönder</button>
</form>
</details>
<?php endif; ?>
</form>
<?php endforeach; ?>
</section>

<?php require_once __DIR__.'/layout_bottom.php'; ?>
