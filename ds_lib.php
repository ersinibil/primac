<?php
// PRIMAC OTS — DESIGN SYSTEM SPRINT 001 / PHASE A: FOUNDATION COMPONENTS (2026-07-15)
// Yeni, saf-ek (additive) PHP yardımcıları. Mevcut badge()/status_tone()/cmd_card() gibi
// fonksiyonlara DOKUNMAZ, mantıklarını TEKRARLAMAZ — ds_badge() mevcut status_tone() eşlemesini
// aynen kullanır, sadece yeni "ds-badge" CSS sınıfını basar. Hiçbir mevcut ekran bu fonksiyonları
// çağırmıyor; bu dosya bugün hiçbir sayfanın görünümünü değiştirmez — kademeli geçiş (eski
// ekranların bu standarda taşınması) sonraki bir sprintin işi.

function ds_styles(){
    echo '<link rel="stylesheet" href="'.h(base_url().'assets/css/ds-foundation.css').'?v=1">';
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
function ds_button($label,$url=null,$variant='',$extraClass='',$attrs=''){
    $cls = trim('ds-btn '.($variant?('ds-btn--'.$variant):'').' '.$extraClass);
    if($url!==null){
        return '<a class="'.h($cls).'" href="'.h($url).'"'.($attrs?' '.$attrs:'').'>'.h($label).'</a>';
    }
    return '<button type="button" class="'.h($cls).'"'.($attrs?' '.$attrs:'').'>'.h($label).'</button>';
}

function ds_table_open($headers){
    echo '<div class="ds-table-wrap"><table class="ds-table"><thead><tr>';
    foreach($headers as $__h) echo '<th>'.h($__h).'</th>';
    echo '</tr></thead><tbody>';
}
function ds_table_close(){
    echo '</tbody></table></div>';
}
