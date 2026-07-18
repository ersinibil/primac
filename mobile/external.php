<?php
require_once 'common.php';

topx('Dış İşler');
?>
<p class="muted" style="font-size:13px;margin:0 0 10px">Dış atölye ve tedarikçide üretim işleri listeleniyor.</p>
<?php
// Tip etiketleri (web'deki job_type_label() ile aynı mantık, ama burada tanımlanabilir yoksa fallback)
function ext_type_label($t) {
    $m = [
        'dis_atolye'          => 'Dış Atölye',
        'tedarikcide_uretim'  => 'Tedarikçide Üretim',
    ];
    return $m[$t] ?? h($t);
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
        ds_empty_state('Henüz dış iş kaydı yok.', null, ds_icon('briefcase',32));
    }
    foreach ($rows as $r) {
        echo '<a href="job_view.php?id=' . (int)$r['id'] . '" class="df-panel" style="display:block;margin-top:10px;text-decoration:none;color:inherit">';
        echo '<div style="display:flex;justify-content:space-between;align-items:flex-start;gap:8px;margin-bottom:6px">';
        echo '<div class="df-list-row-title" style="flex:1">' . h($r['title'] ?? '') . '</div>';
        echo ds_badge($r['status'] ?? '');
        echo '</div>';
        echo '<div class="df-list-row-meta">';
        echo '<span>📋 ' . h($r['job_no'] ?? '') . '</span>';
        echo '<span>🏭 ' . ext_type_label($r['job_type'] ?? '') . '</span>';
        if (!empty($r['customer']))   echo '<span>👤 ' . h($r['customer']) . '</span>';
        if (!empty($r['responsible'])) echo '<span>👷 ' . h($r['responsible']) . '</span>';
        if (!empty($r['due_date']))   echo '<span class="df-list-row-due">📅 ' . h($r['due_date']) . '</span>';
        echo '</div>';
        echo '</a>';
    }
} catch (Throwable $e) {
    echo ds_alert('danger', $e->getMessage());
}
botx();
