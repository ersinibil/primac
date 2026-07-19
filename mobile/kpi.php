<?php
require_once 'common.php';
block_personel('personnel'); // 2026-07-03: kullanıcı onayı verildi — 'personnel' yetkisi olan da girebilir
$pdo=db();
topx('Performans');
?>
<div class="df-panel"><b><?=ds_icon('users',16)?> Personel Performans</b><p class="small" style="margin:4px 0 0">İş teslim oranı, görev tamamlama ve geciken işlerden hesaplanır.</p></div>
<?php
try{
  $rows=$pdo->query("SELECT p.id,p.name,p.role,
    (SELECT COUNT(*) FROM jobs j WHERE j.responsible_personnel_id=p.id) is_top,
    (SELECT COUNT(*) FROM jobs j WHERE j.responsible_personnel_id=p.id AND j.status IN ('Tamamlandı','Teslim Edildi')) is_tamam,
    (SELECT COUNT(*) FROM jobs j WHERE j.responsible_personnel_id=p.id AND j.status NOT IN ('Tamamlandı','Teslim Edildi','İptal')) is_acik,
    (SELECT COUNT(*) FROM jobs j WHERE j.responsible_personnel_id=p.id AND j.status NOT IN ('Tamamlandı','Teslim Edildi','İptal') AND j.due_date IS NOT NULL AND j.due_date < CURDATE()) is_geciken,
    (SELECT COUNT(*) FROM tasks t WHERE t.personnel_id=p.id) gv_top,
    (SELECT COUNT(*) FROM tasks t WHERE t.personnel_id=p.id AND t.status='Tamamlandı') gv_tamam
    FROM personnel p WHERE COALESCE(p.active,1)=1")->fetchAll();

  // Puan hesabı
  foreach($rows as &$r){
    $isOran = $r['is_top']>0 ? ($r['is_tamam']/$r['is_top']*100) : 0;
    $gvOran = $r['gv_top']>0 ? ($r['gv_tamam']/$r['gv_top']*100) : 0;
    $aktif = ($r['is_top']>0 || $r['gv_top']>0);
    // İş yoksa puan 0 (değerlendirme dışı); varsa: %50 teslim + %50 görev - geciken cezası
    $score = $aktif ? max(0, min(100, round(0.5*$isOran + 0.5*$gvOran - $r['is_geciken']*8))) : 0;
    $r['isOran']=round($isOran); $r['gvOran']=round($gvOran); $r['score']=$score; $r['aktif']=$aktif;
  }
  unset($r);
  usort($rows,function($a,$b){ return $b['score']<=>$a['score']; });

  if(!$rows) ds_empty_state('Personel yok.', null, ds_icon('users',20));

  $rank=0;
  foreach($rows as $r){
    $rank++;
    $sc=$r['score'];
    $col = $sc>=75?'var(--df-success-ink)':($sc>=50?'var(--df-warning-ink)':($r['aktif']?'var(--df-danger-ink)':'var(--df-ink-500)'));
    $medal = $rank===1?'🥇':($rank===2?'🥈':($rank===3?'🥉':''));
    echo '<div class="df-panel" style="margin-top:10px">';
    echo '<div style="display:flex;align-items:center;gap:10px">';
    echo '<div style="width:42px;height:42px;border-radius:50%;background:'.$col.';display:flex;align-items:center;justify-content:center;font-weight:900;color:#06281a;flex:0 0 auto">'.($r['aktif']?$sc:'–').'</div>';
    echo '<div style="flex:1;min-width:0"><b>'.$medal.' '.h($r['name']).'</b><br><small class="muted">'.h($r['role']?:'Personel').'</small></div>';
    echo '</div>';
    // puan barı
    echo '<div style="height:8px;background:var(--df-surface-sunken,rgba(255,255,255,.1));border-radius:6px;margin:10px 0;overflow:hidden"><div style="height:100%;width:'.$sc.'%;background:'.$col.'"></div></div>';
    echo '<div class="df-list-row-meta">';
    echo '<span class="df-badge df-badge--info">'.ds_icon('briefcase',12).' İş: <b>'.$r['is_top'].'</b></span>';
    echo '<span class="df-badge df-badge--success">'.ds_icon('check',12).' Tamam: <b>'.$r['is_tamam'].'</b></span>';
    echo '<span class="df-badge df-badge--info">▶ Açık: <b>'.$r['is_acik'].'</b></span>';
    if($r['is_geciken']>0) echo '<span class="df-badge df-badge--danger">⏰ Geciken: <b>'.$r['is_geciken'].'</b></span>';
    echo '<span class="df-badge df-badge--info">🎯 Görev: <b>'.$r['gv_tamam'].'/'.$r['gv_top'].'</b></span>';
    echo '<span class="df-badge df-badge--info">Teslim: <b>%'.$r['isOran'].'</b></span>';
    echo '</div></div>';
  }
}catch(Throwable $e){ echo ds_alert('danger',$e->getMessage()); }
botx();
