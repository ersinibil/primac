<?php
require_once 'common.php';
require_once __DIR__.'/../share_lib.php';
require_once __DIR__.'/../personnel_lib.php';
block_personel('personnel');
$pdo=db(); $id=(int)($_GET['id']??0); $ok=''; $er=''; $waCred='';
// SECURITY SPRINT-001 (2026-07-04): şifre/hesap işlemleri admin'e VEYA admin'in ayrıca
// 'personnel_accounts' yetkisi verdiği bir "alt yönetici"ye açık — düz 'personnel' yetkisi yeterli değil.
$canManageAccounts = $isAdmin || (function_exists('user_can') && user_can('personnel_accounts'));
$hasCvCol = personnel_has_cv_column($pdo);

/* Personeli sil (admin-only, topx'tan ÖNCE) */
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['delete_personnel'])){
    if(!$isAdmin){
        $_SESSION['pers_err']='Bu işlem için yetkiniz yok.';
        header('Location: personnel_view.php?id='.$id); exit;
    }
    try{
        // GÜVENLİK (2026-07-03 denetiminde bulundu): personel silinirken bağlı app_users hesabı
        // pasife alınmıyordu — silinen personelin kullanıcı adı/şifresi (veya "beni hatırla" çerezi)
        // hâlâ geçerli kalıp giriş yapabiliyordu. Personel silinmeden ÖNCE bağlı hesabı pasifleştir.
        try{ $pdo->prepare("UPDATE app_users SET active=0 WHERE personnel_id=?")->execute([$id]); }catch(Throwable $e){}
        try{ $pdo->exec("SET FOREIGN_KEY_CHECKS=0"); }catch(Throwable $e){}
        // Alt kayıtları sil
        $pdo->prepare("DELETE FROM personnel_devices WHERE personnel_id=?")->execute([$id]);
        // Personeli sil
        $pdo->prepare("DELETE FROM personnel WHERE id=?")->execute([$id]);
        try{ $pdo->exec("SET FOREIGN_KEY_CHECKS=1"); }catch(Throwable $e){}
        try{ if(function_exists('activity_log')) activity_log('Silme','Personel silindi','personnel #'.$id,'','admin',null,'personnel.php','🗑'); }catch(Throwable $e){}
        header('Location: ../personnel.php?deleted=1'); exit;
    }catch(Throwable $e){
        $_SESSION['pers_err']='Silinemedi: '.$e->getMessage();
        header('Location: personnel_view.php?id='.$id); exit;
    }
}

if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['clear_cv'])){
    try{
        if($hasCvCol){
            $cur=$pdo->prepare("SELECT cv_path FROM personnel WHERE id=?"); $cur->execute([$id]); $row=$cur->fetch();
            if($row && !empty($row['cv_path'])){
                $full=dirname(__DIR__).'/'.$row['cv_path'];
                if(is_file($full)) @unlink($full);
            }
            $pdo->prepare("UPDATE personnel SET cv_path=NULL WHERE id=?")->execute([$id]);
        }
        $_SESSION['pers_ok']='CV kaldırıldı.';
    }catch(Throwable $e){ $_SESSION['pers_err']=$e->getMessage(); }
    header('Location: personnel_view.php?id='.$id); exit;
}

if($_SERVER['REQUEST_METHOD']==='POST'){
    try{
        if(isset($_POST['save'])){
            $cvPath = $hasCvCol ? personnel_handle_cv_upload() : null;
            if($hasCvCol && $cvPath !== null){
                $pdo->prepare("UPDATE personnel SET name=?,role=?,phone=?,email=?,work_type=?,start_date=?,iban=?,notes=?,active=?,cv_path=? WHERE id=?")
                    ->execute([trim($_POST['name']),trim($_POST['role']??''),trim($_POST['phone']??''),trim($_POST['email']??''),
                               trim($_POST['work_type']??''),($_POST['start_date']??'')?:null,trim($_POST['iban']??''),trim($_POST['notes']??''),
                               isset($_POST['active'])?1:0,$cvPath,$id]);
            }else{
                $pdo->prepare("UPDATE personnel SET name=?,role=?,phone=?,email=?,work_type=?,start_date=?,iban=?,notes=?,active=? WHERE id=?")
                    ->execute([trim($_POST['name']),trim($_POST['role']??''),trim($_POST['phone']??''),trim($_POST['email']??''),
                               trim($_POST['work_type']??''),($_POST['start_date']??'')?:null,trim($_POST['iban']??''),trim($_POST['notes']??''),
                               isset($_POST['active'])?1:0,$id]);
            }
            $ok='Bilgiler güncellendi.';
        }
        if(isset($_POST['make_login']) && trim($_POST['username']??'')!=='' && trim($_POST['password']??'')!==''){
            // GÜVENLİK (2026-07-04 SECURITY SPRINT-001): hesap/kimlik bilgisi işlemleri (kullanıcı
            // adı/şifre oluşturma) sadece admin veya 'personnel_accounts' yetkili "alt yönetici"
            // yapabilir — düz "personnel" modül yetkisi personel bilgilerini görüntüleme/düzenleme
            // içindir, başkasının giriş hesabını yönetme yetkisi vermez.
            if(!$canManageAccounts) throw new Exception('Bu işlem için yönetici yetkisi gerekir.');
            $un=trim($_POST['username']);
            $ex=$pdo->prepare("SELECT id FROM app_users WHERE username=?"); $ex->execute([$un]);
            if($ex->fetch()) throw new Exception('Bu kullanıcı adı zaten var.');
            // P0-AUTH-01 (2026-07-17): bkz. reset_pw bloğu üstündeki not — aynı personele ikinci
            // bir hesap daha açılması hangi hesabın "güncel" sayılacağını belirsizleştiriyordu.
            // Ece'nin re-review'ında bulduğu düzeltme: sadece user_id set mi diye değil, işaret
            // ettiği hesabın HÂLÂ bu personele ait olup olmadığı (personnel_id=?) da doğrulanır.
            $pr0=$pdo->prepare("SELECT user_id FROM personnel WHERE id=?"); $pr0->execute([$id]); $p0=$pr0->fetch();
            $existingUid=(int)($p0['user_id'] ?? 0);
            $hasLink = false;
            if($existingUid){
                $chk0=$pdo->prepare("SELECT id FROM app_users WHERE id=? AND personnel_id=?"); $chk0->execute([$existingUid,$id]);
                $hasLink = (bool)$chk0->fetch();
            }
            if(!$hasLink){
                $le=$pdo->prepare("SELECT id FROM app_users WHERE personnel_id=? LIMIT 1"); $le->execute([$id]);
                $hasLink = (bool)$le->fetch();
            }
            if($hasLink) throw new Exception('Bu personelin zaten bağlı bir giriş hesabı var. Yeni hesap oluşturmak yerine mevcut hesabın şifresini sıfırlayın.');
            $p=$pdo->prepare("SELECT name,phone,email FROM personnel WHERE id=?"); $p->execute([$id]); $pr=$p->fetch();
            $pdo->prepare("INSERT INTO app_users(username,full_name,phone,email,password_hash,role,personnel_id,active,created_at) VALUES(?,?,?,?,?,?,?,1,NOW())")
                ->execute([$un,$pr['name'],$pr['phone'],$pr['email'],password_hash($_POST['password'],PASSWORD_DEFAULT),'personel',$id]);
            $uid=(int)$pdo->lastInsertId();
            $pdo->prepare("UPDATE personnel SET user_id=?,login_enabled=1 WHERE id=?")->execute([$uid,$id]);
            $ok='Giriş hesabı oluşturuldu.';
            $waCred=cred_wa($pr['phone']??'',$un,$_POST['password']);
        }
        if(isset($_POST['reset_pw']) && trim($_POST['newpw']??'')!==''){
            // GÜVENLİK (2026-07-04 SECURITY SPRINT-001): şifre sıfırlama admin veya
            // 'personnel_accounts' yetkili "alt yönetici" tarafından yapılabilir (bkz. make_login
            // üzerindeki aynı gerekçe). Ayrıca $_POST['uid'] doğrudan güvenilmiyor — başka bir
            // hesabın id'si POST edilerek o hesabın şifresi değiştirilebiliyordu, hedef hesap her
            // zaman görüntülenen personele ($id) bağlı gerçek hesaptan çekiliyor.
            if(!$canManageAccounts) throw new Exception('Bu işlem için yönetici yetkisi gerekir.');
            // P0-AUTH-01 (2026-07-17): gerçek kullanıcı testinde "şifre sıfırlandı" mesajı
            // gösterilmesine rağmen personelin yeni şifreyle giriş yapamadığı bildirildi. Kök
            // neden: eski sorgu (LEFT JOIN + OR + ORDER BY'sız LIMIT 1) personele birden fazla
            // app_users kaydı bağlıysa HANGİ kaydın döneceğini garanti etmiyordu. Artık hedef
            // hesap TEK deterministik kaynaktan çözülüyor: personnel.user_id (personel için
            // "güncel hesap" — personnel_lib.php::personnel_reset_password() ile aynı mantık).
            $bu=$pdo->prepare("SELECT user_id FROM personnel WHERE id=?"); $bu->execute([$id]); $brow=$bu->fetch();
            $boundUid=(int)($brow['user_id'] ?? 0);
            if($boundUid){
                // Ece'nin re-review'ında bulduğu düzeltme: hesabın hâlâ BU personele ait olduğunu
                // (personnel_id=?) da doğrula — başka personele taşınmışsa yanlış hesap hedeflenmesin.
                $chk=$pdo->prepare("SELECT id FROM app_users WHERE id=? AND personnel_id=?"); $chk->execute([$boundUid,$id]);
                if(!$chk->fetch()) $boundUid=0;
            }
            if(!$boundUid){
                $fb=$pdo->prepare("SELECT id FROM app_users WHERE personnel_id=? ORDER BY id ASC LIMIT 1"); $fb->execute([$id]);
                $fr=$fb->fetch();
                $boundUid=(int)($fr['id'] ?? 0);
            }
            if(!$boundUid) throw new Exception('Bu personele bağlı bir giriş hesabı yok.');
            $pdo->prepare("UPDATE app_users SET password_hash=? WHERE id=?")->execute([password_hash($_POST['newpw'],PASSWORD_DEFAULT),$boundUid]);
            $ok='Şifre güncellendi.';
            try{ $cu=$pdo->prepare("SELECT username FROM app_users WHERE id=?"); $cu->execute([$boundUid]); $cr=$cu->fetch(); $pp=$pdo->prepare("SELECT phone FROM personnel WHERE id=?"); $pp->execute([$id]); $ppr=$pp->fetch(); if($cr) $waCred=cred_wa($ppr['phone']??'',$cr['username'],$_POST['newpw']); }catch(Throwable $e){}
        }
    }catch(Throwable $e){ $er=$e->getMessage(); }
}

topx('Personel');
if(!empty($_SESSION['pers_ok'])){ $ok=$_SESSION['pers_ok']; unset($_SESSION['pers_ok']); }
if(!empty($_SESSION['pers_err'])){ $er=$_SESSION['pers_err']; unset($_SESSION['pers_err']); }
try{
    $s=$pdo->prepare("SELECT * FROM personnel WHERE id=?"); $s->execute([$id]); $p=$s->fetch();
    if(!$p) throw new Exception('Personel bulunamadı.');
    // P0-AUTH-01 (2026-07-17, Ece re-review): görüntüleme sorgusu artık reset_pw/make_login'deki
    // AYNI deterministik çözümlemeyi izliyor (önce personnel.user_id + sahiplik doğrulaması, yoksa
    // personnel_id üzerinden en eski kayıt) — ekranda gösterilen hesap, gerçekte güncellenecek
    // hesapla HER ZAMAN eşleşir.
    $__uid=(int)($p['user_id']??0); $usr=false;
    if($__uid){
        $uu=$pdo->prepare("SELECT * FROM app_users WHERE id=? AND personnel_id=?"); $uu->execute([$__uid,$id]); $usr=$uu->fetch();
    }
    if(!$usr){
        $u=$pdo->prepare("SELECT * FROM app_users WHERE personnel_id=? ORDER BY id ASC LIMIT 1"); $u->execute([$id]); $usr=$u->fetch();
    }
    $acikIs=(int)$pdo->query("SELECT COUNT(*) c FROM jobs WHERE responsible_personnel_id=$id AND status NOT IN ('Tamamlandı','İptal','Teslim Edildi')")->fetch()['c'];
    $acikGorev=(int)$pdo->query("SELECT COUNT(*) c FROM tasks WHERE personnel_id=$id AND status NOT IN ('Tamamlandı','İptal')")->fetch()['c'];
?>
<?php if($ok): ?><?=ds_alert('success',$ok)?><?php endif; ?>
<?php if($er): ?><?=ds_alert('danger',$er)?><?php endif; ?>
<?php if($waCred): ?><a href="<?=h($waCred)?>" target="_blank" rel="noopener" class="df-btn df-btn--primary" style="display:flex;background:var(--df-success);margin-bottom:var(--df-space-2)"><?=ds_icon('send',16)?> Giriş bilgisini WhatsApp ile gönder</a><?php endif; ?>

<div class="df-panel">
  <h2 style="margin:0"><?=h($p['name'])?></h2>
  <div class="muted"><?=h($p['role']?:'Personel')?><?=$usr?' · '.ds_icon('user',13).' '.h($usr['username']):' · giriş yok'?></div>
  <div style="display:flex;gap:8px;margin-top:10px;flex-wrap:wrap">
    <span class="df-badge df-badge--info"><?=ds_icon('briefcase',13)?> Açık iş: <b><?=$acikIs?></b></span>
    <span class="df-badge df-badge--success"><?=ds_icon('check',13)?> Açık görev: <b><?=$acikGorev?></b></span>
  </div>
  <div style="display:flex;gap:8px;margin-top:10px;flex-wrap:wrap">
    <?=ds_button(ds_icon('calendar',15).' İş Ekle','task_new.php','secondary','','style="flex:1;justify-content:center"',true)?>
    <?=ds_button(ds_icon('briefcase',15).' Performans','kpi.php','secondary','','style="flex:1;justify-content:center"',true)?>
    <?php if($usr): ?><?=ds_button(ds_icon('chat',15).' Mesaj','messages.php?with='.(int)$usr['id'],'primary','','style="flex:1;justify-content:center"',true)?><?php endif; ?>
  </div>
</div>

<div class="df-panel"><b><?=ds_icon('edit',16)?> Bilgileri Düzenle</b>
<form method="post" style="margin-top:8px" enctype="multipart/form-data">
  <label>Ad Soyad</label><input name="name" value="<?=h($p['name'])?>" required>
  <label>Görev / Rol</label><input name="role" value="<?=h($p['role']??'')?>">
  <div style="display:flex;gap:10px"><div style="flex:1"><label>Telefon</label><input name="phone" value="<?=h($p['phone']??'')?>"></div><div style="flex:1"><label>E-posta</label><input name="email" value="<?=h($p['email']??'')?>"></div></div>
  <div style="display:flex;gap:10px"><div style="flex:1"><label>Çalışma Tipi</label><input name="work_type" value="<?=h($p['work_type']??'')?>"></div><div style="flex:1"><label>Başlangıç</label><input type="date" name="start_date" value="<?=h($p['start_date']??'')?>"></div></div>
  <label>IBAN</label><input name="iban" value="<?=h($p['iban']??'')?>">
  <label>Not</label><textarea name="notes" rows="2"><?=h($p['notes']??'')?></textarea>
  <?php if($hasCvCol): ?>
  <label>CV / Özgeçmiş <small class="muted">(opsiyonel — pdf/doc/docx/jpg/jpeg/png, en fazla 15 MB)</small></label>
  <?php if(!empty($p['cv_path'])): ?>
  <div style="margin:4px 0 8px"><a href="<?=h(base_url().$p['cv_path'])?>" target="_blank"><?=ds_icon('box',14)?> Mevcut CV'yi görüntüle</a></div>
  <?php endif; ?>
  <input type="file" name="cv" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
  <?php endif; ?>
  <label style="display:flex;align-items:center;gap:8px"><input type="checkbox" name="active" value="1" <?=$p['active']?'checked':''?> style="width:auto;margin:0"> Aktif</label>
  <button class="df-btn df-btn--primary df-btn--lg" name="save" value="1" style="width:100%;margin-top:8px"><?=ds_icon('check',16)?> Kaydet</button>
</form>
<?php if($hasCvCol && !empty($p['cv_path'])): ?>
<form method="post" style="margin-top:8px" onsubmit="return confirm('CV dosyasını kaldırmak istediğinize emin misiniz?')">
  <button class="df-btn df-btn--secondary" name="clear_cv" value="1" style="width:100%"><?=ds_icon('trash',16)?> CV'yi Kaldır</button>
</form>
<?php endif; ?>
</div>

<?php if($isAdmin): ?>
<div class="df-panel">
  <form method="post" onsubmit="return confirm('Bu personeli ve bağlı tüm verileri KALICI olarak silmek istediğinize emin misiniz?')" style="margin:0">
    <button class="df-btn df-btn--danger" name="delete_personnel" value="1" style="width:100%"><?=ds_icon('trash',16)?> Personeli Sil</button>
  </form>
</div>
<?php endif; ?>

<?php if($canManageAccounts): ?>
<div class="df-panel"><b><?=ds_icon('user',16)?> Giriş Hesabı</b>
<?php if($usr): ?>
  <p class="muted" style="margin:8px 0">Kullanıcı: <b><?=h($usr['username'])?></b> · durum: <?=$usr['active']?ds_badge('Aktif','green'):ds_badge('Pasif','red')?></p>
  <form method="post" style="display:flex;gap:8px"><input name="newpw" placeholder="Yeni şifre" style="flex:1;margin:0"><button class="df-btn df-btn--secondary" name="reset_pw" value="1">Şifre Sıfırla</button></form>
<?php else: ?>
  <p class="muted" style="margin:8px 0">Bu personelin uygulama girişi yok. Oluştur:</p>
  <form method="post"><div style="display:flex;gap:8px"><input name="username" placeholder="Kullanıcı adı" style="flex:1;margin:0"><input name="password" placeholder="Şifre" style="flex:1;margin:0"></div>
  <button class="df-btn df-btn--primary df-btn--lg" name="make_login" value="1" style="width:100%;margin-top:8px"><?=ds_icon('user',16)?> Giriş Hesabı Oluştur</button></form>
<?php endif; ?>
</div>
<?php endif; ?>

<div class="df-panel"><b><?=ds_icon('info',16)?> İşlem Kaydı</b>
  <p class="muted" style="margin:4px 0 8px;font-size:12px">Bu personelin yaptığı son işlemler (düzenleme/ekleme/satış vb.).</p>
  <?php if(function_exists('activity_user_html')) echo activity_user_html($pdo,$usr['id']??0,40); ?>
</div>
<?php
}catch(Throwable $e){ echo ds_alert('danger',$e->getMessage()); }
botx();
