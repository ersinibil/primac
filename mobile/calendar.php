<?php
require_once 'common.php';
require_once __DIR__.'/../notes_lib.php';
$pdo=db(); $me=(int)($_SESSION['user']['id']??0);
// Ay seçimi (YYYY-MM)
$ym=preg_match('/^\d{4}-\d{2}$/',$_GET['ay']??'')?$_GET['ay']:date('Y-m');
$first=DateTime::createFromFormat('Y-m-d',$ym.'-01');
$daysIn=(int)$first->format('t');
$startW=((int)$first->format('N'))-1; // 0=Pzt
$prev=(clone $first)->modify('-1 month')->format('Y-m');
$next=(clone $first)->modify('+1 month')->format('Y-m');

// Bu ayın işleri (termin tarihine göre). Personel ise sadece kendi işleri.
$where = $isAdmin ? "" : " AND responsible_personnel_id IN (SELECT id FROM personnel WHERE user_id=$me)";
$byDay=[]; $list=[];
try{
  $q=$pdo->query("SELECT id,job_no,title,status,due_date,DAY(due_date) d FROM jobs
    WHERE due_date IS NOT NULL AND DATE_FORMAT(due_date,'%Y-%m')='$ym'$where ORDER BY due_date");
  foreach($q->fetchAll() as $r){ $byDay[(int)$r['d']][]=$r; $list[]=$r; }
}catch(Throwable $e){}

// Bana atanan görevler (Görevler modülü) — admin tüm görevleri, personel sadece kendine atananları görür.
try{
  $taskWhere = $isAdmin ? "" : " AND personnel_id IN (SELECT id FROM personnel WHERE user_id=$me)";
  $tq=$pdo->query("SELECT id,title,status,due_date,DAY(due_date) d FROM tasks WHERE due_date IS NOT NULL AND DATE_FORMAT(due_date,'%Y-%m')='$ym'$taskWhere ORDER BY due_date");
  foreach($tq->fetchAll() as $r){
    $row=['id'=>$r['id'],'job_no'=>null,'title'=>$r['title'],'status'=>$r['status'],'due_date'=>$r['due_date'],'_task'=>true];
    $d=(int)date('j',strtotime($r['due_date']));
    $byDay[$d][]=$row; $list[]=$row;
  }
}catch(Throwable $e){}

// Kişisel notlar (sadece kendi user_id'n, kimse başkasınınkini göremez) — kullanıcı isteği:
// "takvime de işlensin".
foreach(personal_notes_for_month($pdo,$me,$ym) as $n){
    $row=['id'=>$n['id'],'job_no'=>null,'title'=>$n['title'],'status'=>'Not','due_date'=>$n['due_date'],'_note'=>true];
    $d=(int)date('j',strtotime($n['due_date']));
    $byDay[$d][]=$row; $list[]=$row;
}
usort($list, function($a,$b){ return strcmp($a['due_date'],$b['due_date']); });

topx('Takvim');
$today=(int)date('j'); $isThisMonth=($ym===date('Y-m'));
$mn=['01'=>'Ocak','02'=>'Şubat','03'=>'Mart','04'=>'Nisan','05'=>'Mayıs','06'=>'Haziran','07'=>'Temmuz','08'=>'Ağustos','09'=>'Eylül','10'=>'Ekim','11'=>'Kasım','12'=>'Aralık'];
?>
<div class="panel" style="padding:12px">
  <div style="display:flex;justify-content:space-between;align-items:center">
    <a href="calendar.php?ay=<?=$prev?>" class="btn" style="padding:8px 12px">‹</a>
    <b style="font-size:17px"><?=$mn[$first->format('m')].' '.$first->format('Y')?></b>
    <a href="calendar.php?ay=<?=$next?>" class="btn" style="padding:8px 12px">›</a>
  </div>
  <div style="display:grid;grid-template-columns:repeat(7,1fr);gap:3px;margin-top:10px;text-align:center">
    <?php foreach(['Pzt','Sal','Çar','Per','Cum','Cmt','Paz'] as $w): ?><div style="color:#94a3b8;font-size:11px;font-weight:700"><?=$w?></div><?php endforeach; ?>
    <?php for($i=0;$i<$startW;$i++) echo '<div></div>';
    for($d=1;$d<=$daysIn;$d++):
      $has=!empty($byDay[$d]); $isToday=($isThisMonth&&$d===$today);
      $cnt=$has?count($byDay[$d]):0;
    ?>
      <a href="calendar.php?ay=<?=$ym?>&g=<?=$d?>" style="text-decoration:none;aspect-ratio:1;display:flex;flex-direction:column;align-items:center;justify-content:center;border-radius:10px;background:<?=$isToday?'#2563eb':($has?'rgba(234,179,8,.18)':'rgba(255,255,255,.05)')?>;color:#fff;font-size:14px">
        <span style="font-weight:<?=$isToday?900:500?>"><?=$d?></span>
        <?php if($has): ?><span style="font-size:9px;background:#eab308;color:#0b0b0b;border-radius:6px;padding:0 4px;font-weight:900"><?=$cnt?></span><?php endif; ?>
      </a>
    <?php endfor; ?>
  </div>
</div>

<?php
$g=(int)($_GET['g']??0);
$show = $g>0 ? ($byDay[$g]??[]) : $list;
$baslik = $g>0 ? ($g.' '.$mn[$first->format('m')].' işleri/notları') : 'Bu ay tüm işler/notlar ('.count($list).')';
?>
<div style="font-weight:900;margin:6px 4px"><?=htmlspecialchars($baslik)?></div>
<?php if(!$show): ?><div class="panel muted" style="text-align:center"><?=$g>0?'Bu günde iş/not yok.':'Bu ay termini olan iş/not yok.'?></div><?php endif; ?>
<?php foreach($show as $j): $isNote=!empty($j['_note']); $isTask=!empty($j['_task']); $st=$j['status']; $sc=($isNote||$isTask)?'#eab308':(in_array($st,['Tamamlandı','Teslim Edildi'])?'#22c55e':($st==='İptal'?'#f87171':'#eab308')); $icon=$isNote?'📝 ':($isTask?'🎯 ':''); ?>
  <a class="item" href="<?=$isNote?'mytasks.php':($isTask?'task_view.php?id='.(int)$j['id']:'job_view.php?id='.(int)$j['id'])?>">
    <b><?=$icon.htmlspecialchars($j['title'])?></b> <span style="color:<?=$sc?>;font-weight:900;font-size:12px"><?=htmlspecialchars($st)?></span><br>
    <small class="muted">📅 <?=htmlspecialchars(date('d.m.Y',strtotime($j['due_date'])))?><?=$j['job_no']?' · '.htmlspecialchars($j['job_no']):''?></small>
  </a>
<?php endforeach; ?>
<?php botx(); ?>
