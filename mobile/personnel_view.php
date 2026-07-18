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
            // RELEASE 0.9 (2026-07-17, Personel/Kullanıcı/Yetki sadeleştirmesi): artık web'deki gibi
            // paylaşılan personnel_lib.php::personnel_create_login() çağrılıyor (mükerrer-hesap
            // koruması + rol/yetki dahil) — önceden burada ayrı/duplike bir raw INSERT vardı.
            $presetDef=personnel_resolve_role_preset($_POST['role_preset'] ?? 'personel');
            $res=personnel_create_login($pdo, $id, $_POST['username'], $_POST['password'], $presetDef['role'], $_POST['permissions'] ?? []);
            $ok='Giriş hesabı oluşturuldu.';
            $waCred=cred_wa($res['phone']??'',trim($_POST['username']),$_POST['password']);
        }
        if(isset($_POST['save_account_role'])){
            if(!$canManageAccounts) throw new Exception('Bu işlem için yönetici yetkisi gerekir.');
            $uid=(int)$_POST['perm_user_id'];
            // IDOR koruması: hedef hesap gerçekten BU personele bağlı mı.
            $ownChk=$pdo->prepare("SELECT role FROM app_users WHERE id=? AND personnel_id=?"); $ownChk->execute([$uid,$id]);
            $ownRow=$ownChk->fetch();
            if(!$ownRow) throw new Exception('Geçersiz hesap.');
            $presetDef=personnel_resolve_role_preset($_POST['role_preset'] ?? 'personel', $ownRow['role']);
            personnel_update_account_role($pdo, $uid, $presetDef['role'], $_POST['permissions'] ?? []);
            $ok='Rol ve yetkiler güncellendi.';
        }
        if(isset($_POST['reset_pw']) && trim($_POST['newpw']??'')!==''){
            // GÜVENLİK (2026-07-04 SECURITY SPRINT-001): şifre sıfırlama admin veya
            // 'personnel_accounts' yetkili "alt yönetici" tarafından yapılabilir (bkz. make_login
            // üzerindeki aynı gerekçe). Ayrıca $_POST['uid'] doğrudan güvenilmiyor — başka bir
            // hesabın id'si POST edilerek o hesabın şifresi değiştirilebiliyordu, hedef hesap her
            // zaman görüntülenen personele ($id) bağlı gerçek hesaptan çekiliyor.
            if(!$canManageAccounts) throw new Exception('Bu işlem için yönetici yetkisi gerekir.');
            // RELEASE 0.9 (2026-07-17): artık web'deki gibi paylaşılan
            // personnel_lib.php::personnel_reset_password() çağrılıyor — P0-AUTH-01'in deterministik
            // hesap çözümlemesi (personnel.user_id + sahiplik doğrulaması) TEK yerden yönetiliyor,
            // önceden burada ayrı/duplike bir kopyası vardı.
            $res=personnel_reset_password($pdo, $id, $_POST['newpw']);
            $ok='Şifre güncellendi.';
            $waCred=cred_wa($res['phone']??'',$res['username']??'',$_POST['newpw']);
        }
    }catch(Throwable $e){ $er=$e->getMessage(); }
}

// P0 MOBİL SHELL KAPANIŞI (2026-07-18): Personel Detayı → Personel listesine deterministik döner
// (bkz. common.php::topx() notu).
topx('Personel', 'personnel.php');
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

    // PERSONEL YÖNETİMİ — TEK MERKEZ/TEK KİŞİ/TEK AKIŞ (2026-07-18, Product Owner kararı): web
    // personnel_edit.php ile aynı bilgi mimarisi — sabit kimlik başlığı + Genel/OTS Hesabı &
    // Yetkiler/Görevler/Performans sekmeleri. Whitelist — GET'ten keyfi değer geçilemez.
    $tabLabels=['genel'=>'👤 Genel'];
    if($canManageAccounts) $tabLabels['giris']='🔑 OTS Hesabı & Yetkiler';
    $tabLabels['gorevler']='✅ Görevler';
    $tabLabels['performans']='📈 Performans';
    $tab = isset($_GET['tab']) ? (string)$_GET['tab'] : 'genel';
    if(!array_key_exists($tab,$tabLabels)) $tab='genel';
?>
<?php if($ok): ?><?=ds_alert('success',$ok)?><?php endif; ?>
<?php if($er): ?><?=ds_alert('danger',$er)?><?php endif; ?>
<?php if($waCred): ?><a href="<?=h($waCred)?>" target="_blank" rel="noopener" class="df-btn df-btn--primary" style="display:flex;background:var(--df-success);margin-bottom:var(--df-space-2)"><?=ds_icon('send',16)?> Giriş bilgisini WhatsApp ile gönder</a><?php endif; ?>

<div class="df-personnel-identity">
  <div class="df-personnel-identity-avatar"><?=h(personnel_initials($p['name']))?></div>
  <div class="df-personnel-identity-text">
    <h2><?=h($p['name'])?></h2>
    <div class="muted"><?=h($p['role']?:'Personel')?></div>
    <div style="display:flex;gap:6px;margin-top:6px;flex-wrap:wrap">
      <?=$p['active']?ds_badge('● Aktif','green'):ds_badge('● Pasif','red')?>
      <?=$usr?ds_badge('OTS: ● Hesap Aktif','green'):ds_badge('OTS: Hesap Yok','gray')?>
    </div>
  </div>
</div>
<div style="display:flex;gap:8px;margin:10px 0;flex-wrap:wrap">
  <span class="df-badge df-badge--info"><?=ds_icon('briefcase',13)?> Açık iş: <b><?=$acikIs?></b></span>
  <span class="df-badge df-badge--success"><?=ds_icon('check',13)?> Açık görev: <b><?=$acikGorev?></b></span>
  <?php if($usr): ?><?=ds_button(ds_icon('chat',15).' Mesaj','messages.php?with='.(int)$usr['id'],'primary','df-btn--sm','',true)?><?php endif; ?>
</div>

<?php
$__tabItems=[];
foreach($tabLabels as $tkey=>$tlabel){ $__tabItems[]=['label'=>$tlabel,'url'=>'personnel_view.php?id='.$id.'&tab='.$tkey,'active'=>$tab===$tkey]; }
ds_tabs($__tabItems);
?>

<?php if($tab==='genel'): ?>
<div class="df-panel" style="margin-top:10px"><b><?=ds_icon('edit',16)?> Bilgileri Düzenle</b>
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

<div class="df-panel"><b><?=ds_icon('info',16)?> İşlem Kaydı</b>
  <p class="muted" style="margin:4px 0 8px;font-size:12px">Bu personelin yaptığı son işlemler (düzenleme/ekleme/satış vb.).</p>
  <?php if(function_exists('activity_user_html')) echo activity_user_html($pdo,$usr['id']??0,40); ?>
</div>

<?php if($isAdmin): ?>
<div class="df-panel">
  <form method="post" onsubmit="return confirm('Bu personeli ve bağlı tüm verileri KALICI olarak silmek istediğinize emin misiniz?')" style="margin:0">
    <button class="df-btn df-btn--danger" name="delete_personnel" value="1" style="width:100%"><?=ds_icon('trash',16)?> Personeli Sil</button>
  </form>
</div>
<?php endif; ?>
<?php endif; ?>

<?php if($canManageAccounts && $tab==='giris'): ?>
<div class="df-panel" style="margin-top:10px"><b><?=ds_icon('user',16)?> OTS Hesabı & Yetkiler</b>
<?php if($usr && (int)$usr['id']===1): ?>
  <p class="muted" style="margin:8px 0">Bu hesap sistemin ana yöneticisi — korumalıdır, rolü buradan değiştirilemez.</p>
<?php elseif($usr): ?>
  <p class="muted" style="margin:8px 0">Kullanıcı: <b><?=h($usr['username'])?></b> · durum: <?=$usr['active']?ds_badge('Aktif','green'):ds_badge('Pasif','red')?></p>
  <form method="post" style="display:flex;gap:8px;margin-bottom:12px"><input name="newpw" placeholder="Yeni şifre" style="flex:1;margin:0"><button class="df-btn df-btn--secondary" name="reset_pw" value="1">Şifre Sıfırla</button></form>
  <?php
    $__usrPerms=json_decode($usr['permissions']??'[]',true); if(!is_array($__usrPerms)) $__usrPerms=[];
    $__usrPreset=personnel_detect_preset($usr['role']??'personel', $__usrPerms);
  ?>
  <form method="post">
    <input type="hidden" name="save_account_role" value="1">
    <input type="hidden" name="perm_user_id" value="<?=(int)$usr['id']?>">
    <?=personnel_role_permission_fields_html($__usrPreset, $__usrPerms)?>
    <button class="df-btn df-btn--primary df-btn--lg" style="width:100%;margin-top:8px"><?=ds_icon('check',16)?> Yetkileri Kaydet</button>
  </form>
<?php else: ?>
  <p class="muted" style="margin:8px 0">Bu personelin uygulama girişi yok. Oluştur:</p>
  <form method="post"><div style="display:flex;gap:8px"><input name="username" placeholder="Kullanıcı adı" style="flex:1;margin:0"><input name="password" placeholder="Şifre" style="flex:1;margin:0"></div>
  <?=personnel_role_permission_fields_html('personel', [])?>
  <button class="df-btn df-btn--primary df-btn--lg" name="make_login" value="1" style="width:100%;margin-top:8px"><?=ds_icon('user',16)?> Giriş Hesabı Oluştur</button></form>
<?php endif; ?>
</div>
<?php endif; ?>

<?php if($tab==='gorevler'): ?>
<div class="df-panel" style="margin-top:10px"><b><?=ds_icon('check',16)?> Görevler</b>
<p class="muted" style="margin:4px 0 8px;font-size:12px">Bu personele atanmış son 20 görev (durum değişikliği için Görevler ekranı kullanılır).</p>
<?php
$__ts=$pdo->prepare("SELECT t.*, j.job_no, j.title job_title FROM tasks t LEFT JOIN jobs j ON j.id=t.job_id WHERE t.personnel_id=? AND t.deleted_at IS NULL ORDER BY t.id DESC LIMIT 20");
$__ts->execute([$id]);
$__tasks=$__ts->fetchAll();
if(!$__tasks) ds_empty_state('Henüz görev yok.');
foreach($__tasks as $__t){
    $__desc=($__t['job_no']?h($__t['job_no'].' - '.$__t['job_title']):'').($__t['due_date']?' · '.h($__t['due_date']):'');
    ds_list_item(h($__t['title']), 'task_view.php?id='.(int)$__t['id'], $__desc, ds_badge($__t['status']));
}
?>
</div>
<?php endif; ?>

<?php if($tab==='performans'): ?>
<div class="df-panel" style="margin-top:10px"><b><?=ds_icon('briefcase',16)?> Performans (KPI)</b>
<p class="muted" style="margin:8px 0">Tüm personel sıralaması ve puan hesaplama yöntemi için KPI ekranına gidin.</p>
<?=ds_button('KPI Sayfasına Git','kpi.php','primary','','style="width:100%;justify-content:center"',true)?>
</div>
<?php endif; ?>

<style>
.df-personnel-identity{position:sticky;top:0;z-index:5;display:flex;align-items:center;gap:12px;background:var(--df-surface,#0f1d33);border-radius:16px;padding:14px;margin-top:10px}
.df-personnel-identity-avatar{width:52px;height:52px;border-radius:14px;background:var(--c-accent,#2563eb);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:900;font-size:18px;flex:0 0 auto}
.df-personnel-identity-text h2{margin:0;font-size:18px}
</style>
<?php
}catch(Throwable $e){ echo ds_alert('danger',$e->getMessage()); }
botx();
