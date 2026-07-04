<?php require_once 'common.php'; topx(app_config()['app_name'] ?? 'OTS');
$pdo=db();
$open=mc("SELECT COUNT(*) c FROM jobs WHERE status NOT IN ('Tamamlandı','İptal','Teslim Edildi')");
$overdue_count=mc("SELECT COUNT(*) c FROM jobs WHERE status NOT IN ('Tamamlandı','İptal','Teslim Edildi') AND due_date IS NOT NULL AND due_date<CURDATE()");

// KPI Karşılaştırması: Bu ay vs Geçen ay (mobil basitleştirilmiş)
$monthStart=date('Y-m-01');
$monthEnd=date('Y-m-t');
$prevMonthEnd=date('Y-m-01', strtotime('-1 day'));
$prevMonthStart=date('Y-m-01', strtotime($prevMonthEnd));

function getKpiMetricsMobile($pdo, $from, $to) {
    $m = [];
    try { $r = $pdo->prepare("SELECT COALESCE(SUM(amount),0) t FROM finance_movements WHERE direction='in' AND DATE(movement_date) BETWEEN ? AND ?"); $r->execute([$from, $to]); $m['rev'] = (float)($r->fetch()['t'] ?? 0); } catch(Throwable $e) { $m['rev'] = 0; }
    try { $r = $pdo->prepare("SELECT COALESCE(SUM(amount),0) t FROM finance_movements WHERE direction='out' AND COALESCE(movement_type,'')<>'transfer' AND DATE(movement_date) BETWEEN ? AND ?"); $r->execute([$from, $to]); $m['exp'] = (float)($r->fetch()['t'] ?? 0); } catch(Throwable $e) { $m['exp'] = 0; }
    return $m;
}

$currM = getKpiMetricsMobile($pdo, $monthStart, $monthEnd);
$prevM = getKpiMetricsMobile($pdo, $prevMonthStart, $prevMonthEnd);


// Kritik stok her zaman gerekli (hem admin hem personel için uyarı panelinde)
$crit=mc("SELECT COUNT(*) c FROM stock_items WHERE quantity<=critical_level");

if($isAdmin){
  $contacts=mc("SELECT COUNT(*) c FROM contacts");
  $stock=mc("SELECT COUNT(*) c FROM stock_items");
  $todayIn=0; try{ $todayIn=(float)(db()->query("SELECT COALESCE(SUM(amount),0) s FROM finance_movements WHERE direction='in' AND DATE(movement_date)=CURDATE()")->fetch()['s']??0); }catch(Throwable $e){}
  $topName=''; try{ $tp=db()->query("SELECT p.name, COUNT(*) c FROM jobs j JOIN personnel p ON p.id=j.responsible_personnel_id WHERE j.status IN ('Tamamlandı','Teslim Edildi') GROUP BY p.id ORDER BY c DESC LIMIT 1")->fetch(); if($tp)$topName=$tp['name'].' ('.$tp['c'].')'; }catch(Throwable $e){}
}
$myMsg=unread_msg(); $myNotif=unread_notif();
?>
<?php if($isAdmin): ?>
  <div class="panel"><b>Online Takip ve Yönetim Sistemi</b><p class="small">Yönetici paneli · Web sürümü ayrı butondadır.</p>
  <div class="grid">
    <?php card('Açık İş',$open.' adet','📋','jobs.php','blue');card('Cari',$contacts.' kayıt','👥','contacts.php','purple');card('Ürün',$stock.' stok','📦','stock.php','orange');card('Kritik',$crit.' ürün','⚠️','stock.php','red');?>
  </div></div>
  <a href="kpi.php" style="text-decoration:none"><div class="panel"><b>📊 Bugün / Özet</b>
    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:8px;font-size:13px">
      <span style="background:rgba(248,113,113,.18);color:#fca5a5;border-radius:10px;padding:6px 10px">⏰ Geciken iş: <b><?=$overdue_count?></b></span>
      <span style="background:rgba(34,197,94,.16);color:#86efac;border-radius:10px;padding:6px 10px">💰 Bugün tahsilat: <b><?=mm($todayIn)?></b></span>
      <?php if($topName): ?><span style="background:rgba(234,179,8,.16);color:#fde68a;border-radius:10px;padding:6px 10px">🏆 Lider: <b><?=htmlspecialchars($topName)?></b></span><?php endif; ?>
    </div>
    <small class="muted" style="display:block;margin-top:8px">Personel performansını gör →</small>
  </div></a>
  <div class="grid">
    <?php
    card('İşler','İş ve görev takibi','📋','jobs.php','green');
    card('İşlerim','Sana atanan işler','🎯','mytasks.php','orange');
    card('Cariler','Müşteri / tedarikçi','👥','contacts.php','purple');
    card('Satış','Mobil satış ekranı','🧾','sales.php','orange');
    card('Tahsilat','Cariye tahsilat gir','💰','collection.php','yellow');
    card('Stok','Ürünleri gör','📦','stock.php','red');
    card('Mesajlar','İç yazışma','💬','messages.php','teal');
    ?>
  </div>
<?php else: ?>
  <div class="panel"><b>Hoş geldin, <?=htmlspecialchars($name)?></b><p class="small">Personel paneli · satış, tahsilat, iş ve mesaj.</p>
  <div class="grid">
    <?php card('Açık İş',$open.' adet','📋','jobs.php','blue');card('Mesajlar',$myMsg.' yeni','💬','messages.php','teal');?>
  </div></div>
  <div class="grid">
    <?php
    card('Satış Yap','Ürün sat + tahsilat','🧾','sales.php','orange');
    card('Tahsilat','Cariden tahsilat','💰','collection.php','yellow');
    card('Cariler','Müşteri / tedarikçi','👥','contacts.php','purple');
    card('İşler','İş ve görev takibi','📋','jobs.php','green');
    card('İşlerim','Sana atanan işler','🎯','mytasks.php','blue');
    card('Stok','Ürünleri gör','📦','stock.php','red');
    card('Mesajlar','Ekiple yazış','💬','messages.php','teal');
    ?>
  </div>
<?php endif; ?>

<!-- ── Karşılaştırmalı KPI (Mobil Basitleştirilmiş) ── -->
<div class="panel"><b>📊 Bu Ay vs Geçen Ay</b>
  <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-top:8px;font-size:12px">
    <div style="background:#f0fdf4;border-radius:var(--radius-sm);padding:10px;border:1px solid var(--c-success-bg)">
      <span style="color:#059669;font-weight:700">💰 Tahsilat</span>
      <div style="font-size:14px;font-weight:900;color:#101828;margin-top:4px"><?=money($currM['rev'])?></div>
      <div style="color:#667085;font-size:11px;margin-top:2px">Geçen: <?=money($prevM['rev'])?></div>
    </div>
    <div style="background:#fef2f2;border-radius:var(--radius-sm);padding:10px;border:1px solid var(--c-danger-bg)">
      <span style="color:var(--c-danger);font-weight:700">💸 Ödeme</span>
      <div style="font-size:14px;font-weight:900;color:#101828;margin-top:4px"><?=money($currM['exp'])?></div>
      <div style="color:#667085;font-size:11px;margin-top:2px">Geçen: <?=money($prevM['exp'])?></div>
    </div>
  </div>
</div>

<!-- ── Gecikme Uyarı (Mobil) ── -->
<?php if($overdue_count > 0 || $crit > 0): ?>
<div class="panel" style="border-left:4px solid var(--c-danger)"><b style="color:var(--c-danger)">⚠️ Dikkat</b>
  <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-top:8px;font-size:12px">
    <div style="background:var(--c-danger-bg);border-radius:var(--radius-sm);padding:10px;text-align:center">
      <span style="color:var(--c-danger);font-weight:700;font-size:16px;display:block"><?=$overdue_count?></span>
      <span style="color:#667085;font-size:11px">Geciken İş</span>
    </div>
    <div style="background:#fed7aa;border-radius:var(--radius-sm);padding:10px;text-align:center">
      <span style="color:#d97706;font-weight:700;font-size:16px;display:block"><?=$crit?></span>
      <span style="color:#667085;font-size:11px">Kritik Stok</span>
    </div>
  </div>
  <?php if($overdue_count > 0): ?>
  <a href="jobs.php?filter=late" style="display:block;margin-top:8px;padding:8px;background:#f3f4f6;border-radius:var(--radius-sm);text-align:center;text-decoration:none;color:var(--c-accent);font-weight:700;font-size:12px">Geciken İşleri Gör →</a>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php botx(); ?>
