<?php
require_once __DIR__.'/layout_top.php';
require_once __DIR__.'/contacts_lib.php';

$mode=$_GET['mode'] ?? '';
$rep=(int)($_GET['representative_id'] ?? 0);
$type=$_GET['type'] ?? '';

$where=[];
$params=[];

if($type){
    $where[]="c.type=?";
    $params[]=$type;
}
if($rep){
    $where[]="cr.personnel_id=?";
    $params[]=$rep;
}

$sqlWhere=$where ? 'WHERE '.implode(' AND ',$where) : '';

$personnel=db()->query("SELECT id,name FROM personnel WHERE active=1 ORDER BY name")->fetchAll();
?>

<style>
.report-grid{display:grid;grid-template-columns:repeat(4,minmax(160px,1fr));gap:14px;margin:14px 0 22px}
.report-card{border-radius:22px;padding:18px;color:#101828;box-shadow:0 10px 30px rgba(16,24,40,.07);border:1px solid rgba(15,23,42,.06)}
.report-card small{display:block;font-weight:900;color:#475467}.report-card strong{display:block;font-size:26px;margin:8px 0}.r1{background:linear-gradient(135deg,#dbeafe,#eff6ff)}.r2{background:linear-gradient(135deg,#dcfce7,#f0fdf4)}.r3{background:linear-gradient(135deg,#fee2e2,#fff1f2)}.r4{background:linear-gradient(135deg,#fef3c7,#fffbeb)}
.money-pos{font-weight:900;color:#166534}.money-neg{font-weight:900;color:#991b1b}.money-zero{font-weight:900;color:#667085}
@media(max-width:960px){.report-grid{grid-template-columns:1fr}}
</style>

<div class="panel-head">
<h1>Cari Raporlar</h1>
<div class="actions">
<a class="btn secondary" href="contacts.php">Cari Hesaplar</a>
<a class="btn secondary" href="finance.php">Finans</a>
</div>
</div>

<section class="panel">
<form method="get" class="form-grid">
<label>Rapor Tipi
<select name="mode">
<option value="">Tüm Bakiyeler</option>
<option value="receivable" <?=$mode==='receivable'?'selected':''?>>Alacaklı Cariler</option>
<option value="payable" <?=$mode==='payable'?'selected':''?>>Borçlu Cariler</option>
<option value="zero" <?=$mode==='zero'?'selected':''?>>Sıfır Bakiyeler</option>
</select>
</label>

<label>Cari Tipi
<select name="type">
<option value="">Tümü</option>
<option <?=$type==='Müşteri'?'selected':''?>>Müşteri</option>
<option <?=$type==='Tedarikçi'?'selected':''?>>Tedarikçi</option>
<option <?=$type==='Her İkisi'?'selected':''?>>Her İkisi</option>
</select>
</label>

<label>Temsilci
<select name="representative_id">
<option value="">Tümü</option>
<?php foreach($personnel as $p): ?>
<option value="<?=$p['id']?>" <?=$rep===$p['id']?'selected':''?>><?=h($p['name'])?></option>
<?php endforeach; ?>
</select>
</label>

<div style="align-self:end"><button class="btn">Raporla</button></div>
</form>
</section>

<?php
$rows=[];
try{
    // 2026-07-10 Finans Çekirdek düzeltmesi: contacts.php ile aynı düzeltilmiş formül.
    $balExprF = contact_balance_case_sql('f');
    $sql="SELECT c.*,
        GROUP_CONCAT(DISTINCT p.name ORDER BY cr.is_primary DESC, p.name SEPARATOR ', ') representatives,
        COALESCE(SUM(CASE WHEN f.direction='in' AND f.movement_type IN ('normal','mobile') AND f.account_id IS NOT NULL THEN f.amount ELSE 0 END),0) total_in,
        COALESCE(SUM(CASE WHEN f.direction='out' AND f.movement_type IN ('normal','mobile') AND f.account_id IS NOT NULL THEN f.amount ELSE 0 END),0) total_out,
        COALESCE(SUM($balExprF),0) net_movements
        FROM contacts c
        LEFT JOIN contact_representatives cr ON cr.contact_id=c.id
        LEFT JOIN personnel p ON p.id=cr.personnel_id
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
    echo "<div class='alert'>".h($e->getMessage())."</div>";
}

// PHP 7.2 uyumluluğu: fn() arrow function PHP 7.4+ gerektirir (2026-07-03 denetiminde bulundu,
// önceden buradaydı, sunucu PHP 7.2 ise bu sayfa parse error verip hiç açılmıyordu).
$totalOpening=array_sum(array_map(function($r){ return (float)$r['opening_balance']; },$rows));
$totalIn=array_sum(array_map(function($r){ return (float)$r['total_in']; },$rows));
$totalOut=array_sum(array_map(function($r){ return (float)$r['total_out']; },$rows));
$totalBalance=array_sum(array_map(function($r){ return (float)$r['balance']; },$rows));
?>

<section class="report-grid">
<div class="report-card r1"><small>Açılış Bakiyesi</small><strong><?=money($totalOpening)?></strong></div>
<div class="report-card r2"><small>Toplam Tahsilat</small><strong><?=money($totalIn)?></strong></div>
<div class="report-card r3"><small>Toplam Ödeme</small><strong><?=money($totalOut)?></strong></div>
<div class="report-card r4"><small>Net Bakiye</small><strong><?=money($totalBalance)?></strong></div>
</section>

<section class="panel">
<div class="panel-head">
<h2>Rapor Detayı</h2>
<div class="actions">
<a class="btn small secondary" href="contacts_report.php?mode=receivable">Alacaklılar</a>
<a class="btn small secondary" href="contacts_report.php?mode=payable">Borçlular</a>
<a class="btn small secondary" href="contacts_report.php">Tümü</a>
<a class="btn small" style="background:#2563eb;color:#fff" href="report.php?modul=cari_toplu&mode=<?=urlencode($mode)?>&type=<?=urlencode($type)?>&from=<?=date('Y-m-01')?>&to=<?=date('Y-m-t')?>">📊 Toplu Ekstre Oluştur (PDF)</a>
</div>
</div>
<table>
<thead><tr><th>Cari</th><th>Tip</th><th>Temsilci</th><th>Açılış</th><th>Tahsilat</th><th>Ödeme</th><th>Bakiye</th><th>Profil</th></tr></thead>
<tbody>
<?php foreach($rows as $r): 
$balClass=$r['balance']>0?'money-pos':($r['balance']<0?'money-neg':'money-zero');
$repName=($r['representative_mode'] ?? '')==='anonim'?'Anonim':($r['representatives'] ?: '-');
?>
<tr>
<td><b><?=h($r['name'])?></b></td>
<td><?=h($r['type'])?></td>
<td><?=h($repName)?></td>
<td><?=money($r['opening_balance'])?></td>
<td class="money-pos"><?=money($r['total_in'])?></td>
<td class="money-neg"><?=money($r['total_out'])?></td>
<td class="<?=$balClass?>"><?=money($r['balance'])?></td>
<td><a class="btn small secondary" href="contact_view.php?id=<?=$r['id']?>">Aç</a></td>
</tr>
<?php endforeach; ?>
<?php if(!$rows): ?><tr><td colspan="8" class="muted">Bu filtreye uygun kayıt yok.</td></tr><?php endif; ?>
</tbody>
</table>
</section>

<?php require_once __DIR__.'/layout_bottom.php'; ?>
