<?php
// WEB Takvim — işler termin tarihine göre (mobil calendar.php paritesi)
require_once __DIR__.'/boot.php'; require_login();
require_once __DIR__.'/notes_lib.php';
$pdo=db(); $__me=(int)($_SESSION['user']['id']??0);
$ym=preg_match('/^\d{4}-\d{2}$/',$_GET['ay']??'')?$_GET['ay']:date('Y-m');
$first=DateTime::createFromFormat('Y-m-d',$ym.'-01');
$daysIn=(int)$first->format('t'); $startW=((int)$first->format('N'))-1;
$prev=(clone $first)->modify('-1 month')->format('Y-m'); $next=(clone $first)->modify('+1 month')->format('Y-m');
$byDay=[];
try{ $q=$pdo->query("SELECT id,job_no,title,status,due_date,DAY(due_date) d FROM jobs WHERE due_date IS NOT NULL AND DATE_FORMAT(due_date,'%Y-%m')='$ym' ORDER BY due_date");
  foreach($q->fetchAll() as $r){ $byDay[(int)$r['d']][]=$r; } }catch(Throwable $e){}
// Bana atanan görevler (Görevler modülü) — admin tüm görevleri, personel sadece kendine atananları görür.
try{
  $taskWhere = is_admin() ? "" : " AND personnel_id IN (SELECT id FROM personnel WHERE user_id=$__me)";
  $tq=$pdo->query("SELECT id,title,status,due_date,DAY(due_date) d FROM tasks WHERE due_date IS NOT NULL AND deleted_at IS NULL AND DATE_FORMAT(due_date,'%Y-%m')='$ym'$taskWhere ORDER BY due_date");
  foreach($tq->fetchAll() as $r){ $byDay[(int)$r['d']][]=['id'=>$r['id'],'status'=>$r['status'],'_task'=>true,'title'=>$r['title']]; }
}catch(Throwable $e){}
// Kişisel notlar (sadece kendi user_id'n) — kullanıcı isteği: "takvime de işlensin".
foreach(personal_notes_for_month($pdo,$__me,$ym) as $n){
    $d=(int)date('j',strtotime($n['due_date']));
    $byDay[$d][]=['id'=>$n['id'],'status'=>'Not','_note'=>true,'title'=>$n['title']];
}
$mn=['01'=>'Ocak','02'=>'Şubat','03'=>'Mart','04'=>'Nisan','05'=>'Mayıs','06'=>'Haziran','07'=>'Temmuz','08'=>'Ağustos','09'=>'Eylül','10'=>'Ekim','11'=>'Kasım','12'=>'Aralık'];
$today=(int)date('j'); $isThisMonth=($ym===date('Y-m'));
require_once __DIR__.'/layout_top.php';
?>
<div class="panel-head"><h1>📅 Takvim — <?=$mn[$first->format('m')].' '.$first->format('Y')?></h1>
<span><a class="btn secondary" href="takvim.php?ay=<?=$prev?>">‹ Önceki</a> <a class="btn secondary" href="takvim.php?ay=<?=$next?>">Sonraki ›</a></span></div>
<section class="panel">
<table style="table-layout:fixed">
<thead><tr><?php foreach(['Pzt','Sal','Çar','Per','Cum','Cmt','Paz'] as $w) echo "<th style='text-align:center'>$w</th>"; ?></tr></thead>
<tbody><tr>
<?php
$cell=0;
for($i=0;$i<$startW;$i++){ echo "<td></td>"; $cell++; }
for($d=1;$d<=$daysIn;$d++){
  if($cell%7===0 && $cell>0) echo "</tr><tr>";
  $isToday=($isThisMonth&&$d===$today);
  echo "<td style='vertical-align:top;height:90px;padding:4px;".($isToday?'background:#1e3a5f':'')."'>";
  echo "<div style='font-weight:700;".($isToday?'color:#60a5fa':'color:#7f95b2')."'>$d</div>";
  if(!empty($byDay[$d])) foreach($byDay[$d] as $j){ $isNote=!empty($j['_note']); $isTask=!empty($j['_task']); $c=($isNote||$isTask)?'#eab308':(in_array($j['status'],['Tamamlandı','Teslim Edildi'])?'#16a34a':($j['status']==='İptal'?'#94a3b8':'#d97706'));
    $icon=$isNote?'📝 ':($isTask?'🎯 ':'');
    $itemStyle="display:block;font-size:11px;background:rgba(217,119,6,.15);border-left:3px solid $c;border-radius:4px;padding:2px 4px;margin-top:2px;color:#e7eefc;text-decoration:none;white-space:nowrap;overflow:hidden;text-overflow:ellipsis";
    // Görev maddesi artık mytasks.php'ye gidiyor — 'tasks' yetkisi istemeyen kişisel görev
    // sayfası (bkz. mytasks.php), önceki "yetkisizse düz metin" geçici çözümüne gerek kalmadı.
    $href=$isNote?'notes.php':($isTask?'task_view.php?id='.(int)$j['id']:'job_view.php?id='.(int)$j['id']);
    echo "<a href='".h($href)."' style='$itemStyle'>".$icon.h($j['title'])."</a>";
  }
  echo "</td>"; $cell++;
}
while($cell%7!==0){ echo "<td></td>"; $cell++; }
?>
</tr></tbody></table>
</section>
<?php require_once __DIR__.'/layout_bottom.php'; ?>
