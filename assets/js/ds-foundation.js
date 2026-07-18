/* PRIMAC OTS — PX-002 MADDE 2 / FAZ 2A (2026-07-17)
 * Minimal, framework'süz vanilla JS altyapısı — Product Owner kararı: "yeni UI framework
 * yazma, React/Vue benzeri yeni teknoloji ekleme". DF-Accordion kabuğu (Madde 2 sınırı —
 * veri modeli ve "tek açık" zorlaması Madde 3/4'ün işi, bkz. ds_lib.php::ds_accordion_item()).
 */
function dfAccordionToggle(headerEl){
    var item = headerEl.closest('.df-accordion-item');
    if(!item) return;
    var isOpen = item.classList.toggle('is-open');
    headerEl.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
}

/* PX-002 FAZ 2B-ii (2026-07-17) — Web Rail tek-açık kategori davranışı. Bu dosya artık
 * SADECE compact modda yükleniyor (layout_top.php::ds_scripts(), yalnızca $__navMode!=='legacy'
 * dalında çağrılıyor) — Legacy Mode bu dosyayı hiç görmez.
 *
 * JS kapalıyken temel erişim: ds-foundation.css'te .df-rail-cat-body varsayılan olarak
 * gizli (display:none), ama layout_top.php geçerli sayfanın sahibi olan kategoriyi sunucu
 * tarafında zaten .is-open bastırıyor (o kategori JS'siz de görünür) VE bir <noscript><style>
 * override'ı TÜM kategori gövdelerini görünür yapıyor — JS tamamen kapalıyken kullanıcı hiçbir
 * linke erişimini kaybetmiyor, sadece tek-açık/akordeon güzelliğini kaybediyor (kabul edilebilir
 * bozulma, Product Owner kararı: "temel link erişimi tamamen kaybolmaz").
 *
 * P0 ACCORDION STATE DÜZELTMESİ (2026-07-18, Product Owner kararı): önceden bir sessionStorage
 * katmanı vardı — "kategori-dışı (global) bir sayfaya" (Ana Sayfa, İletişim Merkezi) gidilince,
 * bir ÖNCEKİ sayfada açık bırakılmış kategoriyi sessionStorage'dan OKUYUP GERİ AÇIYORDU. Bu,
 * BİLDİRİLEN hatanın kök nedeniydi: "İşler" açıkken Ana Sayfa'ya tıklanınca sunucu doğru şekilde
 * hiçbir kategoriyi .is-open basmıyordu (server-side state zaten doğruydu) ama bu JS DOMContentLoaded
 * anında sessionStorage'daki eski değeri okuyup "İşler"i client-side yeniden açıyordu. TEK MERKEZİ
 * NAV STATE kuralı gereği (Product Owner: "her sayfaya ayrı JS hack ekleme") kalıcı istemci-taraflı
 * hafıza TAMAMEN kaldırıldı — accordion durumu artık HER ZAMAN sunucunun $__catHasActive
 * hesaplamasından (layout_top.php, geçerli route'a göre) gelir; dfRailToggle() SADECE aynı sayfa
 * görünümü içindeki manuel aç/kapa tıklamasını yönetir (sayfa değişince zaten yeniden render olur).
 */
(function(){
    function closeAllCats(rail){
        rail.querySelectorAll('.df-rail-cat.is-open').forEach(function(el){
            el.classList.remove('is-open');
            var btn = el.querySelector('.df-rail-cat-btn');
            if(btn) btn.setAttribute('aria-expanded', 'false');
        });
    }
    function openCat(rail, el){
        if(!el) return;
        closeAllCats(rail);
        el.classList.add('is-open');
        var btn = el.querySelector('.df-rail-cat-btn');
        if(btn) btn.setAttribute('aria-expanded', 'true');
    }
    window.dfRailToggle = function(btn){
        var el = btn.closest('.df-rail-cat');
        var rail = btn.closest('.df-rail');
        if(!el || !rail) return;
        var wasOpen = el.classList.contains('is-open');
        if(wasOpen){ closeAllCats(rail); }
        else{ openCat(rail, el); }
    };
})();

/* P0 SEKME OVERFLOW DÜZELTMESİ (2026-07-18) — personnel_edit.php gibi çok sekmeli (~9 sekme)
 * ekranlarda .df-tabs zaten kendi içinde yatay kayıyordu (bkz. ds-foundation.css'teki 2026-07-18
 * notu) ama iki eksik vardı: (1) sayfa her yenilendiğinde kaydırma pozisyonu sıfırlanıyor, aktif
 * sekme listenin sonundaysa (ör. "Hareket Geçmişi") görünür alanın dışında kalıyordu; (2) gizli
 * scrollbar yüzünden daha fazla sekme olduğuna dair hiçbir ipucu yoktu. Bu blok TÜM .df-tabs
 * örneklerinde (web+mobil, ds_tabs() ortak bileşeni — search.php/İletişim Merkezi/personel/
 * çek-senet vb. hepsi otomatik kapsanır) çalışır: aktif sekmeyi yükleme anında görünür alana
 * kaydırır, taşma durumuna göre kenar soluklaştırma class'larını günceller.
 */
(function(){
    function updateFade(bar){
        var maxScroll = bar.scrollWidth - bar.clientWidth;
        var atStart = bar.scrollLeft <= 1;
        var atEnd = bar.scrollLeft >= maxScroll - 1;
        bar.classList.toggle('df-tabs--fade-left', maxScroll > 1 && !atStart);
        bar.classList.toggle('df-tabs--fade-right', maxScroll > 1 && !atEnd);
    }
    function initTabs(bar){
        var active = bar.querySelector('.df-tab--active');
        if(active){ active.scrollIntoView({behavior:'auto', inline:'nearest', block:'nearest'}); }
        updateFade(bar);
        bar.addEventListener('scroll', function(){ updateFade(bar); }, {passive:true});
    }
    document.addEventListener('DOMContentLoaded', function(){
        document.querySelectorAll('.df-tabs').forEach(initTabs);
        window.addEventListener('resize', function(){
            document.querySelectorAll('.df-tabs').forEach(updateFade);
        });
    });
})();

/* FİNANS UX REGRESYON DÜZELTMESİ (2026-07-19, Product Owner kararı) — Tahsilat/Ödeme
 * ekranlarındaki cari <select> TÜM contacts tablosunu (potansiyel binlerce satır) DOM'a
 * döküyordu. Bu, contact_search_ajax.php'ye arayan tek ortak arama/otomatik-tamamlama
 * bileşeni — web (finance_new.php) ve mobil (collection.php/payment.php) BİREBİR aynı
 * fonksiyonu çağırır, cari veri modeli/bakiye mantığı hiç değişmedi, sadece seçim arayüzü.
 * cfg: {inputId, hiddenId, resultsId, endpoint, getScope(): 'customers'|'suppliers'|'all', onSelect?}
 * Dönüş: {refresh, clear} — çağıran taraf yön/kapsam değiştiğinde refresh() çağırır. */
function dfInitContactPicker(cfg){
    var input = document.getElementById(cfg.inputId);
    var hidden = document.getElementById(cfg.hiddenId);
    var results = document.getElementById(cfg.resultsId);
    if(!input || !hidden || !results) return null;
    var timer = null;
    var reqSeq = 0;

    function escAttr(s){ return String(s).replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/</g,'&lt;'); }
    function escText(s){ return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }

    function selectItem(c){
        hidden.value = c.id;
        hidden.dataset.selectedName = c.name;
        input.value = c.name;
        results.hidden = true;
        if(cfg.onSelect) cfg.onSelect(c);
    }
    function render(list){
        if(!list.length){
            results.innerHTML = '<div class="df-contact-picker-empty">Sonuç yok</div>';
            results.hidden = false;
            return;
        }
        results.innerHTML = list.map(function(c, i){
            return '<div class="df-contact-picker-item" data-i="'+i+'">'
                + '<span class="df-contact-picker-name">'+escText(c.name)+'</span>'
                + '<span class="df-contact-picker-badge">'+escText(c.type||'')+'</span>'
                + (c.phone ? '<span class="df-contact-picker-phone">'+escText(c.phone)+'</span>' : '')
                + '</div>';
        }).join('');
        results.hidden = false;
        Array.prototype.forEach.call(results.querySelectorAll('.df-contact-picker-item'), function(el){
            el.addEventListener('mousedown', function(ev){
                ev.preventDefault();
                selectItem(list[parseInt(el.dataset.i, 10)]);
            });
        });
    }
    function fetchResults(q){
        var scope = cfg.getScope ? cfg.getScope() : 'all';
        var seq = ++reqSeq;
        fetch(cfg.endpoint + '?q=' + encodeURIComponent(q) + '&scope=' + encodeURIComponent(scope), {headers:{'Accept':'application/json'}})
            .then(function(r){ return r.json(); })
            .then(function(data){
                if(seq !== reqSeq) return;
                if(!data.ok){ results.hidden = true; return; }
                render(data.contacts || []);
            })
            .catch(function(){ if(seq === reqSeq) results.hidden = true; });
    }
    input.addEventListener('input', function(){
        if(input.value !== (hidden.dataset.selectedName || '')) hidden.value = '';
        clearTimeout(timer);
        var q = input.value.trim();
        timer = setTimeout(function(){ fetchResults(q); }, 250);
    });
    input.addEventListener('focus', function(){ fetchResults(input.value.trim()); });
    document.addEventListener('click', function(ev){
        if(ev.target !== input && !results.contains(ev.target)) results.hidden = true;
    });

    return {
        refresh: function(){ if(!results.hidden || document.activeElement === input) fetchResults(input.value.trim()); },
        clear: function(){ hidden.value=''; hidden.dataset.selectedName=''; input.value=''; results.hidden=true; }
    };
}
