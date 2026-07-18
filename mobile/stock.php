<?php
require_once 'common.php';
$pdo=db();
$pasifDahil=isset($_GET['pasif_dahil']);
// MOBILE UX BUGFIX SPRINT (2026-07-15): web stock.php ile aynı düzeltme — critical=1 artık
// gerçekten filtreliyor (daha önce mobil "Dikkat" panelinden kritik stoğa giden hiçbir yol yoktu).
$criticalOnly=isset($_GET['critical']);
topx('Stok');
?>
<div class="df-panel" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
  <a class="df-btn df-btn--primary" href="product_new.php"><?=ds_icon('plus',14)?> Yeni Ürün</a>
  <a class="df-btn df-btn--secondary" href="report.php?modul=stok"><?=ds_icon('info',14)?> Rapor</a>
  <button class="df-btn df-btn--secondary" id="barcodeBtn" onclick="ACANS_BARCODE_TOGGLE()" style="display:none">📷 Barkod Okut</button>
  <form method="get" style="margin:0;display:flex;align-items:center;gap:10px;flex-wrap:wrap">
    <label style="font-size:13px;color:var(--df-ink-500,#94a3b8);display:flex;align-items:center;gap:4px"><input type="checkbox" name="pasif_dahil" value="1"<?=$pasifDahil?' checked':''?> onchange="this.form.submit()" style="width:auto"> Pasif Dahil</label>
    <label style="font-size:13px;color:var(--df-ink-500,#94a3b8);display:flex;align-items:center;gap:4px"><input type="checkbox" name="critical" value="1"<?=$criticalOnly?' checked':''?> onchange="this.form.submit()" style="width:auto"> Sadece Kritik</label>
  </form>
</div>
<?php
try{
    $sql="SELECT id,name,quantity,unit,sale_price,critical_level,active FROM stock_items";
    $conditions=[];
    if(!$pasifDahil) $conditions[]="(active IS NULL OR active=1)";
    if($criticalOnly) $conditions[]="quantity<=critical_level";
    if($conditions) $sql.=" WHERE ".implode(' AND ',$conditions);
    $sql.=" ORDER BY name LIMIT 200";
    $rows=$pdo->query($sql)->fetchAll();
    if(!$rows){
        ds_empty_state('Kayıt bulunamadı.', null, ds_icon('box',20));
    }else{
        echo '<div class="df-list" style="margin-top:12px">';
        foreach($rows as $r){
            $kr=($r['quantity']<=($r['critical_level']??0));
            $isPasif=isset($r['active']) && !$r['active'];
            $titleHtml = '<b>'.h($r['name']).'</b>';
            if($isPasif) $titleHtml .= ' '.ds_badge('Pasif','red');
            if($kr) $titleHtml .= ' '.ds_badge('Kritik','red');
            $metaHtml = '<span>Stok: '.h($r['quantity'].' '.$r['unit']).'</span><span>Satış: '.mm($r['sale_price']??0).'</span>';
            ds_list_item($titleHtml, 'product_view.php?id='.(int)$r['id'], null, $metaHtml);
        }
        echo '</div>';
    }
}catch(Throwable $e){ echo ds_alert('danger',$e->getMessage()); }
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
