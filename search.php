<?php
require_once __DIR__.'/layout_top.php';
$q = trim($_GET['q'] ?? '');
$pdo = db();

function hl($text, $q) {
    if ($q === '') return htmlspecialchars($text);
    return preg_replace('/('.preg_quote(htmlspecialchars($q),'/').')/iu',
        '<mark style="background:#fef08a;border-radius:3px;padding:0 2px">$1</mark>',
        htmlspecialchars($text));
}
?>
<h1>🔍 Arama</h1>

<form method="get" style="display:flex;gap:10px;margin-bottom:24px">
    <input name="q" value="<?=htmlspecialchars($q)?>" placeholder="İş, müşteri, stok, personel ara…"
        style="flex:1;border:1.5px solid #e5e7eb;border-radius:12px;padding:11px 14px;font-size:15px;outline:none"
        autofocus autocomplete="off">
    <button type="submit" style="background:#2563eb;color:#fff;border:0;border-radius:12px;padding:11px 20px;font-weight:800;cursor:pointer">Ara</button>
</form>

<?php if ($q === ''): ?>
<div style="text-align:center;color:#667085;padding:40px 0">
    <div style="font-size:48px;margin-bottom:12px">🔍</div>
    <p>İş no, müşteri adı, ürün veya personel ismi girin.</p>
</div>
<?php else:
    $like = '%'.$q.'%';
    $found = 0;

    // İşler
    try {
        $s = $pdo->prepare("SELECT j.id, j.job_no, j.title, j.status, c.name customer
            FROM jobs j LEFT JOIN contacts c ON c.id=j.customer_id
            WHERE j.title LIKE ? OR j.job_no LIKE ? OR j.description LIKE ? OR c.name LIKE ?
            ORDER BY j.id DESC LIMIT 30");
        $s->execute([$like,$like,$like,$like]);
        $jobs = $s->fetchAll();
    } catch(Throwable $e) { $jobs = []; }

    // Cari
    try {
        $s = $pdo->prepare("SELECT id,name,phone,email,city FROM contacts
            WHERE name LIKE ? OR phone LIKE ? OR email LIKE ? OR tax_no LIKE ? OR city LIKE ?
            ORDER BY id DESC LIMIT 30");
        $s->execute([$like,$like,$like,$like,$like]);
        $contacts = $s->fetchAll();
    } catch(Throwable $e) { $contacts = []; }

    // Stok
    try {
        $s = $pdo->prepare("SELECT id,name,quantity,unit,sale_price FROM stock_items
            WHERE name LIKE ? OR sku LIKE ? OR description LIKE ?
            ORDER BY id DESC LIMIT 30");
        $s->execute([$like,$like,$like]);
        $stock = $s->fetchAll();
    } catch(Throwable $e) { $stock = []; }

    // Personel
    try {
        $s = $pdo->prepare("SELECT id,name,title,phone,department FROM personnel
            WHERE name LIKE ? OR title LIKE ? OR department LIKE ? OR phone LIKE ?
            ORDER BY id DESC LIMIT 20");
        $s->execute([$like,$like,$like,$like]);
        $personnel = $s->fetchAll();
    } catch(Throwable $e) { $personnel = []; }

    $found = count($jobs)+count($contacts)+count($stock)+count($personnel);
?>
<p style="color:#667085;margin-bottom:20px">"<b><?=htmlspecialchars($q)?></b>" için <?=$found?> sonuç</p>

<?php if ($found === 0): ?>
<div style="text-align:center;color:#667085;padding:40px 0">
    <div style="font-size:48px;margin-bottom:12px">😶</div>
    <p>Sonuç bulunamadı.</p>
</div>
<?php endif; ?>

<?php if ($jobs): ?>
<div class="panel" style="margin-bottom:20px">
    <div style="font-weight:900;font-size:16px;margin-bottom:12px">📋 İşler (<?=count($jobs)?>)</div>
    <table style="width:100%;border-collapse:collapse">
    <?php foreach($jobs as $j):
        $sColor=['Tamamlandı'=>'#16a34a','Teslim Edildi'=>'#16a34a','İptal'=>'#6b7280','Devam Ediyor'=>'#7c3aed','Bekliyor'=>'#d97706'][$j['status']] ?? '#2563eb';
    ?>
    <tr style="border-bottom:1px solid #f1f5f9">
        <td style="padding:10px 8px">
            <a href="job_view.php?id=<?=$j['id']?>" style="color:#101828;text-decoration:none;font-weight:700">
                <?=hl($j['title'],$q)?>
            </a>
            <div style="font-size:12px;color:#667085;margin-top:2px">
                <?php if($j['job_no']): ?><?=hl($j['job_no'],$q)?> · <?php endif; ?>
                <?php if($j['customer']): ?>👤 <?=hl($j['customer'],$q)?><?php endif; ?>
            </div>
        </td>
        <td style="padding:10px 8px;text-align:right;white-space:nowrap">
            <span style="background:<?=$sColor?>18;color:<?=$sColor?>;border-radius:20px;padding:4px 10px;font-size:12px;font-weight:700">
                <?=htmlspecialchars($j['status'])?>
            </span>
        </td>
    </tr>
    <?php endforeach; ?>
    </table>
</div>
<?php endif; ?>

<?php if ($contacts): ?>
<div class="panel" style="margin-bottom:20px">
    <div style="font-weight:900;font-size:16px;margin-bottom:12px">👥 Cariler (<?=count($contacts)?>)</div>
    <table style="width:100%;border-collapse:collapse">
    <?php foreach($contacts as $c): ?>
    <tr style="border-bottom:1px solid #f1f5f9">
        <td style="padding:10px 8px">
            <a href="contact_view.php?id=<?=$c['id']?>" style="color:#101828;text-decoration:none;font-weight:700">
                <?=hl($c['name'],$q)?>
            </a>
            <div style="font-size:12px;color:#667085;margin-top:2px">
                <?php if($c['phone']): ?><?=hl($c['phone'],$q)?><?php endif; ?>
                <?php if($c['city']): ?> · <?=hl($c['city'],$q)?><?php endif; ?>
            </div>
        </td>
        <td style="padding:10px 8px;text-align:right">
            <?php if($c['email']): ?><span style="font-size:12px;color:#667085"><?=hl($c['email'],$q)?></span><?php endif; ?>
        </td>
    </tr>
    <?php endforeach; ?>
    </table>
</div>
<?php endif; ?>

<?php if ($stock): ?>
<div class="panel" style="margin-bottom:20px">
    <div style="font-weight:900;font-size:16px;margin-bottom:12px">📦 Stok (<?=count($stock)?>)</div>
    <table style="width:100%;border-collapse:collapse">
    <?php foreach($stock as $p): ?>
    <tr style="border-bottom:1px solid #f1f5f9">
        <td style="padding:10px 8px">
            <a href="product_view.php?id=<?=$p['id']?>" style="color:#101828;text-decoration:none;font-weight:700">
                <?=hl($p['name'],$q)?>
            </a>
        </td>
        <td style="padding:10px 8px;text-align:right;white-space:nowrap;color:#667085;font-size:13px">
            <?=htmlspecialchars(rtrim(rtrim(number_format((float)$p['quantity'],2,',','.'),'0'),','))?> <?=htmlspecialchars($p['unit']??'')?>
            <?php if($p['sale_price']>0): ?> · <?=number_format((float)$p['sale_price'],2,',','.')?> ₺<?php endif; ?>
        </td>
    </tr>
    <?php endforeach; ?>
    </table>
</div>
<?php endif; ?>

<?php if ($personnel): ?>
<div class="panel" style="margin-bottom:20px">
    <div style="font-weight:900;font-size:16px;margin-bottom:12px">👷 Personel (<?=count($personnel)?>)</div>
    <table style="width:100%;border-collapse:collapse">
    <?php foreach($personnel as $p): ?>
    <tr style="border-bottom:1px solid #f1f5f9">
        <td style="padding:10px 8px">
            <a href="personnel_view.php?id=<?=$p['id']?>" style="color:#101828;text-decoration:none;font-weight:700">
                <?=hl($p['name'],$q)?>
            </a>
            <div style="font-size:12px;color:#667085;margin-top:2px">
                <?php if($p['title']): ?><?=hl($p['title'],$q)?><?php endif; ?>
                <?php if($p['department']): ?> · <?=hl($p['department'],$q)?><?php endif; ?>
            </div>
        </td>
        <td style="padding:10px 8px;text-align:right;font-size:12px;color:#667085">
            <?php if($p['phone']): ?><?=hl($p['phone'],$q)?><?php endif; ?>
        </td>
    </tr>
    <?php endforeach; ?>
    </table>
</div>
<?php endif; ?>

<?php endif; ?>

<?php require_once __DIR__.'/layout_bottom.php'; ?>
