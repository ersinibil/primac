<?php
require_once __DIR__.'/boot.php';
require_login();
require_once __DIR__.'/share_lib.php';
$pdo=db();
$me=(int)($_SESSION['user']['id']??0);
$meName=$_SESSION['user']['name']??$_SESSION['user']['username']??'';
$error=''; $ok='';

if(!function_exists('next_quote_no')){ function next_quote_no(){ return 'TKL-'.date('Y').'-'.str_pad((string)random_int(1,999999),6,'0',STR_PAD_LEFT); } }

// Tablo güvencesi
try{ $pdo->query("SELECT 1 FROM quotes LIMIT 1"); }
catch(Throwable $e){
  try{
    $pdo->exec("CREATE TABLE IF NOT EXISTS quotes(id INT AUTO_INCREMENT PRIMARY KEY,quote_no VARCHAR(40),customer_id INT NULL,customer_name VARCHAR(180),quote_date DATE NULL,valid_until DATE NULL,vat_rate DECIMAL(5,2) DEFAULT 20,subtotal DECIMAL(14,2) DEFAULT 0,vat_amount DECIMAL(14,2) DEFAULT 0,total DECIMAL(14,2) DEFAULT 0,notes TEXT,status VARCHAR(20) DEFAULT 'Taslak',created_by INT NULL,created_by_name VARCHAR(160),created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $pdo->exec("CREATE TABLE IF NOT EXISTS quote_items(id INT AUTO_INCREMENT PRIMARY KEY,quote_id INT NOT NULL,name VARCHAR(255),qty DECIMAL(12,3) DEFAULT 1,unit_price DECIMAL(14,2) DEFAULT 0,line_total DECIMAL(14,2) DEFAULT 0) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  }catch(Throwable $e2){}
}

// Yeni teklif
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['save_quote'])){
  try{
    $cid=(int)($_POST['customer_id']??0)?:null;
    $cname=trim($_POST['customer_name']??'');
    if($cid && $cname===''){ $r=$pdo->prepare("SELECT name FROM contacts WHERE id=?"); $r->execute([$cid]); $cname=$r->fetch()['name']??''; }
    $vat=(float)str_replace(',','.',$_POST['vat_rate']??'20');
    $names=$_POST['item_name']??[]; $qtys=$_POST['item_qty']??[]; $prices=$_POST['item_price']??[];
    $sub=0; $lines=[];
    for($i=0;$i<count($names);$i++){
      $nm=trim($names[$i]); if($nm==='') continue;
      $q=(float)str_replace(',','.',$qtys[$i]??'0'); $pr=(float)str_replace(',','.',$prices[$i]??'0');
      $lt=$q*$pr; $sub+=$lt; $lines[]=[$nm,$q,$pr,$lt];
    }
    if(!$lines) throw new Exception('En az bir kalem girin.');
    $firm=in_array($_POST['firm']??'',['ACANS','PRIMAC'],true)?$_POST['firm']:null;
    $vatAmt=$sub*$vat/100; $tot=$sub+$vatAmt; $no=next_quote_no();
    try{ $pdo->exec("ALTER TABLE quotes ADD COLUMN firm VARCHAR(20) DEFAULT NULL"); }catch(Throwable $e){}
    $pdo->prepare("INSERT INTO quotes(quote_no,firm,customer_id,customer_name,quote_date,valid_until,vat_rate,subtotal,vat_amount,total,notes,status,created_by,created_by_name) VALUES(?,?,?,?,?,?,?,?,?,?,?,'Taslak',?,?)")
      ->execute([$no,$firm,$cid,$cname,date('Y-m-d'),($_POST['valid_until']??'')?:null,$vat,$sub,$vatAmt,$tot,trim($_POST['notes']??''),$me,$meName]);
    $qid=(int)$pdo->lastInsertId();
    $ins=$pdo->prepare("INSERT INTO quote_items(quote_id,name,qty,unit_price,line_total) VALUES(?,?,?,?,?)");
    foreach($lines as $l){ $ins->execute([$qid,$l[0],$l[1],$l[2],$l[3]]); }
    if(function_exists('activity_log')) activity_log('Teklif','Oluşturma',$no.($cname?' · '.$cname:''),'','quote',$qid,'teklif.php?id='.$qid,'📄');
    header('Location: teklif.php?id='.$qid); exit;
  }catch(Throwable $e){ $error=$e->getMessage(); }
}
// Durum
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['set_qstatus']) && (int)($_POST['qid']??0)){
  try{ $pdo->prepare("UPDATE quotes SET status=? WHERE id=?")->execute([$_POST['status'],(int)$_POST['qid']]); }catch(Throwable $e){}
  header('Location: teklif.php?id='.(int)$_POST['qid']); exit;
}

$id=(int)($_GET['id']??0);
$new=!empty($_GET['new']);
require_once __DIR__.'/layout_top.php';

/* ---------- GÖRÜNÜM ---------- */
if($id){
  $q=null; try{ $s=$pdo->prepare("SELECT * FROM quotes WHERE id=?"); $s->execute([$id]); $q=$s->fetch(); }catch(Throwable $e){}
  if(!$q){ echo '<div class="alert">Teklif bulunamadı.</div>'; require __DIR__.'/layout_bottom.php'; exit; }
  $items=[]; try{ $it=$pdo->prepare("SELECT * FROM quote_items WHERE quote_id=? ORDER BY id"); $it->execute([$id]); $items=$it->fetchAll(); }catch(Throwable $e){}
  $cphone=preg_replace('/\D/','',$q['customer_id']?($pdo->query("SELECT phone FROM contacts WHERE id=".(int)$q['customer_id'])->fetch()['phone']??''):'');
  $fi=!empty($q['firm'])?firm_info($q['firm']):null;
  $col=$fi?$fi['c']:'#1d4ed8'; $col2=$fi?$fi['c2']:'#0b1f3a';
?>
<style>@media print{ body *{visibility:hidden!important} #repArea,#repArea *{visibility:visible!important} #repArea{position:absolute;left:0;top:0;width:100%} #repArea>div{min-height:auto!important;border:none!important;border-radius:0!important} .noprint{display:none!important} @page{size:A4;margin:12mm} }</style>
<div class="panel-head"><h1>Teklif <?=h($q['quote_no'])?></h1><a class="btn secondary" href="teklif.php">Liste</a></div>

<div id="repArea" style="max-width:780px;margin:0 auto">
  <div style="background:#fff;color:#111;font-family:Arial,Helvetica,sans-serif;border:1px solid #e5e7eb;border-radius:10px;overflow:hidden;display:flex;flex-direction:column;min-height:1131px">
    <div style="background:<?=$col?>;padding:18px 24px;display:flex;justify-content:space-between;align-items:center">
      <?php if($fi): ?><div style="background:#fff;border-radius:10px;padding:8px 14px;display:inline-block"><img src="<?=h($fi['logo'])?>" alt="logo" style="height:48px;object-fit:contain;display:block"></div><?php else: ?><div style="color:#fff;font-weight:700;font-size:15px">ACANS OTS</div><?php endif; ?>
      <div style="text-align:right;color:#fff">
        <div style="font-size:26px;font-weight:900;letter-spacing:2px">TEKLİF</div>
        <div style="font-size:12px;opacity:.95;margin-top:2px"><?=h($q['quote_no'])?></div>
      </div>
    </div>
    <div style="height:5px;background:<?=$col2?>"></div>

    <div style="padding:24px;flex:1">
      <div style="display:flex;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:18px">
        <div><div style="color:<?=$col?>;font-size:11px;letter-spacing:.06em;font-weight:800">SAYIN</div><div style="font-size:18px;font-weight:700"><?=h($q['customer_name']?:'—')?></div></div>
        <div style="text-align:right;color:#555;font-size:13px">
          <div><b>Tarih:</b> <?=h($q['quote_date'])?></div>
          <?php if($q['valid_until']): ?><div><b>Geçerlilik:</b> <?=h($q['valid_until'])?></div><?php endif; ?>
          <div><b>Durum:</b> <?=h($q['status'])?></div>
        </div>
      </div>
      <table style="width:100%;border-collapse:collapse;font-size:13px;margin-bottom:16px">
        <tr style="background:<?=$col?>;color:#fff;text-align:left"><th style="padding:10px 9px;border-radius:6px 0 0 0">Kalem</th><th style="padding:10px 9px;text-align:right">Adet</th><th style="padding:10px 9px;text-align:right">Birim Fiyat</th><th style="padding:10px 9px;text-align:right;border-radius:0 6px 0 0">Tutar</th></tr>
        <?php foreach($items as $i=>$it): ?>
        <tr style="background:<?=$i%2?'#fafafa':'#fff'?>;border-bottom:1px solid #eee">
          <td style="padding:9px"><?=h($it['name'])?></td>
          <td style="padding:9px;text-align:right"><?=rtrim(rtrim(number_format((float)$it['qty'],3,',','.'),'0'),',')?></td>
          <td style="padding:9px;text-align:right"><?=money($it['unit_price'])?></td>
          <td style="padding:9px;text-align:right;font-weight:600"><?=money($it['line_total'])?></td>
        </tr>
        <?php endforeach; ?>
      </table>
      <div style="margin-left:auto;width:290px;font-size:14px">
        <div style="display:flex;justify-content:space-between;padding:4px 0"><span style="color:#555">Ara Toplam</span><b><?=money($q['subtotal'])?></b></div>
        <div style="display:flex;justify-content:space-between;padding:4px 0;border-bottom:1px solid #eee"><span style="color:#555">KDV (%<?=rtrim(rtrim(number_format((float)$q['vat_rate'],2,',','.'),'0'),',')?>)</span><b><?=money($q['vat_amount'])?></b></div>
        <div style="display:flex;justify-content:space-between;align-items:center;margin-top:8px;background:<?=$col?>;color:#fff;padding:10px 12px;border-radius:8px;font-size:17px"><b>GENEL TOPLAM</b><b><?=money($q['total'])?></b></div>
      </div>
      <?php if($q['notes']): ?><div style="margin-top:20px;font-size:13px;color:#333;background:#f8f9fb;border-left:4px solid <?=$col?>;padding:10px 14px;border-radius:0 6px 6px 0"><b style="color:<?=$col?>">Not</b><br><?=nl2br(h($q['notes']))?></div><?php endif; ?>
    </div>

    <div style="background:<?=$col2?>;color:#fff;padding:12px 24px;text-align:center;font-size:12px">
      <?php if($fi): ?><b><?=h($fi['name'])?></b> &nbsp;·&nbsp; 🌐 <?=h($fi['web'])?><?php else: ?>ACANS OTS — Online Takip Sistemi<?php endif; ?>
    </div>
  </div>
</div>

<section class="panel noprint" style="max-width:780px">
  <b>📤 Teklifi gönder / yazdır</b>
  <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:8px">
    <button onclick="shareReportPDF(this)" class="btn" style="background:#16a34a">📄 PDF Paylaş</button>
    <button onclick="window.print()" class="btn" style="background:#475569">🖨 Yazdır (A4)</button>
  </div>
  <?php
    $txt="📄 Teklif ".$q['quote_no']."\nMüşteri: ".$q['customer_name']."\nTutar: ".money($q['total']).($q['valid_until']?"\nGeçerlilik: ".$q['valid_until']:'');
    echo share_buttons($txt,$cphone,'Teklif '.$q['quote_no']);
  ?>
</section>

<section class="panel noprint" style="max-width:620px">
  <b>Durum</b>
  <div style="display:flex;gap:6px;margin-top:8px;flex-wrap:wrap">
    <?php foreach(['Taslak','Gönderildi','Kabul','Red'] as $st): ?>
    <form method="post" style="margin:0"><input type="hidden" name="qid" value="<?=$id?>"><input type="hidden" name="status" value="<?=$st?>"><button class="btn" name="set_qstatus" value="1" style="background:<?=$q['status']===$st?'#2563eb':'#334155'?>"><?=$st?></button></form>
    <?php endforeach; ?>
  </div>
</section>

<script>window.ACANS_REPORT_NAME='teklif_<?=h($q['quote_no'])?>';window.ACANS_PDF_BG='#ffffff';window.ACANS_PDF_FG='#111111';</script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="report_share.js"></script>
<?php require __DIR__.'/layout_bottom.php'; exit; }

/* ---------- YENİ ---------- */
if($new){
  $cs=$pdo->query("SELECT id,name FROM contacts ORDER BY name")->fetchAll();
?>
<div class="panel-head"><h1>Yeni Teklif</h1><a class="btn secondary" href="teklif.php">Liste</a></div>
<?php if($error): ?><div class="alert"><?=h($error)?></div><?php endif; ?>
<section class="panel" style="max-width:620px">
<form method="post">
  <label>Teklifi veren firma (opsiyonel — logo/iletişim ekler)</label>
  <select name="firm" style="width:100%"><option value="">— Firma yok (sade) —</option><option value="ACANS">ACANS Reklam</option><option value="PRIMAC">PRIMAC</option></select>
  <label style="margin-top:6px">Müşteri</label>
  <select name="customer_id" style="width:100%"><option value="">— Cari seç (veya alta yaz) —</option><?php foreach($cs as $c): ?><option value="<?=$c['id']?>"><?=h($c['name'])?></option><?php endforeach; ?></select>
  <input name="customer_name" placeholder="veya müşteri adı yaz" style="width:100%;margin-top:6px">
  <div style="display:flex;gap:10px;margin-top:6px">
    <div style="flex:1"><label>Geçerlilik</label><input type="date" name="valid_until" style="width:100%"></div>
    <div style="flex:1"><label>KDV %</label><input name="vat_rate" value="20" style="width:100%"></div>
  </div>
  <label style="margin-top:10px;display:block">Kalemler</label>
  <div id="rows"></div>
  <button type="button" class="btn secondary" onclick="addRow()" style="margin-top:6px">+ Kalem Ekle</button>
  <div class="panel" style="margin-top:10px;background:rgba(34,197,94,.08)">
    <div style="display:flex;justify-content:space-between"><span>Ara Toplam</span><b id="tSub">0,00 ₺</b></div>
    <div style="display:flex;justify-content:space-between"><span>KDV</span><b id="tVat">0,00 ₺</b></div>
    <div style="display:flex;justify-content:space-between;font-size:18px;margin-top:4px"><span><b>Toplam</b></span><b id="tTot" style="color:#22c55e">0,00 ₺</b></div>
  </div>
  <label style="margin-top:8px;display:block">Not</label><textarea name="notes" rows="2" style="width:100%" placeholder="Teslim, ödeme koşulu vb."></textarea>
  <button class="btn" name="save_quote" value="1" style="margin-top:10px">💾 Teklifi Oluştur</button>
</form>
</section>
<script>
function fmt(n){ return n.toLocaleString('tr-TR',{minimumFractionDigits:2,maximumFractionDigits:2})+' ₺'; }
function num(v){ return parseFloat(String(v||'').replace(/\./g,'').replace(',','.'))||0; }
function addRow(nm,q,p){
  var d=document.createElement('div'); d.style.cssText='display:flex;gap:6px;margin-bottom:6px';
  d.innerHTML='<input name="item_name[]" placeholder="Ürün/hizmet" style="flex:2" value="'+(nm||'')+'">'+
    '<input name="item_qty[]" placeholder="Adet" style="flex:1" value="'+(q||'1')+'">'+
    '<input name="item_price[]" placeholder="Birim ₺" style="flex:1" value="'+(p||'')+'">'+
    '<button type="button" onclick="this.parentNode.remove();calc()" class="btn" style="background:#7f1d1d">×</button>';
  document.getElementById('rows').appendChild(d); d.addEventListener('input',calc);
}
function calc(){
  var qs=document.getElementsByName('item_qty[]'), ps=document.getElementsByName('item_price[]'), sub=0;
  for(var i=0;i<qs.length;i++){ sub+=num(qs[i].value)*num(ps[i].value); }
  var vr=num(document.getElementsByName('vat_rate')[0].value), vat=sub*vr/100;
  document.getElementById('tSub').textContent=fmt(sub);
  document.getElementById('tVat').textContent=fmt(vat);
  document.getElementById('tTot').textContent=fmt(sub+vat);
}
document.getElementsByName('vat_rate')[0].addEventListener('input',calc);
addRow(); calc();
</script>
<?php require __DIR__.'/layout_bottom.php'; exit; }

/* ---------- LİSTE ---------- */
?>
<div class="panel-head"><h1>Teklifler</h1><a class="btn" href="teklif.php?new=1">+ Yeni Teklif</a></div>
<section class="panel">
<table>
<thead><tr><th>No</th><th>Müşteri</th><th>Tarih</th><th>Durum</th><th style="text-align:right">Tutar</th></tr></thead>
<tbody>
<?php
try{
  $rows=$pdo->query("SELECT id,quote_no,customer_name,total,status,quote_date FROM quotes ORDER BY id DESC LIMIT 200")->fetchAll();
  if(!$rows) echo '<tr><td colspan="5" class="muted">Henüz teklif yok.</td></tr>';
  foreach($rows as $r){
    echo '<tr style="cursor:pointer" onclick="location.href=\'teklif.php?id='.(int)$r['id'].'\'">';
    echo '<td>'.h($r['quote_no']).'</td><td>'.h($r['customer_name']?:'—').'</td><td>'.h($r['quote_date']).'</td>';
    echo '<td>'.h($r['status']).'</td><td style="text-align:right;font-weight:700">'.money($r['total']).'</td></tr>';
  }
}catch(Throwable $e){ echo '<tr><td colspan="5"><div class="alert">'.h($e->getMessage()).'</div></td></tr>'; }
?>
</tbody>
</table>
</section>
<?php require __DIR__.'/layout_bottom.php'; ?>
