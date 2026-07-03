<?php
require_once __DIR__.'/layout_top.php';

$contactId=(int)($_GET['contact_id'] ?? 0);
$direction=$_GET['direction'] ?? '';
$where=[];
$params=[];
if($contactId){ $where[]='f.contact_id=?'; $params[]=$contactId; }
if(in_array($direction,['in','out'])){ $where[]='f.direction=?'; $params[]=$direction; }
$sqlWhere=$where ? 'WHERE '.implode(' AND ',$where) : '';

$cash=safe_sum("SELECT COALESCE(SUM(current_balance),0) s FROM finance_accounts WHERE active=1 AND account_type='Kasa'");
$bank=safe_sum("SELECT COALESCE(SUM(current_balance),0) s FROM finance_accounts WHERE active=1 AND account_type='Banka'");
$card=safe_sum("SELECT COALESCE(SUM(current_balance),0) s FROM finance_accounts WHERE active=1 AND account_type='Kredi Kartı'");
$pos=safe_sum("SELECT COALESCE(SUM(current_balance),0) s FROM finance_accounts WHERE active=1 AND account_type='POS'");
?>

<style>
.finance-grid{display:grid;grid-template-columns:repeat(4,minmax(160px,1fr));gap:14px;margin:14px 0 20px}
.finance-tile{display:block;text-decoration:none;border-radius:22px;padding:18px;color:#101828;box-shadow:0 10px 30px rgba(16,24,40,.08);border:1px solid rgba(15,23,42,.06)}
.finance-tile small{display:block;font-weight:900;color:#475467}
.finance-tile strong{display:block;font-size:26px;margin:10px 0}
.finance-tile span{font-size:13px;color:#667085}
.ft-bank{background:linear-gradient(135deg,#dbeafe,#eff6ff)}
.ft-cash{background:linear-gradient(135deg,#dcfce7,#f0fdf4)}
.ft-card{background:linear-gradient(135deg,#fee2e2,#fff1f2)}
.ft-pos{background:linear-gradient(135deg,#fef3c7,#fffbeb)}
@media(max-width:960px){.finance-grid{grid-template-columns:1fr}}
.command-grid{display:grid;grid-template-columns:repeat(4,minmax(160px,1fr));gap:14px;margin:0 0 20px}
.command-card{display:block;text-decoration:none;background:#fff;border-radius:20px;padding:18px;box-shadow:0 8px 28px rgba(16,24,40,.06);border:1px solid #eef2f6;color:#101828;transition:transform .12s ease,box-shadow .12s ease}
.command-card:hover{transform:translateY(-2px);box-shadow:0 14px 36px rgba(16,24,40,.11)}
.command-card small{display:block;color:#667085;font-weight:800;font-size:11px;text-transform:uppercase;letter-spacing:.06em}
.command-card strong{display:block;font-size:30px;margin:8px 0;line-height:1}
.command-card span{color:#667085;font-size:13px}
.command-card.green{border-left:6px solid #22c55e}
.command-card.red{border-left:6px solid #ef4444}
.command-card.blue{border-left:6px solid #3b82f6}
.command-card.purple{border-left:6px solid #8b5cf6}
@media(max-width:960px){.command-grid{grid-template-columns:1fr}}
</style>

<div class="panel-head">
<h1>Finans Paneli</h1>
<div class="actions">
<a class="btn" href="finance_new.php?direction=in">+ Tahsilat</a>
<a class="btn secondary" href="finance_new.php?direction=out">+ Ödeme</a>
<a class="btn secondary" href="finance_transfer.php">+ Transfer</a>
<a class="btn secondary" href="finance_accounts.php">Hesaplar</a>
</div>
</div>

<?php if(isset($_GET['deleted'])): ?><div class="ok">Finans hareketi silindi, hesap bakiyesi güncellendi.</div><?php endif; ?>

<section class="finance-grid">
<a class="finance-tile ft-bank" href="finance_accounts.php?type=Banka"><small>🏦 Bankalar</small><strong><?=money($bank)?></strong><span>Banka hesapları</span></a>
<a class="finance-tile ft-cash" href="finance_accounts.php?type=Kasa"><small>💵 Kasalar</small><strong><?=money($cash)?></strong><span>Nakit kasa hesapları</span></a>
<a class="finance-tile ft-card" href="finance_accounts.php?type=Kredi Kartı"><small>💳 Kredi Kartları</small><strong><?=money($card)?></strong><span>Kart borç/limit takibi</span></a>
<a class="finance-tile ft-pos" href="finance_accounts.php?type=POS"><small>🧾 POS</small><strong><?=money($pos)?></strong><span>POS / sanal POS</span></a>
</section>

<section class="command-grid">
<a class="command-card green" href="finance.php?direction=in"><small>Tahsilat</small><strong><?=money(safe_sum("SELECT COALESCE(SUM(amount),0) s FROM finance_movements WHERE direction='in'"))?></strong><span>Tüm tahsilatlar</span></a>
<a class="command-card red" href="finance.php?direction=out"><small>Ödeme</small><strong><?=money(safe_sum("SELECT COALESCE(SUM(amount),0) s FROM finance_movements WHERE direction='out'"))?></strong><span>Tüm ödemeler</span></a>
<a class="command-card blue" href="finance_accounts.php"><small>Hesaplar</small><strong><?=safe_count("SELECT COUNT(*) c FROM finance_accounts WHERE active=1")?></strong><span>Banka, kasa, kart</span></a>
<a class="command-card purple" href="finance_transfer.php"><small>Transfer</small><strong>↔</strong><span>Hesaplar arası aktarım</span></a>
</section>

<section class="panel">
<div class="panel-head"><h2>Son Finans Hareketleri</h2><a class="btn small secondary" href="finance_new.php">Yeni Hareket</a></div>
<table>
<thead>
<tr>
<th>Tarih</th>
<th>Tip</th>
<th>Cari</th>
<th>Kategori</th>
<th>Hesap</th>
<th>Yöntem</th>
<th>Tutar</th>
<th>Durum</th>
<th>Açıklama</th>
<th>İşlem</th>
</tr>
</thead>
<tbody>
<?php
require_once __DIR__.'/finance_lib.php';
$editableTypes=finance_movement_editable_types();
try{
    $st=db()->prepare("SELECT f.*, c.name contact_name, ac.name cat_name, a.name account_name, a.account_type, ta.name target_account_name
        FROM finance_movements f
        LEFT JOIN contacts c ON c.id=f.contact_id
        LEFT JOIN accounting_categories ac ON ac.id=f.category_id
        LEFT JOIN finance_accounts a ON a.id=f.account_id
        LEFT JOIN finance_accounts ta ON ta.id=f.target_account_id
        $sqlWhere
        ORDER BY f.id DESC LIMIT 150");
    $st->execute($params);
    $rows=$st->fetchAll();
    foreach($rows as $r){
        $rid=(int)$r['id'];
        $canEdit=in_array($r['movement_type'],$editableTypes,true) && can_edit_delete();
        echo "<tr>";
        echo "<td>".h($r['movement_date'])."</td>";
        echo "<td>".h($r['movement_type']==='transfer'?'Transfer':($r['direction']=='in'?'Tahsilat':'Ödeme'))."</td>";
        echo "<td>".h($r['contact_name'] ?: '-')."</td>";
        echo "<td>".h($r['cat_name'] ?: '-')."</td>";
        $account=$r['account_name'] ?: $r['payment_channel'];
        if($r['movement_type']==='transfer' && $r['target_account_name']) $account.=' → '.$r['target_account_name'];
        echo "<td>".h($account)."</td>";
        echo "<td>".h($r['payment_channel'])."</td>";
        echo "<td>".money($r['amount'])."</td>";
        echo "<td>".badge($r['status'],status_tone($r['status']))."</td>";
        echo "<td>".h($r['description'])."</td>";
        echo "<td>";
        if($canEdit){
            echo "<a class='btn small secondary' href='finance_new.php?id=".$rid."'>✏️ Düzenle</a> ";
            echo "<form method='post' action='sil.php' style='display:inline' onsubmit=\"return confirm('Bu finans hareketi KALICI olarak silinecek ve ilgili hesap bakiyesi geri alınacak. Emin misiniz?')\">"
                ."<input type='hidden' name='t' value='finance'>"
                ."<input type='hidden' name='id' value='".$rid."'>"
                ."<button class='btn small danger' type='submit'>🗑 Sil</button>"
                ."</form>";
        }elseif(!in_array($r['movement_type'],$editableTypes,true)){
            echo "<span class='muted' title='Satış/belge/transfer işleminden otomatik oluştu'>Otomatik</span>";
        }
        echo "</td>";
        echo "</tr>";
    }
    if(!$rows) echo "<tr><td colspan='10' class='muted'>Henüz finans hareketi yok.</td></tr>";
}catch(Throwable $e){
    echo "<tr><td colspan='10'><div class='alert'>".h($e->getMessage())."</div></td></tr>";
}
?>
</tbody>
</table>
</section>

<?php require_once __DIR__.'/layout_bottom.php'; ?>
