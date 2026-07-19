<?php
require_once __DIR__.'/layout_top.php';
require_once __DIR__.'/contacts_lib.php';
require_once __DIR__.'/finance_lib.php';

// active kolonu güvencesi
try{
    db()->exec("ALTER TABLE contacts ADD COLUMN active TINYINT(1) NOT NULL DEFAULT 1");
}catch(Throwable $e){}

$type=$_GET['type'] ?? '';
$showPassive=!empty($_GET['show_passive']);
$q=trim($_GET['q'] ?? '');
$where='';
$params=[];
$conditions=[];
if($type){ $conditions[]="c.type=?"; $params[]=$type; }
if(!$showPassive){ $conditions[]="(c.active IS NULL OR c.active=1)"; }
// CARİ MODÜL İÇİ ARAMA (2026-07-19, Product Owner kararı) — 5.000+ cari ölçeği için isim/yetkili
// kişi/telefon araması. Global arama (search.php) AYRI sistem, bu SADECE bu listeye özel filtre —
// mevcut type/show_passive ile AYNI desen (GET → sunucu tarafı WHERE, tam sayfa yenileme).
if($q!==''){ $conditions[]="(c.name LIKE ? OR c.authorized_person LIKE ? OR c.phone LIKE ?)"; $params[]='%'.$q.'%'; $params[]='%'.$q.'%'; $params[]='%'.$q.'%'; }
if($conditions) $where="WHERE ".implode(' AND ',$conditions);
?>

<style>
.crm-tabs{display:grid;grid-template-columns:repeat(4,minmax(160px,1fr));gap:var(--df-space-4);margin:var(--df-space-4) 0 var(--df-space-5)}
.crm-card{display:block;text-decoration:none;border-radius:var(--df-radius-lg);padding:var(--df-space-4);box-shadow:var(--df-elevation-raised);border:1px solid var(--df-hairline);color:var(--df-ink-900)}
.crm-card small{display:block;color:var(--df-ink-600);font-weight:900;margin-bottom:6px}.crm-card strong{display:block;font-size:26px;line-height:1;margin-bottom:8px}.crm-card span{display:block;color:var(--df-ink-500);font-size:13px}
.crm-blue{background:linear-gradient(135deg,#dbeafe,#eff6ff)}.crm-green{background:linear-gradient(135deg,#dcfce7,#f0fdf4)}.crm-purple{background:linear-gradient(135deg,#ede9fe,#faf5ff)}.crm-orange{background:linear-gradient(135deg,#ffedd5,#fff7ed)}
.crm-table-wrap{overflow:auto}.crm-name a{font-weight:900;color:var(--df-ink-900);text-decoration:none}.crm-name a:hover{text-decoration:underline}
.rep-pill{display:inline-flex;align-items:center;border-radius:999px;background:var(--df-accent-soft);color:var(--df-accent-soft-ink);padding:6px 10px;font-weight:800;font-size:12px}.rep-empty{background:var(--df-surface-sunken);color:var(--df-ink-500)}
.type-pill{display:inline-flex;border-radius:999px;padding:6px 10px;font-weight:900;font-size:12px}.type-customer{background:var(--df-success-soft);color:var(--df-success-ink)}.type-supplier{background:var(--df-warning-soft);color:var(--df-warning-ink)}.type-both{background:var(--df-info-soft);color:var(--df-info-ink)}
.money-pos{font-weight:900;color:var(--df-success-ink)}.money-neg{font-weight:900;color:var(--df-danger-ink)}.money-zero{font-weight:900;color:var(--df-ink-500)}
.passive-row{opacity:.45}
.badge-passive{display:inline-flex;border-radius:999px;padding:3px 8px;font-size:11px;font-weight:900;background:var(--df-surface-sunken);color:var(--df-ink-500);margin-left:6px}
@media(max-width:960px){.crm-tabs{grid-template-columns:1fr}}
</style>

<?php
$__contactActions = ds_button('Müşteri','contact_new.php?type=Müşteri','primary','','',true)
    . ds_button('Tedarikçi','contact_new.php?type=Tedarikçi','secondary','','',true)
    . ds_button('Cari Raporlar','contacts_report.php','secondary','','',true);
ds_page_header('Cari Hesaplar', ds_icon('users',24), 'Müşteri, tedarikçi, temsilci, tahsilat, ödeme ve bakiye yönetimi', $__contactActions, false, true);
?>

<?php
// NOT (2026-07-03 düzeltmesi): Toplam Bakiye eskiden TÜM finance_movements'ı (cari_id'si olsun
// olmasın) topluyordu — cari'ye bağlı olmayan genel muhasebe gider/gelir kayıtları da bu toplama
// karışıp cari listesindeki hiçbir satırla eşleşmeyen bir rakam üretiyordu (kullanıcı bildirdi:
// üstte -4.020 ₺ görünüyor ama tüm cari satırları 0,00 gösteriyordu). Artık alttaki tabloyla
// birebir aynı mantıkla (sadece f.contact_id=c.id ile eşleşen hareketler) hesaplanıyor.
// 2026-07-10 Finans Çekirdek düzeltmesi: contact_view.php ile aynı düzeltilmiş formül — satış/alış
// kendi yönüyle, Tahsilat/Ödeme TERS işaretle sayılır (contacts_lib.php::contact_balance_case_sql()),
// aksi halde "satış + kendi tahsilatı" çift sayılırdı.
$balExprC = contact_balance_case_sql('f');
$totalBalance=safe_sum("SELECT COALESCE(SUM(balance),0) s FROM (SELECT c.id, COALESCE(c.opening_balance,0)+COALESCE(SUM($balExprC),0) balance FROM contacts c LEFT JOIN finance_movements f ON f.contact_id=c.id GROUP BY c.id) x");
$totalReceivable=safe_sum("SELECT COALESCE(SUM(CASE WHEN balance>0 THEN balance ELSE 0 END),0) s FROM (SELECT c.id, COALESCE(c.opening_balance,0)+COALESCE(SUM($balExprC),0) balance FROM contacts c LEFT JOIN finance_movements f ON f.contact_id=c.id GROUP BY c.id) x");
$totalPayable=safe_sum("SELECT COALESCE(SUM(CASE WHEN balance<0 THEN -balance ELSE 0 END),0) s FROM (SELECT c.id, COALESCE(c.opening_balance,0)+COALESCE(SUM($balExprC),0) balance FROM contacts c LEFT JOIN finance_movements f ON f.contact_id=c.id GROUP BY c.id) x");
// PDP-001 (2026-07-15): bu kart eskiden sabit "₺" gösteriyordu — bu ayki gerçek cari hareket sayısı bağlandı.
$movementsThisMonth=safe_count("SELECT COUNT(*) c FROM finance_movements WHERE contact_id IS NOT NULL AND MONTH(movement_date)=MONTH(CURDATE()) AND YEAR(movement_date)=YEAR(CURDATE())");
?>

<section class="crm-tabs">
    <a class="crm-card crm-blue" href="contacts_report.php">
        <small>Toplam Bakiye</small>
        <strong><?=money($totalBalance)?></strong>
        <span>Tüm carilerin net bakiyesi</span>
    </a>
    <a class="crm-card crm-green" href="contacts_report.php?mode=receivable">
        <small>Alacak</small>
        <strong><?=money($totalReceivable)?></strong>
        <span>Pozitif bakiyeli cariler</span>
    </a>
    <a class="crm-card crm-purple" href="contacts_report.php?mode=payable">
        <small>Borç</small>
        <strong><?=money($totalPayable)?></strong>
        <span>Negatif bakiyeli cariler</span>
    </a>
    <a class="crm-card crm-orange" href="finance.php">
        <small>Finans Hareketleri</small>
        <strong><?=$movementsThisMonth?></strong>
        <span>Bu ay cariye bağlı hareket</span>
    </a>
</section>

<?php
// CARİ MODÜL İÇİ ARAMA (2026-07-19) — tip/pasif filtreleriyle AYNI query-string desenini korur,
// sadece 'q' araya eklenir/çıkarılır — hiçbir mevcut link biçimi değişmedi.
// Tip sekmeleri (Tümü/Müşteri/Tedarikçi) orijinal davranışı korur (show_passive TAŞINMAZ, tip
// değişince pasif filtresi sıfırlanır — mevcut davranış) — sadece yeni 'q' eklendi/korundu.
function __cn_url($type=null){
    global $q;
    $qs = [];
    if($type) $qs['type'] = $type;
    if($q!=='') $qs['q'] = $q;
    return 'contacts.php'.($qs ? '?'.http_build_query($qs) : '');
}
?>
<section class="df-card">
<div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:var(--df-space-3)">
    <h2 style="font-size:var(--df-type-section-size);font-weight:var(--df-type-section-weight);color:var(--df-ink-900);margin:0"><?= $type ? h($type).' Listesi' : 'Cari Listesi' ?></h2>
</div>
<form method="get" style="margin-bottom:var(--df-space-3)">
    <?php if($type): ?><input type="hidden" name="type" value="<?=h($type)?>"><?php endif; ?>
    <?php if($showPassive): ?><input type="hidden" name="show_passive" value="1"><?php endif; ?>
    <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
        <input type="text" name="q" value="<?=h($q)?>" placeholder="İsim, yetkili kişi veya telefon ile ara…" style="flex:1;min-width:220px">
        <button type="submit" class="df-btn df-btn--secondary df-btn--sm">Ara</button>
        <?php if($q!==''): ?><a class="df-btn df-btn--ghost df-btn--sm" href="contacts.php<?=$type?'?type='.urlencode($type):''?><?=$showPassive?($type?'&':'?').'show_passive=1':''?>">Temizle</a><?php endif; ?>
    </div>
</form>
<div style="display:flex;justify-content:flex-end;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:var(--df-space-3)">
    <div style="display:flex;gap:6px;flex-wrap:wrap">
        <a class="df-btn df-btn--secondary df-btn--sm" href="<?=h(__cn_url())?>">Tümü</a>
        <a class="df-btn df-btn--secondary df-btn--sm" href="<?=h(__cn_url('Müşteri'))?>">Müşteri</a>
        <a class="df-btn df-btn--secondary df-btn--sm" href="<?=h(__cn_url('Tedarikçi'))?>">Tedarikçi</a>
        <?php if($showPassive): ?>
        <a class="df-btn df-btn--secondary df-btn--sm" href="contacts.php<?=$type?'?type='.urlencode($type):''?><?=$q!==''?($type?'&':'?').'q='.urlencode($q):''?>">Sadece Aktif</a>
        <?php else: ?>
        <a class="df-btn df-btn--secondary df-btn--sm" href="contacts.php?show_passive=1<?=$type?'&type='.urlencode($type):''?><?=$q!==''?'&q='.urlencode($q):''?>">Pasif Dahil</a>
        <?php endif; ?>
        <a class="df-btn df-btn--secondary df-btn--sm" href="contacts_report.php">Rapor</a>
    </div>
</div>

<div class="df-table-wrap crm-table-wrap">
<table class="df-table">
<thead>
<tr>
<th>Cari</th>
<th>Tip</th>
<th>Temsilci</th>
<th>Açılış</th>
<th>Tahsilat</th>
<th>Ödeme</th>
<th>Bakiye</th>
<th>Telefon</th>
<th>İşlem</th>
</tr>
</thead>
<tbody>
<?php
try{
    // 2026-07-10: "Tahsilat"/"Ödeme" kolonları SADECE gerçek kasa/banka hareketlerini gösterir
    // (satış/alış açık borç/alacaktır, tahsilat/ödeme değil); "Bakiye" ise contact_balance_case_sql
    // ile doğru işaretle (Tahsilat/Ödeme ters çevrilerek) hesaplanır — aksi halde çift sayılırdı.
    $balExprF = contact_balance_case_sql('f');
    $sql="SELECT c.*,
        GROUP_CONCAT(DISTINCT p.name ORDER BY cr.is_primary DESC, p.name SEPARATOR ', ') representatives,
        COALESCE(SUM(CASE WHEN f.direction='in' AND f.movement_type IN ('normal','mobile') AND f.account_id IS NOT NULL THEN f.amount ELSE 0 END),0) total_in,
        COALESCE(SUM(CASE WHEN f.direction='out' AND f.movement_type IN ('normal','mobile') AND f.account_id IS NOT NULL THEN f.amount ELSE 0 END),0) total_out,
        COALESCE(SUM($balExprF),0) net_movements
        FROM contacts c
        LEFT JOIN contact_representatives cr ON cr.contact_id=c.id
        LEFT JOIN personnel p ON p.id=cr.personnel_id
        LEFT JOIN finance_movements f ON f.contact_id=c.id
        $where
        GROUP BY c.id
        ORDER BY c.type,c.name";
    $st=db()->prepare($sql);
    $st->execute($params);
    $rows=$st->fetchAll();

    foreach($rows as $r){
        $rep = ($r['representative_mode'] ?? '')==='anonim' ? 'Anonim' : ($r['representatives'] ?: 'Atanmadı');
        $typeClass='type-both';
        if($r['type']==='Müşteri') $typeClass='type-customer';
        if($r['type']==='Tedarikçi') $typeClass='type-supplier';
        $balance=(float)$r['opening_balance']+(float)$r['net_movements'];
        $balClass=$balance>0?'money-pos':($balance<0?'money-neg':'money-zero');

        $isPassive=isset($r['active']) && (int)$r['active']===0;
        echo "<tr".($isPassive?" class='passive-row'":"").">";
        echo "<td class='crm-name'><a href='contact_view.php?id=".h($r['id'])."'>".h($r['name'])."</a>".($isPassive?"<span class='badge-passive'>Pasif</span>":"")."</td>";
        echo "<td><span class='type-pill ".$typeClass."'>".h($r['type'])."</span></td>";
        echo "<td><span class='rep-pill ".($rep==='Atanmadı'?'rep-empty':'')."'>".h($rep)."</span></td>";
        echo "<td>".money($r['opening_balance'])."</td>";
        echo "<td class='money-pos'>".money($r['total_in'])."</td>";
        echo "<td class='money-neg'>".money($r['total_out'])."</td>";
        echo "<td class='".$balClass."'>".money($balance)."</td>";
        echo "<td>".h($r['phone'] ?: '-')."</td>";
        echo "<td><a class='df-btn df-btn--secondary df-btn--sm' href='contact_view.php?id=".h($r['id'])."'>Profil</a></td>";
        echo "</tr>";
    }
    if(!$rows) echo "<tr><td colspan='9' style='color:var(--df-ink-500)'>Kayıt yok.</td></tr>";
}catch(Throwable $e){
    echo "<tr><td colspan='9'>".ds_alert('danger',$e->getMessage())."</td></tr>";
}
?>
</tbody>
</table>
</div>
</section>

<?php require_once __DIR__.'/layout_bottom.php'; ?>
