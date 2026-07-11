<?php
require_once __DIR__.'/boot.php';
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
?>

<style>
.rep-box{border:1px solid #e5e7eb;border-radius:18px;padding:14px;background:#f8fafc}
.rep-mode{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:14px}
.rep-mode label{border:1px solid #e5e7eb;border-radius:14px;padding:12px;background:#fff;font-weight:900}
.rep-list{display:grid;grid-template-columns:repeat(3,minmax(160px,1fr));gap:10px}
.rep-item{display:flex;align-items:center;gap:9px;border:1px solid #e5e7eb;border-radius:14px;background:#fff;padding:10px;font-weight:800}
.rep-item small{display:block;color:#667085;font-weight:700}
.rep-item input{width:auto}
@media(max-width:960px){.rep-mode,.rep-list{grid-template-columns:1fr}}
</style>

<div class="panel-head">
<h1><?=h($c['name'])?></h1>
<div class="actions">
<a class="btn" href="finance_new.php?direction=in&contact_id=<?=$id?>">+ Tahsilat</a>
<a class="btn secondary" href="finance_new.php?direction=out&contact_id=<?=$id?>">+ Ödeme</a>
<a class="btn" href="report.php?modul=cari_detay&ref=<?=$id?>">📊 Cari Raporu</a>
<?php if($waConvId): ?><a class="btn secondary" href="wa_conversation_view.php?id=<?=(int)$waConvId?>">💬 WhatsApp</a>
<?php elseif(!empty($c['phone'])): ?><a class="btn secondary" href="wa_conversation_view.php?phone=<?=urlencode($c['phone'])?>">💬 WhatsApp</a>
<?php endif; ?>
<a class="btn secondary" href="contacts.php">Cari Listesi</a>
<?php if(is_admin() || user_can('contacts')): ?>
<?php $isActive=(int)($c['active'] ?? 1); ?>
<form method="post" style="display:inline">
    <input type="hidden" name="toggle_active" value="<?=$isActive?0:1?>">
    <button class="btn <?=$isActive?'secondary':'danger'?>" style="<?=$isActive?'':'background:#dc2626;color:#fff'?>" onclick="return confirm('<?=$isActive?'Bu cariyi pasif yapmak istediğinize emin misiniz?':'Bu cariyi aktif yapmak istediğinize emin misiniz?'?>')">
        <?=$isActive?'⏸ Pasif Yap':'✅ Aktif Yap'?>
    </button>
</form>
<?php endif; ?>
<?=delete_button('contact',$id)?>
</div>
</div>

<?php if($error): ?><div class="alert"><?=h($error)?></div><?php endif; ?>
<?php if($ok): ?><div class="ok"><?=h($ok)?></div><?php endif; ?>

<div class="cards">
<div class="card"><small>Tip</small><strong><?=h($c['type'])?></strong></div>
<div class="card"><small>Temsilci</small><strong><?=h($repNames)?></strong></div>
<div class="card"><small>Tahsilat</small><strong><?=money($in)?></strong></div>
<div class="card"><small>Bakiye</small><strong><?=money($balance)?></strong></div>
</div>

<?php if(!empty($c['phone']) || !empty($c['email']) || !empty($c['website'])): ?>
<div style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:16px">
<?php if(!empty($c['phone'])): ?>
<a href="tel:<?=h(preg_replace('/\s+/','',$c['phone']))?>" class="btn secondary" style="font-size:13px">📞 <?=h($c['phone'])?></a>
<a href="https://wa.me/<?=h(preg_replace('/\D/','',$c['phone']))?>" class="btn" style="background:#16a34a;color:#fff;font-size:13px">💬 WhatsApp</a>
<?php endif; ?>
<?php if(!empty($c['email'])): ?>
<a href="mailto:<?=h($c['email'])?>" class="btn secondary" style="font-size:13px">✉️ <?=h($c['email'])?></a>
<?php endif; ?>
<?php if(!empty($c['website'])): ?>
<a href="<?=h($c['website'])?>" target="_blank" rel="noopener" class="btn secondary" style="font-size:13px">🌐 Web Sitesi</a>
<?php endif; ?>
</div>
<?php endif; ?>

<section class="panel">
<div class="panel-head"><h2>Cari Profil</h2></div>

<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin:12px 0;font-size:14px">
  <div><span class="muted">Firma / Cari Adı</span><br><?=h($c['name'])?></div>
  <div><span class="muted">Cari Tipi</span><br><?=h($c['type'])?></div>
  <div><span class="muted">Yetkili Kişi</span><br><?=h($c['authorized_person'] ?: '-')?></div>
  <div><span class="muted">Telefon</span><br><?=h($c['phone'] ?: '-')?></div>
  <div><span class="muted">2. Telefon</span><br><?=h($c['phone2'] ?: '-')?></div>
  <div><span class="muted">E-posta</span><br><?=h($c['email'] ?: '-')?></div>
  <div><span class="muted">Web Sitesi</span><br><?=h($c['website'] ?: '-')?></div>
  <div><span class="muted">Vergi Dairesi</span><br><?=h($c['tax_office'] ?: '-')?></div>
  <div><span class="muted">Vergi / TC No</span><br><?=h($c['tax_number'] ?: '-')?></div>
  <div><span class="muted">İl / İlçe</span><br><?=h(trim(($c['city'] ?: '').' / '.($c['district'] ?: ''), ' /') ?: '-')?></div>
  <div><span class="muted">Posta Kodu</span><br><?=h($c['postal_code'] ?: '-')?></div>
  <div><span class="muted">IBAN</span><br><?=h($c['iban'] ?: '-')?></div>
  <div><span class="muted">Açılış Bakiyesi</span><br><?=money($c['opening_balance'])?></div>
</div>
<?php if(!empty($c['address'])): ?><div style="margin-top:6px"><span class="muted">Adres</span><br><?=nl2br(h($c['address']))?></div><?php endif; ?>
<?php if(!empty($c['notes'])): ?><div style="margin-top:8px"><span class="muted">Notlar</span><br><?=nl2br(h($c['notes']))?></div><?php endif; ?>

<?php if($canEdit): ?>
<details style="margin-top:16px">
<summary style="cursor:pointer;font-weight:800">✏️ Düzenle</summary>
<form method="post" class="form-grid" style="margin-top:12px">

<input type="hidden" name="save_profile" value="1">

<label>Firma / Cari Adı
<input name="name" required value="<?=h($c['name'])?>">
</label>

<label>Cari Tipi
<select name="type">
<?php foreach(['Müşteri','Tedarikçi','Her İkisi'] as $t): ?>
<option <?=$c['type']===$t?'selected':''?>><?=$t?></option>
<?php endforeach; ?>
</select>
</label>

<label>Yetkili Kişi
<input name="authorized_person" value="<?=h($c['authorized_person'] ?? '')?>">
</label>

<label>Telefon
<input name="phone" type="tel" value="<?=h($c['phone'] ?? '')?>">
</label>

<label>2. Telefon
<input name="phone2" type="tel" value="<?=h($c['phone2'] ?? '')?>">
</label>

<label>E-posta
<input name="email" type="email" value="<?=h($c['email'] ?? '')?>">
</label>

<label>Web Sitesi
<input name="website" type="url" value="<?=h($c['website'] ?? '')?>" placeholder="https://">
</label>

<label>Vergi Dairesi
<input name="tax_office" value="<?=h($c['tax_office'] ?? '')?>">
</label>

<label>Vergi / TC No
<input name="tax_number" value="<?=h($c['tax_number'] ?? '')?>">
</label>

<label>İl
<input name="city" value="<?=h($c['city'] ?? '')?>">
</label>

<label>İlçe
<input name="district" value="<?=h($c['district'] ?? '')?>">
</label>

<label>Posta Kodu
<input name="postal_code" maxlength="10" value="<?=h($c['postal_code'] ?? '')?>">
</label>

<label>IBAN
<input name="iban" maxlength="32" value="<?=h($c['iban'] ?? '')?>" placeholder="TR00 0000 0000 0000 0000 0000 00">
</label>

<label>Açılış Bakiyesi
<input type="number" step="0.01" name="opening_balance" value="<?=h($c['opening_balance'])?>">
</label>

<div class="full rep-box">
    <h3 style="margin-top:0">Müşteri Temsilcisi</h3>

    <div class="rep-mode">
        <label>
            <input type="radio" name="representative_mode" value="personel" <?=($c['representative_mode'] ?? 'personel')==='personel'?'checked':''?> style="width:auto">
            Personel seç
            <span class="muted" style="display:block">Bir veya birden fazla personel atanır.</span>
        </label>

        <label>
            <input type="radio" name="representative_mode" value="anonim" <?=($c['representative_mode'] ?? '')==='anonim'?'checked':''?> style="width:auto">
            Anonim / Ortak Havuz
            <span class="muted" style="display:block">Belirli temsilci atanmaz.</span>
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
            <div class="alert">Personel bulunamadı. Önce personel ekleyin.</div>
        <?php endif; ?>
    </div>
</div>

<label class="full">Adres
<textarea name="address" rows="3"><?=h($c['address'] ?? '')?></textarea>
</label>

<label class="full">Notlar
<textarea name="notes" rows="4"><?=h($c['notes'] ?? '')?></textarea>
</label>

<button class="btn">Profili Kaydet</button>

</form>
</details>
<?php endif; ?>
</section>

<section class="panel">
<div class="panel-head"><h2>Bu Cariye Ait İşler / İş Emirleri</h2><a class="btn small secondary" href="report.php?modul=cari_detay&ref=<?=$id?>">📊 Ekstre / PDF</a></div>
<table>
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
        echo "<td>".badge($j['status'],status_tone($j['status']))."</td>";
        echo "<td style='text-align:right;font-weight:800'>".((float)$j['sale_amount']>0?money($j['sale_amount']):'—')."</td>";
        echo "</tr>";
    }
    if(!$jrows) echo "<tr><td colspan='5' class='muted'>Bu cariye ait iş yok.</td></tr>";
}catch(Throwable $e){
    echo "<tr><td colspan='5'><div class='alert'>".h($e->getMessage())."</div></td></tr>";
}
?>
</tbody>
</table>
</section>

<section class="panel">
<div class="panel-head"><h2>Alış / Satış Belgeleri</h2></div>
<table>
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
        echo "<td>".badge($t['status'],status_tone($t['status']))."</td>";
        echo "<td><a class='btn secondary small' href='trade_document_view.php?id=".(int)$t['id']."'>Aç</a></td>";
        echo "</tr>";
    }
    if(!$trows) echo "<tr><td colspan='6' class='muted'>Bu cariye ait belge yok.</td></tr>";
}catch(Throwable $e){
    echo "<tr><td colspan='6'><div class='alert'>".h($e->getMessage())."</div></td></tr>";
}
?>
</tbody>
</table>
</section>

<?php if(user_can('finance')): ?>
<section class="panel">
<div class="panel-head"><h2>Finans Hareketleri</h2><a class="btn small secondary" href="finance.php?contact_id=<?=$id?>">Tümü</a></div>
<table>
<thead><tr><th>Tarih</th><th>Tip</th><th>Hesap</th><th>Tutar</th><th>Durum</th><th>Açıklama</th></tr></thead>
<tbody>
<?php
try{
    $st=$pdo->prepare("SELECT f.*, a.name account_name FROM finance_movements f LEFT JOIN finance_accounts a ON a.id=f.account_id WHERE f.contact_id=? ORDER BY f.id DESC LIMIT 20");
    $st->execute([$id]);
    $rows=$st->fetchAll();
    foreach($rows as $r){
        echo "<tr>";
        echo "<td>".h($r['movement_date'])."</td>";
        echo "<td>".h($r['direction']=='in'?'Tahsilat':'Ödeme')."</td>";
        echo "<td>".h($r['account_name'] ?: $r['payment_channel'])."</td>";
        echo "<td>".money($r['amount'])."</td>";
        echo "<td>".badge($r['status'],status_tone($r['status']))."</td>";
        echo "<td>".h($r['description'])."</td>";
        echo "</tr>";
    }
    if(!$rows) echo "<tr><td colspan='6' class='muted'>Hareket yok.</td></tr>";
}catch(Throwable $e){
    echo "<tr><td colspan='6'><div class='alert'>".h($e->getMessage())."</div></td></tr>";
}
?>
</tbody>
</table>
</section>
<?php endif; ?>

<?php require_once __DIR__.'/layout_bottom.php'; ?>
