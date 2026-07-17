<?php
require_once 'common.php';
require_once dirname(__DIR__).'/personnel_lib.php';
block_personel('personnel');
$pdo=db(); $ok=''; $er='';
$hasCvCol = personnel_has_cv_column($pdo);

if($_SERVER['REQUEST_METHOD']==='POST'){
    try{
        $name=trim($_POST['name']??'');
        if($name==='') throw new Exception('Ad Soyad girin.');
        $cvPath = $hasCvCol ? personnel_handle_cv_upload() : null;
        if($hasCvCol){
            $pdo->prepare("INSERT INTO personnel(name,role,phone,email,work_type,start_date,iban,notes,active,created_at,cv_path) VALUES(?,?,?,?,?,?,?,?,1,NOW(),?)")
                ->execute([$name,trim($_POST['role']??''),trim($_POST['phone']??''),trim($_POST['email']??''),trim($_POST['work_type']??''),
                           ($_POST['start_date']??'')?:null,trim($_POST['iban']??''),trim($_POST['notes']??''),$cvPath]);
        }else{
            $pdo->prepare("INSERT INTO personnel(name,role,phone,email,work_type,start_date,iban,notes,active,created_at) VALUES(?,?,?,?,?,?,?,?,1,NOW())")
                ->execute([$name,trim($_POST['role']??''),trim($_POST['phone']??''),trim($_POST['email']??''),trim($_POST['work_type']??''),
                           ($_POST['start_date']??'')?:null,trim($_POST['iban']??''),trim($_POST['notes']??'')]);
        }
        $pid=(int)$pdo->lastInsertId();
        // Opsiyonel giriş hesabı
        if(!empty($_POST['make_login']) && trim($_POST['username']??'')!=='' && trim($_POST['password']??'')!==''){
            $un=trim($_POST['username']); $pw=$_POST['password'];
            $ex=$pdo->prepare("SELECT id FROM app_users WHERE username=?"); $ex->execute([$un]);
            if($ex->fetch()) throw new Exception('Personel eklendi ama kullanıcı adı "'.$un.'" zaten var — giriş açılamadı.');
            $pdo->prepare("INSERT INTO app_users(username,full_name,phone,email,password_hash,role,personnel_id,active,created_at) VALUES(?,?,?,?,?,?,?,1,NOW())")
                ->execute([$un,$name,trim($_POST['phone']??''),trim($_POST['email']??''),password_hash($pw,PASSWORD_DEFAULT),'personel',$pid]);
            $uid=(int)$pdo->lastInsertId();
            $pdo->prepare("UPDATE personnel SET user_id=?, login_enabled=1 WHERE id=?")->execute([$uid,$pid]);
        }
        try{ if(function_exists('activity_log')) activity_log('Personel','Yeni',$name,'','personnel',$pid,base_url().'mobile/personnel_view.php?id='.$pid,'👷'); }catch(Throwable $e){}
        header('Location: personnel_view.php?id='.$pid); exit;
    }catch(Throwable $e){ $er=$e->getMessage(); }
}
topx('Yeni Personel');
?>
<?php if($er): ?><?=ds_alert('danger',$er)?><?php endif; ?>
<div class="df-panel">
<form method="post" enctype="multipart/form-data">
  <label>Ad Soyad *</label><input name="name" required>
  <label>Görev / Rol</label><input name="role" placeholder="örn. Üretim, Montaj, Satış">
  <div style="display:flex;gap:10px"><div style="flex:1"><label>Telefon</label><input name="phone"></div><div style="flex:1"><label>E-posta</label><input name="email"></div></div>
  <div style="display:flex;gap:10px"><div style="flex:1"><label>Çalışma Tipi</label><input name="work_type" placeholder="Tam/Yarı/Dış"></div><div style="flex:1"><label>Başlangıç</label><input type="date" name="start_date"></div></div>
  <label>IBAN</label><input name="iban">
  <label>Not</label><textarea name="notes" rows="2"></textarea>
  <?php if($hasCvCol): ?>
  <label>CV / Özgeçmiş <small class="muted">(opsiyonel — pdf/doc/docx/jpg/jpeg/png, en fazla 15 MB)</small></label>
  <input type="file" name="cv" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
  <?php endif; ?>
  <div class="df-panel" style="background:var(--df-accent-soft)">
    <label style="display:flex;align-items:center;gap:8px;margin:0"><input type="checkbox" name="make_login" value="1" style="width:auto;margin:0" onchange="document.getElementById('lg').style.display=this.checked?'block':'none'"><?=ds_icon('user',16)?> Uygulamaya giriş hesabı oluştur</label>
    <div id="lg" style="display:none;margin-top:8px">
      <div style="display:flex;gap:10px"><div style="flex:1"><label>Kullanıcı Adı</label><input name="username"></div><div style="flex:1"><label>Şifre</label><input name="password"></div></div>
      <small class="muted">Personel bu bilgilerle giriş yapıp mesaj/iş/görev görür (yetkisi kısıtlı).</small>
    </div>
  </div>
  <button class="df-btn df-btn--primary df-btn--lg" style="width:100%;margin-top:8px"><?=ds_icon('user',16)?> Personeli Kaydet</button>
</form>
</div>
<?php botx(); ?>
