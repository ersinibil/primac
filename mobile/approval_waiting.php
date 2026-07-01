<?php
require_once 'common.php';

topx('Müşteri Onayı');
?>
<div class="panel" style="padding:10px 12px 8px">
  <div style="color:#94a3b8;font-size:13px">Müşteri onayı bekleyen dosyalar — <b style="color:#fbbf24">Müşteri Onayı Bekliyor</b> durumundaki kayıtlar listeleniyor.</div>
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
        echo '<div class="panel muted" style="text-align:center;padding:24px">Onay bekleyen dosya yok.</div>';
    }
    foreach ($rows as $r) {
        $share = 'http://acanstr.com/erp/public_file.php?token=' . htmlspecialchars($r['share_token'] ?? '', ENT_QUOTES);
        $tarih = htmlspecialchars(substr($r['created_at'] ?? '', 0, 10));
        echo '<div class="panel" style="padding:13px">';
        // Başlık satırı: iş no + başlık
        echo '<a href="job_view.php?id=' . (int)$r['job_id'] . '" style="text-decoration:none;color:#fff;display:block;margin-bottom:6px">';
        echo '<b>' . htmlspecialchars($r['job_title'] ?? '') . '</b>';
        echo '<small class="muted" style="display:block;margin-top:2px">';
        echo '📋 ' . htmlspecialchars($r['job_no'] ?? '');
        if ($tarih) echo ' &nbsp;·&nbsp; 📅 ' . $tarih;
        echo '</small>';
        echo '</a>';
        // Dosya bilgisi
        echo '<div style="background:rgba(255,255,255,.07);border-radius:12px;padding:10px;margin-bottom:10px">';
        echo '<div style="font-size:13px;color:#e2e8f0">📄 ' . htmlspecialchars($r['original_name'] ?? '') . '</div>';
        if (!empty($r['file_type'])) {
            echo '<div style="font-size:12px;color:#94a3b8;margin-top:3px">Tür: ' . htmlspecialchars($r['file_type']) . '</div>';
        }
        echo '</div>';
        // Durum rozeti + müşteri linki
        echo '<div style="display:flex;justify-content:space-between;align-items:center;gap:8px">';
        echo '<span style="background:#fef3c7;color:#92400e;border-radius:10px;padding:4px 10px;font-size:12px;font-weight:900">⏳ Onay Bekliyor</span>';
        echo '<a class="btn" href="' . $share . '" target="_blank" style="padding:9px 14px;font-size:13px;flex:0 0 auto;background:#2563eb;color:#fff">🔗 Müşteri Linki</a>';
        echo '</div>';
        echo '</div>';
    }
} catch (Throwable $e) {
    echo '<div class="panel err">Hata: ' . htmlspecialchars($e->getMessage()) . '</div>';
}
botx();
