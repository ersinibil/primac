<?php
require_once __DIR__.'/boot.php';
require_login();

$pdo=db();
$id=(int)($_GET['id'] ?? 0);
$error='';
$ok='';

if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['regen_telegram_code'])){
    try{
        $code=(string)random_int(100000,999999);
        $pdo->prepare("UPDATE personnel SET telegram_activation_code=? WHERE id=?")->execute([$code,$id]);
        $ok='Yeni Telegram aktivasyon kodu üretildi.';
    }catch(Throwable $e){ $error=$e->getMessage(); }
}

if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['clear_telegram_binding'])){
    try{
        $pdo->prepare("DELETE FROM personnel_devices WHERE personnel_id=?")->execute([$id]);
        $pdo->prepare("UPDATE personnel SET telegram_bound=0, telegram_chat_id=NULL, telegram_user_id=NULL, telegram_username=NULL, telegram_last_seen=NULL WHERE id=?")->execute([$id]);
        $ok='Telegram bağlantısı kaldırıldı.';
    }catch(Throwable $e){ $error=$e->getMessage(); }
}


if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['save_perms'])){
    try{
        if(!user_can('users')) throw new Exception('Yetki atama izniniz yok.');
        $uid=(int)$_POST['perm_user_id'];
        $perms=$_POST['permissions'] ?? [];
        if(!is_array($perms)) $perms=[];
        $pdo->prepare("UPDATE app_users SET permissions=? WHERE id=?")
            ->execute([json_encode(array_values($perms),JSON_UNESCAPED_UNICODE),$uid]);
        $ok='Personel yetkileri güncellendi.';
    }catch(Throwable $e){ $error=$e->getMessage(); }
}

if($_SERVER['REQUEST_METHOD']==='POST' && !isset($_POST['regen_telegram_code']) && !isset($_POST['clear_telegram_binding']) && !isset($_POST['save_perms'])){
    try{
        $stmt=$pdo->prepare("UPDATE personnel SET
            name=?, role=?, phone=?, email=?, hourly_rate=?, daily_wage=?, active=?, address=?, start_date=?, work_type=?, iban=?, notes=?
            WHERE id=?
        ");
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
            $id
        ]);
        $ok='Personel profili güncellendi.';
    }catch(Throwable $e){
        $error=$e->getMessage();
    }
}

$stmt=$pdo->prepare("SELECT * FROM personnel WHERE id=?");
$stmt->execute([$id]);
$p=$stmt->fetch();

require_once __DIR__.'/layout_top.php';

if(!$p){
    echo "<h1>Personel bulunamadı</h1>";
    require __DIR__.'/layout_bottom.php';
    exit;
}

$tasks=[];
try{
    $ts=$pdo->prepare("SELECT t.*, j.job_no, j.title job_title FROM tasks t LEFT JOIN jobs j ON j.id=t.job_id WHERE t.personnel_id=? ORDER BY t.id DESC LIMIT 20");
    $ts->execute([$id]);
    $tasks=$ts->fetchAll();
}catch(Throwable $e){}
$staffUserId=0; $staffPerms=[]; $staffRole='';
try{ $uu=$pdo->prepare("SELECT id,permissions,role FROM app_users WHERE personnel_id=? ORDER BY id LIMIT 1"); $uu->execute([$id]);
    if($su=$uu->fetch()){ $staffUserId=(int)$su['id']; $staffRole=$su['role']??''; $dp=json_decode($su['permissions']??'[]',true); $staffPerms=is_array($dp)?$dp:[]; }
}catch(Throwable $e){}
?>

<div class="panel-head">
<h1><?=h($p['name'])?></h1>
<div class="actions">
<a class="btn secondary" href="personnel.php">Personel Listesi</a>
<?=delete_button('personnel',$id)?>
</div>
</div>

<?php if($error): ?><div class="alert"><?=h($error)?></div><?php endif; ?>
<?php if($ok): ?><div class="ok"><?=h($ok)?></div><?php endif; ?>

<div class="cards">
<div class="card"><small>Rol</small><strong><?=h($p['role'] ?: '-')?></strong></div>
<div class="card"><small>Çalışma</small><strong><?=h($p['work_type'] ?: '-')?></strong></div>
<div class="card"><small>Durum</small><strong><?=$p['active']?badge('Aktif','green'):badge('Pasif','red')?></strong></div>
<div class="card"><small>Açık Görev</small><strong><?=safe_count("SELECT COUNT(*) c FROM tasks WHERE personnel_id=".(int)$id." AND status!='Tamamlandı'")?></strong></div>
</div>


<section class="panel">
<h2>Profil Bilgileri</h2>
<form method="post" class="form-grid">

<label>Ad Soyad
<input name="name" required value="<?=h($p['name'])?>">
</label>

<label>Rol / Görev
<input name="role" value="<?=h($p['role'])?>">
</label>

<label>Telefon
<input name="phone" value="<?=h($p['phone'])?>">
</label>

<label>E-posta
<input name="email" value="<?=h($p['email'] ?? '')?>">
</label>

<label>Çalışma Tipi
<select name="work_type">
<?php foreach(['Tam Zamanlı','Yarı Zamanlı','Günlük','Dış Paydaş','Stajyer'] as $w): ?>
<option <?=$p['work_type']===$w?'selected':''?>><?=$w?></option>
<?php endforeach; ?>
</select>
</label>

<label>İşe Giriş Tarihi
<input type="date" name="start_date" value="<?=h($p['start_date'] ?? '')?>">
</label>

<label>Saatlik Ücret
<input type="number" step="0.01" name="hourly_rate" value="<?=h($p['hourly_rate'])?>">
</label>

<label>Günlük / Maaş
<input type="number" step="0.01" name="daily_wage" value="<?=h($p['daily_wage'])?>">
</label>

<label class="full">IBAN
<input name="iban" value="<?=h($p['iban'] ?? '')?>">
</label>

<label class="full">Adres
<textarea name="address" rows="2"><?=h($p['address'] ?? '')?></textarea>
</label>

<label class="full">Notlar
<textarea name="notes" rows="4"><?=h($p['notes'] ?? '')?></textarea>
</label>

<label class="full">
<input type="checkbox" name="active" <?=$p['active']?'checked':''?> style="width:auto"> Aktif personel
</label>

<button class="btn">Profili Kaydet</button>

</form>
</section>

<section class="panel">
<h2>Son Görevler</h2>
<table>
<thead><tr><th>Görev</th><th>İş</th><th>Termin</th><th>Durum</th></tr></thead>
<tbody>
<?php foreach($tasks as $t): ?>
<tr>
<td><?=h($t['title'])?></td>
<td><?=h($t['job_no'] ? $t['job_no'].' - '.$t['job_title'] : '-')?></td>
<td><?=h($t['due_date'])?></td>
<td><?=badge($t['status'], status_tone($t['status']))?></td>
</tr>
<?php endforeach; ?>
<?php if(!$tasks): ?><tr><td colspan="4" class="muted">Henüz görev yok.</td></tr><?php endif; ?>
</tbody>
</table>
</section>

<?php if(user_can('users')): ?>
<section class="panel">
<h2>🔐 Yetkiler (Modül Erişimi)</h2>
<?php if($staffRole==='admin' || $staffRole==='yonetici'): ?>
  <p class="muted" style="margin:0">Bu personelin giriş rolü <b><?=h($staffRole)?></b> — tüm modüllere yetkili. Kısıtlamak için <a href="users.php">Kullanıcılar</a> ekranından rolünü "Personel" yapın.</p>
<?php elseif($staffUserId): ?>
  <p class="muted" style="margin:0 0 10px">İşaretli modülleri görür ve kullanabilir. İşaretsiz modüller bu personelin menüsünde görünmez ve açılamaz.</p>
  <form method="post">
  <input type="hidden" name="save_perms" value="1">
  <input type="hidden" name="perm_user_id" value="<?=$staffUserId?>">
  <div style="display:grid;grid-template-columns:repeat(2,minmax(170px,1fr));gap:10px">
  <?php foreach(module_list() as $key=>$label): if($key==='users') continue; ?>
    <label style="background:#f8fafc;border:1px solid #e5e7eb;border-radius:14px;padding:10px;display:flex;align-items:center;gap:8px">
      <input type="checkbox" name="permissions[]" value="<?=$key?>" <?=in_array($key,$staffPerms,true)?'checked':''?> style="width:auto"> <?=h($label)?>
    </label>
  <?php endforeach; ?>
  </div>
  <button class="btn" style="margin-top:12px">🔐 Yetkileri Kaydet</button>
  </form>
<?php else: ?>
  <p class="muted" style="margin:0">Bu personelin giriş hesabı yok. Yetki vermek için önce <a href="users.php?personnel_id=<?=$id?>&full_name=<?=urlencode($p['name'])?>&phone=<?=urlencode($p['phone']??'')?>">Kullanıcı &amp; Yetki</a> ekranından hesap oluşturup bu personele bağlayın (ad/telefon otomatik dolacak).</p>
<?php endif; ?>
</section>
<?php endif; ?>

<section class="panel">
<h2>🧾 İşlem Kaydı</h2>
<p class="muted" style="margin:0 0 8px">Bu personelin yaptığı son işlemler (ürün/cari düzenleme, satış, stok hareketi vb.).</p>
<?php echo function_exists('activity_user_html') ? activity_user_html($pdo,$staffUserId,50) : '<p class="muted">İşlem kaydı modülü yüklü değil.</p>'; ?>
</section>

<?php require_once __DIR__.'/layout_bottom.php'; ?>
