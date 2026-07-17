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
 * sessionStorage: yalnızca whitelist edilmiş 5 kategori anahtarından biri saklanır, sekme
 * kapanınca silinir, DB'ye hiç yazılmaz. Sunucunun kendi seçtiği (geçerli sayfanın kategorisi)
 * her zaman öncelikli — sessionStorage yalnızca kategori-dışı (global) sayfalarda devreye girer.
 */
(function(){
    var STORAGE_KEY = 'dfRailOpenCat';
    var VALID_CATS = ['isler','ticaret','uretim_stok','finans','yonetim'];
    function isValidCat(v){ return VALID_CATS.indexOf(v) !== -1; }
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
        if(wasOpen){
            closeAllCats(rail);
            try{ sessionStorage.removeItem(STORAGE_KEY); }catch(e){}
        }else{
            openCat(rail, el);
            var cat = el.getAttribute('data-cat');
            if(isValidCat(cat)){ try{ sessionStorage.setItem(STORAGE_KEY, cat); }catch(e){} }
        }
    };
    document.addEventListener('DOMContentLoaded', function(){
        var rail = document.querySelector('.df-rail');
        if(!rail) return;
        var serverOpen = rail.querySelector('.df-rail-cat.is-open');
        if(serverOpen){
            // Sunucu geçerli sayfanın kategorisini zaten açtı — sessionStorage'ı onunla senkron tut.
            var cat = serverOpen.getAttribute('data-cat');
            if(isValidCat(cat)){ try{ sessionStorage.setItem(STORAGE_KEY, cat); }catch(e){} }
            return;
        }
        // Kategori-dışı (global) bir sayfadayız — son açık kategoriyi geri getir (varsa, geçerliyse,
        // yetkiliyse — yetkisiz/boş kategori zaten DOM'da hiç yok, querySelector null döner).
        var saved = null;
        try{ saved = sessionStorage.getItem(STORAGE_KEY); }catch(e){}
        if(saved && isValidCat(saved)){
            var el = rail.querySelector('.df-rail-cat[data-cat="' + saved + '"]');
            if(el) openCat(rail, el);
        }
    });
})();
