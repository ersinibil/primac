<?php
/* SATIN ALMA OPERASYON MERKEZİ — Satın Almalar listesi (2026-07-19, P0-3). sales_list.php ile
 * birebir aynı desen (satın alma tarafı) — bkz. o dosyadaki mimari not. */
require_once __DIR__.'/boot.php';
require_login();
$pdo=db();

$q      = trim($_GET['q'] ?? '');
$cid    = (int)($_GET['contact_id'] ?? 0);
$from   = preg_match('/^\d{4}-\d{2}-\d{2}$/',$_GET['from']??'') ? $_GET['from'] : '';
$to     = preg_match('/^\d{4}-\d{2}-\d{2}$/',$_GET['to']??'') ? $_GET['to'] : '';
$status = trim($_GET['status'] ?? '');
$page   = max(1,(int)($_GET['page'] ?? 1));
$per    = 30;

$where=["fm.movement_type='purchase'"];
$params=[];
if($q!==''){ $where[]="(c.name LIKE ? OR fm.description LIKE ? OR td.document_no LIKE ?)"; $params[]="%$q%"; $params[]="%$q%"; $params[]="%$q%"; }
if($cid){ $where[]="fm.contact_id=?"; $params[]=$cid; }
if($from){ $where[]="fm.movement_date>=?"; $params[]=$from; }
if($to){ $where[]="fm.movement_date<=?"; $params[]=$to; }
if($status!==''){ $where[]="fm.status=?"; $params[]=$status; }
$whereSql = 'WHERE '.implode(' AND ',$where);

$rows=[]; $total=0;
try{
    $cs=$pdo->prepare("SELECT COUNT(*) c FROM finance_movements fm LEFT JOIN contacts c ON c.id=fm.contact_id LEFT JOIN trade_documents td ON td.id=fm.document_id $whereSql");
    $cs->execute($params);
    $total=(int)$cs->fetch()['c'];

    $offset=($page-1)*$per;
    $st=$pdo->prepare("SELECT fm.id, fm.movement_date, fm.amount, fm.vat_amount, fm.description, fm.status, fm.document_id, fm.contact_id,
        c.name AS cname, td.document_no
        FROM finance_movements fm
        LEFT JOIN contacts c ON c.id=fm.contact_id
        LEFT JOIN trade_documents td ON td.id=fm.document_id
        $whereSql
        ORDER BY fm.id DESC LIMIT $per OFFSET $offset");
    $st->execute($params);
    $rows=$st->fetchAll();
}catch(Throwable $e){ $err=$e->getMessage(); }

$contacts=[];
try{
    $contacts=$pdo->query("SELECT id,name FROM contacts WHERE type IN ('Tedarikçi','Her İkisi') OR type IS NULL ORDER BY name")->fetchAll();
    if(!$contacts) $contacts=$pdo->query("SELECT id,name FROM contacts ORDER BY name")->fetchAll();
}catch(Throwable $e){}

require_once __DIR__.'/layout_top.php';
?>
<?php
$__actions = ds_button('+ Yeni Satın Alma', 'purchase.php', 'primary', '', '', true)
    . ds_button('Alış Belgesi', 'trade_document_new.php?type=purchase', 'secondary', '', '', true);
ds_page_header('Satın Almalar', ds_icon('box',24), '', $__actions, false, true);
?>

<?php if(!empty($err)): ?><?=ds_alert('danger',$err)?><?php endif; ?>
<?php if(isset($_GET['deleted'])): ?><?=ds_alert('success','Alış geri alındı, stok ve cari bakiye güncellendi.')?><?php endif; ?>

<section class="df-card">
<form method="get" style="display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end">
  <?php ds_form_field('Ara', '<input type="text" name="q" value="'.h($q).'" placeholder="tedarikçi, açıklama, belge no">'); ?>
  <?php
  $__cOpts='<option value="0">— Tüm tedarikçiler —</option>';
  foreach($contacts as $c){ $__cOpts.='<option value="'.(int)$c['id'].'" '.($cid===(int)$c['id']?'selected':'').'>'.h($c['name']).'</option>'; }
  ds_form_field('Tedarikçi', '<select name="contact_id">'.$__cOpts.'</select>');
  ?>
  <?php ds_form_field('Başlangıç', '<input type="date" name="from" value="'.h($from).'">'); ?>
  <?php ds_form_field('Bitiş', '<input type="date" name="to" value="'.h($to).'">'); ?>
  <?php
  $__sOpts='<option value="">— Tüm durumlar —</option>';
  foreach(['Bekliyor','İptal'] as $s){ $__sOpts.='<option '.($status===$s?'selected':'').'>'.$s.'</option>'; }
  ds_form_field('Durum', '<select name="status">'.$__sOpts.'</select>');
  ?>
  <button class="df-btn df-btn--primary" type="submit">Filtrele</button>
  <?php if($q||$cid||$from||$to||$status): ?><a class="df-btn df-btn--secondary" href="purchase_list.php">Temizle</a><?php endif; ?>
</form>
</section>

<section class="df-card" style="margin-top:var(--df-space-4)">
<div class="df-table-wrap"><table class="df-table">
<thead><tr><th>Tarih</th><th>Tedarikçi</th><th>Açıklama</th><th style="text-align:right">Tutar</th><th>Durum</th><th>İşlem</th></tr></thead>
<tbody>
<?php foreach($rows as $r): $isDoc=!empty($r['document_id']); ?>
<tr>
<td class="nowrap"><?=h($r['movement_date'])?></td>
<td><?php if($r['contact_id']): ?><a href="contact_view.php?id=<?=(int)$r['contact_id']?>"><?=h($r['cname'] ?: '—')?></a><?php else: ?>—<?php endif; ?></td>
<td style="font-size:12px;color:var(--df-ink-500)">
  <?php if($isDoc): ?><span class="df-badge df-badge--info"><?=h($r['document_no'] ?: 'Belge')?></span> <?php endif; ?>
  <?=h($r['description'] ?? '')?>
</td>
<td style="text-align:right;font-weight:800;color:var(--df-danger-ink)"><?=money($r['amount'])?></td>
<td><?=ds_badge($r['status'])?></td>
<td><a class="df-btn df-btn--secondary df-btn--sm" href="<?= $isDoc ? 'trade_document_view.php?id='.(int)$r['document_id'] : 'purchase_view.php?id='.(int)$r['id'] ?>">Aç</a></td>
</tr>
<?php endforeach; ?>
<?php if(!$rows): ?><tr><td colspan="6" style="color:var(--df-ink-500)">Kayıt yok.</td></tr><?php endif; ?>
</tbody>
</table></div>

<?php $__pages=(int)ceil($total/$per); if($__pages>1): ?>
<div style="display:flex;gap:6px;flex-wrap:wrap;margin-top:var(--df-space-3)">
<?php for($p=1;$p<=$__pages;$p++):
  $__qs=$_GET; $__qs['page']=$p;
?><a class="df-btn <?=$p===$page?'df-btn--primary':'df-btn--secondary'?> df-btn--sm" href="purchase_list.php?<?=http_build_query($__qs)?>"><?=$p?></a><?php endfor; ?>
</div>
<?php endif; ?>
<p class="df-muted" style="margin-top:var(--df-space-2)">Toplam <?=$total?> kayıt.</p>
</section>

<?php require_once __DIR__.'/layout_bottom.php'; ?>
</content>
