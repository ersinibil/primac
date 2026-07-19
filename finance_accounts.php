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

<?php
$__faActions = ds_button('+ Finans Hareketi','finance_new.php','primary','','',true)
    . ds_button('+ Transfer','finance_transfer.php','secondary','','',true)
    . ds_button('Finans Paneli','finance.php','secondary','','',true);
ds_page_header('Banka / Kasa / Kart Hesapları', ds_icon('wallet',24), '', $__faActions, false, true);
?>

<?php if($error): ?><?=ds_alert('danger',$error)?><?php endif; ?>
<?php if($ok): ?><?=ds_alert('success',$ok)?><?php endif; ?>

<?php
// P0 VERİ BÜTÜNLÜĞÜ (2026-07-19, pilot öncesi kapanış): salt-okunur entegrasyon raporu — bir
// finans hareketi/belge/çek-senet kaydının işaret ettiği hesap artık yoksa (silinmiş) burada
// gösterilir. Otomatik onarım YAPILMAZ (Product Owner kararı: "otomatik uydurma hesap oluşturma") —
// sadece admin'e görünür kılınır, karar admin'e ait.
if(is_admin()){
    try{
        $__orphanReport = finance_account_orphan_report($pdo);
        $__orphanTotal = count($__orphanReport['finance_movements'])+count($__orphanReport['trade_documents'])+count($__orphanReport['checks_notes']);
        if($__orphanTotal > 0):
    ?>
    <section class="df-card" style="margin-bottom:var(--df-space-4);border-color:var(--df-danger)">
    <details>
    <summary style="cursor:pointer;font-weight:800;color:var(--df-danger-ink)"><?=ds_icon('info',16)?> Veri bütünlüğü uyarısı — <?=$__orphanTotal?> kayıt artık var olmayan bir hesaba işaret ediyor</summary>
    <p style="margin:10px 0;color:var(--df-ink-500)">Bu kayıtların işaret ettiği hesap silinmiş. Otomatik düzeltme yapılmadı — aşağıdaki kayıtları inceleyip gerekirse elle (yeni hesap oluşturup ilgili kaydı düzenleyerek) ilişkilendirin.</p>
    <?php if($__orphanReport['finance_movements']): ?>
    <b>Finans Hareketleri (<?=count($__orphanReport['finance_movements'])?>)</b>
    <div class="df-table-wrap"><table class="df-table"><thead><tr><th>Tarih</th><th>Açıklama</th><th>Tutar</th><th>Cari</th></tr></thead><tbody>
    <?php foreach($__orphanReport['finance_movements'] as $__r): ?>
    <tr><td><?=h($__r['movement_date'])?></td><td><?=h($__r['description'])?></td><td><?=money($__r['amount'])?></td><td><?=h($__r['contact_name']??'—')?></td></tr>
    <?php endforeach; ?>
    </tbody></table></div>
    <?php endif; ?>
    <?php if($__orphanReport['trade_documents']): ?>
    <b>Alış/Satış Belgeleri (<?=count($__orphanReport['trade_documents'])?>)</b>
    <div class="df-table-wrap"><table class="df-table"><thead><tr><th>Belge No</th><th>Tür</th><th>Tarih</th><th>Toplam</th></tr></thead><tbody>
    <?php foreach($__orphanReport['trade_documents'] as $__r): ?>
    <tr><td><a href="trade_document_view.php?id=<?=(int)$__r['id']?>"><?=h($__r['document_no'])?></a></td><td><?=h($__r['document_type']==='purchase'?'Alış':'Satış')?></td><td><?=h($__r['document_date'])?></td><td><?=money($__r['grand_total'])?></td></tr>
    <?php endforeach; ?>
    </tbody></table></div>
    <?php endif; ?>
    <?php if($__orphanReport['checks_notes']): ?>
    <b>Çek/Senet (<?=count($__orphanReport['checks_notes'])?>)</b>
    <div class="df-table-wrap"><table class="df-table"><thead><tr><th>No</th><th>Tutar</th><th>Durum</th><th>Cari</th></tr></thead><tbody>
    <?php foreach($__orphanReport['checks_notes'] as $__r): ?>
    <tr><td><a href="check_note_view.php?id=<?=(int)$__r['id']?>"><?=h($__r['number']?:'#'.$__r['id'])?></a></td><td><?=money($__r['amount'])?></td><td><?=h($__r['status'])?></td><td><?=h($__r['contact_name']??'—')?></td></tr>
    <?php endforeach; ?>
    </tbody></table></div>
    <?php endif; ?>
    </details>
    </section>
    <?php endif;
    }catch(Throwable $e){}
}
// KOK NEDEN DUZELTMESI (2026-07-19, P0): burada fazladan bir PHP kapanis etiketi vardi, PHP
// modunu erken kapatip asagidaki yorum satirlarini/typeIsOther/ds_tabs cagrisini (onceden buraya
// dogru sekilde bagli, hic degismemis kod) duz HTML metni olarak bastiriyordu. Kaldirildi.
// UYARI: Bu yorum blogunda kapanis etiketinin iki karakterini yan yana YAZMA — PHP tek satirlik
// yorumlari tirnak/baglam tanimadan o iki karakter dizisini gorunce yorumu ve PHP modunu kapatir.
// Eski derin linkler (finance.php'nin ?type=POS gibi) "Diğer" havuzuna giren GERÇEK bir
// account_type ile gelebilir — bu durumda da "Diğer" sekmesi aktif görünsün (kozmetik, Ece'nin
// code review notu). Tanınmayan/garbage bir değer burada YOK sayılır (WHERE tarafı zaten onu
// "Tümü" gibi ele alıyor, sekmede de hiçbiri aktif görünmez — tutarlı).
$typeIsOther = in_array($type, ['Diger', 'POS', 'Diğer'], true);
ds_tabs([
    ['label'=>'Tümü ('.$typeCounts['all'].')','url'=>finance_accounts_tab_url('', $status, $bank, $q),'active'=>$type===''],
    ['label'=>'💵 Kasalar ('.$typeCounts['Kasa'].')','url'=>finance_accounts_tab_url('Kasa', $status, $bank, $q),'active'=>$type==='Kasa'],
    ['label'=>'🏦 Banka Hesapları ('.$typeCounts['Banka'].')','url'=>finance_accounts_tab_url('Banka', $status, $bank, $q),'active'=>$type==='Banka'],
    ['label'=>'💳 Kredi Kartları ('.$typeCounts['Kredi Kartı'].')','url'=>finance_accounts_tab_url('Kredi Kartı', $status, $bank, $q),'active'=>$type==='Kredi Kartı'],
    ['label'=>'➕ Diğer ('.$typeCounts['Diger'].')','url'=>finance_accounts_tab_url('Diger', $status, $bank, $q),'active'=>$typeIsOther],
]);
?>

<section class="df-card" style="margin:var(--df-space-4) 0">
<form method="get" class="df-form-grid-3">
<?php if($type!==''): ?><input type="hidden" name="type" value="<?=h($type)?>"><?php endif; ?>
<?php
ds_form_field('Durum', '<select name="status" onchange="this.form.submit()">
<option value="" '.($status===''?'selected':'').'>Tümü</option>
<option value="active" '.($status==='active'?'selected':'').'>Aktif</option>
<option value="passive" '.($status==='passive'?'selected':'').'>Pasif</option>
</select>');

$__bankOptsHtml='<option value="">Tüm Bankalar</option>';
foreach($bankOptions as $b){ $__bankOptsHtml.='<option value="'.h($b).'" '.($bank===$b?'selected':'').'>'.h($b).'</option>'; }
ds_form_field('Banka', '<select name="bank" onchange="this.form.submit()">'.$__bankOptsHtml.'</select>');

ds_form_field('Arama', '<input type="text" name="q" value="'.h($q).'" placeholder="Hesap, banka, IBAN veya kart ara...">');
?>
<div class="df-form-span-3" style="display:flex;gap:var(--df-space-3);align-items:center">
<button class="df-btn df-btn--secondary" type="submit">Uygula</button>
<?php if($hasFilter): ?><a href="finance_accounts.php" style="font-size:13px;font-weight:700;color:var(--df-danger-ink)">✕ Filtreyi Temizle</a><?php endif; ?>
</div>
</form>
</section>

<section class="finance-grid">
<div class="finance-tile ft-bank"><small>🏦 Banka</small><strong><?=money(safe_sum("SELECT COALESCE(SUM(current_balance),0) s FROM finance_accounts WHERE active=1 AND account_type='Banka'"))?></strong></div>
<div class="finance-tile ft-cash"><small>💵 Kasa</small><strong><?=money(safe_sum("SELECT COALESCE(SUM(current_balance),0) s FROM finance_accounts WHERE active=1 AND account_type='Kasa'"))?></strong></div>
<div class="finance-tile ft-card"><small>💳 Kredi Kartı</small><strong><?=money(safe_sum("SELECT COALESCE(SUM(current_balance),0) s FROM finance_accounts WHERE active=1 AND account_type='Kredi Kartı'"))?></strong></div>
<div class="finance-tile ft-pos"><small>🧾 POS</small><strong><?=money(safe_sum("SELECT COALESCE(SUM(current_balance),0) s FROM finance_accounts WHERE active=1 AND account_type='POS'"))?></strong></div>
</section>

<section class="df-card">
<h2 style="font-size:var(--df-type-section-size);margin:0 0 var(--df-space-3)">Yeni Hesap</h2>
<form method="post" class="df-form-grid-3">

<?php
ds_form_field('Hesap Adı', '<input name="name" required placeholder="Örn: Ziraat Bankası, Merkez Kasa, İş Kredi Kartı">');
ds_form_field('Hesap Tipi', '<select name="account_type">
<option '.($type==='Banka'?'selected':'').'>Banka</option>
<option '.($type==='Kasa'?'selected':'').'>Kasa</option>
<option '.($type==='Kredi Kartı'?'selected':'').'>Kredi Kartı</option>
<option '.($type==='POS'?'selected':'').'>POS</option>
<option>Diğer</option>
</select>');
ds_form_field('Banka Adı', '<input name="bank_name">');
ds_form_field('IBAN', '<input name="iban">');
ds_form_field('Kart Son 4 Hane', '<input name="card_last4" maxlength="4">');
ds_form_field('Para Birimi', '<select name="currency"><option>TRY</option><option>USD</option><option>EUR</option></select>');
ds_form_field('Açılış Bakiyesi', '<input type="number" step="0.01" name="opening_balance" value="0">');
?>

<div class="df-form-span-3"><?php ds_form_field('Notlar', '<textarea name="notes" rows="2"></textarea>'); ?></div>
<div class="df-form-span-3"><label style="display:flex;align-items:center;gap:8px"><input type="checkbox" name="active" checked style="width:auto"> Aktif</label></div>
<div class="df-form-span-3"><button class="df-btn df-btn--primary" name="save_account" value="1">Hesabı Kaydet</button></div>
</form>
</section>

<section class="df-card" style="margin-top:var(--df-space-4)">
<h2 style="font-size:var(--df-type-section-size);margin:0 0 var(--df-space-3)">Hesaplar</h2>
<div class="df-table-wrap"><table class="df-table">
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
    echo "<td>".($a['active']?ds_badge('Aktif','green'):ds_badge('Pasif','gray'))."</td>";
    echo "<td><div class='row-actions'>"
        ."<a class='df-btn df-btn--secondary df-btn--sm' href='finance_account_view.php?id=".$aid.h($returnQsStr)."'>📄 Ekstre</a>";
    if(can_edit_delete()){
        echo "<button type='button' class='df-btn df-btn--secondary df-btn--sm' onclick=\"document.getElementById('edit-acc-".$aid."').style.display=(document.getElementById('edit-acc-".$aid."').style.display==='none'?'table-row':'none')\">✏️ Düzenle</button>";
        // FİNANS UX NETLEŞTİRME (2026-07-19, P0 kapanış): kullanılmış (geçmiş hareketi olan) bir
        // hesapta kırmızı "Sil" varsayılan aksiyon olarak durmasın — geri uçta zaten soft-delete
        // (pasife alma) yapılıyordu, buton dili artık bunu ÖNCEDEN doğru yansıtıyor.
        $__used = finance_account_has_movements($pdo,$aid);
        if($__used){
            echo "<form method='post' style='display:inline' onsubmit=\"return confirm('Bu hesap geçmiş finans hareketlerinde kullanıldığı için kalıcı silinemez, pasife alınacak. Devam edilsin mi?')\">"
                ."<input type='hidden' name='id' value='".$aid."'>"
                ."<button class='df-btn df-btn--secondary df-btn--sm' name='delete_account' value='1'>⏸ Pasife Al</button>"
                ."</form>";
        } else {
            echo "<form method='post' style='display:inline' onsubmit=\"return confirm('Bu hesabı kalıcı olarak silmek istediğinize emin misiniz? Hiç kullanılmadığı için geri dönüşü olmayan gerçek bir silme olacak.')\">"
                ."<input type='hidden' name='id' value='".$aid."'>"
                ."<button class='df-btn df-btn--danger df-btn--sm' name='delete_account' value='1'>🗑 Sil</button>"
                ."</form>";
        }
    }
    echo "</div></td>";
    echo "</tr>";
    if(can_edit_delete()){
    echo "<tr id='edit-acc-".$aid."' style='display:none;background:var(--df-surface-sunken)'><td colspan='7'>";
    echo "<form method='post' class='df-form-grid-3' style='margin:10px 0;padding:var(--df-space-3) 0'>";
    echo "<input type='hidden' name='id' value='".$aid."'>";
    echo "<div class='df-form-group'><label class='df-form-label'>Hesap Adı</label><input name='name' required value='".h($a['name'])."'></div>";
    $__acctTypeOptsE='';
    foreach($acctTypes as $t){ $__acctTypeOptsE.='<option '.($a['account_type']===$t?'selected':'').'>'.h($t).'</option>'; }
    echo "<div class='df-form-group'><label class='df-form-label'>Hesap Tipi</label><select name='account_type'>".$__acctTypeOptsE."</select></div>";
    echo "<div class='df-form-group'><label class='df-form-label'>Banka Adı</label><input name='bank_name' value='".h($a['bank_name'])."'></div>";
    echo "<div class='df-form-group'><label class='df-form-label'>IBAN</label><input name='iban' value='".h($a['iban'])."'></div>";
    echo "<div class='df-form-group'><label class='df-form-label'>Kart Son 4 Hane</label><input name='card_last4' maxlength='4' value='".h($a['card_last4'])."'></div>";
    $__curOptsE='';
    foreach(['TRY','USD','EUR'] as $c){ $__curOptsE.='<option '.($a['currency']===$c?'selected':'').'>'.h($c).'</option>'; }
    echo "<div class='df-form-group'><label class='df-form-label'>Para Birimi</label><select name='currency'>".$__curOptsE."</select></div>";
    echo "<div class='df-form-span-3 df-form-group'><label class='df-form-label'>Notlar</label><textarea name='notes' rows='2'>".h($a['notes'])."</textarea></div>";
    echo "<div class='df-form-span-3'><label style='display:flex;align-items:center;gap:8px'><input type='checkbox' name='active' ".($a['active']?'checked':'')." style='width:auto'> Aktif</label></div>";
    echo "<div class='df-form-span-3'><button class='df-btn df-btn--primary' name='edit_account' value='1'>💾 Kaydet</button></div>";
    echo "</form>";
    echo "</td></tr>";
    }
}
if(!$rows){
    if($hasFilter){
        echo "<tr><td colspan='7' style='text-align:center;padding:24px 12px;color:var(--df-ink-500)'>Seçili filtrelere uygun hesap bulunamadı.<br><a href='finance_accounts.php' style='font-weight:800'>Filtreleri Temizle</a></td></tr>";
    } else {
        echo "<tr><td colspan='7' style='color:var(--df-ink-500)'>Hesap yok.</td></tr>";
    }
}
}catch(Throwable $e){
echo "<tr><td colspan='7'>".ds_alert('danger',$e->getMessage())."</td></tr>";
}
?>
</tbody>
</table></div>
</section>

<style>
.finance-grid{display:grid;grid-template-columns:repeat(4,minmax(160px,1fr));gap:var(--df-space-4);margin:var(--df-space-4) 0 var(--df-space-5)}
.finance-tile{display:block;border-radius:var(--df-radius-lg);padding:var(--df-space-4);color:var(--df-ink-900);box-shadow:var(--df-elevation-raised);border:1px solid var(--df-hairline)}
.finance-tile small{display:block;font-weight:900;color:var(--df-ink-600)}
.finance-tile strong{display:block;font-size:26px;margin:10px 0 0}
.ft-bank{background:linear-gradient(135deg,#dbeafe,#eff6ff)}
.ft-cash{background:linear-gradient(135deg,#dcfce7,#f0fdf4)}
.ft-card{background:linear-gradient(135deg,#fee2e2,#fff1f2)}
.ft-pos{background:linear-gradient(135deg,#fef3c7,#fffbeb)}
@media(max-width:960px){.finance-grid{grid-template-columns:1fr}}
body.nav-compact .df-form-grid-3{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:0 var(--df-space-4)}
body.nav-compact .df-form-span-3{grid-column:1 / -1}
@media(max-width:900px){body.nav-compact .df-form-grid-3{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:640px){body.nav-compact .df-form-grid-3{grid-template-columns:1fr}}
</style>

<?php require_once __DIR__.'/layout_bottom.php'; ?>
