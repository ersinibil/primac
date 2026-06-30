<?php
require_once 'common.php';
block_personel();
$pdo=db(); $id=(int)($_GET['id']??0); $ok=''; $er='';

if($_SERVER['REQUEST_METHOD']==='POST'){
    try{
        if(isset($_POST['save'])){
            $pdo->prepare("UPDATE personnel SET name=?,role=?,phone=?,email=?,work_type=?,start_date=?,iban=?,notes=?,active=? WHERE id=?")
                ->execute([trim($_POST['name']),trim($_POST['role']??''),trim($_POST['phone']??''),trim($_POST['email']??''),
                           trim($_POST['work_type']??''),($_POST['start_date']??'')?:null,trim($_POST['iban']??''),trim($_POST['notes']??''),
                           isset($_POST['active'])?1:0,$id]);
            $ok='Bilgiler güncellendi.';
        }
        if(isset($_POST['make_login']) && trim($_POST['username']??'')!=='' && trim($_POST['password']??'')!==''){
            $un=trim($_POST['username']);
            $ex=$pdo->prepare("SELECT id FROM app_users WHERE username=?"); $ex->execute([$un]);
            if($ex->fetch()) throw new Exception('Bu kullanıcı adı zaten var.');
            $p=$pdo->prepare("SELECT name,phone,email FROM personnel WHERE id=?"); $p->execute([$id]); $pr=$p->fetch();
            $pdo->prepare("INSERT INTO app_users(username,full_name,phone,email,password_hash,role,personnel_id,active,created_at) VALUES(?,?,?,?,?,?,?,1,NOW())")
                ->execute([$un,$pr['name'],$pr['phone'],$pr['email'],password_hash($_POST['password'],PASSWORD_DEFAULT),'personel',$id]);
            $uid=(int)$pdo->lastInsertId();
            $pdo->prepare("UPDATE personnel SET user_id=?,login_enabled=1 WHERE id=?")->execute([$uid,$id]);
            $ok='Giriş hesabı oluşturuldu.';
        }
        if(isset($_POST['reset_pw']) && trim($_POST['newpw']??'')!=='' && (int)$_POST['uid']){
            $pdo->prepare("UPDATE app_users SET password_hash=? WHERE id=?")->execute([password_hash($_POST['newpw'],PASSWORD_DEFAULT),(int)$_POST['uid']]);
            $ok='Şifre güncellendi.';
        }
    }catch(Throwable $e){ $er=$e->getMessage(); }
}

topx('Personel');
try{
    $s=$pdo->prepare("SELECT * FROM personnel WHERE id=?"); $s->execute([$id]); $p=$s->fetch();
    if(!$p) throw new Exception('Personel bulunamadı.');
    $u=$pdo->prepare("SELECT * FROM app_users WHERE personnel_id=? OR id=? LIMIT 1"); $u->execute([$id,(int)($p['user_id']??0)]); $usr=$u->fetch();
    $acikIs=(int)$pdo->query("SELECT COUNT(*) c FROM jobs WHERE responsible_personnel_id=$id AND status NOT IN ('Tamamlandı','İptal','Teslim Edildi')")->fetch()['c'];
    $acikGorev=(int)$pdo->query("SELECT COUNT(*) c FROM tasks WHERE personnel_id=$id AND status NOT IN ('Tamamlandı','İptal')")->fetch()['c'];
?>
<?php if($ok): ?><div class="notice"><?=htmlspecialchars($ok)?></div><?php endif; ?>
<?php if($er): ?><div class="err"><?=htmlspecialchars($er)?></div><?php endif; ?>

<div class="panel">
  <h2 style="margin:0"><?=htmlspecialchars($p['name'])?></h2>
  <div class="muted"><?=htmlspecialchars($p['role']?:'Personel')?><?=$usr?' · 🔑 '.htmlspecialchars($usr['username']):' · giriş yok'?></div>
  <div style="display:flex;gap:8px;margin-top:10px;flex-wrap:wrap">
    <span style="background:rgba(255,255,255,.08);border-radius:10px;padding:6px 10px;font-size:13px">📋 Açık iş: <b><?=$acikIs?></b></span>
    <span style="background:rgba(34,197,94,.15);color:#86efac;border-radius:10px;padding:6px 10px;font-size:13px">✅ Açık görev: <b><?=$acikGorev?></b></span>
  </div>
  <div style="display:flex;gap:8px;margin-top:10px;flex-wrap:wrap">
    <a class="btn" href="task_new.php" style="background:#334155;color:#fff;flex:1;text-align:center">🎯 Görev Ata</a>
    <a class="btn" href="kpi.php" style="background:#334155;color:#fff;flex:1;text-align:center">🏆 Performans</a>
    <?php if($usr): ?><a class="btn" href="messages.php?with=<?=(int)$usr['id']?>" style="background:#2563eb;color:#fff;flex:1;text-align:center">💬 Mesaj</a><?php endif; ?>
  </div>
</div>

<div class="panel"><b>✏️ Bilgileri Düzenle</b>
<form method="post" style="margin-top:8px">
  <label>Ad Soyad</label><input name="name" value="<?=htmlspecialchars($p['name'])?>" required>
  <label>Görev / Rol</label><input name="role" value="<?=htmlspecialchars($p['role']??'')?>">
  <div style="display:flex;gap:10px"><div style="flex:1"><label>Telefon</label><input name="phone" value="<?=htmlspecialchars($p['phone']??'')?>"></div><div style="flex:1"><label>E-posta</label><input name="email" value="<?=htmlspecialchars($p['email']??'')?>"></div></div>
  <div style="display:flex;gap:10px"><div style="flex:1"><label>Çalışma Tipi</label><input name="work_type" value="<?=htmlspecialchars($p['work_type']??'')?>"></div><div style="flex:1"><label>Başlangıç</label><input type="date" name="start_date" value="<?=htmlspecialchars($p['start_date']??'')?>"></div></div>
  <label>IBAN</label><input name="iban" value="<?=htmlspecialchars($p['iban']??'')?>">
  <label>Not</label><textarea name="notes" rows="2"><?=htmlspecialchars($p['notes']??'')?></textarea>
  <label style="display:flex;align-items:center;gap:8px"><input type="checkbox" name="active" value="1" <?=$p['active']?'checked':''?> style="width:auto;margin:0"> Aktif</label>
  <button class="btn dark" name="save" value="1" style="width:100%;padding:13px;margin-top:8px">💾 Kaydet</button>
</form>
</div>

<div class="panel"><b>🔑 Giriş Hesabı</b>
<?php if($usr): ?>
  <p class="muted" style="margin:8px 0">Kullanıcı: <b><?=htmlspecialchars($usr['username'])?></b> · durum: <?=$usr['active']?'aktif':'pasif'?></p>
  <form method="post" style="display:flex;gap:8px"><input type="hidden" name="uid" value="<?=(int)$usr['id']?>"><input name="newpw" placeholder="Yeni şifre" style="flex:1;margin:0"><button class="btn" name="reset_pw" value="1" style="background:#334155;color:#fff">Şifre Sıfırla</button></form>
<?php else: ?>
  <p class="muted" style="margin:8px 0">Bu personelin uygulama girişi yok. Oluştur:</p>
  <form method="post"><div style="display:flex;gap:8px"><input name="username" placeholder="Kullanıcı adı" style="flex:1;margin:0"><input name="password" placeholder="Şifre" style="flex:1;margin:0"></div>
  <button class="btn dark" name="make_login" value="1" style="width:100%;padding:12px;margin-top:8px">🔑 Giriş Hesabı Oluştur</button></form>
<?php endif; ?>
</div>

<div class="panel"><b>🧾 İşlem Kaydı</b>
  <p class="muted" style="margin:4px 0 8px;font-size:12px">Bu personelin yaptığı son işlemler (düzenleme/ekleme/satış vb.).</p>
  <?php if(function_exists('activity_user_html')) echo activity_user_html($pdo,$usr['id']??0,40); ?>
</div>
<?php
}catch(Throwable $e){ echo '<div class="err">'.htmlspecialchars($e->getMessage()).'</div>'; }
botx();
