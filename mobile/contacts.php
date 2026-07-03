<?php
require_once 'common.php';

// active kolonu güvencesi
try{
    db()->exec("ALTER TABLE contacts ADD COLUMN active TINYINT(1) NOT NULL DEFAULT 1");
}catch(Throwable $e){}

topx('Cariler');

$showPassive=!empty($_GET['show_passive']);

echo '<div class="panel" style="display:flex;gap:8px;flex-wrap:wrap">
  <a class="btn dark" href="contact_new.php">+ Yeni Cari</a>
  <a class="btn" href="collection.php">+ Tahsilat</a>
  <a class="btn" href="sales.php">+ Satış</a>
  <a class="btn" href="contacts_report.php">📊 Cari Raporlar</a>
  <a class="btn" href="contacts.php?show_passive='.($showPassive?'0':'1').'">'.($showPassive?'Sadece Aktif':'Pasif Dahil').'</a>
</div>';

try{
    $sql = $showPassive
        ? "SELECT id,name,type,phone,active FROM contacts ORDER BY name LIMIT 100"
        : "SELECT id,name,type,phone,active FROM contacts WHERE (active IS NULL OR active=1) ORDER BY name LIMIT 100";
    $rows=db()->query($sql)->fetchAll();
    foreach($rows as $r){
        $isPassive=isset($r['active']) && (int)$r['active']===0;
        $style = $isPassive ? ' style="opacity:.45"' : '';
        $badge = $isPassive ? ' <span style="background:#f1f5f9;color:#64748b;border-radius:999px;padding:2px 7px;font-size:11px;font-weight:900">Pasif</span>' : '';
        echo '<a class="item" href="contact_view.php?id='.(int)$r['id'].'"'.$style.'>'
            .'<b>'.htmlspecialchars($r['name']).$badge.'</b><br>'
            .'<small>'.htmlspecialchars($r['type']).' · '.htmlspecialchars($r['phone']??'').'</small>'
            .'</a>';
    }
}catch(Throwable $e){
    echo '<div class="err">'.htmlspecialchars($e->getMessage()).'</div>';
}

botx();
