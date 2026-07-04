<?php
require_once 'common.php';
require_once dirname(__DIR__).'/finance_lib.php';
require_once dirname(__DIR__).'/accounting_lib.php';
$pdo=db(); $id=(int)($_GET['id']??0);

/* Hareket düzenle — topx'tan ÖNCE (PRG) */
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['edit_movement'])){
    if(!can_edit_delete()){
        $_SESSION['mv_err']='Bu işlem için yetkiniz yok.';
        header('Location: movement_view.php?id='.$id); exit;
    }
    try{
        finance_movement_update($pdo,$id,$_POST);
        header('Location: movement_view.php?id='.$id.'&ok=1'); exit;
    }catch(Throwable $e){
        $_SESSION['mv_err']=$e->getMessage();
        header('Location: movement_view.php?id='.$id); exit;
    }
}
/* Hareket sil — admin veya 'edit_delete' yetkili personel */
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['delete_movement'])){
    if(can_edit_delete()){
        $res=finance_movement_delete($pdo,$id);
        if($res['ok']){ header('Location: kasa.php?deleted=1'); exit; }
        $_SESSION['mv_err']=$res['msg'];
        header('Location: movement_view.php?id='.$id); exit;
    }
    header('Location: movement_view.php?id='.$id); exit;
}

topx('Hareket');
if(!empty($_GET['ok'])) echo '<div class="ok">Hareket güncellendi.</div>';
if(!empty($_SESSION['mv_err'])){ echo '<div class="err">'.htmlspecialchars($_SESSION['mv_err']).'</div>'; unset($_SESSION['mv_err']); }

try{
    $m=$pdo->prepare("SELECT f.*, c.name cari, ac.group_name cat_group, fa.account_type acc_type FROM finance_movements f
        LEFT JOIN contacts c ON c.id=f.contact_id
        LEFT JOIN accounting_categories ac ON ac.id=f.category_id
        LEFT JOIN finance_accounts fa ON fa.id=f.account_id
        WHERE f.id=?");
    $m->execute([$id]); $mv=$m->fetch();
    if(!$mv) throw new Exception('Hareket bulunamadı.');
    $in=$mv['direction']==='in';
    $editable=in_array($mv['movement_type'],finance_movement_editable_types(),true);
    // FINANCE UX REFACTOR (2026-07-04): mevcut kaydın dolu alanlarına bakarak en olası sihirbaz
    // adımını türet — DB'ye yeni bir "tür" kolonu eklemeden, eski kayıtlar da doğru adımla açılır.
    $initialStep = finance_record_type_info($mv, $mv['cat_group'] ?? null, $mv['acc_type'] ?? null);
?>
<div class="panel">
  <h2 style="margin:0 0 4px;color:<?=$in?'#4ade80':'#f87171'?>"><?=$in?'💰 Tahsilat':'💸 Ödeme'?></h2>
  <div style="font-size:28px;font-weight:900;margin-top:6px"><?=mm($mv['amount'])?></div>
  <div class="muted" style="margin-top:6px"><?=htmlspecialchars(($mv['cari']?:'Cari seçilmedi').' · '.($mv['payment_channel']?:'').' · '.($mv['movement_date']??''))?></div>
  <?php if($mv['description']): ?><div style="margin-top:8px"><?=nl2br(htmlspecialchars($mv['description']))?></div><?php endif; ?>
  <?php if(!$editable): ?>
    <div class="muted" style="margin-top:10px">Bu hareket başka bir işlemden (satış/belge/transfer) otomatik oluşturulduğu için burada düzenlenip silinemez.</div>
  <?php elseif(can_edit_delete()): ?>
  <div style="display:flex;gap:8px;margin-top:12px;flex-wrap:wrap">
    <form method="post" style="margin:0" onsubmit="return confirm('Bu hareketi silmek istediğinize emin misiniz? Hesap bakiyesi geri alınacak.')">
      <input type="hidden" name="delete_movement" value="1">
      <button class="btn" style="background:#dc2626;color:#fff;padding:9px 16px;font-size:14px">🗑 Sil</button>
    </form>
  </div>
  <?php endif; ?>
</div>

<?php if($editable && can_edit_delete()): ?>
<details class="panel">
  <summary style="font-weight:900;cursor:pointer">✏️ Hareketi Düzenle</summary>
  <form method="post" style="margin-top:10px">
    <?php
    $cs=[]; $accounts=[]; $giderCats=[]; $gelirCats=[]; $personnel=[];
    try{ $cs=$pdo->query("SELECT id,name FROM contacts ORDER BY name")->fetchAll(); }catch(Throwable $e){}
    try{ $accounts=$pdo->query("SELECT * FROM finance_accounts WHERE COALESCE(active,1)=1 ORDER BY account_type,name")->fetchAll(); }catch(Throwable $e){}
    try{ $personnel=$pdo->query("SELECT id,name FROM personnel WHERE COALESCE(active,1)=1 ORDER BY name")->fetchAll(); }catch(Throwable $e){}
    $giderCats = acc_categories($pdo, 'gider');
    $gelirCats = acc_categories($pdo, 'gelir');
    $stepOpts = finance_record_type_options();
    ?>
    <label>İşlem Tipi</label>
    <select name="direction" id="mvDirection" onchange="mvFilterCats();mvToggleWizard()">
      <option value="in" <?=$in?'selected':''?>>Tahsilat</option>
      <option value="out" <?=!$in?'selected':''?>>Ödeme</option>
    </select>

    <div id="mvWizard" style="<?=$in?'display:none':''?>">
    <label>Ne kaydediyorsun?</label>
    <select name="record_step" id="mvStep" onchange="mvApplyStep()">
      <?php foreach($stepOpts as $key=>$o): ?><option value="<?=$key?>" <?=$initialStep===$key?'selected':''?>><?=$o['icon']?> <?=htmlspecialchars($o['label'])?></option><?php endforeach; ?>
    </select>
    </div>

    <div id="mvField_contact_id">
    <label>Cari <small class="muted">(opsiyonel)</small></label>
    <select name="contact_id"><option value="">— Cari seçilmedi —</option>
    <?php foreach($cs as $c): ?><option value="<?=$c['id']?>" <?=(int)$mv['contact_id']===(int)$c['id']?'selected':''?>><?=htmlspecialchars($c['name'])?></option><?php endforeach; ?></select>
    </div>

    <div id="mvField_personnel_id" style="display:none">
    <label>Personel</label>
    <select name="personnel_id"><option value="">— Personel seçilmedi —</option>
    <?php foreach($personnel as $p): ?><option value="<?=(int)$p['id']?>" <?=(int)($mv['personnel_id']??0)===(int)$p['id']?'selected':''?>><?=htmlspecialchars($p['name'])?></option><?php endforeach; ?></select>
    </div>

    <label><?=$in?'Kategori':'Gider Türü'?> <small class="muted">(opsiyonel)</small></label>
    <select name="category_id" id="mvCatSel"><option value="">— Seçilmedi —</option>
    <?php foreach($giderCats as $c): ?><option value="<?=(int)$c['id']?>" data-type="out" style="<?=$in?'display:none':''?>" <?=(int)$mv['category_id']===(int)$c['id']?'selected':''?>>[<?=htmlspecialchars($c['group_name'])?>] <?=htmlspecialchars($c['name'])?></option><?php endforeach; ?>
    <?php foreach($gelirCats as $c): ?><option value="<?=(int)$c['id']?>" data-type="in" style="<?=$in?'':'display:none'?>" <?=(int)$mv['category_id']===(int)$c['id']?'selected':''?>>[<?=htmlspecialchars($c['group_name'])?>] <?=htmlspecialchars($c['name'])?></option><?php endforeach; ?>
    </select>
    <label>Hesap / Kasa / Kart</label>
    <select name="account_id" required><option value="">Seçiniz</option>
    <?php foreach($accounts as $a): ?><option value="<?=$a['id']?>" <?=(int)$mv['account_id']===(int)$a['id']?'selected':''?>><?=htmlspecialchars($a['account_type'].' - '.$a['name'])?></option><?php endforeach; ?></select>
    <label>Tutar</label><input type="number" step="0.01" name="amount" value="<?=htmlspecialchars($mv['amount'])?>" required>
    <label>Ödeme Yöntemi</label>
    <select name="payment_channel">
    <?php foreach(['Nakit','Banka','Kredi Kartı','POS','Çek','Senet','Diğer'] as $ch): ?><option <?=$mv['payment_channel']===$ch?'selected':''?>><?=$ch?></option><?php endforeach; ?>
    </select>
    <label>Tarih</label><input type="date" name="movement_date" value="<?=htmlspecialchars($mv['movement_date'])?>">
    <label>Açıklama</label><textarea name="description" id="mvDesc" rows="2"><?=htmlspecialchars($mv['description']??'')?></textarea>
    <button class="btn dark" name="edit_movement" value="1" style="width:100%;padding:13px;margin-top:8px">💾 Kaydet</button>
  </form>
</details>
<script>
function mvFilterCats(){
  var t=document.getElementById('mvDirection').value;
  var opts=document.getElementById('mvCatSel').options;
  for(var i=0;i<opts.length;i++){
    var o=opts[i];
    if(!o.dataset.type){o.style.display='';continue;}
    o.style.display=(o.dataset.type===t)?'':'none';
    if(o.dataset.type!==t&&o.selected) o.selected=false;
  }
}
// FINANCE UX REFACTOR (2026-07-04): sihirbaz SADECE Ödeme (out) tarafında aktif — Tahsilat/Gelir
// akışı bu ekranda da hiç değişmiyor, sihirbaz seçilince gizlenir, alanlar zorunlu olmaz.
function mvToggleWizard(){
  var out=document.getElementById('mvDirection').value==='out';
  document.getElementById('mvWizard').style.display = out?'':'none';
  if(!out){
    document.getElementById('mvField_personnel_id').style.display='none';
    document.getElementById('mvField_personnel_id').querySelector('select').required=false;
    document.getElementById('mvField_contact_id').style.display='';
    document.getElementById('mvDesc').required=false;
  } else { mvApplyStep(); }
}
function mvApplyStep(){
  if(document.getElementById('mvDirection').value!=='out') return;
  var step=document.getElementById('mvStep').value;
  var need={cari:'contact_id',isletme:'category_id',personel:'personnel_id',vergi:'category_id',arac:'category_id',kart:null,diger:null}[step];
  var contactBox=document.getElementById('mvField_contact_id');
  contactBox.style.display = (step==='personel') ? 'none' : '';
  contactBox.querySelector('select').required = (need==='contact_id');
  document.getElementById('mvCatSel').required = (need==='category_id');
  var persBox=document.getElementById('mvField_personnel_id');
  persBox.style.display = (step==='personel') ? '' : 'none';
  persBox.querySelector('select').required = (need==='personnel_id');
  document.getElementById('mvDesc').required = (step==='diger');
}
mvToggleWizard();
</script>
<?php endif; ?>
<?php
}catch(Throwable $e){ echo '<div class="err">'.htmlspecialchars($e->getMessage()).'</div>'; }
botx();
