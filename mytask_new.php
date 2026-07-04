<?php
// Kendime İş Ekle — kullanıcının kendine (kendi personnel_id'sine) iş kaydı oluşturması.
// task_new.php'den (admin başkasına atar) FARKLI ve AYRI bir form: 'tasks' yetkisi istemiyor,
// personel seçimi yok (her zaman kendine), İşlerim (mytasks.php) listesine düşer.
require_once __DIR__.'/boot.php';
require_login();
$pdo=db();
$me=(int)($_SESSION['user']['id']??0);
$pid=(int)($_SESSION['user']['personnel_id']??0);
if(!$pid){ try{ $r=$pdo->prepare("SELECT personnel_id FROM app_users WHERE id=?"); $r->execute([$me]); $pid=(int)($r->fetch()['personnel_id']??0); }catch(Throwable $e){} }
$error='';

if($_SERVER['REQUEST_METHOD']==='POST'){
    try{
        if(!$pid) throw new Exception('Bu hesap henüz bir personel kaydıyla ilişkilendirilmemiştir. Genel Sistem Yönetimi > Kullanıcılar bölümünden personel eşleştirmesi yapabilirsiniz.');
        $title=trim($_POST['title'] ?? '');
        if($title==='') throw new Exception('İş başlığı girin.');
        $pdo->prepare("INSERT INTO tasks(personnel_id,title,description,due_date,status,priority,created_by) VALUES(?,?,?,?,'Atandı',?,?)")
            ->execute([$pid,$title,trim($_POST['description']??''),$_POST['due_date']?:null,$_POST['priority']??'Normal',$me?:null]);
        try{ if(function_exists('activity_log')) activity_log('Görev','Kendime Ekle',$title,'','task',(int)$pdo->lastInsertId(),'mytasks.php','📋'); }catch(Throwable $e){}
        header('Location: mytasks.php?ok=1'); exit;
    }catch(Throwable $e){ $error=$e->getMessage(); }
}

require_once __DIR__.'/layout_top.php';
?>
<div class="panel-head"><h1>➕ Kendime İş Ekle</h1></div>
<p class="muted">Bu iş sadece sana atanır, <a href="mytasks.php">İşlerim</a> listende görünür.</p>

<?php if($error): ?><div class="alert"><?=h($error)?></div><?php endif; ?>

<section class="panel">
<form method="post" class="form-grid">
<label class="full">İş Başlığı *
<input type="text" name="title" value="<?=h($_POST['title']??'')?>" required>
</label>
<label class="full">Açıklama
<textarea name="description" rows="3"><?=h($_POST['description']??'')?></textarea>
</label>
<label>Öncelik
<select name="priority">
<option<?=($_POST['priority']??'Normal')==='Normal'?' selected':''?>>Normal</option>
<option<?=($_POST['priority']??'')==='Yüksek'?' selected':''?>>Yüksek</option>
<option<?=($_POST['priority']??'')==='Acil'?' selected':''?>>Acil</option>
</select>
</label>
<label>Termin Tarihi
<input type="date" name="due_date" value="<?=h($_POST['due_date']??'')?>">
</label>
<div class="full">
<button class="btn" type="submit" style="margin-top:6px">➕ İşi Ekle</button>
</div>
</form>
</section>

<?php require_once __DIR__.'/layout_bottom.php'; ?>
