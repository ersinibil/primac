<?php
require_once 'common.php';
require_once __DIR__.'/../share_lib.php';
if(!$isAdmin){ header('Location: index.php'); exit; }
$pdo=db();
$ok=''; $er=''; $wa_results='';

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
    // HOTFIX-02 EK (2026-07-17, ACİL — Ece code-review bulgusu): web users.php'deki rol whitelist +
    // "ana yönetici (uid=1) korunur" kuralı burada hiç yoktu. Bu sayfa zaten yalnızca $isAdmin
    // (admin/yönetici) erişimine kapalı olduğu için "admin-olmayan biri admin yapıyor" senaryosu
    // web'deki gibi yok, ama whitelist dışı bir rol string'i doğrudan kaydedilebiliyordu VE uid=1
    // (baş yönetici) burada rütbesi düşürülüp/pasifleştirilebiliyordu — web ile aynı korumaya taşındı.
    $__validRoles = ['personel','yonetici','admin'];
    $role = in_array($_POST['role'] ?? '', $__validRoles, true) ? $_POST['role'] : 'personel';
    $perms=$_POST['permissions'] ?? [];
    $active=(int)($_POST['active'] ?? 1);
    if($uid===1){ $role='admin'; $active=1; }
    try{
        $pdo->prepare("UPDATE app_users SET role=?,permissions=?,active=? WHERE id=?")->execute([$role,json_encode($perms),$active,$uid]);
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
        // HOTFIX-02 EK: whitelist — geçersiz/rastgele bir rol string'i artık kaydedilemez.
        $__validRoles = ['personel','yonetici','admin'];
        $role = in_array($_POST['role'] ?? '', $__validRoles, true) ? $_POST['role'] : 'personel';
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

// Toplu WhatsApp gönder
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['send_bulk_wa'])){
    $selected=$_POST['selected_users'] ?? [];
    $mode=$_POST['wa_mode'] ?? 'all';
    $genNewPw=isset($_POST['gen_new_password'])?1:0;

    if($mode==='selected' && empty($selected)){
        $er='Lütfen en az bir kullanıcı seçin.';
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
                    $password=generate_random_password(10);
                    $pdo->prepare("UPDATE app_users SET password_hash=? WHERE id=?")->execute([password_hash($password,PASSWORD_DEFAULT),$uid]);
                }

                if(!$password){
                    $results['failed']++;
                    $results['details'][]=['name'=>$full_name,'status'=>'Şifre bulunamadı','ok'=>false];
                    continue;
                }

                $appName=(app_config()['app_name'] ?? 'OTS');
                $txt="🔐 ".$appName." giriş bilgileriniz\nKullanıcı: ".$username."\nŞifre: ".$password;
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

        $_SESSION['wa_results']=$results;
    }
    header('Location: users.php'); exit;
}

$users=$pdo->query("SELECT u.*,p.name pname FROM app_users u LEFT JOIN personnel p ON p.id=u.personnel_id ORDER BY u.role,u.full_name")->fetchAll();

// Session'dan wa_results oku ve temizle
if(!empty($_SESSION['wa_results'])){
    $wa_results=$_SESSION['wa_results'];
    unset($_SESSION['wa_results']);
}

topx('Kullanıcılar & Yetkiler');
?>
<?php if($ok): ?><?=ds_alert('success',$ok)?><?php endif; ?>
<?php if($er): ?><?=ds_alert('danger',$er)?><?php endif; ?>

<?php if($wa_results && is_array($wa_results)):
    $res=$wa_results;
?>
<div class="df-panel">
<h3 style="margin-top:0">WhatsApp Gönderim Özeti</h3>
<div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:8px;margin-bottom:12px">
    <div style="background:rgba(22,163,74,.2);border-radius:12px;padding:10px;text-align:center;border:1px solid rgba(22,163,74,.4)">
        <div style="font-size:24px;color:#86efac;font-weight:bold"><?=$res['success']?></div>
        <div style="font-size:11px;color:#86efac">Başarılı</div>
    </div>
    <div style="background:rgba(239,68,68,.2);border-radius:12px;padding:10px;text-align:center;border:1px solid rgba(239,68,68,.4)">
        <div style="font-size:24px;color:#fca5a5;font-weight:bold"><?=$res['failed']?></div>
        <div style="font-size:11px;color:#fca5a5">Başarısız</div>
    </div>
    <div style="background:rgba(107,114,128,.2);border-radius:12px;padding:10px;text-align:center;border:1px solid rgba(107,114,128,.4)">
        <div style="font-size:24px;color:#d1d5db;font-weight:bold"><?=$res['no_phone']?></div>
        <div style="font-size:11px;color:#d1d5db">Telefon Yok</div>
    </div>
</div>
<?php if(!empty($res['details'])): ?>
<div style="font-size:12px;max-height:200px;overflow-y:auto">
<?php foreach($res['details'] as $d): ?>
<div style="display:flex;justify-content:space-between;align-items:center;padding:8px;border-bottom:1px solid var(--df-hairline,rgba(255,255,255,.1))">
    <div><?=h($d['name'])?></div>
    <div style="background:<?=$d['ok']?'rgba(22,163,74,.3)':'rgba(239,68,68,.3)'?>;color:<?=$d['ok']?'#86efac':'#fca5a5'?>;border-radius:6px;padding:3px 8px;font-size:11px">
    <?=$d['ok']?'✓':'✗'?> <?=h($d['status'])?>
    </div>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>
</div>
<?php endif; ?>

<details class="df-panel">
  <summary style="font-weight:900;cursor:pointer"><?=ds_icon('send',16)?> Toplu WhatsApp Gönderimi</summary>
  <form method="post" style="margin-top:10px">
    <input type="hidden" name="send_bulk_wa" value="1">

    <div style="margin:0 0 10px">
    <label style="display:flex;align-items:center;gap:8px;margin-bottom:8px">
      <input type="radio" name="wa_mode" value="all" checked style="width:auto">
      <span>Tüm Aktif Kullanıcılara</span>
    </label>
    <label style="display:flex;align-items:center;gap:8px">
      <input type="radio" name="wa_mode" value="selected" style="width:auto">
      <span>Seçilenlere</span>
    </label>
    </div>

    <div style="max-height:180px;overflow-y:auto;border:1px solid var(--df-hairline,rgba(255,255,255,.12));border-radius:12px;padding:8px;margin-bottom:10px">
    <?php foreach($users as $u): if(!($u['active'] ?? true)) continue; ?>
    <label style="display:flex;align-items:center;gap:8px;padding:8px;margin:4px 0;background:var(--df-surface-sunken,rgba(255,255,255,.05));border-radius:10px;font-size:12px">
      <input type="checkbox" name="selected_users[]" value="<?=$u['id']?>" style="width:auto">
      <span><?=h($u['full_name'] ?: $u['username'])?></span>
      <?php if(!($u['phone'] ?? '')): ?><span style="color:#ef4444;font-size:10px;margin-left:auto">Telefon yok</span><?php endif; ?>
    </label>
    <?php endforeach; ?>
    </div>

    <label style="display:flex;align-items:center;gap:8px;margin-bottom:10px;font-size:13px">
      <input type="checkbox" name="gen_new_password" value="1" checked style="width:auto">
      <span>Yeni rastgele şifre üret</span>
    </label>

    <button class="df-btn df-btn--primary df-btn--lg" style="width:100%;background:var(--df-success)"><?=ds_icon('send',16)?> WhatsApp ile Gönder</button>
  </form>
</details>

<details class="df-panel">
  <summary style="font-weight:900;cursor:pointer"><?=ds_icon('plus',16)?> Yeni Kullanıcı</summary>
  <form method="post" style="margin-top:10px">
    <label style="color:var(--df-ink-500,#94a3b8);font-size:12px">Kullanıcı Adı</label>
    <input name="username" required placeholder="kullanici_adi">
    <label style="color:var(--df-ink-500,#94a3b8);font-size:12px">Ad Soyad</label>
    <input name="full_name" placeholder="Ad Soyad">
    <label style="color:var(--df-ink-500,#94a3b8);font-size:12px">Şifre</label>
    <input type="password" name="password" minlength="6" required placeholder="En az 6 karakter">
    <label style="color:var(--df-ink-500,#94a3b8);font-size:12px">Rol</label>
    <select name="role">
      <option value="personel">Personel</option>
      <option value="yonetici">Yönetici</option>
      <option value="admin">Admin</option>
    </select>
    <div style="margin:8px 0 4px;font-size:13px;color:var(--df-ink-500,#94a3b8)">Yetkiler</div>
    <?php foreach($permLabels as $k=>$v): if($k==='users') continue; ?>
    <label style="display:flex;align-items:center;gap:8px;background:var(--df-surface-sunken,rgba(255,255,255,.06));border-radius:10px;padding:8px;margin:4px 0">
      <input type="checkbox" name="permissions[]" value="<?=h($k)?>" style="width:auto">
      <?=h($v)?>
    </label>
    <?php endforeach; ?>
    <button class="df-btn df-btn--primary df-btn--lg" name="create_user" value="1" style="width:100%;margin-top:10px"><?=ds_icon('plus',16)?> Oluştur</button>
  </form>
</details>

<?php foreach($users as $u):
  $perms=json_decode($u['permissions'] ?? '[]',true);
  if(!is_array($perms)) $perms=[];
  $roleTone=['admin'=>'danger','yonetici'=>'info','yönetici'=>'info'][$u['role']] ?? 'info';
  $isMe=(int)$u['id']===$ME;
?>
<div class="df-panel">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">
    <div>
      <b><?=h($u['full_name'] ?: $u['username'])?></b>
      <?php if($isMe): ?><span class="df-badge df-badge--info" style="margin-left:4px">Sen</span><?php endif; ?>
      <div style="font-size:12px;color:var(--df-ink-500,#94a3b8)"><?=h($u['username'])?> · <?=h($u['pname'] ?? 'Personel bağlı değil')?></div>
    </div>
    <span class="df-badge df-badge--<?=$roleTone?>"><?=h($u['role'])?></span>
  </div>

  <details>
    <summary style="font-size:13px;color:var(--df-ink-500,#94a3b8);cursor:pointer"><?=ds_icon('settings',14)?> Yetki & Rol Düzenle</summary>
    <form method="post" style="margin-top:10px">
      <input type="hidden" name="uid" value="<?=(int)$u['id']?>">
      <label style="color:var(--df-ink-500,#94a3b8);font-size:12px">Rol</label>
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
      <label style="display:flex;align-items:center;gap:8px;background:var(--df-surface-sunken,rgba(255,255,255,.06));border-radius:10px;padding:8px;margin:4px 0;font-size:13px">
        <input type="checkbox" name="permissions[]" value="<?=h($k)?>" <?=in_array($k,$perms,true)?'checked':''?> style="width:auto">
        <?=h($v)?>
      </label>
      <?php endforeach; ?>
      <button class="df-btn df-btn--primary df-btn--lg" name="save_perms" value="1" style="width:100%;margin-top:8px"><?=ds_icon('check',16)?> Kaydet</button>
    </form>
  </details>

  <details style="margin-top:6px">
    <summary style="font-size:13px;color:#f87171;cursor:pointer"><?=ds_icon('user',14)?> Şifre Sıfırla</summary>
    <form method="post" style="margin-top:8px;display:flex;gap:8px">
      <input type="hidden" name="uid" value="<?=(int)$u['id']?>">
      <input type="password" name="new_pass" minlength="6" required placeholder="Yeni şifre" style="flex:1;margin:0">
      <button class="df-btn df-btn--primary" name="reset_pass" value="1">Sıfırla</button>
    </form>
  </details>

  <?php if(($u['phone'] ?? '') && ($u['active'] ?? true)): ?>
  <details style="margin-top:6px">
    <summary style="font-size:13px;color:#60a5fa;cursor:pointer"><?=ds_icon('send',14)?> WhatsApp ile Gönder</summary>
    <form method="post" style="margin-top:8px">
      <input type="hidden" name="send_bulk_wa" value="1">
      <input type="hidden" name="wa_mode" value="selected">
      <input type="hidden" name="selected_users[]" value="<?=(int)$u['id']?>">
      <input type="hidden" name="gen_new_password" value="1">
      <button class="df-btn df-btn--primary df-btn--lg" style="width:100%;background:var(--df-success);margin-top:8px"><?=ds_icon('send',16)?> Şifre Sıfırla ve Gönder</button>
    </form>
  </details>
  <?php endif; ?>
</div>
<?php endforeach; ?>

<?php botx(); ?>
