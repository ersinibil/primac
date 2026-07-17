<?php
require_once 'common.php';

// active kolonu güvencesi
try{
    db()->exec("ALTER TABLE contacts ADD COLUMN active TINYINT(1) NOT NULL DEFAULT 1");
}catch(Throwable $e){}

topx('Cariler');

$showPassive=!empty($_GET['show_passive']);

echo '<div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:8px">'
    .ds_button(ds_icon('plus',15).' Yeni Cari','contact_new.php','primary','','style="flex:1;justify-content:center;min-width:110px"',true)
    .ds_button('Tahsilat','collection.php','secondary','','style="flex:1;justify-content:center;min-width:90px"',true)
    .ds_button('Satış','sales.php','secondary','','style="flex:1;justify-content:center;min-width:90px"',true)
    .ds_button('Cari Raporlar','contacts_report.php','secondary','','style="flex:1;justify-content:center;min-width:110px"',true)
    .'</div>';
echo '<div style="margin-bottom:12px">'.ds_button($showPassive?'Sadece Aktif':'Pasif Dahil','contacts.php?show_passive='.($showPassive?'0':'1'),'ghost','df-btn--sm','',true).'</div>';

try{
    $sql = $showPassive
        ? "SELECT id,name,type,phone,active FROM contacts ORDER BY name LIMIT 100"
        : "SELECT id,name,type,phone,active FROM contacts WHERE (active IS NULL OR active=1) ORDER BY name LIMIT 100";
    $rows=db()->query($sql)->fetchAll();
    if(!$rows) ds_empty_state('Kayıtlı cari yok.', null, ds_icon('users',20));
    foreach($rows as $r){
        $isPassive=isset($r['active']) && (int)$r['active']===0;
        echo '<a href="contact_view.php?id='.(int)$r['id'].'" class="df-panel" style="display:block;margin-top:10px;text-decoration:none;color:inherit'.($isPassive?';opacity:.55':'').'">';
        echo '<div style="display:flex;justify-content:space-between;gap:8px;align-items:center">';
        echo '<div class="df-list-row-title">'.h($r['name']).'</div>';
        if($isPassive) echo ds_badge('Pasif','gray');
        echo '</div>';
        echo '<div class="df-list-row-meta" style="margin-top:4px"><span>'.h($r['type']).'</span>'.(!empty($r['phone'])?'<span>'.ds_icon('phone',13).' '.h($r['phone']).'</span>':'').'</div>';
        echo '</a>';
    }
}catch(Throwable $e){
    echo ds_alert('danger',$e->getMessage());
}

botx();
