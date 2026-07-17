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
  // Dashboard "Bugün Teslim" kartının hedefi (2026-07-14) — Dashboard Date Logic düzeltmesiyle
  // aynı CURDATE() mantığı: sadece bugün vadeli, henüz kapanmamış işler.
  'bugun'=>"j.due_date IS NOT NULL AND j.due_date=CURDATE() AND j.status NOT IN ('Tamamlandı','Teslim Edildi','İptal')",
  'tumu'=>"1",
];
$where=[$statusMap[$s]??$statusMap['aktif']]; $params=[];
if($type){ $where[]="j.job_type=?"; $params[]=$type; }
$sqlWhere='WHERE '.implode(' AND ',$where);
$tabs=['aktif'=>'Aktif','bekleyen'=>'Bekleyen','devam'=>'Devam Eden','tamam'=>'Tamamlanan','iptal'=>'İptal','gec'=>'Geciken','bugun'=>'Bugün Teslim','tumu'=>'Tümü'];
ds_page_header('İş Emirleri', ds_icon('briefcase',24), 'Müşteri işleri ve operasyon takibi', ds_button('Yeni İş','job_new.php','primary','','',true), false, true);
$__jobTabItems=[];
foreach($tabs as $k=>$v){ $__jobTabItems[]=['label'=>$v,'url'=>'jobs.php?s='.$k,'active'=>$s===$k]; }
ds_tabs($__jobTabItems);
?>
<section class="df-card" style="margin-top:var(--df-space-4)">
<div class="df-table-wrap"><table class="df-table">
<thead><tr><th>İş No</th><th>Başlık</th><th>Müşteri</th><th>Sorumlu</th><th>Termin</th><th>Durum</th><th>İşlem</th></tr></thead>
<tbody>
<?php
try{
$stmt=db()->prepare("SELECT j.*, c.name customer_name, p.name responsible_name FROM jobs j LEFT JOIN contacts c ON c.id=j.customer_id LEFT JOIN personnel p ON p.id=j.responsible_personnel_id $sqlWhere ORDER BY j.id DESC LIMIT 300");
$stmt->execute($params); $rows=$stmt->fetchAll();
foreach($rows as $r){ $aktif=!in_array($r['status'],['Tamamlandı','Teslim Edildi','İptal']);
 echo "<tr><td><a href='job_view.php?id=".h($r['id'])."'>".h($r['job_no'])."</a></td><td>".h($r['title'])."</td><td>".h($r['customer_name'])."</td><td>".h($r['responsible_name'])."</td><td>".h($r['due_date'])."</td><td>".ds_badge($r['status'])."</td><td style='white-space:nowrap'>";
 echo "<a class='df-btn df-btn--ghost df-btn--sm' href='job_view.php?id=".h($r['id'])."'>Detay</a> ";
 if($aktif){
   if($r['status']!=='Devam Ediyor') echo "<form method='post' style='display:inline'><input type='hidden' name='jid' value='".h($r['id'])."'><button class='df-btn df-btn--ghost df-btn--sm' name='set' value='Devam Ediyor'>▶</button></form> ";
   echo "<form method='post' style='display:inline'><input type='hidden' name='jid' value='".h($r['id'])."'><button class='df-btn df-btn--primary df-btn--sm' name='set' value='Tamamlandı'>✓ Tamamla</button></form> ";
   echo "<form method='post' style='display:inline' onsubmit=\"return confirm('İptal?')\"><input type='hidden' name='jid' value='".h($r['id'])."'><button class='df-btn df-btn--ghost df-btn--sm' name='set' value='İptal'>✕</button></form>";
 } elseif($r['status']==='Tamamlandı'){
   echo "<form method='post' style='display:inline'><input type='hidden' name='jid' value='".h($r['id'])."'><button class='df-btn df-btn--ghost df-btn--sm' name='set' value='Teslim Edildi'>📦 Teslim/Kapat</button></form>";
 }
 echo "</td></tr>";
}
if(!$rows) echo "<tr><td colspan='7' class='df-muted'>Bu kategoride iş yok.</td></tr>";
}catch(Throwable $e){ echo "<tr><td colspan='7'>".ds_alert('danger',$e->getMessage())."</td></tr>";}
?>
</tbody></table></div>
</section>
<style>body.nav-compact .df-muted{color:var(--df-ink-500)}</style>
<?php require_once __DIR__.'/layout_bottom.php'; ?>
