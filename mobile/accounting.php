<?php
require_once 'common.php';
require_once __DIR__.'/../accounting_lib.php';
require_once __DIR__.'/../finance_lib.php';
$pdo=db();
$ok=''; $er='';

$month=(int)($_GET['m'] ?? date('m'));
$year=(int)($_GET['y'] ?? date('Y'));

// POST işlemleri (PRG deseni — topx'ten ÖNCE)
if($_SERVER['REQUEST_METHOD']==='POST'){
    // Kayıt düzenleme
    if(isset($_POST['edit_entry'])){
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
            $ok='Kayıt güncellendi.';
        }catch(Throwable $e){ $er=$e->getMessage(); }
        header('Location: accounting.php?m='.$month.'&y='.$year); exit;
    }
    // Kayıt sil
    if(isset($_POST['del_entry'])){
        try{
            if(!can_edit_delete()) throw new Exception('Bu işlem için yetkiniz yok.');
            $res=accounting_entry_delete($pdo, (int)$_POST['del_entry']);
            if($res['ok']) $ok=$res['msg']; else $er=$res['msg'];
        }catch(Throwable $e){ $er=$e->getMessage(); }
        header('Location: accounting.php?m='.$month.'&y='.$year); exit;
    }
    // Yeni kayıt
    if(isset($_POST['save_entry'])){
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
            $accId=(int)($_POST['account_id']??0) ?: null;
            $pid=(int)($_POST['personnel_id']??0) ?: null;
            $pt=trim($_POST['payment_type'] ?? '');
            $contactId=(int)($_POST['contact_id']??0) ?: null;
            $direction = $type==='gelir' ? 'in' : 'out';
            if($amount<=0) throw new Exception('Tutar geçersiz.');
            // FINANCE UX REFACTOR (2026-07-04): sihirbaz sadece gider tarafında aktif.
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
            $pdo->prepare("INSERT INTO finance_movements(contact_id,category_id,direction,amount,vat_mode,vat_rate,vat_amount,account_id,personnel_id,payment_type,status,movement_date,description,movement_type) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,'muhasebe')")
                ->execute([$contactId,$catId,$direction,$amount,$vatMode,$vc['vat_rate'],$vc['vat_amount'],$accId,$pid,$pt,($direction==='in'?'Tahsil Edildi':'Ödendi'),$date,$desc]);
            if($accId){
                $dir=$direction==='in'?'+':'-';
                try{ $pdo->prepare("UPDATE finance_accounts SET current_balance=current_balance{$dir}? WHERE id=?")->execute([$amount,$accId]); }catch(Throwable $e){}
            }
            $ok='Kayıt eklendi.';
        }catch(Throwable $e){ $er=$e->getMessage(); }
        header('Location: accounting.php?m='.$month.'&y='.$year); exit;
    }
}

$sum=acc_summary($pdo,$month,$year);
$net=$sum['gelir']-$sum['gider'];
$cats=acc_categories($pdo);
// GİDER TÜRÜ CONTEXT-AWARE (2026-07-04): "Gider Türü" artık category_id yerine payment_type
// katalogundan geliyor (finance_expense_type_options()) — category_id sadece Gelir tarafında kalır.
$gelirCats=array_filter($cats,function($c){ return $c['type']==='gelir'; });
try{ $accounts=$pdo->query("SELECT id,name FROM finance_accounts WHERE active=1 ORDER BY name")->fetchAll(); }catch(Throwable $e){ $accounts=[]; }
try{ $personnel=$pdo->query("SELECT id,name FROM personnel WHERE COALESCE(active,1)=1 ORDER BY name")->fetchAll(); }catch(Throwable $e){ $personnel=[]; }
try{ $contacts=$pdo->query("SELECT id,name FROM contacts ORDER BY name")->fetchAll(); }catch(Throwable $e){ $contacts=[]; }
try{
    $entries=$pdo->query("SELECT fm.*, fm.movement_date entry_date, IF(fm.direction='in','gelir','gider') type,
        ac.name cat_name,ac.group_name,fa.account_type,p.name pers_name,c.name contact_name
        FROM finance_movements fm
        LEFT JOIN accounting_categories ac ON ac.id=fm.category_id
        LEFT JOIN finance_accounts fa ON fa.id=fm.account_id
        LEFT JOIN personnel p ON p.id=fm.personnel_id
        LEFT JOIN contacts c ON c.id=fm.contact_id
        WHERE fm.movement_type='muhasebe' AND YEAR(fm.movement_date)=$year AND MONTH(fm.movement_date)=$month
        ORDER BY fm.movement_date DESC,fm.id DESC LIMIT 80")->fetchAll();
}catch(Throwable $e){ $entries=[]; }

$prevM=$month===1?12:$month-1; $prevY=$month===1?$year-1:$year;
$nextM=$month===12?1:$month+1; $nextY=$month===12?$year+1:$year;

topx('Muhasebe');
?>
<?php if($ok): ?><?=ds_alert('success',$ok)?><?php endif; ?>
<?php if($er): ?><?=ds_alert('danger',$er)?><?php endif; ?>

<!-- Ay gezinme — web accounting.php (RELEASE 0.9, 2026-07-17) ile aynı desen: ds_button(...,true) df-btn ghost -->
<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;gap:6px">
  <?=ds_button('‹','?m='.$prevM.'&y='.$prevY,'ghost','','',true)?>
  <span style="font-weight:900;font-size:15px"><?=date('F Y',mktime(0,0,0,$month,1,$year))?></span>
  <?=ds_button('›','?m='.$nextM.'&y='.$nextY,'ghost','','',true)?>
</div>

<div class="df-stat-row">
  <div class="df-stat"><span>📈 Gelir</span><strong><?=mm($sum['gelir'])?></strong></div>
  <div class="df-stat"><span>📉 Gider</span><strong><?=mm($sum['gider'])?></strong></div>
</div>
<style>
.df-stat-row{display:grid;grid-template-columns:1fr 1fr;gap:10px}
.df-stat{background:var(--df-surface-sunken,rgba(255,255,255,.06));border-radius:var(--df-radius-md,14px);padding:12px;display:flex;flex-direction:column;gap:4px}
.df-stat span{font-size:12px;color:var(--df-ink-500,#94a3b8)}
.df-stat strong{font-size:18px;font-weight:900}
</style>
<div class="df-panel" style="text-align:center;margin-top:8px">
  <small class="muted">Net</small>
  <div style="font-size:22px;font-weight:900;color:<?=$net>=0?'var(--df-success-ink)':'var(--df-danger-ink)'?>"><?=$net>=0?'+':'-'?><?=mm(abs($net))?></div>
</div>

<details class="df-panel" style="margin-top:12px">
  <summary style="font-weight:900;cursor:pointer"><?=ds_icon('plus',14)?> Yeni Kayıt</summary>
  <form method="post" style="margin-top:10px">
    <label style="color:#94a3b8;font-size:12px">Tür</label>
    <select name="type" id="mtype" onchange="mToggleWizard()">
      <option value="gider">📉 Gider</option>
      <option value="gelir">📈 Gelir</option>
    </select>
    <div id="mWizardBox">
    <label style="color:#94a3b8;font-size:12px">Ne kaydediyorsun?</label>
    <select name="record_step" id="mStep" onchange="mApplyStep()">
      <?php foreach(finance_record_type_options() as $key=>$o): ?><option value="<?=$key?>"><?=$o['icon']?> <?=h($o['label'])?></option><?php endforeach; ?>
    </select>
    </div>
    <label style="color:#94a3b8;font-size:12px">Tarih</label>
    <input type="date" name="entry_date" value="<?=date('Y-m-d')?>">
    <div id="mCatBox">
    <label style="color:#94a3b8;font-size:12px">Kategori</label>
    <select name="category_id" id="mcats">
      <option value="">— Seç —</option>
      <?php foreach($gelirCats as $c): ?>
      <option value="<?=(int)$c['id']?>"><?=h($c['group_name'])?> — <?=h($c['name'])?></option>
      <?php endforeach; ?>
    </select>
    </div>
    <label style="color:#94a3b8;font-size:12px">Tutar (₺)</label>
    <input type="number" step="0.01" min="0.01" name="amount" id="mAmt" required placeholder="0,00" oninput="mCalcVat()">
    <label style="color:#94a3b8;font-size:12px">KDV Durumu</label>
    <select name="vat_mode" id="mVatMode" onchange="mToggleVat()">
      <option value="yok">KDV Yok / Belirtilmedi</option>
      <option value="dahil">KDV Dahil (girilen tutar toplam)</option>
      <option value="haric">KDV Hariç (girilen tutar KDV'siz taban)</option>
    </select>
    <div id="mVatRateWrap" style="display:none">
      <label style="color:#94a3b8;font-size:12px">KDV Oranı</label>
      <select name="vat_rate" id="mVatRate" onchange="mCalcVat()">
        <?php foreach(acc_vat_rates() as $vr): ?>
        <option value="<?=$vr?>" <?=$vr===20?'selected':''?>>%<?=$vr?></option>
        <?php endforeach; ?>
      </select>
      <div id="mVatPreview" class="small" style="margin:-6px 0 10px"></div>
    </div>
    <label style="color:#94a3b8;font-size:12px">Açıklama</label>
    <input name="description" id="mDesc" placeholder="İsteğe bağlı">
    <label style="color:#94a3b8;font-size:12px">Hesap</label>
    <select name="account_id">
      <option value="">— Seçme —</option>
      <?php foreach($accounts as $a): ?><option value="<?=(int)$a['id']?>"><?=h($a['name'])?></option><?php endforeach; ?>
    </select>
    <div id="mContactBox" style="display:none">
    <label style="color:#94a3b8;font-size:12px">Cari</label>
    <select name="contact_id">
      <option value="">— Cari seçilmedi —</option>
      <?php foreach($contacts as $c): ?><option value="<?=(int)$c['id']?>"><?=h($c['name'])?></option><?php endforeach; ?>
    </select>
    </div>
    <div id="mPersBox" style="display:none">
    <label style="color:#94a3b8;font-size:12px">Personel</label>
    <select name="personnel_id">
      <option value="">— Yok —</option>
      <?php foreach($personnel as $p): ?><option value="<?=(int)$p['id']?>"><?=h($p['name'])?></option><?php endforeach; ?>
    </select>
    </div>
    <!-- GİDER TÜRÜ CONTEXT-AWARE (2026-07-04): önceden sadece sabit bir liste sunan bu alan artık
         TÜM adımlara özel (finance_expense_type_options()), mApplyStep() ile yeniden kurulur. -->
    <div id="mTurBox" style="display:none">
    <label style="color:#94a3b8;font-size:12px">Gider Türü</label>
    <select name="payment_type" id="mTurSel">
      <option value="">— Seç —</option>
    </select>
    </div>
    <button class="df-btn df-btn--primary df-btn--lg" name="save_entry" value="1" style="width:100%;margin-top:8px"><?=ds_icon('check',16)?> Kaydet</button>
  </form>
</details>

<script>
function mToggleVat(){
  var m=document.getElementById('mVatMode').value;
  document.getElementById('mVatRateWrap').style.display=(m==='yok')?'none':'block';
  mCalcVat();
}
function mCalcVat(){
  var m=document.getElementById('mVatMode').value;
  var amt=parseFloat(document.getElementById('mAmt').value)||0;
  var rate=parseFloat(document.getElementById('mVatRate').value)||0;
  var out=document.getElementById('mVatPreview');
  if(m==='haric'){ var vat=amt*rate/100; var total=amt+vat;
    out.textContent='KDV: '+vat.toLocaleString('tr-TR',{minimumFractionDigits:2})+' ₺ · Toplam: '+total.toLocaleString('tr-TR',{minimumFractionDigits:2})+' ₺'; }
  else if(m==='dahil'){ var vat=amt-(amt/(1+rate/100));
    out.textContent='İçindeki KDV: '+vat.toLocaleString('tr-TR',{minimumFractionDigits:2})+' ₺ (toplam değişmez)'; }
  else out.textContent='';
}
function mToggleVatEdit(id){
  var m=document.getElementById('meditVatMode'+id).value;
  document.getElementById('meditVatRateWrap'+id).style.display=(m==='yok')?'none':'block';
}
// GİDER TÜRÜ CONTEXT-AWARE (2026-07-04): finance_lib.php'deki finance_expense_type_options() ile
// BİREBİR aynı katalog (web+mobil tek kaynak).
var M_TUR_CATALOG = <?=json_encode(finance_expense_type_options())?>;
var M_TUR_REQUIRED = <?=json_encode(finance_expense_type_required_steps())?>;
function mBuildTurOptions(selectEl,step){
  var cur=selectEl.value || selectEl.dataset.current || '';
  var opts=M_TUR_CATALOG[step] || [];
  selectEl.innerHTML='<option value="">— Seç —</option>';
  for(var i=0;i<opts.length;i++){
    var el=document.createElement('option');
    el.value=opts[i].v; el.textContent=opts[i].t;
    if(opts[i].v===cur) el.selected=true;
    selectEl.appendChild(el);
  }
  selectEl.dataset.current='';
}
// FINANCE UX REFACTOR (2026-07-04): "Ne kaydediyorsun?" sihirbazı sadece Gider tarafında aktif.
function mToggleWizard(){
  var gider=document.getElementById('mtype').value==='gider';
  document.getElementById('mWizardBox').style.display=gider?'':'none';
  document.getElementById('mCatBox').style.display=gider?'none':'';
  document.getElementById('mcats').required=false;
  document.getElementById('mTurBox').style.display=gider?'':'none';
  if(!gider){
    document.getElementById('mPersBox').style.display='none';
    document.getElementById('mPersBox').querySelector('select').required=false;
    document.getElementById('mContactBox').style.display='';
    document.getElementById('mDesc').required=false;
    document.getElementById('mTurSel').required=false;
  } else { mApplyStep(); }
}
// Her adım sadece kendi ilgili alanını gösterir (yanlış kayıt ihtimalini azaltma amacı) — Cari
// alanı SADECE "Cari Ödemesi" adımında, Personel SADECE "Personel Ödemesi" adımında görünür.
function mApplyStep(){
  if(document.getElementById('mtype').value!=='gider') return;
  var step=document.getElementById('mStep').value;
  var contactBox=document.getElementById('mContactBox');
  contactBox.style.display=(step==='cari')?'':'none';
  contactBox.querySelector('select').required=(step==='cari');
  var persBox=document.getElementById('mPersBox');
  persBox.style.display=(step==='personel')?'':'none';
  persBox.querySelector('select').required=(step==='personel');
  mBuildTurOptions(document.getElementById('mTurSel'),step);
  document.getElementById('mTurSel').required=(M_TUR_REQUIRED.indexOf(step)!==-1);
  document.getElementById('mDesc').required=(step==='diger');
}
mToggleWizard();
function mToggleWizardEdit(id){
  var box=document.getElementById('meditWizardBox'+id); if(!box) return;
  var gider=document.getElementById('medit'+id).value==='gider';
  box.style.display=gider?'':'none';
  document.getElementById('meditCatBox'+id).style.display=gider?'none':'';
  document.getElementById('meditcats'+id).required=false;
  document.getElementById('meditTurBox'+id).style.display=gider?'':'none';
  if(!gider){
    document.getElementById('meditPersBox'+id).style.display='none';
    document.getElementById('meditPersBox'+id).querySelector('select').required=false;
    document.getElementById('meditContactBox'+id).style.display='';
    document.getElementById('meditDesc'+id).required=false;
    document.getElementById('meditTurSel'+id).required=false;
  } else { mApplyStepEdit(id); }
}
// Her adım sadece kendi ilgili alanını gösterir — Cari SADECE "Cari Ödemesi"nde, Personel SADECE
// "Personel Ödemesi"nde görünür (yanlış kayıt ihtimalini azaltma amacı).
function mApplyStepEdit(id){
  if(document.getElementById('medit'+id).value!=='gider') return;
  var step=document.getElementById('meditStep'+id).value;
  var contactBox=document.getElementById('meditContactBox'+id);
  contactBox.style.display=(step==='cari')?'':'none';
  contactBox.querySelector('select').required=(step==='cari');
  var persBox=document.getElementById('meditPersBox'+id);
  persBox.style.display=(step==='personel')?'':'none';
  persBox.querySelector('select').required=(step==='personel');
  mBuildTurOptions(document.getElementById('meditTurSel'+id),step);
  document.getElementById('meditTurSel'+id).required=(M_TUR_REQUIRED.indexOf(step)!==-1);
  document.getElementById('meditDesc'+id).required=(step==='diger');
}
</script>

<div class="df-panel" style="margin-top:12px">
  <b><?=ds_icon('info',16)?> <?=date('F Y',mktime(0,0,0,$month,1,$year))?> Kayıtları</b>
  <?php if(!$entries): ?><div style="margin-top:8px"><?php ds_empty_state('Bu ay kayıt yok.'); ?></div><?php endif; ?>
  <?php foreach($entries as $e):
    $ig=$e['type']==='gider'; $tc=$ig?'var(--df-danger-ink)':'var(--df-success-ink)';
  ?>
  <details class="df-panel" style="margin:10px 0;padding:0">
    <summary style="padding:10px;cursor:pointer;font-weight:900;display:flex;justify-content:space-between;align-items:center">
      <div>
        <b style="font-size:14px"><?=h($e['cat_name'] ?: 'Kategorisiz')?></b>
        <div style="font-size:11px;color:#94a3b8;margin-top:2px">
          <?=h(date('d.m.Y',strtotime($e['entry_date'])))?>
          <?php if($e['contact_name']): ?> · 🤝 <?=h($e['contact_name'])?><?php endif; ?>
          <?php if($e['pers_name']): ?> · <?=h($e['pers_name'])?><?php endif; ?>
        </div>
      </div>
      <div style="text-align:right;flex:0 0 auto;margin-left:8px">
        <b style="color:<?=$tc?>"><?=$ig?'-':'+'?><?=mm($e['amount'])?></b>
        <?php if(($e['vat_mode']??'yok')!=='yok' && $e['vat_rate']): ?>
        <div style="font-size:10px;color:#64748b;margin-top:2px">KDV %<?=(int)$e['vat_rate']?> <?=$e['vat_mode']==='dahil'?'dahil':'hariç'?></div>
        <?php endif; ?>
      </div>
    </summary>
    <?php if($e['description']): ?><div style="font-size:12px;color:#cbd5e1;padding:0 10px;margin-bottom:6px"><?=h($e['description'])?></div><?php endif; ?>
    <?php if(can_edit_delete()): ?>
    <details style="margin:8px 10px 0;padding:8px 0;border-top:1px solid rgba(255,255,255,.08)">
      <summary style="cursor:pointer;font-weight:900;color:var(--df-success-ink)"><?=ds_icon('edit',14)?> Düzenle</summary>
      <form method="post" style="margin-top:10px">
        <input type="hidden" name="id" value="<?=(int)$e['id']?>">
        <label style="color:#94a3b8;font-size:12px">Tür</label>
        <select name="type" id="medit<?=(int)$e['id']?>" onchange="mToggleWizardEdit(<?=(int)$e['id']?>)">
          <option value="gider" <?=$e['type']==='gider'?'selected':''?>>📉 Gider</option>
          <option value="gelir" <?=$e['type']==='gelir'?'selected':''?>>📈 Gelir</option>
        </select>
        <?php $eStep=finance_record_type_info($e, $e['group_name']??null, $e['account_type']??null); ?>
        <div id="meditWizardBox<?=(int)$e['id']?>" style="<?=$ig?'':'display:none'?>">
        <label style="color:#94a3b8;font-size:12px">Ne kaydediyorsun?</label>
        <select name="record_step" id="meditStep<?=(int)$e['id']?>" onchange="mApplyStepEdit(<?=(int)$e['id']?>)">
          <?php foreach(finance_record_type_options() as $key=>$o): ?><option value="<?=$key?>" <?=$eStep===$key?'selected':''?>><?=$o['icon']?> <?=h($o['label'])?></option><?php endforeach; ?>
        </select>
        </div>
        <label style="color:#94a3b8;font-size:12px">Tarih</label>
        <input type="date" name="entry_date" value="<?=h($e['entry_date'])?>">
        <div id="meditCatBox<?=(int)$e['id']?>" style="<?=$ig?'display:none':''?>">
        <label style="color:#94a3b8;font-size:12px">Kategori</label>
        <select name="category_id" id="meditcats<?=(int)$e['id']?>">
          <option value="">— Seç —</option>
          <?php foreach($gelirCats as $c): ?>
          <option value="<?=(int)$c['id']?>" <?=$e['category_id']==$c['id']?'selected':''?>><?=h($c['group_name'])?> — <?=h($c['name'])?></option>
          <?php endforeach; ?>
        </select>
        </div>
        <label style="color:#94a3b8;font-size:12px">Tutar (₺)</label>
        <input type="number" step="0.01" min="0.01" name="amount" required value="<?=h(str_replace('.',',',$e['amount']))?>">
        <label style="color:#94a3b8;font-size:12px">KDV Durumu</label>
        <select name="vat_mode" id="meditVatMode<?=(int)$e['id']?>" onchange="mToggleVatEdit(<?=(int)$e['id']?>)">
          <option value="yok" <?=($e['vat_mode']??'yok')==='yok'?'selected':''?>>KDV Yok / Belirtilmedi</option>
          <option value="dahil" <?=($e['vat_mode']??'')==='dahil'?'selected':''?>>KDV Dahil</option>
          <option value="haric" <?=($e['vat_mode']??'')==='haric'?'selected':''?>>KDV Hariç</option>
        </select>
        <div id="meditVatRateWrap<?=(int)$e['id']?>" style="display:<?=in_array($e['vat_mode']??'yok',['dahil','haric'],true)?'block':'none'?>">
          <label style="color:#94a3b8;font-size:12px">KDV Oranı</label>
          <select name="vat_rate">
            <?php foreach(acc_vat_rates() as $vr): ?>
            <option value="<?=$vr?>" <?=(int)($e['vat_rate']??20)===$vr?'selected':''?>>%<?=$vr?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <label style="color:#94a3b8;font-size:12px">Açıklama</label>
        <input name="description" id="meditDesc<?=(int)$e['id']?>" value="<?=h($e['description'] ?? '')?>">
        <label style="color:#94a3b8;font-size:12px">Hesap</label>
        <select name="account_id">
          <option value="">— Seçme —</option>
          <?php foreach($accounts as $a): ?><option value="<?=(int)$a['id']?>" <?=$e['account_id']==$a['id']?'selected':''?>><?=h($a['name'])?></option><?php endforeach; ?>
        </select>
        <div id="meditContactBox<?=(int)$e['id']?>" style="<?=($ig && $eStep!=='cari')?'display:none':''?>">
        <label style="color:#94a3b8;font-size:12px">Cari</label>
        <select name="contact_id">
          <option value="">— Cari seçilmedi —</option>
          <?php foreach($contacts as $c): ?><option value="<?=(int)$c['id']?>" <?=$e['contact_id']==$c['id']?'selected':''?>><?=h($c['name'])?></option><?php endforeach; ?>
        </select>
        </div>
        <div id="meditPersBox<?=(int)$e['id']?>" style="<?=($ig && $eStep!=='personel')?'display:none':''?>">
        <label style="color:#94a3b8;font-size:12px">Personel</label>
        <select name="personnel_id">
          <option value="">— Yok —</option>
          <?php foreach($personnel as $p): ?><option value="<?=(int)$p['id']?>" <?=$e['personnel_id']==$p['id']?'selected':''?>><?=h($p['name'])?></option><?php endforeach; ?>
        </select>
        </div>
        <!-- GİDER TÜRÜ CONTEXT-AWARE (2026-07-04): payment_type-bağlı "Gider Türü" — seçenekleri
             mApplyStepEdit() ile adıma özel yeniden oluşturulur. -->
        <div id="meditTurBox<?=(int)$e['id']?>" style="<?=$ig?'':'display:none'?>">
        <label style="color:#94a3b8;font-size:12px">Gider Türü</label>
        <select name="payment_type" id="meditTurSel<?=(int)$e['id']?>" data-current="<?=h($e['payment_type'] ?? '')?>">
          <option value="">— Seç —</option>
        </select>
        </div>
        <button class="df-btn df-btn--primary df-btn--lg" name="edit_entry" value="1" style="width:100%;margin-top:8px"><?=ds_icon('check',16)?> Kaydet</button>
      </form>
    </details>
    <script>mToggleWizardEdit(<?=(int)$e['id']?>);</script>
    <form method="post" style="margin:8px 10px 0 0">
      <button name="del_entry" value="<?=(int)$e['id']?>" class="df-btn df-btn--danger" style="width:100%;margin-top:8px" onclick="return confirm('Silinsin mi?')"><?=ds_icon('trash',14)?> Sil</button>
    </form>
    <?php endif; ?>
  </details>
  <?php endforeach; ?>
</div>
<?php botx(); ?>
