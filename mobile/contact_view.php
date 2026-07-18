<?php
require_once 'common.php';
require_once dirname(__DIR__).'/finance_lib.php';
require_once dirname(__DIR__).'/cpa_lib.php';
require_once dirname(__DIR__).'/cpa_allocation_lib.php';
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

// Silme (topx öncesi) - sadece admin. Bağlı finans/iş/belge/teklif/whatsapp kaydı varsa kalıcı
// silmez, pasife alır (contacts_lib.php::contact_delete_or_deactivate() — sil.php ile ortak).
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['delete_contact'])){
    if(is_admin()){
        require_once __DIR__.'/../contacts_lib.php';
        try{ contact_delete_or_deactivate($pdo,$id); }catch(Throwable $e){}
        header('Location: contacts.php'); exit;
    }
}

// Cari düzenle (çıktıdan önce) — web contact_view.php ile aynı desen: eksik olan yetki kontrolü eklendi.
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['save_contact'])){
    if(can_edit_delete()){
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
    header('Location: contact_view.php?id='.$id.'&err=yetki'); exit;
}

// P1 — CPA (2026-07-18): web contact_view.php ile aynı iki işlem, aynı cpa_lib.php fonksiyonları.
// Bu dosyanın mevcut flash-mesaj deseni GET param'a dayanıyor (?ok=1/?err=yetki) — session flash
// yok, aynı desen izlendi.
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['cpa_save'])){
    try{ cpa_upsert($pdo, $_SESSION['user']['id']??0, $id, $_POST['stock_item_id']??0, $_POST['supplier_id']??0, $_POST['priority']??1, !empty($_POST['is_default']), $_POST['notes']??''); header('Location: contact_view.php?id='.$id.'&cpaok=1'); }
    catch(Throwable $e){ header('Location: contact_view.php?id='.$id.'&cpaerr='.urlencode($e->getMessage())); }
    exit;
}
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['cpa_toggle'])){
    try{ cpa_set_status($pdo, $_SESSION['user']['id']??0, $_POST['cpa_id']??0, $_POST['cpa_toggle']==='activate'?'Aktif':'Pasif'); header('Location: contact_view.php?id='.$id.'&cpaok=1'); }
    catch(Throwable $e){ header('Location: contact_view.php?id='.$id.'&cpaerr='.urlencode($e->getMessage())); }
    exit;
}
// P0 SON KAPANIŞ (2026-07-18) — bu karttan hızlı iptal; miktar azaltma/aktarım cpa_allocation.php'de.
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['alloc_cancel'])){
    try{ cpa_alloc_cancel($pdo, $_SESSION['user']['id']??0, $_POST['alloc_id']??0); header('Location: contact_view.php?id='.$id.'&cpaok=1'); }
    catch(Throwable $e){ header('Location: contact_view.php?id='.$id.'&cpaerr='.urlencode($e->getMessage())); }
    exit;
}
topx('Cari Detay');
if(!empty($_GET['ok'])) echo ds_alert('success','Cari güncellendi.');
if(!empty($_GET['cpaok'])) echo ds_alert('success','Tedarik tercihi güncellendi.');
if(!empty($_GET['cpaerr'])) echo ds_alert('danger',$_GET['cpaerr']);
if(!empty($_GET['deleted'])) echo ds_alert('success','Finans hareketi silindi, hesap bakiyesi güncellendi.');
if(!empty($_GET['err']) && $_GET['err']==='yetki') echo ds_alert('danger','Bu işlem için yetkiniz yok.');
try{
    $s=db()->prepare("SELECT * FROM contacts WHERE id=?"); $s->execute([$id]); $c=$s->fetch();
    if(!$c) throw new Exception('Cari bulunamadı.');

    // View/Edit ayrımı: web contact_view.php ile aynı desen — düzenleme sadece mevcut kaydı
    // değiştirme yetkisi olana gösterilir.
    $canEdit = can_edit_delete();

    // WhatsApp konuşma geçmişi — bu cariye ait bir conversation varsa direkt oraya, yoksa yeni
    // mesaj gönderme ekranına (telefon önceden dolu) yönlendirilir.
    $waConvId=null;
    try{
        $waq=db()->prepare("SELECT id FROM wa_conversations WHERE contact_id=? ORDER BY last_message_at DESC LIMIT 1");
        $waq->execute([$id]);
        $waConvId=$waq->fetchColumn() ?: null;
    }catch(Throwable $e){}

    // Bakiye: web contact_view.php ile aynı düzeltilmiş formül (2026-07-10 Finans Çekirdek
    // düzeltmesi) — satış/alış (Bekliyor) borç yaratır, Tahsilat/Ödeme bunu ters işaretle kapatır.
    require_once __DIR__.'/../contacts_lib.php';
    $bal=contact_balance(db(), $id);
    $balCol = $bal>0 ? 'var(--df-success-ink)' : ($bal<0 ? 'var(--df-danger-ink)' : 'var(--df-ink-500)');
?>
<div class="df-panel">
  <h2 style="margin:0 0 4px"><?=h($c['name'])?></h2>
  <div class="df-text-caption"><?=h($c['type'] ?? '')?><?=$c['phone']?' · '.h($c['phone']):''?></div>
  <div style="display:flex;justify-content:space-between;align-items:center;margin-top:14px;padding-top:14px;border-top:1px solid var(--df-hairline)">
    <div><div class="df-text-caption">Bakiye</div><div style="font-size:24px;font-weight:900;color:<?=$balCol?>"><?=mm($bal)?></div></div>
    <div style="text-align:right"><div class="df-text-caption">Tahsilat / Ödeme</div><div style="font-weight:800"><?=mm($ft['tin'])?> / <?=mm($ft['tout'])?></div></div>
  </div>
</div>

<?php if(is_admin() || user_can('contacts')): ?>
<?php $isActive=(int)($c['active'] ?? 1); ?>
<div style="display:flex;gap:10px;margin:10px 0">
  <form method="post" style="flex:1;margin:0">
    <input type="hidden" name="toggle_active" value="<?=$isActive?0:1?>">
    <button type="submit" class="df-btn <?=$isActive?'df-btn--secondary':'df-btn--primary'?>" style="width:100%;justify-content:center"
      onclick="return confirm('<?=$isActive?'Pasif yapmak istediğinize emin misiniz?':'Aktif yapmak istediğinize emin misiniz?'?>')">
      <?=$isActive?ds_icon('close',15).' Pasif Yap':ds_icon('check',15).' Aktif Yap'?>
    </button>
  </form>
  <?php if(is_admin()): ?>
  <form method="post" style="flex:1;margin:0">
    <input type="hidden" name="delete_contact" value="1">
    <button type="submit" class="df-btn df-btn--danger" style="width:100%;justify-content:center"
      onclick="return confirm('Bu cariyi silmek istediğinize emin misiniz? Bu işlem geri alınamaz.')">
      <?=ds_icon('trash',15)?> Sil
    </button>
  </form>
  <?php endif; ?>
</div>
<?php endif; ?>

<div class="df-panel" style="margin-top:10px">
  <div style="display:flex;gap:8px;flex-wrap:wrap">
    <?=ds_button(ds_icon('wallet',15).' Tahsilat','collection.php?contact_id='.$id,'secondary','','style="flex:1 1 45%;justify-content:center"',true)?>
    <?=ds_button(ds_icon('box',15).' Satış','sales.php?contact_id='.$id,'secondary','','style="flex:1 1 45%;justify-content:center"',true)?>
    <?=ds_button(ds_icon('chat',15).' Cari Sohbeti','thread_open.php?type=cari&ref='.$id,'secondary','','style="flex:1 1 45%;justify-content:center"',true)?>
    <?=ds_button(ds_icon('info',15).' Cari Raporu','report.php?modul=cari_detay&ref='.$id,'secondary','','style="flex:1 1 45%;justify-content:center"',true)?>
    <?php if($waConvId): ?>
    <?=ds_button(ds_icon('send',15).' WhatsApp','wa_conversation_view.php?id='.(int)$waConvId,'secondary','','style="flex:1 1 100%;justify-content:center"',true)?>
    <?php elseif(!empty($c['phone'])): ?>
    <?=ds_button(ds_icon('send',15).' WhatsApp','wa_conversation_view.php?phone='.urlencode($c['phone']),'secondary','','style="flex:1 1 100%;justify-content:center"',true)?>
    <?php endif; ?>
  </div>
</div>

<?php if(!empty($c['phone']) || !empty($c['email']) || !empty($c['website'])): ?>
<div class="df-panel" style="margin-top:10px">
  <div style="display:flex;gap:8px;flex-wrap:wrap">
  <?php if(!empty($c['phone'])): ?>
    <?=ds_button(ds_icon('phone',15).' Ara','tel:'.preg_replace('/\s+/','',$c['phone']),'secondary','','style="flex:1 1 45%;justify-content:center"',true)?>
    <?=ds_button(ds_icon('send',15).' WhatsApp','https://wa.me/'.preg_replace('/\D/','',$c['phone']),'secondary','','style="flex:1 1 45%;justify-content:center"',true)?>
  <?php endif; ?>
  <?php if(!empty($c['email'])): ?>
    <?=ds_button(ds_icon('info',15).' E-posta','mailto:'.$c['email'],'secondary','','style="flex:1 1 45%;justify-content:center"',true)?>
  <?php endif; ?>
  <?php if(!empty($c['website'])): ?>
    <?=ds_button(ds_icon('tag',15).' '.h(parse_url($c['website'],PHP_URL_HOST) ?: $c['website']),$c['website'],'secondary','','target="_blank" rel="noopener" style="flex:1 1 100%;justify-content:center"',true)?>
  <?php endif; ?>
  </div>
</div>
<?php endif; ?>

<?php if($canEdit): ?>
<details class="df-panel" style="margin-top:10px">
  <summary style="cursor:pointer;font-weight:700;user-select:none"><?=ds_icon('edit',15)?> Cari Bilgilerini Düzenle</summary>
  <form method="post" style="margin-top:10px">
    <label>Cari Adı</label><input name="name" value="<?=h($c['name'])?>" required>
    <label>Tür</label>
    <select name="type"><?php foreach(['Müşteri','Tedarikçi','Her İkisi'] as $tp): ?><option <?=($c['type']??'')===$tp?'selected':''?>><?=$tp?></option><?php endforeach; ?></select>
    <label>Yetkili Kişi</label><input name="authorized_person" value="<?=h($c['authorized_person']??'')?>">
    <label>Telefon</label><input name="phone" type="tel" value="<?=h($c['phone']??'')?>">
    <label>2. Telefon</label><input name="phone2" type="tel" value="<?=h($c['phone2']??'')?>">
    <label>E-posta</label><input name="email" type="email" value="<?=h($c['email']??'')?>">
    <label>Web Sitesi</label><input name="website" type="url" value="<?=h($c['website']??'')?>" placeholder="https://">
    <label>Vergi Dairesi</label><input name="tax_office" value="<?=h($c['tax_office']??'')?>">
    <label>Vergi / TC No</label><input name="tax_number" value="<?=h($c['tax_number']??'')?>">
    <label>İl</label><input name="city" value="<?=h($c['city']??'')?>">
    <label>İlçe</label><input name="district" value="<?=h($c['district']??'')?>">
    <label>Posta Kodu</label><input name="postal_code" maxlength="10" value="<?=h($c['postal_code']??'')?>">
    <label>Adres</label><textarea name="address" rows="2"><?=h($c['address']??'')?></textarea>
    <label>IBAN</label><input name="iban" maxlength="32" value="<?=h($c['iban']??'')?>" placeholder="TR00 0000 0000 0000 0000 0000 00">
    <label>Açılış Bakiyesi (₺)</label><input name="opening_balance" value="<?=h($c['opening_balance']??'0')?>">
    <label>Notlar</label><textarea name="notes" rows="2"><?=h($c['notes']??'')?></textarea>
    <button type="submit" class="df-btn df-btn--primary df-btn--lg" name="save_contact" value="1" style="width:100%;margin-top:8px"><?=ds_icon('check',16)?> Kaydet</button>
  </form>
</details>
<?php endif; ?>

<div class="df-panel" style="margin-top:10px">
  <b><?=ds_icon('briefcase',16)?> İşler</b>
  <?php
  try{
    $jq=db()->prepare("SELECT id,job_no,title,status,due_date FROM jobs WHERE customer_id=? ORDER BY id DESC LIMIT 20");
    $jq->execute([$id]); $jobs=$jq->fetchAll();
  }catch(Throwable $e){ $jobs=[]; }
  if(!$jobs) echo '<p class="df-text-caption" style="margin:10px 0 0">Bu cariye ait iş yok.</p>';
  foreach($jobs as $j):
  ?>
  <a href="job_view.php?id=<?=(int)$j['id']?>" style="display:block;text-decoration:none;color:inherit;margin-top:10px;border-top:1px solid var(--df-hairline);padding-top:10px">
    <div style="display:flex;justify-content:space-between;gap:8px;align-items:center">
      <b><?=h($j['title'])?></b><?=ds_badge($j['status'])?>
    </div>
    <div class="df-list-row-meta" style="margin-top:4px">
      <?php if($j['job_no']): ?><span><?=h($j['job_no'])?></span><?php endif; ?>
      <?php if($j['due_date']): ?><span class="df-list-row-due"><?=ds_icon('calendar',13)?> <?=h($j['due_date'])?></span><?php endif; ?>
    </div>
  </a>
  <?php endforeach; ?>
</div>

<?php if(user_can('finance')): ?>
<div class="df-panel" style="margin-top:10px">
  <b>Son Hareketler</b>
  <?php
  $mv=db()->prepare("SELECT * FROM finance_movements WHERE contact_id=? ORDER BY id DESC LIMIT 25");
  $mv->execute([$id]); $rows=$mv->fetchAll();
  if(!$rows) echo '<p class="df-text-caption" style="margin:10px 0 0">Henüz hareket yok.</p>';
  // FINANCE CRUD UX PATCH 001 (2026-07-12): "Tahsilat"/"Ödeme" etiketi direction'dan değil
  // finance_movement_type_label()'dan (web ile aynı fonksiyon) geliyor artık — satış/alış/belge
  // kaynaklı satırlar burada da yanlışlıkla "Tahsilat"/"Ödeme" göstermesin diye. Manuel hareketler
  // mevcut mobil düzenleme ekranına (movement_view.php) bağlanıyor — yeni bir CRUD YAZILMADI.
  foreach($rows as $m):
    $in=$m['direction']==='in';
    $actions=finance_movement_actions($m);
    $canEdit=$actions['editable'] && can_edit_delete();
    $srcUrl=$actions['source_url'];
    if($srcUrl && in_array($actions['source_type'],['document','settlement'],true)) $srcUrl='../'.$srcUrl; // trade_document_view.php/finance.php sadece kökte var
  ?>
  <div style="display:flex;justify-content:space-between;align-items:center;gap:8px;margin-top:10px;border-top:1px solid var(--df-hairline);padding-top:10px">
    <div style="flex:1;min-width:0">
      <b style="color:<?=$in?'var(--df-success-ink)':'var(--df-danger-ink)'?>"><?=h(finance_movement_type_label($m))?>: <?=mm($m['amount'])?></b><br>
      <small class="df-text-caption"><?=h($m['movement_date'] ?? '')?><?=$m['description']?' · '.h($m['description']):''?></small>
    </div>
    <?php if($canEdit): ?>
    <a class="df-icon-btn" href="movement_view.php?id=<?=(int)$m['id']?>&return_context=contact&return_ref=<?=$id?>" aria-label="Düzenle"><?=ds_icon('edit',16)?></a>
    <?php elseif($srcUrl): ?>
    <a class="df-icon-btn" href="<?=h($srcUrl)?>" aria-label="Kaynağa git"><?=ds_icon('info',16)?></a>
    <?php endif; ?>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<?php if(cpa_can_view()): ?>
<div class="df-panel" style="margin-top:10px">
  <b>🎯 Tercih Edilen Tedarikçiler</b>
  <p class="df-text-caption" style="margin:4px 0 10px">Ürün bazlı tercih edilen tedarikçiler — satın alma sırasında akıllı öneri olarak kullanılır.</p>
  <?php
  $__cpaRowsM = cpa_list_for_customer($pdo, $id, true);
  foreach($__cpaRowsM as $cr):
  ?>
  <div style="display:flex;justify-content:space-between;align-items:center;gap:8px;margin-top:8px;border-top:1px solid var(--df-hairline);padding-top:8px">
    <div style="flex:1;min-width:0">
      <b><?=h($cr['product_name'] ?: '#'.$cr['stock_item_id'])?></b> → <?=h($cr['supplier_name'] ?: '#'.$cr['supplier_id'])?><br>
      <small class="df-text-caption">Öncelik <?=(int)$cr['priority']?><?=$cr['is_default']?' · Varsayılan':''?> · <?=h($cr['status'])?></small>
    </div>
    <?php if(cpa_can_edit()): ?>
    <form method="post" onsubmit="return confirm('<?=$cr['status']==='Aktif'?'Pasife almak':'Aktif etmek'?> istediğinize emin misiniz?')">
      <input type="hidden" name="cpa_id" value="<?=(int)$cr['id']?>">
      <button type="submit" class="df-btn df-btn--secondary df-btn--sm" name="cpa_toggle" value="<?=$cr['status']==='Aktif'?'deactivate':'activate'?>"><?=$cr['status']==='Aktif'?'Pasife Al':'Aktif Et'?></button>
    </form>
    <?php endif; ?>
  </div>
  <?php endforeach; ?>
  <?php if(!$__cpaRowsM): ?><p class="df-text-caption" style="margin:8px 0 0">Henüz tercih tanımlanmamış.</p><?php endif; ?>

  <?php if(cpa_can_edit()):
  $__cpaProductsM = $pdo->query("SELECT id,name FROM stock_items WHERE COALESCE(active,1)=1 ORDER BY name")->fetchAll();
  $__cpaSuppliersM = $pdo->query("SELECT id,name FROM contacts WHERE type IN ('Tedarikçi','Her İkisi') ORDER BY name")->fetchAll();
  if(!$__cpaSuppliersM) $__cpaSuppliersM = $pdo->query("SELECT id,name FROM contacts ORDER BY name")->fetchAll();
  ?>
  <details style="margin-top:10px">
    <summary style="cursor:pointer;font-weight:700">➕ Yeni Tercih Ekle</summary>
    <form method="post" style="margin-top:8px">
      <input type="hidden" name="cpa_save" value="1">
      <label>Ürün / Stok Kartı</label>
      <select name="stock_item_id" required>
        <option value="">— Ürün seç —</option>
        <?php foreach($__cpaProductsM as $p): ?><option value="<?=$p['id']?>"><?=h($p['name'])?></option><?php endforeach; ?>
      </select>
      <label>Tercih Edilen Tedarikçi</label>
      <select name="supplier_id" required>
        <option value="">— Tedarikçi seç —</option>
        <?php foreach($__cpaSuppliersM as $s): ?><option value="<?=$s['id']?>"><?=h($s['name'])?></option><?php endforeach; ?>
      </select>
      <label>Öncelik</label>
      <input type="number" name="priority" value="1" min="1">
      <label style="display:flex;align-items:center;gap:8px"><input type="checkbox" name="is_default" value="1" style="width:auto;margin:0"> Bu ürün için varsayılan tedarikçi</label>
      <label>Açıklama</label>
      <textarea name="notes" rows="2"></textarea>
      <button type="submit" class="df-btn df-btn--primary df-btn--lg" style="width:100%;margin-top:8px">Tercihi Kaydet</button>
    </form>
  </details>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php if(cpa_alloc_can_view()):
  $__allocRowsM = cpa_alloc_list_for_customer($pdo, $id, true);
?>
<div class="df-panel" style="margin-top:10px">
  <b>📦 Müşteriye Ayrılan Stok</b>
  <p class="df-text-caption" style="margin:4px 0 10px">Bu müşteri için satın almadan ayrılan miktarlar — fiziksel stoktan ayrı izlenir, satış yapıldığında otomatik düşer.</p>
  <?php if(!$__allocRowsM): ?>
  <p class="df-text-caption" style="margin:0">Henüz müşteriye ayrılmamış.</p>
  <?php else: foreach($__allocRowsM as $ar): $__remM=(float)$ar['allocated_qty']-(float)$ar['consumed_qty']; ?>
  <div style="margin-top:8px;border-top:1px solid var(--df-hairline);padding-top:8px">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:8px">
      <b><?=h($ar['product_name'] ?: '#'.$ar['stock_item_id'])?></b>
      <?=ds_badge($ar['status'])?>
    </div>
    <small class="df-text-caption">Ayrılan <?=stock_qty_fmt($ar['allocated_qty'])?> · Tüketilen <?=stock_qty_fmt($ar['consumed_qty'])?> · Kalan <b><?=stock_qty_fmt($__remM)?></b></small>
    <?php if(cpa_alloc_can_edit()): ?>
    <div style="display:flex;gap:6px;margin-top:6px;flex-wrap:wrap">
      <?php if($ar['status']!=='İptal' && $__remM>0.0000001): ?>
      <a class="df-btn df-btn--primary df-btn--sm" href="sales.php?contact_id=<?=$id?>&stock_item_id=<?=(int)$ar['stock_item_id']?>&qty=<?=h($__remM)?>">🧾 Sat</a>
      <?php endif; ?>
      <a class="df-btn df-btn--secondary df-btn--sm" href="cpa_allocation.php?purchase_id=<?=(int)$ar['purchase_movement_id']?>">Yönet</a>
      <?php if($ar['status']!=='İptal'): ?>
      <form method="post" style="margin:0" onsubmit="return confirm('Bu tahsis iptal edilecek. Emin misiniz?')">
        <input type="hidden" name="alloc_id" value="<?=(int)$ar['id']?>">
        <button type="submit" class="df-btn df-btn--danger df-btn--sm" name="alloc_cancel" value="1">İptal</button>
      </form>
      <?php endif; ?>
    </div>
    <?php endif; ?>
  </div>
  <?php endforeach; endif; ?>
</div>
<?php endif; ?>

<?php
}catch(Throwable $e){ echo ds_alert('danger',$e->getMessage()); }
botx();
