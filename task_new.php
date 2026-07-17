<?php
require_once __DIR__.'/boot.php';
require_login();
require_permission('tasks');
$pdo=db();
$error='';
$ok='';

if($_SERVER['REQUEST_METHOD']==='POST'){
    try{
        $pid=(int)$_POST['personnel_id'];
        $title=trim($_POST['title'] ?? '');
        if(!$pid) throw new Exception('Personel seçin.');
        if($title==='') throw new Exception('Görev başlığı girin.');

        $pdo->prepare("INSERT INTO tasks(job_id,personnel_id,title,description,due_date,status,priority,created_by) VALUES(?,?,?,?,?,'Atandı',?,?)")
            ->execute([(int)($_POST['job_id']??0)?:null,$pid,$title,trim($_POST['description']??''),$_POST['due_date']?:null,$_POST['priority']??'Normal',(int)($_SESSION['user']['id']??0)?:null]);
        $tid=(int)$pdo->lastInsertId();

        // Personel adı + bağlı kullanıcı
        $pn=$pdo->prepare("SELECT name FROM personnel WHERE id=?");
        $pn->execute([$pid]);
        $pname=$pn->fetch()['name']??'';
        $uu=$pdo->prepare("SELECT id FROM app_users WHERE personnel_id=? AND active=1 LIMIT 1");
        $uu->execute([$pid]);
        $urow=$uu->fetch();

        // Bildirim ve mesaj
        if($urow){
            $ruid=(int)$urow['id'];
            // mytasks.php'ye link — atanan personelin 'tasks' yetkisi olmayabilir, tasks.php onu kilitler
            if(function_exists('notify_user')) notify_user($ruid,'📋 Yeni görev: '.$title,($pname?$pname.' · ':'').($_POST['description']??''),'mytasks.php');
            // TOPBAR MESSAGE BADGE GHOST COUNT düzeltmesi (2026-07-14): kendine atama (atayan =
            // atanan) durumunda internal_messages'a YAZILMAZ — bildirim (yukarıdaki notify_user)
            // yine oluşur, sadece Mesajlar ekranında hiç görünemeyecek bir "hayalet" kayıt önlenir.
            $sid=(int)($_SESSION['user']['id']??0);
            if($sid!==$ruid){
                try{ $pdo->prepare("INSERT INTO internal_messages(sender_user_id,receiver_user_id,message,is_read) VALUES(?,?,?,0)")->execute([$sid?:null,$ruid,'📋 Yeni görev: '.$title.($_POST['due_date']?"\n📅 Termin: ".$_POST['due_date']:'').(trim($_POST['description']??'')?"\n".trim($_POST['description']):'')]); }catch(Throwable $e){}
            }
        }
        try{ if(function_exists('activity_log')) activity_log('Görev','Atama',$pname.' · '.$title,'','task',$tid,'task_view.php?id='.$tid,'📋'); }catch(Throwable $e){}

        $ok='Görev atandı, '.($pname?:'personele').' bildirim gönderildi.';

        // Formu temizle
        $_POST=[];
    }catch(Throwable $e){
        $error=$e->getMessage();
    }
}

require_once __DIR__.'/layout_top.php';
$pers=$pdo->query("SELECT id,name,role FROM personnel WHERE COALESCE(active,1)=1 ORDER BY name")->fetchAll();
$jobs=$pdo->query("SELECT id,job_no,title FROM jobs ORDER BY id DESC LIMIT 100")->fetchAll();
?>

<?php ds_page_header('Görev Ekle', ds_icon('calendar',24), '', '', false, true); ?>

<?php if($error): ?><?=ds_alert('danger',$error)?><?php endif; ?>
<?php if($ok): ?><?=ds_alert('success',$ok)?><?php endif; ?>

<section class="df-card">
  <form method="post" class="df-form-grid-2">
    <div class="df-form-span-2">
    <?php
    $__persOpts='<option value="">— Seç —</option>';
    foreach($pers as $p){ $__persOpts.='<option value="'.(int)$p['id'].'">'.h($p['name'].($p['role']?' · '.$p['role']:'')).'</option>'; }
    ds_form_field('Personel *', '<select name="personnel_id" required>'.$__persOpts.'</select>');
    ?>
    </div>

    <div class="df-form-span-2"><?php ds_form_field('Görev Başlığı *', '<input type="text" name="title" value="'.h($_POST['title']??'').'" required>'); ?></div>
    <div class="df-form-span-2"><?php ds_form_field('Açıklama', '<textarea name="description" rows="3">'.h($_POST['description']??'').'</textarea>'); ?></div>

    <?php
    $__pr=$_POST['priority']??'Normal';
    $__prOpts='';
    foreach(['Normal','Yüksek','Acil'] as $__p){ $__prOpts.='<option'.($__pr===$__p?' selected':'').'>'.$__p.'</option>'; }
    ds_form_field('Öncelik', '<select name="priority">'.$__prOpts.'</select>');
    ?>
    <?php ds_form_field('Termin Tarihi', '<input type="date" name="due_date" value="'.h($_POST['due_date']??'').'">'); ?>

    <div class="df-form-span-2">
    <?php
    $__jobOpts='<option value="">— Yok —</option>';
    foreach($jobs as $j){ $__jobOpts.='<option value="'.(int)$j['id'].'"'.((int)($_POST['job_id']??0)===(int)$j['id']?' selected':'').'>'.h($j['job_no'].' · '.$j['title']).'</option>'; }
    ds_form_field('İlgili İş (Opsiyonel)', '<select name="job_id">'.$__jobOpts.'</select>');
    ?>
    </div>

    <div class="df-form-span-2"><button class="df-btn df-btn--primary df-btn--lg" style="width:100%"><?=ds_icon('calendar',16)?> İşi Ekle &amp; Bildir</button></div>
  </form>
</section>

<style>
body.nav-compact .df-form-grid-2{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:0 var(--df-space-4)}
body.nav-compact .df-form-span-2{grid-column:1 / -1}
@media(max-width:640px){body.nav-compact .df-form-grid-2{grid-template-columns:1fr}}
</style>

<?php require_once __DIR__.'/layout_bottom.php'; ?>
