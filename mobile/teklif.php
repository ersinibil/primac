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
    $firm=in_array($_POST['firm']??'',['ACANS','PRIMAC'],true)?$_POST['firm']:null;
    $vatAmt=$sub*$vat/100; $tot=$sub+$vatAmt; $no=next_quote_no();
    $token=bin2hex(random_bytes(24));
    try{ $pdo->exec("ALTER TABLE quotes ADD COLUMN firm VARCHAR(20) DEFAULT NULL"); }catch(Throwable $e){}
    try{ $pdo->exec("ALTER TABLE quotes ADD COLUMN intro_note TEXT NULL"); }catch(Throwable $e){}
    try{ $pdo->exec("ALTER TABLE quotes ADD COLUMN approval_token VARCHAR(64) NULL"); }catch(Throwable $e){}
    try{ $pdo->exec("ALTER TABLE quotes ADD COLUMN approval_decision_at TIMESTAMP NULL"); }catch(Throwable $e){}
    $pdo->prepare("INSERT INTO quotes(quote_no,firm,customer_id,customer_name,intro_note,quote_date,valid_until,vat_rate,subtotal,vat_amount,total,notes,status,approval_token,created_by,created_by_name) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,'Taslak',?,?,?)")
      ->execute([$no,$firm,$cid,$cname,trim($_POST['intro_note']??''),date('Y-m-d'),($_POST['valid_until']??'')?:null,$vat,$sub,$vatAmt,$tot,trim($_POST['notes']??''),$token,$me,$meName]);
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

// Teklif sil (topx'tan önce)
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['del_quote']) && $isAdmin){
  $did=(int)($_POST['del_id']??0);
  if($did){
    try{
      $pdo->prepare("DELETE FROM quote_items WHERE quote_id=?")->execute([$did]);
      $pdo->prepare("DELETE FROM quotes WHERE id=?")->execute([$did]);
    }catch(Throwable $e){}
  }
  header('Location: teklif.php'); exit;
}

// Teklif düzenle (topx'tan önce)
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['edit_quote']) && $isAdmin){
  $eid=(int)($_POST['edit_id']??0);
  if($eid){
    try{
      $cid=(int)($_POST['customer_id']??0)?:null;
      $cname=trim($_POST['customer_name']??'');
      if($cid && $cname===''){ $r=$pdo->prepare("SELECT name FROM contacts WHERE id=?"); $r->execute([$cid]); $cname=$r->fetch()['name']??''; }
      $vat=(float)str_replace(',','.',$_POST['vat_rate']??'20');
      $names=$_POST['item_name']??[]; $qtys=$_POST['item_qty']??[]; $prices=$_POST['item_price']??[];
      $sub=0; $lines=[];
      for($i=0;$i<count($names);$i++){
        $nm=trim($names[$i]); if($nm==='') continue;
        $q2=(float)str_replace(',','.',$qtys[$i]??'0'); $pr=(float)str_replace(',','.',$prices[$i]??'0');
        $lt=$q2*$pr; $sub+=$lt; $lines[]=[$nm,$q2,$pr,$lt];
      }
      if(!$lines) throw new Exception('En az bir kalem girin.');
      $firm=in_array($_POST['firm']??'',['ACANS','PRIMAC'],true)?$_POST['firm']:null;
      $vatAmt=$sub*$vat/100; $tot=$sub+$vatAmt;
      $pdo->prepare("UPDATE quotes SET firm=?,customer_id=?,customer_name=?,intro_note=?,valid_until=?,vat_rate=?,subtotal=?,vat_amount=?,total=?,notes=? WHERE id=?")
        ->execute([$firm,$cid,$cname,trim($_POST['intro_note']??''),($_POST['valid_until']??'')?:null,$vat,$sub,$vatAmt,$tot,trim($_POST['notes']??''),$eid]);
      $pdo->prepare("DELETE FROM quote_items WHERE quote_id=?")->execute([$eid]);
      $ins=$pdo->prepare("INSERT INTO quote_items(quote_id,name,qty,unit_price,line_total) VALUES(?,?,?,?,?)");
      foreach($lines as $l){ $ins->execute([$eid,$l[0],$l[1],$l[2],$l[3]]); }
      try{ if(function_exists('activity_log')) activity_log('Teklif','Düzenleme','ID:'.$eid,'','quote',$eid,'teklif.php?id='.$eid,'✏️'); }catch(Throwable $e){}
      header('Location: teklif.php?id='.$eid); exit;
    }catch(Throwable $e){ $er=$e->getMessage(); }
  }
}

$id=(int)($_GET['id']??0);
$new=!empty($_GET['new']);
$editMode=($id && !empty($_GET['edit']));

/* ---------- TEKLİF GÖRÜNÜMÜ (PDF/paylaş) ---------- */
if($id && !$editMode){
  $q=null; try{ $s=$pdo->prepare("SELECT * FROM quotes WHERE id=?"); $s->execute([$id]); $q=$s->fetch(); }catch(Throwable $e){}
  if(!$q){ topx('Teklif'); echo '<div class="err">Teklif bulunamadı.</div>'; botx(); exit; }
  $items=[]; try{ $it=$pdo->prepare("SELECT * FROM quote_items WHERE quote_id=? ORDER BY id"); $it->execute([$id]); $items=$it->fetchAll(); }catch(Throwable $e){}
  $cphone=preg_replace('/\D/','',$q['customer_id']?($pdo->query("SELECT phone FROM contacts WHERE id=".(int)$q['customer_id'])->fetch()['phone']??''):'');
  $fi=!empty($q['firm'])?firm_info($q['firm']):null;
  $col=$fi?$fi['c']:'#1d4ed8'; $col2=$fi?$fi['c2']:'#0b1f3a';
  topx('Teklif '.$q['quote_no']);
  ?>
  <?php if($isAdmin): ?>
  <div class="panel noprint" style="display:flex;gap:8px">
    <a class="btn" href="teklif.php?id=<?=$id?>&edit=1" style="flex:1;text-align:center;background:#0369a1;color:#fff">✏️ Düzenle</a>
    <form method="post" style="flex:1;margin:0" onsubmit="return confirm('Bu teklif silinsin mi?')">
      <input type="hidden" name="del_id" value="<?=$id?>">
      <button class="btn" name="del_quote" value="1" style="width:100%;background:#991b1b;color:#fff">🗑 Sil</button>
    </form>
  </div>
  <?php endif; ?>
  <style>
  @media print{ body *{visibility:hidden!important} #repArea,#repArea *{visibility:visible!important} #repArea{position:absolute;left:0;top:0;width:100%} #repArea>div{min-height:auto!important;border-radius:0!important} .noprint{display:none!important} @page{size:A4;margin:0} }
  .pdfmode .paper{min-height:1131px!important;position:relative!important;border:none!important;border-radius:0!important}
  .pdfmode .qfoot{position:absolute!important;left:0;right:0;bottom:0}
  .paper.lh{aspect-ratio:210/297;position:relative!important}
  .paper.lh>.lhbg{position:absolute!important;top:0;left:0;width:100%!important;height:100%!important;object-fit:fill;z-index:0;display:block}
  .paper.lh>.lhbody{position:relative!important;z-index:1}
  .pdfmode .paper.lh{aspect-ratio:auto}
  </style>
  <?php $lh = ($fi && !empty($fi['letterhead']) && is_file(__DIR__.'/../'.$fi['letterhead'])) ? $fi['letterhead'] : ''; ?>
  <div id="repArea">
    <div class="paper<?=$lh?' lh':''?>" style="background:#fff;color:#1f2937;font-family:Arial,Helvetica,sans-serif;<?=$lh?'':'border:1px solid #e5e7eb;border-radius:10px;'?>overflow:hidden">
      <?php if($lh): ?><img class="lhbg" src="../<?=htmlspecialchars($lh)?>" alt=""><?php endif; ?>
      <?php if(!$lh): ?>
      <div style="height:5px;background:<?=$col?>"></div>
      <div style="padding:16px 16px 12px;display:flex;justify-content:space-between;align-items:center;border-bottom:1px solid #eef0f2">
        <?php if($fi && !empty($fi['mark'])): ?><div style="display:flex;align-items:center;gap:9px"><span style="background:<?=$col?>;border-radius:7px;padding:5px"><img src="../<?=htmlspecialchars($fi['mark'])?>" alt="logo" style="height:36px;object-fit:contain;display:block"></span><div style="font-weight:800;font-size:14px;color:#1f2937"><?=htmlspecialchars($fi['name'])?></div></div><?php elseif($fi): ?><img src="../<?=htmlspecialchars($fi['logo'])?>" alt="logo" style="height:36px;object-fit:contain;display:block"><?php else: ?><div style="font-weight:800;color:#1f2937"><?=htmlspecialchars(app_config()['app_name'] ?? 'OTS')?></div><?php endif; ?>
        <div style="text-align:right">
          <div style="font-size:23px;font-weight:900;letter-spacing:1px;color:#1f2937">TEKLİF</div>
          <div style="font-size:11px;color:<?=$col?>;font-weight:700"><?=htmlspecialchars($q['quote_no'])?></div>
        </div>
      </div>
      <?php endif; ?>
      <div class="<?=$lh?'lhbody':''?>" style="<?=$lh?'padding:15% 7% 9%':'padding:16px'?>">
        <?php if($lh): ?><div style="display:flex;justify-content:flex-end;margin-bottom:12px"><div style="text-align:right"><div style="font-size:22px;font-weight:900;letter-spacing:1px;color:#1f2937">TEKLİF</div><div style="font-size:11px;color:<?=$col?>;font-weight:700"><?=htmlspecialchars($q['quote_no'])?></div></div></div><?php endif; ?>
        <div style="display:flex;justify-content:space-between;flex-wrap:wrap;gap:8px;margin-bottom:14px">
          <div><div style="color:<?=$col?>;font-size:11px;font-weight:800">SAYIN</div><div style="font-size:16px;font-weight:700"><?=htmlspecialchars($q['customer_name']?:'—')?></div></div>
          <div style="text-align:right;color:#555;font-size:12px"><div><?=htmlspecialchars($q['quote_date'])?></div><?php if($q['valid_until']): ?><div>Geç: <?=htmlspecialchars($q['valid_until'])?></div><?php endif; ?></div>
        </div>
        <?php if(!empty($q['intro_note'])): ?><div style="font-size:12.5px;color:#374151;line-height:1.5;margin-bottom:14px"><?=nl2br(htmlspecialchars($q['intro_note']))?></div><?php endif; ?>
        <table style="width:100%;border-collapse:collapse;font-size:13px;margin-bottom:14px">
          <tr style="background:#f1f3f5;color:#1f2937;text-align:left;border-bottom:2px solid <?=$col?>"><th style="padding:9px 7px">Kalem</th><th style="padding:9px 7px;text-align:right">Adet</th><th style="padding:9px 7px;text-align:right">Birim</th><th style="padding:9px 7px;text-align:right">Tutar</th></tr>
          <?php foreach($items as $i=>$it): ?>
          <tr style="background:<?=$i%2?'#fafafa':'#fff'?>;border-bottom:1px solid #eee">
            <td style="padding:8px 7px"><?=htmlspecialchars($it['name'])?></td>
            <td style="padding:8px 7px;text-align:right"><?=rtrim(rtrim(number_format((float)$it['qty'],3,',','.'),'0'),',')?></td>
            <td style="padding:8px 7px;text-align:right"><?=mm($it['unit_price'])?></td>
            <td style="padding:8px 7px;text-align:right;font-weight:600"><?=mm($it['line_total'])?></td>
          </tr>
          <?php endforeach; ?>
        </table>
        <div style="margin-left:auto;width:230px;font-size:14px">
          <div style="display:flex;justify-content:space-between;padding:3px 0"><span style="color:#555">Ara Toplam</span><b><?=mm($q['subtotal'])?></b></div>
          <div style="display:flex;justify-content:space-between;padding:3px 0;border-bottom:1px solid #eee"><span style="color:#555">KDV (%<?=rtrim(rtrim(number_format((float)$q['vat_rate'],2,',','.'),'0'),',')?>)</span><b><?=mm($q['vat_amount'])?></b></div>
          <div style="display:flex;justify-content:space-between;align-items:center;margin-top:7px;background:<?=$col?>;color:#fff;padding:9px 11px;border-radius:8px;font-size:16px"><b>TOPLAM</b><b><?=mm($q['total'])?></b></div>
        </div>
        <?php if($q['notes']): ?><div style="margin-top:16px;font-size:13px;color:#333;background:#f8f9fb;border-left:4px solid <?=$col?>;padding:9px 12px;border-radius:0 6px 6px 0"><b style="color:<?=$col?>">Not</b><br><?=nl2br(htmlspecialchars($q['notes']))?></div><?php endif; ?>
      </div>
      <?php if(!$lh): ?>
      <div class="qfoot" style="border-top:2px solid <?=$col?>;background:#f8f9fa;color:#374151;padding:11px 16px;text-align:center;font-size:12px">
        <?php if($fi): ?><b style="color:#1f2937"><?=htmlspecialchars($fi['name'])?></b> · 🌐 <?=htmlspecialchars($fi['web'])?><?php else: ?><?=htmlspecialchars(app_config()['app_name'] ?? 'OTS')?> — Online Takip Sistemi<?php endif; ?>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <div class="panel noprint">
    <b>📤 Teklifi gönder / yazdır</b>
    <button onclick="shareReportPDF(this)" class="btn" style="display:block;width:100%;background:#16a34a;color:#fff;padding:14px;margin-top:10px">📄 PDF İndir / Paylaş (WhatsApp/Mail)</button>
    <?php
      $approvalUrl=base_url().'quote_approve.php?token='.$q['approval_token'];
      $txt="📄 Teklif ".$q['quote_no']."\nMüşteri: ".$q['customer_name']."\nTutar: ".mm($q['total']).($q['valid_until']?"\nGeçerlilik: ".$q['valid_until']:'')."\n\n✅ Onaylamak için: ".$approvalUrl;
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

  <script>window.ACANS_REPORT_NAME='teklif_<?=htmlspecialchars($q['quote_no'])?>';window.ACANS_PDF_BG='#ffffff';window.ACANS_PDF_FG='#111111';window.ACANS_PDF_FIT=true;window.ACANS_PDF_PAD=0;</script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
  <script src="../report_share.js"></script>
  <?php botx(); exit;
}

/* ---------- DÜZENLE ---------- */
if($editMode && $isAdmin){
  $q=null; try{ $s=$pdo->prepare("SELECT * FROM quotes WHERE id=?"); $s->execute([$id]); $q=$s->fetch(); }catch(Throwable $e){}
  if(!$q){ topx('Teklif'); echo '<div class="err">Teklif bulunamadı.</div>'; botx(); exit; }
  $items=[]; try{ $it=$pdo->prepare("SELECT * FROM quote_items WHERE quote_id=? ORDER BY id"); $it->execute([$id]); $items=$it->fetchAll(); }catch(Throwable $e){}
  $cs=$pdo->query("SELECT id,name FROM contacts ORDER BY name")->fetchAll();
  topx('Teklif Düzenle');
  if($er) echo '<div class="err">'.htmlspecialchars($er).'</div>';
  ?>
  <div class="panel">
  <form method="post">
    <input type="hidden" name="edit_id" value="<?=$id?>">
    <label>Teklifi veren firma (opsiyonel)</label>
    <select name="firm"><option value="">— Firma yok (sade) —</option><option value="ACANS"<?=$q['firm']==='ACANS'?' selected':''?>>ACANS Reklam</option><option value="PRIMAC"<?=$q['firm']==='PRIMAC'?' selected':''?>>PRIMAC</option></select>
    <label>Müşteri</label>
    <select name="customer_id"><option value="">— Cari seç (veya alta yaz) —</option><?php foreach($cs as $c): ?><option value="<?=$c['id']?>"<?=(int)$q['customer_id']===(int)$c['id']?' selected':''?>><?=htmlspecialchars($c['name'])?></option><?php endforeach; ?></select>
    <input name="customer_name" placeholder="veya müşteri adı yaz" value="<?=htmlspecialchars($q['customer_name'])?>">
    <label>Giriş Açıklaması (SAYIN altında görünür)</label>
    <textarea name="intro_note" rows="3"><?=htmlspecialchars($q['intro_note']??'')?></textarea>
    <label>Geçerlilik Tarihi</label><input type="date" name="valid_until" value="<?=htmlspecialchars($q['valid_until']??'')?>">
    <label>KDV %</label><input name="vat_rate" inputmode="decimal" value="<?=htmlspecialchars(rtrim(rtrim(number_format((float)$q['vat_rate'],2,'.',''),'0'),'.'))?>" >

    <label style="margin-top:10px">Kalemler</label>
    <div id="rows"></div>
    <button type="button" class="btn" onclick="addRow()" style="width:100%;background:#334155;color:#fff;margin-top:6px">+ Kalem Ekle</button>

    <div class="panel" style="margin-top:10px;background:rgba(34,197,94,.08)">
      <div style="display:flex;justify-content:space-between"><span>Ara Toplam</span><b id="tSub">0,00 ₺</b></div>
      <div style="display:flex;justify-content:space-between"><span>KDV</span><b id="tVat">0,00 ₺</b></div>
      <div style="display:flex;justify-content:space-between;font-size:17px;margin-top:4px"><span><b>Toplam</b></span><b id="tTot" style="color:#22c55e">0,00 ₺</b></div>
    </div>

    <label>Not</label><textarea name="notes" rows="2"><?=htmlspecialchars($q['notes']??'')?></textarea>
    <button class="btn dark" name="edit_quote" value="1" style="width:100%;padding:14px;margin-top:8px;background:#0369a1">💾 Değişiklikleri Kaydet</button>
  </form>
  </div>
  <a class="btn dark" href="teklif.php?id=<?=$id?>" style="display:block;text-align:center;margin-top:8px">← İptal</a>
  <script>
  var initItems=<?=json_encode(array_values(array_map(function($it){ return [$it['name'],(float)$it['qty'],(float)$it['unit_price']]; },$items)))?>;
  function fmt(n){ return n.toLocaleString('tr-TR',{minimumFractionDigits:2,maximumFractionDigits:2})+' ₺'; }
  function num(v){ return parseFloat(String(v||'').replace(/\./g,'').replace(',','.'))||0; }
  function addRow(nm,q,p){
    var d=document.createElement('div'); d.className='qrow'; d.style.cssText='display:flex;gap:6px;margin-bottom:6px';
    d.innerHTML='<input name="item_name[]" placeholder="Ürün/hizmet" style="flex:2;margin:0" value="'+(nm||'')+'">'+
      '<input name="item_qty[]" inputmode="decimal" placeholder="Adet" style="flex:1;margin:0" value="'+(q!==undefined?q:'1')+'">'+
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
  for(var i=0;i<initItems.length;i++){ addRow(initItems[i][0],initItems[i][1],initItems[i][2]); }
  if(!initItems.length) addRow();
  calc();
  </script>
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
    <label>Teklifi veren firma (opsiyonel)</label>
    <select name="firm"><option value="">— Firma yok (sade) —</option><option value="ACANS">ACANS Reklam</option><option value="PRIMAC">PRIMAC</option></select>
    <label>Müşteri</label>
    <select name="customer_id"><option value="">— Cari seç (veya alta yaz) —</option><?php foreach($cs as $c): ?><option value="<?=$c['id']?>"><?=htmlspecialchars($c['name'])?></option><?php endforeach; ?></select>
    <input name="customer_name" placeholder="veya müşteri adı yaz">
    <label>Giriş Açıklaması (SAYIN altında görünür)</label>
    <textarea name="intro_note" rows="3" placeholder="Örn: Firmamızdan talep etmiş olduğunuz ürün/hizmetler için hazırladığımız teklifimiz aşağıdaki gibidir."></textarea>
    <label>Geçerlilik Tarihi</label><input type="date" name="valid_until">
    <label>KDV %</label><input name="vat_rate" inputmode="decimal" value="20">

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
