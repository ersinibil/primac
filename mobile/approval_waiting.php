<?php
require_once 'common.php';

topx('Müşteri Onayı');
?>
<div class="df-panel" style="padding:10px 12px 8px">
  <div style="color:var(--df-ink-500,#94a3b8);font-size:13px">Müşteri onayı bekleyen dosyalar — <b style="color:#fbbf24">Müşteri Onayı Bekliyor</b> durumundaki kayıtlar listeleniyor.</div>
</div>
<?php
try {
    $st = db()->query(
        "SELECT f.*, j.job_no, j.title AS job_title
         FROM job_files f
         LEFT JOIN jobs j ON j.id = f.job_id
         WHERE f.approval_status = 'Müşteri Onayı Bekliyor'
         ORDER BY f.id DESC"
    );
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);
    if (!$rows) {
        ds_empty_state('Onay bekleyen dosya yok.', null, ds_icon('info',28));
    }
    foreach ($rows as $r) {
        $share = base_url().'public_file.php?token=' . h($r['share_token'] ?? '');
        $tarih = h(substr($r['created_at'] ?? '', 0, 10));
        echo '<div class="df-panel" style="padding:13px">';
        // Başlık satırı: iş no + başlık
        echo '<a href="job_view.php?id=' . (int)$r['job_id'] . '" style="text-decoration:none;color:var(--df-ink-900,#fff);display:block;margin-bottom:6px">';
        echo '<b>' . h($r['job_title'] ?? '') . '</b>';
        echo '<small class="muted" style="display:block;margin-top:2px">';
        echo ds_icon('briefcase',13) . ' ' . h($r['job_no'] ?? '');
        if ($tarih) echo ' &nbsp;·&nbsp; ' . ds_icon('calendar',13) . ' ' . $tarih;
        echo '</small>';
        echo '</a>';
        // Dosya bilgisi
        echo '<div style="background:var(--df-surface-sunken,rgba(255,255,255,.07));border-radius:12px;padding:10px;margin-bottom:10px">';
        echo '<div style="font-size:13px;color:var(--df-ink-900,#e2e8f0)">' . ds_icon('box',14) . ' ' . h($r['original_name'] ?? '') . '</div>';
        if (!empty($r['file_type'])) {
            echo '<div style="font-size:12px;color:var(--df-ink-500,#94a3b8);margin-top:3px">Tür: ' . h($r['file_type']) . '</div>';
        }
        echo '</div>';
        // Durum rozeti + müşteri linki
        echo '<div style="display:flex;justify-content:space-between;align-items:center;gap:8px">';
        echo '<span class="df-badge df-badge--warning">⏳ Onay Bekliyor</span>';
        echo '<a class="df-btn df-btn--primary df-btn--sm" href="' . h($share) . '" target="_blank">' . ds_icon('send',14) . ' Müşteri Linki</a>';
        echo '</div>';
        echo '</div>';
    }
} catch (Throwable $e) {
    echo ds_alert('danger', 'Hata: ' . $e->getMessage());
}
botx();
