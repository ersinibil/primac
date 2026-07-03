<?php
require_once __DIR__.'/layout_top.php';

// active kolonu güvencesi
try{
    db()->exec("ALTER TABLE contacts ADD COLUMN active TINYINT(1) NOT NULL DEFAULT 1");
}catch(Throwable $e){}

$type=$_GET['type'] ?? '';
$showPassive=!empty($_GET['show_passive']);
$where='';
$params=[];
$conditions=[];
if($type){ $conditions[]="c.type=?"; $params[]=$type; }
if(!$showPassive){ $conditions[]="(c.active IS NULL OR c.active=1)"; }
if($conditions) $where="WHERE ".implode(' AND ',$conditions);
?>

<style>
.crm-hero{display:flex;align-items:center;justify-content:space-between;gap:14px;margin-bottom:18px}
.crm-actions{display:flex;gap:10px;flex-wrap:wrap}
.crm-tabs{display:grid;grid-template-columns:repeat(4,minmax(160px,1fr));gap:14px;margin:14px 0 22px}
.crm-card{display:block;text-decoration:none;border-radius:22px;padding:18px;box-shadow:0 10px 30px rgba(16,24,40,.07);border:1px solid rgba(15,23,42,.06);color:#101828}
.crm-card small{display:block;color:#475467;font-weight:900;margin-bottom:6px}.crm-card strong{display:block;font-size:26px;line-height:1;margin-bottom:8px}.crm-card span{display:block;color:#667085;font-size:13px}
.crm-blue{background:linear-gradient(135deg,#dbeafe,#eff6ff)}.crm-green{background:linear-gradient(135deg,#dcfce7,#f0fdf4)}.crm-purple{background:linear-gradient(135deg,#ede9fe,#faf5ff)}.crm-orange{background:linear-gradient(135deg,#ffedd5,#fff7ed)}
.crm-table-wrap{overflow:auto}.crm-name a{font-weight:900;color:#101828;text-decoration:none}.crm-name a:hover{text-decoration:underline}
.rep-pill{display:inline-flex;align-items:center;border-radius:999px;background:#eef2ff;color:#3730a3;padding:6px 10px;font-weight:800;font-size:12px}.rep-empty{background:#f2f4f7;color:#667085}
.type-pill{display:inline-flex;border-radius:999px;padding:6px 10px;font-weight:900;font-size:12px}.type-customer{background:#dcfce7;color:#166534}.type-supplier{background:#fef3c7;color:#92400e}.type-both{background:#dbeafe;color:#1e40af}
.money-pos{font-weight:900;color:#166534}.money-neg{font-weight:900;color:#991b1b}.money-zero{font-weight:900;color:#667085}
.passive-row{opacity:.45}
.badge-passive{display:inline-flex;border-radius:999px;padding:3px 8px;font-size:11px;font-weight:900;background:#f1f5f9;color:#64748b;margin-left:6px}
@media(max-width:960px){.crm-tabs{grid-template-columns:1fr}.crm-hero{align-items:flex-start;flex-direction:column}}
</style>

<div class="crm-hero">
    <div>
        <h1>Cari Hesaplar</h1>
        <p class="muted">Müşteri, tedarikçi, temsilci, tahsilat, ödeme ve bakiye yönetimi</p>
    </div>
    <div class="crm-actions">
        <a class="btn" href="contact_new.php?type=Müşteri">+ Müşteri</a>
        <a class="btn secondary" href="contact_new.php?type=Tedarikçi">+ Tedarikçi</a>
        <a class="btn secondary" href="contacts_report.php">Cari Raporlar</a>
    </div>
</div>

<?php
// NOT (2026-07-03 düzeltmesi): Toplam Bakiye eskiden TÜM finance_movements'ı (cari_id'si olsun
// olmasın) topluyordu — cari'ye bağlı olmayan genel muhasebe gider/gelir kayıtları da bu toplama
// karışıp cari listesindeki hiçbir satırla eşleşmeyen bir rakam üretiyordu (kullanıcı bildirdi:
// üstte -4.020 ₺ görünüyor ama tüm cari satırları 0,00 gösteriyordu). Artık alttaki tabloyla
// birebir aynı mantıkla (sadece f.contact_id=c.id ile eşleşen hareketler) hesaplanıyor.
$totalBalance=safe_sum("SELECT COALESCE(SUM(balance),0) s FROM (SELECT c.id, COALESCE(c.opening_balance,0)+COALESCE(SUM(CASE WHEN f.direction='in' THEN f.amount ELSE 0 END),0)-COALESCE(SUM(CASE WHEN f.direction='out' THEN f.amount ELSE 0 END),0) balance FROM contacts c LEFT JOIN finance_movements f ON f.contact_id=c.id GROUP BY c.id) x");
$totalReceivable=safe_sum("SELECT COALESCE(SUM(CASE WHEN balance>0 THEN balance ELSE 0 END),0) s FROM (SELECT c.id, COALESCE(c.opening_balance,0)+COALESCE(SUM(CASE WHEN f.direction='in' THEN f.amount ELSE 0 END),0)-COALESCE(SUM(CASE WHEN f.direction='out' THEN f.amount ELSE 0 END),0) balance FROM contacts c LEFT JOIN finance_movements f ON f.contact_id=c.id GROUP BY c.id) x");
$totalPayable=safe_sum("SELECT COALESCE(SUM(CASE WHEN balance<0 THEN -balance ELSE 0 END),0) s FROM (SELECT c.id, COALESCE(c.opening_balance,0)+COALESCE(SUM(CASE WHEN f.direction='in' THEN f.amount ELSE 0 END),0)-COALESCE(SUM(CASE WHEN f.direction='out' THEN f.amount ELSE 0 END),0) balance FROM contacts c LEFT JOIN finance_movements f ON f.contact_id=c.id GROUP BY c.id) x");
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
        <strong>₺</strong>
        <span>Tahsilat, ödeme ve transferler</span>
    </a>
</section>

<section class="panel">
<div class="panel-head">
    <h2><?= $type ? h($type).' Listesi' : 'Cari Listesi' ?></h2>
    <div class="actions">
        <a class="btn small secondary" href="contacts.php">Tümü</a>
        <a class="btn small secondary" href="contacts.php?type=Müşteri">Müşteri</a>
        <a class="btn small secondary" href="contacts.php?type=Tedarikçi">Tedarikçi</a>
        <?php if($showPassive): ?>
        <a class="btn small secondary" href="contacts.php<?=$type?'?type='.urlencode($type):''?>">Sadece Aktif</a>
        <?php else: ?>
        <a class="btn small secondary" href="contacts.php?show_passive=1<?=$type?'&type='.urlencode($type):''?>">Pasif Dahil</a>
        <?php endif; ?>
        <a class="btn small secondary" href="contacts_report.php">Rapor</a>
    </div>
</div>

<div class="crm-table-wrap">
<table>
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
    $sql="SELECT c.*,
        GROUP_CONCAT(DISTINCT p.name ORDER BY cr.is_primary DESC, p.name SEPARATOR ', ') representatives,
        COALESCE(SUM(CASE WHEN f.direction='in' THEN f.amount ELSE 0 END),0) total_in,
        COALESCE(SUM(CASE WHEN f.direction='out' THEN f.amount ELSE 0 END),0) total_out
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
        $balance=(float)$r['opening_balance']+(float)$r['total_in']-(float)$r['total_out'];
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
        echo "<td><a class='btn small secondary' href='contact_view.php?id=".h($r['id'])."'>Profil</a></td>";
        echo "</tr>";
    }
    if(!$rows) echo "<tr><td colspan='9' class='muted'>Kayıt yok.</td></tr>";
}catch(Throwable $e){
    echo "<tr><td colspan='9'><div class='alert'>".h($e->getMessage())."</div></td></tr>";
}
?>
</tbody>
</table>
</div>
</section>

<?php require_once __DIR__.'/layout_bottom.php'; ?>
