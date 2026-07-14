<?php
require_once 'common.php';
block_personel('tasks');
$pdo=db(); $ok=''; $er='';
if($_SERVER['REQUEST_METHOD']==='POST'){
    try{
        $pid=(int)$_POST['personnel_id']; $title=trim($_POST['title'] ?? '');
        if(!$pid) throw new Exception('Personel seçin.');
        if($title==='') throw new Exception('Görev başlığı girin.');
        $pdo->prepare("INSERT INTO tasks(job_id,personnel_id,title,description,due_date,status,priority,created_by) VALUES(?,?,?,?,?,'Atandı',?,?)")
            ->execute([(int)($_POST['job_id']??0)?:null,$pid,$title,trim($_POST['description']??''),$_POST['due_date']?:null,$_POST['priority']??'Normal',(int)($_SESSION['user']['id']??0)?:null]);
        $tid=(int)$pdo->lastInsertId();
        // Personel adı + bağlı kullanıcı
        $pn=$pdo->prepare("SELECT name FROM personnel WHERE id=?"); $pn->execute([$pid]); $pname=$pn->fetch()['name']??'';
        $uu=$pdo->prepare("SELECT id FROM app_users WHERE personnel_id=? AND active=1 LIMIT 1"); $uu->execute([$pid]); $urow=$uu->fetch();
        // KİŞİSEL bildirim (uygulama içi + push) + iç mesaj (Mesajlar ekranında görünür) — sadece atanan personele
        if($urow){
            $ruid=(int)$urow['id'];
            $msgText='📋 Yeni görev: '.$title.($_POST['due_date']?"\n📅 Termin: ".$_POST['due_date']:'').(trim($_POST['description']??'')?"\n".trim($_POST['description']):'');
            if(function_exists('notify_user')) notify_user($ruid,'📋 Yeni görev: '.$title,($pname?$pname.' · ':'').($_POST['description']??''),'mytasks.php');
            // TOPBAR MESSAGE BADGE GHOST COUNT düzeltmesi (2026-07-14): kendine atama durumunda
            // internal_messages'a YAZILMAZ — bildirim (yukarıdaki notify_user) yine oluşur.
            $sid=(int)($_SESSION['user']['id']??0);
            if($sid!==$ruid){
                try{ $pdo->prepare("INSERT INTO internal_messages(sender_user_id,receiver_user_id,message,is_read) VALUES(?,?,?,0)")->execute([$sid?:null,$ruid,$msgText]); }catch(Throwable $e){}
            }
        }
        try{ if(function_exists('activity_log')) activity_log('Görev','Atama',$pname.' · '.$title,'','task',$tid,'task_view.php?id='.$tid,'📋'); }catch(Throwable $e){}
        $ok='Görev atandı, '.($pname?:'personele').' bildirim gönderildi.';
    }catch(Throwable $e){ $er=$e->getMessage(); }
}
topx('İş Ekle');
$pers=$pdo->query("SELECT id,name,role FROM personnel WHERE COALESCE(active,1)=1 ORDER BY name")->fetchAll();
$jobs=$pdo->query("SELECT id,job_no,title FROM jobs ORDER BY id DESC LIMIT 100")->fetchAll();
?>
<?php if($ok): ?><div class="notice"><?=htmlspecialchars($ok)?></div><?php endif; ?>
<?php if($er): ?><div class="err"><?=htmlspecialchars($er)?></div><?php endif; ?>
<div class="panel">
<form method="post">
  <label>Personel</label>
  <select name="personnel_id" required><option value="">— Seç —</option>
  <?php foreach($pers as $p): ?><option value="<?=$p['id']?>"><?=htmlspecialchars($p['name'].($p['role']?' · '.$p['role']:''))?></option><?php endforeach; ?></select>
  <label>Görev Başlığı</label><input name="title" required>
  <label>Açıklama</label><textarea name="description" rows="3"></textarea>
  <div style="display:flex;gap:10px">
    <div style="flex:1"><label>Öncelik</label><select name="priority"><option>Normal</option><option>Yüksek</option><option>Acil</option></select></div>
    <div style="flex:1"><label>Termin</label><input type="date" name="due_date"></div>
  </div>
  <label>İlgili İş (ops.)</label>
  <select name="job_id"><option value="">— Yok —</option><?php foreach($jobs as $j): ?><option value="<?=$j['id']?>"><?=htmlspecialchars($j['job_no'].' · '.$j['title'])?></option><?php endforeach; ?></select>
  <button class="btn dark" style="width:100%;padding:14px;margin-top:8px">🎯 İşi Ekle & Bildir</button>
</form>
</div>
<?php botx(); ?>
