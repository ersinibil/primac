<?php
require_once 'common.php';
require_once dirname(__DIR__).'/search_lib.php';
$pdo = db();
$q = trim($_GET['q'] ?? '');

topx('Arama');
?>
<form method="get" style="display:flex;gap:8px;margin-bottom:6px">
    <input name="q" value="<?=h($q)?>" placeholder="İş, cari, banka/kart, işlem, çek/senet, teklif, belge, stok, personel, görev, kullanıcı, not, mesaj…" autofocus autocomplete="off" style="margin:0;flex:1;min-width:0;width:auto">
    <button class="df-btn df-btn--primary" type="submit" style="flex:0 0 auto"><?=ds_icon('search',16)?> Ara</button>
</form>

<?php if ($q === ''): ?>
<?php ds_empty_state('Aramaya başlayın', 'İş no, müşteri adı, banka/kart, işlem, çek/senet, teklif, belge, ürün, personel, görev, kullanıcı, not veya mesaj metni girin.', ds_icon('search',32)); ?>
<?php else:
    $r = search_run($pdo, $q, 'mobile');
    $jobs = $r['jobs']; $contacts = $r['contacts']; $stock = $r['stock']; $personnel = $r['personnel'];
    $accounts = $r['accounts']; $movements = $r['movements']; $checks = $r['checks']; $quotes = $r['quotes'];
    $documents = $r['documents']; $pages = $r['pages'];
    $files = $r['files']; $tasks = $r['tasks']; $users = $r['users']; $notes = $r['notes']; $messages = $r['messages'];
    $found = search_total_count($r);
    // FAZ 2B-ii-R/2b (2026-07-17): elle yazılmış $pageTargets (4 sabit girdi) kaldırıldı — href
    // artık nav_lib.php::nav_module_by_key()+nav_url_for_platform('mobile') üzerinden çözülüyor
    // (TEK KAYNAK, PARITY-003 sınıfı sapma riski yok). search_run()'a 3. parametre olarak 'mobile'
    // verildi ki nav_search_index() mobileHide filtresini doğru platformla uygulasın.
?>
<div class="df-text-caption" style="margin-bottom:var(--df-space-4)">&quot;<?=h($q)?>&quot; için <?=(int)$found?> sonuç</div>

<?php if ($found === 0): ?>
<?php ds_empty_state('Sonuç bulunamadı', '"'.$q.'" için hiçbir kayıt bulunamadı.', ds_icon('search',32)); ?>
<?php endif; ?>

<?php if ($pages): ?>
<div class="df-text-section" style="margin-bottom:var(--df-space-2)">Sayfalar (<?=count($pages)?>)</div>
<div class="df-list" style="margin-bottom:var(--df-space-5)">
<?php foreach($pages as $pg): $__navItem = nav_module_by_key($pg['target']); $href = $__navItem ? nav_url_for_platform($__navItem, 'mobile') : null; if (!$href) continue;
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
    $__meta = '<span class="df-text-tabular">'.h(mm($a['current_balance'])).'</span>';
    ds_list_item(ds_highlight($a['name'], $q), 'account_view.php?id='.(int)$a['id'], $__desc, $__meta);
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
    $__dTone = $m['direction']==='in' ? 'success' : 'danger';
    $__meta = '<span class="df-badge df-badge--'.$__dTone.' df-text-tabular">'.h(mm($m['amount'])).'</span>';
    ds_list_item($__title, 'movement_view.php?id='.(int)$m['id'], $__desc, $__meta);
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
    $__meta = '<span class="df-text-tabular">'.h(mm($k['amount'])).'</span>';
    ds_list_item(ds_highlight($__title, $q), 'check_note_view.php?id='.(int)$k['id'], $__desc, $__meta);
endforeach; ?>
</div>
<?php endif; ?>

<?php if ($documents): ?>
<div class="df-text-section" style="margin-bottom:var(--df-space-2)">Ticari Belgeler — Alış/Satış (<?=count($documents)?>)</div>
<div class="df-list" style="margin-bottom:var(--df-space-5)">
<?php foreach($documents as $d):
    $docTypeLabel = $d['document_type']==='sale' ? 'Satış' : ($d['document_type']==='purchase' ? 'Alış' : h($d['document_type']));
    $__title = $docTypeLabel.($d['document_no'] ? ' '.$d['document_no'] : '');
    $__desc = $d['contact_name'] ? ds_highlight($d['contact_name'], $q) : '';
    $remaining = (float)$d['grand_total'] - (float)$d['paid_amount'];
    $__meta = '<span class="df-text-tabular">'.h(mm($d['grand_total'])).'</span>';
    if ($remaining > 0.009) $__meta .= '<span class="df-badge df-badge--danger">Kalan '.h(mm($remaining)).'</span>';
    // Mobilde belge detay ekranı yok — orijinal davranış korunuyor (tıklanamaz satır).
    ds_list_item(ds_highlight($__title, $q), null, $__desc, $__meta);
endforeach; ?>
</div>
<?php endif; ?>

<?php if ($quotes): ?>
<div class="df-text-section" style="margin-bottom:var(--df-space-2)">Teklifler (<?=count($quotes)?>)</div>
<div class="df-list" style="margin-bottom:var(--df-space-5)">
<?php foreach($quotes as $t):
    $__desc = $t['customer_name'] ? ds_highlight($t['customer_name'], $q).' · '.h($t['status']) : h($t['status']);
    $__meta = '<span class="df-text-tabular">'.h(mm($t['total'])).'</span>';
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
    $__meta = $p['phone'] ? '<span class="df-list-row-due">'.ds_highlight($p['phone'], $q).'</span>' : null;
    ds_list_item(ds_highlight($p['name'], $q), 'personnel_view.php?id='.(int)$p['id'], $__desc, $__meta);
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
    ds_list_item(ds_highlight($f['original_name'], $q), 'job_view.php?id='.(int)$f['job_id'], $__desc);
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
    $__desc = $n['note'] ? ds_highlight(mb_substr($n['note'],0,60),$q) : null;
    $__meta = '<span class="df-list-row-due">'.h($n['status']).'</span>';
    ds_list_item(ds_highlight($n['title'], $q), 'mytasks.php', $__desc, $__meta);
endforeach; ?>
</div>
<?php endif; ?>

<?php if ($messages): ?>
<div class="df-text-section" style="margin-bottom:var(--df-space-2)">Mesajlar (<?=count($messages)?>)</div>
<div class="df-list" style="margin-bottom:var(--df-space-5)">
<?php foreach($messages as $m):
    $msgHref = $m['thread_id'] ? 'messages.php?thread='.(int)$m['thread_id'] : 'messages.php?with='.(int)$m['with_user_id'];
    ds_list_item(ds_highlight(mb_substr($m['message'],0,60),$q), $msgHref, h($m['with_label'] ?: '-'));
endforeach; ?>
</div>
<?php endif; ?>

<?php endif; ?>
<?php botx(); ?>
