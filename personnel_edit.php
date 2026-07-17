<?php
require_once __DIR__.'/boot.php';
require_login();
require_once __DIR__.'/personnel_lib.php';
require_once __DIR__.'/share_lib.php';

$pdo=db();
$id=(int)($_GET['id'] ?? 0);
$error='';
$ok='';
$waCred='';
// personnel_new.php'nin "aynı işlemde giriş hesabı oluştur" akışından (Kullanıcı/Yetki birleştirme,
// 2026-07-17) redirect sonrası tek seferlik flash mesaj — session'da kalıp tekrar görünmesin.
if(!empty($_SESSION['_new_personnel_wa'])){ $waCred=$_SESSION['_new_personnel_wa']; $ok='Personel ve giriş hesabı oluşturuldu.'; unset($_SESSION['_new_personnel_wa']); }
if(!empty($_SESSION['_new_personnel_login_err'])){ $error=$_SESSION['_new_personnel_login_err']; unset($_SESSION['_new_personnel_login_err']); }
$hasCvCol = personnel_has_cv_column($pdo);
// PDP-001 (2026-07-15): mobile/personnel_view.php ile aynı yetki mantığı — şifre/hesap işlemleri
// admin'e VEYA admin'in ayrıca 'personnel_accounts' yetkisi verdiği bir "alt yönetici"ye açık.
// Web'de bu yetkinin daha önce hiç karşılığı yoktu (users.php page_module_map'te tam 'users'
// istiyor, alt-yönetici oraya hiç giremiyordu) — bu personnel_accounts izni web'de işlevsizdi.
$canManageAccounts = is_admin() || user_can('personnel_accounts');

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


if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['save_account_role'])){
    // RELEASE 0.9 — Personel/Kullanıcı/Yetki Sadeleştirme (2026-07-17): önceden bu işlem SADECE
    // user_can('users') olanlara açıktı (users.php'nin ayrı erişim kuralı). Tek-ekran hedefi
    // gereği artık $canManageAccounts (admin veya 'personnel_accounts' alt yönetici) yeterli —
    // 'personnel_accounts' yetkisinin kapsamı bilinçli olarak modül-yetki düzenlemeyi de içerecek
    // şekilde genişletildi. Rol YÜKSELTMESİ (Yönetici) yine SADECE gerçek admin/yönetici oturumuna
    // açık kalır (personnel_resolve_role_preset() içinde), 'users' modül yetkisi bu ekrandan asla
    // verilemez (personnel_sanitize_permissions() filtreler) — bu iki korumaya dokunulmadı.
    try{
        if(!$canManageAccounts) throw new Exception('Yetki atama izniniz yok.');
        $uid=(int)$_POST['perm_user_id'];
        // IDOR koruması: hedef hesap gerçekten BU personele bağlı mı (POST'tan gelen uid'e körü
        // körüne güvenilmez — personnel_reset_password()'daki aynı gerekçe).
        $ownChk=$pdo->prepare("SELECT role FROM app_users WHERE id=? AND personnel_id=?"); $ownChk->execute([$uid,$id]);
        $ownRow=$ownChk->fetch();
        if(!$ownRow) throw new Exception('Geçersiz hesap.');
        $presetDef=personnel_resolve_role_preset($_POST['role_preset'] ?? 'personel', $ownRow['role']);
        personnel_update_account_role($pdo, $uid, $presetDef['role'], $_POST['permissions'] ?? []);
        $ok='Rol ve yetkiler güncellendi.';
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

if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['make_login']) && trim($_POST['username']??'')!=='' && trim($_POST['password']??'')!==''){
    // PDP-001: paylaşılan işlem personnel_lib.php::personnel_create_login() — yetki kontrolü burada.
    // RELEASE 0.9 (2026-07-17): rol+yetki artık aynı adımda seçilebiliyor.
    try{
        if(!$canManageAccounts) throw new Exception('Bu işlem için yönetici yetkisi gerekir.');
        $presetDef=personnel_resolve_role_preset($_POST['role_preset'] ?? 'personel');
        $res=personnel_create_login($pdo, $id, $_POST['username'], $_POST['password'], $presetDef['role'], $_POST['permissions'] ?? []);
        $ok='Giriş hesabı oluşturuldu.';
        $waCred=cred_wa($res['phone'], trim($_POST['username']), $_POST['password']);
    }catch(Throwable $e){ $error=$e->getMessage(); }
}

if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['reset_pw']) && trim($_POST['newpw']??'')!==''){
    // PDP-001: paylaşılan işlem personnel_lib.php::personnel_reset_password() — yetki kontrolü burada,
    // hedef hesap fonksiyon içinde her zaman $id üzerinden DB'den çözülür (IDOR'a kapalı).
    try{
        if(!$canManageAccounts) throw new Exception('Bu işlem için yönetici yetkisi gerekir.');
        $res=personnel_reset_password($pdo, $id, $_POST['newpw']);
        $ok='Şifre güncellendi.';
        $waCred=cred_wa($res['phone'], $res['username'], $_POST['newpw']);
    }catch(Throwable $e){ $error=$e->getMessage(); }
}

if($_SERVER['REQUEST_METHOD']==='POST' && !isset($_POST['regen_telegram_code']) && !isset($_POST['clear_telegram_binding']) && !isset($_POST['save_account_role']) && !isset($_POST['clear_cv']) && !isset($_POST['make_login']) && !isset($_POST['reset_pw'])){
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
$staffUserId=0; $staffPerms=[]; $staffRole=''; $staffUsername='';
try{ $uu=$pdo->prepare("SELECT id,permissions,role,username FROM app_users WHERE personnel_id=? ORDER BY id LIMIT 1"); $uu->execute([$id]);
    if($su=$uu->fetch()){ $staffUserId=(int)$su['id']; $staffRole=$su['role']??''; $staffUsername=$su['username']??''; $dp=json_decode($su['permissions']??'[]',true); $staffPerms=is_array($dp)?$dp:[]; }
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
if($canManageAccounts) $tabLabels['giris']='🔑 Hesap ve Yetki';

$tab = isset($_GET['tab']) ? (string)$_GET['tab'] : 'genel';
if(!array_key_exists($tab,$tabLabels)) $tab='genel';

// RELEASE 0.9 — Personel Ekranları DS Migration (2026-07-17): render katmanı ds_lib.php'ye
// taşındı, POST/iş mantığı (yukarısı) HİÇ değişmedi. ds_tabs() zaten canlı kanıtlanmış bir
// bileşen (search.php); df-table ilk kez burada kullanılıyor ama CSS'i tam ve stabil.
ds_page_header($p['name'], ds_icon('user',24), '', ds_button('Personel Listesi','personnel.php','secondary','','',true).delete_button('personnel',$id), false, true);
?>

<?php if($error): ?><?=ds_alert('danger',$error)?><?php endif; ?>
<?php if($ok): ?><?=ds_alert('success',$ok)?><?php endif; ?>
<?php if($waCred): ?><a href="<?=h($waCred)?>" target="_blank" rel="noopener" class="df-btn df-btn--primary" style="background:var(--df-success);margin-bottom:var(--df-space-3)">📲 Giriş bilgisini WhatsApp ile gönder</a><?php endif; ?>

<div class="df-personnel-statrow">
<div class="df-personnel-stat"><span>Rol</span><strong><?=h($p['role'] ?: '-')?></strong></div>
<div class="df-personnel-stat"><span>Çalışma</span><strong><?=h($p['work_type'] ?: '-')?></strong></div>
<div class="df-personnel-stat"><span>Durum</span><strong><?=$p['active']?ds_badge('Aktif','green'):ds_badge('Pasif','red')?></strong></div>
<div class="df-personnel-stat"><span>Açık Görev</span><strong><?=safe_count("SELECT COUNT(*) c FROM tasks WHERE personnel_id=".(int)$id." AND status!='Tamamlandı' AND deleted_at IS NULL")?></strong></div>
</div>

<?php
$__tabItems=[];
foreach($tabLabels as $tkey=>$tlabel){ $__tabItems[]=['label'=>$tlabel,'url'=>'personnel_edit.php?id='.$id.'&tab='.$tkey,'active'=>$tab===$tkey]; }
ds_tabs($__tabItems);
?>

<?php if($tab==='genel'): ?>
<section class="df-card" style="margin-top:var(--df-space-4)">
<h2 class="df-section-title">Profil Bilgileri</h2>
<form method="post" class="df-form-grid-2" enctype="multipart/form-data">

<?php ds_form_field('Ad Soyad', '<input name="name" required value="'.h($p['name']).'">'); ?>
<?php ds_form_field('Rol / Görev', '<input name="role" value="'.h($p['role']).'">'); ?>
<?php ds_form_field('Telefon', '<input name="phone" value="'.h($p['phone']).'">'); ?>
<?php ds_form_field('E-posta', '<input name="email" value="'.h($p['email'] ?? '').'">'); ?>

<?php
$__workOpts='';
foreach(['Tam Zamanlı','Yarı Zamanlı','Günlük','Dış Paydaş','Stajyer'] as $w){ $__workOpts.='<option '.($p['work_type']===$w?'selected':'').'>'.h($w).'</option>'; }
ds_form_field('Çalışma Tipi', '<select name="work_type">'.$__workOpts.'</select>');
?>
<?php ds_form_field('İşe Giriş Tarihi', '<input type="date" name="start_date" value="'.h($p['start_date'] ?? '').'">'); ?>
<?php ds_form_field('Saatlik Ücret', '<input type="number" step="0.01" name="hourly_rate" value="'.h($p['hourly_rate']).'">'); ?>
<?php ds_form_field('Günlük / Maaş', '<input type="number" step="0.01" name="daily_wage" value="'.h($p['daily_wage']).'">'); ?>

<div class="df-form-span-2"><?php ds_form_field('IBAN', '<input name="iban" value="'.h($p['iban'] ?? '').'">'); ?></div>
<div class="df-form-span-2"><?php ds_form_field('Adres', '<textarea name="address" rows="2">'.h($p['address'] ?? '').'</textarea>'); ?></div>
<div class="df-form-span-2"><?php ds_form_field('Notlar', '<textarea name="notes" rows="4">'.h($p['notes'] ?? '').'</textarea>'); ?></div>

<?php if($hasCvCol): ?>
<div class="df-form-span-2">
<?php ds_form_field('CV / Özgeçmiş', '<input type="file" name="cv" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">', 'Opsiyonel — pdf/doc/docx/jpg/jpeg/png, en fazla 15 MB. Yeni dosya seçilirse eskisinin yerine geçer, boş bırakılırsa mevcut korunur. Mevcut CV için "📎 Dosyalar" sekmesine bakın.'); ?>
</div>
<?php endif; ?>

<div class="df-form-span-2">
<label style="display:flex;align-items:center;gap:8px;font-size:var(--df-type-body-size);color:var(--df-ink-900)">
<input type="checkbox" name="active" <?=$p['active']?'checked':''?> style="width:auto"> Aktif personel
</label>
</div>

<div class="df-form-span-2"><button class="df-btn df-btn--primary">Profili Kaydet</button></div>

</form>
</section>
<?php endif; ?>

<?php if($tab==='gorevler'): ?>
<section class="df-card" style="margin-top:var(--df-space-4)">
<h2 class="df-section-title">Görevler</h2>
<p class="df-section-hint">Bu personele atanmış son 20 görev (salt-okunur — durum değişikliği için <a href="tasks.php">Görevler</a> ekranı kullanılır).</p>
<div class="df-table-wrap"><table class="df-table">
<thead><tr><th>Görev</th><th>İş</th><th>Termin</th><th>Durum</th></tr></thead>
<tbody>
<?php foreach($tasks as $t): ?>
<tr>
<td><?=h($t['title'])?></td>
<td><?=h($t['job_no'] ? $t['job_no'].' - '.$t['job_title'] : '-')?></td>
<td><?=h($t['due_date'])?></td>
<td><?=ds_badge($t['status'])?></td>
</tr>
<?php endforeach; ?>
<?php if(!$tasks): ?><tr><td colspan="4" class="df-muted">Henüz görev yok.</td></tr><?php endif; ?>
</tbody>
</table></div>
</section>
<?php endif; ?>

<?php if($tab==='takvim'): ?>
<section class="df-card" style="margin-top:var(--df-space-4)">
<h2 class="df-section-title">Takvim</h2>
<p class="df-section-hint">Bu personelin termin tarihli iş ve görevleri (tam takvim için <a href="takvim.php">Takvim</a> ekranı).</p>
<div class="df-table-wrap"><table class="df-table">
<thead><tr><th>Tür</th><th>Başlık</th><th>Tarih</th><th>Durum</th></tr></thead>
<tbody>
<?php foreach($calendarItems as $ci): ?>
<tr>
<td><?=h($ci['tip'])?></td>
<td><?=h($ci['baslik'])?></td>
<td><?=h($ci['tarih'])?></td>
<td><?=ds_badge($ci['durum'])?></td>
</tr>
<?php endforeach; ?>
<?php if(!$calendarItems): ?><tr><td colspan="4" class="df-muted">Termin tarihli iş/görev yok.</td></tr><?php endif; ?>
</tbody>
</table></div>
</section>
<?php endif; ?>

<?php if($tab==='mesajlar'): ?>
<section class="df-card" style="margin-top:var(--df-space-4)">
<h2 class="df-section-title">Mesajlar</h2>
<?php if($staffUserId): ?>
<p class="df-section-hint">Bu personelin bağlı kullanıcı hesabıyla birebir mesajlaşabilirsiniz.</p>
<?=ds_button('Mesaj Gönder','messages.php?u='.$staffUserId,'primary','','',true)?>
<?php else: ?>
<p class="df-muted">Bu personelin bağlı bir kullanıcı hesabı yok, mesaj gönderilemez.</p>
<?php endif; ?>
</section>
<?php endif; ?>

<?php if($tab==='notlar'): ?>
<section class="df-card" style="margin-top:var(--df-space-4)">
<h2 class="df-section-title">Notlar</h2>
<?php if(!$staffUserId): ?>
<p class="df-muted">Bu personelin bağlı bir kullanıcı hesabı yok, kişisel not kaydı görüntülenemez.</p>
<?php elseif(!$personalNotes): ?>
<p class="df-muted">Bu kullanıcının kayıtlı kişisel notu yok.</p>
<?php else: ?>
<div class="df-table-wrap"><table class="df-table">
<thead><tr><th>Başlık</th><th>Termin</th><th>Durum</th></tr></thead>
<tbody>
<?php foreach($personalNotes as $n): ?>
<tr>
<td><?=h($n['title'])?></td>
<td><?=h($n['due_date'] ?: '-')?></td>
<td><?=ds_badge($n['status'])?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table></div>
<?php endif; ?>
</section>
<?php endif; ?>

<?php if($hasCvCol && $tab==='dosyalar'): ?>
<section class="df-card" style="margin-top:var(--df-space-4)">
<h2 class="df-section-title">Dosyalar</h2>
<?php if(!empty($p['cv_path'])): ?>
<p style="margin:0 0 10px"><a href="<?=h(base_url().$p['cv_path'])?>" target="_blank">📎 Mevcut CV'yi görüntüle</a></p>
<form method="post" onsubmit="return confirm('CV dosyasını kaldırmak istediğinize emin misiniz?')">
<button class="df-btn df-btn--secondary" name="clear_cv" value="1"><?=ds_icon('trash',16)?> CV'yi Kaldır</button>
</form>
<?php else: ?>
<p class="df-muted">Henüz yüklenmiş bir CV yok.</p>
<?php endif; ?>
<p class="df-section-hint" style="margin-top:var(--df-space-3)">CV yüklemek veya değiştirmek için "Genel Bilgiler" sekmesindeki profil formunu kullanın.</p>
</section>
<?php endif; ?>

<?php if($tab==='performans'): ?>
<section class="df-card" style="margin-top:var(--df-space-4)">
<h2 class="df-section-title">Performans (KPI)</h2>
<p class="df-section-hint">Tüm personel sıralaması ve puan hesaplama yöntemi için KPI ekranına gidin.</p>
<?=ds_button('KPI Sayfasına Git','kpi.php','primary','','',true)?>
</section>
<?php endif; ?>

<?php if($tab==='maas'): ?>
<section class="df-card" style="margin-top:var(--df-space-4)">
<h2 class="df-section-title">Maaş / Avans / Prim</h2>
<p class="df-section-hint">Bu personele ait muhasebe kayıtları (tam yönetim için <a href="accounting.php">Muhasebe</a> ekranı).</p>
<div class="df-table-wrap"><table class="df-table">
<thead><tr><th>Tarih</th><th>Tür</th><th>Kategori</th><th>Tutar</th><th>Durum</th><th>Açıklama</th></tr></thead>
<tbody>
<?php foreach($financeRows as $fr): ?>
<tr>
<td><?=h($fr['movement_date'])?></td>
<td><?=h($fr['payment_type'] ?: '-')?></td>
<td><?=h($fr['category_name'] ?? '-')?></td>
<td><?=money($fr['amount'])?></td>
<td><?=ds_badge($fr['status'])?></td>
<td><?=h($fr['description'] ?? '')?></td>
</tr>
<?php endforeach; ?>
<?php if(!$financeRows): ?><tr><td colspan="6" class="df-muted">Bu personele ait maaş/avans/prim kaydı yok.</td></tr><?php endif; ?>
</tbody>
</table></div>
</section>
<?php endif; ?>

<?php
// RELEASE 0.9 — PİLOT KULLANIMA HAZIRLIK / Personel-Kullanıcı-Yetki Sadeleştirme (2026-07-17,
// Product Owner kararı: "Personel oluşturmak veya düzenlemek için farklı ekranlar arasında
// dolaşılmayacak — ayrı users.php mantığını kullanıcıya hissettirme"). Bu sekme artık account
// oluşturma + şifre sıfırlama + rol + yetki düzenlemenin TEK yeri — user_can('users') ayrımı
// kaldırıldı, $canManageAccounts (admin veya 'personnel_accounts' alt yönetici) tek yetki kapısı.
// users.php hâlâ duruyor (toplu WhatsApp gönderimi + bağsız/eski hesaplar için), ama normal
// personel akışı artık ona hiç ihtiyaç duymuyor.
?>
<?php if($canManageAccounts && $tab==='giris'): ?>
<section class="df-card" style="margin-top:var(--df-space-4)">
<h2 class="df-section-title"><?=ds_icon('user',20)?> Hesap ve Yetki</h2>

<?php if($staffUserId===1): ?>
  <p class="df-section-hint">Bu hesap sistemin ana yöneticisi — korumalıdır, rolü buradan değiştirilemez.</p>
<?php elseif(!$staffUserId): ?>
  <h3 class="df-section-subtitle"><?=ds_icon('plus',16)?> OTS Hesabı Aç</h3>
  <p class="df-section-hint">Bu personelin henüz OTS hesabı yok.</p>
  <form method="post" style="max-width:420px;margin-bottom:var(--df-space-5)">
    <input type="hidden" name="make_login" value="1">
    <?php ds_form_field('Kullanıcı Adı', '<input name="username" required>'); ?>
    <?php ds_form_field('Şifre', '<input type="password" name="password" required minlength="4">'); ?>
    <?=personnel_role_permission_fields_html('personel', [])?>
    <button class="df-btn df-btn--primary" style="margin-top:var(--df-space-3)">Hesap Oluştur</button>
  </form>
<?php else: ?>
  <h3 class="df-section-subtitle"><?=ds_icon('settings',16)?> Şifre Sıfırla</h3>
  <p class="df-section-hint">Kullanıcı adı: <b><?=h($staffUsername)?></b></p>
  <form method="post" style="max-width:360px;margin-bottom:var(--df-space-5)">
    <input type="hidden" name="reset_pw" value="1">
    <?php ds_form_field('Yeni Şifre', '<input type="password" name="newpw" required minlength="4">'); ?>
    <button class="df-btn df-btn--primary">Şifreyi Güncelle</button>
  </form>

  <h3 class="df-section-subtitle"><?=ds_icon('check',16)?> Rol ve Yetkiler</h3>
  <p class="df-section-hint">Hazır bir rol seçin — sadece özel bir durumda "Gelişmiş Yetkiler" ile tek tek modül işaretleyin.</p>
  <form method="post">
    <input type="hidden" name="save_account_role" value="1">
    <input type="hidden" name="perm_user_id" value="<?=$staffUserId?>">
    <?=personnel_role_permission_fields_html(personnel_detect_preset($staffRole,$staffPerms), $staffPerms)?>
    <button class="df-btn df-btn--primary" style="margin-top:var(--df-space-3)"><?=ds_icon('check',16)?> Yetkileri Kaydet</button>
  </form>
<?php endif; ?>

</section>
<?php endif; ?>

<?php if($tab==='hareket'): ?>
<section class="df-card" style="margin-top:var(--df-space-4)">
<h2 class="df-section-title">Hareket Geçmişi</h2>
<p class="df-section-hint">Bu personelin yaptığı son işlemler (ürün/cari düzenleme, satış, stok hareketi vb.).</p>
<?php echo function_exists('activity_user_html') ? activity_user_html($pdo,$staffUserId,50) : '<p class="df-muted">İşlem kaydı modülü yüklü değil.</p>'; ?>
</section>
<?php endif; ?>

<style>
body.nav-compact .df-personnel-statrow{display:flex;flex-wrap:wrap;gap:var(--df-space-3);margin:var(--df-space-4) 0}
body.nav-compact .df-personnel-stat{flex:1;min-width:120px;background:var(--df-surface);border:1px solid var(--df-hairline);border-radius:var(--df-radius-md);padding:var(--df-space-3);display:flex;flex-direction:column;gap:4px}
body.nav-compact .df-personnel-stat span{font-size:var(--df-type-caption-size);color:var(--df-ink-500)}
body.nav-compact .df-personnel-stat strong{font-size:var(--df-type-subtitle-size);color:var(--df-ink-900)}
body.nav-compact .df-section-title{font-size:var(--df-type-section-size);font-weight:var(--df-type-section-weight);color:var(--df-ink-900);margin:0 0 var(--df-space-3);display:flex;align-items:center;gap:8px}
body.nav-compact .df-section-subtitle{font-size:14px;font-weight:700;color:var(--df-ink-900);margin:0 0 var(--df-space-2);display:flex;align-items:center;gap:6px}
body.nav-compact .df-section-hint{font-size:var(--df-type-caption-size);color:var(--df-ink-500);margin:0 0 var(--df-space-3)}
body.nav-compact .df-muted{color:var(--df-ink-500)}
body.nav-compact .df-form-grid-2{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:0 var(--df-space-4)}
body.nav-compact .df-form-span-2{grid-column:1 / -1}
body.nav-compact .df-permission-grid{display:grid;grid-template-columns:repeat(2,minmax(170px,1fr));gap:var(--df-space-2)}
body.nav-compact .df-permission-chip{background:var(--df-surface-sunken);border:1px solid var(--df-hairline);border-radius:var(--df-radius-md);padding:var(--df-space-2) var(--df-space-3);display:flex;align-items:center;gap:8px;font-size:var(--df-type-body-size);color:var(--df-ink-900)}
@media(max-width:640px){body.nav-compact .df-form-grid-2,body.nav-compact .df-permission-grid{grid-template-columns:1fr}}
</style>

<?php require_once __DIR__.'/layout_bottom.php'; ?>
