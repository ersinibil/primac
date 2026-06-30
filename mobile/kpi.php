<?php
require_once 'common.php';
block_personel(); // sadece yönetici
$pdo=db();
topx('Performans');
?>
<div class="panel" style="padding:12px"><b>👷 Personel Performans</b><p class="small" style="margin:4px 0 0">İş teslim oranı, görev tamamlama ve geciken işlerden hesaplanır.</p></div>
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

  $rank=0;
  foreach($rows as $r){
    $rank++;
    $sc=$r['score'];
    $col = $sc>=75?'#22c55e':($sc>=50?'#eab308':($r['aktif']?'#f87171':'#475569'));
    $medal = $rank===1?'🥇':($rank===2?'🥈':($rank===3?'🥉':''));
    echo '<div class="panel" style="padding:13px">';
    echo '<div style="display:flex;align-items:center;gap:10px">';
    echo '<div class="av" style="width:42px;height:42px;border-radius:50%;background:'.$col.';display:flex;align-items:center;justify-content:center;font-weight:900;color:#06281a">'.($r['aktif']?$sc:'–').'</div>';
    echo '<div style="flex:1;min-width:0"><b>'.$medal.' '.htmlspecialchars($r['name']).'</b><br><small class="muted">'.htmlspecialchars($r['role']?:'Personel').'</small></div>';
    echo '</div>';
    // puan barı
    echo '<div style="height:8px;background:rgba(255,255,255,.1);border-radius:6px;margin:10px 0;overflow:hidden"><div style="height:100%;width:'.$sc.'%;background:'.$col.'"></div></div>';
    echo '<div style="display:flex;gap:6px;flex-wrap:wrap;font-size:12px">';
    echo '<span style="background:rgba(255,255,255,.08);border-radius:8px;padding:4px 8px">📋 İş: <b>'.$r['is_top'].'</b></span>';
    echo '<span style="background:rgba(34,197,94,.15);color:#86efac;border-radius:8px;padding:4px 8px">✓ Tamam: <b>'.$r['is_tamam'].'</b></span>';
    echo '<span style="background:rgba(255,255,255,.08);border-radius:8px;padding:4px 8px">▶ Açık: <b>'.$r['is_acik'].'</b></span>';
    if($r['is_geciken']>0) echo '<span style="background:rgba(248,113,113,.2);color:#fca5a5;border-radius:8px;padding:4px 8px">⏰ Geciken: <b>'.$r['is_geciken'].'</b></span>';
    echo '<span style="background:rgba(255,255,255,.08);border-radius:8px;padding:4px 8px">🎯 Görev: <b>'.$r['gv_tamam'].'/'.$r['gv_top'].'</b></span>';
    echo '<span style="background:rgba(255,255,255,.08);border-radius:8px;padding:4px 8px">Teslim: <b>%'.$r['isOran'].'</b></span>';
    echo '</div></div>';
  }
  if(!$rows) echo '<div class="panel muted" style="text-align:center">Personel yok.</div>';
}catch(Throwable $e){ echo '<div class="err">'.htmlspecialchars($e->getMessage()).'</div>'; }
botx();
