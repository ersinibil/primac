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

// $icon ve $actionsHtml BİLEREK escape edilmiyor (ikon/aksiyon HTML'i taşımak için) — bu
// parametrelere yalnızca geliştirici-kontrollü sabit string/HTML geçilmeli, ASLA $_GET/$_POST/DB
// verisi. Kullanıcı/veri kaynaklı her şey (title, subtitle) zaten h() ile escape ediliyor.
// $bordered: orijinal projede iki panel-head varyantı var — düz (border yok, çoğunluk, ~47
// dosya) ve `.page-header` eklentili (border-bottom'lu, azınlık, ~5 dosya). Varsayılan false
// (düz) daha yaygın deseni yansıtır; border'lı ekranlar (dashboard/sales/purchase/finance/
// contact_view tarzı) $bordered=true geçmeli. DS-002A code-review'da (Ece) bu ayrımın
// gözetilmediği, tüm ekranlara koşulsuz border uygulandığı tespit edilip düzeltildi.
function ds_page_header($title, $icon='', $subtitle='', $actionsHtml='', $bordered=false){
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
function ds_action_bar($html){
    echo '<div class="ds-action-bar">'.$html.'</div>';
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

function ds_badge($text, $tone=null){
    if($tone===null) $tone = function_exists('status_tone') ? status_tone($text) : 'gray';
    return '<span class="ds-badge '.h($tone).'">'.h($text).'</span>';
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
function ds_tone_map($legacyTone){
    $map=['blue'=>'info','green'=>'success','purple'=>'info','red'=>'danger','gray'=>'info'];
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
    ];
    if(!isset($__ds_icons[$name])) return '';
    $size = (int)$size; if($size<1) $size=20;
    $cls = trim('df-icon '.$class);
    return '<span class="'.h($cls).'" style="width:'.$size.'px;height:'.$size.'px" aria-hidden="true"><svg viewBox="0 0 24 24">'.$__ds_icons[$name].'</svg></span>';
}

function ds_table_open($headers){
    echo '<div class="ds-table-wrap"><table class="ds-table"><thead><tr>';
    foreach($headers as $__h) echo '<th>'.h($__h).'</th>';
    echo '</tr></thead><tbody>';
}
function ds_table_close(){
    echo '</tbody></table></div>';
}
