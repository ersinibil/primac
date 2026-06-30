<?php
require_once __DIR__.'/boot.php';
require_once __DIR__.'/activity_lib.php';
require_login();
require_once __DIR__.'/telegram.php';

$error='';
$pdo=db();
$preType=$_GET['type'] ?? '3d_imalat';

if($_SERVER['REQUEST_METHOD']==='POST'){
    try{
        $jobNo=next_job_no();

        $stmt=$pdo->prepare("INSERT INTO jobs(
            job_no,title,job_type,customer_id,supplier_id,responsible_personnel_id,
            description,due_date,status,sale_amount,cost_amount,created_by,priority,channel,delivery_address,file_link
        ) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");

        $stmt->execute([
            $jobNo,
            trim($_POST['title']),
            $_POST['job_type'],
            $_POST['customer_id'] ?: null,
            $_POST['supplier_id'] ?: null,
            $_POST['responsible_personnel_id'] ?: null,
            trim($_POST['description']),
            $_POST['due_date'] ?: null,
            $_POST['status'],
            (float)$_POST['sale_amount'],
            (float)$_POST['cost_amount'],
            $_SESSION['user']['id'],
            $_POST['priority'],
            trim($_POST['channel']),
            trim($_POST['delivery_address']),
            trim($_POST['file_link'])
        ]);

        $jobId=$pdo->lastInsertId();

        $templates=[
            '3d_imalat'=>['Planlama','Malzeme Kontrol','3D Baskı','Kalite Kontrol','Stok Girişi','Satış Kanalına Aktarım'],
            'uv_baski'=>['Grafik Onayı','Malzeme Hazırlık','UV Baskı','Kalite Kontrol','Teslim'],
            'lazer'=>['Çizim/Dosya','Malzeme Hazırlık','Lazer Kesim/Kazıma','Temizlik','Teslim'],
            'grafik_tasarim'=>['Brief','Tasarım','Revize','Müşteri Onayı','Baskıya Hazır'],
            'dis_atolye'=>['Tedarikçiye Verildi','Dış Üretim','Teslim Alındı','Kalite Kontrol','Müşteriye Teslim'],
            'tedarikcide_uretim'=>['Sipariş','Tedarikçide Üretim','Sevkiyat','Teslim Alındı','Muhasebe'],
            'montaj'=>['Randevu','Malzeme Hazırlık','Montaj','Fotoğraf/Kontrol','Teslim'],
            'satin_alma'=>['Talep','Onay','Sipariş','Teslim Alındı','Ödeme/Muhasebe'],
            'muhasebe'=>['Evrak','Kayıt','Kontrol','Onay','Tamamlandı'],
            'karma'=>['Teklif','Onay','Grafik/Tasarım','Satın Alma','Üretim/Dış Tedarik','Montaj/Teslim','Tahsilat']
        ];

        $stages=$templates[$_POST['job_type']] ?? $templates['karma'];
        $ins=$pdo->prepare("INSERT INTO job_stages(job_id,stage_name,sort_order,status) VALUES(?,?,?,'Bekliyor')");
        $i=1;
        foreach($stages as $s){
            $ins->execute([$jobId,$s,$i++]);
        }

        if(!empty($_POST['responsible_personnel_id'])){
            $task=$pdo->prepare("INSERT INTO tasks(job_id,personnel_id,title,description,due_date,status,priority) VALUES(?,?,?,?,?,'Açık',?)");
            $task->execute([
                $jobId,
                $_POST['responsible_personnel_id'],
                'İş takibi: '.trim($_POST['title']),
                trim($_POST['description']),
                $_POST['due_date'] ?: null,
                $_POST['priority']
            ]);
        }


        try{
            $customerName = '-';
            if(!empty($_POST['customer_id'])){
                $cs = $pdo->prepare("SELECT name FROM contacts WHERE id=?");
                $cs->execute([$_POST['customer_id']]);
                $row = $cs->fetch();
                $customerName = $row['name'] ?? '-';
            }

            $personnelName = '-';
            if(!empty($_POST['responsible_personnel_id'])){
                $ps = $pdo->prepare("SELECT name FROM personnel WHERE id=?");
                $ps->execute([$_POST['responsible_personnel_id']]);
                $row = $ps->fetch();
                $personnelName = $row['name'] ?? '-';
            }

            telegram_send(
                "🆕 Yeni İş Açıldı\n\n".
                $jobNo."\n".
                trim($_POST['title'])."\n\n".
                "👤 Müşteri: ".$customerName."\n".
                "👷 Sorumlu: ".$personnelName."\n".
                "📅 Termin: ".($_POST['due_date'] ?: '-')."\n".
                "🏷 Tip: ".job_type_label($_POST['job_type']),
                erp_url('job_view.php?id='.$jobId)
            );
        }catch(Throwable $e){}

        header("Location: job_view.php?id=".$jobId);
        exit;

    }catch(Throwable $e){
        $error=$e->getMessage();
    }
}

require_once __DIR__.'/layout_top.php';

$customers=$pdo->query("SELECT * FROM contacts WHERE type IN ('Müşteri','Her İkisi') ORDER BY name")->fetchAll();
$suppliers=$pdo->query("SELECT * FROM contacts WHERE type IN ('Tedarikçi','Her İkisi') ORDER BY name")->fetchAll();
$personnel=$pdo->query("SELECT * FROM personnel WHERE active=1 ORDER BY name")->fetchAll();

function selected_type($value, $preType){
    return $value===$preType ? 'selected' : '';
}
?>

<h1>Yeni İş Aç</h1>

<?php if($error): ?>
<div class="alert"><?=h($error)?></div>
<?php endif; ?>

<section class="panel">
<form method="post" class="form-grid">
<label>İş Başlığı<input name="title" required placeholder="Örn: 150 adet telefon standı"></label>

<label>İş Tipi
<select name="job_type">
<option value="3d_imalat" <?=selected_type('3d_imalat',$preType)?>>3D İmalat</option>
<option value="uv_baski" <?=selected_type('uv_baski',$preType)?>>UV Baskı</option>
<option value="lazer" <?=selected_type('lazer',$preType)?>>Lazer</option>
<option value="grafik_tasarim" <?=selected_type('grafik_tasarim',$preType)?>>Grafik Tasarım</option>
<option value="dis_atolye" <?=selected_type('dis_atolye',$preType)?>>Dış Atölye</option>
<option value="tedarikcide_uretim" <?=selected_type('tedarikcide_uretim',$preType)?>>Tedarikçide Üretim</option>
<option value="montaj" <?=selected_type('montaj',$preType)?>>Montaj</option>
<option value="satin_alma" <?=selected_type('satin_alma',$preType)?>>Satın Alma</option>
<option value="muhasebe" <?=selected_type('muhasebe',$preType)?>>Muhasebe İşlemi</option>
<option value="karma" <?=selected_type('karma',$preType)?>>Karma İş</option>
</select>
</label>

<label>Müşteri
<select name="customer_id">
<option value="">Seçiniz</option>
<?php foreach($customers as $c): ?><option value="<?=$c['id']?>"><?=h($c['name'])?></option><?php endforeach; ?>
</select>
</label>

<label>Tedarikçi / Dış Atölye
<select name="supplier_id">
<option value="">Yok</option>
<?php foreach($suppliers as $s): ?><option value="<?=$s['id']?>"><?=h($s['name'])?></option><?php endforeach; ?>
</select>
</label>

<label>Sorumlu Personel
<select name="responsible_personnel_id">
<option value="">Seçiniz</option>
<?php foreach($personnel as $p): ?><option value="<?=$p['id']?>"><?=h($p['name'])?></option><?php endforeach; ?>
</select>
</label>

<label>Termin<input type="date" name="due_date"></label>

<label>Öncelik
<select name="priority"><option>Normal</option><option>Acil</option><option>Çok Acil</option><option>Düşük</option></select>
</label>

<label>Durum
<select name="status"><option>Yeni</option><option>Teklif</option><option>Onay Bekliyor</option><option>Planlandı</option><option>Devam Ediyor</option><option>Dışarıda</option><option>Montajda</option><option>Tamamlandı</option></select>
</label>

<label>Satış Kanalı / Pazar<input name="channel" placeholder="Primac.com.tr, Trendyol, mağaza, müşteri özel..."></label>
<label>Dosya Linki<input name="file_link" placeholder="Tasarım, STL, Drive linki..."></label>
<label>Satış Tutarı<input type="number" step="0.01" name="sale_amount" value="0"></label>
<label>Maliyet<input type="number" step="0.01" name="cost_amount" value="0"></label>
<label class="full">Teslim / Montaj Adresi<textarea name="delivery_address" rows="2"></textarea></label>
<label class="full">Açıklama<textarea name="description" rows="5"></textarea></label>

<button class="btn">İşi Kaydet</button>
</form>
</section>

<?php require_once __DIR__.'/layout_bottom.php'; ?>
