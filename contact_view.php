<?php
require_once __DIR__.'/boot.php';
require_once __DIR__.'/finance_lib.php';
require_once __DIR__.'/cpa_lib.php';
require_once __DIR__.'/cpa_allocation_lib.php';
require_login();

$pdo=db();
$id=(int)($_GET['id'] ?? 0);
$error='';
$ok='';

// Kolon güvenceleri
try{ $pdo->exec("ALTER TABLE contacts ADD COLUMN active TINYINT(1) NOT NULL DEFAULT 1"); }catch(Throwable $e){}
try{ $pdo->exec("ALTER TABLE contacts ADD COLUMN phone2 varchar(60) DEFAULT NULL AFTER phone"); }catch(Throwable $e){}
try{ $pdo->exec("ALTER TABLE contacts ADD COLUMN website varchar(255) DEFAULT NULL AFTER email"); }catch(Throwable $e){}
try{ $pdo->exec("ALTER TABLE contacts ADD COLUMN postal_code varchar(20) DEFAULT NULL AFTER district"); }catch(Throwable $e){}
try{ $pdo->exec("ALTER TABLE contacts ADD COLUMN iban varchar(60) DEFAULT NULL AFTER postal_code"); }catch(Throwable $e){}

// Aktif/Pasif toggle
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['toggle_active'])){
    if(is_admin() || user_can('contacts')){
        try{
            $newActive=(int)$_POST['toggle_active'];
            $pdo->prepare("UPDATE contacts SET active=? WHERE id=?")->execute([$newActive,$id]);
        }catch(Throwable $e){}
    }
    header('Location: contact_view.php?id='.$id); exit;
}

if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['save_profile'])){
    if(!can_edit_delete()){
        $error='Bu işlem için yetkiniz yok.';
    } else {
    try{
        $stmt=$pdo->prepare("UPDATE contacts SET
            name=?, type=?, authorized_person=?,
            phone=?, phone2=?, email=?, website=?,
            tax_office=?, tax_number=?,
            city=?, district=?, postal_code=?, address=?,
            iban=?, opening_balance=?, notes=?, representative_mode=?
            WHERE id=?
        ");
        $stmt->execute([
            trim($_POST['name']),
            $_POST['type'],
            trim($_POST['authorized_person']),
            trim($_POST['phone']),
            trim($_POST['phone2']),
            trim($_POST['email']),
            trim($_POST['website']),
            trim($_POST['tax_office']),
            trim($_POST['tax_number']),
            trim($_POST['city']),
            trim($_POST['district']),
            trim($_POST['postal_code']),
            trim($_POST['address']),
            trim($_POST['iban']),
            (float)$_POST['opening_balance'],
            trim($_POST['notes']),
            $_POST['representative_mode'],
            $id
        ]);

        $pdo->prepare("CREATE TABLE IF NOT EXISTS contact_representatives (
            id INT AUTO_INCREMENT PRIMARY KEY,
            contact_id INT NOT NULL,
            personnel_id INT NOT NULL,
            is_primary TINYINT(1) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_contact_personnel(contact_id, personnel_id),
            INDEX idx_contact(contact_id),
            INDEX idx_personnel(personnel_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4")->execute();

        $pdo->prepare("DELETE FROM contact_representatives WHERE contact_id=?")->execute([$id]);

        if($_POST['representative_mode']==='personel' && !empty($_POST['representatives'])){
            $first=true;
            $ins=$pdo->prepare("INSERT IGNORE INTO contact_representatives(contact_id,personnel_id,is_primary) VALUES(?,?,?)");
            foreach($_POST['representatives'] as $pid){
                $ins->execute([$id,(int)$pid,$first?1:0]);
                $first=false;
            }
        }

        $ok='Cari profil güncellendi.';
    }catch(Throwable $e){
        $error=$e->getMessage();
    }
    }
}

// P1 — CPA (2026-07-18): tercih ekle/güncelle ve pasife al — cpa_lib.php ile aynı yetki kuralı
// (cpa_can_edit() içinde uygulanır), IDOR'a kapalı (hedef kayıt her zaman DB'den doğrulanır).
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['cpa_save'])){
    try{
        cpa_upsert($pdo, $_SESSION['user']['id']??0, $id, $_POST['stock_item_id']??0, $_POST['supplier_id']??0, $_POST['priority']??1, !empty($_POST['is_default']), $_POST['notes']??'');
        $ok='Tedarik tercihi kaydedildi.';
    }catch(Throwable $e){ $error=$e->getMessage(); }
}
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['cpa_toggle'])){
    try{
        cpa_set_status($pdo, $_SESSION['user']['id']??0, $_POST['cpa_id']??0, $_POST['cpa_toggle']==='activate'?'Aktif':'Pasif');
        $ok='Tercih durumu güncellendi.';
    }catch(Throwable $e){ $error=$e->getMessage(); }
}
// P0 SON KAPANIŞ (2026-07-18) — bu karttan hızlı iptal; miktar azaltma/aktarım cpa_allocation.php'de.
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['alloc_cancel'])){
    try{
        cpa_alloc_cancel($pdo, $_SESSION['user']['id']??0, $_POST['alloc_id']??0);
        $ok='Tahsis iptal edildi.';
    }catch(Throwable $e){ $error=$e->getMessage(); }
}

$stmt=$pdo->prepare("SELECT * FROM contacts WHERE id=?");
$stmt->execute([$id]);
$c=$stmt->fetch();

require_once __DIR__.'/layout_top.php';

if(!$c){
    echo "<h1>Cari bulunamadı</h1>";
    require __DIR__.'/layout_bottom.php';
    exit;
}

// View/Edit ayrımı: sayfa varsayılan olarak salt-okunur açılır, düzenleme formu SADECE
// mevcut kaydı değiştirme yetkisi olana (task_view.php/checks_notes.php ile aynı desen) gösterilir.
$canEdit = can_edit_delete();

try{
    $personnel=$pdo->query("SELECT * FROM personnel ORDER BY active DESC, name")->fetchAll();
}catch(Throwable $e){
    $personnel=[];
}

// WhatsApp konuşma geçmişi — bu cariye ait bir conversation varsa direkt oraya, yoksa yeni mesaj
// gönderme ekranına (telefon önceden dolu) yönlendirilir.
$waConvId=null;
try{
    $waq=$pdo->prepare("SELECT id FROM wa_conversations WHERE contact_id=? ORDER BY last_message_at DESC LIMIT 1");
    $waq->execute([$id]);
    $waConvId=$waq->fetchColumn() ?: null;
}catch(Throwable $e){}

$assigned=[];
try{
    $as=$pdo->prepare("SELECT personnel_id FROM contact_representatives WHERE contact_id=?");
    $as->execute([$id]);
    $assigned=array_map('intval', array_column($as->fetchAll(),'personnel_id'));
}catch(Throwable $e){}

$repNames='Anonim / Ortak Havuz';
if(($c['representative_mode'] ?? '')!=='anonim'){
    $names=[];
    foreach($personnel as $p){
        if(in_array((int)$p['id'],$assigned,true)) $names[]=$p['name'];
    }
    $repNames=$names ? implode(', ',$names) : 'Atanmadı';
}

// FİNANS ÇEKİRDEK DÜZELTMESİ (2026-07-10): bakiye artık contacts_lib.php::contact_balance()
// üzerinden hesaplanıyor — satış/alış (Bekliyor) borç yaratır, Tahsilat/Ödeme bunu kapatır
// (ters işaretle sayılır), aksi halde "satış + kendi tahsilatı" çift sayılırdı.
require_once __DIR__.'/contacts_lib.php';
$balance=contact_balance($pdo, $id);

// PDP-001 (2026-07-15): "Tahsilat" kartı $in değişkeni hiç tanımlanmadan kullanılıyordu (her
// zaman boş/₺0 basıyordu). Bu cariden yapılan gerçek tahsilat toplamı bağlandı.
try{
    $inSt=$pdo->prepare("SELECT COALESCE(SUM(amount),0) s FROM finance_movements WHERE contact_id=? AND direction='in'");
    $inSt->execute([$id]);
    $in=(float)$inSt->fetchColumn();
}catch(Throwable $e){ $in=0; }
?>

<style>
.rep-box{border:1px solid var(--df-hairline);border-radius:var(--df-radius-lg);padding:14px;background:var(--df-surface-sunken)}
.rep-mode{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:14px}
.rep-mode label{border:1px solid var(--df-hairline);border-radius:var(--df-radius-md);padding:12px;background:var(--df-surface);font-weight:900}
.rep-list{display:grid;grid-template-columns:repeat(3,minmax(160px,1fr));gap:10px}
.rep-item{display:flex;align-items:center;gap:9px;border:1px solid var(--df-hairline);border-radius:var(--df-radius-md);background:var(--df-surface);padding:10px;font-weight:800}
.rep-item small{display:block;color:var(--df-ink-500);font-weight:700}
.rep-item input{width:auto}
@media(max-width:960px){.rep-mode,.rep-list{grid-template-columns:1fr}}
</style>

<?php
// DS-002A: koşullu aksiyon bloğu 100% aynı kod, sadece ob_start()/ob_get_clean() ile
// yakalanıp ds_page_header()'a veriliyor — mantık/koşullar hiç değişmedi.
// UX-001 (2026-07-16): 7 buton neredeyse eşit ağırlıktaydı — birincil/ikincil/gezinme/durum-
// değişimi/riskli olarak gruplandı. Koşullar/hedefler birebir aynı, sadece görsel hiyerarşi.
ob_start();
?>
<?=ds_button('+ Tahsilat', 'finance_new.php?direction=in&contact_id='.$id, 'primary', '', '', true)?>
<?=ds_button('+ Ödeme', 'finance_new.php?direction=out&contact_id='.$id, 'secondary', '', '', true)?>
<?php // CARİDEN BAŞLATILAN İŞLEMLERDE BAĞLAMI KORU (2026-07-19, Product Owner kararı) — Tahsilat/
// Ödeme ile aynı standart: cari bağlamı ?contact_id= ile hedef forma taşınır, sales.php/purchase.php
// bunu okuyup carii otomatik seçer (aynı mekanizma, yeni bir akış İCAT edilmedi). ?>
<?=ds_button('+ Satış', 'sales.php?contact_id='.$id, 'secondary', '', '', true)?>
<?=ds_button('+ Alış', 'purchase.php?contact_id='.$id, 'secondary', '', '', true)?>
<?php // CARİ TEK MERKEZ (2026-07-19, P0-1) — temel aksiyonlara Teklif/İş Emri eklendi, cari bağlamı ?>
<?=ds_button('+ Teklif', 'teklif.php?customer_id='.$id, 'secondary', '', '', true)?>
<?=ds_button('+ İş Emri', 'job_new.php?customer_id='.$id, 'secondary', '', '', true)?>
<?=ds_button('📊 Cari Raporu (analiz)', 'report.php?modul=cari_detay&ref='.$id, 'secondary', '', '', true)?>
<?php if($waConvId): ?><?=ds_button('💬 WhatsApp', 'wa_conversation_view.php?id='.(int)$waConvId, 'secondary', '', '', true)?>
<?php elseif(!empty($c['phone'])): ?><?=ds_button('💬 WhatsApp', 'wa_conversation_view.php?phone='.urlencode($c['phone']), 'secondary', '', '', true)?>
<?php endif; ?>
<?=ds_button('Cari Listesi', 'contacts.php', 'ghost', '', '', true)?>
<?php if(is_admin() || user_can('contacts')): ?>
<?php $isActive=(int)($c['active'] ?? 1); ?>
<form method="post" style="display:inline">
    <input type="hidden" name="toggle_active" value="<?=$isActive?0:1?>">
    <button class="df-btn df-btn--warn" onclick="return confirm('<?=$isActive?'Bu cariyi pasif yapmak istediğinize emin misiniz?':'Bu cariyi aktif yapmak istediğinize emin misiniz?'?>')">
        <?=$isActive?'⏸ Pasif Yap':'✅ Aktif Yap'?>
    </button>
</form>
<?php endif; ?>
<?=delete_button('contact',$id)?>
<?php
$__actions = ob_get_clean();
ds_page_header($c['name'], ds_icon('users',24), '', $__actions, false, true);
?>

<?php if($error): ?><?=ds_alert('danger',$error)?><?php endif; ?>
<?php if($ok): ?><?=ds_alert('success',$ok)?><?php endif; ?>
<?php if(isset($_GET['deleted'])): ?><?=ds_alert('success','Finans hareketi silindi, hesap bakiyesi güncellendi.')?><?php endif; ?>

<div class="df-personnel-statrow">
<div class="df-personnel-stat"><span>Tip</span><strong><?=h($c['type'])?></strong></div>
<div class="df-personnel-stat"><span>Temsilci</span><strong><?=h($repNames)?></strong></div>
<div class="df-personnel-stat"><span>Tahsilat</span><strong><?=money($in)?></strong></div>
<div class="df-personnel-stat"><span>Bakiye</span><strong><?=money($balance)?></strong></div>
</div>

<?php if(!empty($c['phone']) || !empty($c['email']) || !empty($c['website'])): ?>
<div style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:var(--df-space-4)">
<?php if(!empty($c['phone'])): ?>
<a href="tel:<?=h(preg_replace('/\s+/','',$c['phone']))?>" class="df-btn df-btn--secondary df-btn--sm">📞 <?=h($c['phone'])?></a>
<a href="https://wa.me/<?=h(preg_replace('/\D/','',$c['phone']))?>" class="df-btn df-btn--primary df-btn--sm" style="background:var(--df-success)">💬 WhatsApp</a>
<?php endif; ?>
<?php if(!empty($c['email'])): ?>
<a href="mailto:<?=h($c['email'])?>" class="df-btn df-btn--secondary df-btn--sm">✉️ <?=h($c['email'])?></a>
<?php endif; ?>
<?php if(!empty($c['website'])): ?>
<a href="<?=h($c['website'])?>" target="_blank" rel="noopener" class="df-btn df-btn--secondary df-btn--sm">🌐 Web Sitesi</a>
<?php endif; ?>
</div>
<?php endif; ?>

<?php
// CARİ TEK MERKEZ (2026-07-19, Product Owner kararı, P0-1): SADECE bu cariye ait (contact_id/
// customer_id=? ile garanti), tarihe göre birleştirilmiş tek hareket akışı — Satış/Alış/Tahsilat/
// Ödeme/Çek-Senet/Transfer/Muhasebe/İş Emri/Teklif. Yeni bir tablo/mimari İCAT EDİLMEDİ —
// contacts_lib.php::contact_ledger_rows() var olan 3 kaynağı (finance_movements/jobs/quotes) birleştirir
// (bkz. o fonksiyondaki mimari not). Eskiden ayrı duran "Finans Hareketleri" tablosu bu bölümün
// İÇİNE alındı (aşağıda ayrıca tekrar YOK) — Cari Raporu ise kasten AYRI/analiz amaçlı kaldı.
?>
<section class="df-card">
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:var(--df-space-3)">
<h2 style="font-size:var(--df-type-section-size);margin:0">Cari Hareketleri</h2>
<span class="df-muted" style="font-size:12px">Sadece bu cariye ait, kronolojik</span>
</div>
<?php
$__ledger = contact_ledger_rows($pdo, $id, 50);
// Eski "Finans Hareketleri" bölümü user_can('finance') ile korunuyordu — bu ledger onun yerini
// aldığı için AYNI sınırı korur: finans yetkisi olmayan personel finans satırlarını (tahsilat/
// ödeme/satış tutarı vb.) görmesin, iş emri/teklif satırları (finans DEĞİL) etkilenmez.
if(!user_can('finance')){
    $__ledger = array_filter($__ledger, function($__it){ return $__it['kind']!=='finance'; });
}
if(!$__ledger): ?>
<?=ds_empty_state('Bu cariye ait henüz hiçbir hareket yok.')?>
<?php else: ?>
<div class="df-table-wrap"><table class="df-table">
<thead><tr><th>Tarih</th><th>Tür</th><th>Açıklama</th><th style="text-align:right">Tutar</th><th>Durum</th><th>İşlem</th></tr></thead>
<tbody>
<?php foreach($__ledger as $__item): $__v = contact_ledger_row_view($__item, $pdo); if(!$__v) continue; ?>
<tr>
<td class="nowrap"><?=h($__v['date'])?></td>
<td><?=h($__v['type'])?></td>
<td style="font-size:12px;color:var(--df-ink-500)"><?=h($__v['desc'])?></td>
<td style="text-align:right;font-weight:800;<?= $__v['amount']===null ? '' : ($__v['sign']==='+' ? 'color:var(--df-success-ink)' : ($__v['sign']==='-' ? 'color:var(--df-danger-ink)' : '')) ?>">
  <?= $__v['amount']===null ? '—' : $__v['sign'].money($__v['amount']) ?>
</td>
<td><?=ds_badge($__v['status'])?></td>
<td class="nowrap"><div class="row-actions">
<?php if($__v['open_url']): ?><a class="df-btn df-btn--secondary df-btn--sm" href="<?=h($__v['open_url'])?>">Aç</a><?php endif; ?>
<?php if($__v['edit_url'] && can_edit_delete()): ?><a class="df-btn df-btn--secondary df-btn--sm" href="<?=h($__v['edit_url'])?>">✏️</a><?php endif; ?>
<?php if(!empty($__v['deletable']) && can_edit_delete()): ?>
<form method="post" action="sil.php" style="display:inline" onsubmit="return confirm('Bu hareket KALICI olarak silinecek ve ilgili hesap/cari bakiyesi geri alınacak. Emin misiniz?')">
<input type="hidden" name="t" value="finance">
<input type="hidden" name="id" value="<?=(int)$__v['id']?>">
<input type="hidden" name="return_context" value="contact">
<input type="hidden" name="return_ref" value="<?=$id?>">
<button class="df-btn df-btn--danger df-btn--sm" type="submit">🗑</button>
</form>
<?php endif; ?>
<?php if($__v['source_url'] && !$__v['open_url']): ?><a class="df-btn df-btn--secondary df-btn--sm" href="<?=h($__v['source_url'])?>"><?=h($__v['source_label'] ?: 'Kaynağa Git')?></a><?php endif; ?>
</div></td>
</tr>
<?php endforeach; ?>
</tbody>
</table></div>
<?php endif; ?>
</section>

<section class="df-card" style="margin-top:var(--df-space-4)">
<h2 style="font-size:var(--df-type-section-size);margin:0 0 var(--df-space-3)">Cari Profil</h2>

<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin:12px 0;font-size:14px">
  <div><span style="color:var(--df-ink-500)">Firma / Cari Adı</span><br><?=h($c['name'])?></div>
  <div><span style="color:var(--df-ink-500)">Cari Tipi</span><br><?=h($c['type'])?></div>
  <div><span style="color:var(--df-ink-500)">Yetkili Kişi</span><br><?=h($c['authorized_person'] ?: '-')?></div>
  <div><span style="color:var(--df-ink-500)">Telefon</span><br><?=h($c['phone'] ?: '-')?></div>
  <div><span style="color:var(--df-ink-500)">2. Telefon</span><br><?=h($c['phone2'] ?: '-')?></div>
  <div><span style="color:var(--df-ink-500)">E-posta</span><br><?=h($c['email'] ?: '-')?></div>
  <div><span style="color:var(--df-ink-500)">Web Sitesi</span><br><?=h($c['website'] ?: '-')?></div>
  <div><span style="color:var(--df-ink-500)">Vergi Dairesi</span><br><?=h($c['tax_office'] ?: '-')?></div>
  <div><span style="color:var(--df-ink-500)">Vergi / TC No</span><br><?=h($c['tax_number'] ?: '-')?></div>
  <div><span style="color:var(--df-ink-500)">İl / İlçe</span><br><?=h(trim(($c['city'] ?: '').' / '.($c['district'] ?: ''), ' /') ?: '-')?></div>
  <div><span style="color:var(--df-ink-500)">Posta Kodu</span><br><?=h($c['postal_code'] ?: '-')?></div>
  <div><span style="color:var(--df-ink-500)">IBAN</span><br><?=h($c['iban'] ?: '-')?></div>
  <div><span style="color:var(--df-ink-500)">Açılış Bakiyesi</span><br><?=money($c['opening_balance'])?></div>
</div>
<?php if(!empty($c['address'])): ?><div style="margin-top:6px"><span style="color:var(--df-ink-500)">Adres</span><br><?=nl2br(h($c['address']))?></div><?php endif; ?>
<?php if(!empty($c['notes'])): ?><div style="margin-top:8px"><span style="color:var(--df-ink-500)">Notlar</span><br><?=nl2br(h($c['notes']))?></div><?php endif; ?>

<?php if($canEdit): ?>
<details style="margin-top:16px">
<summary style="cursor:pointer;font-weight:800">✏️ Düzenle</summary>
<form method="post" class="df-form-grid-2" style="margin-top:12px">

<input type="hidden" name="save_profile" value="1">

<?php ds_form_field('Firma / Cari Adı', '<input name="name" required value="'.h($c['name']).'">'); ?>
<?php
$__typeOpts='';
foreach(['Müşteri','Tedarikçi','Her İkisi'] as $t){ $__typeOpts.='<option '.($c['type']===$t?'selected':'').'>'.$t.'</option>'; }
ds_form_field('Cari Tipi', '<select name="type">'.$__typeOpts.'</select>');
?>
<?php ds_form_field('Yetkili Kişi', '<input name="authorized_person" value="'.h($c['authorized_person'] ?? '').'">'); ?>
<?php ds_form_field('Telefon', '<input name="phone" type="tel" value="'.h($c['phone'] ?? '').'">'); ?>
<?php ds_form_field('2. Telefon', '<input name="phone2" type="tel" value="'.h($c['phone2'] ?? '').'">'); ?>
<?php ds_form_field('E-posta', '<input name="email" type="email" value="'.h($c['email'] ?? '').'">'); ?>
<?php ds_form_field('Web Sitesi', '<input name="website" type="url" value="'.h($c['website'] ?? '').'" placeholder="https://">'); ?>
<?php ds_form_field('Vergi Dairesi', '<input name="tax_office" value="'.h($c['tax_office'] ?? '').'">'); ?>
<?php ds_form_field('Vergi / TC No', '<input name="tax_number" value="'.h($c['tax_number'] ?? '').'">'); ?>
<?php ds_form_field('İl', '<input name="city" value="'.h($c['city'] ?? '').'">'); ?>
<?php ds_form_field('İlçe', '<input name="district" value="'.h($c['district'] ?? '').'">'); ?>
<?php ds_form_field('Posta Kodu', '<input name="postal_code" maxlength="10" value="'.h($c['postal_code'] ?? '').'">'); ?>
<?php ds_form_field('IBAN', '<input name="iban" maxlength="32" value="'.h($c['iban'] ?? '').'" placeholder="TR00 0000 0000 0000 0000 0000 00">'); ?>
<?php ds_form_field('Açılış Bakiyesi', '<input type="number" step="0.01" name="opening_balance" value="'.h($c['opening_balance']).'">'); ?>

<div class="df-form-span-2 rep-box">
    <h3 style="margin-top:0">Müşteri Temsilcisi</h3>

    <div class="rep-mode">
        <label>
            <input type="radio" name="representative_mode" value="personel" <?=($c['representative_mode'] ?? 'personel')==='personel'?'checked':''?> style="width:auto">
            Personel seç
            <span style="color:var(--df-ink-500);display:block">Bir veya birden fazla personel atanır.</span>
        </label>

        <label>
            <input type="radio" name="representative_mode" value="anonim" <?=($c['representative_mode'] ?? '')==='anonim'?'checked':''?> style="width:auto">
            Anonim / Ortak Havuz
            <span style="color:var(--df-ink-500);display:block">Belirli temsilci atanmaz.</span>
        </label>
    </div>

    <div class="rep-list">
        <?php foreach($personnel as $p): ?>
        <label class="rep-item">
            <input type="checkbox" name="representatives[]" value="<?=$p['id']?>" <?=in_array((int)$p['id'],$assigned,true)?'checked':''?>>
            <span>
                <?=h($p['name'])?>
                <small><?=h(($p['role'] ?: 'Personel').(!$p['active']?' / Pasif':''))?></small>
            </span>
        </label>
        <?php endforeach; ?>
        <?php if(!$personnel): ?>
            <?=ds_alert('warning','Personel bulunamadı. Önce personel ekleyin.')?>
        <?php endif; ?>
    </div>
</div>

<div class="df-form-span-2"><?php ds_form_field('Adres', '<textarea name="address" rows="3">'.h($c['address'] ?? '').'</textarea>'); ?></div>
<div class="df-form-span-2"><?php ds_form_field('Notlar', '<textarea name="notes" rows="4">'.h($c['notes'] ?? '').'</textarea>'); ?></div>

<div class="df-form-span-2"><button class="df-btn df-btn--primary">Profili Kaydet</button></div>

</form>
</details>
<?php endif; ?>
</section>

<style>
body.nav-compact .df-form-grid-2{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:0 var(--df-space-4)}
body.nav-compact .df-form-span-2{grid-column:1 / -1}
body.nav-compact .df-personnel-statrow{display:flex;flex-wrap:wrap;gap:var(--df-space-3);margin:var(--df-space-4) 0}
body.nav-compact .df-personnel-stat{flex:1;min-width:120px;background:var(--df-surface);border:1px solid var(--df-hairline);border-radius:var(--df-radius-md);padding:var(--df-space-3);display:flex;flex-direction:column;gap:4px}
body.nav-compact .df-personnel-stat span{font-size:var(--df-type-caption-size);color:var(--df-ink-500)}
body.nav-compact .df-personnel-stat strong{font-size:var(--df-type-subtitle-size);color:var(--df-ink-900)}
@media(max-width:640px){body.nav-compact .df-form-grid-2{grid-template-columns:1fr}}
</style>

<section class="df-card" style="margin-top:var(--df-space-4)">
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:var(--df-space-3)"><h2 style="font-size:var(--df-type-section-size);margin:0">Bu Cariye Ait İş Emirleri</h2><?=ds_button('Ekstre / PDF','report.php?modul=cari_detay&ref='.$id,'secondary','df-btn--sm','',true)?></div>
<div class="df-table-wrap"><table class="df-table">
<thead><tr><th>İş No</th><th>Başlık</th><th>Tarih</th><th>Durum</th><th style="text-align:right">Tutar</th></tr></thead>
<tbody>
<?php
try{
    $jrows=$pdo->prepare("SELECT id,job_no,title,status,due_date,sale_amount,created_at FROM jobs WHERE customer_id=? ORDER BY id DESC LIMIT 20");
    $jrows->execute([$id]);
    $jrows=$jrows->fetchAll();
    foreach($jrows as $j){
        $jt=$j['due_date'] ?: ($j['created_at'] ? substr($j['created_at'],0,10) : '—');
        echo "<tr>";
        echo "<td><a href='job_view.php?id=".h($j['id'])."'>".h($j['job_no'])."</a></td>";
        echo "<td>".h($j['title'])."</td>";
        echo "<td class='nowrap'>".h($jt)."</td>";
        echo "<td>".ds_badge($j['status'])."</td>";
        echo "<td style='text-align:right;font-weight:800'>".((float)$j['sale_amount']>0?money($j['sale_amount']):'—')."</td>";
        echo "</tr>";
    }
    if(!$jrows) echo "<tr><td colspan='5' style='color:var(--df-ink-500)'>Bu cariye ait iş yok.</td></tr>";
}catch(Throwable $e){
    echo "<tr><td colspan='5'>".ds_alert('danger',$e->getMessage())."</td></tr>";
}
?>
</tbody>
</table></div>
</section>

<section class="df-card" style="margin-top:var(--df-space-4)">
<div style="display:flex;justify-content:space-between;align-items:center;margin:0 0 var(--df-space-3);gap:var(--df-space-3)">
<h2 style="font-size:var(--df-type-section-size);margin:0">Alış / Satış Belgeleri</h2>
<?=ds_button('Tümünü Gör','contact_documents.php?id='.$id,'secondary','','',true)?>
</div>
<div class="df-table-wrap"><table class="df-table">
<thead><tr><th>Belge No</th><th>Tür</th><th>Tarih</th><th style="text-align:right">Genel Toplam</th><th>Durum</th><th>Aç</th></tr></thead>
<tbody>
<?php
try{
    $trows=$pdo->prepare("SELECT * FROM trade_documents WHERE contact_id=? ORDER BY id DESC LIMIT 20");
    $trows->execute([$id]);
    $trows=$trows->fetchAll();
    foreach($trows as $t){
        echo "<tr>";
        echo "<td><b>".h($t['document_no'])."</b></td>";
        echo "<td>".h($t['document_type']==='purchase'?'Alış':'Satış')."</td>";
        echo "<td class='nowrap'>".h($t['document_date'])."</td>";
        echo "<td style='text-align:right;font-weight:800'>".money($t['grand_total'])."</td>";
        echo "<td>".ds_badge($t['status'])."</td>";
        echo "<td><a class='df-btn df-btn--secondary df-btn--sm' href='trade_document_view.php?id=".(int)$t['id']."'>Aç</a></td>";
        echo "</tr>";
    }
    if(!$trows) echo "<tr><td colspan='6' style='color:var(--df-ink-500)'>Bu cariye ait belge yok.</td></tr>";
}catch(Throwable $e){
    echo "<tr><td colspan='6'>".ds_alert('danger',$e->getMessage())."</td></tr>";
}
?>
</tbody>
</table></div>
</section>

<?php if(cpa_can_view()): ?>
<section class="df-card" style="margin-top:var(--df-space-4)">
<h2 style="font-size:var(--df-type-section-size);margin:0 0 var(--df-space-2)">🎯 Tercih Edilen Tedarikçiler</h2>
<p class="df-section-hint" style="margin:0 0 var(--df-space-3)">Bu müşteri için ürün bazlı tercih edilen tedarikçiler — satın alma sırasında akıllı öneri olarak kullanılır, zorunlu değildir.</p>

<div class="df-table-wrap"><table class="df-table">
<thead><tr><th>Ürün</th><th>Tercih Edilen Tedarikçi</th><th>Öncelik</th><th>Varsayılan</th><th>Durum</th><th>Not</th><?php if(cpa_can_edit()): ?><th></th><?php endif; ?></tr></thead>
<tbody>
<?php
$__cpaRows = cpa_list_for_customer($pdo, $id, true);
foreach($__cpaRows as $cr):
?>
<tr>
<td><?=h($cr['product_name'] ?: '#'.$cr['stock_item_id'])?></td>
<td><?=h($cr['supplier_name'] ?: '#'.$cr['supplier_id'])?></td>
<td><?=(int)$cr['priority']?></td>
<td><?=$cr['is_default']?ds_badge('Varsayılan','green'):''?></td>
<td><?=ds_badge($cr['status'])?></td>
<td style="color:var(--df-ink-500);font-size:12px"><?=h($cr['notes'] ?: '')?></td>
<?php if(cpa_can_edit()): ?>
<td>
<form method="post" style="margin:0" onsubmit="return confirm('<?=$cr['status']==='Aktif'?'Bu tercihi pasife almak':'Bu tercihi yeniden aktif etmek'?> istediğinize emin misiniz?')">
<input type="hidden" name="cpa_id" value="<?=(int)$cr['id']?>">
<button class="df-btn df-btn--secondary df-btn--sm" type="submit" name="cpa_toggle" value="<?=$cr['status']==='Aktif'?'deactivate':'activate'?>"><?=$cr['status']==='Aktif'?'Pasife Al':'Aktif Et'?></button>
</form>
</td>
<?php endif; ?>
</tr>
<?php endforeach; ?>
<?php if(!$__cpaRows): ?><tr><td colspan="7" style="color:var(--df-ink-500)">Henüz tercih tanımlanmamış.</td></tr><?php endif; ?>
</tbody>
</table></div>

<?php if(cpa_can_edit()):
$__cpaProducts = $pdo->query("SELECT id,name FROM stock_items WHERE COALESCE(active,1)=1 ORDER BY name")->fetchAll();
$__cpaSuppliers = $pdo->query("SELECT id,name FROM contacts WHERE type IN ('Tedarikçi','Her İkisi') ORDER BY name")->fetchAll();
if(!$__cpaSuppliers) $__cpaSuppliers = $pdo->query("SELECT id,name FROM contacts ORDER BY name")->fetchAll();
?>
<details style="margin-top:var(--df-space-4)">
<summary style="cursor:pointer;font-weight:700">➕ Yeni Tercih Ekle</summary>
<form method="post" class="df-form-grid-2" style="margin-top:var(--df-space-3)">
<input type="hidden" name="cpa_save" value="1">
<?php
$__pOpts='<option value="">— Ürün seç —</option>';
foreach($__cpaProducts as $p){ $__pOpts.='<option value="'.$p['id'].'">'.h($p['name']).'</option>'; }
ds_form_field('Ürün / Stok Kartı', '<select name="stock_item_id" required>'.$__pOpts.'</select>');

$__sOpts='<option value="">— Tedarikçi seç —</option>';
foreach($__cpaSuppliers as $s){ $__sOpts.='<option value="'.$s['id'].'">'.h($s['name']).'</option>'; }
ds_form_field('Tercih Edilen Tedarikçi', '<select name="supplier_id" required>'.$__sOpts.'</select>');

ds_form_field('Öncelik', '<input type="number" name="priority" value="1" min="1" style="max-width:100px">');
?>
<div class="df-form-span-2">
<label style="display:flex;align-items:center;gap:8px;font-size:var(--df-type-body-size);color:var(--df-ink-900)">
<input type="checkbox" name="is_default" value="1" style="width:auto"> Bu ürün için varsayılan tedarikçi
</label>
</div>
<div class="df-form-span-2"><?php ds_form_field('Açıklama', '<textarea name="notes" rows="2"></textarea>'); ?></div>
<div class="df-form-span-2"><button class="df-btn df-btn--primary">Tercihi Kaydet</button></div>
</form>
</details>
<?php endif; ?>
</section>
<?php endif; ?>

<?php if(cpa_alloc_can_view()):
$__allocRows = cpa_alloc_list_for_customer($pdo, $id, true);
?>
<section class="df-card" style="margin-top:var(--df-space-4)">
<h2 style="font-size:var(--df-type-section-size);margin:0 0 var(--df-space-2)">📦 Müşteriye Ayrılan Stok</h2>
<p class="df-section-hint" style="margin:0 0 var(--df-space-3)">Bu müşteri için satın almadan ayrılan miktarlar — fiziksel stoktan ayrı izlenir, satış yapıldığında otomatik düşer.</p>
<?php if(!$__allocRows): ?>
<?php ds_empty_state('Bu müşteri için henüz müşteriye ayrılmamış.'); ?>
<?php else: ?>
<div class="df-table-wrap"><table class="df-table">
<thead><tr><th>Ürün</th><th style="text-align:right">Ayrılan</th><th style="text-align:right">Tüketilen</th><th style="text-align:right">Kalan</th><th>Durum</th><?php if(cpa_alloc_can_edit()): ?><th></th><?php endif; ?></tr></thead>
<tbody>
<?php foreach($__allocRows as $ar): $__rem=(float)$ar['allocated_qty']-(float)$ar['consumed_qty']; ?>
<tr>
<td><?=h($ar['product_name'] ?: '#'.$ar['stock_item_id'])?><?php if($ar['purchase_date']): ?><div class="df-muted" style="font-size:11px">Alış: <?=h($ar['purchase_date'])?></div><?php endif; ?></td>
<td style="text-align:right"><?=stock_qty_fmt($ar['allocated_qty'])?></td>
<td style="text-align:right"><?=stock_qty_fmt($ar['consumed_qty'])?></td>
<td style="text-align:right;font-weight:800"><?=stock_qty_fmt($__rem)?></td>
<td><?=ds_badge($ar['status'])?></td>
<?php if(cpa_alloc_can_edit()): ?>
<td class="nowrap">
<?php if($ar['status']!=='İptal' && $__rem>0.0000001): ?>
<a class="df-btn df-btn--primary df-btn--sm" href="sales.php?contact_id=<?=$id?>&stock_item_id=<?=(int)$ar['stock_item_id']?>&qty=<?=h($__rem)?>">🧾 Sat</a>
<?php endif; ?>
<a class="df-btn df-btn--secondary df-btn--sm" href="cpa_allocation.php?purchase_id=<?=(int)$ar['purchase_movement_id']?>">Yönet</a>
<?php if($ar['status']!=='İptal'): ?>
<form method="post" style="display:inline-block;margin:0" onsubmit="return confirm('Bu tahsis iptal edilecek, kalan miktar serbest stoğa dönecek. Emin misiniz?')">
<input type="hidden" name="alloc_id" value="<?=(int)$ar['id']?>">
<button class="df-btn df-btn--danger df-btn--sm" type="submit" name="alloc_cancel" value="1">İptal</button>
</form>
<?php endif; ?>
</td>
<?php endif; ?>
</tr>
<?php endforeach; ?>
</tbody>
</table></div>
<?php endif; ?>
</section>
<?php endif; ?>

<?php require_once __DIR__.'/layout_bottom.php'; ?>
