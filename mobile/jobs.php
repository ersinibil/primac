<?php
require_once 'common.php';
$pdo=db(); $me=(int)($_SESSION['user']['id']??0);
// Personel id (oturumdan; yoksa app_users'tan)
$myPid=(int)($_SESSION['user']['personnel_id'] ?? 0);
if(!$myPid && !$isAdmin){ try{ $r=$pdo->prepare("SELECT personnel_id FROM app_users WHERE id=?"); $r->execute([$me]); $myPid=(int)($r->fetch()['personnel_id']??0); }catch(Throwable $e){} }

// Hızlı durum güncelle (liste içinden) — çıktıdan önce
if($_SERVER['REQUEST_METHOD']==='POST' && (int)($_POST['jid']??0) && !empty($_POST['set'])){
    try{
        $pdo->prepare("UPDATE jobs SET status=? WHERE id=?")->execute([$_POST['set'],(int)$_POST['jid']]);
        try{ if(function_exists('activity_log')) activity_log('İş','Durum',$_POST['set'],'','job',(int)$_POST['jid'],'job_view.php?id='.(int)$_POST['jid'],'🔄'); }catch(Throwable $e){}
    }catch(Throwable $e){}
    header('Location: jobs.php?s='.urlencode($_GET['s']??'aktif')); exit;
}

$s=$_GET['s'] ?? 'aktif';
$statusMap=[
  'aktif'   => "j.status NOT IN ('Tamamlandı','Teslim Edildi','İptal')",
  'bekleyen'=> "j.status IN ('Yeni','Bekliyor')",
  'devam'   => "j.status='Devam Ediyor'",
  'tamam'   => "j.status IN ('Tamamlandı','Teslim Edildi')",
  'iptal'   => "j.status='İptal'",
  // Web jobs.php ile parite (2026-07-14, dashboard filtre linki düzeltmesi) — aynı CURDATE() mantığı.
  'gec'     => "j.due_date IS NOT NULL AND j.due_date<CURDATE() AND j.status NOT IN ('Tamamlandı','Teslim Edildi','İptal')",
  'bugun'   => "j.due_date IS NOT NULL AND j.due_date=CURDATE() AND j.status NOT IN ('Tamamlandı','Teslim Edildi','İptal')",
  'tumu'    => "1",
  'atanmamis'=> "(j.responsible_personnel_id IS NULL OR j.responsible_personnel_id=0)",
];
$where=[]; $params=[];
if(!$isAdmin){ $where[]='j.responsible_personnel_id=?'; $params[]=$myPid?:-1; } // personel sadece kendi işleri
$where[]=$statusMap[$s] ?? $statusMap['aktif'];
$sql="SELECT j.*, c.name customer, p.name responsible FROM jobs j LEFT JOIN contacts c ON c.id=j.customer_id LEFT JOIN personnel p ON p.id=j.responsible_personnel_id WHERE ".implode(' AND ',$where).' ORDER BY j.id DESC LIMIT 120';

topx('İş Emirleri');
$tabs=['aktif'=>'Aktif','bekleyen'=>'Bekleyen','devam'=>'Devam Eden','tamam'=>'Tamamlanan','iptal'=>'İptal','gec'=>'Geciken','bugun'=>'Bugün Teslim','tumu'=>'Tümü'];
if($isAdmin) $tabs['atanmamis']='Atanmamış';
?>
<div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:10px">
  <?=ds_button(ds_icon('plus',15).' Yeni İş','job_new.php','primary','','style="flex:1;justify-content:center;min-width:120px"',true)?>
  <?=ds_button(ds_icon('calendar',15).' Takvim','calendar.php','secondary','','style="flex:1;justify-content:center;min-width:100px"',true)?>
  <?=ds_button('Rapor','report.php?modul=is','secondary','','style="flex:1;justify-content:center;min-width:100px"',true)?>
</div>
<div class="df-tabs" style="overflow:auto;max-width:100%;-webkit-overflow-scrolling:touch;margin-bottom:14px">
  <?php foreach($tabs as $k=>$v): ?>
    <a class="df-tab<?=$s===$k?' df-tab--active':''?>" href="jobs.php?s=<?=h($k)?>"><?=h($v)?></a>
  <?php endforeach; ?>
</div>
<?php
try{
  $st=$pdo->prepare($sql); $st->execute($params); $rows=$st->fetchAll();
  if(!$rows) ds_empty_state('Bu kategoride iş yok.', null, ds_icon('briefcase',20));
  foreach($rows as $r){ $aktif=!in_array($r['status'],['Tamamlandı','Teslim Edildi','İptal']);
    echo '<div class="df-panel" style="margin-top:10px">';
    echo '<a href="job_view.php?id='.(int)$r['id'].'" style="text-decoration:none;color:inherit;display:block">';
    echo '<div style="display:flex;justify-content:space-between;gap:8px;align-items:flex-start">';
    echo '<div class="df-list-row-title" style="flex:1;min-width:0">'.h($r['title']).'</div>';
    echo ds_badge($r['status']);
    echo '</div>';
    echo '<div class="df-list-row-meta" style="margin-top:6px">';
    if(!empty($r['job_no'])) echo '<span>'.h($r['job_no']).'</span>';
    if(!empty($r['customer'])) echo '<span>'.ds_icon('user',13).' '.h($r['customer']).'</span>';
    if(!empty($r['responsible'])) echo '<span>'.ds_icon('users',13).' '.h($r['responsible']).'</span>';
    if(!empty($r['due_date'])) echo '<span class="df-list-row-due">'.ds_icon('calendar',13).' '.h($r['due_date']).'</span>';
    echo '</div>';
    echo '</a>';
    // Hızlı durum aksiyonları
    if($aktif){
      echo '<div style="display:flex;gap:6px;margin-top:10px;flex-wrap:wrap">';
      if($r['status']!=='Devam Ediyor') echo '<form method="post" style="flex:1;margin:0"><input type="hidden" name="jid" value="'.(int)$r['id'].'"><button type="submit" class="df-btn df-btn--secondary" name="set" value="Devam Ediyor" style="width:100%;justify-content:center">'.ds_icon('check',14).' Başlat</button></form>';
      echo '<form method="post" style="flex:1;margin:0"><input type="hidden" name="jid" value="'.(int)$r['id'].'"><button type="submit" class="df-btn df-btn--primary" name="set" value="Tamamlandı" style="width:100%;justify-content:center">'.ds_icon('check',14).' Tamamla</button></form>';
      echo '<form method="post" style="flex:1;margin:0" onsubmit="return confirm(\'İptal edilsin mi?\')"><input type="hidden" name="jid" value="'.(int)$r['id'].'"><button type="submit" class="df-btn df-btn--danger" name="set" value="İptal" style="width:100%;justify-content:center">'.ds_icon('close',14).'</button></form>';
      echo '</div>';
    } elseif($r['status']==='Tamamlandı'){
      echo '<div style="margin-top:10px"><form method="post" style="margin:0"><input type="hidden" name="jid" value="'.(int)$r['id'].'"><button type="submit" class="df-btn df-btn--secondary" name="set" value="Teslim Edildi" style="width:100%;justify-content:center">'.ds_icon('box',14).' Teslim / Kapat</button></form></div>';
    }
    echo '</div>';
  }
}catch(Throwable $e){ echo ds_alert('danger',$e->getMessage()); }
botx();
