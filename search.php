<?php
require_once __DIR__.'/layout_top.php';
require_once __DIR__.'/search_lib.php';
$q = trim($_GET['q'] ?? '');
$pdo = db();
?>
<h1>🔍 Arama</h1>

<form method="get" style="display:flex;gap:10px;margin-bottom:24px">
    <input name="q" value="<?=htmlspecialchars($q)?>" placeholder="İş, cari, banka/kart, işlem, çek/senet, teklif, belge, stok, personel, görev, kullanıcı, not veya mesaj ara…"
        style="flex:1;border:1.5px solid #e5e7eb;border-radius:12px;padding:11px 14px;font-size:15px;outline:none"
        autofocus autocomplete="off">
    <button type="submit" style="background:#2563eb;color:#fff;border:0;border-radius:12px;padding:11px 20px;font-weight:800;cursor:pointer">Ara</button>
</form>

<?php if ($q === ''): ?>
<div style="text-align:center;color:#667085;padding:40px 0">
    <div style="font-size:48px;margin-bottom:12px">🔍</div>
    <p>İş no, müşteri adı, banka/kart, işlem, çek/senet, teklif, belge, ürün, personel, görev, kullanıcı, not veya mesaj metni girin.</p>
</div>
<?php else:
    $r = search_run($pdo, $q);
    $jobs = $r['jobs']; $contacts = $r['contacts']; $stock = $r['stock']; $personnel = $r['personnel'];
    $accounts = $r['accounts']; $movements = $r['movements']; $checks = $r['checks']; $quotes = $r['quotes'];
    $documents = $r['documents']; $pages = $r['pages'];
    $files = $r['files']; $tasks = $r['tasks']; $users = $r['users']; $notes = $r['notes']; $messages = $r['messages'];
    $found = search_total_count($r);
    $pageTargets = ['contacts_report'=>'contacts_report.php', 'report'=>'report.php', 'accounting'=>'accounting.php', 'takvim'=>'takvim.php'];
?>
<p style="color:#667085;margin-bottom:20px">"<b><?=htmlspecialchars($q)?></b>" için <?=$found?> sonuç</p>

<?php if ($found === 0): ?>
<div style="text-align:center;color:#667085;padding:40px 0">
    <div style="font-size:48px;margin-bottom:12px">😶</div>
    <p>Sonuç bulunamadı.</p>
</div>
<?php endif; ?>

<?php if ($pages): ?>
<div class="panel" style="margin-bottom:20px">
    <div style="font-weight:900;font-size:16px;margin-bottom:12px">🔗 Sayfalar (<?=count($pages)?>)</div>
    <table style="width:100%;border-collapse:collapse">
    <?php foreach($pages as $pg): $href = $pageTargets[$pg['target']] ?? null; if (!$href) continue; ?>
    <tr style="border-bottom:1px solid #f1f5f9">
        <td style="padding:10px 8px">
            <a href="<?=htmlspecialchars($href)?>" style="color:#101828;text-decoration:none;font-weight:700">
                <?=$pg['icon']?> <?=htmlspecialchars($pg['label'])?>
            </a>
        </td>
    </tr>
    <?php endforeach; ?>
    </table>
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
                <?=search_hl($j['title'],$q)?>
            </a>
            <div style="font-size:12px;color:#667085;margin-top:2px">
                <?php if($j['job_no']): ?><?=search_hl($j['job_no'],$q)?> · <?php endif; ?>
                <?php if($j['customer']): ?>👤 <?=search_hl($j['customer'],$q)?><?php endif; ?>
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
                <?=search_hl($c['name'],$q)?>
            </a>
            <div style="font-size:12px;color:#667085;margin-top:2px">
                <?php if($c['phone']): ?><?=search_hl($c['phone'],$q)?><?php endif; ?>
                <?php if($c['city']): ?> · <?=search_hl($c['city'],$q)?><?php endif; ?>
            </div>
        </td>
        <td style="padding:10px 8px;text-align:right">
            <?php if($c['email']): ?><span style="font-size:12px;color:#667085"><?=search_hl($c['email'],$q)?></span><?php endif; ?>
        </td>
    </tr>
    <?php endforeach; ?>
    </table>
</div>
<?php endif; ?>

<?php if ($accounts): ?>
<div class="panel" style="margin-bottom:20px">
    <div style="font-weight:900;font-size:16px;margin-bottom:12px">🏦 Finans Hesapları (<?=count($accounts)?>)</div>
    <table style="width:100%;border-collapse:collapse">
    <?php foreach($accounts as $a): ?>
    <tr style="border-bottom:1px solid #f1f5f9">
        <td style="padding:10px 8px">
            <a href="finance_account_view.php?id=<?=$a['id']?>" style="color:#101828;text-decoration:none;font-weight:700">
                <?=search_hl($a['name'],$q)?>
            </a>
            <div style="font-size:12px;color:#667085;margin-top:2px">
                <?=search_hl($a['account_type'],$q)?>
                <?php if($a['bank_name']): ?> · <?=search_hl($a['bank_name'],$q)?><?php endif; ?>
                <?php if($a['iban']): ?> · <?=search_hl($a['iban'],$q)?><?php endif; ?>
            </div>
        </td>
        <td style="padding:10px 8px;text-align:right;white-space:nowrap;font-weight:700">
            <?=money($a['current_balance'])?>
        </td>
    </tr>
    <?php endforeach; ?>
    </table>
</div>
<?php endif; ?>

<?php if ($movements): ?>
<div class="panel" style="margin-bottom:20px">
    <div style="font-weight:900;font-size:16px;margin-bottom:12px">💸 Finans Hareketleri (<?=count($movements)?>)</div>
    <table style="width:100%;border-collapse:collapse">
    <?php foreach($movements as $m):
        $dColor = $m['direction']==='in' ? '#16a34a' : '#dc2626';
    ?>
    <tr style="border-bottom:1px solid #f1f5f9">
        <td style="padding:10px 8px">
            <a href="finance.php" style="color:#101828;text-decoration:none;font-weight:700">
                <?=$m['description'] ? search_hl($m['description'],$q) : htmlspecialchars($m['payment_channel'])?>
            </a>
            <div style="font-size:12px;color:#667085;margin-top:2px">
                <?=search_hl($m['payment_channel'],$q)?>
                <?php if($m['contact_name']): ?> · 👤 <?=search_hl($m['contact_name'],$q)?><?php endif; ?>
                <?php if($m['movement_date']): ?> · <?=htmlspecialchars($m['movement_date'])?><?php endif; ?>
            </div>
        </td>
        <td style="padding:10px 8px;text-align:right;white-space:nowrap;font-weight:700;color:<?=$dColor?>">
            <?=money($m['amount'])?>
        </td>
    </tr>
    <?php endforeach; ?>
    </table>
</div>
<?php endif; ?>

<?php if ($checks): ?>
<div class="panel" style="margin-bottom:20px">
    <div style="font-weight:900;font-size:16px;margin-bottom:12px">🧾 Çek / Senet (<?=count($checks)?>)</div>
    <table style="width:100%;border-collapse:collapse">
    <?php foreach($checks as $k):
        $typeLabel = $k['type']==='senet' ? 'Senet' : 'Çek';
    ?>
    <tr style="border-bottom:1px solid #f1f5f9">
        <td style="padding:10px 8px">
            <a href="checks_notes.php?type=<?=htmlspecialchars($k['type'])?>" style="color:#101828;text-decoration:none;font-weight:700">
                <?=$typeLabel?> <?=$k['number'] ? search_hl($k['number'],$q) : ''?>
            </a>
            <div style="font-size:12px;color:#667085;margin-top:2px">
                <?php if($k['bank_name']): ?><?=search_hl($k['bank_name'],$q)?> · <?php endif; ?>
                <?php if($k['contact_name']): ?>👤 <?=search_hl($k['contact_name'],$q)?> · <?php endif; ?>
                <?php if($k['due_date']): ?>Vade: <?=htmlspecialchars($k['due_date'])?><?php endif; ?>
            </div>
        </td>
        <td style="padding:10px 8px;text-align:right;white-space:nowrap;font-weight:700">
            <?=money($k['amount'])?>
        </td>
    </tr>
    <?php endforeach; ?>
    </table>
</div>
<?php endif; ?>

<?php if ($documents): ?>
<div class="panel" style="margin-bottom:20px">
    <div style="font-weight:900;font-size:16px;margin-bottom:12px">🧾 Ticari Belgeler (Alış/Satış) (<?=count($documents)?>)</div>
    <table style="width:100%;border-collapse:collapse">
    <?php foreach($documents as $d):
        $docTypeLabel = $d['document_type']==='sale' ? 'Satış' : ($d['document_type']==='purchase' ? 'Alış' : htmlspecialchars($d['document_type']));
        $remaining = (float)$d['grand_total'] - (float)$d['paid_amount'];
    ?>
    <tr style="border-bottom:1px solid #f1f5f9">
        <td style="padding:10px 8px">
            <a href="trade_document_view.php?id=<?=$d['id']?>" style="color:#101828;text-decoration:none;font-weight:700">
                <?=$docTypeLabel?> <?=$d['document_no'] ? search_hl($d['document_no'],$q) : ''?>
            </a>
            <div style="font-size:12px;color:#667085;margin-top:2px">
                <?php if($d['contact_name']): ?>👤 <?=search_hl($d['contact_name'],$q)?> · <?php endif; ?>
                <?php if($d['document_date']): ?><?=htmlspecialchars($d['document_date'])?><?php endif; ?>
            </div>
        </td>
        <td style="padding:10px 8px;text-align:right;white-space:nowrap">
            <div style="font-weight:700"><?=money($d['grand_total'])?></div>
            <?php if($remaining > 0.009): ?>
            <div style="font-size:11px;color:#dc2626;margin-top:2px">Kalan: <?=money($remaining)?></div>
            <?php endif; ?>
        </td>
    </tr>
    <?php endforeach; ?>
    </table>
</div>
<?php endif; ?>

<?php if ($quotes): ?>
<div class="panel" style="margin-bottom:20px">
    <div style="font-weight:900;font-size:16px;margin-bottom:12px">📄 Teklifler (<?=count($quotes)?>)</div>
    <table style="width:100%;border-collapse:collapse">
    <?php foreach($quotes as $t): ?>
    <tr style="border-bottom:1px solid #f1f5f9">
        <td style="padding:10px 8px">
            <a href="teklif.php?id=<?=$t['id']?>" style="color:#101828;text-decoration:none;font-weight:700">
                <?=search_hl($t['quote_no'],$q)?>
            </a>
            <div style="font-size:12px;color:#667085;margin-top:2px">
                <?php if($t['customer_name']): ?>👤 <?=search_hl($t['customer_name'],$q)?><?php endif; ?>
                · <?=htmlspecialchars($t['status'])?>
            </div>
        </td>
        <td style="padding:10px 8px;text-align:right;white-space:nowrap;font-weight:700">
            <?=money($t['total'])?>
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
                <?=search_hl($p['name'],$q)?>
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
            <a href="personnel_edit.php?id=<?=$p['id']?>" style="color:#101828;text-decoration:none;font-weight:700">
                <?=search_hl($p['name'],$q)?>
            </a>
            <div style="font-size:12px;color:#667085;margin-top:2px">
                <?php if($p['role']): ?><?=search_hl($p['role'],$q)?><?php endif; ?>
                <?php if($p['work_type']): ?> · <?=search_hl($p['work_type'],$q)?><?php endif; ?>
            </div>
        </td>
        <td style="padding:10px 8px;text-align:right;font-size:12px;color:#667085">
            <?php if($p['phone']): ?><?=search_hl($p['phone'],$q)?><?php endif; ?>
        </td>
    </tr>
    <?php endforeach; ?>
    </table>
</div>
<?php endif; ?>

<?php if ($tasks): ?>
<div class="panel" style="margin-bottom:20px">
    <div style="font-weight:900;font-size:16px;margin-bottom:12px">✅ Görevler (<?=count($tasks)?>)</div>
    <table style="width:100%;border-collapse:collapse">
    <?php foreach($tasks as $t):
        $taskHref = $t['job_id'] ? "job_view.php?id=".(int)$t['job_id'] : "tasks.php";
    ?>
    <tr style="border-bottom:1px solid #f1f5f9">
        <td style="padding:10px 8px">
            <a href="<?=htmlspecialchars($taskHref)?>" style="color:#101828;text-decoration:none;font-weight:700">
                <?=search_hl($t['title'],$q)?>
            </a>
            <div style="font-size:12px;color:#667085;margin-top:2px">
                <?php if($t['job_no']): ?><?=htmlspecialchars($t['job_no'])?> · <?php endif; ?>
                <?php if($t['personnel_name']): ?>👤 <?=search_hl($t['personnel_name'],$q)?><?php endif; ?>
            </div>
        </td>
        <td style="padding:10px 8px;text-align:right;white-space:nowrap">
            <span style="background:#2563eb18;color:#2563eb;border-radius:20px;padding:4px 10px;font-size:12px;font-weight:700">
                <?=htmlspecialchars($t['status'])?>
            </span>
        </td>
    </tr>
    <?php endforeach; ?>
    </table>
</div>
<?php endif; ?>

<?php if ($files): ?>
<div class="panel" style="margin-bottom:20px">
    <div style="font-weight:900;font-size:16px;margin-bottom:12px">📁 Dosyalar (<?=count($files)?>)</div>
    <table style="width:100%;border-collapse:collapse">
    <?php foreach($files as $f): ?>
    <tr style="border-bottom:1px solid #f1f5f9">
        <td style="padding:10px 8px">
            <a href="job_view.php?id=<?=(int)$f['job_id']?>" style="color:#101828;text-decoration:none;font-weight:700">
                <?=search_hl($f['original_name'],$q)?>
            </a>
            <div style="font-size:12px;color:#667085;margin-top:2px">
                <?php if($f['job_no']): ?><?=search_hl($f['job_no'],$q)?> · <?php endif; ?>
                <?php if($f['job_title']): ?><?=search_hl($f['job_title'],$q)?><?php endif; ?>
            </div>
        </td>
        <td style="padding:10px 8px;text-align:right;font-size:12px;color:#667085">
            <?=htmlspecialchars($f['approval_status'] ?? '')?>
        </td>
    </tr>
    <?php endforeach; ?>
    </table>
</div>
<?php endif; ?>

<?php if ($users): ?>
<div class="panel" style="margin-bottom:20px">
    <div style="font-weight:900;font-size:16px;margin-bottom:12px">🧑‍💻 Kullanıcılar (<?=count($users)?>)</div>
    <table style="width:100%;border-collapse:collapse">
    <?php foreach($users as $u): ?>
    <tr style="border-bottom:1px solid #f1f5f9">
        <td style="padding:10px 8px">
            <a href="users.php" style="color:#101828;text-decoration:none;font-weight:700">
                <?=search_hl($u['full_name'] ?: $u['username'],$q)?>
            </a>
            <div style="font-size:12px;color:#667085;margin-top:2px">
                <?=search_hl($u['username'],$q)?>
                <?php if($u['phone']): ?> · <?=search_hl($u['phone'],$q)?><?php endif; ?>
            </div>
        </td>
        <td style="padding:10px 8px;text-align:right;font-size:12px;color:#667085">
            <?=htmlspecialchars($u['role'])?>
        </td>
    </tr>
    <?php endforeach; ?>
    </table>
</div>
<?php endif; ?>

<?php if ($notes): ?>
<div class="panel" style="margin-bottom:20px">
    <div style="font-weight:900;font-size:16px;margin-bottom:12px">📝 Notlarım (<?=count($notes)?>)</div>
    <table style="width:100%;border-collapse:collapse">
    <?php foreach($notes as $n): ?>
    <tr style="border-bottom:1px solid #f1f5f9">
        <td style="padding:10px 8px">
            <a href="notes.php" style="color:#101828;text-decoration:none;font-weight:700">
                <?=search_hl($n['title'],$q)?>
            </a>
            <?php if($n['note']): ?><div style="font-size:12px;color:#667085;margin-top:2px"><?=search_hl(mb_substr($n['note'],0,80),$q)?></div><?php endif; ?>
        </td>
        <td style="padding:10px 8px;text-align:right;font-size:12px;color:#667085">
            <?=htmlspecialchars($n['status'])?>
        </td>
    </tr>
    <?php endforeach; ?>
    </table>
</div>
<?php endif; ?>

<?php if ($messages): ?>
<div class="panel" style="margin-bottom:20px">
    <div style="font-weight:900;font-size:16px;margin-bottom:12px">💬 Mesajlar (<?=count($messages)?>)</div>
    <table style="width:100%;border-collapse:collapse">
    <?php foreach($messages as $m):
        $msgHref = $m['thread_id'] ? "messages.php?thread=".(int)$m['thread_id'] : "messages.php?u=".(int)$m['with_user_id'];
    ?>
    <tr style="border-bottom:1px solid #f1f5f9">
        <td style="padding:10px 8px">
            <a href="<?=htmlspecialchars($msgHref)?>" style="color:#101828;text-decoration:none;font-weight:700">
                <?=search_hl(mb_substr($m['message'],0,80),$q)?>
            </a>
            <div style="font-size:12px;color:#667085;margin-top:2px">
                💬 <?=htmlspecialchars($m['with_label'] ?: '-')?>
            </div>
        </td>
    </tr>
    <?php endforeach; ?>
    </table>
</div>
<?php endif; ?>

<?php endif; ?>

<?php require_once __DIR__.'/layout_bottom.php'; ?>
