<?php
require_once __DIR__.'/boot.php';
require_permission('users');

$pdo=db();
$error='';
$ok='';

$permLabels=module_list();

if($_SERVER['REQUEST_METHOD']==='POST'){
    try{
        if(isset($_POST['create_user'])){
            $perms=$_POST['permissions'] ?? [];
            $hash=password_hash($_POST['password'] ?: '123456', PASSWORD_DEFAULT);
            $pdo->prepare("INSERT INTO app_users(username,full_name,phone,email,password_hash,role,personnel_id,permissions,active) VALUES(?,?,?,?,?,?,?,?,?)")
                ->execute([
                    trim($_POST['username']),
                    trim($_POST['full_name']),
                    trim($_POST['phone']),
                    trim($_POST['email']),
                    $hash,
                    $_POST['role'],
                    (int)$_POST['personnel_id'] ?: null,
                    json_encode($perms,JSON_UNESCAPED_UNICODE),
                    isset($_POST['active'])?1:0
                ]);
            $ok='Kullanıcı oluşturuldu.';
        }

        if(isset($_POST['update_user'])){
            $uid=(int)$_POST['user_id'];
            $perms=$_POST['permissions'] ?? [];
            $pdo->prepare("UPDATE app_users SET username=?, full_name=?, phone=?, email=?, role=?, personnel_id=?, permissions=?, active=? WHERE id=?")
                ->execute([
                    trim($_POST['username']),
                    trim($_POST['full_name']),
                    trim($_POST['phone']),
                    trim($_POST['email']),
                    $_POST['role'],
                    (int)$_POST['personnel_id'] ?: null,
                    json_encode($perms,JSON_UNESCAPED_UNICODE),
                    isset($_POST['active'])?1:0,
                    $uid
                ]);
            if(!empty($_POST['password'])){
                $pdo->prepare("UPDATE app_users SET password_hash=? WHERE id=?")
                    ->execute([password_hash($_POST['password'],PASSWORD_DEFAULT),$uid]);
            }
            $ok='Kullanıcı güncellendi.';
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

<section class="panel">
<h2>Yeni Kullanıcı</h2>
<form method="post" class="form-grid">
<input type="hidden" name="create_user" value="1">

<label>Ad Soyad
<input name="full_name" required>
</label>

<label>Kullanıcı Adı
<input name="username" required>
</label>

<label>Telefon
<input name="phone">
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
<option value="<?=$p['id']?>"><?=h($p['name'].' / '.($p['role'] ?: '-'))?></option>
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
</form>
<?php endforeach; ?>
</section>

<?php require_once __DIR__.'/layout_bottom.php'; ?>
