// ACANS OTS — Rapor paylaşımı: çok-sayfalı gerçek PDF (mobil+web ortak)
// Gerekli: html2canvas + jspdf CDN (sayfa <head>/altında yüklü olmalı)
(function(){
  function getPDF(){ return (window.jspdf && window.jspdf.jsPDF) ? window.jspdf.jsPDF : (window.jsPDF||null); }

  // Raporu sabit okunur genişlikte (A4) yakala, A4 sayfalara böl, PDF üret, paylaş/indir.
  window.shareReportPDF = function(btn){
    var src = document.getElementById('repArea') || document.querySelector('.rep');
    var JsPDF = getPDF();
    if(!src || typeof html2canvas==='undefined' || !JsPDF){ window.print(); return; }
    var t = btn ? btn.textContent : '';
    if(btn){ btn.textContent='⏳ PDF hazırlanıyor...'; btn.disabled=true; }

    // Telefon dar ekranını değil, sabit 800px okunur düzeni yakala
    var holder = document.createElement('div');
    holder.style.cssText = 'position:fixed;left:-10000px;top:0;width:800px;background:#0b1220;padding:16px;color:#e5edf7';
    holder.appendChild(src.cloneNode(true));
    document.body.appendChild(holder);

    function done(){ if(btn){ btn.textContent=t; btn.disabled=false; } if(holder.parentNode) holder.parentNode.removeChild(holder); }

    html2canvas(holder,{backgroundColor:'#0b1220',scale:2,useCORS:true}).then(function(canvas){
      try{
        var pdf = new JsPDF('p','mm','a4');
        var pw=210, ph=297;
        var ratio = pw / canvas.width;          // mm / px
        var pageHpx = Math.floor(ph / ratio);    // bir A4 sayfasına sığan px yüksekliği
        var cctx = canvas.getContext('2d');
        // Bir satır tamamen koyu arka plan mı (kartlar arası boşluk)? → güvenli kesim noktası
        function rowIsBg(yy){
          try{
            var d = cctx.getImageData(0, yy, canvas.width, 1).data;
            for(var i=0;i<d.length;i+=32){ if(!(d[i]<32 && d[i+1]<40 && d[i+2]<55)) return false; }
            return true;
          }catch(e){ return false; }
        }
        var y=0, page=0;
        while(y < canvas.height){
          var target = Math.min(y + pageHpx, canvas.height);
          var cut = target;
          if(target < canvas.height){
            // Hedefin biraz üstünde kartlar arası boşluk ara (kartı ortadan kesme)
            var lim = Math.max(y + Math.floor(pageHpx*0.5), target - 280);
            for(var s=target; s>lim; s--){ if(rowIsBg(s)){ cut=s; break; } }
          }
          var hpx = cut - y;
          var slice = document.createElement('canvas');
          slice.width = canvas.width; slice.height = hpx;
          slice.getContext('2d').drawImage(canvas, 0, y, canvas.width, hpx, 0, 0, canvas.width, hpx);
          var img = slice.toDataURL('image/jpeg', 0.92);
          if(page>0) pdf.addPage();
          pdf.addImage(img, 'JPEG', 0, 0, pw, hpx*ratio);
          y = cut; page++;
        }
        var blob = pdf.output('blob');
        var fn = (window.ACANS_REPORT_NAME||'rapor') + '.pdf';
        var file = new File([blob], fn, {type:'application/pdf'});
        if(navigator.canShare && navigator.canShare({files:[file]})){
          navigator.share({files:[file], title: fn}).catch(function(){}).finally(done);
        } else {
          var a=document.createElement('a'); a.href=URL.createObjectURL(blob); a.download=fn;
          document.body.appendChild(a); a.click(); a.remove(); done();
        }
      }catch(e){ done(); window.print(); }
    }).catch(function(){ done(); window.print(); });
  };
})();
