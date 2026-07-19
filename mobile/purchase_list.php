<?php
/* SATIN ALMA OPERASYON MERKEZİ — Satın Almalar listesi (mobil, 2026-07-19, P0-3). */
require_once 'common.php';
$pdo=db();

$q      = trim($_GET['q'] ?? '');
$cid    = (int)($_GET['contact_id'] ?? 0);
$from   = preg_match('/^\d{4}-\d{2}-\d{2}$/',$_GET['from']??'') ? $_GET['from'] : '';
$to     = preg_match('/^\d{4}-\d{2}-\d{2}$/',$_GET['to']??'') ? $_GET['to'] : '';
$status = trim($_GET['status'] ?? '');
$page   = max(1,(int)($_GET['page'] ?? 1));
$per    = 20;

$where=["fm.movement_type='purchase'"];
$params=[];
if($q!==''){ $where[]="(c.name LIKE ? OR fm.description LIKE ?)"; $params[]="%$q%"; $params[]="%$q%"; }
if($cid){ $where[]="fm.contact_id=?"; $params[]=$cid; }
if($from){ $where[]="fm.movement_date>=?"; $params[]=$from; }
if($to){ $where[]="fm.movement_date<=?"; $params[]=$to; }
if($status!==''){ $where[]="fm.status=?"; $params[]=$status; }
$whereSql='WHERE '.implode(' AND ',$where);

$rows=[]; $total=0;
try{
    $cs=$pdo->prepare("SELECT COUNT(*) c FROM finance_movements fm LEFT JOIN contacts c ON c.id=fm.contact_id $whereSql");
    $cs->execute($params);
    $total=(int)$cs->fetch()['c'];
    $offset=($page-1)*$per;
    $st=$pdo->prepare("SELECT fm.id,fm.movement_date,fm.amount,fm.description,fm.status,fm.document_id,fm.contact_id,c.name cname,td.document_no
        FROM finance_movements fm LEFT JOIN contacts c ON c.id=fm.contact_id LEFT JOIN trade_documents td ON td.id=fm.document_id
        $whereSql ORDER BY fm.id DESC LIMIT $per OFFSET $offset");
    $st->execute($params);
    $rows=$st->fetchAll();
}catch(Throwable $e){ $err=$e->getMessage(); }

topx('Satın Almalar');
?>
<a class="df-btn df-btn--primary df-btn--lg" href="purchase.php" style="width:100%;justify-content:center;margin-bottom:10px"><?=ds_icon('plus',16)?> Yeni Satın Alma</a>

<?php if(!empty($err)): ?><?=ds_alert('danger',$err)?><?php endif; ?>
<?php if(isset($_GET['deleted'])): ?><?=ds_alert('success','Alış geri alındı.')?><?php endif; ?>

<details class="df-panel" style="margin-bottom:10px">
<summary style="cursor:pointer;font-weight:700">🔍 Filtrele<?=($q||$cid||$from||$to||$status)?' (aktif)':''?></summary>
<form method="get" style="margin-top:10px">
  <input type="text" name="q" value="<?=h($q)?>" placeholder="tedarikçi, açıklama" style="margin-bottom:8px">
  <?php
  $__cOpts='<option value="0">— Tüm tedarikçiler —</option>';
  try{ $cs=$pdo->query("SELECT id,name FROM contacts ORDER BY name")->fetchAll(); }catch(Throwable $e){ $cs=[]; }
  foreach($cs as $c){ $__cOpts.='<option value="'.(int)$c['id'].'" '.($cid===(int)$c['id']?'selected':'').'>'.h($c['name']).'</option>'; }
  ?>
  <select name="contact_id" style="margin-bottom:8px"><?=$__cOpts?></select>
  <div style="display:flex;gap:8px;margin-bottom:8px">
    <input type="date" name="from" value="<?=h($from)?>" style="flex:1">
    <input type="date" name="to" value="<?=h($to)?>" style="flex:1">
  </div>
  <select name="status" style="margin-bottom:10px">
    <option value="">— Tüm durumlar —</option>
    <?php foreach(['Bekliyor','İptal'] as $s): ?><option <?=$status===$s?'selected':''?>><?=$s?></option><?php endforeach; ?>
  </select>
  <button class="df-btn df-btn--primary" type="submit" style="width:100%">Filtrele</button>
  <?php if($q||$cid||$from||$to||$status): ?><a class="df-btn df-btn--secondary" href="purchase_list.php" style="width:100%;justify-content:center;margin-top:6px">Temizle</a><?php endif; ?>
</form>
</details>

<?php foreach($rows as $r): $isDoc=!empty($r['document_id']); ?>
<a href="<?= $isDoc ? '../trade_document_view.php?id='.(int)$r['document_id'].'&web=1' : 'purchase_view.php?id='.(int)$r['id'] ?>" class="df-panel" style="display:block;margin-top:10px;text-decoration:none;color:inherit">
  <div style="display:flex;justify-content:space-between;gap:8px;align-items:flex-start">
    <div class="df-list-row-title" style="color:var(--df-danger-ink)"><?=mm($r['amount'])?></div>
    <?=ds_badge($r['status'])?>
  </div>
  <div class="df-list-row-meta" style="margin-top:6px"><span><?=h($r['movement_date'])?></span><span><?=h($r['cname'] ?: '—')?></span></div>
  <?php if($isDoc || $r['description']): ?>
  <div class="df-list-row-desc" style="margin-top:4px"><?php if($isDoc): ?><b><?=h($r['document_no'] ?: 'Belge')?></b> · <?php endif; ?><?=h($r['description'] ?? '')?></div>
  <?php endif; ?>
</a>
<?php endforeach; ?>
<?php if(!$rows): ?><?=ds_empty_state('Kayıt yok.', null, ds_icon('box',20))?><?php endif; ?>

<?php $__pages=(int)ceil($total/$per); if($__pages>1): ?>
<div style="display:flex;gap:6px;flex-wrap:wrap;margin-top:12px;justify-content:center">
<?php for($p=1;$p<=$__pages;$p++): $__qs=$_GET; $__qs['page']=$p; ?>
<a class="df-btn <?=$p===$page?'df-btn--primary':'df-btn--secondary'?> df-btn--sm" href="purchase_list.php?<?=http_build_query($__qs)?>"><?=$p?></a>
<?php endfor; ?>
</div>
<?php endif; ?>
<p class="muted" style="text-align:center;margin-top:10px;font-size:12px">Toplam <?=$total?> kayıt.</p>
<?php botx(); ?>
</content>
