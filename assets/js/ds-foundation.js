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
