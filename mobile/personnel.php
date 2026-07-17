<?php
require_once 'common.php';
block_personel('personnel');
$pdo=db();
topx('Personel');
?>
<div style="display:flex;gap:8px;margin-bottom:var(--df-space-3)">
<?=ds_button('Yeni Personel','personnel_new.php','primary','','style="flex:1;justify-content:center"',true)?>
<?=ds_button(ds_icon('calendar',15).' İş Ekle','task_new.php','secondary','','style="flex:1;justify-content:center"',true)?>
</div>
<?php
try{
  $rows=$pdo->query("SELECT p.*,
    (SELECT COUNT(*) FROM tasks t WHERE t.personnel_id=p.id AND t.status NOT IN ('Tamamlandı','İptal')) acik_gorev,
    (SELECT COUNT(*) FROM jobs j WHERE j.responsible_personnel_id=p.id AND j.status NOT IN ('Tamamlandı','İptal','Teslim Edildi')) acik_is
    FROM personnel p ORDER BY COALESCE(p.active,1) DESC, p.name")->fetchAll();
  if(!$rows){
    ds_empty_state('Henüz personel yok.', 'Yeni Personel butonuyla ilk personeli ekleyebilirsiniz.', ds_icon('users',32));
  } else {
    echo '<div class="df-list">';
    foreach($rows as $r){
        $pasif=!((int)($r['active']??1));
        $title=h($r['name']).($r['role']?' <span style="color:var(--df-ink-500);font-weight:400">· '.h($r['role']).'</span>':'');
        if($pasif) $title.=' '.ds_badge('Pasif','red');
        $meta='Açık iş: '.(int)$r['acik_is'].' · Açık görev: '.(int)$r['acik_gorev'];
        if($r['phone']) $meta.=' · '.h($r['phone']);
        echo ds_list_item($title, 'personnel_view.php?id='.(int)$r['id'], h($meta));
    }
    echo '</div>';
  }
}catch(Throwable $e){ echo ds_alert('danger',$e->getMessage()); }
botx();
