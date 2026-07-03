<?php
require_once 'common.php';
block_personel('personnel');
$pdo=db();
topx('Personel');
?>
<div class="panel" style="padding:10px;display:flex;gap:8px"><a class="btn dark" style="flex:1;text-align:center" href="personnel_new.php">+ Yeni Personel</a><a class="btn" style="flex:1;text-align:center;background:#334155;color:#fff" href="task_new.php">🎯 Görev Ata</a></div>
<?php
try{
  $rows=$pdo->query("SELECT p.*,
    (SELECT COUNT(*) FROM tasks t WHERE t.personnel_id=p.id AND t.status NOT IN ('Tamamlandı','İptal')) acik_gorev,
    (SELECT COUNT(*) FROM jobs j WHERE j.responsible_personnel_id=p.id AND j.status NOT IN ('Tamamlandı','İptal','Teslim Edildi')) acik_is
    FROM personnel p ORDER BY COALESCE(p.active,1) DESC, p.name")->fetchAll();
  if(!$rows) echo '<div class="panel muted">Personel yok.</div>';
  foreach($rows as $r){ $pasif=!((int)($r['active']??1));
    echo '<a class="item" href="personnel_view.php?id='.(int)$r['id'].'"'.($pasif?' style="opacity:.55"':'').'><b>'.htmlspecialchars($r['name']).'</b>'.($r['role']?' <span class="muted">· '.htmlspecialchars($r['role']).'</span>':'').($pasif?' <span style="color:#f87171;font-size:11px">PASİF</span>':'');
    echo '<br><small>📋 Açık iş: '.(int)$r['acik_is'].' · ✅ Açık görev: '.(int)$r['acik_gorev'].($r['phone']?' · 📞 '.htmlspecialchars($r['phone']):'').'</small></a>';
  }
}catch(Throwable $e){ echo '<div class="err">'.htmlspecialchars($e->getMessage()).'</div>'; }
botx();
