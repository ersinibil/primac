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

<?php
ds_page_header('Cari Raporlar', ds_icon('users',24), '',
    ds_button('Cari Hesaplar','contacts.php','secondary','','',true).ds_button('Finans','finance.php','secondary','','',true), false, true);
?>

<section class="df-card" style="margin-top:var(--df-space-4)">
<form method="get" class="df-form-grid-3">
<?php
$__modeOpts='<option value="">Tüm Bakiyeler</option>
<option value="receivable" '.($mode==='receivable'?'selected':'').'>Alacaklı Cariler</option>
<option value="payable" '.($mode==='payable'?'selected':'').'>Borçlu Cariler</option>
<option value="zero" '.($mode==='zero'?'selected':'').'>Sıfır Bakiyeler</option>';
ds_form_field('Rapor Tipi', '<select name="mode">'.$__modeOpts.'</select>');

$__typeOpts='<option value="">Tümü</option>
<option '.($type==='Müşteri'?'selected':'').'>Müşteri</option>
<option '.($type==='Tedarikçi'?'selected':'').'>Tedarikçi</option>
<option '.($type==='Her İkisi'?'selected':'').'>Her İkisi</option>';
ds_form_field('Cari Tipi', '<select name="type">'.$__typeOpts.'</select>');

$__repOpts='<option value="">Tümü</option>';
foreach($personnel as $p){ $__repOpts.='<option value="'.(int)$p['id'].'" '.($rep===(int)$p['id']?'selected':'').'>'.h($p['name']).'</option>'; }
ds_form_field('Temsilci', '<select name="representative_id">'.$__repOpts.'</select>');
?>
<div class="df-form-span-3"><button class="df-btn df-btn--primary" type="submit">Raporla</button></div>
</form>
</section>

<style>
body.nav-compact .df-form-grid-3{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:0 var(--df-space-4)}
body.nav-compact .df-form-span-3{grid-column:1 / -1}
@media(max-width:900px){body.nav-compact .df-form-grid-3{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:640px){body.nav-compact .df-form-grid-3{grid-template-columns:1fr}}
.report-grid{display:grid;grid-template-columns:repeat(4,minmax(160px,1fr));gap:var(--df-space-4);margin:var(--df-space-4) 0 var(--df-space-5)}
.report-card{border-radius:var(--df-radius-lg);padding:var(--df-space-4);color:var(--df-ink-900);box-shadow:var(--df-elevation-raised);border:1px solid var(--df-hairline)}
.report-card small{display:block;font-weight:900;color:var(--df-ink-600)}.report-card strong{display:block;font-size:26px;margin:8px 0}
.r1{background:linear-gradient(135deg,#dbeafe,#eff6ff)}.r2{background:linear-gradient(135deg,#dcfce7,#f0fdf4)}.r3{background:linear-gradient(135deg,#fee2e2,#fff1f2)}.r4{background:linear-gradient(135deg,#fef3c7,#fffbeb)}
.money-pos{font-weight:900;color:var(--df-success-ink)}.money-neg{font-weight:900;color:var(--df-danger-ink)}.money-zero{font-weight:900;color:var(--df-ink-500)}
@media(max-width:960px){.report-grid{grid-template-columns:1fr}}
</style>

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
    echo ds_alert('danger', $e->getMessage());
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

<section class="df-card" style="margin-top:var(--df-space-4)">
<div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:var(--df-space-3);margin-bottom:var(--df-space-3)">
<h2 class="df-section-title" style="margin:0">Rapor Detayı</h2>
<div style="display:flex;gap:8px;flex-wrap:wrap">
<?=ds_button('Alacaklılar','contacts_report.php?mode=receivable','secondary','','',true)?>
<?=ds_button('Borçlular','contacts_report.php?mode=payable','secondary','','',true)?>
<?=ds_button('Tümü','contacts_report.php','secondary','','',true)?>
<?=ds_button('📊 Toplu Ekstre Oluştur (PDF)','report.php?modul=cari_toplu&mode='.urlencode($mode).'&type='.urlencode($type).'&from='.date('Y-m-01').'&to='.date('Y-m-t'),'primary','','',true)?>
</div>
</div>
<div class="df-table-wrap"><table class="df-table">
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
<td><?=ds_button('Aç','contact_view.php?id='.(int)$r['id'],'secondary','df-btn--sm','',true)?></td>
</tr>
<?php endforeach; ?>
<?php if(!$rows): ?><tr><td colspan="8" class="df-muted">Bu filtreye uygun kayıt yok.</td></tr><?php endif; ?>
</tbody>
</table></div>
</section>

<style>
body.nav-compact .df-section-title{font-size:var(--df-type-section-size);font-weight:var(--df-type-section-weight);color:var(--df-ink-900)}
</style>

<?php require_once __DIR__.'/layout_bottom.php'; ?>
