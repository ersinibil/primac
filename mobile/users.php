<?php
require_once 'common.php';
if(!$isAdmin){ header('Location: index.php'); exit; }
$pdo=db();
$ok=''; $er='';

$permLabels=module_list();

// Şifre sıfırla
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['reset_pass'])){
    $uid=(int)$_POST['uid'];
    $np=trim($_POST['new_pass'] ?? '');
    try{
        if(strlen($np)<6) throw new Exception('Şifre en az 6 karakter.');
        $pdo->prepare("UPDATE app_users SET password_hash=? WHERE id=?")->execute([password_hash($np,PASSWORD_DEFAULT),$uid]);
        $ok='Şifre güncellendi.';
    }catch(Throwable $e){ $er=$e->getMessage(); }
    header('Location: users.php'); exit;
}

// Yetki/rol güncelle
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['save_perms'])){
    $uid=(int)$_POST['uid'];
    $role=$_POST['role'] ?? 'personel';
    $perms=$_POST['permissions'] ?? [];
    try{
        $pdo->prepare("UPDATE app_users SET role=?,permissions=?,active=? WHERE id=?")->execute([$role,json_encode($perms),(int)($_POST['active'] ?? 1),$uid]);
        $ok='Kullanıcı güncellendi.';
    }catch(Throwable $e){ $er=$e->getMessage(); }
    header('Location: users.php'); exit;
}

// Yeni kullanıcı
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['create_user'])){
    try{
        $uname=trim($_POST['username'] ?? '');
        $fn=trim($_POST['full_name'] ?? '');
        $pw=trim($_POST['password'] ?? '');
        $role=$_POST['role'] ?? 'personel';
        $perms=$_POST['permissions'] ?? [];
        if(!$uname || !$pw) throw new Exception('Kullanıcı adı ve şifre zorunlu.');
        if(strlen($pw)<6) throw new Exception('Şifre en az 6 karakter.');
        $chk=$pdo->prepare("SELECT id FROM app_users WHERE username=?"); $chk->execute([$uname]);
        if($chk->fetch()) throw new Exception('Bu kullanıcı adı zaten var.');
        $pdo->prepare("INSERT INTO app_users(username,full_name,password_hash,role,permissions,active) VALUES(?,?,?,?,?,1)")
            ->execute([$uname,$fn,password_hash($pw,PASSWORD_DEFAULT),$role,json_encode($perms)]);
        $ok='Kullanıcı oluşturuldu.';
    }catch(Throwable $e){ $er=$e->getMessage(); }
    header('Location: users.php'); exit;
}

$users=$pdo->query("SELECT u.*,p.name pname FROM app_users u LEFT JOIN personnel p ON p.id=u.personnel_id ORDER BY u.role,u.full_name")->fetchAll();
topx('Kullanıcılar & Yetkiler');
?>
<?php if($ok): ?><div class="notice"><?=htmlspecialchars($ok)?></div><?php endif; ?>
<?php if($er): ?><div class="err"><?=htmlspecialchars($er)?></div><?php endif; ?>

<details class="panel">
  <summary style="font-weight:900;cursor:pointer">➕ Yeni Kullanıcı</summary>
  <form method="post" style="margin-top:10px">
    <label style="color:#94a3b8;font-size:12px">Kullanıcı Adı</label>
    <input name="username" required placeholder="kullanici_adi">
    <label style="color:#94a3b8;font-size:12px">Ad Soyad</label>
    <input name="full_name" placeholder="Ad Soyad">
    <label style="color:#94a3b8;font-size:12px">Şifre</label>
    <input type="password" name="password" minlength="6" required placeholder="En az 6 karakter">
    <label style="color:#94a3b8;font-size:12px">Rol</label>
    <select name="role">
      <option value="personel">Personel</option>
      <option value="yonetici">Yönetici</option>
      <option value="admin">Admin</option>
    </select>
    <div style="margin:8px 0 4px;font-size:13px;color:#94a3b8">Yetkiler</div>
    <?php foreach($permLabels as $k=>$v): if($k==='users') continue; ?>
    <label style="display:flex;align-items:center;gap:8px;background:rgba(255,255,255,.06);border-radius:10px;padding:8px;margin:4px 0">
      <input type="checkbox" name="permissions[]" value="<?=htmlspecialchars($k)?>" style="width:auto">
      <?=htmlspecialchars($v)?>
    </label>
    <?php endforeach; ?>
    <button class="btn dark" name="create_user" value="1" style="width:100%;padding:13px;margin-top:10px">➕ Oluştur</button>
  </form>
</details>

<?php foreach($users as $u):
  $perms=json_decode($u['permissions'] ?? '[]',true);
  if(!is_array($perms)) $perms=[];
  $roleColor=['admin'=>'#dc2626','yonetici'=>'#7c3aed','yönetici'=>'#7c3aed'][$u['role']] ?? '#2563eb';
  $isMe=(int)$u['id']===$ME;
?>
<div class="panel">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">
    <div>
      <b><?=htmlspecialchars($u['full_name'] ?: $u['username'])?></b>
      <?php if($isMe): ?><span style="font-size:11px;background:#2563eb;color:#fff;border-radius:8px;padding:2px 6px;margin-left:4px">Sen</span><?php endif; ?>
      <div style="font-size:12px;color:#94a3b8"><?=htmlspecialchars($u['username'])?> · <?=htmlspecialchars($u['pname'] ?? 'Personel bağlı değil')?></div>
    </div>
    <span style="background:<?=$roleColor?>22;color:<?=$roleColor?>;border-radius:8px;padding:4px 8px;font-size:12px;font-weight:700"><?=htmlspecialchars($u['role'])?></span>
  </div>

  <details>
    <summary style="font-size:13px;color:#94a3b8;cursor:pointer">⚙️ Yetki & Rol Düzenle</summary>
    <form method="post" style="margin-top:10px">
      <input type="hidden" name="uid" value="<?=(int)$u['id']?>">
      <label style="color:#94a3b8;font-size:12px">Rol</label>
      <select name="role">
        <?php foreach(['personel','yonetici','admin'] as $r): ?>
        <option value="<?=$r?>" <?=$u['role']===$r?'selected':''?>><?=ucfirst($r)?></option>
        <?php endforeach; ?>
      </select>
      <label style="display:flex;align-items:center;gap:8px;margin:8px 0">
        <input type="checkbox" name="active" value="1" <?=($u['active']??1)?'checked':''?> style="width:auto">
        Aktif
      </label>
      <?php foreach($permLabels as $k=>$v): if($k==='users') continue; ?>
      <label style="display:flex;align-items:center;gap:8px;background:rgba(255,255,255,.06);border-radius:10px;padding:8px;margin:4px 0;font-size:13px">
        <input type="checkbox" name="permissions[]" value="<?=htmlspecialchars($k)?>" <?=in_array($k,$perms,true)?'checked':''?> style="width:auto">
        <?=htmlspecialchars($v)?>
      </label>
      <?php endforeach; ?>
      <button class="btn dark" name="save_perms" value="1" style="width:100%;padding:11px;margin-top:8px">💾 Kaydet</button>
    </form>
  </details>

  <details style="margin-top:6px">
    <summary style="font-size:13px;color:#f87171;cursor:pointer">🔑 Şifre Sıfırla</summary>
    <form method="post" style="margin-top:8px;display:flex;gap:8px">
      <input type="hidden" name="uid" value="<?=(int)$u['id']?>">
      <input type="password" name="new_pass" minlength="6" required placeholder="Yeni şifre" style="flex:1;margin:0">
      <button class="btn dark" name="reset_pass" value="1" style="padding:10px 14px;margin:0">Sıfırla</button>
    </form>
  </details>
</div>
<?php endforeach; ?>

<?php botx(); ?>
