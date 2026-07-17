/* PRIMAC OTS — PX-002 MADDE 2 / FAZ 2A (2026-07-17)
 * Minimal, framework'süz vanilla JS altyapısı — Product Owner kararı: "yeni UI framework
 * yazma, React/Vue benzeri yeni teknoloji ekleme". Bu dosya tek başlığa hizmet ediyor:
 * DF-Accordion kabuğu (Madde 2 sınırı — veri modeli ve "tek açık" zorlaması Madde 3/4'ün işi,
 * bkz. ds_lib.php::ds_accordion_item()). Hiçbir sayfa bu dosyayı henüz yüklemiyor — inert,
 * FAZ 2B/2D'de shell/sayfa dosyalarına <script> ile bağlanacak.
 */
function dfAccordionToggle(headerEl){
    var item = headerEl.closest('.df-accordion-item');
    if(!item) return;
    var isOpen = item.classList.toggle('is-open');
    headerEl.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
}
