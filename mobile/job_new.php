<?php
require_once 'common.php';
$pdo=db(); $ok=''; $er='';
if($_SERVER['REQUEST_METHOD']==='POST'){
    try{
        $title=trim($_POST['title'] ?? '');
        if($title==='') throw new Exception('İş başlığı girin.');
        $no=function_exists('next_job_no')?next_job_no():'MOB-'.date('YmdHis');
        $resp=(int)($_POST['responsible_personnel_id'] ?? 0) ?: null;
        $pdo->prepare("INSERT INTO jobs(job_no,title,description,job_type,customer_id,responsible_personnel_id,due_date,status,priority,created_at)
            VALUES(?,?,?,?,?,?,?,'Yeni',?,NOW())")
            ->execute([$no,$title,trim($_POST['description']??''),$_POST['job_type']??'karma',(int)($_POST['customer_id']??0)?:null,$resp,$_POST['due_date']?:null,$_POST['priority']??'Normal']);
        $jid=(int)$pdo->lastInsertId();
        if($resp){
            $pn=$pdo->prepare("SELECT name FROM personnel WHERE id=?"); $pn->execute([$resp]); $pname=$pn->fetch()['name']??'';
            $uu=$pdo->prepare("SELECT id FROM app_users WHERE personnel_id=? AND active=1 LIMIT 1"); $uu->execute([$resp]); $u=$uu->fetch();
            if($u && function_exists('notify_user')) notify_user((int)$u['id'],'📋 Yeni iş atandı: '.$title,$pname.' · '.$no,'job_view.php?id='.$jid);
        }
        try{ if(function_exists('activity_log')) activity_log('İş','Yeni',$title,'','job',$jid,'job_view.php?id='.$jid,'📋'); }catch(Throwable $e){}
        $ok='İş oluşturuldu'.($resp?' ve atandı.':'.');
    }catch(Throwable $e){ $er=$e->getMessage(); }
}
topx('Yeni İş');
$cs=$pdo->query("SELECT id,name FROM contacts ORDER BY name")->fetchAll();
$pers=$pdo->query("SELECT id,name FROM personnel WHERE COALESCE(active,1)=1 ORDER BY name")->fetchAll();
?>
<?php if($ok): ?><div class="notice"><?=htmlspecialchars($ok)?> · <a href="jobs.php" style="color:#fff;text-decoration:underline">İşler</a></div><?php endif; ?>
<?php if($er): ?><div class="err"><?=htmlspecialchars($er)?></div><?php endif; ?>
<div class="panel">
<form method="post">
  <label>İş Başlığı</label><input name="title" required>
  <label>Müşteri</label>
  <select name="customer_id"><option value="">— Yok —</option><?php foreach($cs as $c): ?><option value="<?=$c['id']?>"><?=htmlspecialchars($c['name'])?></option><?php endforeach; ?></select>
  <label>Sorumlu Personel</label>
  <select name="responsible_personnel_id"><option value="">— Atanmadı —</option><?php foreach($pers as $p): ?><option value="<?=$p['id']?>"><?=htmlspecialchars($p['name'])?></option><?php endforeach; ?></select>
  <label>İş Tipi</label>
  <select name="job_type"><option value="karma">Karma</option><option value="3d_imalat">3D İmalat</option><option value="uv_baski">UV Baskı</option><option value="lazer">Lazer</option><option value="grafik_tasarim">Grafik Tasarım</option><option value="montaj">Montaj</option><option value="dis_atolye">Dış Atölye</option></select>
  <div style="display:flex;gap:10px">
    <div style="flex:1"><label>Öncelik</label><select name="priority"><option>Normal</option><option>Yüksek</option><option>Acil</option></select></div>
    <div style="flex:1"><label>Termin</label><input type="date" name="due_date"></div>
  </div>
  <label>Açıklama</label><textarea name="description" rows="3"></textarea>
  <button class="btn dark" style="width:100%;padding:14px;margin-top:8px">📋 İşi Oluştur & Ata</button>
</form>
</div>
<?php botx(); ?>
