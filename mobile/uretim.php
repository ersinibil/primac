<?php
require_once 'common.php';
require_once __DIR__.'/../job_stages_lib.php';
$pdo=db(); $me=(int)($_SESSION['user']['id']??0);
$myPid=(int)($_SESSION['user']['personnel_id']??0);
if(!$myPid && !$isAdmin){ try{ $r=$pdo->prepare("SELECT personnel_id FROM app_users WHERE id=?"); $r->execute([$me]); $myPid=(int)($r->fetch()['personnel_id']??0); }catch(Throwable $e){} }
topx('Üretim');
?>
<div class="df-panel">
  <b><?=ds_icon('box',16)?> Üretim</b>
  <p class="small" style="margin:4px 0 10px">Üretim emri oluştur, aşamaları (Tasarım→Üretim→Kontrol→Teslim) takip et.</p>
  <div style="display:flex;gap:8px;flex-wrap:wrap">
    <a class="df-btn df-btn--primary" style="flex:1;justify-content:center" href="uretim_new.php"><?=ds_icon('box',14)?> Yeni Üretim Emri</a>
    <a class="df-btn df-btn--secondary" style="flex:1;justify-content:center" href="product_new.php"><?=ds_icon('plus',14)?> Yeni Ürün</a>
  </div>
</div>
<div style="font-weight:900;margin:14px 4px 6px">Aktif Üretim İşleri</div>
<?php
try{
  $w="j.status NOT IN ('Tamamlandı','Teslim Edildi','İptal')";
  $params=[];
  if(!$isAdmin){ $w.=" AND j.responsible_personnel_id=?"; $params[]=$myPid?:-1; }
  $jobs=$pdo->prepare("SELECT j.id,j.title,j.job_no,j.job_type,j.status,c.name customer,p.name responsible FROM jobs j LEFT JOIN contacts c ON c.id=j.customer_id LEFT JOIN personnel p ON p.id=j.responsible_personnel_id WHERE $w ORDER BY j.id DESC LIMIT 120");
  $jobs->execute($params); $rows=$jobs->fetchAll();
  if(!$rows) ds_empty_state('Aktif iş yok.', null, ds_icon('briefcase',20));
  foreach($rows as $r){
    $stages=get_stages($pdo,$r['id']); list($d,$t,$pct,$cur)=stage_progress($stages);
    $col=$pct>=100?'var(--df-success-ink)':($pct>0?'var(--df-warning-ink)':'var(--df-ink-500)');
    echo '<a href="job_view.php?id='.(int)$r['id'].'" class="df-panel" style="display:block;text-decoration:none;color:inherit;margin-top:10px">';
    echo '<div style="display:flex;justify-content:space-between;gap:8px"><b>'.h($r['title']).'</b><span class="muted" style="font-size:12px">'.h($r['job_no']??'').'</span></div>';
    echo '<small class="muted">'.($r['customer']?ds_icon('user',12).' '.h($r['customer']).' · ':'').($r['responsible']?ds_icon('users',12).' '.h($r['responsible']):'').'</small>';
    if($t>0){
      echo '<div style="height:9px;background:rgba(255,255,255,.1);border-radius:6px;overflow:hidden;margin:8px 0 4px"><div style="height:100%;width:'.$pct.'%;background:'.$col.'"></div></div>';
      echo '<small style="color:'.$col.';font-weight:700">'.$d.'/'.$t.' aşama · Şu an: '.h($cur).' (%'.$pct.')</small>';
    } else {
      echo '<div style="margin-top:6px"><small class="muted">Aşama tanımlı değil — işe girip oluştur</small></div>';
    }
    echo '</a>';
  }
}catch(Throwable $e){ echo ds_alert('danger',$e->getMessage()); }
botx();
