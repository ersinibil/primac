<?php
require_once 'common.php';

topx('Dış İşler');
?>
<div class="panel" style="padding:10px 12px 8px">
  <div style="color:#94a3b8;font-size:13px">Dış atölye ve tedarikçide üretim işleri listeleniyor.</div>
</div>
<?php
// Tip etiketleri (web'deki job_type_label() ile aynı mantık, ama burada tanımlanabilir yoksa fallback)
function ext_type_label($t) {
    $m = [
        'dis_atolye'          => 'Dış Atölye',
        'tedarikcide_uretim'  => 'Tedarikçide Üretim',
    ];
    return $m[$t] ?? htmlspecialchars($t);
}

// Durum rengi
function ext_status_color($s) {
    $m = [
        'Yeni'           => ['bg' => '#dbeafe', 'fg' => '#1e3a8a'],
        'Devam Ediyor'   => ['bg' => '#ede9fe', 'fg' => '#4c1d95'],
        'Bekliyor'       => ['bg' => '#fef3c7', 'fg' => '#92400e'],
        'Tamamlandı'     => ['bg' => '#dcfce7', 'fg' => '#14532d'],
        'Teslim Edildi'  => ['bg' => '#dcfce7', 'fg' => '#14532d'],
        'İptal'          => ['bg' => '#f1f5f9', 'fg' => '#475569'],
    ];
    return $m[$s] ?? ['bg' => '#334155', 'fg' => '#e2e8f0'];
}

try {
    $st = db()->query(
        "SELECT j.*, c.name AS customer, p.name AS responsible
         FROM jobs j
         LEFT JOIN contacts c ON c.id = j.customer_id
         LEFT JOIN personnel p ON p.id = j.responsible_personnel_id
         WHERE j.job_type IN ('dis_atolye','tedarikcide_uretim')
         ORDER BY j.id DESC"
    );
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);
    if (!$rows) {
        echo '<div class="panel muted" style="text-align:center;padding:24px">Henüz dış iş kaydı yok.</div>';
    }
    foreach ($rows as $r) {
        $col = ext_status_color($r['status'] ?? '');
        echo '<div class="panel" style="padding:13px">';
        echo '<a href="job_view.php?id=' . (int)$r['id'] . '" style="text-decoration:none;color:#fff;display:block">';
        // Başlık + durum
        echo '<div style="display:flex;justify-content:space-between;align-items:flex-start;gap:8px;margin-bottom:6px">';
        echo '<b style="flex:1">' . htmlspecialchars($r['title'] ?? '') . '</b>';
        echo '<span style="background:' . $col['bg'] . ';color:' . $col['fg'] . ';border-radius:10px;padding:3px 9px;font-size:12px;font-weight:900;white-space:nowrap">' . htmlspecialchars($r['status'] ?? '') . '</span>';
        echo '</div>';
        // Meta bilgi
        echo '<small class="muted">';
        echo '📋 ' . htmlspecialchars($r['job_no'] ?? '');
        echo ' &nbsp;·&nbsp; 🏭 ' . ext_type_label($r['job_type'] ?? '');
        if (!empty($r['customer']))   echo ' &nbsp;·&nbsp; 👤 ' . htmlspecialchars($r['customer']);
        if (!empty($r['responsible'])) echo ' &nbsp;·&nbsp; 👷 ' . htmlspecialchars($r['responsible']);
        if (!empty($r['due_date']))   echo ' &nbsp;·&nbsp; 📅 ' . htmlspecialchars($r['due_date']);
        echo '</small>';
        echo '</a>';
        echo '</div>';
    }
} catch (Throwable $e) {
    echo '<div class="panel err">Hata: ' . htmlspecialchars($e->getMessage()) . '</div>';
}
botx();
