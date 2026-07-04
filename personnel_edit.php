<?php
require_once __DIR__.'/boot.php';
require_login();
require_once __DIR__.'/personnel_lib.php';

$pdo=db();
$id=(int)($_GET['id'] ?? 0);
$error='';
$ok='';
$hasCvCol = personnel_has_cv_column($pdo);

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

if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['clear_cv'])){
    try{
        if($hasCvCol){
            $cur=$pdo->prepare("SELECT cv_path FROM personnel WHERE id=?"); $cur->execute([$id]); $row=$cur->fetch();
            if($row && !empty($row['cv_path'])){
                $full=__DIR__.'/'.$row['cv_path'];
                if(is_file($full)) @unlink($full);
            }
            $pdo->prepare("UPDATE personnel SET cv_path=NULL WHERE id=?")->execute([$id]);
        }
        $ok='CV kaldırıldı.';
    }catch(Throwable $e){ $error=$e->getMessage(); }
}

if($_SERVER['REQUEST_METHOD']==='POST' && !isset($_POST['regen_telegram_code']) && !isset($_POST['clear_telegram_binding']) && !isset($_POST['save_perms']) && !isset($_POST['clear_cv'])){
    try{
        $cvPath = $hasCvCol ? personnel_handle_cv_upload() : null;

        if($hasCvCol){
            if($cvPath !== null){
                $stmt=$pdo->prepare("UPDATE personnel SET
                    name=?, role=?, phone=?, email=?, hourly_rate=?, daily_wage=?, active=?, address=?, start_date=?, work_type=?, iban=?, notes=?, cv_path=?
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
                    $cvPath,
                    $id
                ]);
            }else{
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
            }
        }else{
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
        }
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
    $ts=$pdo->prepare("SELECT t.*, j.job_no, j.title job_title FROM tasks t LEFT JOIN jobs j ON j.id=t.job_id WHERE t.personnel_id=? AND t.deleted_at IS NULL ORDER BY t.id DESC LIMIT 20");
    $ts->execute([$id]);
    $tasks=$ts->fetchAll();
}catch(Throwable $e){}
$staffUserId=0; $staffPerms=[]; $staffRole='';
try{ $uu=$pdo->prepare("SELECT id,permissions,role FROM app_users WHERE personnel_id=? ORDER BY id LIMIT 1"); $uu->execute([$id]);
    if($su=$uu->fetch()){ $staffUserId=(int)$su['id']; $staffRole=$su['role']??''; $dp=json_decode($su['permissions']??'[]',true); $staffPerms=is_array($dp)?$dp:[]; }
}catch(Throwable $e){}

// --- Sekmeli görünüm için ek, salt-okunur veriler (mevcut sorgu desenleriyle birebir aynı,
// yeni bir iş mantığı icat edilmedi — sadece bu personele filtrelenmiş görünümler). ---

// Takvim: bu personele termin tarihli iş (jobs.responsible_personnel_id) + görev (tasks.due_date).
$calendarItems=[];
try{
    $js=$pdo->prepare("SELECT job_no, title, due_date, status FROM jobs WHERE responsible_personnel_id=? AND due_date IS NOT NULL ORDER BY due_date ASC LIMIT 20");
    $js->execute([$id]);
    foreach($js->fetchAll() as $j){
        $calendarItems[]=['tip'=>'İş','baslik'=>($j['job_no'] ? $j['job_no'].' - ' : '').$j['title'],'tarih'=>$j['due_date'],'durum'=>$j['status']];
    }
}catch(Throwable $e){}
try{
    $tsd=$pdo->prepare("SELECT title, due_date, status FROM tasks WHERE personnel_id=? AND due_date IS NOT NULL AND deleted_at IS NULL ORDER BY due_date ASC LIMIT 20");
    $tsd->execute([$id]);
    foreach($tsd->fetchAll() as $t){
        $calendarItems[]=['tip'=>'Görev','baslik'=>$t['title'],'tarih'=>$t['due_date'],'durum'=>$t['status']];
    }
}catch(Throwable $e){}
usort($calendarItems, function($a,$b){ return strcmp((string)$a['tarih'],(string)$b['tarih']); });

// Notlar: personal_notes KULLANICIYA bağlı (user_id), personele değil — bağlı hesabı olmayan
// personel için gösterecek bir şey yok (bkz. görev talimatı, tabloya dokunulmadı).
$personalNotes=[];
if($staffUserId){
    try{
        $pn=$pdo->prepare("SELECT * FROM personal_notes WHERE user_id=? ORDER BY created_at DESC LIMIT 20");
        $pn->execute([$staffUserId]);
        $personalNotes=$pn->fetchAll();
    }catch(Throwable $e){}
}

// Maaş/Avans/Prim: finance_movements.personnel_id (accounting.php'nin "Personel Ödemesi" akışıyla
// dolduruluyor, bkz. migration 020/035) — sadece bu personele ait kayıtlar, muhasebe.php'ye
// dokunulmadan salt-okunur filtrelenmiş liste.
$financeRows=[];
try{
    $fm=$pdo->prepare("SELECT fm.*, ac.name AS category_name FROM finance_movements fm LEFT JOIN accounting_categories ac ON ac.id=fm.category_id WHERE fm.personnel_id=? ORDER BY fm.movement_date DESC, fm.id DESC LIMIT 30");
    $fm->execute([$id]);
    $financeRows=$fm->fetchAll();
}catch(Throwable $e){}

// Sekme listesi + geçerli sekme (whitelist — GET parametresinden keyfi değer geçilemez).
$tabLabels=[
    'genel'=>'👤 Genel Bilgiler',
    'gorevler'=>'✅ Görevler',
    'takvim'=>'📅 Takvim',
    'mesajlar'=>'💬 Mesajlar',
    'notlar'=>'📝 Notlar',
];
if($hasCvCol) $tabLabels['dosyalar']='📎 Dosyalar';
$tabLabels['performans']='📈 Performans';
$tabLabels['maas']='💰 Maaş/Avans/Prim';
$tabLabels['hareket']='🧾 Hareket Geçmişi';
if(user_can('users')) $tabLabels['giris']='🔑 Giriş Hesabı';

$tab = isset($_GET['tab']) ? (string)$_GET['tab'] : 'genel';
if(!array_key_exists($tab,$tabLabels)) $tab='genel';
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
<div class="card"><small>Açık Görev</small><strong><?=safe_count("SELECT COUNT(*) c FROM tasks WHERE personnel_id=".(int)$id." AND status!='Tamamlandı' AND deleted_at IS NULL")?></strong></div>
</div>

<div class="ptabs">
<?php foreach($tabLabels as $tkey=>$tlabel): ?>
<a class="ptab<?=$tab===$tkey?' active':''?>" href="personnel_edit.php?id=<?=$id?>&tab=<?=h($tkey)?>"><?=$tlabel?></a>
<?php endforeach; ?>
</div>

<?php if($tab==='genel'): ?>
<section class="panel">
<h2>Profil Bilgileri</h2>
<form method="post" class="form-grid" enctype="multipart/form-data">

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

<?php if($hasCvCol): ?>
<label class="full">CV / Özgeçmiş <small style="font-weight:400;color:#667085">(opsiyonel — pdf/doc/docx/jpg/jpeg/png, en fazla 15 MB. Yeni dosya seçilirse eskisinin yerine geçer, boş bırakılırsa mevcut korunur. Mevcut CV'yi görüntülemek/kaldırmak için "📎 Dosyalar" sekmesine bakın)</small>
<input type="file" name="cv" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
</label>
<?php endif; ?>

<label class="full">
<input type="checkbox" name="active" <?=$p['active']?'checked':''?> style="width:auto"> Aktif personel
</label>

<button class="btn">Profili Kaydet</button>

</form>
</section>
<?php endif; ?>

<?php if($tab==='gorevler'): ?>
<section class="panel">
<h2>Görevler</h2>
<p class="muted" style="margin:0 0 8px">Bu personele atanmış son 20 görev (salt-okunur — durum değişikliği için <a href="tasks.php">Görevler</a> ekranı kullanılır).</p>
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
<?php endif; ?>

<?php if($tab==='takvim'): ?>
<section class="panel">
<h2>Takvim</h2>
<p class="muted" style="margin:0 0 8px">Bu personelin termin tarihli iş ve görevleri (tam takvim için <a href="takvim.php">Takvim</a> ekranı).</p>
<table>
<thead><tr><th>Tür</th><th>Başlık</th><th>Tarih</th><th>Durum</th></tr></thead>
<tbody>
<?php foreach($calendarItems as $ci): ?>
<tr>
<td><?=h($ci['tip'])?></td>
<td><?=h($ci['baslik'])?></td>
<td><?=h($ci['tarih'])?></td>
<td><?=badge($ci['durum'], status_tone($ci['durum']))?></td>
</tr>
<?php endforeach; ?>
<?php if(!$calendarItems): ?><tr><td colspan="4" class="muted">Termin tarihli iş/görev yok.</td></tr><?php endif; ?>
</tbody>
</table>
</section>
<?php endif; ?>

<?php if($tab==='mesajlar'): ?>
<section class="panel">
<h2>Mesajlar</h2>
<?php if($staffUserId): ?>
<p class="muted" style="margin:0 0 10px">Bu personelin bağlı kullanıcı hesabıyla birebir mesajlaşabilirsiniz.</p>
<a class="btn" href="messages.php?u=<?=$staffUserId?>">💬 Mesaj Gönder</a>
<?php else: ?>
<p class="muted" style="margin:0">Bu personelin bağlı bir kullanıcı hesabı yok, mesaj gönderilemez.</p>
<?php endif; ?>
</section>
<?php endif; ?>

<?php if($tab==='notlar'): ?>
<section class="panel">
<h2>Notlar</h2>
<?php if(!$staffUserId): ?>
<p class="muted" style="margin:0">Bu personelin bağlı bir kullanıcı hesabı yok, kişisel not kaydı görüntülenemez.</p>
<?php elseif(!$personalNotes): ?>
<p class="muted" style="margin:0">Bu kullanıcının kayıtlı kişisel notu yok.</p>
<?php else: ?>
<table>
<thead><tr><th>Başlık</th><th>Termin</th><th>Durum</th></tr></thead>
<tbody>
<?php foreach($personalNotes as $n): ?>
<tr>
<td><?=h($n['title'])?></td>
<td><?=h($n['due_date'] ?: '-')?></td>
<td><?=badge($n['status'], status_tone($n['status']))?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
<?php endif; ?>
</section>
<?php endif; ?>

<?php if($hasCvCol && $tab==='dosyalar'): ?>
<section class="panel">
<h2>Dosyalar</h2>
<?php if(!empty($p['cv_path'])): ?>
<p style="margin:0 0 10px"><a href="<?=h(base_url().$p['cv_path'])?>" target="_blank">📎 Mevcut CV'yi görüntüle</a></p>
<form method="post" onsubmit="return confirm('CV dosyasını kaldırmak istediğinize emin misiniz?')">
<button class="btn secondary" name="clear_cv" value="1">🗑 CV'yi Kaldır</button>
</form>
<?php else: ?>
<p class="muted" style="margin:0">Henüz yüklenmiş bir CV yok.</p>
<?php endif; ?>
<p class="muted" style="margin:10px 0 0">CV yüklemek veya değiştirmek için "👤 Genel Bilgiler" sekmesindeki profil formunu kullanın.</p>
</section>
<?php endif; ?>

<?php if($tab==='performans'): ?>
<section class="panel">
<h2>Performans (KPI)</h2>
<p class="muted" style="margin:0 0 10px">Tüm personel sıralaması ve puan hesaplama yöntemi için KPI ekranına gidin.</p>
<a class="btn" href="kpi.php">📈 KPI Sayfasına Git</a>
</section>
<?php endif; ?>

<?php if($tab==='maas'): ?>
<section class="panel">
<h2>Maaş / Avans / Prim</h2>
<p class="muted" style="margin:0 0 8px">Bu personele ait muhasebe kayıtları (tam yönetim için <a href="accounting.php">Muhasebe</a> ekranı).</p>
<table>
<thead><tr><th>Tarih</th><th>Tür</th><th>Kategori</th><th>Tutar</th><th>Durum</th><th>Açıklama</th></tr></thead>
<tbody>
<?php foreach($financeRows as $fr): ?>
<tr>
<td><?=h($fr['movement_date'])?></td>
<td><?=h($fr['payment_type'] ?: '-')?></td>
<td><?=h($fr['category_name'] ?? '-')?></td>
<td><?=money($fr['amount'])?></td>
<td><?=badge($fr['status'], status_tone($fr['status']))?></td>
<td><?=h($fr['description'] ?? '')?></td>
</tr>
<?php endforeach; ?>
<?php if(!$financeRows): ?><tr><td colspan="6" class="muted">Bu personele ait maaş/avans/prim kaydı yok.</td></tr><?php endif; ?>
</tbody>
</table>
</section>
<?php endif; ?>

<?php if(user_can('users') && $tab==='giris'): ?>
<section class="panel">
<h2>🔑 Giriş Hesabı — 🔐 Yetkiler (Modül Erişimi)</h2>
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

<?php if($tab==='hareket'): ?>
<section class="panel">
<h2>🧾 Hareket Geçmişi</h2>
<p class="muted" style="margin:0 0 8px">Bu personelin yaptığı son işlemler (ürün/cari düzenleme, satış, stok hareketi vb.).</p>
<?php echo function_exists('activity_user_html') ? activity_user_html($pdo,$staffUserId,50) : '<p class="muted">İşlem kaydı modülü yüklü değil.</p>'; ?>
</section>
<?php endif; ?>

<style>
.ptabs{display:flex;flex-wrap:wrap;gap:6px;margin:16px 0 4px}
.ptab{background:#eef2f6;color:#101828;text-decoration:none;border-radius:999px;padding:9px 14px;font-weight:700;font-size:13.5px}
.ptab.active{background:#111827;color:#fff}
</style>

<?php require_once __DIR__.'/layout_bottom.php'; ?>
