<?php
require_once 'common.php';
$pdo=db();
$id=(int)($_GET['id'] ?? 0);

// Kolon güvenceleri
try{ $pdo->exec("ALTER TABLE contacts ADD COLUMN active TINYINT(1) NOT NULL DEFAULT 1"); }catch(Throwable $e){}
try{ $pdo->exec("ALTER TABLE contacts ADD COLUMN phone2 varchar(60) DEFAULT NULL AFTER phone"); }catch(Throwable $e){}
try{ $pdo->exec("ALTER TABLE contacts ADD COLUMN website varchar(255) DEFAULT NULL AFTER email"); }catch(Throwable $e){}
try{ $pdo->exec("ALTER TABLE contacts ADD COLUMN postal_code varchar(20) DEFAULT NULL AFTER district"); }catch(Throwable $e){}
try{ $pdo->exec("ALTER TABLE contacts ADD COLUMN iban varchar(60) DEFAULT NULL AFTER postal_code"); }catch(Throwable $e){}

// Aktif/Pasif toggle (topx öncesi)
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['toggle_active'])){
    if(is_admin() || user_can('contacts')){
        try{
            $pdo->prepare("UPDATE contacts SET active=? WHERE id=?")->execute([(int)$_POST['toggle_active'],$id]);
        }catch(Throwable $e){}
    }
    header('Location: contact_view.php?id='.$id); exit;
}

// Silme (topx öncesi) - sadece admin
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['delete_contact'])){
    if(is_admin()){
        try{
            $pdo->prepare("DELETE FROM contact_representatives WHERE contact_id=?")->execute([$id]);
            $pdo->prepare("DELETE FROM contacts WHERE id=?")->execute([$id]);
        }catch(Throwable $e){}
        header('Location: contacts.php'); exit;
    }
}

// Cari düzenle (çıktıdan önce)
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['save_contact'])){
    try{
        $pdo->prepare("UPDATE contacts SET
            name=?,type=?,authorized_person=?,
            phone=?,phone2=?,email=?,website=?,
            tax_office=?,tax_number=?,
            city=?,district=?,postal_code=?,address=?,
            iban=?,opening_balance=?,notes=?
            WHERE id=?")
            ->execute([
                trim($_POST['name']),
                trim($_POST['type'] ?? ''),
                trim($_POST['authorized_person'] ?? ''),
                trim($_POST['phone'] ?? ''),
                trim($_POST['phone2'] ?? ''),
                trim($_POST['email'] ?? ''),
                trim($_POST['website'] ?? ''),
                trim($_POST['tax_office'] ?? ''),
                trim($_POST['tax_number'] ?? ''),
                trim($_POST['city'] ?? ''),
                trim($_POST['district'] ?? ''),
                trim($_POST['postal_code'] ?? ''),
                trim($_POST['address'] ?? ''),
                trim($_POST['iban'] ?? ''),
                (float)str_replace(',','.',$_POST['opening_balance'] ?? '0'),
                trim($_POST['notes'] ?? ''),
                $id
            ]);
        try{ if(function_exists('activity_log')) activity_log('Cari','Düzenleme',trim($_POST['name']),'','contact',$id,'contact_view.php?id='.$id,'✏️'); }catch(Throwable $e){}
    }catch(Throwable $e){}
    header('Location: contact_view.php?id='.$id.'&ok=1'); exit;
}
topx('Cari Detay');
if(!empty($_GET['ok'])) echo '<div class="notice">Cari güncellendi.</div>';
try{
    $s=db()->prepare("SELECT * FROM contacts WHERE id=?"); $s->execute([$id]); $c=$s->fetch();
    if(!$c) throw new Exception('Cari bulunamadı.');

    // WhatsApp konuşma geçmişi — bu cariye ait bir conversation varsa direkt oraya, yoksa yeni
    // mesaj gönderme ekranına (telefon önceden dolu) yönlendirilir.
    $waConvId=null;
    try{
        $waq=db()->prepare("SELECT id FROM wa_conversations WHERE contact_id=? ORDER BY last_message_at DESC LIMIT 1");
        $waq->execute([$id]);
        $waConvId=$waq->fetchColumn() ?: null;
    }catch(Throwable $e){}

    // Bakiye: açılış + tahsilat(in) - ödeme(out)
    $f=db()->prepare("SELECT
        COALESCE(SUM(CASE WHEN direction='in' THEN amount ELSE 0 END),0) tin,
        COALESCE(SUM(CASE WHEN direction='out' THEN amount ELSE 0 END),0) tout
        FROM finance_movements WHERE contact_id=?");
    $f->execute([$id]); $ft=$f->fetch();
    $bal=(float)($c['opening_balance'] ?? 0)+(float)$ft['tin']-(float)$ft['tout'];
    $balCol = $bal>0 ? '#22c55e' : ($bal<0 ? '#f87171' : '#94a3b8');
?>
<div class="panel">
  <h2 style="margin:0 0 4px"><?=htmlspecialchars($c['name'])?></h2>
  <div class="muted"><?=htmlspecialchars($c['type'] ?? '')?><?=$c['phone']?' · '.htmlspecialchars($c['phone']):''?></div>
  <div style="display:flex;justify-content:space-between;align-items:center;margin-top:14px;padding-top:14px;border-top:1px solid rgba(255,255,255,.12)">
    <div><div class="muted" style="font-size:12px">Bakiye</div><div style="font-size:24px;font-weight:900;color:<?=$balCol?>"><?=mm($bal)?></div></div>
    <div style="text-align:right"><div class="muted" style="font-size:12px">Tahsilat / Ödeme</div><div style="font-weight:800"><?=mm($ft['tin'])?> / <?=mm($ft['tout'])?></div></div>
  </div>
</div>

<?php if(is_admin() || user_can('contacts')): ?>
<?php $isActive=(int)($c['active'] ?? 1); ?>
<div style="display:flex;gap:10px;margin-bottom:4px">
  <form method="post" style="flex:1">
    <input type="hidden" name="toggle_active" value="<?=$isActive?0:1?>">
    <button class="btn<?=$isActive?'':' dark'?>" style="width:100%;<?=$isActive?'background:#f1f5f9;color:#334155':'background:#22c55e;color:#fff'?>"
      onclick="return confirm('<?=$isActive?'Pasif yapmak istediğinize emin misiniz?':'Aktif yapmak istediğinize emin misiniz?'?>')">
      <?=$isActive?'⏸ Pasif Yap':'✅ Aktif Yap'?>
    </button>
  </form>
  <?php if(is_admin()): ?>
  <form method="post" style="flex:1">
    <input type="hidden" name="delete_contact" value="1">
    <button class="btn" style="width:100%;background:#dc2626;color:#fff"
      onclick="return confirm('Bu cariyi silmek istediğinize emin misiniz? Bu işlem geri alınamaz.')">
      🗑 Sil
    </button>
  </form>
  <?php endif; ?>
</div>
<?php endif; ?>

<div class="grid">
  <a class="card green" href="collection.php?contact_id=<?=$id?>"><span>💰</span><b>Tahsilat</b><small>Bu cariden tahsilat gir</small></a>
  <a class="card orange" href="sales.php?contact_id=<?=$id?>"><span>🧾</span><b>Satış</b><small>Bu cariye satış yap</small></a>
  <a class="card purple" href="thread_open.php?type=cari&ref=<?=$id?>"><span>💬</span><b>Cari Sohbeti</b><small>Bu cariyle ilgili ekip sohbeti</small></a>
  <a class="card blue" href="report.php?modul=cari_detay&ref=<?=$id?>"><span>📊</span><b>Cari Raporu</b><small>Ekstre · hareketler · işler (PDF/paylaş)</small></a>
  <?php if($waConvId): ?>
  <a class="card green" href="wa_conversation_view.php?id=<?=(int)$waConvId?>"><span>📲</span><b>WhatsApp</b><small>Konuşma geçmişi</small></a>
  <?php elseif(!empty($c['phone'])): ?>
  <a class="card green" href="wa_conversation_view.php?phone=<?=urlencode($c['phone'])?>"><span>📲</span><b>WhatsApp</b><small>Mesaj gönder</small></a>
  <?php endif; ?>
</div>

<?php if(!empty($c['phone']) || !empty($c['email']) || !empty($c['website'])): ?>
<div class="grid">
<?php if(!empty($c['phone'])): ?>
  <a class="card blue" href="tel:<?=htmlspecialchars(preg_replace('/\s+/','',$c['phone']))?>"><span>📞</span><b>Ara</b><small><?=htmlspecialchars($c['phone'])?></small></a>
  <a class="card teal" href="https://wa.me/<?=htmlspecialchars(preg_replace('/\D/','',$c['phone']))?>"><span>💬</span><b>WhatsApp</b><small>Mesaj gönder</small></a>
<?php endif; ?>
<?php if(!empty($c['email'])): ?>
  <a class="card yellow" href="mailto:<?=htmlspecialchars($c['email'])?>"><span>✉️</span><b>E-posta</b><small><?=htmlspecialchars($c['email'])?></small></a>
<?php endif; ?>
<?php if(!empty($c['website'])): ?>
  <a class="card purple" href="<?=htmlspecialchars($c['website'])?>" target="_blank" rel="noopener"><span>🌐</span><b>Web Sitesi</b><small><?=htmlspecialchars(parse_url($c['website'],PHP_URL_HOST) ?: $c['website'])?></small></a>
<?php endif; ?>
</div>
<?php endif; ?>

<details class="panel">
  <summary style="font-weight:900;cursor:pointer">✏️ Cari Bilgilerini Düzenle</summary>
  <form method="post" style="margin-top:10px">
    <label>Cari Adı</label><input name="name" value="<?=htmlspecialchars($c['name'])?>" required>
    <label>Tür</label>
    <select name="type"><?php foreach(['Müşteri','Tedarikçi','Her İkisi'] as $tp): ?><option <?=($c['type']??'')===$tp?'selected':''?>><?=$tp?></option><?php endforeach; ?></select>
    <label>Yetkili Kişi</label><input name="authorized_person" value="<?=htmlspecialchars($c['authorized_person']??'')?>">
    <label>Telefon</label><input name="phone" type="tel" value="<?=htmlspecialchars($c['phone']??'')?>">
    <label>2. Telefon</label><input name="phone2" type="tel" value="<?=htmlspecialchars($c['phone2']??'')?>">
    <label>E-posta</label><input name="email" type="email" value="<?=htmlspecialchars($c['email']??'')?>">
    <label>Web Sitesi</label><input name="website" type="url" value="<?=htmlspecialchars($c['website']??'')?>" placeholder="https://">
    <label>Vergi Dairesi</label><input name="tax_office" value="<?=htmlspecialchars($c['tax_office']??'')?>">
    <label>Vergi / TC No</label><input name="tax_number" value="<?=htmlspecialchars($c['tax_number']??'')?>">
    <label>İl</label><input name="city" value="<?=htmlspecialchars($c['city']??'')?>">
    <label>İlçe</label><input name="district" value="<?=htmlspecialchars($c['district']??'')?>">
    <label>Posta Kodu</label><input name="postal_code" maxlength="10" value="<?=htmlspecialchars($c['postal_code']??'')?>">
    <label>Adres</label><textarea name="address" rows="2"><?=htmlspecialchars($c['address']??'')?></textarea>
    <label>IBAN</label><input name="iban" maxlength="32" value="<?=htmlspecialchars($c['iban']??'')?>" placeholder="TR00 0000 0000 0000 0000 0000 00">
    <label>Açılış Bakiyesi (₺)</label><input name="opening_balance" value="<?=htmlspecialchars($c['opening_balance']??'0')?>">
    <label>Notlar</label><textarea name="notes" rows="2"><?=htmlspecialchars($c['notes']??'')?></textarea>
    <button class="btn dark" name="save_contact" value="1" style="width:100%;padding:13px;margin-top:8px">💾 Kaydet</button>
  </form>
</details>

<div class="panel">
  <b>📋 İşler</b>
  <?php
  try{
    $jq=db()->prepare("SELECT id,job_no,title,status,due_date FROM jobs WHERE customer_id=? ORDER BY id DESC LIMIT 20");
    $jq->execute([$id]); $jobs=$jq->fetchAll();
  }catch(Throwable $e){ $jobs=[]; }
  if(!$jobs) echo '<p class="muted" style="margin:10px 0 0">Bu cariye ait iş yok.</p>';
  foreach($jobs as $j):
    $st=$j['status']; $sc=in_array($st,['Tamamlandı','Teslim Edildi'])?'#22c55e':($st==='İptal'?'#f87171':'#eab308');
  ?>
  <a class="item" href="job_view.php?id=<?=(int)$j['id']?>">
    <b><?=htmlspecialchars($j['title'])?></b> <span style="color:<?=$sc?>;font-weight:900;font-size:12px"><?=htmlspecialchars($st)?></span><br>
    <small><?=htmlspecialchars($j['job_no']??'')?><?=$j['due_date']?' · 📅 '.htmlspecialchars($j['due_date']):''?></small>
  </a>
  <?php endforeach; ?>
</div>

<div class="panel">
  <b>Son Hareketler</b>
  <?php
  $mv=db()->prepare("SELECT * FROM finance_movements WHERE contact_id=? ORDER BY id DESC LIMIT 25");
  $mv->execute([$id]); $rows=$mv->fetchAll();
  if(!$rows) echo '<p class="muted" style="margin:10px 0 0">Henüz hareket yok.</p>';
  foreach($rows as $m):
    $in=$m['direction']==='in';
  ?>
  <div class="item">
    <b style="color:<?=$in?'#22c55e':'#f87171'?>"><?=$in?'Tahsilat':'Ödeme'?>: <?=mm($m['amount'])?></b><br>
    <small><?=htmlspecialchars($m['movement_date'] ?? '')?><?=$m['description']?' · '.htmlspecialchars($m['description']):''?></small>
  </div>
  <?php endforeach; ?>
</div>

<?php
}catch(Throwable $e){ echo '<div class="err">'.htmlspecialchars($e->getMessage()).'</div>'; }
botx();
