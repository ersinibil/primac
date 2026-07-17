<?php
require_once __DIR__.'/layout_top.php';
require_once __DIR__.'/accounting_lib.php';
require_once __DIR__.'/finance_lib.php';
$pdo=db();

$month=(int)($_GET['m'] ?? date('m'));
$year=(int)($_GET['y'] ?? date('Y'));
// HOTFIX-01 (2026-07-17, ACİL): $_GET['tab'] önceden hiç doğrulanmadan href'lere basılıyordu
// (reflected XSS). Sadece uygulamada gerçekten var olan 4 sekme kabul edilir, aksi halde güvenli
// varsayılana düşer — whitelist + aşağıdaki h() çıktısı birlikte (savunma derinliği).
$__accValidTabs = ['kayitlar','yeni','personel','ozet'];
$tab = $_GET['tab'] ?? 'kayitlar';
if(!in_array($tab, $__accValidTabs, true)) $tab = 'kayitlar';
$msg=''; $err='';

// Kayıt düzenleme
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['edit_entry'])){
    try{
        if(!can_edit_delete()) throw new Exception('Bu işlem için yetkiniz yok.');
        // FINANCE UX REFACTOR (2026-07-04): sihirbaz sadece gider tarafında aktif.
        if(($_POST['type'] ?? 'gider')==='gider'){
            $step=$_POST['record_step'] ?? 'diger';
            if($step==='cari' && !(int)($_POST['contact_id']??0)) throw new Exception('Cari Ödemesi için cari seçilmelidir.');
            if($step==='personel' && !(int)($_POST['personnel_id']??0)) throw new Exception('Personel Ödemesi için personel seçilmelidir.');
            if($step==='diger' && trim($_POST['description']??'')==='') throw new Exception('Diğer seçildiğinde açıklama zorunludur.');
            // GİDER TÜRÜ CONTEXT-AWARE (2026-07-04): Gider Türü artık payment_type kolonunda.
            if(in_array($step,finance_expense_type_required_steps(),true) && trim($_POST['payment_type']??'')===''){
                $stepLabels=['isletme'=>'İşletme Gideri','vergi'=>'Vergi / SGK','arac'=>'Araç Gideri'];
                throw new Exception(($stepLabels[$step]??'Bu adım').' için Gider Türü seçilmelidir.');
            }
        }
        accounting_entry_update($pdo, (int)$_POST['id'], $_POST);
        $msg='Kayıt güncellendi.';
    }catch(Throwable $e){ $err=$e->getMessage(); }
}

// Kayıt sil
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['del_entry'])){
    try{
        if(!can_edit_delete()) throw new Exception('Bu işlem için yetkiniz yok.');
        $res=accounting_entry_delete($pdo, (int)$_POST['del_entry']);
        if($res['ok']) $msg=$res['msg']; else $err=$res['msg'];
    }catch(Throwable $e){ $err=$e->getMessage(); }
}

// Yeni kayıt
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['save_entry'])){
    try{
        $type=$_POST['type'] ?? 'gider';
        $rawAmount=(float)str_replace(',','.',$_POST['amount'] ?? '0');
        $vatMode=in_array($_POST['vat_mode']??'yok',['dahil','haric','yok'],true)?$_POST['vat_mode']:'yok';
        $vatRate=(float)($_POST['vat_rate'] ?? 0);
        $vc=acc_calc_vat($rawAmount,$vatMode,$vatRate);
        $amount=$vc['amount'];
        $date=$_POST['entry_date'] ?: date('Y-m-d');
        $catId=(int)($_POST['category_id']??0) ?: null;
        $desc=trim($_POST['description'] ?? '');
        $refNo=trim($_POST['reference_no'] ?? '');
        $accId=(int)($_POST['account_id']??0) ?: null;
        $pid=(int)($_POST['personnel_id']??0) ?: null;
        $pt=trim($_POST['payment_type'] ?? '');
        $contactId=(int)($_POST['contact_id']??0) ?: null;
        $direction = $type==='gelir' ? 'in' : 'out';
        if($amount<=0) throw new Exception('Tutar sıfırdan büyük olmalı.');
        // FINANCE UX REFACTOR (2026-07-04): "Ne kaydediyorsun?" sihirbazı sadece gider (out)
        // tarafında aktif — Gelir kayıtları hiç etkilenmiyor. Sunucu tarafı doğrulama (JS'e ek).
        if($type==='gider'){
            $step=$_POST['record_step'] ?? 'diger';
            if($step==='cari' && !$contactId) throw new Exception('Cari Ödemesi için cari seçilmelidir.');
            if($step==='personel' && !$pid) throw new Exception('Personel Ödemesi için personel seçilmelidir.');
            if($step==='diger' && $desc==='') throw new Exception('Diğer seçildiğinde açıklama zorunludur.');
            // GİDER TÜRÜ CONTEXT-AWARE (2026-07-04): Gider Türü artık payment_type kolonunda.
            if(in_array($step,finance_expense_type_required_steps(),true) && $pt===''){
                $stepLabels=['isletme'=>'İşletme Gideri','vergi'=>'Vergi / SGK','arac'=>'Araç Gideri'];
                throw new Exception(($stepLabels[$step]??'Bu adım').' için Gider Türü seçilmelidir.');
            }
        }
        // 2026-07-03: Muhasebe kayıtları artık finance_movements'a yazılır (movement_type='muhasebe')
        // — böylece cari (varsa) ekstresinde ve genel finans raporlarında otomatik görünür.
        $pdo->prepare("INSERT INTO finance_movements(contact_id,category_id,direction,amount,vat_mode,vat_rate,vat_amount,account_id,personnel_id,payment_type,status,movement_date,description,reference_no,movement_type)
            VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,'muhasebe')")
            ->execute([$contactId,$catId,$direction,$amount,$vatMode,$vc['vat_rate'],$vc['vat_amount'],$accId,$pid,$pt,($direction==='in'?'Tahsil Edildi':'Ödendi'),$date,$desc,$refNo]);
        // Kasa bakiyesi güncelle
        if($accId){
            $dir=$direction==='in'?'+':'-';
            try{ $pdo->prepare("UPDATE finance_accounts SET current_balance=current_balance{$dir}? WHERE id=?")->execute([$amount,$accId]); }catch(Throwable $e){}
        }
        $msg='Kayıt eklendi.';
    }catch(Throwable $e){ $err=$e->getMessage(); }
}

$sum=acc_summary($pdo,$month,$year);
$net=$sum['gelir']-$sum['gider'];
$cats=acc_categories($pdo);
// GİDER TÜRÜ CONTEXT-AWARE (2026-07-04): "Gider Türü" artık category_id yerine payment_type
// katalogundan geliyor (finance_expense_type_options()) — category_id sadece Gelir tarafında kalır.
$gelirCats=array_filter($cats,function($c){ return $c['type']==='gelir'; });
try{ $accounts=$pdo->query("SELECT id,name,account_type FROM finance_accounts WHERE active=1 ORDER BY name")->fetchAll(); }catch(Throwable $e){ $accounts=[]; }
try{ $personnel=$pdo->query("SELECT id,name FROM personnel WHERE COALESCE(active,1)=1 ORDER BY name")->fetchAll(); }catch(Throwable $e){ $personnel=[]; }
try{ $contacts=$pdo->query("SELECT id,name FROM contacts ORDER BY name")->fetchAll(); }catch(Throwable $e){ $contacts=[]; }

// Kayıtlar listesi — finance_movements (movement_type='muhasebe')
$where="WHERE fm.movement_type='muhasebe' AND YEAR(fm.movement_date)=$year AND MONTH(fm.movement_date)=$month";
$typeF=$_GET['type'] ?? '';
if($typeF==='gelir') $where.=" AND fm.direction='in'";
elseif($typeF==='gider') $where.=" AND fm.direction='out'";
$catF=(int)($_GET['cat'] ?? 0);
if($catF) $where.=" AND fm.category_id=$catF";
try{
    $entries=$pdo->query("SELECT fm.*, fm.movement_date entry_date, IF(fm.direction='in','gelir','gider') type,
        ac.name cat_name,ac.group_name,fa.name acc_name,fa.account_type,p.name pers_name,c.name contact_name
        FROM finance_movements fm
        LEFT JOIN accounting_categories ac ON ac.id=fm.category_id
        LEFT JOIN finance_accounts fa ON fa.id=fm.account_id
        LEFT JOIN personnel p ON p.id=fm.personnel_id
        LEFT JOIN contacts c ON c.id=fm.contact_id
        $where ORDER BY fm.movement_date DESC,fm.id DESC LIMIT 200")->fetchAll();
}catch(Throwable $e){ $entries=[]; }

$prevM=$month===1?12:$month-1; $prevY=$month===1?$year-1:$year;
$nextM=$month===12?1:$month+1; $nextY=$month===12?$year+1:$year;

$groups=acc_group_summary($pdo,$month,$year);
?>
<style>
.acc-tabs{display:flex;gap:6px;margin-bottom:18px;flex-wrap:wrap}
.acc-tabs a{padding:8px 16px;border-radius:10px;text-decoration:none;font-weight:700;font-size:13px;background:#f1f5f9;color:#374151}
.acc-tabs a.active{background:#2563eb;color:#fff}
.acc-summary{display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin-bottom:20px}
.acc-card{background:#fff;border:1px solid #e5e7eb;border-radius:14px;padding:16px;text-align:center}
.acc-card .num{font-size:24px;font-weight:900}
.acc-card small{color:#667085;font-size:12px;display:block;margin-top:3px}
.gider-color{color:#dc2626}.gelir-color{color:#16a34a}.net-pos{color:#16a34a}.net-neg{color:#dc2626}
.acc-entry{display:flex;justify-content:space-between;align-items:flex-start;padding:10px 0;border-bottom:1px solid #f1f5f9}
.acc-entry:last-child{border-bottom:0}
.group-bar{display:flex;justify-content:space-between;padding:6px 10px;border-radius:8px;margin:4px 0;font-size:13px}
</style>

<?php
$__accActions = ds_button('‹','?m='.$prevM.'&y='.$prevY.'&tab='.h($tab),'ghost','','',true)
    . '<span style="font-weight:900;font-size:15px;padding:0 6px;display:inline-flex;align-items:center">'.date('F Y',mktime(0,0,0,$month,1,$year)).'</span>'
    . ds_button('›','?m='.$nextM.'&y='.$nextY.'&tab='.h($tab),'ghost','','',true)
    . ds_button('Bu Ay','?m='.(int)date('m').'&y='.(int)date('Y').'&tab='.h($tab),'secondary','df-btn--sm','',true);
ds_page_header('Muhasebe', ds_icon('wallet',24), '', $__accActions, false, true);
?>

<div class="acc-summary">
  <div class="acc-card"><div class="num gelir-color"><?=money($sum['gelir'])?></div><small>Gelir</small></div>
  <div class="acc-card"><div class="num gider-color"><?=money($sum['gider'])?></div><small>Gider</small></div>
  <div class="acc-card"><div class="num <?=$net>=0?'net-pos':'net-neg'?>"><?=money(abs($net))?></div><small><?=$net>=0?'Net Kâr':'Net Zarar'?></small></div>
</div>

<?php if($msg): ?><?=ds_alert('success',$msg)?><?php endif; ?>
<?php if($err): ?><?=ds_alert('danger',$err)?><?php endif; ?>

<?php
$__accTabItems=[
    ['label'=>'📋 Kayıtlar','url'=>'?m='.$month.'&y='.$year.'&tab=kayitlar','active'=>$tab==='kayitlar'],
    ['label'=>'➕ Yeni Kayıt','url'=>'?m='.$month.'&y='.$year.'&tab=yeni','active'=>$tab==='yeni'],
    ['label'=>'👷 Personel','url'=>'?m='.$month.'&y='.$year.'&tab=personel','active'=>$tab==='personel'],
    ['label'=>'📊 Özet','url'=>'?m='.$month.'&y='.$year.'&tab=ozet','active'=>$tab==='ozet'],
];
ds_tabs($__accTabItems);
if(is_admin()) echo '<div style="margin-top:8px">'.ds_button('⚙ Kategoriler','accounting_categories.php','ghost','df-btn--sm','',true).'</div>';
?>

<script>
// GİDER TÜRÜ CONTEXT-AWARE (2026-07-04): adıma özel "Gider Türü" katalogu — finance_lib.php'deki
// finance_expense_type_options() ile BİREBİR aynı (web+mobil tek kaynak, PHP'den JSON'a dökülüyor).
// Bu blok, tab'den BAĞIMSIZ olarak sayfanın en başında render edilir — hem "Yeni Kayıt" (tab=yeni)
// formu hem de "Kayıtlar" (tab=kayitlar) satır-içi düzenleme formları kullanır, ikisinden de ÖNCE
// tanımlı olması gerekir (aksi halde "is not defined" hatası ile sihirbaz hiç çalışmaz).
var ACC_TUR_CATALOG = <?=json_encode(finance_expense_type_options())?>;
var ACC_TUR_REQUIRED = <?=json_encode(finance_expense_type_required_steps())?>;
function accBuildTurOptions(selectEl,step){
  var cur=selectEl.value || selectEl.dataset.current || '';
  var opts=ACC_TUR_CATALOG[step] || [];
  selectEl.innerHTML='<option value="">— Seç —</option>';
  for(var i=0;i<opts.length;i++){
    var el=document.createElement('option');
    el.value=opts[i].v; el.textContent=opts[i].t;
    if(opts[i].v===cur) el.selected=true;
    selectEl.appendChild(el);
  }
  selectEl.dataset.current='';
}
</script>

<?php if($tab==='yeni'): ?>
<!-- RELEASE DS MIGRATION (2026-07-18): render katmanı df-card/df-form-grid-2'ye taşındı, POST
     mantığı (yukarısı) ve tüm input name/id öznitelikleri HİÇ değişmedi — aşağıdaki script bloğu
     (filterCats/accToggleWizard/accApplyStep/toggleVatRate/calcVat) birebir aynı id'lerle çalışır. -->
<section class="df-card">
<h2 class="df-section-title">Yeni Muhasebe Kaydı</h2>
<form method="post" class="df-form-grid-2">
<input type="hidden" name="save_entry" value="1">

<div class="df-form-group">
<label class="df-form-label">Tür</label>
<select name="type" id="entryType" onchange="filterCats();accToggleWizard()">
  <option value="gider">Gider</option>
  <option value="gelir">Gelir</option>
</select>
</div>

<div class="df-form-group df-form-span-2" id="accWizardLabel">
<label class="df-form-label">Ne kaydediyorsun?</label>
<select name="record_step" id="accStep" onchange="accApplyStep()">
  <?php foreach(finance_record_type_options() as $key=>$o): ?><option value="<?=$key?>"><?=$o['icon']?> <?=h($o['label'])?></option><?php endforeach; ?>
</select>
</div>

<div class="df-form-group">
<label class="df-form-label">Tarih</label>
<input type="date" name="entry_date" value="<?=date('Y-m-d')?>">
</div>

<div class="df-form-group" id="accCatLabel">
<label class="df-form-label">Kategori</label>
<select name="category_id" id="catSel">
  <option value="">— Seç —</option>
  <?php foreach($gelirCats as $c): ?>
  <option value="<?=(int)$c['id']?>" data-type="gelir">[<?=h($c['group_name'])?>] <?=h($c['name'])?></option>
  <?php endforeach; ?>
</select>
</div>

<div class="df-form-group">
<label class="df-form-label">Tutar (₺)</label>
<input type="number" step="0.01" min="0.01" name="amount" id="amtInput" required placeholder="0,00" oninput="calcVat()">
</div>

<div class="df-form-group">
<label class="df-form-label">KDV Durumu</label>
<select name="vat_mode" id="vatMode" onchange="toggleVatRate()">
  <option value="yok">KDV Yok / Belirtilmedi</option>
  <option value="dahil">KDV Dahil (girilen tutar toplam)</option>
  <option value="haric">KDV Hariç (girilen tutar KDV'siz taban)</option>
</select>
</div>

<div class="df-form-group" id="vatRateLabel" style="display:none">
<label class="df-form-label">KDV Oranı</label>
<select name="vat_rate" id="vatRate" onchange="calcVat()">
  <?php foreach(acc_vat_rates() as $vr): ?>
  <option value="<?=$vr?>" <?=$vr===20?'selected':''?>>%<?=$vr?></option>
  <?php endforeach; ?>
</select>
</div>
<div id="vatPreview" class="df-form-span-2" style="display:none;font-size:13px;color:#475467;margin-top:-8px"></div>

<div class="df-form-group">
<label class="df-form-label">Açıklama</label>
<input name="description" id="accDesc" placeholder="İsteğe bağlı">
</div>

<div class="df-form-group">
<label class="df-form-label">Belge / Ref No</label>
<input name="reference_no" placeholder="Fatura no, makbuz no vb.">
</div>

<div class="df-form-group">
<label class="df-form-label">Hesap (Kasa/Banka)</label>
<select name="account_id">
  <option value="">— Seçme —</option>
  <?php foreach($accounts as $a): ?>
  <option value="<?=(int)$a['id']?>"><?=h($a['name'])?> (<?=h($a['account_type'])?>)</option>
  <?php endforeach; ?>
</select>
</div>

<div class="df-form-group" id="accContactLabel" style="display:none">
<label class="df-form-label">Cari</label>
<select name="contact_id">
  <option value="">— Cari seçilmedi —</option>
  <?php foreach($contacts as $c): ?>
  <option value="<?=(int)$c['id']?>"><?=h($c['name'])?></option>
  <?php endforeach; ?>
</select>
</div>

<div class="df-form-group" id="persLabel">
<label class="df-form-label">Personel</label>
<select name="personnel_id">
  <option value="">— Yok —</option>
  <?php foreach($personnel as $p): ?>
  <option value="<?=(int)$p['id']?>"><?=h($p['name'])?></option>
  <?php endforeach; ?>
</select>
</div>

<!-- GİDER TÜRÜ CONTEXT-AWARE (2026-07-04): önceden sadece "Personel ise" sabit bir liste sunan bu
     alan artık TÜM adımlara özel (finance_expense_type_options()), accApplyStep() ile yeniden kurulur. -->
<div class="df-form-group" id="ptLabel">
<label class="df-form-label">Gider Türü</label>
<select name="payment_type" id="turSel">
  <option value="">— Seç —</option>
</select>
</div>

<div class="df-form-span-2">
<button class="df-btn df-btn--primary" type="submit">💾 Kaydet</button>
</div>
</form>
</section>
<script>
function filterCats(){
  var t=document.getElementById('entryType').value;
  var opts=document.getElementById('catSel').options;
  for(var i=0;i<opts.length;i++){
    var o=opts[i];
    if(!o.dataset.type){o.style.display='';continue;}
    o.style.display=(o.dataset.type===t)?'':'none';
    if(o.dataset.type!==t&&o.selected) o.selected=false;
  }
}
// FINANCE UX REFACTOR (2026-07-04): "Ne kaydediyorsun?" sihirbazı sadece Gider tarafında aktif —
// Gelir kayıtları hiç etkilenmiyor, sihirbaz gizlenir, alanlar zorunlu olmaz.
function accToggleWizard(){
  var gider=document.getElementById('entryType').value==='gider';
  document.getElementById('accWizardLabel').style.display=gider?'':'none';
  document.getElementById('accCatLabel').style.display=gider?'none':'';
  document.getElementById('catSel').required=false;
  document.getElementById('ptLabel').style.display=gider?'':'none';
  if(!gider){
    document.getElementById('persLabel').style.display='none';
    document.getElementById('persLabel').querySelector('select').required=false;
    document.getElementById('accContactLabel').style.display='';
    document.getElementById('accDesc').required=false;
    document.getElementById('turSel').required=false;
  } else { accApplyStep(); }
}
// Her adım sadece kendi ilgili alanını gösterir (yanlış kayıt ihtimalini azaltma amacı) — Cari
// alanı SADECE "Cari Ödemesi" adımında, Personel SADECE "Personel Ödemesi" adımında görünür.
function accApplyStep(){
  if(document.getElementById('entryType').value!=='gider') return;
  var step=document.getElementById('accStep').value;
  var contactBox=document.getElementById('accContactLabel');
  contactBox.style.display=(step==='cari')?'':'none';
  contactBox.querySelector('select').required=(step==='cari');
  var persBox=document.getElementById('persLabel');
  persBox.style.display=(step==='personel')?'':'none';
  persBox.querySelector('select').required=(step==='personel');
  accBuildTurOptions(document.getElementById('turSel'),step);
  document.getElementById('turSel').required=(ACC_TUR_REQUIRED.indexOf(step)!==-1);
  document.getElementById('accDesc').required=(step==='diger');
}
accToggleWizard();
// KDV dahil/hariç hesabı (yeni kayıt formu)
function toggleVatRate(){
  var m=document.getElementById('vatMode').value;
  document.getElementById('vatRateLabel').style.display=(m==='yok')?'none':'block';
  document.getElementById('vatPreview').style.display=(m==='yok')?'none':'block';
  calcVat();
}
function calcVat(){
  var m=document.getElementById('vatMode').value;
  var amt=parseFloat(document.getElementById('amtInput').value)||0;
  var rate=parseFloat(document.getElementById('vatRate').value)||0;
  var out=document.getElementById('vatPreview');
  if(m==='haric'){ var vat=amt*rate/100; var total=amt+vat;
    out.textContent='KDV: '+vat.toLocaleString('tr-TR',{minimumFractionDigits:2})+' ₺ · Ödenecek/Alınacak Toplam: '+total.toLocaleString('tr-TR',{minimumFractionDigits:2})+' ₺'; }
  else if(m==='dahil'){ var vat=amt-(amt/(1+rate/100));
    out.textContent='Girilen tutarın içindeki KDV: '+vat.toLocaleString('tr-TR',{minimumFractionDigits:2})+' ₺ (toplam değişmez)'; }
  else out.textContent='';
}
// Aynı desen — düzenleme formları için (id bazlı)
function toggleVatRateEdit(id){
  var m=document.getElementById('editVatMode'+id).value;
  document.getElementById('editVatRateLabel'+id).style.display=(m==='yok')?'none':'block';
}
</script>

<?php elseif($tab==='personel'): ?>
<section class="df-card">
<h2 class="df-section-title">Personel Ödemeleri — <?=$year?></h2>
<?php
$py=acc_personnel_summary($pdo,$year);
if(!$py){ ds_empty_state('Bu yıl personel ödemesi kaydı yok.', null, ds_icon('users',32)); }
else{
    $byPers=[];
    foreach($py as $r){ $byPers[$r['pers_name']][$r['payment_type']]=(float)$r['total']; }
    echo '<div class="df-table-wrap"><table class="df-table">';
    echo '<thead><tr><th>Personel</th><th>Maaş</th><th>Avans</th><th>Prim</th><th>SGK</th><th>Toplam</th></tr></thead><tbody>';
    foreach($byPers as $name=>$types){
        $total=array_sum($types);
        echo '<tr>';
        echo '<td style="font-weight:700">'.h($name).'</td>';
        foreach(['maas','avans','prim','sgk'] as $pt) echo '<td style="text-align:right;font-size:13px">'.($types[$pt]??0?money($types[$pt]):'-').'</td>';
        echo '<td style="text-align:right;font-weight:900">'.money($total).'</td>';
        echo '</tr>';
    }
    echo '</tbody></table></div>';
}
?>
</section>

<?php elseif($tab==='ozet'): ?>
<section class="df-card">
<h2 class="df-section-title">Grup Özeti — <?=date('F Y',mktime(0,0,0,$month,1,$year))?></h2>
<?php if(!$groups){ ds_empty_state('Bu ay kayıt yok.', null, ds_icon('wallet',32)); }
else{
    $byType=['gider'=>[],'gelir'=>[]];
    foreach($groups as $g){ $byType[$g['type']][]=$g; }
    foreach(['gelir','gider'] as $t){
        if(!$byType[$t]) continue;
        echo '<div style="font-weight:900;color:'.($t==='gelir'?'#16a34a':'#dc2626').';margin:14px 0 6px">'.($t==='gelir'?'📈 Gelir':'📉 Gider').'</div>';
        foreach($byType[$t] as $g){
            $pct=($sum[$t]>0)?round($g['total']/$sum[$t]*100):0;
            echo '<div class="group-bar" style="background:'.($t==='gelir'?'#f0fdf4':'#fef2f2').'">';
            echo '<span>'.h($g['group_name']).'</span>';
            echo '<span style="font-weight:700">'.money($g['total']).' <span style="color:#94a3b8;font-size:11px">'.$pct.'%</span></span></div>';
        }
    }
}
?>
</section>

<?php else: ?>
<section class="df-card">
<?php
ds_tabs([
    ['label'=>'Tümü','url'=>'?m='.$month.'&y='.$year.'&tab=kayitlar','active'=>!$typeF],
    ['label'=>'Gider','url'=>'?m='.$month.'&y='.$year.'&tab=kayitlar&type=gider','active'=>$typeF==='gider'],
    ['label'=>'Gelir','url'=>'?m='.$month.'&y='.$year.'&tab=kayitlar&type=gelir','active'=>$typeF==='gelir'],
]);
?>
<div style="margin-top:var(--df-space-3)">
<?php if(!$entries){ ds_empty_state('Bu dönemde kayıt yok.', null, ds_icon('wallet',32)); }
foreach($entries as $e):
    $isGider=$e['type']==='gider';
    $tColor=$isGider?'#dc2626':'#16a34a';
?>
<div class="acc-entry">
  <div style="flex:1;min-width:0">
    <div style="font-weight:700;font-size:14px"><?=h($e['cat_name'] ?: 'Kategorisiz')?></div>
    <div style="font-size:12px;color:#667085;margin-top:2px">
      <?=h(date('d.m.Y',strtotime($e['entry_date'])))?>
      <?php if($e['contact_name']): ?> · 🤝 <?=h($e['contact_name'])?><?php endif; ?>
      <?php if($e['pers_name']): ?> · 👷 <?=h($e['pers_name'])?><?php endif; ?>
      <?php if($e['acc_name']): ?> · 🏦 <?=h($e['acc_name'])?><?php else: ?> · <span class="df-badge df-badge--warning" title="Bu kayıt bir Kasa/Banka hesabına bağlı değil, Finans'taki hesap bakiyelerine yansımaz">⚠️ Hesaba bağlı değil</span><?php endif; ?>
      <?php if($e['reference_no']): ?> · #<?=h($e['reference_no'])?><?php endif; ?>
    </div>
    <?php if($e['description']): ?><div style="font-size:12px;color:#374151;margin-top:2px"><?=h($e['description'])?></div><?php endif; ?>
  </div>
  <div style="text-align:right;flex:0 0 auto;margin-left:10px">
    <div style="font-weight:900;color:<?=$tColor?>;font-size:15px"><?=$isGider?'-':'+' ?><?=money($e['amount'])?></div>
    <?php if(($e['vat_mode']??'yok')!=='yok' && $e['vat_rate']): ?>
    <div style="font-size:10.5px;color:#94a3b8;margin-top:2px">KDV %<?=(int)$e['vat_rate']?> <?=$e['vat_mode']==='dahil'?'dahil':'hariç'?> (<?=money($e['vat_amount'])?>)</div>
    <?php endif; ?>
    <?php if(can_edit_delete()): ?>
    <div class="row-actions" style="margin:4px 0 0;justify-content:flex-end">
      <button class="df-btn df-btn--secondary df-btn--sm" type="button" onclick="document.getElementById('edit-acc-<?=(int)$e['id']?>').style.display=(document.getElementById('edit-acc-<?=(int)$e['id']?>').style.display==='none'?'block':'none')">✏️ Düzenle</button>
      <form method="post" style="display:inline" onsubmit="return confirm('Bu kaydı silmek istediğinize emin misiniz?')">
        <button name="del_entry" value="<?=(int)$e['id']?>" class="df-btn df-btn--danger df-btn--sm">🗑 Sil</button>
      </form>
    </div>
    <?php endif; ?>
  </div>
</div>
<?php if(can_edit_delete()): ?>
<div id="edit-acc-<?=(int)$e['id']?>" class="df-card" style="display:none;background:var(--df-surface-sunken);margin:10px 0">
  <h3 style="margin:0 0 14px;font-size:15px">Kaydı Düzenle</h3>
  <form method="post" class="df-form-grid-2">
    <input type="hidden" name="id" value="<?=(int)$e['id']?>">
    <div class="df-form-group">
      <label class="df-form-label">Tür</label>
      <select name="type" id="editType<?=(int)$e['id']?>" onchange="filterCatsEdit(<?=(int)$e['id']?>);accToggleWizardEdit(<?=(int)$e['id']?>)">
        <option value="gider" <?=$e['type']==='gider'?'selected':''?>>Gider</option>
        <option value="gelir" <?=$e['type']==='gelir'?'selected':''?>>Gelir</option>
      </select>
    </div>
    <?php $eStep=finance_record_type_info($e, $e['group_name']??null, $e['account_type']??null); ?>
    <div class="df-form-group df-form-span-2" id="editWizardLabel<?=(int)$e['id']?>" style="<?=$isGider?'':'display:none'?>">
      <label class="df-form-label">Ne kaydediyorsun?</label>
      <select name="record_step" id="editStep<?=(int)$e['id']?>" onchange="accApplyStepEdit(<?=(int)$e['id']?>)">
        <?php foreach(finance_record_type_options() as $key=>$o): ?><option value="<?=$key?>" <?=$eStep===$key?'selected':''?>><?=$o['icon']?> <?=h($o['label'])?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="df-form-group">
      <label class="df-form-label">Tarih</label>
      <input type="date" name="entry_date" value="<?=h($e['entry_date'])?>">
    </div>
    <div class="df-form-group" id="editCatLabel<?=(int)$e['id']?>" style="<?=$isGider?'display:none':''?>">
      <label class="df-form-label">Kategori</label>
      <select name="category_id" id="editCatSel<?=(int)$e['id']?>">
        <option value="">— Seç —</option>
        <?php foreach($gelirCats as $c): ?>
        <option value="<?=(int)$c['id']?>" data-type="gelir" <?=$e['category_id']==$c['id']?'selected':''?>>[<?=h($c['group_name'])?>] <?=h($c['name'])?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="df-form-group">
      <label class="df-form-label">Tutar (₺)</label>
      <input type="number" step="0.01" min="0.01" name="amount" required value="<?=h(str_replace('.',',',$e['amount']))?>">
    </div>
    <div class="df-form-group">
      <label class="df-form-label">KDV Durumu</label>
      <select name="vat_mode" id="editVatMode<?=(int)$e['id']?>" onchange="toggleVatRateEdit(<?=(int)$e['id']?>)">
        <option value="yok" <?=($e['vat_mode']??'yok')==='yok'?'selected':''?>>KDV Yok / Belirtilmedi</option>
        <option value="dahil" <?=($e['vat_mode']??'')==='dahil'?'selected':''?>>KDV Dahil</option>
        <option value="haric" <?=($e['vat_mode']??'')==='haric'?'selected':''?>>KDV Hariç</option>
      </select>
    </div>
    <div class="df-form-group" id="editVatRateLabel<?=(int)$e['id']?>" style="display:<?=in_array($e['vat_mode']??'yok',['dahil','haric'],true)?'block':'none'?>">
      <label class="df-form-label">KDV Oranı</label>
      <select name="vat_rate">
        <?php foreach(acc_vat_rates() as $vr): ?>
        <option value="<?=$vr?>" <?=(int)($e['vat_rate']??20)===$vr?'selected':''?>>%<?=$vr?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="df-form-group df-form-span-2">
      <label class="df-form-label">Açıklama</label>
      <input name="description" id="editDesc<?=(int)$e['id']?>" value="<?=h($e['description'] ?? '')?>">
    </div>
    <div class="df-form-group df-form-span-2">
      <label class="df-form-label">Belge / Ref No</label>
      <input name="reference_no" value="<?=h($e['reference_no'] ?? '')?>">
    </div>
    <div class="df-form-group">
      <label class="df-form-label">Hesap (Kasa/Banka)</label>
      <select name="account_id">
        <option value="">— Seçme —</option>
        <?php foreach($accounts as $a): ?>
        <option value="<?=(int)$a['id']?>" <?=$e['account_id']==$a['id']?'selected':''?>><?=h($a['name'])?> (<?=h($a['account_type'])?>)</option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="df-form-group" id="editContactLabel<?=(int)$e['id']?>" style="<?=($isGider && $eStep!=='cari')?'display:none':''?>">
      <label class="df-form-label">Cari</label>
      <select name="contact_id">
        <option value="">— Cari seçilmedi —</option>
        <?php foreach($contacts as $c): ?>
        <option value="<?=(int)$c['id']?>" <?=$e['contact_id']==$c['id']?'selected':''?>><?=h($c['name'])?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="df-form-group" id="editPersLabel<?=(int)$e['id']?>" style="<?=($isGider && $eStep!=='personel')?'display:none':''?>">
      <label class="df-form-label">Personel</label>
      <select name="personnel_id">
        <option value="">— Yok —</option>
        <?php foreach($personnel as $p): ?>
        <option value="<?=(int)$p['id']?>" <?=$e['personnel_id']==$p['id']?'selected':''?>><?=h($p['name'])?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <!-- GİDER TÜRÜ CONTEXT-AWARE (2026-07-04): payment_type-bağlı yeni "Gider Türü" — seçenekleri
         accApplyStepEdit() ile adıma özel yeniden oluşturulur. -->
    <div class="df-form-group" id="editTurLabel<?=(int)$e['id']?>" style="<?=$isGider?'':'display:none'?>">
      <label class="df-form-label">Gider Türü</label>
      <select name="payment_type" id="editTurSel<?=(int)$e['id']?>" data-current="<?=h($e['payment_type'] ?? '')?>">
        <option value="">— Seç —</option>
      </select>
    </div>
    <div class="df-form-span-2" style="display:flex;gap:8px">
      <button class="df-btn df-btn--primary" type="submit" name="edit_entry" value="1">💾 Kaydet</button>
      <button class="df-btn df-btn--secondary" type="button" onclick="document.getElementById('edit-acc-<?=(int)$e['id']?>').style.display='none'">✕ Kapat</button>
    </div>
  </form>
</div>
<script>
// NOT (2026-07-04 düzeltmesi): accToggleWizardEdit()'in tanımı sayfanın en altındaki ortak script
// bloğunda — bu satır (kayıt döngüsü içinde, tanım bloğundan ÖNCE) doğrudan çağrılırsa "is not
// defined" hatası veriyordu. DOMContentLoaded'a ertelenerek tüm script bloklarının yüklenmesi
// bekleniyor (Gider Türü kutusunun ilk açılışta doğru adım/zorunluluk ile gelmesi için gerekli).
document.addEventListener('DOMContentLoaded',function(){accToggleWizardEdit(<?=(int)$e['id']?>);});
</script>
<?php endif; ?>
<?php endforeach; ?>
</div>
</section>
<?php endif; ?>

<script>
// NOT (2026-07-04 düzeltmesi): filterCatsEdit önceden yalnızca tab=yeni'nin script bloğunda
// tanımlıydı — satır-içi Düzenle formları (tab=kayitlar) bu fonksiyonu çağırdığı için tab=kayitlar'da
// "filterCatsEdit is not defined" hatası veriyor, onchange zinciri (accToggleWizardEdit dahil) hiç
// çalışmıyordu. Buraya (her tab'de render edilen ortak bloğa) taşındı.
function filterCatsEdit(id){
  var t=document.getElementById('editType'+id).value;
  var opts=document.getElementById('editCatSel'+id).options;
  for(var i=0;i<opts.length;i++){
    var o=opts[i];
    if(!o.dataset.type){o.style.display='';continue;}
    o.style.display=(o.dataset.type===t)?'':'none';
    if(o.dataset.type!==t&&o.selected) o.selected=false;
  }
}
// FINANCE UX REFACTOR (2026-07-04): satır-içi düzenleme formlarının sihirbaz mantığı (add-form'daki
// accToggleWizard/accApplyStep ile aynı desen, sadece id son eki ile satıra özel).
function accToggleWizardEdit(id){
  var wl=document.getElementById('editWizardLabel'+id); if(!wl) return;
  var gider=document.getElementById('editType'+id).value==='gider';
  wl.style.display=gider?'':'none';
  document.getElementById('editCatLabel'+id).style.display=gider?'none':'';
  document.getElementById('editCatSel'+id).required=false;
  document.getElementById('editTurLabel'+id).style.display=gider?'':'none';
  if(!gider){
    document.getElementById('editPersLabel'+id).style.display='none';
    document.getElementById('editPersLabel'+id).querySelector('select').required=false;
    document.getElementById('editContactLabel'+id).style.display='';
    document.getElementById('editDesc'+id).required=false;
    document.getElementById('editTurSel'+id).required=false;
  } else { accApplyStepEdit(id); }
}
// Her adım sadece kendi ilgili alanını gösterir — Cari SADECE "Cari Ödemesi"nde, Personel SADECE
// "Personel Ödemesi"nde görünür (yanlış kayıt ihtimalini azaltma amacı).
function accApplyStepEdit(id){
  if(document.getElementById('editType'+id).value!=='gider') return;
  var step=document.getElementById('editStep'+id).value;
  var contactBox=document.getElementById('editContactLabel'+id);
  contactBox.style.display=(step==='cari')?'':'none';
  contactBox.querySelector('select').required=(step==='cari');
  var persBox=document.getElementById('editPersLabel'+id);
  persBox.style.display=(step==='personel')?'':'none';
  persBox.querySelector('select').required=(step==='personel');
  accBuildTurOptions(document.getElementById('editTurSel'+id),step);
  document.getElementById('editTurSel'+id).required=(ACC_TUR_REQUIRED.indexOf(step)!==-1);
  document.getElementById('editDesc'+id).required=(step==='diger');
}
</script>

<style>
body.nav-compact .df-section-title{font-size:var(--df-type-section-size);font-weight:var(--df-type-section-weight);color:var(--df-ink-900);margin:0 0 var(--df-space-3)}
body.nav-compact .df-form-grid-2{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:0 var(--df-space-4)}
body.nav-compact .df-form-span-2{grid-column:1 / -1}
@media(max-width:640px){body.nav-compact .df-form-grid-2{grid-template-columns:1fr}}
</style>

<?php require_once __DIR__.'/layout_bottom.php'; ?>
