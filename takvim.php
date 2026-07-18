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
    $byDay[$d][]=['id'=>$n['id'],'status'=>'Not','_note'=>true,'title'=>$n['title'],'due_date'=>$n['due_date']];
}
$mn=['01'=>'Ocak','02'=>'Şubat','03'=>'Mart','04'=>'Nisan','05'=>'Mayıs','06'=>'Haziran','07'=>'Temmuz','08'=>'Ağustos','09'=>'Eylül','10'=>'Ekim','11'=>'Kasım','12'=>'Aralık'];
$today=(int)date('j'); $isThisMonth=($ym===date('Y-m'));
// Gün filtresi (mobile/calendar.php ile aynı ?g= deseni) — kullanıcı bildirimi: "bir güne
// tıklayınca sadece ilgili günü göstermeli" — önceden gün numarasının hiç linki yoktu, ay
// ızgarası zaten tüm günleri iç içe gösterdiği için tıklamanın hiçbir etkisi olmuyordu.
$g=(int)($_GET['g']??0);
require_once __DIR__.'/layout_top.php';
$__takvimActions = ds_button('‹ Önceki','takvim.php?ay='.h($prev),'secondary','','',true).ds_button('Sonraki ›','takvim.php?ay='.h($next),'secondary','','',true);
ds_page_header('Takvim — '.$mn[$first->format('m')].' '.$first->format('Y'), ds_icon('calendar',24), '', $__takvimActions, false, true);
?>
<section class="df-card df-calendar-card" style="margin-top:var(--df-space-4)">
<table class="df-calendar-table" style="table-layout:fixed">
<thead><tr><?php foreach(['Pzt','Sal','Çar','Per','Cum','Cmt','Paz'] as $w) echo "<th style='text-align:center'>$w</th>"; ?></tr></thead>
<tbody><tr>
<?php
$cell=0;
for($i=0;$i<$startW;$i++){ echo "<td></td>"; $cell++; }
for($d=1;$d<=$daysIn;$d++){
  if($cell%7===0 && $cell>0) echo "</tr><tr>";
  $isToday=($isThisMonth&&$d===$today); $isSel=($g===$d);
  echo "<td style='vertical-align:top;height:90px;padding:4px;".($isSel?'background:var(--df-ink-300)':($isToday?'background:var(--df-accent-soft)':''))."'>";
  echo "<a href='takvim.php?ay=".h($ym)."&g=$d' style='text-decoration:none;font-weight:700;display:block;".($isSel?'color:var(--df-surface)':($isToday?'color:var(--df-accent)':'color:var(--df-ink-500)'))."'>$d</a>";
  // Bir gün seçiliyken (g>0) ızgara SADECE seçili günün maddelerini gösterir — diğer günler için
  // sadece bir nokta rozeti (kaç madde olduğu) kalır. UX/STABILITY PATCH-002: kullanıcı "bir güne
  // basınca sadece o gün görünmeli, başka gün gösterilmeyecek" dedi — önceden ızgara HER günün
  // madde başlıklarını hep birlikte gösteriyordu, bu da "gün filtresi çalışmıyor" izlenimi
  // veriyordu (aslında sadece alttaki detay paneli filtreleniyordu, ızgara değil).
  if(!empty($byDay[$d])){
    if($g>0 && !$isSel){
      echo "<span style='display:block;text-align:center;margin-top:4px;font-size:10px;color:var(--df-warning-ink)'>●".(count($byDay[$d])>1?' '.count($byDay[$d]):'')."</span>";
    }else{
      foreach($byDay[$d] as $j){ $isNote=!empty($j['_note']); $isTask=!empty($j['_task']); $c=($isNote||$isTask)?'var(--df-warning)':(in_array($j['status'],['Tamamlandı','Teslim Edildi'])?'var(--df-success)':($j['status']==='İptal'?'var(--df-ink-300)':'var(--df-warning)'));
        $icon=$isNote?'📝 ':($isTask?'🎯 ':'');
        $itemStyle="display:block;font-size:11px;background:var(--df-warning-soft);border-left:3px solid $c;border-radius:4px;padding:2px 4px;margin-top:2px;color:var(--df-ink-900);text-decoration:none;white-space:nowrap;overflow:hidden;text-overflow:ellipsis";
        // Görev maddesi artık mytasks.php'ye gidiyor — 'tasks' yetkisi istemeyen kişisel görev
        // sayfası (bkz. mytasks.php), önceki "yetkisizse düz metin" geçici çözümüne gerek kalmadı.
        // REOPEN-001: not linki artık kendi gününe (?date=) filtrelenmiş notes.php'ye gidiyor —
        // önceden notes.php'ye tarih olmadan gidip TÜM açık notları gösteriyordu.
        $href=$isNote?('notes.php?date='.($j['due_date']??'')):($isTask?'task_view.php?id='.(int)$j['id']:'job_view.php?id='.(int)$j['id']);
        echo "<a href='".h($href)."' style='$itemStyle'>".$icon.h($j['title'])."</a>";
      }
    }
  }
  echo "</td>"; $cell++;
}
while($cell%7!==0){ echo "<td></td>"; $cell++; }
?>
</tr></tbody></table>
</section>
<?php if($g>0): ?>
<section class="df-card" style="margin-top:var(--df-space-4)">
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:var(--df-space-3)">
<h2 class="df-section-title" style="margin:0"><?=$g.' '.$mn[$first->format('m')]?> işleri/notları</h2>
<?=ds_button('✕ Kapat','takvim.php?ay='.h($ym),'secondary','','',true)?>
</div>
<?php if(empty($byDay[$g])): ?>
<?=ds_empty_state('Bu günde iş/görev/not yok.')?>
<?php else: foreach($byDay[$g] as $j):
  $isNote=!empty($j['_note']); $isTask=!empty($j['_task']);
  $icon=$isNote?'📝':($isTask?'🎯':'📋');
  // REOPEN-001: not linki kendi gününe (?date=) filtrelenmiş notes.php'ye gidiyor.
  $href=$isNote?('notes.php?date='.($j['due_date']??'')):($isTask?'task_view.php?id='.(int)$j['id']:'job_view.php?id='.(int)$j['id']);
?>
<a href="<?=h($href)?>" style="display:flex;justify-content:space-between;align-items:center;padding:10px 4px;border-bottom:1px solid var(--df-hairline);text-decoration:none;color:inherit">
<span><?=$icon?> <?=htmlspecialchars($j['title'])?></span>
<span class="df-muted" style="font-size:12px"><?=htmlspecialchars($j['status'])?></span>
</a>
<?php endforeach; endif; ?>
</section>
<?php endif; ?>

<style>
body.nav-compact .df-calendar-table{width:100%;border-collapse:collapse}
body.nav-compact .df-calendar-table th{padding:6px;color:var(--df-ink-500);font-size:12px;font-weight:700}
body.nav-compact .df-calendar-table td{border:1px solid var(--df-hairline)}
body.nav-compact .df-section-title{font-size:var(--df-type-section-size);font-weight:var(--df-type-section-weight);color:var(--df-ink-900)}
</style>

<?php require_once __DIR__.'/layout_bottom.php'; ?>
