<?php
require_once 'common.php';
require_once __DIR__.'/../job_stages_lib.php';
$pdo=db(); $me=(int)($_SESSION['user']['id']??0);
$myPid=(int)($_SESSION['user']['personnel_id']??0);
if(!$myPid && !$isAdmin){ try{ $r=$pdo->prepare("SELECT personnel_id FROM app_users WHERE id=?"); $r->execute([$me]); $myPid=(int)($r->fetch()['personnel_id']??0); }catch(Throwable $e){} }
topx('Üretim');
?>
<div class="panel" style="padding:12px">
  <b>🏭 Üretim</b>
  <p class="small" style="margin:4px 0 10px">Üretim emri oluştur, aşamaları (Tasarım→Üretim→Kontrol→Teslim) takip et.</p>
  <div style="display:flex;gap:8px;flex-wrap:wrap">
    <a class="btn dark" style="flex:1;text-align:center" href="uretim_new.php">🏭 Yeni Üretim Emri</a>
    <a class="btn" style="flex:1;text-align:center;background:#16a34a;color:#fff" href="product_new.php">+ Yeni Ürün</a>
  </div>
</div>
<div style="font-weight:900;margin:6px 4px">Aktif Üretim İşleri</div>
<?php
try{
  $w="j.status NOT IN ('Tamamlandı','Teslim Edildi','İptal')";
  $params=[];
  if(!$isAdmin){ $w.=" AND j.responsible_personnel_id=?"; $params[]=$myPid?:-1; }
  $jobs=$pdo->prepare("SELECT j.id,j.title,j.job_no,j.job_type,j.status,c.name customer,p.name responsible FROM jobs j LEFT JOIN contacts c ON c.id=j.customer_id LEFT JOIN personnel p ON p.id=j.responsible_personnel_id WHERE $w ORDER BY j.id DESC LIMIT 120");
  $jobs->execute($params); $rows=$jobs->fetchAll();
  if(!$rows) echo '<div class="panel muted" style="text-align:center">Aktif iş yok.</div>';
  foreach($rows as $r){
    $stages=get_stages($pdo,$r['id']); list($d,$t,$pct,$cur)=stage_progress($stages);
    $col=$pct>=100?'#22c55e':($pct>0?'#eab308':'#64748b');
    echo '<a class="panel" href="job_view.php?id='.(int)$r['id'].'" style="display:block;text-decoration:none;color:#fff;padding:12px">';
    echo '<div style="display:flex;justify-content:space-between;gap:8px"><b>'.htmlspecialchars($r['title']).'</b><span class="muted" style="font-size:12px">'.htmlspecialchars($r['job_no']??'').'</span></div>';
    echo '<small class="muted">'.($r['customer']?'👤 '.htmlspecialchars($r['customer']).' · ':'').($r['responsible']?'👷 '.htmlspecialchars($r['responsible']):'').'</small>';
    if($t>0){
      echo '<div style="height:9px;background:rgba(255,255,255,.1);border-radius:6px;overflow:hidden;margin:8px 0 4px"><div style="height:100%;width:'.$pct.'%;background:'.$col.'"></div></div>';
      echo '<small style="color:'.$col.';font-weight:700">'.$d.'/'.$t.' aşama · Şu an: '.htmlspecialchars($cur).' (%'.$pct.')</small>';
    } else {
      echo '<div style="margin-top:6px"><small style="color:#94a3b8">Aşama tanımlı değil — işe girip oluştur</small></div>';
    }
    echo '</a>';
  }
}catch(Throwable $e){ echo '<div class="err">'.htmlspecialchars($e->getMessage()).'</div>'; }
botx();
