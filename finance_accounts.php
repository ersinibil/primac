<?php
require_once __DIR__.'/boot.php';
require_login();
require_once __DIR__.'/finance_lib.php';

$pdo=db();
$error='';
$ok= !empty($_GET['deleted']) ? 'Hesap silindi.' : '';

// FINANCE ACCOUNT LIST FILTER UX (2026-07-14): tür/durum/banka/arama, GET tabanlı server-side
// filtreleme. Whitelist dışı type/status sessizce "filtre yok" (Tümü) davranışına düşer — asla
// hataya ya da SQL'e ham yansımaya sebep olmaz (finance_account_filter_where(), finance_lib.php).
$type=$_GET['type'] ?? '';
$status=$_GET['status'] ?? '';
$bank=trim($_GET['bank'] ?? '');
$q=trim($_GET['q'] ?? '');
$hasFilter = ($type!=='' || $status!=='' || $bank!=='' || $q!=='');

if($_SERVER['REQUEST_METHOD']==='POST'){
    try{
        if(isset($_POST['save_account'])){
            $stmt=$pdo->prepare("INSERT INTO finance_accounts(name,account_type,bank_name,iban,card_last4,currency,opening_balance,current_balance,active,notes)
                VALUES(?,?,?,?,?,?,?,?,?,?)");
            $opening=(float)$_POST['opening_balance'];
            $stmt->execute([
                trim($_POST['name']),
                $_POST['account_type'],
                trim($_POST['bank_name']),
                trim($_POST['iban']),
                trim($_POST['card_last4']),
                $_POST['currency'],
                $opening,
                $opening,
                isset($_POST['active'])?1:0,
                trim($_POST['notes'])
            ]);
            $ok='Hesap eklendi.';
        }elseif(isset($_POST['edit_account'])){
            if(!can_edit_delete()){
                $error='Bu işlem için yetkiniz yok.';
            }else{
                finance_account_update($pdo, (int)$_POST['id'], $_POST);
                $ok='Hesap güncellendi.';
            }
        }elseif(isset($_POST['delete_account'])){
            if(!can_edit_delete()){
                $error='Hesap silme için yetkiniz yok.';
            }else{
                $res=finance_account_delete($pdo, (int)$_POST['id']);
                if($res['ok']) $ok=$res['msg']; else $error=$res['msg'];
            }
        }
    }catch(Throwable $e){
        $error=$e->getMessage();
    }
}

require_once __DIR__.'/layout_top.php';

list($where, $params) = finance_account_filter_where($type, $status, $bank, $q);
$typeCounts = finance_account_type_counts($pdo, $status);
$bankOptions = finance_account_bank_options($pdo);

// Tür sekmeleri diğer aktif filtreleri (durum/banka/arama) KAYBETMEDEN değiştirilebilsin diye
// her sekme linki mevcut status/bank/q'yu da taşır — "Kredi Kartları + Halkbank + 0472" gibi
// birleşik bir filtre, sekmeler arasında geçişte bozulmaz.
function finance_accounts_tab_url($typeVal, $status, $bank, $q){
    $qs = [];
    if($typeVal!=='') $qs['type']=$typeVal;
    if($status!=='') $qs['status']=$status;
    if($bank!=='') $qs['bank']=$bank;
    if($q!=='') $qs['q']=$q;
    return 'finance_accounts.php'.($qs ? '?'.http_build_query($qs) : '');
}
?>

<style>
.account-tabs{display:flex;gap:10px;flex-wrap:wrap;margin:8px 0 12px}
.account-tabs a{text-decoration:none;background:#eef2f6;color:#101828;border-radius:999px;padding:10px 14px;font-weight:900}
.account-tabs a.active{background:#111827;color:white}
.account-tabs a span{opacity:.65;font-weight:700}
.account-filter-bar{display:flex;gap:10px;flex-wrap:wrap;align-items:center;background:#f9fafb;border:1px solid #eef2f6;border-radius:14px;padding:10px 12px;margin:0 0 18px}
.account-filter-bar label{display:flex;flex-direction:column;gap:3px;font-size:11px;font-weight:800;color:#667085;text-transform:uppercase;letter-spacing:.03em}
.account-filter-bar select,.account-filter-bar input[type=text]{border:1px solid #e4e7ec;border-radius:9px;padding:8px 10px;font-size:14px;font-family:inherit;min-width:150px}
.account-filter-bar .clear-link{margin-left:auto;align-self:flex-end;font-size:13px;font-weight:800;color:#dc2626;text-decoration:none}
@media(max-width:720px){.account-filter-bar{flex-direction:column;align-items:stretch}.account-filter-bar select,.account-filter-bar input[type=text]{min-width:0}.account-filter-bar .clear-link{margin-left:0;align-self:flex-start}}
.account-card-grid{display:grid;grid-template-columns:repeat(4,minmax(160px,1fr));gap:14px;margin:14px 0 20px}
.account-card{border-radius:22px;background:white;padding:18px;box-shadow:0 10px 30px rgba(16,24,40,.07);border:1px solid #eef2f6}
.account-card small{color:#667085;font-weight:900}.account-card strong{display:block;font-size:24px;margin:8px 0}
@media(max-width:960px){.account-card-grid{grid-template-columns:1fr}}
</style>

<div class="panel-head">
<h1>Banka / Kasa / Kart Hesapları</h1>
<div class="actions">
<a class="btn" href="finance_new.php">+ Finans Hareketi</a>
<a class="btn secondary" href="finance_transfer.php">+ Transfer</a>
<a class="btn secondary" href="finance.php">Finans Paneli</a>
</div>
</div>

<?php if($error): ?><div class="alert"><?=h($error)?></div><?php endif; ?>
<?php if($ok): ?><div class="ok"><?=h($ok)?></div><?php endif; ?>

<?php
// Eski derin linkler (finance.php'nin ?type=POS gibi) "Diğer" havuzuna giren GERÇEK bir
// account_type ile gelebilir — bu durumda da "Diğer" sekmesi aktif görünsün (kozmetik, Ece'nin
// code review notu). Tanınmayan/garbage bir değer burada YOK sayılır (WHERE tarafı zaten onu
// "Tümü" gibi ele alıyor, sekmede de hiçbiri aktif görünmez — tutarlı).
$typeIsOther = in_array($type, ['Diger', 'POS', 'Diğer'], true);
?>
<div class="account-tabs">
<a class="<?=$type===''?'active':''?>" href="<?=h(finance_accounts_tab_url('', $status, $bank, $q))?>">Tümü <span>(<?=$typeCounts['all']?>)</span></a>
<a class="<?=$type==='Kasa'?'active':''?>" href="<?=h(finance_accounts_tab_url('Kasa', $status, $bank, $q))?>">💵 Kasalar <span>(<?=$typeCounts['Kasa']?>)</span></a>
<a class="<?=$type==='Banka'?'active':''?>" href="<?=h(finance_accounts_tab_url('Banka', $status, $bank, $q))?>">🏦 Banka Hesapları <span>(<?=$typeCounts['Banka']?>)</span></a>
<a class="<?=$type==='Kredi Kartı'?'active':''?>" href="<?=h(finance_accounts_tab_url('Kredi Kartı', $status, $bank, $q))?>">💳 Kredi Kartları <span>(<?=$typeCounts['Kredi Kartı']?>)</span></a>
<a class="<?=$typeIsOther?'active':''?>" href="<?=h(finance_accounts_tab_url('Diger', $status, $bank, $q))?>">➕ Diğer <span>(<?=$typeCounts['Diger']?>)</span></a>
</div>

<form method="get" class="account-filter-bar">
<?php if($type!==''): ?><input type="hidden" name="type" value="<?=h($type)?>"><?php endif; ?>
<label>Durum
<select name="status" onchange="this.form.submit()">
<option value="" <?=$status===''?'selected':''?>>Tümü</option>
<option value="active" <?=$status==='active'?'selected':''?>>Aktif</option>
<option value="passive" <?=$status==='passive'?'selected':''?>>Pasif</option>
</select>
</label>
<label>Banka
<select name="bank" onchange="this.form.submit()">
<option value="">Tüm Bankalar</option>
<?php foreach($bankOptions as $b): ?><option value="<?=h($b)?>" <?=$bank===$b?'selected':''?>><?=h($b)?></option><?php endforeach; ?>
</select>
</label>
<label>Arama
<input type="text" name="q" value="<?=h($q)?>" placeholder="Hesap, banka, IBAN veya kart ara...">
</label>
<button class="btn small" type="submit">Uygula</button>
<?php if($hasFilter): ?><a class="clear-link" href="finance_accounts.php">✕ Filtreyi Temizle</a><?php endif; ?>
</form>

<section class="account-card-grid">
<div class="account-card"><small>🏦 Banka</small><strong><?=money(safe_sum("SELECT COALESCE(SUM(current_balance),0) s FROM finance_accounts WHERE active=1 AND account_type='Banka'"))?></strong></div>
<div class="account-card"><small>💵 Kasa</small><strong><?=money(safe_sum("SELECT COALESCE(SUM(current_balance),0) s FROM finance_accounts WHERE active=1 AND account_type='Kasa'"))?></strong></div>
<div class="account-card"><small>💳 Kredi Kartı</small><strong><?=money(safe_sum("SELECT COALESCE(SUM(current_balance),0) s FROM finance_accounts WHERE active=1 AND account_type='Kredi Kartı'"))?></strong></div>
<div class="account-card"><small>🧾 POS</small><strong><?=money(safe_sum("SELECT COALESCE(SUM(current_balance),0) s FROM finance_accounts WHERE active=1 AND account_type='POS'"))?></strong></div>
</section>

<section class="panel">
<h2>Yeni Hesap</h2>
<form method="post" class="form-grid">

<label>Hesap Adı
<input name="name" required placeholder="Örn: Ziraat Bankası, Merkez Kasa, İş Kredi Kartı">
</label>

<label>Hesap Tipi
<select name="account_type">
<option <?=$type==='Banka'?'selected':''?>>Banka</option>
<option <?=$type==='Kasa'?'selected':''?>>Kasa</option>
<option <?=$type==='Kredi Kartı'?'selected':''?>>Kredi Kartı</option>
<option <?=$type==='POS'?'selected':''?>>POS</option>
<option>Diğer</option>
</select>
</label>

<label>Banka Adı
<input name="bank_name">
</label>

<label>IBAN
<input name="iban">
</label>

<label>Kart Son 4 Hane
<input name="card_last4" maxlength="4">
</label>

<label>Para Birimi
<select name="currency">
<option>TRY</option>
<option>USD</option>
<option>EUR</option>
</select>
</label>

<label>Açılış Bakiyesi
<input type="number" step="0.01" name="opening_balance" value="0">
</label>

<label class="full">Notlar
<textarea name="notes" rows="2"></textarea>
</label>

<label class="full"><input type="checkbox" name="active" checked style="width:auto"> Aktif</label>

<button class="btn" name="save_account" value="1">Hesabı Kaydet</button>
</form>
</section>

<section class="panel">
<h2>Hesaplar</h2>
<table>
<thead><tr><th>Hesap</th><th>Tip</th><th>Banka</th><th>IBAN/Kart</th><th>Bakiye</th><th>Durum</th><th>İşlem</th></tr></thead>
<tbody>
<?php
try{
$st=$pdo->prepare("SELECT * FROM finance_accounts $where ORDER BY active DESC, account_type, name");
$st->execute($params);
$rows=$st->fetchAll();
$acctTypes=finance_account_types();
// Filtreli listeden ekstreye geçip "Hesaplar"a dönüldüğünde filtre bağlamı korunsun diye kısa,
// whitelist'li r* parametreleri taşınıyor (finance_account_view.php kendi tarafında bunları
// SADECE finance_accounts.php'ye dönüş linkinde kullanır, ham URL/host asla kabul etmez).
$returnQs = [];
if($type!=='') $returnQs['rtype']=$type;
if($status!=='') $returnQs['rstatus']=$status;
if($bank!=='') $returnQs['rbank']=$bank;
if($q!=='') $returnQs['rq']=$q;
$returnQsStr = $returnQs ? '&'.http_build_query($returnQs) : '';
foreach($rows as $a){
    $aid=(int)$a['id'];
    echo "<tr>";
    echo "<td><a href='finance_account_view.php?id=".$aid."'><b>".h($a['name'])."</b></a></td>";
    echo "<td>".h($a['account_type'])."</td>";
    echo "<td>".h($a['bank_name'] ?: '-')."</td>";
    echo "<td>".h($a['iban'] ?: ($a['card_last4'] ? '**** '.$a['card_last4'] : '-'))."</td>";
    echo "<td>".money($a['current_balance'])."</td>";
    echo "<td>".($a['active']?badge('Aktif','green'):badge('Pasif','gray'))."</td>";
    echo "<td>"
        ."<a class='btn small secondary' href='finance_account_view.php?id=".$aid.h($returnQsStr)."'>📄 Ekstre</a> ";
    if(can_edit_delete()){
        echo "<button type='button' class='btn small secondary' onclick=\"document.getElementById('edit-acc-".$aid."').style.display=(document.getElementById('edit-acc-".$aid."').style.display==='none'?'table-row':'none')\">✏️ Düzenle</button> ";
        echo "<form method='post' style='display:inline' onsubmit=\"return confirm('Bu hesabı silmek istediğinize emin misiniz? Hareketleri olan hesaplar kalıcı silinmez, pasife alınır.')\">"
            ."<input type='hidden' name='id' value='".$aid."'>"
            ."<button class='btn small danger' name='delete_account' value='1'>🗑 Sil</button>"
            ."</form>";
    }
    echo "</td>";
    echo "</tr>";
    if(can_edit_delete()){
    echo "<tr id='edit-acc-".$aid."' style='display:none;background:#f9fafb'><td colspan='7'>";
    echo "<form method='post' class='form-grid' style='margin:10px 0'>";
    echo "<input type='hidden' name='id' value='".$aid."'>";
    echo "<label>Hesap Adı<input name='name' required value='".h($a['name'])."'></label>";
    echo "<label>Hesap Tipi<select name='account_type'>";
    foreach($acctTypes as $t){ echo "<option ".($a['account_type']===$t?'selected':'').">".h($t)."</option>"; }
    echo "</select></label>";
    echo "<label>Banka Adı<input name='bank_name' value='".h($a['bank_name'])."'></label>";
    echo "<label>IBAN<input name='iban' value='".h($a['iban'])."'></label>";
    echo "<label>Kart Son 4 Hane<input name='card_last4' maxlength='4' value='".h($a['card_last4'])."'></label>";
    echo "<label>Para Birimi<select name='currency'>";
    foreach(['TRY','USD','EUR'] as $c){ echo "<option ".($a['currency']===$c?'selected':'').">".h($c)."</option>"; }
    echo "</select></label>";
    echo "<label class='full'>Notlar<textarea name='notes' rows='2'>".h($a['notes'])."</textarea></label>";
    echo "<label class='full'><input type='checkbox' name='active' ".($a['active']?'checked':'')." style='width:auto'> Aktif</label>";
    echo "<button class='btn' name='edit_account' value='1'>💾 Kaydet</button>";
    echo "</form>";
    echo "</td></tr>";
    }
}
if(!$rows){
    if($hasFilter){
        echo "<tr><td colspan='7' class='muted' style='text-align:center;padding:24px 12px'>Seçili filtrelere uygun hesap bulunamadı.<br><a href='finance_accounts.php' style='font-weight:800'>Filtreleri Temizle</a></td></tr>";
    } else {
        echo "<tr><td colspan='7' class='muted'>Hesap yok.</td></tr>";
    }
}
}catch(Throwable $e){
echo "<tr><td colspan='7'><div class='alert'>".h($e->getMessage())."</div></td></tr>";
}
?>
</tbody>
</table>
</section>

<?php require_once __DIR__.'/layout_bottom.php'; ?>
