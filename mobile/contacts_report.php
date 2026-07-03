<?php
require_once 'common.php';

$mode = $_GET['mode'] ?? '';
$type = $_GET['type'] ?? '';

$where=[]; $params=[];
if($type){ $where[]="c.type=?"; $params[]=$type; }
$sqlWhere = $where ? 'WHERE '.implode(' AND ',$where) : '';

topx('Cari Raporlar');
?>
<div class="panel">
  <form method="get" style="display:flex;gap:8px;flex-wrap:wrap">
    <div style="flex:1;min-width:130px"><label>Rapor Tipi</label>
      <select name="mode" style="margin:0">
        <option value="">Tüm Bakiyeler</option>
        <option value="receivable" <?=$mode==='receivable'?'selected':''?>>Alacaklı Cariler</option>
        <option value="payable" <?=$mode==='payable'?'selected':''?>>Borçlu Cariler</option>
        <option value="zero" <?=$mode==='zero'?'selected':''?>>Sıfır Bakiyeler</option>
      </select>
    </div>
    <div style="flex:1;min-width:130px"><label>Cari Tipi</label>
      <select name="type" style="margin:0">
        <option value="">Tümü</option>
        <option <?=$type==='Müşteri'?'selected':''?>>Müşteri</option>
        <option <?=$type==='Tedarikçi'?'selected':''?>>Tedarikçi</option>
        <option <?=$type==='Her İkisi'?'selected':''?>>Her İkisi</option>
      </select>
    </div>
    <button class="btn dark" style="padding:12px 16px;align-self:end">Filtrele</button>
  </form>
</div>

<?php
$rows=[];
try{
    $sql="SELECT c.*,
        COALESCE(SUM(CASE WHEN f.direction='in' THEN f.amount ELSE 0 END),0) total_in,
        COALESCE(SUM(CASE WHEN f.direction='out' THEN f.amount ELSE 0 END),0) total_out
        FROM contacts c
        LEFT JOIN finance_movements f ON f.contact_id=c.id
        $sqlWhere
        GROUP BY c.id
        ORDER BY c.name";
    $st=db()->prepare($sql);
    $st->execute($params);
    $all=$st->fetchAll();
    foreach($all as $r){
        $balance=(float)$r['opening_balance']+(float)$r['total_in']-(float)$r['total_out'];
        if($mode==='receivable' && $balance<=0) continue;
        if($mode==='payable' && $balance>=0) continue;
        if($mode==='zero' && abs($balance)>0.01) continue;
        $r['balance']=$balance;
        $rows[]=$r;
    }
}catch(Throwable $e){
    echo '<div class="err">'.htmlspecialchars($e->getMessage()).'</div>';
}

$totalAlacak=0; $totalBorc=0;
foreach($rows as $r){ if($r['balance']>0) $totalAlacak+=$r['balance']; else $totalBorc+=abs($r['balance']); }
?>

<div class="panel" style="display:flex;gap:10px;text-align:center">
  <div style="flex:1"><small class="muted">Cari</small><br><b style="font-size:20px"><?=count($rows)?></b></div>
  <div style="flex:1"><small class="muted">Alacak</small><br><b style="font-size:16px;color:#4ade80"><?=mm($totalAlacak)?></b></div>
  <div style="flex:1"><small class="muted">Borç</small><br><b style="font-size:16px;color:#f87171"><?=mm($totalBorc)?></b></div>
</div>

<div class="panel">
  <a class="btn dark" style="display:block;width:100%;text-align:center;padding:14px;font-size:15px"
     href="report.php?modul=cari_toplu&mode=<?=urlencode($mode)?>&type=<?=urlencode($type)?>&from=<?=date('Y-m-01')?>&to=<?=date('Y-m-t')?>">
     📊 Toplu Ekstre Oluştur (PDF)
  </a>
  <small class="muted" style="display:block;margin-top:8px">Bu filtreye uygun tüm carilerin ayrı ayrı hareket dökümünü tek raporda üretir.</small>
</div>

<?php foreach($rows as $r):
  $balColor = $r['balance']>0?'#4ade80':($r['balance']<0?'#f87171':'#94a3b8');
?>
<a class="item" href="contact_view.php?id=<?=(int)$r['id']?>">
  <b><?=htmlspecialchars($r['name'])?></b><br>
  <small><?=htmlspecialchars($r['type'])?></small>
  <div style="text-align:right;margin-top:-22px;font-weight:900;color:<?=$balColor?>"><?=mm($r['balance'])?></div>
</a>
<?php endforeach; ?>
<?php if(!$rows): ?><div class="panel muted">Bu filtreye uygun kayıt yok.</div><?php endif; ?>

<?php botx(); ?>
