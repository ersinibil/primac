<?php
require_once 'common.php';
require_once __DIR__.'/../contacts_lib.php';

$mode = $_GET['mode'] ?? '';
$type = $_GET['type'] ?? '';

$where=[]; $params=[];
if($type){ $where[]="c.type=?"; $params[]=$type; }
$sqlWhere = $where ? 'WHERE '.implode(' AND ',$where) : '';

topx('Cari Raporlar');
?>
<div class="df-panel">
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
    <button class="df-btn df-btn--primary df-btn--lg" style="align-self:end">Filtrele</button>
  </form>
</div>

<?php
$rows=[];
try{
    // 2026-07-10 Finans Çekirdek düzeltmesi: web contacts_report.php ile aynı düzeltilmiş formül.
    $balExprF = contact_balance_case_sql('f');
    $sql="SELECT c.*,
        COALESCE(SUM(CASE WHEN f.direction='in' AND f.movement_type IN ('normal','mobile') AND f.account_id IS NOT NULL THEN f.amount ELSE 0 END),0) total_in,
        COALESCE(SUM(CASE WHEN f.direction='out' AND f.movement_type IN ('normal','mobile') AND f.account_id IS NOT NULL THEN f.amount ELSE 0 END),0) total_out,
        COALESCE(SUM($balExprF),0) net_movements
        FROM contacts c
        LEFT JOIN finance_movements f ON f.contact_id=c.id
        $sqlWhere
        GROUP BY c.id
        ORDER BY c.name";
    $st=db()->prepare($sql);
    $st->execute($params);
    $all=$st->fetchAll();
    foreach($all as $r){
        $balance=(float)$r['opening_balance']+(float)$r['net_movements'];
        if($mode==='receivable' && $balance<=0) continue;
        if($mode==='payable' && $balance>=0) continue;
        if($mode==='zero' && abs($balance)>0.01) continue;
        $r['balance']=$balance;
        $rows[]=$r;
    }
}catch(Throwable $e){
    echo ds_alert('danger',$e->getMessage());
}

$totalAlacak=0; $totalBorc=0;
foreach($rows as $r){ if($r['balance']>0) $totalAlacak+=$r['balance']; else $totalBorc+=abs($r['balance']); }
?>

<div class="df-panel" style="display:flex;gap:10px;text-align:center">
  <div style="flex:1"><small class="muted">Cari</small><br><b style="font-size:20px"><?=count($rows)?></b></div>
  <div style="flex:1"><small class="muted">Alacak</small><br><b style="font-size:16px;color:var(--df-success-ink,#4ade80)"><?=mm($totalAlacak)?></b></div>
  <div style="flex:1"><small class="muted">Borç</small><br><b style="font-size:16px;color:var(--df-danger-ink,#f87171)"><?=mm($totalBorc)?></b></div>
</div>

<div class="df-panel">
  <a class="df-btn df-btn--primary df-btn--lg" style="width:100%"
     href="report.php?modul=cari_toplu&mode=<?=urlencode($mode)?>&type=<?=urlencode($type)?>&from=<?=date('Y-m-01')?>&to=<?=date('Y-m-t')?>">
     <?=ds_icon('info',16)?> Toplu Ekstre Oluştur (PDF)
  </a>
  <small class="muted" style="display:block;margin-top:8px">Bu filtreye uygun tüm carilerin ayrı ayrı hareket dökümünü tek raporda üretir.</small>
</div>

<div class="df-list">
<?php foreach($rows as $r):
  $balColor = $r['balance']>0?'success':($r['balance']<0?'danger':'info');
  $__meta='<span class="df-badge df-badge--'.$balColor.' df-text-tabular">'.mm($r['balance']).'</span>';
  ds_list_item(h($r['name']), 'contact_view.php?id='.(int)$r['id'], h($r['type']), $__meta);
endforeach; ?>
</div>
<?php if(!$rows): ?><?php ds_empty_state('Bu filtreye uygun kayıt yok.'); ?><?php endif; ?>

<?php botx(); ?>
