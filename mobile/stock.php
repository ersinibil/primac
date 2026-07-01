<?php
require_once 'common.php';
$pdo=db();
$pasifDahil=isset($_GET['pasif_dahil']);
topx('Stok');
echo '<div class="panel" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">';
echo '<a class="btn dark" href="product_new.php">+ Yeni Ürün</a>';
echo '<a class="btn" href="report.php?modul=stok" style="background:#334155;color:#fff">📊 Rapor</a>';
echo '<form method="get" style="margin:0;display:flex;align-items:center;gap:6px"><label style="font-size:13px;color:#94a3b8;display:flex;align-items:center;gap:4px"><input type="checkbox" name="pasif_dahil" value="1"'.($pasifDahil?' checked':'').' onchange="this.form.submit()" style="width:auto"> Pasif Dahil</label></form>';
echo '</div>';
try{
    $sql="SELECT id,name,quantity,unit,sale_price,critical_level,active FROM stock_items";
    if(!$pasifDahil) $sql.=" WHERE (active IS NULL OR active=1)";
    $sql.=" ORDER BY name LIMIT 200";
    $rows=$pdo->query($sql)->fetchAll();
    foreach($rows as $r){
        $kr=($r['quantity']<=($r['critical_level']??0));
        $isPasif=isset($r['active']) && !$r['active'];
        $style=$isPasif?' style="opacity:.45"':'';
        echo '<a class="item" href="product_view.php?id='.(int)$r['id'].'"'.$style.'>';
        echo '<b>'.htmlspecialchars($r['name']).'</b>';
        if($isPasif) echo ' <span style="font-size:11px;background:#ef4444;color:#fff;border-radius:6px;padding:1px 6px">Pasif</span>';
        if($kr) echo ' <span style="color:#f87171;font-size:12px;font-weight:900">⚠️ kritik</span>';
        echo '<br><small>Stok: '.htmlspecialchars($r['quantity'].' '.$r['unit']).' · Satış: '.mm($r['sale_price']??0).'</small>';
        echo '</a>';
    }
}catch(Throwable $e){ echo '<div class="err">'.htmlspecialchars($e->getMessage()).'</div>'; }
botx();
?>