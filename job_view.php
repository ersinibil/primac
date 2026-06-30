<?php
require_once __DIR__.'/boot.php';
require_login();
require_once __DIR__.'/telegram.php';

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
                if(in_array($_POST['job_status'], ['Tamamlandı','Teslim Edildi','İptal'])){
                    $js = $pdo->prepare("SELECT job_no,title FROM jobs WHERE id=?");
                    $js->execute([$id]);
                    $jj = $js->fetch();

                    telegram_send(
                        "✅ İş Durumu Güncellendi\n\n".
                        (($jj['job_no'] ?? 'İş'))."\n".
                        (($jj['title'] ?? ''))."\n\n".
                        "Yeni durum: ".$_POST['job_status'],
                        erp_url('job_view.php?id='.$id)
                    );
                }
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

            $allowed=['jpg','jpeg','png','webp','gif','pdf','ai','cdr','eps','svg','stl','obj','3mf','zip','rar','mp4','mov','doc','docx','xls','xlsx'];
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
?>

<div class="panel-head">
    <h1><?=h($j['job_no'])?> - <?=h($j['title'])?></h1>
    <a class="btn secondary" href="jobs.php">Liste</a>
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
$share='http://acanstr.com/erp/public_file.php?token='.$f['share_token'];
$download='http://acanstr.com/erp/'.$f['file_path'];
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
<?php }catch(Throwable $e){ ?><tr><td colspan="3"><div class="alert"><?=h($e->getMessage())?></div></td></tr><?php } ?>
</tbody>
</table>
</section>

<?php require_once __DIR__.'/layout_bottom.php'; ?>
