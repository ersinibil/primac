<?php
/* Hesap ekstresi — tek banka/kasa/kart/POS hesabının hareketleri ve güncel bakiyesi. */
require_once __DIR__.'/boot.php';
require_login();
require_once __DIR__.'/finance_lib.php';
$pdo=db();
$id=(int)($_GET['id'] ?? 0);
$editError='';
$editOk='';

if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['edit_account'])){
    if(!can_edit_delete()){
        $editError='Bu işlem için yetkiniz yok.';
    }else{
        try{
            finance_account_update($pdo, $id, $_POST);
            $editOk='Hesap güncellendi.';
        }catch(Throwable $e){
            $editError=$e->getMessage();
        }
    }
}

$a=null;
try{ $s=$pdo->prepare("SELECT * FROM finance_accounts WHERE id=?"); $s->execute([$id]); $a=$s->fetch(); }catch(Throwable $e){}

// FINANCE ACCOUNT LIST FILTER UX (2026-07-14): finance_accounts.php'den filtreli gelindiyse
// (r* param'ları), "Hesaplar" linki filtre bağlamını korusun diye aynı 4 anahtarı geri taşır.
// Asla ham URL/host kabul ETMEZ — sadece sabit finance_accounts.php'ye eklenen bilinen 4 query
// param'ı, open redirect riski yok.
$backQs=[];
if(!empty($_GET['rtype'])) $backQs['type']=$_GET['rtype'];
if(!empty($_GET['rstatus'])) $backQs['status']=$_GET['rstatus'];
if(!empty($_GET['rbank'])) $backQs['bank']=$_GET['rbank'];
if(!empty($_GET['rq'])) $backQs['q']=$_GET['rq'];
$accountsBackUrl='finance_accounts.php'.($backQs ? '?'.http_build_query($backQs) : '');

require_once __DIR__.'/layout_top.php';
if(!$a){ echo ds_alert('danger','Hesap bulunamadı.'); require __DIR__.'/layout_bottom.php'; exit; }

// Bu hesabı etkileyen tüm hareketler (doğrudan ya da transfer hedefi)
$rows=[];
try{
    $q=$pdo->prepare("SELECT m.*, c.name contact_name FROM finance_movements m
        LEFT JOIN contacts c ON c.id=m.contact_id
        WHERE m.account_id=? OR m.target_account_id=?
        ORDER BY m.movement_date DESC, m.id DESC LIMIT 300");
    $q->execute([$id,$id]);
    $rows=$q->fetchAll();
}catch(Throwable $e){}

$isCard = ($a['account_type']==='Kredi Kartı');
$__faActions = ds_button('Hesaplar', $accountsBackUrl, 'secondary', '', '', true);
if($isCard) $__faActions .= ds_button('💳 Karta Ödeme', 'finance_transfer.php?to='.$id, 'primary', '', '', true);
$__faActions .= ds_button('+ Giriş', 'finance_new.php?direction=in&account_id='.$id, 'primary', '', '', true);
$__faActions .= ds_button('+ Çıkış', 'finance_new.php?direction=out&account_id='.$id, 'secondary', '', '', true);
if(can_edit_delete()) $__faActions .= delete_button('account',$id);
ds_page_header($a['name'].' · '.$a['account_type'].($a['bank_name']?' · '.$a['bank_name']:''), ds_icon('wallet',24), '', $__faActions, false, true);
?>

<?php if(isset($_GET['deleted'])): ?><?=ds_alert('success','Finans hareketi silindi, hesap bakiyesi güncellendi.')?><?php endif; ?>
<?php if($editOk): ?><?=ds_alert('success',$editOk)?><?php endif; ?>
<?php if($editError): ?><?=ds_alert('danger',$editError)?><?php endif; ?>

<div class="df-stat-row">
  <div class="df-stat"><span>Güncel Bakiye</span><strong><?=money($a['current_balance'])?></strong></div>
  <div class="df-stat"><span>Açılış Bakiyesi</span><strong><?=money($a['opening_balance'])?></strong></div>
  <div class="df-stat"><span><?=$isCard?'Kart No':'IBAN'?></span><strong style="font-size:16px"><?=h($isCard ? ($a['card_last4']?'**** '.$a['card_last4']:'-') : ($a['iban']?:'-'))?></strong></div>
  <div class="df-stat"><span>Hareket Sayısı</span><strong><?=count($rows)?></strong></div>
</div>

<?php if(can_edit_delete()): ?>
<section class="df-card" style="margin-top:var(--df-space-4)">
<details><summary style="font-weight:700;cursor:pointer;font-size:15px">✏️ Hesabı Düzenle</summary>
<form method="post" class="df-form-grid-2" style="margin-top:var(--df-space-3)">
<?php ds_form_field('Hesap Adı', '<input name="name" required value="'.h($a['name']).'">'); ?>
<?php
$__atOpts=''; foreach(finance_account_types() as $t){ $__atOpts.='<option '.($a['account_type']===$t?'selected':'').'>'.h($t).'</option>'; }
ds_form_field('Hesap Tipi', '<select name="account_type">'.$__atOpts.'</select>');
?>
<?php ds_form_field('Banka Adı', '<input name="bank_name" value="'.h($a['bank_name']).'">'); ?>
<?php ds_form_field('IBAN', '<input name="iban" value="'.h($a['iban']).'">'); ?>
<?php ds_form_field('Kart Son 4 Hane', '<input name="card_last4" maxlength="4" value="'.h($a['card_last4']).'">'); ?>
<?php
$__curOpts=''; foreach(['TRY','USD','EUR'] as $c){ $__curOpts.='<option '.($a['currency']===$c?'selected':'').'>'.h($c).'</option>'; }
ds_form_field('Para Birimi', '<select name="currency">'.$__curOpts.'</select>');
?>
<div class="df-form-span-2"><?php ds_form_field('Notlar', '<textarea name="notes" rows="2">'.h($a['notes']).'</textarea>'); ?></div>
<div class="df-form-span-2">
<label style="display:flex;align-items:center;gap:8px">
<input type="checkbox" name="active" <?=$a['active']?'checked':''?> style="width:auto"> Aktif
</label>
</div>
<div class="df-form-span-2"><button class="df-btn df-btn--primary" name="edit_account" value="1">💾 Kaydet</button></div>
</form>
</details>
</section>
<?php endif; ?>

<?php if($isCard): ?>
<div class="df-alert df-alert--info" style="margin-top:var(--df-space-4)">💳 Bu bir kredi kartı hesabıdır. Karta yapılan ödemeler (kasa/bankadan) <b>Karta Ödeme</b> ile işlenir; harcamalar <b>+ Çıkış</b> olarak girilir.</div>
<?php endif; ?>

<section class="df-card" style="margin-top:var(--df-space-4)">
<h2 class="df-section-title">Hesap Hareketleri</h2>
<div class="df-table-wrap"><table class="df-table">
<thead><tr><th>Tarih</th><th>Açıklama</th><th>Cari</th><th>Tür</th><th style="text-align:right">Tutar</th><th>İşlem</th></tr></thead>
<tbody>
<?php foreach($rows as $m):
    // Bu hesaba etkisi: transfer hedefi ise giriş (+); değilse direction'a göre
    $incoming = ((int)$m['target_account_id']===$id) || ($m['direction']==='in' && (int)$m['account_id']===$id);
    $sign = $incoming ? '+' : '−';
    $tone = $incoming ? 'green' : 'red';
    // FINANCE CRUD UX PATCH 001 (2026-07-12, Ece/code-review): eskiden burada da direction bazlı
    // "Tahsilat"/"Ödeme" yazıyordu — bu tablo account_id filtreli olduğu için normalde sadece
    // gerçek nakit hareketleri görünür, ama migration-öncesi eski "Peşin satış" kayıtları (account_id
    // dolu) hâlâ buraya düşebilir ve yanlış etiketlenirdi. finance_movement_type_label() ile
    // contact_view.php'de düzeltilen aynı hata burada da giderildi.
    $label = ($m['movement_type']==='transfer') ? ((int)$m['target_account_id']===$id?'Transfer Giriş':'Transfer Çıkış') : finance_movement_type_label($m);
    // FINANCE CRUD UX PATCH 001 (2026-07-12): aynı karar mekanizması contact_view.php/finance.php
    // ile birebir — bir kaydın cari ekranında düzenlenebilir, kasa ekranında düzenlenemez olması
    // yasak (kullanıcı talebi), tek merkezi fonksiyon bunu garanti ediyor.
    $mid=(int)$m['id'];
    $actions=finance_movement_actions($m);
    $canEdit=$actions['editable'] && can_edit_delete();
?>
<tr>
  <td><?=h($m['movement_date'])?></td>
  <td><?=h($m['description'] ?: '-')?></td>
  <td><?=h($m['contact_name'] ?: '-')?></td>
  <td><?=ds_badge($label,$tone)?></td>
  <td style="text-align:right;font-weight:700;color:<?=$incoming?'var(--df-success-ink)':'var(--df-danger-ink)'?>"><?=$sign.' '.money($m['amount'])?></td>
  <td>
  <?php if($canEdit): ?>
    <a class="df-btn df-btn--secondary df-btn--sm" href="finance_new.php?id=<?=$mid?>&return_context=account&return_ref=<?=$id?>">✏️ Düzenle</a>
    <form method="post" action="sil.php" style="display:inline" onsubmit="return confirm('Bu finans hareketi KALICI olarak silinecek ve ilgili hesap bakiyesi geri alınacak. Emin misiniz?')">
      <input type="hidden" name="t" value="finance">
      <input type="hidden" name="id" value="<?=$mid?>">
      <input type="hidden" name="return_context" value="account">
      <input type="hidden" name="return_ref" value="<?=$id?>">
      <button class="df-btn df-btn--danger df-btn--sm" type="submit">🗑 Sil</button>
    </form>
  <?php elseif($actions['source_url']): ?>
    <a class="df-btn df-btn--secondary df-btn--sm" href="<?=h($actions['source_url'])?>" title="<?=h($actions['block_reason'])?>"><?=h($actions['source_label'])?></a>
  <?php else: ?>
    <span class="df-muted" title="<?=h($actions['block_reason'])?>">Otomatik</span>
  <?php endif; ?>
  </td>
</tr>
<?php endforeach; ?>
<?php if(!$rows): ?><tr><td colspan="6" class="df-muted">Bu hesapta henüz hareket yok.</td></tr><?php endif; ?>
</tbody>
</table></div>
</section>

<style>
body.nav-compact .df-form-grid-2{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:0 var(--df-space-4)}
body.nav-compact .df-form-span-2{grid-column:1 / -1}
body.nav-compact .df-stat-row{display:flex;flex-wrap:wrap;gap:var(--df-space-3);margin:var(--df-space-4) 0}
body.nav-compact .df-stat{flex:1;min-width:120px;background:var(--df-surface);border:1px solid var(--df-hairline);border-radius:var(--df-radius-md);padding:var(--df-space-3);display:flex;flex-direction:column;gap:4px}
body.nav-compact .df-stat span{font-size:var(--df-type-caption-size);color:var(--df-ink-500)}
body.nav-compact .df-stat strong{font-size:var(--df-type-subtitle-size);color:var(--df-ink-900)}
body.nav-compact .df-section-title{font-size:var(--df-type-section-size);font-weight:var(--df-type-section-weight);color:var(--df-ink-900);margin:0 0 var(--df-space-3)}
@media(max-width:640px){body.nav-compact .df-form-grid-2{grid-template-columns:1fr}}
</style>

<?php require_once __DIR__.'/layout_bottom.php'; ?>
