<?php
require_once __DIR__.'/layout_top.php';
require_once __DIR__.'/accounting_lib.php';
$pdo=db();

$month=(int)($_GET['m'] ?? date('m'));
$year=(int)($_GET['y'] ?? date('Y'));
$tab=$_GET['tab'] ?? 'kayitlar';
$msg=''; $err='';

// Kayıt sil
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['del_entry']) && is_admin()){
    try{ $pdo->prepare("DELETE FROM accounting_entries WHERE id=?")->execute([(int)$_POST['del_entry']]); $msg='Kayıt silindi.'; }
    catch(Throwable $e){ $err=$e->getMessage(); }
}

// Yeni kayıt
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['save_entry'])){
    try{
        $type=$_POST['type'] ?? 'gider';
        $amount=(float)str_replace(',','.',$_POST['amount'] ?? '0');
        $date=$_POST['entry_date'] ?? date('Y-m-d');
        $catId=(int)($_POST['category_id']??0) ?: null;
        $desc=trim($_POST['description'] ?? '');
        $refNo=trim($_POST['reference_no'] ?? '');
        $accId=(int)($_POST['account_id']??0) ?: null;
        $pid=(int)($_POST['personnel_id']??0) ?: null;
        $pt=trim($_POST['payment_type'] ?? '');
        if($amount<=0) throw new Exception('Tutar sıfırdan büyük olmalı.');
        $pdo->prepare("INSERT INTO accounting_entries(entry_date,type,category_id,amount,description,reference_no,account_id,personnel_id,payment_type,created_by)
            VALUES(?,?,?,?,?,?,?,?,?,?)")
            ->execute([$date,$type,$catId,$amount,$desc,$refNo,$accId,$pid,$pt,$_SESSION['user']['id'] ?? null]);
        // Kasa bakiyesi güncelle
        if($accId){
            $dir=$type==='gelir'?'+':'-';
            try{ $pdo->prepare("UPDATE finance_accounts SET current_balance=current_balance{$dir}? WHERE id=?")->execute([$amount,$accId]); }catch(Throwable $e){}
        }
        $msg='Kayıt eklendi.';
    }catch(Throwable $e){ $err=$e->getMessage(); }
}

$sum=acc_summary($pdo,$month,$year);
$net=$sum['gelir']-$sum['gider'];
$cats=acc_categories($pdo);
$giderCats=array_filter($cats,function($c){ return $c['type']==='gider'; });
$gelirCats=array_filter($cats,function($c){ return $c['type']==='gelir'; });
try{ $accounts=$pdo->query("SELECT id,name,account_type FROM finance_accounts WHERE active=1 ORDER BY name")->fetchAll(); }catch(Throwable $e){ $accounts=[]; }
try{ $personnel=$pdo->query("SELECT id,name FROM personnel WHERE COALESCE(active,1)=1 ORDER BY name")->fetchAll(); }catch(Throwable $e){ $personnel=[]; }

// Kayıtlar listesi
$where="WHERE YEAR(ae.entry_date)=$year AND MONTH(ae.entry_date)=$month";
$typeF=$_GET['type'] ?? '';
if($typeF==='gelir'||$typeF==='gider') $where.=" AND ae.type='$typeF'";
$catF=(int)($_GET['cat'] ?? 0);
if($catF) $where.=" AND ae.category_id=$catF";
try{
    $entries=$pdo->query("SELECT ae.*,ac.name cat_name,ac.group_name,fa.name acc_name,p.name pers_name
        FROM accounting_entries ae
        LEFT JOIN accounting_categories ac ON ac.id=ae.category_id
        LEFT JOIN finance_accounts fa ON fa.id=ae.account_id
        LEFT JOIN personnel p ON p.id=ae.personnel_id
        $where ORDER BY ae.entry_date DESC,ae.id DESC LIMIT 200")->fetchAll();
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

<h1>📒 Muhasebe</h1>

<div style="display:flex;align-items:center;gap:10px;margin-bottom:16px">
  <a href="?m=<?=$prevM?>&y=<?=$prevY?>&tab=<?=$tab?>" class="btn secondary" style="padding:8px 14px">‹</a>
  <span style="font-weight:900;font-size:17px"><?=date('F Y',mktime(0,0,0,$month,1,$year))?></span>
  <a href="?m=<?=$nextM?>&y=<?=$nextY?>&tab=<?=$tab?>" class="btn secondary" style="padding:8px 14px">›</a>
  <a href="?m=<?=(int)date('m')?>&y=<?=(int)date('Y')?>&tab=<?=$tab?>" class="btn secondary" style="padding:8px 12px;font-size:12px">Bu Ay</a>
</div>

<div class="acc-summary">
  <div class="acc-card"><div class="num gelir-color"><?=money($sum['gelir'])?></div><small>Gelir</small></div>
  <div class="acc-card"><div class="num gider-color"><?=money($sum['gider'])?></div><small>Gider</small></div>
  <div class="acc-card"><div class="num <?=$net>=0?'net-pos':'net-neg'?>"><?=money(abs($net))?></div><small><?=$net>=0?'Net Kâr':'Net Zarar'?></small></div>
</div>

<?php if($msg): ?><div class="ok"><?=h($msg)?></div><?php endif; ?>
<?php if($err): ?><div class="alert"><?=h($err)?></div><?php endif; ?>

<div class="acc-tabs">
  <a href="?m=<?=$month?>&y=<?=$year?>&tab=kayitlar" class="<?=$tab==='kayitlar'?'active':''?>">📋 Kayıtlar</a>
  <a href="?m=<?=$month?>&y=<?=$year?>&tab=yeni" class="<?=$tab==='yeni'?'active':''?>">➕ Yeni Kayıt</a>
  <a href="?m=<?=$month?>&y=<?=$year?>&tab=personel" class="<?=$tab==='personel'?'active':''?>">👷 Personel</a>
  <a href="?m=<?=$month?>&y=<?=$year?>&tab=ozet" class="<?=$tab==='ozet'?'active':''?>">📊 Özet</a>
  <?php if(is_admin()): ?><a href="accounting_categories.php">⚙ Kategoriler</a><?php endif; ?>
</div>

<?php if($tab==='yeni'): ?>
<section class="panel">
<h2>Yeni Muhasebe Kaydı</h2>
<form method="post" class="form-grid">
<input type="hidden" name="save_entry" value="1">

<label>Tür
<select name="type" id="entryType" onchange="filterCats()">
  <option value="gider">Gider</option>
  <option value="gelir">Gelir</option>
</select>
</label>

<label>Tarih
<input type="date" name="entry_date" value="<?=date('Y-m-d')?>">
</label>

<label>Kategori
<select name="category_id" id="catSel">
  <option value="">— Seç —</option>
  <?php foreach($giderCats as $c): ?>
  <option value="<?=(int)$c['id']?>" data-type="gider">[<?=h($c['group_name'])?>] <?=h($c['name'])?></option>
  <?php endforeach; ?>
  <?php foreach($gelirCats as $c): ?>
  <option value="<?=(int)$c['id']?>" data-type="gelir" style="display:none">[<?=h($c['group_name'])?>] <?=h($c['name'])?></option>
  <?php endforeach; ?>
</select>
</label>

<label>Tutar (₺)
<input type="number" step="0.01" min="0.01" name="amount" required placeholder="0,00">
</label>

<label>Açıklama
<input name="description" placeholder="İsteğe bağlı">
</label>

<label>Belge / Ref No
<input name="reference_no" placeholder="Fatura no, makbuz no vb.">
</label>

<label>Hesap (Kasa/Banka)
<select name="account_id">
  <option value="">— Seçme —</option>
  <?php foreach($accounts as $a): ?>
  <option value="<?=(int)$a['id']?>"><?=h($a['name'])?> (<?=h($a['account_type'])?>)</option>
  <?php endforeach; ?>
</select>
</label>

<label id="persLabel">Personel (Ödeme ise)
<select name="personnel_id">
  <option value="">— Yok —</option>
  <?php foreach($personnel as $p): ?>
  <option value="<?=(int)$p['id']?>"><?=h($p['name'])?></option>
  <?php endforeach; ?>
</select>
</label>

<label id="ptLabel">Ödeme Türü (Personel ise)
<select name="payment_type">
  <option value="">—</option>
  <option value="maas">Maaş</option>
  <option value="avans">Avans</option>
  <option value="prim">Prim / İkramiye</option>
  <option value="sgk">SGK Primi</option>
  <option value="vergi">Vergi</option>
  <option value="diger">Diğer</option>
</select>
</label>

<div class="full">
<button class="btn" type="submit">💾 Kaydet</button>
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
</script>

<?php elseif($tab==='personel'): ?>
<section class="panel">
<h2>Personel Ödemeleri — <?=$year?></h2>
<?php
$py=acc_personnel_summary($pdo,$year);
if(!$py){ echo '<p class="muted">Bu yıl personel ödemesi kaydı yok.</p>'; }
else{
    $byPers=[];
    foreach($py as $r){ $byPers[$r['pers_name']][$r['payment_type']]=(float)$r['total']; }
    echo '<table style="width:100%;border-collapse:collapse">';
    echo '<thead><tr style="background:#f8fafc"><th style="padding:8px;text-align:left">Personel</th><th style="padding:8px">Maaş</th><th style="padding:8px">Avans</th><th style="padding:8px">Prim</th><th style="padding:8px">SGK</th><th style="padding:8px">Toplam</th></tr></thead><tbody>';
    foreach($byPers as $name=>$types){
        $total=array_sum($types);
        echo '<tr style="border-bottom:1px solid #f1f5f9">';
        echo '<td style="padding:8px;font-weight:700">'.h($name).'</td>';
        foreach(['maas','avans','prim','sgk'] as $pt) echo '<td style="padding:8px;text-align:right;font-size:13px">'.($types[$pt]??0?money($types[$pt]):'-').'</td>';
        echo '<td style="padding:8px;text-align:right;font-weight:900">'.money($total).'</td>';
        echo '</tr>';
    }
    echo '</tbody></table>';
}
?>
</section>

<?php elseif($tab==='ozet'): ?>
<section class="panel">
<h2>Grup Özeti — <?=date('F Y',mktime(0,0,0,$month,1,$year))?></h2>
<?php if(!$groups){ echo '<p class="muted">Bu ay kayıt yok.</p>'; }
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
<section class="panel">
<div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin-bottom:12px">
  <a href="?m=<?=$month?>&y=<?=$year?>&tab=kayitlar" class="btn small <?=!$typeF?'secondary':''?>">Tümü</a>
  <a href="?m=<?=$month?>&y=<?=$year?>&tab=kayitlar&type=gider" class="btn small <?=$typeF==='gider'?'':'secondary'?>" style="<?=$typeF==='gider'?'background:#dc2626;color:#fff':''?>">Gider</a>
  <a href="?m=<?=$month?>&y=<?=$year?>&tab=kayitlar&type=gelir" class="btn small <?=$typeF==='gelir'?'':'secondary'?>" style="<?=$typeF==='gelir'?'background:#16a34a;color:#fff':''?>">Gelir</a>
</div>
<?php if(!$entries){ echo '<p class="muted">Bu dönemde kayıt yok.</p>'; }
foreach($entries as $e):
    $isGider=$e['type']==='gider';
    $tColor=$isGider?'#dc2626':'#16a34a';
?>
<div class="acc-entry">
  <div style="flex:1;min-width:0">
    <div style="font-weight:700;font-size:14px"><?=h($e['cat_name'] ?: 'Kategorisiz')?></div>
    <div style="font-size:12px;color:#667085;margin-top:2px">
      <?=h(date('d.m.Y',strtotime($e['entry_date'])))?>
      <?php if($e['pers_name']): ?> · 👷 <?=h($e['pers_name'])?><?php endif; ?>
      <?php if($e['acc_name']): ?> · 🏦 <?=h($e['acc_name'])?><?php endif; ?>
      <?php if($e['reference_no']): ?> · #<?=h($e['reference_no'])?><?php endif; ?>
    </div>
    <?php if($e['description']): ?><div style="font-size:12px;color:#374151;margin-top:2px"><?=h($e['description'])?></div><?php endif; ?>
  </div>
  <div style="text-align:right;flex:0 0 auto;margin-left:10px">
    <div style="font-weight:900;color:<?=$tColor?>;font-size:15px"><?=$isGider?'-':'+' ?><?=money($e['amount'])?></div>
    <?php if(is_admin()): ?>
    <form method="post" style="margin:4px 0 0">
      <button name="del_entry" value="<?=(int)$e['id']?>" class="btn danger" style="padding:3px 8px;font-size:11px" onclick="return confirm('Silinsin mi?')">Sil</button>
    </form>
    <?php endif; ?>
  </div>
</div>
<?php endforeach; ?>
</section>
<?php endif; ?>

<?php require_once __DIR__.'/layout_bottom.php'; ?>
