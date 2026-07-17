<?php
require_once __DIR__.'/layout_top.php';
require_once __DIR__.'/search_lib.php';
$q = trim($_GET['q'] ?? '');
$pdo = db();

/* ═══════════════════════════════════════════════════════════════════════
 * FAZ 2B-ii-R/2 (2026-07-17) — SEARCH REFERANS EKRAN DÖNÜŞÜMÜ.
 * Yalnızca RENDER katmanı değişti — search_run()/search_hl() dokunulmadı, arama algoritması/yetki
 * filtreleri/URL yapıları R/2 kapsamı dışında (Product Owner kuralı 1). Legacy dal (aşağıdaki
 * else:) 2026-07-04'ten beri var olan orijinal markup'ın BİREBİR kopyası.
 *
 * FAZ 2B-ii-R/2b (2026-07-17) — AKILLI ARAMA. Elle yazılmış $pageTargets haritası (4 sabit girdi)
 * KALDIRILDI — 'pages' artık nav_lib.php::nav_search_index()'in TEK KAYNAK kataloğuna bağlı
 * (search_module_shortcuts()), href de aynı tek kaynaktan (nav_module_by_key()+
 * nav_url_for_platform()) çözülüyor. Elle kopyalanmış bir URL haritasının zamanla sapması
 * (PARITY-003 sınıfı hata) burada bir daha açılmadı.
 * ═══════════════════════════════════════════════════════════════════════ */

if ($__navMode !== 'legacy'): // ── COMPACT ──────────────────────────────────────────────────

ds_page_header('Arama', ds_icon('search',24), '', '', false, true);

if ($q === ''):
    ds_empty_state('Aramaya başlayın', 'İş, cari, banka/kart, işlem, çek/senet, teklif, belge, stok, personel, görev, kullanıcı, not veya mesaj aramak için yukarıdaki arama kutusunu kullanın.', ds_icon('search',40));
else:
    $__searchErr = false;
    try {
        $r = search_run($pdo, $q);
        $found = search_total_count($r);
    } catch (Throwable $e) {
        $__searchErr = true; $found = 0; $r = null;
    }

    if ($__searchErr):
        echo ds_alert('danger', 'Arama sırasında bir hata oluştu. Lütfen tekrar deneyin.');
    else:
        $jobs = $r['jobs']; $contacts = $r['contacts']; $stock = $r['stock']; $personnel = $r['personnel'];
        $accounts = $r['accounts']; $movements = $r['movements']; $checks = $r['checks']; $quotes = $r['quotes'];
        $documents = $r['documents']; $pages = $r['pages'];
        $files = $r['files']; $tasks = $r['tasks']; $users = $r['users']; $notes = $r['notes']; $messages = $r['messages'];

        echo '<div class="df-text-caption" style="margin-bottom:var(--df-space-4)">&quot;'.h($q).'&quot; için '.(int)$found.' sonuç</div>';

        if ($found === 0):
            ds_empty_state('Sonuç bulunamadı', '"'.$q.'" için hiçbir kayıt bulunamadı. Arama yalnızca yetkili olduğunuz alanlarda yapılır — bazı sonuçlar yetki kısıtı nedeniyle görünmüyor olabilir.', ds_icon('search',40));
        else:

        if ($pages): ?>
        <div class="df-text-section" style="margin-bottom:var(--df-space-2)">Sayfalar (<?=count($pages)?>)</div>
        <div class="df-list" style="margin-bottom:var(--df-space-5)">
        <?php foreach($pages as $pg): $__navItem = nav_module_by_key($pg['target']); $href = $__navItem ? nav_url_for_platform($__navItem, 'web') : null; if (!$href) continue;
            ds_list_item(h($pg['label']), $href);
        endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if ($jobs): ?>
        <div class="df-text-section" style="margin-bottom:var(--df-space-2)">İşler (<?=count($jobs)?>)</div>
        <div class="df-list" style="margin-bottom:var(--df-space-5)">
        <?php foreach($jobs as $j):
            $__desc = ds_highlight($j['job_no'] ?: '', $q);
            if ($j['customer']) $__desc .= ($__desc !== '' ? ' · ' : '').ds_highlight($j['customer'], $q);
            ds_list_item(ds_highlight($j['title'], $q), 'job_view.php?id='.(int)$j['id'], $__desc, ds_badge($j['status']));
        endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if ($contacts): ?>
        <div class="df-text-section" style="margin-bottom:var(--df-space-2)">Cariler (<?=count($contacts)?>)</div>
        <div class="df-list" style="margin-bottom:var(--df-space-5)">
        <?php foreach($contacts as $c):
            $__desc = ds_highlight($c['phone'] ?: '', $q);
            if ($c['city']) $__desc .= ($__desc !== '' ? ' · ' : '').ds_highlight($c['city'], $q);
            $__meta = $c['email'] ? '<span class="df-list-row-due">'.h($c['email']).'</span>' : null;
            ds_list_item(ds_highlight($c['name'], $q), 'contact_view.php?id='.(int)$c['id'], $__desc, $__meta);
        endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if ($accounts): ?>
        <div class="df-text-section" style="margin-bottom:var(--df-space-2)">Finans Hesapları (<?=count($accounts)?>)</div>
        <div class="df-list" style="margin-bottom:var(--df-space-5)">
        <?php foreach($accounts as $a):
            $__desc = ds_highlight($a['account_type'], $q);
            if ($a['bank_name']) $__desc .= ' · '.ds_highlight($a['bank_name'], $q);
            if ($a['iban']) $__desc .= ' · '.ds_highlight($a['iban'], $q);
            $__meta = '<span class="df-text-tabular">'.h(money($a['current_balance'])).'</span>';
            ds_list_item(ds_highlight($a['name'], $q), 'finance_account_view.php?id='.(int)$a['id'], $__desc, $__meta);
        endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if ($movements): ?>
        <div class="df-text-section" style="margin-bottom:var(--df-space-2)">Finans Hareketleri (<?=count($movements)?>)</div>
        <div class="df-list" style="margin-bottom:var(--df-space-5)">
        <?php foreach($movements as $m):
            $__title = $m['description'] ? ds_highlight($m['description'], $q) : h($m['payment_channel']);
            $__desc = ds_highlight($m['payment_channel'], $q);
            if ($m['contact_name']) $__desc .= ' · '.ds_highlight($m['contact_name'], $q);
            if ($m['movement_date']) $__desc .= ' · '.h($m['movement_date']);
            $__dTone = $m['direction']==='in' ? 'success' : 'danger';
            $__meta = '<span class="df-badge df-badge--'.$__dTone.' df-text-tabular">'.h(money($m['amount'])).'</span>';
            ds_list_item($__title, 'finance.php', $__desc, $__meta);
        endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if ($checks): ?>
        <div class="df-text-section" style="margin-bottom:var(--df-space-2)">Çek / Senet (<?=count($checks)?>)</div>
        <div class="df-list" style="margin-bottom:var(--df-space-5)">
        <?php foreach($checks as $k):
            $typeLabel = $k['type']==='senet' ? 'Senet' : 'Çek';
            $__title = $typeLabel.($k['number'] ? ' '.$k['number'] : '');
            $__desc = '';
            if ($k['bank_name']) $__desc .= ds_highlight($k['bank_name'], $q);
            if ($k['contact_name']) $__desc .= ($__desc !== '' ? ' · ' : '').ds_highlight($k['contact_name'], $q);
            if ($k['due_date']) $__desc .= ($__desc !== '' ? ' · ' : '').'Vade: '.h($k['due_date']);
            $__meta = '<span class="df-text-tabular">'.h(money($k['amount'])).'</span>';
            ds_list_item(ds_highlight($__title, $q), 'checks_notes.php?type='.h($k['type']), $__desc, $__meta);
        endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if ($documents): ?>
        <div class="df-text-section" style="margin-bottom:var(--df-space-2)">Ticari Belgeler — Alış/Satış (<?=count($documents)?>)</div>
        <div class="df-list" style="margin-bottom:var(--df-space-5)">
        <?php foreach($documents as $d):
            $docTypeLabel = $d['document_type']==='sale' ? 'Satış' : ($d['document_type']==='purchase' ? 'Alış' : $d['document_type']);
            $__title = $docTypeLabel.($d['document_no'] ? ' '.$d['document_no'] : '');
            $__desc = '';
            if ($d['contact_name']) $__desc .= ds_highlight($d['contact_name'], $q);
            if ($d['document_date']) $__desc .= ($__desc !== '' ? ' · ' : '').h($d['document_date']);
            $remaining = (float)$d['grand_total'] - (float)$d['paid_amount'];
            $__meta = '<span class="df-text-tabular">'.h(money($d['grand_total'])).'</span>';
            if ($remaining > 0.009) $__meta .= '<span class="df-badge df-badge--danger">Kalan '.h(money($remaining)).'</span>';
            ds_list_item(ds_highlight($__title, $q), 'trade_document_view.php?id='.(int)$d['id'], $__desc, $__meta);
        endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if ($quotes): ?>
        <div class="df-text-section" style="margin-bottom:var(--df-space-2)">Teklifler (<?=count($quotes)?>)</div>
        <div class="df-list" style="margin-bottom:var(--df-space-5)">
        <?php foreach($quotes as $t):
            $__desc = $t['customer_name'] ? ds_highlight($t['customer_name'], $q).' · '.h($t['status']) : h($t['status']);
            $__meta = '<span class="df-text-tabular">'.h(money($t['total'])).'</span>';
            ds_list_item(ds_highlight($t['quote_no'], $q), 'teklif.php?id='.(int)$t['id'], $__desc, $__meta);
        endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if ($stock): ?>
        <div class="df-text-section" style="margin-bottom:var(--df-space-2)">Stok (<?=count($stock)?>)</div>
        <div class="df-list" style="margin-bottom:var(--df-space-5)">
        <?php foreach($stock as $p):
            $__qty = rtrim(rtrim(number_format((float)$p['quantity'],2,',','.'),'0'),',').' '.($p['unit']??'');
            $__meta = '<span class="df-text-tabular">'.h($__qty).'</span>';
            if ($p['sale_price']>0) $__meta .= '<span class="df-text-tabular">'.number_format((float)$p['sale_price'],2,',','.').' ₺</span>';
            ds_list_item(ds_highlight($p['name'], $q), 'product_view.php?id='.(int)$p['id'], null, $__meta);
        endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if ($personnel): ?>
        <div class="df-text-section" style="margin-bottom:var(--df-space-2)">Personel (<?=count($personnel)?>)</div>
        <div class="df-list" style="margin-bottom:var(--df-space-5)">
        <?php foreach($personnel as $p):
            $__desc = ds_highlight($p['role'] ?: '', $q);
            if ($p['work_type']) $__desc .= ($__desc !== '' ? ' · ' : '').ds_highlight($p['work_type'], $q);
            $__meta = $p['phone'] ? '<span class="df-list-row-due">'.h($p['phone']).'</span>' : null;
            ds_list_item(ds_highlight($p['name'], $q), 'personnel_edit.php?id='.(int)$p['id'], $__desc, $__meta);
        endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if ($tasks): ?>
        <div class="df-text-section" style="margin-bottom:var(--df-space-2)">Görevler (<?=count($tasks)?>)</div>
        <div class="df-list" style="margin-bottom:var(--df-space-5)">
        <?php foreach($tasks as $t):
            $__desc = $t['job_no'] ? h($t['job_no']) : '';
            if ($t['personnel_name']) $__desc .= ($__desc !== '' ? ' · ' : '').ds_highlight($t['personnel_name'], $q);
            ds_list_item(ds_highlight($t['title'], $q), 'task_view.php?id='.(int)$t['id'], $__desc, ds_badge($t['status']));
        endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if ($files): ?>
        <div class="df-text-section" style="margin-bottom:var(--df-space-2)">Dosyalar (<?=count($files)?>)</div>
        <div class="df-list" style="margin-bottom:var(--df-space-5)">
        <?php foreach($files as $f):
            $__desc = $f['job_no'] ? ds_highlight($f['job_no'], $q) : '';
            if ($f['job_title']) $__desc .= ($__desc !== '' ? ' · ' : '').ds_highlight($f['job_title'], $q);
            $__meta = $f['approval_status'] ? '<span class="df-list-row-due">'.h($f['approval_status']).'</span>' : null;
            ds_list_item(ds_highlight($f['original_name'], $q), 'job_view.php?id='.(int)$f['job_id'], $__desc, $__meta);
        endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if ($users): ?>
        <div class="df-text-section" style="margin-bottom:var(--df-space-2)">Kullanıcılar (<?=count($users)?>)</div>
        <div class="df-list" style="margin-bottom:var(--df-space-5)">
        <?php foreach($users as $u):
            $__desc = ds_highlight($u['username'], $q);
            if ($u['phone']) $__desc .= ' · '.ds_highlight($u['phone'], $q);
            $__meta = '<span class="df-list-row-due">'.h($u['role']).'</span>';
            ds_list_item(ds_highlight($u['full_name'] ?: $u['username'], $q), 'users.php', $__desc, $__meta);
        endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if ($notes): ?>
        <div class="df-text-section" style="margin-bottom:var(--df-space-2)">Notlarım (<?=count($notes)?>)</div>
        <div class="df-list" style="margin-bottom:var(--df-space-5)">
        <?php foreach($notes as $n):
            $__desc = $n['note'] ? ds_highlight(mb_substr($n['note'],0,80), $q) : null;
            $__meta = '<span class="df-list-row-due">'.h($n['status']).'</span>';
            ds_list_item(ds_highlight($n['title'], $q), 'notes.php', $__desc, $__meta);
        endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if ($messages): ?>
        <div class="df-text-section" style="margin-bottom:var(--df-space-2)">Mesajlar (<?=count($messages)?>)</div>
        <div class="df-list" style="margin-bottom:var(--df-space-5)">
        <?php foreach($messages as $m):
            $msgHref = $m['thread_id'] ? "messages.php?thread=".(int)$m['thread_id'] : "messages.php?u=".(int)$m['with_user_id'];
            ds_list_item(ds_highlight(mb_substr($m['message'],0,80), $q), $msgHref, h($m['with_label'] ?: '-'));
        endforeach; ?>
        </div>
        <?php endif; ?>

        <?php endif; // found === 0 ?>
    <?php endif; // $__searchErr ?>
<?php endif; // $q === '' ?>

<?php else: // ── LEGACY — orijinal ekran, 2026-07-04'ten beri birebir aynı ────────────────── ?>
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
    <?php foreach($pages as $pg): $__navItem = nav_module_by_key($pg['target']); $href = $__navItem ? nav_url_for_platform($__navItem, 'web') : null; if (!$href) continue; ?>
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
        $taskHref = "task_view.php?id=".(int)$t['id'];
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

<?php endif; // $__navMode ?>

<?php require_once __DIR__.'/layout_bottom.php'; ?>
