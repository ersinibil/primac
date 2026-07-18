<?php
// Kendime İş Ekle — kullanıcının kendine (kendi personnel_id'sine) iş kaydı oluşturması.
// task_new.php'den (admin başkasına atar) FARKLI ve AYRI bir form: 'tasks' yetkisi istemiyor,
// personel seçimi yok (her zaman kendine), Görevlerim (mytasks.php) listesine düşer.
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
        $newTid=(int)$pdo->lastInsertId();
        try{ if(function_exists('activity_log')) activity_log('Görev','Kendime Ekle',$title,'','task',$newTid,'task_view.php?id='.$newTid,'📋'); }catch(Throwable $e){}
        header('Location: mytasks.php?ok=1'); exit;
    }catch(Throwable $e){ $error=$e->getMessage(); }
}

require_once __DIR__.'/layout_top.php';
?>
<?php
// UX-001 (2026-07-16): açıklama metni header'ın subtitle alanına taşındı (tekrarlayan ayrı blok
// azaltıldı). subtitle h() ile escape edildiği için içindeki <a> linki taşınamaz — "Görevlerim"e
// dönüş artık ayrı bir ghost header aksiyonu (aynı hedef, daha görünür).
ds_page_header('➕ Kendime İş Ekle', '', 'Bu iş sadece sana atanır ve Görevlerim listende görünür.', ds_button('← Görevlerim', 'mytasks.php', 'ghost'));
?>

<?php if($error): ?><?=ds_alert('danger',$error)?><?php endif; ?>

<section class="df-card">
<form method="post" class="df-form-grid-2">
<div class="df-form-span-2"><?php ds_form_field('İş Başlığı *', '<input type="text" name="title" value="'.h($_POST['title']??'').'" required>'); ?></div>
<div class="df-form-span-2"><?php ds_form_field('Açıklama', '<textarea name="description" rows="3">'.h($_POST['description']??'').'</textarea>'); ?></div>
<?php
$__prOpts='';
foreach(['Normal','Yüksek','Acil'] as $__pr){ $__prOpts.='<option'.(($_POST['priority']??'Normal')===$__pr?' selected':'').'>'.$__pr.'</option>'; }
ds_form_field('Öncelik', '<select name="priority">'.$__prOpts.'</select>');
?>
<?php ds_form_field('Termin Tarihi', '<input type="date" name="due_date" value="'.h($_POST['due_date']??'').'">'); ?>
<div class="df-form-span-2">
<button class="df-btn df-btn--primary" type="submit" style="margin-top:6px">➕ İşi Ekle</button>
</div>
</form>
</section>

<style>
body.nav-compact .df-form-grid-2{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:0 var(--df-space-4)}
body.nav-compact .df-form-span-2{grid-column:1 / -1}
@media(max-width:640px){body.nav-compact .df-form-grid-2{grid-template-columns:1fr}}
</style>

<?php require_once __DIR__.'/layout_bottom.php'; ?>
