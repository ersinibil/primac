<?php
require_once __DIR__.'/boot.php';
require_once __DIR__.'/activity_lib.php';
require_login();

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

        }catch(Throwable $e){}

        // İş atanan personele iç mesaj + bildirim + push (Mesajlar ekranında görünür)
        if(!empty($_POST['responsible_personnel_id'])){
            try{
                $ru=$pdo->prepare("SELECT id FROM app_users WHERE personnel_id=? AND active=1 LIMIT 1");
                $ru->execute([$_POST['responsible_personnel_id']]);
                $ruid=(int)($ru->fetch()['id'] ?? 0);
                if($ruid){
                    $senderId=(int)($_SESSION['user']['id'] ?? 0);
                    $msgText="🆕 Yeni iş atandı: ".$jobNo." — ".trim($_POST['title'])
                        .($_POST['due_date']?"\n📅 Termin: ".$_POST['due_date']:'')
                        .($customerName!=='-'?"\n👤 Müşteri: ".$customerName:'');
                    try{ $pdo->prepare("INSERT INTO internal_notifications(title,message,target_user_id,action_url,is_read) VALUES(?,?,?,?,0)")->execute(['Yeni İş Atandı',$msgText,$ruid,'job_view.php?id='.$jobId]); }catch(Throwable $e){}
                    // TOPBAR MESSAGE BADGE GHOST COUNT düzeltmesi (2026-07-14): kendini sorumlu
                    // seçme durumunda internal_messages'a YAZILMAZ — bildirim yine oluşur.
                    if($senderId!==$ruid){
                        try{ $pdo->prepare("INSERT INTO internal_messages(sender_user_id,receiver_user_id,message,is_read) VALUES(?,?,?,0)")->execute([$senderId?:null,$ruid,$msgText]); }catch(Throwable $e){}
                    }
                    if(function_exists('push_to_user')){ try{ push_to_user($ruid,'Yeni İş Atandı',$jobNo.' — '.trim($_POST['title']),'job_view.php?id='.$jobId); }catch(Throwable $e){} }
                }
            }catch(Throwable $e){}
        }

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

<?php ds_page_header('Yeni İş Aç', ds_icon('briefcase',24), '', '', false, true); ?>

<?php if($error): ?><?=ds_alert('danger',$error)?><?php endif; ?>

<section class="df-card">
<form method="post" class="df-form-grid-2">
<div class="df-form-span-2"><?php ds_form_field('İş Başlığı', '<input name="title" required placeholder="Örn: 150 adet telefon standı">'); ?></div>

<?php
$__jobTypes=['3d_imalat'=>'3D İmalat','uv_baski'=>'UV Baskı','lazer'=>'Lazer','grafik_tasarim'=>'Grafik Tasarım','dis_atolye'=>'Dış Atölye','tedarikcide_uretim'=>'Tedarikçide Üretim','montaj'=>'Montaj','satin_alma'=>'Satın Alma','muhasebe'=>'Muhasebe İşlemi','karma'=>'Karma İş'];
$__opts='';
foreach($__jobTypes as $__k=>$__v){ $__opts.='<option value="'.h($__k).'" '.selected_type($__k,$preType).'>'.h($__v).'</option>'; }
ds_form_field('İş Tipi', '<select name="job_type">'.$__opts.'</select>');
?>

<?php
$__custOpts='<option value="">Seçiniz</option>';
foreach($customers as $c){ $__custOpts.='<option value="'.h($c['id']).'">'.h($c['name']).'</option>'; }
ds_form_field('Müşteri', '<select name="customer_id">'.$__custOpts.'</select>');
?>

<?php
$__supOpts='<option value="">Yok</option>';
foreach($suppliers as $s){ $__supOpts.='<option value="'.h($s['id']).'">'.h($s['name']).'</option>'; }
ds_form_field('Tedarikçi / Dış Atölye', '<select name="supplier_id">'.$__supOpts.'</select>');
?>

<?php
$__persOpts='<option value="">Seçiniz</option>';
foreach($personnel as $p){ $__persOpts.='<option value="'.h($p['id']).'">'.h($p['name']).'</option>'; }
ds_form_field('Sorumlu Personel', '<select name="responsible_personnel_id">'.$__persOpts.'</select>');
?>

<?php ds_form_field('Termin', '<input type="date" name="due_date">'); ?>
<?php ds_form_field('Öncelik', '<select name="priority"><option>Normal</option><option>Acil</option><option>Çok Acil</option><option>Düşük</option></select>'); ?>
<?php ds_form_field('Durum', '<select name="status"><option>Yeni</option><option>Teklif</option><option>Onay Bekliyor</option><option>Planlandı</option><option>Devam Ediyor</option><option>Dışarıda</option><option>Montajda</option><option>Tamamlandı</option></select>'); ?>
<?php ds_form_field('Satış Kanalı / Pazar', '<input name="channel" placeholder="Primac.com.tr, Trendyol, mağaza, müşteri özel...">'); ?>
<?php ds_form_field('Dosya Linki', '<input name="file_link" placeholder="Tasarım, STL, Drive linki...">'); ?>
<?php ds_form_field('Satış Tutarı', '<input type="number" step="0.01" name="sale_amount" value="0">'); ?>
<?php ds_form_field('Maliyet', '<input type="number" step="0.01" name="cost_amount" value="0">'); ?>
<div class="df-form-span-2"><?php ds_form_field('Teslim / Montaj Adresi', '<textarea name="delivery_address" rows="2"></textarea>'); ?></div>
<div class="df-form-span-2"><?php ds_form_field('Açıklama', '<textarea name="description" rows="5"></textarea>'); ?></div>

<div class="df-form-span-2"><button class="df-btn df-btn--primary">İşi Kaydet</button></div>
</form>
</section>

<style>
body.nav-compact .df-form-grid-2{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:0 var(--df-space-4)}
body.nav-compact .df-form-span-2{grid-column:1 / -1}
@media(max-width:640px){body.nav-compact .df-form-grid-2{grid-template-columns:1fr}}
</style>

<?php require_once __DIR__.'/layout_bottom.php'; ?>
