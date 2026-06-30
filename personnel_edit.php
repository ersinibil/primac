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


if($_SERVER['REQUEST_METHOD']==='POST' && !isset($_POST['regen_telegram_code']) && !isset($_POST['clear_telegram_binding'])){
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
$staffUserId=0;
try{ $uu=$pdo->prepare("SELECT id FROM app_users WHERE personnel_id=? ORDER BY id LIMIT 1"); $uu->execute([$id]); $staffUserId=(int)($uu->fetch()['id']??0); }catch(Throwable $e){}
?>

<div class="panel-head">
<h1><?=h($p['name'])?></h1>
<a class="btn secondary" href="personnel.php">Personel Listesi</a>
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

<section class="panel">
<h2>🧾 İşlem Kaydı</h2>
<p class="muted" style="margin:0 0 8px">Bu personelin yaptığı son işlemler (ürün/cari düzenleme, satış, stok hareketi vb.).</p>
<?php echo function_exists('activity_user_html') ? activity_user_html($pdo,$staffUserId,50) : '<p class="muted">İşlem kaydı modülü yüklü değil.</p>'; ?>
</section>

<?php require_once __DIR__.'/layout_bottom.php'; ?>
