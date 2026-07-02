<?php
require_once 'common.php';
$pdo=db();
$pasifDahil=isset($_GET['pasif_dahil']);
topx('Stok');
echo '<div class="panel" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">';
echo '<a class="btn dark" href="product_new.php">+ Yeni Ürün</a>';
echo '<a class="btn" href="report.php?modul=stok" style="background:#334155;color:#fff">📊 Rapor</a>';
echo '<button class="btn" id="barcodeBtn" onclick="ACANS_BARCODE_TOGGLE()" style="background:#8b5cf6;color:#fff;display:none">📷 Barkod Okut</button>';
echo '<form method="get" style="margin:0;display:flex;align-items:center;gap:6px"><label style="font-size:13px;color:#94a3b8;display:flex;align-items:center;gap:4px"><input type="checkbox" name="pasif_dahil" value="1"'.($pasifDahil?' checked':'').' onchange="this.form.submit()" style="width:auto"> Pasif Dahil</label></form>';
echo '</div>';
try{
    $sql="SELECT id,name,quantity,unit,sale_price,critical_level,active FROM stock_items";
    if(!$pasifDahil) $sql.=" WHERE (active IS NULL OR active=1)";
    $sql.=" ORDER BY name LIMIT 200";
    $rows=$pdo->query($sql)->fetchAll();
    foreach($rows as $r){
        $kr=($r['quantity']<=($r['critical_level']??0));
        $isPasif=isset($r['active']) && !$r['active'];
        $style=$isPasif?' style="opacity:.45"':'';
        echo '<a class="item" href="product_view.php?id='.(int)$r['id'].'"'.$style.'>';
        echo '<b>'.htmlspecialchars($r['name']).'</b>';
        if($isPasif) echo ' <span style="font-size:11px;background:#ef4444;color:#fff;border-radius:6px;padding:1px 6px">Pasif</span>';
        if($kr) echo ' <span style="color:#f87171;font-size:12px;font-weight:900">⚠️ kritik</span>';
        echo '<br><small>Stok: '.htmlspecialchars($r['quantity'].' '.$r['unit']).' · Satış: '.mm($r['sale_price']??0).'</small>';
        echo '</a>';
    }
}catch(Throwable $e){ echo '<div class="err">'.htmlspecialchars($e->getMessage()).'</div>'; }
botx();
?>
<!-- Barkod Modal -->
<div id="barcode-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.96);z-index:10000;flex-direction:column;padding:calc(10px + env(safe-area-inset-top)) 14px 10px">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;color:#fff">
    <b style="font-size:16px">Barkod / QR Kod Okut</b>
    <button onclick="ACANS_BARCODE_CLOSE()" style="background:rgba(255,255,255,.18);color:#fff;border:0;border-radius:10px;width:40px;height:40px;font-size:20px;cursor:pointer">✕</button>
  </div>
  <video id="barcode-video" style="width:100%;height:100%;max-width:500px;object-fit:cover;border-radius:16px;margin:0 auto;display:flex;align-items:center;justify-content:center;background:#000"></video>
  <div id="barcode-result" style="margin-top:12px;background:rgba(34,197,94,.2);border:1px solid #22c55e;border-radius:12px;padding:12px;color:#86efac;display:none;word-break:break-all"><b id="barcode-text"></b></div>
  <small style="text-align:center;color:#94a3b8;margin-top:12px">Kameraya barkodu göster. Oktu sonra direkt sayfaya gidilecek.</small>
</div>

<script>
window.ACANS_BARCODE_DETECTOR=null;
window.ACANS_BARCODE_STREAM=null;
window.ACANS_BARCODE_PROCESSING=false;

window.ACANS_BARCODE_TOGGLE=function(){
  var modal=document.getElementById('barcode-modal');
  if(modal.style.display==='none'){
    modal.style.display='flex';
    ACANS_BARCODE_START();
  }else{
    ACANS_BARCODE_CLOSE();
  }
};

window.ACANS_BARCODE_CLOSE=function(){
  var modal=document.getElementById('barcode-modal');
  modal.style.display='none';
  if(window.ACANS_BARCODE_STREAM){
    window.ACANS_BARCODE_STREAM.getTracks().forEach(function(t){t.stop();});
    window.ACANS_BARCODE_STREAM=null;
  }
};

window.ACANS_BARCODE_START=function(){
  if(!('BarcodeDetector' in window)){ alert('Bu cihaz barkod okutmayı desteklemiyor.'); ACANS_BARCODE_CLOSE(); return; }

  var video=document.getElementById('barcode-video');
  navigator.mediaDevices.getUserMedia({video:{facingMode:'environment'}})
    .then(function(stream){
      window.ACANS_BARCODE_STREAM=stream;
      video.srcObject=stream;
      video.play().catch(function(){});
      ACANS_BARCODE_DETECT();
    })
    .catch(function(err){
      alert('Kamera erişimi reddedildi: '+err.message);
      ACANS_BARCODE_CLOSE();
    });
};

window.ACANS_BARCODE_DETECT=function(){
  if(!window.ACANS_BARCODE_STREAM || window.ACANS_BARCODE_PROCESSING) return;

  var video=document.getElementById('barcode-video');
  if(video.videoWidth===0) { setTimeout(ACANS_BARCODE_DETECT,500); return; }

  var detector=new BarcodeDetector({formats:['code_128','code_39','code_93','codabar','ean_8','ean_13','qr_code','upc_a','upc_e']});

  detector.detect(video)
    .then(function(codes){
      if(codes.length>0){
        var code=codes[0].rawValue;
        window.ACANS_BARCODE_PROCESSING=true;

        // Taranan kodu göster
        document.getElementById('barcode-text').textContent=code;
        document.getElementById('barcode-result').style.display='block';

        // Ürünü ara: product_code veya barcode alanında eşleş
        fetch('stock.php?search='+encodeURIComponent(code))
          .then(function(r){return r.text();})
          .then(function(html){
            var match=html.match(/href="product_view\.php\?id=(\d+)"/);
            if(match){
              setTimeout(function(){ location.href='product_view.php?id='+match[1]; },1200);
            }else{
              window.ACANS_BARCODE_PROCESSING=false;
              setTimeout(ACANS_BARCODE_DETECT,2000);
            }
          })
          .catch(function(){ window.ACANS_BARCODE_PROCESSING=false; setTimeout(ACANS_BARCODE_DETECT,2000); });
      }else{
        setTimeout(ACANS_BARCODE_DETECT,500);
      }
    })
    .catch(function(){ setTimeout(ACANS_BARCODE_DETECT,500); });
};

// Sayfa yüklenince BarcodeDetector desteğini kontrol et ve butonu göster/gizle
(function(){
  if(!('BarcodeDetector' in window)){ return; }
  // Destek varsa, stock.php içindeki şartlı buton zaten gösterilmiş olacak
})();
</script>
