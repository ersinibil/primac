<?php
require_once __DIR__.'/boot.php';
require_permission('personnel'); // sadece yönetici veya personel yetkisi

$pdo = db();

require_once __DIR__.'/layout_top.php';
?>

<h1>Personel Performans (KPI)</h1>

<div class="muted" style="margin:-10px 0 18px">
  İş teslim oranı, görev tamamlama ve geciken işlerden hesaplanır. Sıralama en yüksek puandan düşüğe.
</div>

<?php
try {
    $rows = $pdo->query(
        "SELECT p.id, p.name, p.role,
           (SELECT COUNT(*) FROM jobs j WHERE j.responsible_personnel_id=p.id) is_top,
           (SELECT COUNT(*) FROM jobs j WHERE j.responsible_personnel_id=p.id AND j.status IN ('Tamamlandı','Teslim Edildi')) is_tamam,
           (SELECT COUNT(*) FROM jobs j WHERE j.responsible_personnel_id=p.id AND j.status NOT IN ('Tamamlandı','Teslim Edildi','İptal')) is_acik,
           (SELECT COUNT(*) FROM jobs j WHERE j.responsible_personnel_id=p.id AND j.status NOT IN ('Tamamlandı','Teslim Edildi','İptal') AND j.due_date IS NOT NULL AND j.due_date < CURDATE()) is_geciken,
           (SELECT COUNT(*) FROM tasks t WHERE t.personnel_id=p.id) gv_top,
           (SELECT COUNT(*) FROM tasks t WHERE t.personnel_id=p.id AND t.status='Tamamlandı') gv_tamam
         FROM personnel p WHERE COALESCE(p.active,1)=1"
    )->fetchAll();

    // Puan hesabı
    foreach ($rows as &$r) {
        $isOran = $r['is_top'] > 0 ? ($r['is_tamam'] / $r['is_top'] * 100) : 0;
        $gvOran = $r['gv_top'] > 0 ? ($r['gv_tamam'] / $r['gv_top'] * 100) : 0;
        $aktif  = ($r['is_top'] > 0 || $r['gv_top'] > 0);
        $score  = $aktif
            ? max(0, min(100, (int)round(0.5 * $isOran + 0.5 * $gvOran - $r['is_geciken'] * 8)))
            : 0;
        $r['isOran'] = (int)round($isOran);
        $r['gvOran'] = (int)round($gvOran);
        $r['score']  = $score;
        $r['aktif']  = $aktif;
    }
    unset($r);

    usort($rows, function ($a, $b) { return $b['score'] - $a['score']; });

    if (!$rows) {
        echo '<div class="panel muted" style="text-align:center;padding:40px">Personel kaydı bulunamadı.</div>';
    } else {
        // Özet kartlar (üst 3)
        $top3 = array_slice($rows, 0, 3);
        echo '<div class="cards">';
        $medals = ['🥇', '🥈', '🥉'];
        foreach ($top3 as $i => $r) {
            $col = $r['score'] >= 75 ? 'green' : ($r['score'] >= 50 ? 'yellow' : ($r['aktif'] ? 'red' : 'gray'));
            echo '<div class="card">';
            echo '<small>' . ($medals[$i] ?? '') . ' ' . h($r['role'] ?: 'Personel') . '</small>';
            echo '<strong class="badge ' . $col . '" style="font-size:28px;padding:8px 14px;margin:8px 0">';
            echo ($r['aktif'] ? $r['score'] : '–');
            echo '</strong>';
            echo '<div style="font-weight:800;font-size:15px">' . h($r['name']) . '</div>';
            echo '</div>';
        }
        // 4. kart: toplam personel sayısı
        echo '<div class="card">';
        echo '<small>Toplam Personel</small>';
        echo '<strong>' . count($rows) . '</strong>';
        echo '<div class="muted" style="font-size:12px">Değerlendirilen</div>';
        echo '</div>';
        echo '</div>';

        // Detay tablosu
        echo '<div class="panel">';
        echo '<div class="panel-head"><h2>Tüm Personel Sıralama</h2></div>';
        echo '<table>';
        echo '<thead><tr>';
        echo '<th>#</th><th>Ad</th><th>Rol</th>';
        echo '<th style="text-align:center">Puan</th>';
        echo '<th style="text-align:center">İş</th>';
        echo '<th style="text-align:center">Teslim %</th>';
        echo '<th style="text-align:center">Açık</th>';
        echo '<th style="text-align:center">Geciken</th>';
        echo '<th style="text-align:center">Görev</th>';
        echo '<th style="text-align:center">Görev %</th>';
        echo '</tr></thead><tbody>';

        $rank = 0;
        foreach ($rows as $r) {
            $rank++;
            $sc  = $r['score'];
            $tone = $sc >= 75 ? 'green' : ($sc >= 50 ? 'yellow' : ($r['aktif'] ? 'red' : 'gray'));

            echo '<tr' . ($r['is_geciken'] > 0 ? ' class="danger-row"' : '') . '>';
            echo '<td class="muted">' . $rank . '</td>';
            echo '<td style="font-weight:800">';
            // İsme tıklayınca personel sayfasına git (varsa)
            echo '<a href="personnel_edit.php?id=' . (int)$r['id'] . '" style="color:#101828;text-decoration:none">'
                . h($r['name']) . '</a>';
            echo '</td>';
            echo '<td class="muted">' . h($r['role'] ?: 'Personel') . '</td>';

            // Puan: rozet + ilerleme çubuğu
            echo '<td style="text-align:center">';
            if ($r['aktif']) {
                echo '<span class="badge ' . $tone . '">' . $sc . '</span>';
                echo '<div style="height:5px;background:#eef2f6;border-radius:4px;margin-top:5px;width:80px;display:inline-block">';
                $colMap = ['green' => '#22c55e', 'yellow' => '#eab308', 'red' => '#f87171', 'gray' => '#94a3b8'];
                $barCol = isset($colMap[$tone]) ? $colMap[$tone] : '#94a3b8';
                echo '<div style="height:100%;width:' . $sc . '%;background:' . $barCol . ';border-radius:4px"></div></div>';
            } else {
                echo '<span class="badge gray">—</span>';
            }
            echo '</td>';

            echo '<td style="text-align:center">' . (int)$r['is_top'] . '</td>';
            echo '<td style="text-align:center">';
            echo '<span class="badge ' . ($r['isOran'] >= 75 ? 'green' : ($r['isOran'] >= 50 ? 'yellow' : 'gray')) . '">%' . $r['isOran'] . '</span>';
            echo '</td>';
            echo '<td style="text-align:center">' . (int)$r['is_acik'] . '</td>';

            echo '<td style="text-align:center">';
            if ($r['is_geciken'] > 0) {
                echo '<span class="badge red">' . (int)$r['is_geciken'] . ' gecikmiş</span>';
            } else {
                echo '<span class="badge green">0</span>';
            }
            echo '</td>';

            echo '<td style="text-align:center">' . (int)$r['gv_tamam'] . '/' . (int)$r['gv_top'] . '</td>';
            echo '<td style="text-align:center">';
            echo '<span class="badge ' . ($r['gvOran'] >= 75 ? 'green' : ($r['gvOran'] >= 50 ? 'yellow' : 'gray')) . '">%' . $r['gvOran'] . '</span>';
            echo '</td>';

            echo '</tr>';
        }

        echo '</tbody></table>';
        echo '</div>'; // panel

        // Puan açıklaması
        echo '<div class="panel" style="margin-top:16px;background:#f8fafc">';
        echo '<div class="panel-head"><h2 style="font-size:14px;color:#667085">Puan Hesaplama Yöntemi</h2></div>';
        echo '<p class="muted" style="margin:0;font-size:13px">';
        echo 'Puan = <b>%50 İş Teslim Oranı</b> + <b>%50 Görev Tamamlama Oranı</b> &minus; <b>Geciken İş × 8</b> (0&ndash;100 arasında sınırlanır).';
        echo ' &bull; İşi veya görevi olmayan personel değerlendirme dışı (<b>—</b>).';
        echo ' &bull; Kırmızı satır: geciken işi olan personel.';
        echo '</p></div>';
    }
} catch (Throwable $e) {
    echo '<div class="alert">' . h($e->getMessage()) . '</div>';
}
?>

<?php require_once __DIR__.'/layout_bottom.php'; ?>
