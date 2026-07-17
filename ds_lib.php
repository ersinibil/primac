<?php
// PRIMAC OTS — DESIGN SYSTEM SPRINT 001 / PHASE A: FOUNDATION COMPONENTS (2026-07-15)
// Yeni, saf-ek (additive) PHP yardımcıları. Mevcut badge()/status_tone()/cmd_card() gibi
// fonksiyonlara DOKUNMAZ, mantıklarını TEKRARLAMAZ — ds_badge() mevcut status_tone() eşlemesini
// aynen kullanır, sadece yeni "ds-badge" CSS sınıfını basar. Hiçbir mevcut ekran bu fonksiyonları
// çağırmıyor; bu dosya bugün hiçbir sayfanın görünümünü değiştirmez — kademeli geçiş (eski
// ekranların bu standarda taşınması) sonraki bir sprintin işi.
//
// DS-003A EKİ (2026-07-16): ds_icon() — Visual Language Foundation'ın onaylanan ikon standardının
// PHP karşılığı, "df-" (design foundation) CSS namespace'iyle eşleşir (bkz. ds-foundation.css dosya
// sonu). Diğer ds_*() fonksiyonları gibi bugün hiçbir ekran tarafından çağrılmıyor — inert.
//
// PX-001A EKİ (2026-07-16): mytasks.php Visual Language uygulaması. Product Owner kararı: PHP
// tarafında TEK canonical component API korunur — ds_button() geriye-dönük-uyumlu $df parametresi
// ile genişletildi (df-* çıktısı üretebilir), yeni ds_priority() eklendi. ds_page_header()/
// ds_badge() DEĞİŞMEDİ (mevcut $icon parametresi zaten ds_icon() çıktısını taşıyabiliyor).

// PX-001A DÜZELTME (2026-07-16): "?v=1" DS-002A'dan beri sabitti — dosya her sprintte değişti ama
// versiyon dizesi hiç değişmediği için tarayıcı/CDN eski bir kopyayı sonsuza dek önbellekte
// tutabiliyordu (primac.tr'de canlı olarak gözlemlendi: df-* sınıfları hiç uygulanmamış, ds_icon()
// SVG'leri stilsiz/dev boyutta render olmuş). Artık gerçek dosyanın mtime'ı — her CSS değişikliği
// otomatik olarak yeni bir versiyon dizesi, dolayısıyla zorunlu yeniden-indirme üretiyor.
function ds_styles(){
    $__path = __DIR__.'/assets/css/ds-foundation.css';
    $__v = is_file($__path) ? filemtime($__path) : 1;
    echo '<link rel="stylesheet" href="'.h(base_url().'assets/css/ds-foundation.css').'?v='.(int)$__v.'">';
}

// PX-002 FAZ 2B-ii (2026-07-17) — ds_styles() ile aynı desen (mtime cache-bust), JS için.
// Yalnızca compact modda çağrılır (layout_top.php) — Rail'in tek-açık kategori/sessionStorage
// davranışı burada. Legacy Mode bu dosyayı hiç yüklemez.
function ds_scripts(){
    $__path = __DIR__.'/assets/js/ds-foundation.js';
    $__v = is_file($__path) ? filemtime($__path) : 1;
    echo '<script src="'.h(base_url().'assets/js/ds-foundation.js').'?v='.(int)$__v.'"></script>';
}

// $icon ve $actionsHtml BİLEREK escape edilmiyor (ikon/aksiyon HTML'i taşımak için) — bu
// parametrelere yalnızca geliştirici-kontrollü sabit string/HTML geçilmeli, ASLA $_GET/$_POST/DB
// verisi. Kullanıcı/veri kaynaklı her şey (title, subtitle) zaten h() ile escape ediliyor.
// $bordered: orijinal projede iki panel-head varyantı var — düz (border yok, çoğunluk, ~47
// dosya) ve `.page-header` eklentili (border-bottom'lu, azınlık, ~5 dosya). Varsayılan false
// (düz) daha yaygın deseni yansıtır; border'lı ekranlar (dashboard/sales/purchase/finance/
// contact_view tarzı) $bordered=true geçmeli. DS-002A code-review'da (Ece) bu ayrımın
// gözetilmediği, tüm ekranlara koşulsuz border uygulandığı tespit edilip düzeltildi.
// PX-002 FAZ 2A EKİ (2026-07-17): $df=true → df-page-header (Compact Mode, DF-PageHeader/
// DF-PageTitle/DF-PageSubtitle). $df=false VARSAYILAN — 8 canlı çağrı (external/mytasks/sales/
// purchase/contact_view/mytask_new/task_view/finance) birebir eski ds-page-header çıktısını
// almaya devam eder, davranış hiç değişmedi (ds_button()'daki aynı geriye-uyumlu desen).
function ds_page_header($title, $icon='', $subtitle='', $actionsHtml='', $bordered=false, $df=false){
    if($df){
        echo '<div class="df-page-header'.($bordered?' df-page-header--bordered':'').'">';
        echo '<div class="df-page-header-id">';
        if($icon!=='') echo '<span class="df-page-header-icon">'.$icon.'</span>';
        echo '<div><h1 class="df-page-title">'.h($title).'</h1>';
        if($subtitle!=='') echo '<div class="df-page-subtitle">'.h($subtitle).'</div>';
        echo '</div></div>';
        if($actionsHtml!=='') echo '<div class="df-action-bar">'.$actionsHtml.'</div>';
        echo '</div>';
        return;
    }
    echo '<div class="ds-page-header'.($bordered?' ds-page-header--bordered':'').'">';
    echo '<div class="ds-page-header-id">';
    if($icon!=='') echo '<span class="ds-page-header-icon">'.$icon.'</span>';
    echo '<div class="ds-page-header-text"><h1>'.h($title).'</h1>';
    if($subtitle!=='') echo '<div class="ds-page-header-sub">'.h($subtitle).'</div>';
    echo '</div></div>';
    if($actionsHtml!=='') echo '<div class="ds-action-bar">'.$actionsHtml.'</div>';
    echo '</div>';
}

// $html BİLEREK escape edilmiyor — yalnızca geliştirici-kontrollü sabit HTML (örn. birden çok
// ds_button() çıktısının birleşimi), ASLA kullanıcı/veri kaynaklı ham string geçilmemeli.
// PX-002 FAZ 2A (2026-07-17): bu fonksiyonun kendisi 0 canlı çağrıya sahipti (grep ile
// doğrulandı) — ds_page_header()'ın kendi ds-action-bar'ı HARDCODED, bu fonksiyonu hiç
// çağırmıyor, dolayısıyla df-action-bar'a geçiş risksiz.
function ds_action_bar($html){
    echo '<div class="df-action-bar">'.$html.'</div>';
}

function ds_kpi_card($title,$value,$desc,$url=null,$tone='blue'){
    $tag = $url ? 'a' : 'div';
    $href = $url ? ' href="'.h($url).'"' : '';
    echo '<'.$tag.' class="ds-kpi-card '.h($tone).'"'.$href.'>';
    echo '<small>'.h($title).'</small>';
    echo '<strong>'.h($value).'</strong>';
    echo '<span>'.h($desc).'</span>';
    echo '</'.$tag.'>';
}

// PX-002 FAZ 2A (2026-07-17): 0 canlı çağrı (grep ile doğrulandı) — ds-badge yerine doğrudan
// df-badge basılıyor, ds_tone_map() zaten legacy renk isimlerini df tonlarına köprülüyordu.
function ds_badge($text, $tone=null){
    if($tone===null) $tone = function_exists('status_tone') ? status_tone($text) : 'gray';
    $tone = ds_tone_map($tone);
    return '<span class="df-badge df-badge--'.h($tone).'">'.h($text).'</span>';
}

// $attrs BİLEREK escape edilmiyor (data-*/onclick gibi ham HTML öznitelik eklemek için) —
// yalnızca geliştirici-kontrollü sabit string olmalı, ASLA kullanıcı/veri kaynaklı ham girdi.
// PX-001A EKİ (2026-07-16): $df=true → "df-*" (Visual Language Foundation) sınıfları üretir ve
// $label ARTIK ESCAPE EDİLMEZ (ds_icon() + metin birleşimini taşıyabilmek için — bu durumda
// yalnızca geliştirici-kontrollü sabit string/HTML geçilmeli, ASLA kullanıcı/veri kaynaklı ham
// girdi). $df=false VARSAYILAN — TÜM eski çağrılar (positional, 5 parametreye kadar) birebir eski
// davranışı üretir: "ds-*" sınıfları, $label h() ile escape. Product Owner kararı (PX-001A):
// paralel bir df_button() ailesi AÇILMADI, tek canonical API (ds_button) geriye-dönük-uyumlu
// genişletildi.
function ds_button($label,$url=null,$variant='',$extraClass='',$attrs='',$df=false){
    $prefix = $df ? 'df-btn' : 'ds-btn';
    $cls = trim($prefix.' '.($variant?($prefix.'--'.$variant):'').' '.$extraClass);
    $lbl = $df ? $label : h($label);
    if($url!==null){
        return '<a class="'.h($cls).'" href="'.h($url).'"'.($attrs?' '.$attrs:'').'>'.$lbl.'</a>';
    }
    return '<button type="button" class="'.h($cls).'"'.($attrs?' '.$attrs:'').'>'.$lbl.'</button>';
}

// PX-001A (2026-07-16): öncelik sinyali artık renkli sol çubuk değil — küçük nokta (+ metin, renk
// tek başına anlam taşımasın diye). $label verilmezse yalnızca nokta döner (satır başlığının
// yanına gömülebilir).
function ds_priority($priority, $label=null){
    $tone = ($priority==='Acil') ? 'urgent' : (($priority==='Yüksek') ? 'high' : 'normal');
    $dot = '<span class="df-priority-dot df-priority-dot--'.h($tone).'"></span>';
    if($label===null) return $dot;
    return '<span class="df-priority">'.$dot.h($label).'</span>';
}

// PX-001B (2026-07-16): checks_notes_status_tone() gibi eski 'blue/green/purple/red/gray' renk
// isimleri döndüren fonksiyonlarla df-badge (success/warning/danger/info) arasında köprü. Bilinçli
// olarak checks_notes_lib.php'ye DEĞİL buraya kondu — bu sprintin kapsamı checks_notes_* mantığını
// kapsamıyor, bu sadece görsel bir eşleme (Ece/Elif review notu — task_view.php + mobile'de
// tekrarlanan aynı diziyi tek yere çıkarır).
// PX-002 FAZ 2A EKİ (2026-07-17): status_tone() (boot.php) 'yellow'/'orange'/'teal' de
// döndürebiliyor (Onay Bekliyor/Dışarıda/Montajda) — önceden ikisi de sessizce 'info'ya
// düşüyordu (df-badge'in 0 canlı çağrısı olduğu için fark edilmemişti). Sadece EKLEME —
// mevcut 5 eşleme DEĞİŞMEDİ, task_view.php/mobile/task_view.php'nin checks_notes_status_tone()
// çağrıları etkilenmez.
function ds_tone_map($legacyTone){
    $map=['blue'=>'info','green'=>'success','purple'=>'info','red'=>'danger','gray'=>'info',
        'yellow'=>'warning','orange'=>'warning','teal'=>'info'];
    return $map[$legacyTone] ?? 'info';
}

// DS-003A (2026-07-16): self-hosted SVG ikon altyapısı — harici CDN/istek yok. $name SABİT bir
// whitelist dizisine karşı aranır, asla kullanıcı/veri kaynaklı ham SVG/path enjekte edilmez.
// İkon varsayılan olarak dekoratiftir (aria-hidden) — yalnızca metin/etiketle birlikte kullanılır.
// İkon TEK içerik olarak bir buton/linke konursa çağıran taraf aria-label eklemeli. Hiçbir ekran
// bugün bu fonksiyonu çağırmıyor (bkz. Visual Language Foundation, madde 9) — ekran uygulamaları
// PX-001A ve sonraki sprintlerin işi.
function ds_icon($name, $size=20, $class=''){
    static $__ds_icons = [
        'check'=>'<polyline points="4 13 10 19 20 6"/>',
        'plus'=>'<line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>',
        'close'=>'<line x1="6" y1="6" x2="18" y2="18"/><line x1="18" y1="6" x2="6" y2="18"/>',
        'chevron-right'=>'<polyline points="9 6 15 12 9 18"/>',
        'chevron-down'=>'<polyline points="6 9 12 15 18 9"/>',
        'edit'=>'<path d="M4 20v-3.5L16 5a2 2 0 0 1 3 3L7.5 19.5 4 20z"/>',
        'trash'=>'<polyline points="4 7 20 7"/><path d="M6 7v13a1 1 0 0 0 1 1h10a1 1 0 0 0 1-1V7M9 7V4h6v3"/>',
        'search'=>'<circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.5" y2="16.5"/>',
        'bell'=>'<path d="M18 8a6 6 0 1 0-12 0c0 6-2.5 7-2.5 7h17S18 14 18 8z"/><path d="M10 21a2 2 0 0 0 4 0"/>',
        'calendar'=>'<rect x="4" y="5" width="16" height="16" rx="2"/><line x1="4" y1="10" x2="20" y2="10"/><line x1="8" y1="3" x2="8" y2="7"/><line x1="16" y1="3" x2="16" y2="7"/>',
        'phone'=>'<path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2z"/>',
        'send'=>'<line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/>',
        'menu-dots'=>'<circle cx="6" cy="12" r="1.4"/><circle cx="12" cy="12" r="1.4"/><circle cx="18" cy="12" r="1.4"/>',
        'user'=>'<circle cx="12" cy="8" r="3.5"/><path d="M5 20c0-3.5 3-6 7-6s7 2.5 7 6"/>',
        'home'=>'<path d="M4 11 12 4l8 7"/><path d="M6 10v9h12v-9"/>',
        'info'=>'<circle cx="12" cy="12" r="9"/><line x1="12" y1="8" x2="12" y2="13"/><circle cx="12" cy="16" r="0.6" fill="currentColor"/>',
        'filter'=>'<line x1="4" y1="6" x2="20" y2="6"/><line x1="7" y1="12" x2="17" y2="12"/><line x1="10" y1="18" x2="14" y2="18"/>',
        // PX-002 FAZ 2A EKİ (2026-07-17) — Compact Mode'da emoji ikon kalmasın diye eklenen
        // eksik karşılıklar (madde 6): 📋→briefcase, 👥→users, 💬→chat, ☰→menu, 📦→box, 💰→wallet.
        'briefcase'=>'<rect x="3" y="7" width="18" height="13" rx="2"/><path d="M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="3" y1="12" x2="21" y2="12"/>',
        'users'=>'<circle cx="9" cy="8" r="3.2"/><path d="M3 20c0-3.2 2.7-5.5 6-5.5s6 2.3 6 5.5"/><path d="M16 8.2a2.8 2.8 0 1 1 0 5.6"/><path d="M16.5 14.6c2.5.3 4.5 2.3 4.5 5.4"/>',
        'chat'=>'<path d="M4 5h16v11H8l-4 4z"/>',
        'menu'=>'<line x1="4" y1="7" x2="20" y2="7"/><line x1="4" y1="12" x2="20" y2="12"/><line x1="4" y1="17" x2="20" y2="17"/>',
        'box'=>'<path d="M3 8l9-5 9 5-9 5-9-5z"/><path d="M3 8v9l9 5 9-5V8"/><line x1="12" y1="13" x2="12" y2="22"/>',
        'wallet'=>'<rect x="3" y="6" width="18" height="13" rx="2"/><path d="M3 10h18"/><circle cx="16.5" cy="14.5" r="1.1" fill="currentColor" stroke="none"/>',
        // PX-002 FAZ 2B-ii EKİ (2026-07-17) — Web Rail'in 5 kategori ikonu + Hesap/Çıkış için
        // eksik 3 karşılık. Yeni harici ikon kütüphanesi eklenmedi, aynı stroke/viewBox stiliyle.
        'tag'=>'<path d="M20 13.5 12.5 21 3 11.5V3h8.5L20 11.5a2 2 0 0 1 0 2z"/><circle cx="8" cy="8" r="1.3" fill="currentColor" stroke="none"/>',
        'settings'=>'<circle cx="12" cy="12" r="3"/><path d="M19.4 13a7.97 7.97 0 0 0 0-2l2-1.5-2-3.4-2.4 1a8 8 0 0 0-1.7-1L15 3h-4l-.3 2.6a8 8 0 0 0-1.7 1l-2.4-1-2 3.4L6.6 11a8 8 0 0 0 0 2l-2 1.5 2 3.4 2.4-1a8 8 0 0 0 1.7 1L11 21h4l.3-2.6a8 8 0 0 0 1.7-1l2.4 1 2-3.4-2-1.5z"/>',
        'logout'=>'<path d="M9 4H6a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h3"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/>',
    ];
    if(!isset($__ds_icons[$name])) return '';
    $size = (int)$size; if($size<1) $size=20;
    $cls = trim('df-icon '.$class);
    return '<span class="'.h($cls).'" style="width:'.$size.'px;height:'.$size.'px" aria-hidden="true"><svg viewBox="0 0 24 24">'.$__ds_icons[$name].'</svg></span>';
}

// PX-002 FAZ 2A (2026-07-17): 0 canlı çağrı — ds-table-wrap/ds-table yerine df-table-wrap/
// df-table basılıyor (DF-Table/DF-DataGrid — ayrı bir DataGrid ihtiyacı bulunamadı, Table'a
// katlandı, bkz. Dead Component Kararı).
function ds_table_open($headers){
    echo '<div class="df-table-wrap"><table class="df-table"><thead><tr>';
    foreach($headers as $__h) echo '<th>'.h($__h).'</th>';
    echo '</tr></thead><tbody>';
}
function ds_table_close(){
    echo '</tbody></table></div>';
}

// PX-002 FAZ 2A EKİ (2026-07-17) — DF-Alert. $type: info|success|warning|danger. role="alert"
// ekranı okuyan yardımcı teknolojiler için (madde 13 erişilebilirlik). $message h() ile
// escape edilir — kullanıcı/veri kaynaklı metin güvenle geçilebilir.
function ds_alert($type, $message){
    if(!in_array($type, ['info','success','warning','danger'], true)) $type = 'info';
    return '<div class="df-alert df-alert--'.h($type).'" role="alert">'.h($message).'</div>';
}

// DF-EmptyState — CSS (df-empty) DS-003A'dan beri vardı, PHP helper'ı yoktu. $icon geliştirici-
// kontrollü ds_icon() çıktısı olmalı (escape edilmez), $title/$desc kullanıcı/veri kaynaklı
// olabilir (h() ile escape edilir).
function ds_empty_state($title, $desc=null, $icon=null, $error=false){
    echo '<div class="df-empty'.($error?' df-empty--error':'').'">';
    if($icon) echo '<div class="df-empty-icon">'.$icon.'</div>';
    echo '<div class="df-empty-title">'.h($title).'</div>';
    if($desc) echo '<div class="df-empty-desc">'.h($desc).'</div>';
    echo '</div>';
}

// DF-Form / DF-FormGroup — tek satırlık label+input+help/error sarmalayıcı. $inputHtml BİLEREK
// escape edilmiyor (gerçek <input>/<select>/<textarea> markup'ı taşır — çağıran taraf kendi
// value/name özniteliklerini kendi h() ile escape etmeli, tıpkı önceki tüm ds_*'in $actionsHtml
// deseninde olduğu gibi). $label/$help/$error kullanıcı okunur metin, h() ile escape edilir.
function ds_form_field($label, $inputHtml, $help=null, $error=null){
    echo '<div class="df-form-group'.($error?' has-error':'').'">';
    echo '<label class="df-form-label">'.h($label).'</label>';
    echo $inputHtml;
    if($error) echo '<div class="df-form-error">'.h($error).'</div>';
    elseif($help) echo '<div class="df-form-help">'.h($help).'</div>';
    echo '</div>';
}

// DF-Accordion — YALNIZCA KABUK (Madde 2 sınırı). $id yoksa otomatik üretilir (aria-controls
// için); $open başlangıç durumu. Tek-açık-kalsın zorlaması burada YOK — Madde 3/4'ün işi.
// $bodyHtml BİLEREK escape edilmiyor (gerçek markup taşır, ds_action_bar/$actionsHtml deseniyle
// aynı kural).
function ds_accordion_item($title, $bodyHtml, $open=false, $id=null){
    static $__auto = 0;
    $id = $id ?: ('df-acc-'.(++$__auto));
    echo '<div class="df-accordion-item'.($open?' is-open':'').'">';
    echo '<button type="button" class="df-accordion-header" aria-expanded="'.($open?'true':'false').'" aria-controls="'.h($id).'" onclick="dfAccordionToggle(this)">';
    echo '<span>'.h($title).'</span><span class="df-accordion-chevron" aria-hidden="true"></span>';
    echo '</button>';
    echo '<div class="df-accordion-body" id="'.h($id).'">'.$bodyHtml.'</div>';
    echo '</div>';
}

// DF-Tabs / DF-FilterBar — aynı görsel dili paylaşıyor (bkz. FAZ 2A teslim raporu), ayrı bir
// FilterBar CSS'i açılmadı. $items: [['label'=>'Tümü','url'=>'jobs.php','active'=>true], ...]
// $item['url'] href olarak h() ile escape edilir, $item['label'] de h() ile escape edilir —
// tamamen veri-güvenli, geliştirici $actionsHtml deseni burada yok.
function ds_tabs($items){
    echo '<div class="df-tabs">';
    foreach($items as $__it){
        $cls = 'df-tab'.(!empty($__it['active']) ? ' df-tab--active' : '');
        echo '<a class="'.h($cls).'" href="'.h($__it['url']).'">'.h($__it['label']).'</a>';
    }
    echo '</div>';
}

// FAZ 2B-ii-R/1 (2026-07-17) — DF-ListItem. Mevcut .df-list-row CSS'i (mytasks.php'de zaten
// canlı) aynen kullanılıyor, yeni sınıf icat edilmedi. $url verilirse gerçek <a> (JS'siz de tam
// satır tıklanabilir — mytasks.php web tarafının div+onclick+closest() deseni BİLEREK
// kullanılmadı, search.php gibi satır-içi ikincil aksiyon barındırmayan listeler için <a> daha
// sağlam/erişilebilir).
// FAZ 2B-ii-R/2 DÜZELTMESİ (2026-07-17): $title/$desc R/1'de h() ile OTOMATİK escape ediliyordu —
// search.php'nin gerçek kullanımında (rule 8: "eşleşen metin güvenli highlight ile gösterilecek")
// bunun İÇİNDE ds_highlight() çıktısı (zaten <mark> taşıyan HTML) taşınması gerektiği ortaya
// çıktı. R/1'in 0 canlı çağrısı vardı, ilk gerçek tüketiciyle birlikte sözleşme netleşti:
// $title/$desc artık $metaRightHtml ile AYNI kural — BİLEREK escape edilmiyor, çağıran taraf
// ds_highlight()/h() ile kendi güvenliğini sağlamalı (diğer ds_*'in $actionsHtml deseniyle aynı).
// search.php'de HER çağrı ds_highlight() üzerinden geçiyor (boş sorguda bile içeride h() çalışır,
// bkz. ds_highlight() — hiçbir yol escape'siz kalmıyor).
function ds_list_item($titleHtml, $url=null, $descHtml=null, $metaRightHtml=null, $chevron=true){
    $tag = $url ? 'a' : 'div';
    $href = $url ? ' href="'.h($url).'"' : '';
    echo '<'.$tag.' class="df-list-row"'.$href.'>';
    echo '<div class="df-list-row-body">';
    echo '<div class="df-list-row-title">'.$titleHtml.'</div>';
    if($descHtml !== null && $descHtml !== '') echo '<div class="df-list-row-desc">'.$descHtml.'</div>';
    echo '</div>';
    if($metaRightHtml !== null && $metaRightHtml !== '') echo '<div class="df-list-row-meta">'.$metaRightHtml.'</div>';
    if($url && $chevron) echo ds_icon('chevron-right', 16, 'df-list-row-chevron');
    echo '</'.$tag.'>';
}

// FAZ 2B-ii-R/1 (2026-07-17) — DF-Match. search_lib.php::search_hl() (web+mobil ortak, aktif
// kullanımda) BİLEREK değiştirilmedi — bu R/1'in "sıfır sayfa değişikliği" sınırının dışında
// kalır. ds_highlight() ayrı, yeni bir fonksiyon: aynı güvenli davranış (önce htmlspecialchars(),
// sonra eşleşen kısmı sarmalar) ama inline style yerine df-match sınıfı basar. $q boşsa metin
// düz döner. Bugün 0 canlı çağrı — FAZ 2B-ii-R/2'de search.php web tarafı search_hl() yerine
// buna geçecek (mobil search.php'ye dokunulmuyor, o search_hl()'i kullanmaya devam edecek).
function ds_highlight($text, $q){
    $text = h((string)$text);
    $q = trim((string)$q);
    if($q === '') return $text;
    return preg_replace('/('.preg_quote(h($q), '/').')/iu', '<mark class="df-match">$1</mark>', $text);
}
