<?php require_once 'common.php'; topx(app_config()['app_name'] ?? 'OTS');
require_once __DIR__.'/../tasks_lib.php';
$pdo=db();

// NAV-001B (2026-07-16) — Product Owner kararı: "Diğer kullanıcıların mevcut web VE MOBİL
// deneyimi birebir korunacaktır" — bu dosya artık $__navMode'a (mobile/common.php'de hesaplanır)
// göre iki ayrı yol izliyor. legacy: ORİJİNAL ekran (renkli kart grid'i + KPI karşılaştırması)
// birebir korunur. compact: Product Owner'ın istediği sade "şu an ne yapmalıyım" ekranı.
$open=mc("SELECT COUNT(*) c FROM jobs WHERE status NOT IN ('Tamamlandı','İptal','Teslim Edildi')");
$overdue_count=mc("SELECT COUNT(*) c FROM jobs WHERE status NOT IN ('Tamamlandı','İptal','Teslim Edildi') AND due_date IS NOT NULL AND due_date<CURDATE()");
$crit=mc("SELECT COUNT(*) c FROM stock_items WHERE quantity<=critical_level");

$__pulseOk = true; $__pulseOverdue = 0; $__pulseCriticalStock = 0;
try {
    $__pulseOverdue = (int)($pdo->query("SELECT COUNT(*) c FROM jobs WHERE status NOT IN ('Tamamlandı','İptal','Teslim Edildi') AND due_date IS NOT NULL AND due_date<CURDATE()")->fetch()['c'] ?? 0);
    $__pulseCriticalStock = (int)($pdo->query("SELECT COUNT(*) c FROM stock_items WHERE quantity<=critical_level")->fetch()['c'] ?? 0);
} catch(Throwable $e) { $__pulseOk = false; }
$__pulseShowJobs = $isAdmin||user_can('jobs');
$__pulseShowStock = $isAdmin||user_can('stock');
$__pulse = dashboard_pulse_state($__pulseOk, $__pulseOverdue, $__pulseShowJobs, $__pulseCriticalStock, $__pulseShowStock);

$__gecikmeShowOverdue = $__pulseShowJobs && $overdue_count > 0;
$__gecikmeShowCritical = $__pulseShowStock && $crit > 0;
$__pulseTarget = null;
if($__gecikmeShowOverdue && $__gecikmeShowCritical){ $__pulseTarget = '#gecikme-uyari'; }
elseif($__gecikmeShowOverdue){ $__pulseTarget = 'jobs.php?s=gec'; }
elseif($__gecikmeShowCritical){ $__pulseTarget = 'stock.php?critical=1'; }

$myMsg=unread_msg(); $myNotif=unread_notif();
$__pulseColors=[
  'green'  =>['fg'=>'var(--c-success)','bg'=>'var(--c-success-bg)','fgtext'=>'var(--c-success-text)'],
  'yellow' =>['fg'=>'var(--c-warn)','bg'=>'var(--c-warn-bg)','fgtext'=>'#78350f'],
  'red'    =>['fg'=>'var(--c-danger)','bg'=>'var(--c-danger-bg)','fgtext'=>'var(--c-danger-text)'],
  'neutral'=>['fg'=>'var(--c-muted)','bg'=>'rgba(148,163,184,.15)','fgtext'=>'var(--c-muted)'],
];
$__pc=$__pulseColors[$__pulse['level']];
?>
<!-- ── Nabız Satırı — her iki modda da aynı (değişmedi) ── -->
<?php if($__pulseTarget !== null): ?>
<a href="<?=htmlspecialchars($__pulseTarget)?>" class="panel" style="background:<?=$__pc['bg']?>;border-color:<?=$__pc['fg']?>;display:flex;align-items:center;gap:8px;padding:12px 14px;text-decoration:none;color:<?=$__pc['fgtext']?>">
  <span style="font-size:16px;line-height:1"><?=$__pulse['icon']?></span>
  <span style="flex:1;font-size:12.5px;font-weight:700;color:<?=$__pc['fgtext']?>"><?=htmlspecialchars($__pulse['message'])?></span>
  <span style="flex:0 0 auto;font-size:11px;font-weight:800;color:<?=$__pc['fgtext']?>;background:rgba(255,255,255,.5);padding:5px 10px;border-radius:999px">İncele</span>
</a>
<?php else: ?>
<div class="panel" style="background:<?=$__pc['bg']?>;border-color:<?=$__pc['fg']?>;display:flex;align-items:center;gap:8px;padding:12px 14px">
  <span style="font-size:16px;line-height:1"><?=$__pulse['icon']?></span>
  <span style="flex:1;font-size:12.5px;font-weight:700;color:<?=$__pc['fgtext']?>"><?=htmlspecialchars($__pulse['message'])?></span>
</div>
<?php endif; ?>

<?php if($__navMode === 'legacy'): ?>
<!-- ── LEGACY — orijinal ekran, PX-001B öncesi hâliyle birebir ── -->
<?php
$__qaSplit = dashboard_quick_actions_split(function($perm) use($isAdmin){ return $isAdmin||user_can($perm); });
$monthStart=date('Y-m-01'); $monthEnd=date('Y-m-t');
$prevMonthEnd=date('Y-m-01', strtotime('-1 day')); $prevMonthStart=date('Y-m-01', strtotime($prevMonthEnd));
function getKpiMetricsMobile($pdo, $from, $to) {
    $m = [];
    try { $r = $pdo->prepare("SELECT COALESCE(SUM(amount),0) t FROM finance_movements WHERE direction='in' AND DATE(movement_date) BETWEEN ? AND ?"); $r->execute([$from, $to]); $m['rev'] = (float)($r->fetch()['t'] ?? 0); } catch(Throwable $e) { $m['rev'] = 0; }
    try { $r = $pdo->prepare("SELECT COALESCE(SUM(amount),0) t FROM finance_movements WHERE direction='out' AND COALESCE(movement_type,'')<>'transfer' AND DATE(movement_date) BETWEEN ? AND ?"); $r->execute([$from, $to]); $m['exp'] = (float)($r->fetch()['t'] ?? 0); } catch(Throwable $e) { $m['exp'] = 0; }
    return $m;
}
$currM = getKpiMetricsMobile($pdo, $monthStart, $monthEnd);
$prevM = getKpiMetricsMobile($pdo, $prevMonthStart, $prevMonthEnd);
if($isAdmin){
  $contacts=mc("SELECT COUNT(*) c FROM contacts");
  $stock=mc("SELECT COUNT(*) c FROM stock_items");
  $todayIn=0; try{ $todayIn=(float)(db()->query("SELECT COALESCE(SUM(amount),0) s FROM finance_movements WHERE direction='in' AND DATE(movement_date)=CURDATE()")->fetch()['s']??0); }catch(Throwable $e){}
  $topName=''; try{ $tp=db()->query("SELECT p.name, COUNT(*) c FROM jobs j JOIN personnel p ON p.id=j.responsible_personnel_id WHERE j.status IN ('Tamamlandı','Teslim Edildi') GROUP BY p.id ORDER BY c DESC LIMIT 1")->fetch(); if($tp)$topName=$tp['name'].' ('.$tp['c'].')'; }catch(Throwable $e){}
}
?>
<div class="panel">
  <b>⚡ Hızlı İşlemler</b>
  <?php $__qaByCat=[]; foreach($__qaSplit['primary'] as $__qa){ $__qaByCat[$__qa['category']][]=$__qa; } ?>
  <?php foreach(['TİCARET','FİNANS','OPERASYON','İLETİŞİM'] as $__qaCat): if(empty($__qaByCat[$__qaCat])) continue; ?>
  <div style="margin-top:10px">
    <div style="font-size:10.5px;font-weight:800;text-transform:uppercase;letter-spacing:.05em;color:var(--c-muted);margin-bottom:6px"><?=htmlspecialchars($__qaCat)?></div>
    <div style="display:flex;flex-wrap:wrap;gap:8px">
      <?php foreach($__qaByCat[$__qaCat] as $__qa): ?>
      <a class="btn" style="padding:10px 12px;font-size:13px" href="<?=htmlspecialchars($__qa['mobileUrl'] ?? $__qa['url'])?>"><?=$__qa['icon']?> <?=htmlspecialchars($__qa['label'])?></a>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endforeach; ?>
  <?php if($__qaSplit['overflow']): ?>
  <details style="margin-top:10px">
    <summary style="font-size:12px;font-weight:800;color:var(--c-muted);cursor:pointer">Diğer İşlemler (<?=count($__qaSplit['overflow'])?>)</summary>
    <div style="display:flex;flex-wrap:wrap;gap:8px;margin-top:8px">
      <?php foreach($__qaSplit['overflow'] as $__qa): ?>
      <a class="btn" style="padding:10px 12px;font-size:13px" href="<?=htmlspecialchars($__qa['mobileUrl'] ?? $__qa['url'])?>"><?=$__qa['icon']?> <?=htmlspecialchars($__qa['label'])?></a>
      <?php endforeach; ?>
    </div>
  </details>
  <?php endif; ?>
</div>

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
    card('İş Emirleri','Müşteri işleri ve operasyon takibi','📋','jobs.php','green');
    card('Görevlerim','Bana atanan görevler ve hatırlatmalar','🎯','mytasks.php','orange');
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
    card('İş Emirleri','Müşteri işleri ve operasyon takibi','📋','jobs.php','green');
    card('Görevlerim','Bana atanan görevler ve hatırlatmalar','🎯','mytasks.php','blue');
    card('Stok','Ürünleri gör','📦','stock.php','red');
    card('Mesajlar','Ekiple yazış','💬','messages.php','teal');
    ?>
  </div>
<?php endif; ?>

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

<?php if($__gecikmeShowOverdue || $__gecikmeShowCritical): ?>
<div class="panel" id="gecikme-uyari" style="border-left:4px solid var(--c-danger);scroll-margin-top:130px"><b style="color:var(--c-danger)">⚠️ Dikkat</b>
  <div style="display:grid;grid-template-columns:<?=($__gecikmeShowOverdue && $__gecikmeShowCritical)?'1fr 1fr':'1fr'?>;gap:8px;margin-top:8px;font-size:12px">
    <?php if($__gecikmeShowOverdue): ?>
    <a href="jobs.php?s=gec" style="display:block;background:var(--c-danger-bg);border-radius:var(--radius-sm);padding:10px;text-align:center;text-decoration:none">
      <span style="color:var(--c-danger);font-weight:700;font-size:16px;display:block"><?=$overdue_count?></span>
      <span style="color:#667085;font-size:11px">Geciken İş ›</span>
    </a>
    <?php endif; ?>
    <?php if($__gecikmeShowCritical): ?>
    <a href="stock.php?critical=1" style="display:block;background:#fed7aa;border-radius:var(--radius-sm);padding:10px;text-align:center;text-decoration:none">
      <span style="color:#d97706;font-weight:700;font-size:16px;display:block"><?=$crit?></span>
      <span style="color:#667085;font-size:11px">Kritik Stok ›</span>
    </a>
    <?php endif; ?>
  </div>
</div>
<?php endif; ?>

<?php else: ?>
<!-- ── COMPACT — NAV-001B sade ana ekran (Product Owner kararı) ── -->
<?php
$pid = task_my_personnel_id($pdo, $ME);
$__taskStats = task_my_stats($pdo, $pid);
$__openJobs = $__pulseShowJobs ? $open : 0;
$__upcoming = 0;
if($__pulseShowJobs){
    try{ $__upcoming = (int)($pdo->query("SELECT COUNT(*) c FROM jobs WHERE status NOT IN ('Tamamlandı','İptal','Teslim Edildi') AND due_date IS NOT NULL AND due_date>=CURDATE() AND due_date<=DATE_ADD(CURDATE(),INTERVAL 7 DAY)")->fetch()['c'] ?? 0); }catch(Throwable $e){}
}
$__qaSplit1 = dashboard_quick_actions_split(function($perm) use($isAdmin){ return $isAdmin||user_can($perm); }, 1);
$__singleAction = $__qaSplit1['primary'][0] ?? null;
?>
<div class="df-panel">
  <div class="df-ops-summary">
    <div class="df-ops-stat"><strong><?=$__taskStats['today']?></strong><span>Bugünkü Görev</span></div>
    <div class="df-ops-stat"><strong<?=$__taskStats['overdue']?' class="is-danger"':''?>><?=$__taskStats['overdue']?></strong><span>Geciken Görev</span></div>
    <?php if($__pulseShowJobs): ?>
    <div class="df-ops-stat"><strong><?=$__openJobs?></strong><span>Devam Eden İş</span></div>
    <div class="df-ops-stat"><strong><?=$__upcoming?></strong><span>Yaklaşan (7g)</span></div>
    <?php endif; ?>
  </div>
</div>

<div class="df-panel" style="display:flex;flex-direction:column;gap:8px">
  <a class="df-nav-row" href="messages.php" style="background:var(--df-surface-sunken)">
    <span>Mesajlar<?=$myMsg?' · '.$myMsg.' yeni':''?></span>
  </a>
  <?php if($__singleAction): ?>
  <a class="df-btn df-btn--primary" style="width:100%" href="<?=htmlspecialchars($__singleAction['mobileUrl'] ?? $__singleAction['url'])?>"><?=$__singleAction['icon']?> <?=htmlspecialchars($__singleAction['label'])?></a>
  <?php endif; ?>
  <a class="df-btn df-btn--secondary" style="width:100%" href="more.php">Tüm Modüller</a>
</div>
<?php endif; ?>

<?php botx(); ?>
