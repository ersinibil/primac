<?php
// Kendime İş Ekle — web mytask_new.php paritesi. task_new.php'den (admin başkasına atar) AYRI,
// 'tasks' yetkisi istemiyor, personel seçimi yok (her zaman kendine).
require_once 'common.php';
$pdo=db();
$me=(int)($_SESSION['user']['id']??0);
$pid=(int)($_SESSION['user']['personnel_id']??0);
if(!$pid){ try{ $r=$pdo->prepare("SELECT personnel_id FROM app_users WHERE id=?"); $r->execute([$me]); $pid=(int)($r->fetch()['personnel_id']??0); }catch(Throwable $e){} }
$er='';

if($_SERVER['REQUEST_METHOD']==='POST'){
    try{
        if(!$pid) throw new Exception('Bu hesap henüz bir personel kaydıyla ilişkilendirilmemiştir. Genel Sistem Yönetimi > Kullanıcılar bölümünden personel eşleştirmesi yapabilirsiniz.');
        $title=trim($_POST['title'] ?? '');
        if($title==='') throw new Exception('İş başlığı girin.');
        $pdo->prepare("INSERT INTO tasks(personnel_id,title,description,due_date,status,priority,created_by) VALUES(?,?,?,?,'Atandı',?,?)")
            ->execute([$pid,$title,trim($_POST['description']??''),$_POST['due_date']?:null,$_POST['priority']??'Normal',$me?:null]);
        try{ if(function_exists('activity_log')) activity_log('Görev','Kendime Ekle',$title,'','task',(int)$pdo->lastInsertId(),'mytasks.php','📋'); }catch(Throwable $e){}
        header('Location: mytasks.php'); exit;
    }catch(Throwable $e){ $er=$e->getMessage(); }
}
topx('Kendime İş Ekle');
?>
<?php if($er): ?><div class="err"><?=htmlspecialchars($er)?></div><?php endif; ?>
<div class="panel">
<form method="post">
  <label>İş Başlığı</label><input name="title" required>
  <label>Açıklama</label><textarea name="description" rows="3"></textarea>
  <div style="display:flex;gap:10px">
    <div style="flex:1"><label>Öncelik</label><select name="priority"><option>Normal</option><option>Yüksek</option><option>Acil</option></select></div>
    <div style="flex:1"><label>Termin</label><input type="date" name="due_date"></div>
  </div>
  <button class="btn dark" style="width:100%;padding:14px;margin-top:8px">➕ İşi Ekle</button>
</form>
</div>
<?php botx(); ?>
