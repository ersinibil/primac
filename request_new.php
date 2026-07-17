<?php
require_once __DIR__.'/boot.php';
require_login();
require_once __DIR__.'/request_lib.php';

$pdo=db();
$error='';

if($_SERVER['REQUEST_METHOD']==='POST'){
    try{
        $reqNo='TLP-'.date('Y').'-'.str_pad((string)random_int(1,999999),6,'0',STR_PAD_LEFT);
        $jobId=$_POST['related_job_id'] ?: null;
        $category=$_POST['category'];
        $priority=$_POST['priority'];
        $title=trim($_POST['title']);
        $me=(int)($_SESSION['user']['id'] ?? 0);
        $stmt=$pdo->prepare("INSERT INTO management_requests(
            request_no,personnel_id,related_job_id,category,title,description,priority,status,created_by
        ) VALUES(?,?,?,?,?,?,?,?,?)");
        $stmt->execute([
            $reqNo,
            $_POST['personnel_id'] ?: null,
            $jobId,
            $category,
            $title,
            trim($_POST['description']),
            $priority,
            'Yeni',
            $me ?: null
        ]);

        // P0-REQ-01 (2026-07-17): hedef mobil ile birebir aynı şekilde request_lib.php::
        // request_resolve_recipient() ile çözülür — sadece ilgili işin sorumlu personeline,
        // atanmış/geçerli bir hedef yoksa hiç kimseye bildirim gönderilmez.
        // İLETİŞİM MERKEZİ (2026-07-17, Product Owner kararı): "Talep bildirimleri sohbet listesine
        // düşmeyecek; Bildirimler sekmesinde gösterilecek" — internal_messages'a YAZILMIYOR artık,
        // sadece internal_notifications (+ push). Mesaj/Bildirim ayrımı ürün prensibi olarak korunuyor.
        try{
            $recipientUid=request_resolve_recipient($pdo,$jobId);
            if($recipientUid && $recipientUid!==$me){
                $myName=$_SESSION['user']['name'] ?? 'Personel';
                $notifTitle='📨 Yeni talep: '.$title;
                $notifMsg=$myName.' · '.$category.' · '.$priority;
                try{ $pdo->prepare("INSERT INTO internal_notifications(title,message,target_user_id,action_url,is_read) VALUES(?,?,?,?,0)")->execute([$notifTitle,$notifMsg,$recipientUid,'requests.php']); }catch(Throwable $e2){}
                if(file_exists(__DIR__.'/push_lib.php')){ require_once __DIR__.'/push_lib.php'; try{ push_to_user($recipientUid,$notifTitle,$notifMsg,'requests.php'); }catch(Throwable $e2){} }
            }
        }catch(Throwable $e){}

        header("Location: requests.php");
        exit;
    }catch(Throwable $e){
        $error=$e->getMessage();
    }
}

require_once __DIR__.'/layout_top.php';

$personnel=$pdo->query("SELECT * FROM personnel WHERE active=1 ORDER BY name")->fetchAll();
$jobs=$pdo->query("SELECT id, job_no, title FROM jobs ORDER BY id DESC LIMIT 100")->fetchAll();
?>

<?php
// DS migration (2026-07-17) sırasında bulundu: bu link daha önce requests.php'ye gidiyordu — o
// sayfa admin-only (is_admin() zorunlu), ama request_new.php'ye HERKES erişebiliyor (yetki
// kontrolü yok). Admin olmayan bir personel bu linke tıklarsa 403 alıyordu. Artık İletişim
// Merkezi'nin herkese açık "Taleplerim" sekmesine gidiyor.
ds_page_header('Yeni Yönetim Talebi', ds_icon('send',24), '', ds_button('Taleplerim','taleplerim.php','secondary','','',true), false, true);
?>

<?php if($error): ?><?=ds_alert('danger',$error)?><?php endif; ?>

<section class="df-card">
<form method="post" class="df-form-grid-2">

<?php
$__persOpts='<option value="">Seçiniz</option>';
foreach($personnel as $p){ $__persOpts.='<option value="'.$p['id'].'">'.h($p['name']).' - '.h($p['role']).'</option>'; }
ds_form_field('Talep Eden Personel', '<select name="personnel_id">'.$__persOpts.'</select>');
?>

<?php
$__jobOpts='<option value="">İşle bağlantılı değil</option>';
foreach($jobs as $j){ $__jobOpts.='<option value="'.$j['id'].'">'.h($j['job_no']).' - '.h($j['title']).'</option>'; }
ds_form_field('İlgili İş', '<select name="related_job_id">'.$__jobOpts.'</select>');
?>

<?php
$__cats=['Malzeme Talebi','Satın Alma Talebi','Avans Talebi','İzin Talebi','Mesai Talebi','Arıza / Teknik Sorun','Grafik Revize Talebi','Dış Atölye Talebi','Montaj Talebi','Muhasebe / Evrak Talebi','Ödeme Talebi','Özel İzin/Talep','Genel'];
$__catOpts='';
foreach($__cats as $__c){ $__catOpts.='<option>'.$__c.'</option>'; }
ds_form_field('Talep Kategorisi', '<select name="category">'.$__catOpts.'</select>');
?>

<?php ds_form_field('Öncelik', '<select name="priority"><option>Normal</option><option>Acil</option><option>Çok Acil</option><option>Düşük</option></select>'); ?>

<div class="df-form-span-2"><?php ds_form_field('Talep Başlığı', '<input name="title" required placeholder="Örn: Siyah PLA alınması gerekiyor">'); ?></div>
<div class="df-form-span-2"><?php ds_form_field('Açıklama', '<textarea name="description" rows="5" placeholder="Talebin detayını yazın. Örn: K2 Plus işleri için siyah PLA stokta kalmadı. 10 kg alınmalı."></textarea>'); ?></div>

<div class="df-form-span-2"><button class="df-btn df-btn--primary">Talebi Gönder</button></div>

</form>
</section>

<style>
body.nav-compact .df-form-grid-2{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:0 var(--df-space-4)}
body.nav-compact .df-form-span-2{grid-column:1 / -1}
@media(max-width:640px){body.nav-compact .df-form-grid-2{grid-template-columns:1fr}}
</style>

<?php require_once __DIR__.'/layout_bottom.php'; ?>
