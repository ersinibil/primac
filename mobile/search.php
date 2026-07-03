<?php
require_once 'common.php';
require_once dirname(__DIR__).'/search_lib.php';
$pdo = db();
$q = trim($_GET['q'] ?? '');

topx('Arama');
?>
<form method="get" style="display:flex;gap:8px;margin-bottom:6px">
    <input name="q" value="<?=htmlspecialchars($q)?>" placeholder="İş, cari, banka/kart, işlem, çek/senet, teklif, belge, stok, personel…" autofocus autocomplete="off" style="margin:0;flex:1;min-width:0;width:auto">
    <button class="btn dark" type="submit" style="flex:0 0 auto">Ara</button>
</form>

<?php if ($q === ''): ?>
<div class="panel" style="text-align:center;color:#94a3b8">
    <div style="font-size:40px;margin-bottom:8px">🔍</div>
    <p>İş no, müşteri adı, banka/kart, işlem, çek/senet, teklif, belge, ürün veya personel ismi girin.</p>
</div>
<?php else:
    $r = search_run($pdo, $q);
    $jobs = $r['jobs']; $contacts = $r['contacts']; $stock = $r['stock']; $personnel = $r['personnel'];
    $accounts = $r['accounts']; $movements = $r['movements']; $checks = $r['checks']; $quotes = $r['quotes'];
    $documents = $r['documents']; $pages = $r['pages'];
    $found = search_total_count($r);
    // Web'den farklı: mobil dosya adları kendi rotasına göre kurulur (takvim -> calendar.php).
    $pageTargets = ['contacts_report'=>'contacts_report.php', 'report'=>'report.php', 'accounting'=>'accounting.php', 'takvim'=>'calendar.php'];
?>
<p class="small" style="margin:10px 2px">"<b><?=htmlspecialchars($q)?></b>" için <?=$found?> sonuç</p>

<?php if ($found === 0): ?>
<div class="panel" style="text-align:center;color:#94a3b8">
    <div style="font-size:40px;margin-bottom:8px">😶</div>
    <p>Sonuç bulunamadı.</p>
</div>
<?php endif; ?>

<?php if ($pages): ?>
<div class="panel"><b>🔗 Sayfalar (<?=count($pages)?>)</b>
<?php foreach($pages as $pg): $href = $pageTargets[$pg['target']] ?? null; if (!$href) continue; ?>
    <a class="item" href="<?=htmlspecialchars($href)?>">
        <b><?=$pg['icon']?> <?=htmlspecialchars($pg['label'])?></b>
    </a>
<?php endforeach; ?>
</div>
<?php endif; ?>

<?php if ($jobs): ?>
<div class="panel"><b>📋 İşler (<?=count($jobs)?>)</b>
<?php foreach($jobs as $j): ?>
    <a class="item" href="job_view.php?id=<?=(int)$j['id']?>">
        <b><?=search_hl($j['title'],$q)?></b><br>
        <small class="muted">
            <?php if($j['job_no']): ?><?=search_hl($j['job_no'],$q)?> · <?php endif; ?>
            <?php if($j['customer']): ?>👤 <?=search_hl($j['customer'],$q)?> · <?php endif; ?>
            <?=htmlspecialchars($j['status'])?>
        </small>
    </a>
<?php endforeach; ?>
</div>
<?php endif; ?>

<?php if ($contacts): ?>
<div class="panel"><b>👥 Cariler (<?=count($contacts)?>)</b>
<?php foreach($contacts as $c): ?>
    <a class="item" href="contact_view.php?id=<?=(int)$c['id']?>">
        <b><?=search_hl($c['name'],$q)?></b><br>
        <small class="muted">
            <?php if($c['phone']): ?><?=search_hl($c['phone'],$q)?><?php endif; ?>
            <?php if($c['city']): ?> · <?=search_hl($c['city'],$q)?><?php endif; ?>
            <?php if($c['email']): ?> · <?=search_hl($c['email'],$q)?><?php endif; ?>
        </small>
    </a>
<?php endforeach; ?>
</div>
<?php endif; ?>

<?php if ($accounts): ?>
<div class="panel"><b>🏦 Finans Hesapları (<?=count($accounts)?>)</b>
<?php foreach($accounts as $a): ?>
    <a class="item" href="account_view.php?id=<?=(int)$a['id']?>" style="display:flex;justify-content:space-between;align-items:center">
        <span><b><?=search_hl($a['name'],$q)?></b><br>
        <small class="muted"><?=search_hl($a['account_type'],$q)?><?php if($a['bank_name']): ?> · <?=search_hl($a['bank_name'],$q)?><?php endif; ?></small></span>
        <b><?=mm($a['current_balance'])?></b>
    </a>
<?php endforeach; ?>
</div>
<?php endif; ?>

<?php if ($movements): ?>
<div class="panel"><b>💸 Finans Hareketleri (<?=count($movements)?>)</b>
<?php foreach($movements as $m):
    $dColor = $m['direction']==='in' ? '#4ade80' : '#f87171';
?>
    <a class="item" href="movement_view.php?id=<?=(int)$m['id']?>" style="display:flex;justify-content:space-between;align-items:center">
        <span><b><?=$m['description'] ? search_hl($m['description'],$q) : htmlspecialchars($m['payment_channel'])?></b><br>
        <small class="muted"><?=search_hl($m['payment_channel'],$q)?><?php if($m['contact_name']): ?> · 👤 <?=search_hl($m['contact_name'],$q)?><?php endif; ?></small></span>
        <b style="color:<?=$dColor?>"><?=mm($m['amount'])?></b>
    </a>
<?php endforeach; ?>
</div>
<?php endif; ?>

<?php if ($checks): ?>
<div class="panel"><b>🧾 Çek / Senet (<?=count($checks)?>)</b>
<?php foreach($checks as $k):
    $typeLabel = $k['type']==='senet' ? 'Senet' : 'Çek';
?>
    <a class="item" href="check_note_view.php?id=<?=(int)$k['id']?>" style="display:flex;justify-content:space-between;align-items:center">
        <span><b><?=$typeLabel?> <?=$k['number'] ? search_hl($k['number'],$q) : ''?></b><br>
        <small class="muted"><?php if($k['bank_name']): ?><?=search_hl($k['bank_name'],$q)?> · <?php endif; ?><?php if($k['contact_name']): ?>👤 <?=search_hl($k['contact_name'],$q)?><?php endif; ?></small></span>
        <b><?=mm($k['amount'])?></b>
    </a>
<?php endforeach; ?>
</div>
<?php endif; ?>

<?php if ($documents): ?>
<div class="panel"><b>🧾 Ticari Belgeler (Alış/Satış) (<?=count($documents)?>)</b>
<?php foreach($documents as $d):
    $docTypeLabel = $d['document_type']==='sale' ? 'Satış' : ($d['document_type']==='purchase' ? 'Alış' : htmlspecialchars($d['document_type']));
    $remaining = (float)$d['grand_total'] - (float)$d['paid_amount'];
?>
    <div class="item" style="display:flex;justify-content:space-between;align-items:center">
        <span><b><?=$docTypeLabel?> <?=$d['document_no'] ? search_hl($d['document_no'],$q) : ''?></b><br>
        <small class="muted"><?php if($d['contact_name']): ?>👤 <?=search_hl($d['contact_name'],$q)?><?php endif; ?></small></span>
        <span style="text-align:right">
            <b><?=mm($d['grand_total'])?></b>
            <?php if($remaining > 0.009): ?><br><small style="color:#f87171">Kalan: <?=mm($remaining)?></small><?php endif; ?>
        </span>
    </div>
<?php endforeach; ?>
</div>
<?php endif; ?>

<?php if ($quotes): ?>
<div class="panel"><b>📄 Teklifler (<?=count($quotes)?>)</b>
<?php foreach($quotes as $t): ?>
    <a class="item" href="teklif.php?id=<?=(int)$t['id']?>" style="display:flex;justify-content:space-between;align-items:center">
        <span><b><?=search_hl($t['quote_no'],$q)?></b><br>
        <small class="muted"><?php if($t['customer_name']): ?>👤 <?=search_hl($t['customer_name'],$q)?> · <?php endif; ?><?=htmlspecialchars($t['status'])?></small></span>
        <b><?=mm($t['total'])?></b>
    </a>
<?php endforeach; ?>
</div>
<?php endif; ?>

<?php if ($stock): ?>
<div class="panel"><b>📦 Stok (<?=count($stock)?>)</b>
<?php foreach($stock as $p): ?>
    <a class="item" href="product_view.php?id=<?=(int)$p['id']?>" style="display:flex;justify-content:space-between;align-items:center">
        <b><?=search_hl($p['name'],$q)?></b>
        <small class="muted"><?=htmlspecialchars(rtrim(rtrim(number_format((float)$p['quantity'],2,',','.'),'0'),','))?> <?=htmlspecialchars($p['unit']??'')?></small>
    </a>
<?php endforeach; ?>
</div>
<?php endif; ?>

<?php if ($personnel): ?>
<div class="panel"><b>👷 Personel (<?=count($personnel)?>)</b>
<?php foreach($personnel as $p): ?>
    <a class="item" href="personnel_view.php?id=<?=(int)$p['id']?>" style="display:flex;justify-content:space-between;align-items:center">
        <span><b><?=search_hl($p['name'],$q)?></b><br>
        <small class="muted"><?php if($p['role']): ?><?=search_hl($p['role'],$q)?><?php endif; ?><?php if($p['work_type']): ?> · <?=search_hl($p['work_type'],$q)?><?php endif; ?></small></span>
        <small class="muted"><?php if($p['phone']): ?><?=search_hl($p['phone'],$q)?><?php endif; ?></small>
    </a>
<?php endforeach; ?>
</div>
<?php endif; ?>

<?php endif; ?>
<?php botx(); ?>
