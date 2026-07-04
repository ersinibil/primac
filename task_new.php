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
            try{ $sid=$_SESSION['user']['id']??null; $pdo->prepare("INSERT INTO internal_messages(sender_user_id,receiver_user_id,message,is_read) VALUES(?,?,?,0)")->execute([$sid,$ruid,'📋 Yeni görev: '.$title.($_POST['due_date']?"\n📅 Termin: ".$_POST['due_date']:'').(trim($_POST['description']??'')?"\n".trim($_POST['description']):'')]); }catch(Throwable $e){}
        }
        try{ if(function_exists('activity_log')) activity_log('Görev','Atama',$pname.' · '.$title,'','task',$tid,'tasks.php','📋'); }catch(Throwable $e){}

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

<h1>İş Ekle</h1>

<?php if($error): ?><div class="alert"><?=h($error)?></div><?php endif; ?>
<?php if($ok): ?><div class="ok"><?=h($ok)?></div><?php endif; ?>

<section class="panel">
  <form method="post">
    <label>Personel *</label>
    <select name="personnel_id" required>
      <option value="">— Seç —</option>
      <?php foreach($pers as $p): ?>
        <option value="<?=(int)$p['id']?>"><?=h($p['name'].($p['role']?' · '.$p['role']:''))?></option>
      <?php endforeach; ?>
    </select>

    <label>Görev Başlığı *</label>
    <input type="text" name="title" value="<?=h($_POST['title']??'')?>" required>

    <label>Açıklama</label>
    <textarea name="description" rows="3"><?=h($_POST['description']??'')?></textarea>

    <div style="display:flex;gap:16px">
      <div style="flex:1">
        <label>Öncelik</label>
        <select name="priority">
          <option<?=($_POST['priority']??'Normal')==='Normal'?' selected':''?>>Normal</option>
          <option<?=($_POST['priority']??'')==='Yüksek'?' selected':''?>>Yüksek</option>
          <option<?=($_POST['priority']??'')==='Acil'?' selected':''?>>Acil</option>
        </select>
      </div>
      <div style="flex:1">
        <label>Termin Tarihi</label>
        <input type="date" name="due_date" value="<?=h($_POST['due_date']??'')?>">
      </div>
    </div>

    <label>İlgili İş (Opsiyonel)</label>
    <select name="job_id">
      <option value="">— Yok —</option>
      <?php foreach($jobs as $j): ?>
        <option value="<?=(int)$j['id']?>"<?=((int)($_POST['job_id']??0)===(int)$j['id']?' selected':'')?>><?=h($j['job_no'].' · '.$j['title'])?></option>
      <?php endforeach; ?>
    </select>

    <button class="btn dark" style="width:100%;padding:14px;margin-top:8px">🎯 İşi Ekle & Bildir</button>
  </form>
</section>

<?php require_once __DIR__.'/layout_bottom.php'; ?>
