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
  'tumu'    => "1",
  'atanmamis'=> "(j.responsible_personnel_id IS NULL OR j.responsible_personnel_id=0)",
];
$where=[]; $params=[];
if(!$isAdmin){ $where[]='j.responsible_personnel_id=?'; $params[]=$myPid?:-1; } // personel sadece kendi işleri
$where[]=$statusMap[$s] ?? $statusMap['aktif'];
$sql="SELECT j.*, c.name customer, p.name responsible FROM jobs j LEFT JOIN contacts c ON c.id=j.customer_id LEFT JOIN personnel p ON p.id=j.responsible_personnel_id WHERE ".implode(' AND ',$where).' ORDER BY j.id DESC LIMIT 120';

topx('İş Emirleri');
$tones=['Yeni'=>'#3b82f6','Devam Ediyor'=>'#a855f7','Bekliyor'=>'#eab308','Tamamlandı'=>'#22c55e','Teslim Edildi'=>'#22c55e','İptal'=>'#94a3b8'];
$tabs=['aktif'=>'Aktif','bekleyen'=>'Bekleyen','devam'=>'Devam Eden','tamam'=>'Tamamlanan','iptal'=>'İptal','tumu'=>'Tümü'];
if($isAdmin) $tabs['atanmamis']='Atanmamış';
?>
<div class="panel" style="padding:10px;display:flex;gap:8px"><a class="btn dark" style="flex:1;text-align:center" href="job_new.php">+ Yeni İş</a><a class="btn" style="flex:0 0 auto;background:#334155;color:#fff" href="calendar.php">📅</a><a class="btn" style="flex:0 0 auto;background:#334155;color:#fff" href="report.php?modul=is">📊</a></div>
<div style="display:flex;gap:6px;overflow:auto;margin-bottom:10px;-webkit-overflow-scrolling:touch">
  <?php foreach($tabs as $k=>$v): ?>
    <a class="btn" style="white-space:nowrap;padding:8px 13px;<?=$s===$k?'background:#2563eb;color:#fff':'background:#334155;color:#cbd5e1'?>" href="jobs.php?s=<?=$k?>"><?=$v?></a>
  <?php endforeach; ?>
</div>
<?php
try{
  $st=$pdo->prepare($sql); $st->execute($params); $rows=$st->fetchAll();
  if(!$rows) echo '<div class="panel muted" style="text-align:center">Bu kategoride iş yok.</div>';
  foreach($rows as $r){ $col=$tones[$r['status']]??'#94a3b8'; $aktif=!in_array($r['status'],['Tamamlandı','Teslim Edildi','İptal']);
    echo '<div class="panel" style="padding:12px">';
    echo '<a href="job_view.php?id='.(int)$r['id'].'" style="text-decoration:none;color:#fff;display:block">';
    echo '<div style="display:flex;justify-content:space-between;gap:8px"><b>'.htmlspecialchars($r['title']).'</b><span style="color:'.$col.';font-weight:900;font-size:12px;white-space:nowrap">●'.htmlspecialchars($r['status']).'</span></div>';
    echo '<small class="muted">'.htmlspecialchars($r['job_no']??'').($r['customer']?' · 👤 '.htmlspecialchars($r['customer']):'').($r['responsible']?' · 👷 '.htmlspecialchars($r['responsible']):'').($r['due_date']?' · 📅 '.htmlspecialchars($r['due_date']):'').'</small>';
    echo '</a>';
    // Hızlı durum aksiyonları
    if($aktif){
      echo '<div style="display:flex;gap:6px;margin-top:10px;flex-wrap:wrap">';
      if($r['status']!=='Devam Ediyor') echo '<form method="post" style="flex:1;margin:0"><input type="hidden" name="jid" value="'.(int)$r['id'].'"><button class="btn" name="set" value="Devam Ediyor" style="width:100%;background:#2563eb;color:#fff;padding:9px">▶ Başlat</button></form>';
      echo '<form method="post" style="flex:1;margin:0"><input type="hidden" name="jid" value="'.(int)$r['id'].'"><button class="btn" name="set" value="Tamamlandı" style="width:100%;background:#16a34a;color:#fff;padding:9px">✓ Tamamla</button></form>';
      echo '<form method="post" style="flex:1;margin:0"><input type="hidden" name="jid" value="'.(int)$r['id'].'"><button class="btn" name="set" value="İptal" style="width:100%;background:#7f1d1d;color:#fff;padding:9px" onclick="return confirm(\'İptal edilsin mi?\')">✕</button></form>';
      echo '</div>';
    } elseif($r['status']==='Tamamlandı'){
      echo '<div style="display:flex;gap:6px;margin-top:10px"><form method="post" style="flex:1;margin:0"><input type="hidden" name="jid" value="'.(int)$r['id'].'"><button class="btn" name="set" value="Teslim Edildi" style="width:100%;background:#334155;color:#fff;padding:9px">📦 Teslim / Kapat</button></form></div>';
    }
    echo '</div>';
  }
}catch(Throwable $e){ echo '<div class="err">'.htmlspecialchars($e->getMessage()).'</div>'; }
botx();
