<?php
require_once __DIR__.'/boot.php'; require_login();
if(file_exists(__DIR__.'/activity_lib.php')) require_once __DIR__.'/activity_lib.php';
$pdo=db();
// Hızlı durum güncelle (layout'tan önce)
if($_SERVER['REQUEST_METHOD']==='POST' && (int)($_POST['jid']??0) && !empty($_POST['set'])){
    try{ $pdo->prepare("UPDATE jobs SET status=? WHERE id=?")->execute([$_POST['set'],(int)$_POST['jid']]);
        if(function_exists('activity_log')) activity_log('İş','Durum',$_POST['set'],'','job',(int)$_POST['jid'],'job_view.php?id='.(int)$_POST['jid'],'🔄');
    }catch(Throwable $e){}
    header('Location: jobs.php?s='.urlencode($_GET['s']??'aktif').($_GET['type']??''?'&type='.urlencode($_GET['type']):'')); exit;
}
require_once __DIR__.'/layout_top.php';
$s=$_GET['s'] ?? 'aktif'; $type=$_GET['type'] ?? '';
$statusMap=[
  'aktif'=>"j.status NOT IN ('Tamamlandı','Teslim Edildi','İptal')",
  'bekleyen'=>"j.status IN ('Yeni','Bekliyor')",'devam'=>"j.status='Devam Ediyor'",
  'tamam'=>"j.status IN ('Tamamlandı','Teslim Edildi')",'iptal'=>"j.status='İptal'",
  'gec'=>"j.due_date IS NOT NULL AND j.due_date<CURDATE() AND j.status NOT IN ('Tamamlandı','Teslim Edildi','İptal')",
  'tumu'=>"1",
];
$where=[$statusMap[$s]??$statusMap['aktif']]; $params=[];
if($type){ $where[]="j.job_type=?"; $params[]=$type; }
$sqlWhere='WHERE '.implode(' AND ',$where);
$tabs=['aktif'=>'Aktif','bekleyen'=>'Bekleyen','devam'=>'Devam Eden','tamam'=>'Tamamlanan','iptal'=>'İptal','gec'=>'Geciken','tumu'=>'Tümü'];
?>
<div class="panel-head"><h1>İş Emirleri</h1><a class="btn" href="job_new.php">+ Yeni İş</a></div>
<p class="muted">Müşteri işleri ve operasyon takibi</p>
<div class="filters">
<?php foreach($tabs as $k=>$v): ?><a href="jobs.php?s=<?=$k?>" style="<?=$s===$k?'background:#2563eb;color:#fff;border-radius:8px;padding:6px 10px':''?>"><?=$v?></a><?php endforeach; ?>
</div>
<section class="panel">
<table>
<thead><tr><th>İş No</th><th>Başlık</th><th>Müşteri</th><th>Sorumlu</th><th>Termin</th><th>Durum</th><th>İşlem</th></tr></thead>
<tbody>
<?php
try{
$stmt=db()->prepare("SELECT j.*, c.name customer_name, p.name responsible_name FROM jobs j LEFT JOIN contacts c ON c.id=j.customer_id LEFT JOIN personnel p ON p.id=j.responsible_personnel_id $sqlWhere ORDER BY j.id DESC LIMIT 300");
$stmt->execute($params); $rows=$stmt->fetchAll();
foreach($rows as $r){ $aktif=!in_array($r['status'],['Tamamlandı','Teslim Edildi','İptal']);
 echo "<tr><td><a href='job_view.php?id=".h($r['id'])."'>".h($r['job_no'])."</a></td><td>".h($r['title'])."</td><td>".h($r['customer_name'])."</td><td>".h($r['responsible_name'])."</td><td>".h($r['due_date'])."</td><td>".badge($r['status'],status_tone($r['status']))."</td><td style='white-space:nowrap'>";
 echo "<a class='btn ghost' href='job_view.php?id=".h($r['id'])."'>Detay</a> ";
 if($aktif){
   if($r['status']!=='Devam Ediyor') echo "<form method='post' style='display:inline'><input type='hidden' name='jid' value='".h($r['id'])."'><button class='btn ghost' name='set' value='Devam Ediyor'>▶</button></form> ";
   echo "<form method='post' style='display:inline'><input type='hidden' name='jid' value='".h($r['id'])."'><button class='btn' name='set' value='Tamamlandı'>✓ Tamamla</button></form> ";
   echo "<form method='post' style='display:inline' onsubmit=\"return confirm('İptal?')\"><input type='hidden' name='jid' value='".h($r['id'])."'><button class='btn ghost' name='set' value='İptal'>✕</button></form>";
 } elseif($r['status']==='Tamamlandı'){
   echo "<form method='post' style='display:inline'><input type='hidden' name='jid' value='".h($r['id'])."'><button class='btn ghost' name='set' value='Teslim Edildi'>📦 Teslim/Kapat</button></form>";
 }
 echo "</td></tr>";
}
if(!$rows) echo "<tr><td colspan='7' class='muted'>Bu kategoride iş yok.</td></tr>";
}catch(Throwable $e){ echo "<tr><td colspan='7'><div class='alert'>".h($e->getMessage())."</div></td></tr>";}
?>
</tbody></table>
</section>
<?php require_once __DIR__.'/layout_bottom.php'; ?>
