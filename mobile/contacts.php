<?php
require_once 'common.php';

// active kolonu güvencesi
try{
    db()->exec("ALTER TABLE contacts ADD COLUMN active TINYINT(1) NOT NULL DEFAULT 1");
}catch(Throwable $e){}

topx('Cariler');

$showPassive=!empty($_GET['show_passive']);

echo '<div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:8px">'
    .ds_button(ds_icon('plus',15).' Yeni Cari','contact_new.php','primary','','style="flex:1;justify-content:center;min-width:110px"',true)
    .ds_button('Tahsilat','collection.php','secondary','','style="flex:1;justify-content:center;min-width:90px"',true)
    .ds_button('Satış','sales.php','secondary','','style="flex:1;justify-content:center;min-width:90px"',true)
    .ds_button('Cari Raporlar','contacts_report.php','secondary','','style="flex:1;justify-content:center;min-width:110px"',true)
    .'</div>';
echo '<div style="margin-bottom:12px">'.ds_button($showPassive?'Sadece Aktif':'Pasif Dahil','contacts.php?show_passive='.($showPassive?'0':'1'),'ghost','df-btn--sm','',true).'</div>';

// CARİ MODÜL İÇİ ARAMA (2026-07-19, Product Owner kararı) — 5.000+ cari ölçeği için isim/yetkili
// kişi/telefon araması. Statik liste (ilk 100, hızlı SSR) VARSAYILAN görünüm olarak kalır — arama
// veya Müşteri/Tedarikçi filtresi aktif olduğunda contact_search_ajax.php'ye (Finans cari picker
// ile TEK ortak AJAX kaynağı) geçilir, binlerce cari asla tek seferde DOM'a basılmaz.
?>
<div class="df-panel" style="margin-bottom:12px">
  <input type="text" id="cListQuery" autocomplete="off" placeholder="İsim, yetkili kişi veya telefon ile ara…">
  <div class="df-tabs" id="cListScope" style="margin-top:8px">
    <button type="button" class="df-tab df-tab--active" data-scope="all" onclick="cListSetScope(this,'all')">Tümü</button>
    <button type="button" class="df-tab" data-scope="customers" onclick="cListSetScope(this,'customers')">Müşteriler</button>
    <button type="button" class="df-tab" data-scope="suppliers" onclick="cListSetScope(this,'suppliers')">Tedarikçiler</button>
  </div>
</div>

<div id="cListStatic">
<?php
try{
    $sql = $showPassive
        ? "SELECT id,name,type,phone,active FROM contacts ORDER BY name LIMIT 100"
        : "SELECT id,name,type,phone,active FROM contacts WHERE (active IS NULL OR active=1) ORDER BY name LIMIT 100";
    $rows=db()->query($sql)->fetchAll();
    if(!$rows) ds_empty_state('Kayıtlı cari yok.', null, ds_icon('users',20));
    foreach($rows as $r){
        $isPassive=isset($r['active']) && (int)$r['active']===0;
        echo '<a href="contact_view.php?id='.(int)$r['id'].'" class="df-panel" style="display:block;margin-top:10px;text-decoration:none;color:inherit'.($isPassive?';opacity:.55':'').'">';
        echo '<div style="display:flex;justify-content:space-between;gap:8px;align-items:center">';
        echo '<div class="df-list-row-title">'.h($r['name']).'</div>';
        if($isPassive) echo ds_badge('Pasif','gray');
        echo '</div>';
        echo '<div class="df-list-row-meta" style="margin-top:4px"><span>'.h($r['type']).'</span>'.(!empty($r['phone'])?'<span>'.ds_icon('phone',13).' '.h($r['phone']).'</span>':'').'</div>';
        echo '</a>';
    }
}catch(Throwable $e){
    echo ds_alert('danger',$e->getMessage());
}
?>
</div>
<div id="cListSearchResults" hidden></div>

<script>
var cListScope='all';
var cListTimer=null;
function cListSetScope(btn,scope){
  document.querySelectorAll('#cListScope .df-tab').forEach(function(b){ b.classList.toggle('df-tab--active', b.dataset.scope===scope); });
  cListScope=scope;
  cListRun();
}
function cListRun(){
  var q=document.getElementById('cListQuery').value.trim();
  var staticBox=document.getElementById('cListStatic');
  var resBox=document.getElementById('cListSearchResults');
  if(q==='' && cListScope==='all'){ staticBox.hidden=false; resBox.hidden=true; return; }
  staticBox.hidden=true; resBox.hidden=false;
  resBox.innerHTML='<div class="df-panel" style="text-align:center;color:var(--df-ink-500,#94a3b8)">Aranıyor…</div>';
  fetch('../contact_search_ajax.php?q='+encodeURIComponent(q)+'&scope='+encodeURIComponent(cListScope), {headers:{'Accept':'application/json'}})
    .then(function(r){ return r.json(); })
    .then(function(data){
      if(!data.ok || !data.contacts.length){ resBox.innerHTML='<div class="df-panel" style="text-align:center;color:var(--df-ink-500,#94a3b8)">Sonuç yok.</div>'; return; }
      resBox.innerHTML=data.contacts.map(function(c){
        return '<a href="contact_view.php?id='+c.id+'" class="df-panel" style="display:block;margin-top:10px;text-decoration:none;color:inherit">'
          +'<div class="df-list-row-title">'+esc(c.name)+'</div>'
          +'<div class="df-list-row-meta" style="margin-top:4px"><span>'+esc(c.type||'')+'</span>'+(c.phone?'<span>'+esc(c.phone)+'</span>':'')+'</div>'
          +'</a>';
      }).join('');
    })
    .catch(function(){ resBox.innerHTML='<div class="df-panel" style="text-align:center;color:var(--df-ink-500,#94a3b8)">Bağlantı hatası.</div>'; });
}
function esc(s){ return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
document.getElementById('cListQuery').addEventListener('input', function(){
  clearTimeout(cListTimer);
  cListTimer=setTimeout(cListRun, 250);
});
</script>
<?php
botx();
