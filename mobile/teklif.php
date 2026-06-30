<?php
require_once 'common.php';
require_once __DIR__.'/../share_lib.php';
$pdo=db();
$me=(int)($_SESSION['user']['id']??0);
$meName=$_SESSION['user']['name']??$_SESSION['user']['username']??'';
$er='';

if(!function_exists('next_quote_no')){ function next_quote_no(){ return 'TKL-'.date('Y').'-'.str_pad((string)random_int(1,999999),6,'0',STR_PAD_LEFT); } }

// Tablo güvencesi (migrate atlanmışsa)
try{ $pdo->query("SELECT 1 FROM quotes LIMIT 1"); }
catch(Throwable $e){
  try{
    $pdo->exec("CREATE TABLE IF NOT EXISTS quotes(id INT AUTO_INCREMENT PRIMARY KEY,quote_no VARCHAR(40),customer_id INT NULL,customer_name VARCHAR(180),quote_date DATE NULL,valid_until DATE NULL,vat_rate DECIMAL(5,2) DEFAULT 20,subtotal DECIMAL(14,2) DEFAULT 0,vat_amount DECIMAL(14,2) DEFAULT 0,total DECIMAL(14,2) DEFAULT 0,notes TEXT,status VARCHAR(20) DEFAULT 'Taslak',created_by INT NULL,created_by_name VARCHAR(160),created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $pdo->exec("CREATE TABLE IF NOT EXISTS quote_items(id INT AUTO_INCREMENT PRIMARY KEY,quote_id INT NOT NULL,name VARCHAR(255),qty DECIMAL(12,3) DEFAULT 1,unit_price DECIMAL(14,2) DEFAULT 0,line_total DECIMAL(14,2) DEFAULT 0) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  }catch(Throwable $e2){}
}

// Yeni teklif kaydet
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
    $vatAmt=$sub*$vat/100; $tot=$sub+$vatAmt; $no=next_quote_no();
    $pdo->prepare("INSERT INTO quotes(quote_no,customer_id,customer_name,quote_date,valid_until,vat_rate,subtotal,vat_amount,total,notes,status,created_by,created_by_name) VALUES(?,?,?,?,?,?,?,?,?,?,'Taslak',?,?)")
      ->execute([$no,$cid,$cname,date('Y-m-d'),($_POST['valid_until']??'')?:null,$vat,$sub,$vatAmt,$tot,trim($_POST['notes']??''),$me,$meName]);
    $qid=(int)$pdo->lastInsertId();
    $ins=$pdo->prepare("INSERT INTO quote_items(quote_id,name,qty,unit_price,line_total) VALUES(?,?,?,?,?)");
    foreach($lines as $l){ $ins->execute([$qid,$l[0],$l[1],$l[2],$l[3]]); }
    try{ if(function_exists('activity_log')) activity_log('Teklif','Oluşturma',$no.($cname?' · '.$cname:''),'','quote',$qid,'teklif.php?id='.$qid,'📄'); }catch(Throwable $e){}
    header('Location: teklif.php?id='.$qid); exit;
  }catch(Throwable $e){ $er=$e->getMessage(); }
}

// Durum güncelle
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['set_qstatus']) && (int)($_POST['qid']??0)){
  try{ $pdo->prepare("UPDATE quotes SET status=? WHERE id=?")->execute([$_POST['status'],(int)$_POST['qid']]); }catch(Throwable $e){}
  header('Location: teklif.php?id='.(int)$_POST['qid']); exit;
}

$id=(int)($_GET['id']??0);
$new=!empty($_GET['new']);

/* ---------- TEKLİF GÖRÜNÜMÜ (PDF/paylaş) ---------- */
if($id){
  $q=null; try{ $s=$pdo->prepare("SELECT * FROM quotes WHERE id=?"); $s->execute([$id]); $q=$s->fetch(); }catch(Throwable $e){}
  if(!$q){ topx('Teklif'); echo '<div class="err">Teklif bulunamadı.</div>'; botx(); exit; }
  $items=[]; try{ $it=$pdo->prepare("SELECT * FROM quote_items WHERE quote_id=? ORDER BY id"); $it->execute([$id]); $items=$it->fetchAll(); }catch(Throwable $e){}
  $cphone=preg_replace('/\D/','',$q['customer_id']?($pdo->query("SELECT phone FROM contacts WHERE id=".(int)$q['customer_id'])->fetch()['phone']??''):'');
  topx('Teklif '.$q['quote_no']);
  ?>
  <div id="repArea">
    <div class="rep">
      <div class="rep-hero" style="background:linear-gradient(135deg,#1d4ed8,#0ea5e9);border-radius:16px;padding:16px;color:#fff;margin-bottom:12px">
        <div style="font-size:20px;font-weight:900">📄 TEKLİF</div>
        <div style="opacity:.9;font-size:13px;margin-top:2px"><?=htmlspecialchars($q['quote_no'])?> · <?=htmlspecialchars($q['quote_date'])?><?=$q['valid_until']?' · Geçerlilik: '.htmlspecialchars($q['valid_until']):''?></div>
      </div>
      <div class="panel" style="margin-bottom:10px">
        <div><b>Müşteri:</b> <?=htmlspecialchars($q['customer_name']?:'—')?></div>
        <div><b>Durum:</b> <?=htmlspecialchars($q['status'])?> · <b>Hazırlayan:</b> <?=htmlspecialchars($q['created_by_name']?:'—')?></div>
      </div>
      <div class="panel" style="overflow:auto;margin-bottom:10px">
        <table style="width:100%;border-collapse:collapse;font-size:13px">
          <tr style="text-align:left;opacity:.7"><th style="padding:6px 4px">Kalem</th><th style="padding:6px 4px;text-align:right">Adet</th><th style="padding:6px 4px;text-align:right">Birim</th><th style="padding:6px 4px;text-align:right">Tutar</th></tr>
          <?php foreach($items as $it): ?>
          <tr style="border-top:1px solid rgba(128,128,128,.2)">
            <td style="padding:6px 4px"><?=htmlspecialchars($it['name'])?></td>
            <td style="padding:6px 4px;text-align:right"><?=rtrim(rtrim(number_format((float)$it['qty'],3,',','.'),'0'),',')?></td>
            <td style="padding:6px 4px;text-align:right"><?=mm($it['unit_price'])?></td>
            <td style="padding:6px 4px;text-align:right"><?=mm($it['line_total'])?></td>
          </tr>
          <?php endforeach; ?>
        </table>
      </div>
      <div class="panel" style="margin-bottom:10px">
        <div style="display:flex;justify-content:space-between"><span>Ara Toplam</span><b><?=mm($q['subtotal'])?></b></div>
        <div style="display:flex;justify-content:space-between"><span>KDV (%<?=rtrim(rtrim(number_format((float)$q['vat_rate'],2,',','.'),'0'),',')?>)</span><b><?=mm($q['vat_amount'])?></b></div>
        <div style="display:flex;justify-content:space-between;font-size:17px;margin-top:6px;border-top:1px solid rgba(128,128,128,.25);padding-top:6px"><span><b>GENEL TOPLAM</b></span><b style="color:#22c55e"><?=mm($q['total'])?></b></div>
      </div>
      <?php if($q['notes']): ?><div class="panel" style="margin-bottom:10px"><b>Not:</b><br><?=nl2br(htmlspecialchars($q['notes']))?></div><?php endif; ?>
      <div class="rep-foot" style="text-align:center;opacity:.6;font-size:12px;padding:8px">ACANS OTS — Online Takip Sistemi · Bu teklif otomatik üretilmiştir</div>
    </div>
  </div>

  <div class="panel noprint">
    <b>📤 Teklifi gönder</b>
    <button onclick="shareReportPDF(this)" class="btn" style="display:block;width:100%;background:#16a34a;color:#fff;padding:14px;margin-top:10px">📄 PDF Olarak Paylaş (WhatsApp/Mail)</button>
    <?php
      $txt="📄 Teklif ".$q['quote_no']."\nMüşteri: ".$q['customer_name']."\nTutar: ".mm($q['total']).($q['valid_until']?"\nGeçerlilik: ".$q['valid_until']:'');
      echo share_buttons($txt,$cphone,'Teklif '.$q['quote_no']);
    ?>
  </div>

  <div class="panel noprint">
    <b>Durum</b>
    <div style="display:flex;gap:6px;margin-top:8px;flex-wrap:wrap">
      <?php foreach(['Taslak','Gönderildi','Kabul','Red'] as $st): ?>
      <form method="post" style="flex:1;min-width:46%;margin:0"><input type="hidden" name="qid" value="<?=$id?>"><input type="hidden" name="status" value="<?=$st?>"><button class="btn" name="set_qstatus" value="1" style="width:100%;background:<?=$q['status']===$st?'#2563eb':'#334155'?>;color:#fff;font-size:13px"><?=$st?></button></form>
      <?php endforeach; ?>
    </div>
  </div>
  <a class="btn dark" href="teklif.php" style="display:block;text-align:center;margin-top:8px">← Teklif Listesi</a>

  <script>window.ACANS_REPORT_NAME='teklif_<?=htmlspecialchars($q['quote_no'])?>';</script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
  <script src="../report_share.js"></script>
  <?php botx(); exit;
}

/* ---------- YENİ TEKLİF ---------- */
if($new){
  $cs=$pdo->query("SELECT id,name FROM contacts ORDER BY name")->fetchAll();
  topx('Yeni Teklif');
  if($er) echo '<div class="err">'.htmlspecialchars($er).'</div>';
  ?>
  <div class="panel">
  <form method="post">
    <label>Müşteri</label>
    <select name="customer_id"><option value="">— Cari seç (veya alta yaz) —</option><?php foreach($cs as $c): ?><option value="<?=$c['id']?>"><?=htmlspecialchars($c['name'])?></option><?php endforeach; ?></select>
    <input name="customer_name" placeholder="veya müşteri adı yaz">
    <div style="display:flex;gap:10px">
      <div style="flex:1"><label>Geçerlilik</label><input type="date" name="valid_until"></div>
      <div style="flex:1"><label>KDV %</label><input name="vat_rate" inputmode="decimal" value="20"></div>
    </div>

    <label style="margin-top:10px">Kalemler</label>
    <div id="rows"></div>
    <button type="button" class="btn" onclick="addRow()" style="width:100%;background:#334155;color:#fff;margin-top:6px">+ Kalem Ekle</button>

    <div class="panel" style="margin-top:10px;background:rgba(34,197,94,.08)">
      <div style="display:flex;justify-content:space-between"><span>Ara Toplam</span><b id="tSub">0,00 ₺</b></div>
      <div style="display:flex;justify-content:space-between"><span>KDV</span><b id="tVat">0,00 ₺</b></div>
      <div style="display:flex;justify-content:space-between;font-size:17px;margin-top:4px"><span><b>Toplam</b></span><b id="tTot" style="color:#22c55e">0,00 ₺</b></div>
    </div>

    <label>Not</label><textarea name="notes" rows="2" placeholder="Teslim, ödeme koşulu vb."></textarea>
    <button class="btn dark" name="save_quote" value="1" style="width:100%;padding:14px;margin-top:8px">💾 Teklifi Oluştur</button>
  </form>
  </div>
  <script>
  function fmt(n){ return n.toLocaleString('tr-TR',{minimumFractionDigits:2,maximumFractionDigits:2})+' ₺'; }
  function num(v){ return parseFloat(String(v||'').replace(/\./g,'').replace(',','.'))||0; }
  function addRow(nm,q,p){
    var d=document.createElement('div'); d.className='qrow'; d.style.cssText='display:flex;gap:6px;margin-bottom:6px';
    d.innerHTML='<input name="item_name[]" placeholder="Ürün/hizmet" style="flex:2;margin:0" value="'+(nm||'')+'">'+
      '<input name="item_qty[]" inputmode="decimal" placeholder="Adet" style="flex:1;margin:0" value="'+(q||'1')+'">'+
      '<input name="item_price[]" inputmode="decimal" placeholder="Birim ₺" style="flex:1;margin:0" value="'+(p||'')+'">'+
      '<button type="button" onclick="this.parentNode.remove();calc()" class="btn" style="background:#7f1d1d;color:#fff;padding:0 10px">×</button>';
    document.getElementById('rows').appendChild(d);
    d.addEventListener('input',calc);
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
  <?php botx(); exit;
}

/* ---------- LİSTE ---------- */
topx('Teklifler');
?>
<div class="panel" style="display:flex;gap:8px"><a class="btn dark" href="teklif.php?new=1" style="flex:1;text-align:center">📄 Yeni Teklif</a></div>
<?php
try{
  $rows=$pdo->query("SELECT id,quote_no,customer_name,total,status,quote_date FROM quotes ORDER BY id DESC LIMIT 100")->fetchAll();
  if(!$rows) echo '<div class="panel muted" style="text-align:center">Henüz teklif yok.</div>';
  $stc=['Taslak'=>'#94a3b8','Gönderildi'=>'#2563eb','Kabul'=>'#22c55e','Red'=>'#f87171'];
  foreach($rows as $r){
    echo '<a class="panel" href="teklif.php?id='.(int)$r['id'].'" style="display:block;text-decoration:none;color:inherit;padding:12px">';
    echo '<div style="display:flex;justify-content:space-between"><b>'.htmlspecialchars($r['customer_name']?:'(müşteri yok)').'</b><span style="color:'.($stc[$r['status']]??'#94a3b8').';font-weight:700;font-size:12px">'.htmlspecialchars($r['status']).'</span></div>';
    echo '<small class="muted">'.htmlspecialchars($r['quote_no']).' · '.htmlspecialchars($r['quote_date']).'</small>';
    echo '<div style="text-align:right;font-weight:900;color:#22c55e">'.mm($r['total']).'</div>';
    echo '</a>';
  }
}catch(Throwable $e){ echo '<div class="err">'.htmlspecialchars($e->getMessage()).'</div>'; }
botx();
