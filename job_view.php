<?php
require_once __DIR__.'/boot.php';
require_login();

$id=(int)($_GET['id'] ?? 0);
$pdo=db();
$error='';
$ok='';

require_once __DIR__.'/job_stages_lib.php';
stage_ajax_respond($pdo,$id); // aşama butonları AJAX (tüm sayfa yenilenmesin → donma yok)

function add_log($jobId, $message, $type='Sistem'){
    try{
        db()->prepare("INSERT INTO job_logs(job_id,user_id,log_type,message) VALUES(?,?,?,?)")
            ->execute([$jobId, $_SESSION['user']['id'] ?? null, $type, $message]);
    }catch(Throwable $e){}
}

if($_SERVER['REQUEST_METHOD']==='POST'){
    // Güvenlik: görüntüleme (GET) bilinçli olarak herkese açık (bildirimden açma), ama yazma
    // işlemleri 'jobs' yetkisi VEYA işin kendisine atanmış olması gerektiriyor (2026-07-03 denetimi).
    if(!job_can_write($pdo,$id)){
        http_response_code(403);
        exit('Bu işlem için yetkiniz yok.');
    }
    try{
        if(isset($_POST['stage_id'])){
            $pdo->prepare("UPDATE job_stages SET status=?, completed_at=IF(?='Tamamlandı',NOW(),completed_at) WHERE id=? AND job_id=?")
                ->execute([$_POST['stage_status'],$_POST['stage_status'],(int)$_POST['stage_id'],$id]);
            add_log($id, "Aşama durumu güncellendi: ".$_POST['stage_status'], "Aşama");
            $ok="Aşama güncellendi.";
        }

        if(isset($_POST['job_status'])){
            $pdo->prepare("UPDATE jobs SET status=?, updated_at=NOW() WHERE id=?")->execute([$_POST['job_status'],$id]);
            add_log($id, "İş durumu güncellendi: ".$_POST['job_status'], "Durum");
            $ok="İş durumu güncellendi.";
            // İş "Tamamlandı/Teslim" ise üretileni otomatik stoğa ekle (ürün bağlıysa, bir kez)
            if(in_array($_POST['job_status'],['Tamamlandı','Teslim Edildi'],true)){
                list($pok,$pmsg)=produce_to_stock($pdo,$id);
                if($pok){ add_log($id,'Üretim stoğa eklendi: '.$pmsg,'Stok'); $ok.=' · 📦 '.$pmsg; }
            }
            try{
            }catch(Throwable $e){}
        }

        if(isset($_POST['upload_file'])){
            if(!isset($_FILES['job_file'])){
                throw new Exception("Dosya alanı sunucuya ulaşmadı.");
            }

            if($_FILES['job_file']['error'] !== UPLOAD_ERR_OK){
                $errors=[
                    UPLOAD_ERR_INI_SIZE=>"Dosya sunucunun izin verdiği boyuttan büyük.",
                    UPLOAD_ERR_FORM_SIZE=>"Dosya form limitinden büyük.",
                    UPLOAD_ERR_PARTIAL=>"Dosya eksik yüklendi.",
                    UPLOAD_ERR_NO_FILE=>"Dosya seçilmedi.",
                    UPLOAD_ERR_NO_TMP_DIR=>"Sunucuda geçici klasör yok.",
                    UPLOAD_ERR_CANT_WRITE=>"Dosya sunucuya yazılamadı.",
                    UPLOAD_ERR_EXTENSION=>"PHP eklentisi yüklemeyi durdurdu."
                ];
                $code=$_FILES['job_file']['error'];
                throw new Exception($errors[$code] ?? "Dosya yükleme hatası. Kod: ".$code);
            }

            $uploadDir=__DIR__.'/uploads/job_files';
            if(!is_dir($uploadDir)){
                if(!mkdir($uploadDir,0755,true)){
                    throw new Exception("uploads/job_files klasörü oluşturulamadı.");
                }
            }

            if(!is_writable($uploadDir)){
                throw new Exception("uploads/job_files klasörü yazılabilir değil. cPanel izinlerini kontrol et.");
            }

            $original=$_FILES['job_file']['name'];
            $tmp=$_FILES['job_file']['tmp_name'];
            $size=(int)$_FILES['job_file']['size'];
            $mime=$_FILES['job_file']['type'] ?? '';
            $ext=strtolower(pathinfo($original, PATHINFO_EXTENSION));

            // 'svg' whitelist'ten çıkarıldı (2026-07-03 güvenlik denetimi): public_file.php
            // (girişsiz, müşteri onay sayfası) 'image' işaretlenmemiş dosyaları doğrudan <a
            // target="_blank"> ile aynı origin'de açıyor — SVG içine gömülü <script> bu şekilde
            // (img tag'i DIŞINDA) doğrudan navigasyonla açıldığında çalışabiliyor (stored XSS).
            // Vektör tasarım için ai/cdr/eps/pdf zaten whitelist'te, iş akışını bozmaz.
            $allowed=['jpg','jpeg','png','webp','gif','pdf','ai','cdr','eps','stl','obj','3mf','zip','rar','mp4','mov','doc','docx','xls','xlsx'];
            if(!in_array($ext,$allowed)){
                throw new Exception("Bu dosya türüne izin verilmiyor: ".$ext);
            }

            if($size > 50*1024*1024){
                throw new Exception("Dosya 50 MB üzerinde olamaz.");
            }

            $stored='job_'.$id.'_'.date('YmdHis').'_'.bin2hex(random_bytes(4)).'.'.$ext;
            $target=$uploadDir.'/'.$stored;

            if(!move_uploaded_file($tmp,$target)){
                throw new Exception("Dosya yüklenemedi. Sunucu yazma izni veya dosya limiti olabilir.");
            }

            $token=bin2hex(random_bytes(24));
            $relative='uploads/job_files/'.$stored;

            $stmt=$pdo->prepare("INSERT INTO job_files(job_id,uploaded_by,file_type,original_name,stored_name,file_path,mime_type,file_size,share_token,approval_status) VALUES(?,?,?,?,?,?,?,?,?,?)");
            $stmt->execute([
                $id,
                $_SESSION['user']['id'] ?? null,
                $_POST['file_type'],
                $original,
                $stored,
                $relative,
                $mime,
                $size,
                $token,
                $_POST['approval_status']
            ]);

            add_log($id, "Dosya yüklendi: ".$original." / Tür: ".$_POST['file_type']." / Durum: ".$_POST['approval_status'], "Dosya");
            $ok="Dosya başarıyla yüklendi. Müşteri paylaşım linki oluşturuldu.";
        }
        // İş notu (mobil ile aynı job_notes tablosu) — parite
        if(isset($_POST['add_note']) && trim($_POST['note']??'')!==''){
            $pdo->exec("CREATE TABLE IF NOT EXISTS job_notes(id INT AUTO_INCREMENT PRIMARY KEY, job_id INT NOT NULL, user_id INT NULL, note TEXT, created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP, KEY idx_job(job_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            $pdo->prepare("INSERT INTO job_notes(job_id,user_id,note) VALUES(?,?,?)")->execute([$id,(int)($_SESSION['user']['id']??0),trim($_POST['note'])]);
            add_log($id,"Not: ".mb_substr(trim($_POST['note']),0,80),"Not");
            $ok="Not eklendi.";
        }
        if(isset($_POST['del_note'])){ try{ $pdo->prepare("DELETE FROM job_notes WHERE id=? AND job_id=?")->execute([(int)$_POST['del_note'],$id]); }catch(Throwable $e){} }
        // İş bilgisi düzenle
        if(isset($_POST['save_job']) && trim($_POST['title']??'')!==''){
            $pdo->prepare("UPDATE jobs SET title=?,description=?,job_type=?,customer_id=?,due_date=?,priority=? WHERE id=?")
                ->execute([trim($_POST['title']),trim($_POST['description']??''),$_POST['job_type']??'karma',(int)($_POST['customer_id']??0)?:null,($_POST['due_date']??'')?:null,$_POST['priority']??'Normal',$id]);
            add_log($id,"İş bilgileri düzenlendi","Düzenleme"); $ok="İş güncellendi.";
        }
        // Üretim aşaması
        if(isset($_POST['init_stages'])||isset($_POST['stage_set'])){
            require_once __DIR__.'/job_stages_lib.php';
            $jt=''; try{ $q=$pdo->prepare("SELECT job_type FROM jobs WHERE id=?"); $q->execute([$id]); $jt=$q->fetch()['job_type']??''; }catch(Throwable $e){}
            handle_stage_post($pdo,$id,$jt);
        }
        // Üretimi stoğa ekle / stok ürünü bağla
        if(isset($_POST['produce_stock'])||isset($_POST['link_produce'])){
            require_once __DIR__.'/job_stages_lib.php';
            if(isset($_POST['produce_stock'])) add_log($id,'Üretim stoğa eklendi','Stok');
            handle_produce_post($pdo,$id);
        }
    }catch(Throwable $e){
        $error=$e->getMessage();
    }
}
require_once __DIR__.'/job_stages_lib.php';
if(!empty($_SESSION['flash'])){ $ok=($ok?$ok.' · ':'').$_SESSION['flash']; unset($_SESSION['flash']); }

$stmt=$pdo->prepare("SELECT j.*, c.name customer_name, s.name supplier_name, p.name responsible_name
FROM jobs j 
LEFT JOIN contacts c ON c.id=j.customer_id 
LEFT JOIN contacts s ON s.id=j.supplier_id
LEFT JOIN personnel p ON p.id=j.responsible_personnel_id
WHERE j.id=?");
$stmt->execute([$id]);
$j=$stmt->fetch();

require_once __DIR__.'/layout_top.php';

if(!$j){
    echo "<h1>İş bulunamadı</h1>";
    require __DIR__.'/layout_bottom.php';
    exit;
}

// PX-001B (2026-07-16) — İş Detay v1 pilot: mockup turu kapandı, gerçek veri. Legacy dal (klasik
// ekran) aşağıda birebir korunuyor, compact dal ayrı bir render yolu (job_detail_lib.php).
$__navSavedMode = function_exists('user_pref_get') ? user_pref_get($pdo, (int)($_SESSION['user']['id']??0), 'nav_layout_mode', null) : null;
$__navMode = function_exists('nav_effective_mode') ? nav_effective_mode($__navSavedMode, is_admin(), nav_is_pilot_user((int)($_SESSION['user']['id']??0))) : 'legacy';
?>

<?php if($__navMode === 'legacy'): ?>
<div class="panel-head">
    <h1><?=h($j['job_no'])?> - <?=h($j['title'])?></h1>
    <div class="actions">
    <a class="btn secondary" href="jobs.php">Liste</a>
    <?=delete_button('job',$id)?>
    </div>
</div>

<?php if($error): ?><div class="alert"><?=h($error)?></div><?php endif; ?>
<?php if($ok): ?><div class="ok"><?=h($ok)?></div><?php endif; ?>

<?php
require_once __DIR__.'/share_lib.php';
$jobPhone=preg_replace('/\D/','',($j['customer_id']?($pdo->query("SELECT phone FROM contacts WHERE id=".(int)$j['customer_id'])->fetch()['phone']??''):''));
$jobTxt="📋 İş: ".$j['title']."\nNo: ".($j['job_no']??'')."\nDurum: ".$j['status'].($j['customer_name']?"\nMüşteri: ".$j['customer_name']:'').($j['responsible_name']?"\nSorumlu: ".$j['responsible_name']:'').($j['due_date']?"\nTermin: ".$j['due_date']:'');
echo '<div style="max-width:520px">'.share_buttons($jobTxt,$jobPhone,'İş: '.$j['title']);
if(!empty($j['due_date'])) echo '<a href="ics.php?job='.$id.'" class="btn" style="display:block;text-align:center;margin-top:8px;background:#0ea5e9;color:#fff;text-decoration:none">📅 Takvime Ekle</a>';
echo '</div>';
?>

<div class="cards">
<div class="card"><small>Tip</small><strong><?=h(job_type_label($j['job_type']))?></strong></div>
<div class="card"><small>Durum</small><strong><?=badge($j['status'],status_tone($j['status']))?></strong></div>
<div class="card"><small>Müşteri</small><strong><?=h($j['customer_name'] ?: '-')?></strong></div>
<div class="card"><small>Tedarikçi</small><strong><?=h($j['supplier_name'] ?: '-')?></strong></div>
<div class="card"><small>Sorumlu</small><strong><?=h($j['responsible_name'] ?: '-')?></strong></div>
<div class="card"><small>Termin</small><strong><?=h($j['due_date'] ?: '-')?></strong></div>
<div class="card"><small>Satış</small><strong><?=money($j['sale_amount'])?></strong></div>
<div class="card"><small>Maliyet</small><strong><?=money($j['cost_amount'])?></strong></div>
</div>

<section class="panel">
<div class="panel-head"><h2>İş Durumu</h2></div>
<form method="post" class="inline">
<select name="job_status">
<?php foreach(['Yeni','Teklif','Onay Bekliyor','Planlandı','Devam Ediyor','Dışarıda','Montajda','Teslim Edildi','Tamamlandı','İptal'] as $s): ?>
<option <?=$j['status']===$s?'selected':''?>><?=$s?></option>
<?php endforeach; ?>
</select>
<button>Durumu Kaydet</button>
</form>
<p><b>Satış kanalı:</b> <?=h($j['channel'] ?: '-')?></p>
<p><b>Dosya linki:</b> <?=h($j['file_link'] ?: '-')?></p>
<p><b>Adres:</b> <?=nl2br(h($j['delivery_address'] ?: '-'))?></p>
<p><?=nl2br(h($j['description']))?></p>
</section>

<section class="panel">
<h2>Aşamalar</h2>
<div class="stage-list">
<?php
$st=$pdo->prepare("SELECT * FROM job_stages WHERE job_id=? ORDER BY sort_order");
$st->execute([$id]);
foreach($st->fetchAll() as $s): ?>
<div class="stage">
<div><b><?=h($s['sort_order'])?>. <?=h($s['stage_name'])?></b><br><?=badge($s['status'],status_tone($s['status']))?></div>
<form method="post" class="inline">
<input type="hidden" name="stage_id" value="<?=$s['id']?>">
<select name="stage_status"><option>Bekliyor</option><option>Devam Ediyor</option><option>Tamamlandı</option></select>
<button class="btn small">Aşamayı Kaydet</button>
</form>
</div>
<?php endforeach; ?>
</div>
</section>

<section class="panel">
<div class="panel-head">
<h2>Dosyalar & Onaylar</h2>
<span class="muted">Tasarım, üretim görseli, montaj fotoğrafı, fatura ve müşteri onay dosyaları</span>
</div>

<form method="post" enctype="multipart/form-data" class="form-grid">
<input type="hidden" name="upload_file" value="1">

<label>1. Dosya Türü
<select name="file_type">
<option>Müşteri Onayı İçin</option>
<option>İç Üretim Dosyası</option>
<option>Tedarikçiye Gönderilecek</option>
<option>Grafik Tasarım</option>
<option>STL / 3D Dosyası</option>
<option>Montaj Fotoğrafı</option>
<option>Üretim Aşama Görseli</option>
<option>Teslim Kanıtı</option>
<option>Fatura / Evrak</option>
<option>Revize Dosyası</option>
<option>Diğer</option>
</select>
</label>

<label>2. Paylaşım Durumu
<select name="approval_status">
<option>İç Kullanım</option>
<option>Müşteri Onayı Bekliyor</option>
<option>Tedarikçiye Gönderilecek</option>
<option>Teslim Kanıtı</option>
</select>
</label>

<label class="full">3. Dosya Seç
<input type="file" name="job_file" required>
</label>

<div class="full">
<button class="btn" type="submit">Kaydet ve Yükle</button>
<span class="muted">Dosya yüklenince listede görünür ve müşteri linki otomatik oluşur.</span>
</div>
</form>

<hr style="border:0;border-top:1px solid #eef2f6;margin:18px 0">

<table>
<thead><tr><th>Dosya</th><th>Tür</th><th>Durum</th><th>Boyut</th><th>Tarih</th><th>İşlem</th></tr></thead>
<tbody>
<?php
try{
$fs=$pdo->prepare("SELECT * FROM job_files WHERE job_id=? ORDER BY id DESC");
$fs->execute([$id]);
$files=$fs->fetchAll();
foreach($files as $f):
$share=base_url().'public_file.php?token='.$f['share_token'];
$download=base_url().$f['file_path'];
?>
<tr>
<td><a href="<?=h($download)?>" target="_blank"><?=h($f['original_name'])?></a></td>
<td><?=h($f['file_type'])?></td>
<td><?=badge($f['approval_status'], status_tone($f['approval_status']))?></td>
<td><?=number_format(((int)$f['file_size'])/1024,1,',','.')?> KB</td>
<td><?=h($f['created_at'])?></td>
<td class="actions">
<a class="btn small secondary" target="_blank" href="<?=h($download)?>">Aç</a>
<a class="btn small" target="_blank" href="<?=h($share)?>">Müşteri Linki</a>
</td>
</tr>
<?php endforeach; ?>
<?php if(!$files): ?><tr><td colspan="6" class="muted">Henüz dosya yok.</td></tr><?php endif; ?>
<?php }catch(Throwable $e){ ?><tr><td colspan="6"><div class="alert"><?=h($e->getMessage())?></div></td></tr><?php } ?>
</tbody>
</table>
</section>

<section class="panel">
<details><summary style="font-weight:900;cursor:pointer;font-size:18px">✏️ İşi Düzenle</summary>
<?php $cs=$pdo->query("SELECT id,name FROM contacts ORDER BY name")->fetchAll(); ?>
<form method="post" style="max-width:560px;margin-top:10px">
  <label>Başlık</label><input name="title" value="<?=h($j['title'])?>" required style="width:100%">
  <label>Müşteri</label>
  <select name="customer_id" style="width:100%"><option value="">— Yok —</option><?php foreach($cs as $cc): ?><option value="<?=$cc['id']?>" <?=$j['customer_id']==$cc['id']?'selected':''?>><?=h($cc['name'])?></option><?php endforeach; ?></select>
  <div style="display:flex;gap:10px"><div style="flex:1"><label>İş Tipi</label>
    <select name="job_type" style="width:100%"><?php foreach(['karma'=>'Karma','3d_imalat'=>'3D İmalat','uv_baski'=>'UV Baskı','lazer'=>'Lazer','grafik_tasarim'=>'Grafik','montaj'=>'Montaj','dis_atolye'=>'Dış Atölye'] as $k=>$v): ?><option value="<?=$k?>" <?=($j['job_type']??'')===$k?'selected':''?>><?=$v?></option><?php endforeach; ?></select></div>
    <div style="flex:1"><label>Termin</label><input type="date" name="due_date" value="<?=h($j['due_date']??'')?>" style="width:100%"></div></div>
  <label>Öncelik</label>
  <select name="priority" style="width:100%"><?php foreach(['Normal','Acil','Çok Acil','Düşük'] as $pr): ?><option <?=($j['priority']??'Normal')===$pr?'selected':''?>><?=$pr?></option><?php endforeach; ?></select>
  <label>Açıklama</label><textarea name="description" rows="3" style="width:100%"><?=h($j['description']??'')?></textarea>
  <button class="btn" name="save_job" value="1" style="margin-top:8px">💾 Kaydet</button>
</form>
</details>
</section>

<section class="panel">
<h2>🏭 Üretim Aşamaları</h2>
<div style="max-width:520px"><?=stages_html($pdo,$id,'job_view.php?id='.$id)?></div>
<div style="max-width:520px"><?=produce_box_html($pdo,$j)?></div>
</section>

<section class="panel" style="text-align:center">
<h2>📷 İş QR Kodu</h2>
<img loading="lazy" decoding="async" src="https://api.qrserver.com/v1/create-qr-code/?size=170x170&data=<?=urlencode((function_exists('base_url')?base_url():'').'job_view.php?id='.$id)?>" alt="QR" style="width:170px;height:170px;background:#fff;border-radius:12px;padding:6px">
</section>

<section class="panel">
<h2>📝 Notlar</h2>
<form method="post" style="display:flex;gap:8px;margin-bottom:10px">
  <input name="note" placeholder="İşle ilgili not yaz…" style="flex:1">
  <button class="btn" name="add_note" value="1">+ Not Ekle</button>
</form>
<table><tbody>
<?php
try{ $nt=$pdo->prepare("SELECT n.*, u.full_name, u.username FROM job_notes n LEFT JOIN app_users u ON u.id=n.user_id WHERE n.job_id=? ORDER BY n.id DESC"); $nt->execute([$id]); $notes=$nt->fetchAll(); }catch(Throwable $e){ $notes=[]; }
foreach($notes as $n){ $who=$n['full_name']?:$n['username']?:'?';
  echo "<tr><td>".nl2br(h($n['note']))."<br><small class='muted'>".h($who)." · ".h($n['created_at'])."</small></td><td style='width:60px'><form method='post' onsubmit=\"return confirm('Sil?')\"><button class='btn ghost' name='del_note' value='".(int)$n['id']."'>🗑</button></form></td></tr>";
}
if(!$notes) echo "<tr><td class='muted'>Henüz not yok.</td></tr>";
?>
</tbody></table>
</section>

<section class="panel">
<h2>Zaman Çizelgesi</h2>
<table>
<thead><tr><th>Tarih</th><th>Tip</th><th>İşlem</th></tr></thead>
<tbody>
<?php
try{
$lg=$pdo->prepare("SELECT * FROM job_logs WHERE job_id=? ORDER BY id DESC LIMIT 50");
$lg->execute([$id]);
$logs=$lg->fetchAll();
foreach($logs as $l): ?>
<tr><td><?=h($l['created_at'])?></td><td><?=h($l['log_type'])?></td><td><?=h($l['message'])?></td></tr>
<?php endforeach; ?>
<?php if(!$logs): ?><tr><td colspan="3" class="muted">Henüz kayıt yok.</td></tr><?php endif; ?>
<?php }catch(Throwable $e){ ?><tr><td colspan="3" class="muted">Henüz kayıt yok.</td></tr><?php } ?>
</tbody>
</table>
</section>

<?php else: ?>
<?php
// PX-001B — İş Detay v1 (mockup turu kapandı, Product Owner kararı 2026-07-16). Legacy'nin
// yazma/POST akışları (aşama/durum/dosya/not/düzenleme) hiç değişmedi — bu sadece yeni bir OKUMA
// dalı. "Tamamlandı" butonu bile mevcut $_POST['job_status'] işleyicisini kullanıyor.
require_once __DIR__.'/share_lib.php';
$__pendingApprovals = job_detail_pending_approvals($pdo, $id);
$__nextStep = job_detail_next_step($j, $__pendingApprovals);
$__timeline = job_detail_timeline($id, 4);
$__jdPhone = preg_replace('/\D/','', ($j['customer_id'] ? ($pdo->query("SELECT phone FROM contacts WHERE id=".(int)$j['customer_id'])->fetch()['phone'] ?? '') : ''));
$__jdTxt = "📋 İş: ".$j['title']."\nNo: ".($j['job_no']??'')."\nDurum: ".$j['status'].($j['customer_name']?"\nMüşteri: ".$j['customer_name']:'').($j['due_date']?"\nTermin: ".$j['due_date']:'');
try{ $__s=$pdo->prepare("SELECT n.*, u.full_name, u.username FROM job_notes n LEFT JOIN app_users u ON u.id=n.user_id WHERE n.job_id=? ORDER BY n.id DESC LIMIT 1"); $__s->execute([$id]); $__jdNoteRow=$__s->fetch(); }catch(Throwable $e){ $__jdNoteRow=null; }
try{ $__s=$pdo->prepare("SELECT * FROM job_files WHERE job_id=? ORDER BY id DESC LIMIT 2"); $__s->execute([$id]); $__jdFileRows=$__s->fetchAll(); }catch(Throwable $e){ $__jdFileRows=[]; }
$__jdOverdue = !empty($j['due_date']) && $j['due_date'] < date('Y-m-d') && !in_array($j['status'], ['Tamamlandı','İptal','Teslim Edildi'], true);
$__jdDaysLate = $__jdOverdue ? (int)round((strtotime(date('Y-m-d')) - strtotime($j['due_date']))/86400) : 0;
$__jdNextIcons = ['call'=>'phone','money'=>'send','check'=>'check','clock'=>'calendar'];
?>
<div class="df-jd">
  <a class="df-jd-back" href="jobs.php"><span class="df-jd-back-a"></span> İşler</a>

  <div class="df-jd-hero">
    <span class="df-badge df-badge--<?=($__jdOverdue?'danger':'info')?>"><?=h($__jdOverdue ? 'GECİKTİ' : mb_strtoupper($j['status'],'UTF-8'))?></span>
    <div class="df-jd-title"><?=h($j['title'])?></div>
    <div class="df-jd-sub"><?=h($j['customer_name'] ?: 'Cari yok')?><?php if($__jdOverdue): ?><span class="df-jd-dot"></span><b><?=(int)$__jdDaysLate?> gün gecikti</b><?php endif; ?></div>
  </div>

  <?php if($__nextStep): ?>
  <div class="df-jd-next">
    <div class="df-jd-next-ic"><?=ds_icon($__jdNextIcons[$__nextStep['icon']] ?? 'check', 19)?></div>
    <div class="df-jd-next-body">
      <div class="df-jd-next-label">Sonraki Adım</div>
      <div class="df-jd-next-title"><?=h($__nextStep['title'])?></div>
      <div class="df-jd-next-sub"><?=h($__nextStep['sub'])?></div>
    </div>
  </div>
  <?php else: ?>
  <div class="df-panel" style="text-align:center;padding:20px 16px">
    <div style="font-weight:700;font-size:13.5px">Şu an acil bir adım yok</div>
  </div>
  <?php endif; ?>

  <?php if($__timeline): ?>
  <div>
    <div class="df-jd-lab">Zaman Akışı</div>
    <div>
      <?php foreach(array_reverse($__timeline) as $__tlRow): ?>
      <div class="df-jd-tl-row"><span class="df-jd-tl-t"><?=h(date('H:i', strtotime($__tlRow['created_at'])))?></span><span class="df-jd-tl-dot"></span><span class="df-jd-tl-e"><?=h($__tlRow['title'] ?: $__tlRow['action'])?></span></div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <div>
    <div class="df-jd-lab">İlgili Kişiler</div>
    <div class="df-jd-people">
      <div class="df-jd-p-row"><span class="df-jd-p-role">Müşteri</span><span class="df-jd-p-name"><?=h($j['customer_name'] ?: '-')?></span></div>
      <div class="df-jd-p-row"><span class="df-jd-p-role">Sorumlu</span><span class="df-jd-p-name"><?=h($j['responsible_name'] ?: '-')?></span></div>
    </div>
  </div>

  <?php if($__jdFileRows): ?>
  <div id="df-jd-files">
    <div class="df-jd-lab">Dosyalar</div>
    <div class="df-jd-files">
      <?php foreach($__jdFileRows as $__f): $__ext=strtolower(pathinfo($__f['original_name'] ?? '', PATHINFO_EXTENSION)); $__isImg = in_array($__ext,['jpg','jpeg','png','gif','webp'],true) || strpos((string)$__f['mime_type'],'image/')===0; ?>
      <a class="df-jd-f-row" href="<?=h($__f['file_path'] ?: '#')?>" target="_blank" rel="noopener">
        <span class="df-jd-f-badge<?=($__isImg?' df-jd-f-badge--img':'')?>"><?=h($__isImg?'IMG':mb_strtoupper($__ext ?: 'DOSYA','UTF-8'))?></span>
        <span class="df-jd-f-name"><?=h($__f['original_name'] ?: 'Dosya')?></span>
        <span class="df-jd-f-time"><?=h(function_exists('activity_time_ago') ? activity_time_ago($__f['created_at']) : $__f['created_at'])?></span>
      </a>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <?php if($__jdNoteRow): $__who=$__jdNoteRow['full_name'] ?: $__jdNoteRow['username'] ?: '?'; ?>
  <div>
    <div class="df-jd-lab">Notlar</div>
    <div class="df-jd-note"><b><?=h($__who)?>:</b> <?=nl2br(h($__jdNoteRow['note']))?><div class="df-jd-note-meta"><?=h($__jdNoteRow['created_at'])?></div></div>
  </div>
  <?php endif; ?>

  <div class="df-jd-bar">
    <?php if($__jdPhone): ?><a class="df-jd-bar-btn" href="tel:<?=h($__jdPhone)?>"><?=ds_icon('phone',18)?><span>Ara</span></a><?php endif; ?>
    <a class="df-jd-bar-btn" href="<?=h(wa_link($__jdTxt,$__jdPhone))?>" target="_blank" rel="noopener"><span style="font-size:16px">📱</span><span>WhatsApp</span></a>
    <a class="df-jd-bar-btn" href="#df-jd-files"><span style="font-size:16px">📎</span><span>Dosya</span></a>
    <a class="df-jd-bar-btn" href="<?=h(mail_link('İş: '.$j['title'],$__jdTxt))?>"><?=ds_icon('send',18)?><span>Paylaş</span></a>
    <?php if(!in_array($j['status'],['Tamamlandı','Teslim Edildi','İptal'],true)): ?>
    <form method="post" style="flex:1;margin:0"><input type="hidden" name="job_status" value="Tamamlandı"><button type="submit" class="df-jd-bar-btn is-done" style="width:100%;border:0;background:none"><?=ds_icon('check',18)?><span>Tamamlandı</span></button></form>
    <?php endif; ?>
  </div>

  <?php if(can_edit_delete()):
  // İŞ EMRİ YAŞAM DÖNGÜSÜ TAMAMLAMA (2026-07-19, USER TEST bulgusu — "Tamamlandı" bir iş burada
  // hiçbir yönetim aksiyonu SUNMUYORDU, sadece yukarıdaki "Tahsilat/Sonraki Adım" görünüyordu).
  // Aşağıdaki form'lar YENİ bir backend kuralı İCAT ETMİYOR — save_job/job_status POST handler'ları
  // bu dosyanın en üstünde ZATEN var (satır ~134/~35), sadece compact ekranda hiç gösterilmiyordu.
  ?>
  <div class="df-card" style="margin-top:16px">
    <h2 style="font-size:15px;margin:0 0 12px">Yönetim</h2>
    <details style="margin-bottom:12px">
      <summary style="cursor:pointer;font-weight:700">✏️ İşi Düzenle</summary>
      <?php $__jcs=$pdo->query("SELECT id,name FROM contacts ORDER BY name")->fetchAll(); ?>
      <form method="post" style="margin-top:10px">
        <?php ds_form_field('Başlık', '<input name="title" value="'.h($j['title']).'" required>'); ?>
        <?php
        $__jcOpts='<option value="">— Yok —</option>';
        foreach($__jcs as $__cc){ $__jcOpts.='<option value="'.(int)$__cc['id'].'" '.((int)$j['customer_id']===(int)$__cc['id']?'selected':'').'>'.h($__cc['name']).'</option>'; }
        ds_form_field('Müşteri', '<select name="customer_id">'.$__jcOpts.'</select>');
        ds_form_field('Termin', '<input type="date" name="due_date" value="'.h($j['due_date']??'').'">');
        ds_form_field('Açıklama', '<textarea name="description" rows="3">'.h($j['description']??'').'</textarea>');
        ?>
        <input type="hidden" name="job_type" value="<?=h($j['job_type'])?>">
        <input type="hidden" name="priority" value="<?=h($j['priority'] ?? 'Normal')?>">
        <button class="df-btn df-btn--primary" name="save_job" value="1">💾 Kaydet</button>
      </form>
    </details>

    <form method="post" style="display:flex;gap:8px;flex-wrap:wrap;align-items:flex-end;margin-bottom:12px">
      <div style="flex:1;min-width:160px">
      <?php
      $__jsOpts='';
      foreach(['Yeni','Teklif','Onay Bekliyor','Planlandı','Devam Ediyor','Dışarıda','Montajda','Teslim Edildi','Tamamlandı','İptal'] as $__s){ $__jsOpts.='<option '.($j['status']===$__s?'selected':'').'>'.$__s.'</option>'; }
      ds_form_field('Durum (İptal / Geri Al dahil, mevcut durum makinesi)', '<select name="job_status">'.$__jsOpts.'</select>');
      ?>
      </div>
      <button class="df-btn df-btn--secondary" type="submit">Durumu Kaydet</button>
    </form>

    <?php if(is_admin()): ?>
    <div style="border-top:1px solid var(--df-hairline);padding-top:12px">
      <?=delete_button('job',$id,'🗑 İş Emrini Sil')?>
      <p class="df-muted" style="font-size:12px;margin-top:6px">Bağlı finans/stok hareketi varsa silme engellenir — önce kaynağından (Finans/Stok Hareketleri) geri alın.</p>
    </div>
    <?php endif; ?>
  </div>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php require_once __DIR__.'/layout_bottom.php'; ?>
