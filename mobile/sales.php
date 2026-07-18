<?php
require_once 'common.php';
require_once __DIR__.'/../stock_lib.php';
require_once __DIR__.'/../cpa_allocation_lib.php';
$pdo=db();
$cid=(int)($_GET['contact_id'] ?? 0);
$ok=''; $er='';
$stockShortage = null; // KONTROLLÜ NEGATİF STOK POLİTİKASI (2026-07-11) — bkz. ../stock_lib.php

if($_SERVER['REQUEST_METHOD']==='POST'){
    try{
        $confirmNegativeStock = !empty($_POST['allow_negative_stock']);
        if(isset($_POST['delete_sale'])){
            // Satış silme (PRG: POST → işlem → redirect)
            if(!can_edit_delete()) throw new Exception('Silme için yetkiniz yok.');
            $saleId=(int)$_POST['id'];
            $res=stock_reverse_sale($pdo,$saleId);
            if($res['ok']){
                $_SESSION['sales_ok']=$res['message'];
            }else{
                $_SESSION['sales_er']=$res['message'];
            }
            redirect('sales.php');
        }elseif(isset($_POST['edit_id'])){
            // Satış düzenleme (2026-07-10, migration 043: satır bazlı fiyat/KDV altyapısı — web ile aynı mantık)
            if(!can_edit_delete()) throw new Exception('Düzenleme için yetkiniz yok.');
            $editId=(int)$_POST['edit_id'];
            $elig=stock_can_edit_sale($pdo,$editId);
            if(!$elig['editable']) throw new Exception($elig['reason']);
            try{
                $res=stock_update_sale(
                    $pdo,$editId,(int)$_POST['contact_id'],
                    $_POST['stock_item_id'] ?? [],$_POST['quantity'] ?? [],$_POST['unit_price'] ?? [],$_POST['vat_rate'] ?? [],
                    'Mobil satış', $confirmNegativeStock
                );
                if($res['ok']){ $ok=$res['message']; }else{ $er=$res['message']; }
            }catch(StockShortageException $e){
                $stockShortage = $e->shortages;
            }
        }else{
            // Yeni satış kaydı — bir cariye BİRDEN FAZLA ürün satırı (sepet) eklenebilir
            // (2026-07-03 kullanıcı isteği, web ile aynı mantık — bkz. ../sales.php).
            //
            // FİNANS ÇEKİRDEK DÜZELTMESİ (2026-07-10): satış ekranı tahsilat YAPMAZ. Ödeme yöntemi
            // seçimi kaldırıldı — her satış cariye açık borç oluşturur, durum her zaman "Bekliyor",
            // kasa/banka/kart hiçbir zaman etkilenmez.
            $contact=(int)$_POST['contact_id'];
            $ids=$_POST['stock_item_id'] ?? [];
            $qtys=$_POST['quantity'] ?? [];
            $prices=$_POST['unit_price'] ?? [];
            $vatRates=$_POST['vat_rate'] ?? [];

            if(!$contact) throw new Exception('Cari seçin.');

            try{
                $built=stock_sale_build_lines($pdo,$ids,$qtys,$prices,$vatRates,[],$confirmNegativeStock);
            }catch(StockShortageException $e){
                $stockShortage = $e->shortages;
                $built = null;
            }

            if($built){
            $lines=$built['lines'];
            $grandTotal=$built['grand_total']; $grandVat=$built['grand_vat'];
            $profitTotal=$built['profit_total']; $descParts=$built['desc_parts']; $desc=$built['desc'];

            // Sepetteki BÜTÜN satırlar tek transaction içinde işlenir (2026-07-03 düzeltmesi —
            // web sales.php ile aynı gerekçe: çoklu ürüne geçilince yarım-işlem riski doğdu).
            $pdo->beginTransaction();
            try{
                // 1) Stok düş (her satır için)
                foreach($lines as $l){
                    $pdo->prepare("UPDATE stock_items SET quantity=quantity-? WHERE id=?")->execute([$l['qty'],$l['item']['id']]);
                }

                // 2) Finans hareketi ÖNCE oluşturulur (sepetin TOPLAMI ile) — id'si stok
                // hareketlerine kesin referans olarak yazılacak. Kasa/banka/kart HİÇBİR ZAMAN
                // etkilenmez (account_id=NULL), durum her zaman "Bekliyor".
                $pdo->prepare("INSERT INTO finance_movements(contact_id,direction,amount,vat_rate,vat_amount,payment_channel,account_id,status,movement_date,description,movement_type)
                    VALUES(?,'in',?,?,?,NULL,NULL,'Bekliyor',?,?,'mobile_sale')")
                    ->execute([$contact,$grandTotal,count($lines)===1?($lines[0]['vat_rate']?:null):null,$grandVat,date('Y-m-d'),$desc]);
                $financeMovementId=(int)$pdo->lastInsertId();

                // 3) Her satır için stok hareketi — hepsi aynı finance_movement_id ile, birim
                // fiyat/KDV satır bazında da kaydedilir (migration 043 — düzenleme için gerekli;
                // kolonlar henüz yoksa stock_insert_sale_movement() eski şemaya güvenle düşer)
                foreach($lines as $l){
                    stock_insert_sale_movement($pdo, $l['item']['id'], $financeMovementId, $l['qty'], $l['price'], $l['vat_rate'], 'Satış', 'Mobil satış');
                }

                $pdo->commit();
            }catch(Throwable $e){
                $pdo->rollBack();
                throw $e;
            }

            // 4) Log
            try{ if(function_exists('activity_log')) activity_log('Satış','Mobil',$desc.' '.mm($grandTotal).' (kâr '.mm($profitTotal).')','Açık borç','sale',$lines[0]['item']['id'],'sales.php','🧾'); }catch(Throwable $e){}

            $kz = $profitTotal>=0 ? ('Kâr: '.mm($profitTotal)) : ('Zarar: '.mm(-$profitTotal));
            $ok=implode(', ',$descParts).' satıldı: '.mm($grandTotal).($grandVat>0?' (KDV: '.mm($grandVat).')':'').' — açık borç (Bekliyor) · '.$kz;

            // P0 SON KAPANIŞ (2026-07-18) — web sales.php ile aynı otomatik tahsis tüketimi
            // (bkz. ../sales.php içindeki not). stock/finans matematiğine hiç karışmaz.
            $__cpaConsumedParts=[];
            foreach($lines as $__l){
                $__consumed=cpa_alloc_consume_for_sale($pdo, $u['id']??0, $financeMovementId, $contact, $__l['item']['id'], $__l['qty']);
                if($__consumed>0) $__cpaConsumedParts[]=$__l['item']['name'].' x'.stock_qty_fmt($__consumed);
            }
            if($__cpaConsumedParts) $ok .= ' · 🎯 Tahsisten düşüldü: '.implode(', ',$__cpaConsumedParts);

            $cid=$contact;
            }
        }
    }catch(Throwable $e){ $er=$e->getMessage(); }
}

// Session'dan mesajları oku (PRG dari silme sonrası)
if(!empty($_SESSION['sales_ok'])){
    $ok=$_SESSION['sales_ok'];
    unset($_SESSION['sales_ok']);
}
if(!empty($_SESSION['sales_er'])){
    $er=$_SESSION['sales_er'];
    unset($_SESSION['sales_er']);
}

// Düzenleme modu (2026-07-10, migration 043): ?edit_id=N ile mevcut bir satışı forma doldurup
// düzenlemeye aç — web sales.php ile aynı mantık (bkz. ../sales.php). Stok yetersiz uyarısı
// bekliyorsa GET'ten değil, kullanıcının az önce POSTladığı ham değerlerden yeniden doldurulur.
$editId=(int)($_GET['edit_id'] ?? 0);
$editMode=null;
$justEdited = isset($_POST['edit_id']) && $ok!=='';
if($editId && !$justEdited && !$stockShortage && can_edit_delete()){
    $elig=stock_can_edit_sale($pdo,$editId);
    if($elig['editable']){
        $editMode=['id'=>$editId,'sale'=>$elig['sale'],'lines'=>$elig['lines']];
        $cid=(int)$editMode['sale']['contact_id'];
    }elseif($er===''){
        $er=$elig['reason'];
    }
}

// Görünüm durumu (2026-07-11): web sales.php ile aynı mantık — normal düzenleme modu VEYA
// stok-yetersiz onay bekleyen bir deneme, ikisi de aynı şablonu farklı veri kaynağıyla kullanır.
$isEditView = false;
$viewEditId = null;
$viewLines = [];
if($stockShortage){
    $isEditView = isset($_POST['edit_id']);
    $viewEditId = $isEditView ? (int)$_POST['edit_id'] : null;
    $cid = (int)($_POST['contact_id'] ?? 0);
    foreach($_POST['stock_item_id'] ?? [] as $i=>$pid){
        if(!$pid) continue;
        $viewLines[] = ['id'=>(int)$pid, 'qty'=>(float)($_POST['quantity'][$i] ?? 0), 'price'=>(float)($_POST['unit_price'][$i] ?? 0), 'vat'=>(float)($_POST['vat_rate'][$i] ?? 0)];
    }
}elseif($editMode){
    $isEditView = true;
    $viewEditId = $editMode['id'];
    foreach($editMode['lines'] as $l){
        $viewLines[] = ['id'=>$l['stock_item_id'], 'qty'=>$l['quantity'], 'price'=>$l['unit_price'], 'vat'=>$l['vat_rate']];
    }
}

topx($isEditView ? 'Satışı Düzenle' : 'Satış Yap');
?>
<?php if($ok): ?><?=ds_alert('success',$ok)?><?php endif; ?>
<?php if($er): ?><?=ds_alert('danger',$er)?><?php endif; ?>

<?php
$cs=$pdo->query("SELECT id,name FROM contacts ORDER BY name")->fetchAll();
$ps=$pdo->query("SELECT id,name,quantity,unit,sale_price,vat_rate FROM stock_items WHERE COALESCE(active,1)=1 ORDER BY name")->fetchAll();

// P0 CPA KULLANICI AKIŞI (2026-07-18, "satış nereden olacak?" sadeleştirme) — web sales.php ile AYNI
// GET ön-doldurma ("Müşteriye Ayrılan" ekranlarındaki "🧾 Sat" bağlantısı). $cid zaten dosyanın
// başında $_GET['contact_id']'den geliyor, burada sadece ürün satırı ekleniyor.
if(!$stockShortage && !$editMode && !empty($_GET['stock_item_id'])){
    $__gpid=(int)$_GET['stock_item_id'];
    $__gprod=null;
    foreach($ps as $__p){ if((int)$__p['id']===$__gpid){ $__gprod=$__p; break; } }
    $viewLines[] = [
        'id'=>$__gpid,
        'qty'=>(float)($_GET['qty'] ?? 1),
        'price'=>$__gprod ? (float)$__gprod['sale_price'] : 0,
        'vat'=>$__gprod && $__gprod['vat_rate']!==null ? (float)$__gprod['vat_rate'] : 20,
    ];
}
?>

<!-- JS ile dinamik satır eklenen kritik akış — #itemsBody/.row-prod/.row-qty/.row-price/.row-vat/
     .row-sub/.new-prod-box/.np-name/.np-unit class'ları JS'e SIKI bağlı (aşağıdaki <script> bloğu
     hiç değişmedi) — sadece görsel katman (df-panel/df-btn) taşındı, JS selector'larına dokunulmadı. -->
<div class="df-panel">
<?=ds_alert('info','Bu ekran tahsilat yapmaz — satış cariye açık borç (Bekliyor) olarak kaydedilir. Tahsilat "Tahsilat" ekranından ayrıca girilir.')?>
<?php if($isEditView && !$stockShortage): ?>
<div style="margin-top:10px"><?=ds_alert('info','Bu satışı düzenliyorsunuz. Kaydettiğinizde stok otomatik yeniden hesaplanır.')?></div>
<?php endif; ?>
<form method="post" style="margin-top:10px">
  <?php if($viewEditId): ?><input type="hidden" name="edit_id" value="<?=(int)$viewEditId?>"><?php endif; ?>
  <?php if($stockShortage): ?>
  <div class="df-alert df-alert--warning" style="display:block;margin-bottom:12px">
    <b><?=ds_icon('info',14)?> Mevcut stok bu satış için yetersiz.</b><br>
    İşlem tamamlanırsa aşağıdaki ürün(ler)de stok negatife düşecek:
    <ul style="margin:8px 0 8px 20px;padding:0">
    <?php foreach($stockShortage as $s): ?>
      <li><b><?=h($s['name'])?></b> — mevcut <?=h(stock_qty_fmt($s['available_stock']))?> <?=h($s['unit'])?>,
      satış <?=h(stock_qty_fmt($s['requested_qty']))?> <?=h($s['unit'])?>,
      sonuç <?=h(stock_qty_fmt($s['resulting_stock']))?> <?=h($s['unit'])?></li>
    <?php endforeach; ?>
    </ul>
    <label style="display:block;background:rgba(234,179,8,.12);border-radius:10px;padding:10px;margin-top:8px">
      <input type="checkbox" name="allow_negative_stock" value="1" style="width:auto;display:inline-block;margin-right:6px">
      Stok yetersiz olsa da devam etmek istiyorum.
    </label>
  </div>
  <?php endif; ?>
  <label>Cari (Müşteri)</label>
  <select name="contact_id" id="contactSel" required onchange="onContactChange()">
    <option value="">— Seç —</option>
    <?php foreach($cs as $c): ?><option value="<?=$c['id']?>" <?=$cid===(int)$c['id']?'selected':''?>><?=h($c['name'])?></option><?php endforeach; ?>
    <option value="__new__">➕ Listede yok — Yeni Cari Ekle…</option>
  </select>
  <div id="newContactBox" class="df-panel" style="display:none;background:rgba(37,99,235,.12);margin:6px 0 12px">
    <input type="text" id="qsContactName" placeholder="Müşteri adı">
    <button type="button" class="df-btn df-btn--primary" style="width:100%" onclick="quickContactSales(document.getElementById('qsContactName').value, 'Müşteri')"><?=ds_icon('check',14)?> Ekle ve Seç</button>
  </div>

  <label style="margin-top:10px;font-weight:800">Ürünler</label>
  <div id="itemsBody"></div>
  <datalist id="vatPresets">
    <option value="0"></option>
    <option value="1"></option>
    <option value="8"></option>
    <option value="10"></option>
    <option value="20"></option>
  </datalist>
  <button type="button" class="df-btn df-btn--secondary" style="width:100%;margin:8px 0" onclick="addItemRow()"><?=ds_icon('plus',14)?> Satır Ekle</button>

  <div class="df-panel" style="background:rgba(37,99,235,.18);margin:14px 0">
    <div style="display:flex;justify-content:space-between;padding:2px 0"><small class="muted">Ara Toplam</small><b id="salesSubtotal">0,00 ₺</b></div>
    <div style="display:flex;justify-content:space-between;padding:2px 0"><small class="muted">KDV</small><b id="salesVat">0,00 ₺</b></div>
    <div style="display:flex;justify-content:space-between;padding:6px 0 0;border-top:1px solid rgba(255,255,255,.15);margin-top:4px">
      <span style="font-weight:800">Genel Toplam</span><span id="tot" style="font-size:24px;font-weight:900">0,00 ₺</span>
    </div>
  </div>

  <button class="df-btn df-btn--primary df-btn--lg" style="width:100%" type="submit"><?php if($stockShortage): ?>⚠️ Onaylıyorum, Devam Et<?php elseif($isEditView): ?><?=ds_icon('check',16)?> Değişiklikleri Kaydet<?php else: ?>🧾 Satışı Tamamla (Açık Borç)<?php endif; ?></button>
  <?php if($isEditView && !$stockShortage): ?><a href="sales.php" class="df-btn df-btn--secondary" style="width:100%;margin-top:8px;justify-content:center">✕ Vazgeç</a><?php endif; ?>
</form>
</div>

<div class="df-panel" style="margin-top:14px">
  <b><?=ds_icon('box',16)?> Son Satışlar</b>
  <?php
  try{
      $recentM = $pdo->query(
          "SELECT fm.id, fm.movement_date, fm.amount, fm.vat_amount, fm.description, fm.status, fm.document_id, c.name AS cname, td.document_no
           FROM finance_movements fm
           LEFT JOIN contacts c ON c.id=fm.contact_id
           LEFT JOIN trade_documents td ON td.id=fm.document_id
           WHERE fm.movement_type='sale' OR fm.movement_type='mobile_sale'
           ORDER BY fm.id DESC LIMIT 10"
      )->fetchAll();
  }catch(Throwable $e){ $recentM=[]; }
  if(!$recentM) ds_empty_state('Henüz kayıt yok.');
  foreach($recentM as $row):
      $isDoc = !empty($row['document_id']);
      $rowEditable = !$isDoc && can_edit_delete() && stock_can_edit_sale($pdo,(int)$row['id'])['editable'];
  ?>
  <div class="df-panel" style="margin-top:10px">
    <div style="display:flex;justify-content:space-between;gap:8px;align-items:flex-start">
      <div class="df-list-row-title" style="color:var(--df-success-ink)"><?=mm($row['amount'])?></div>
      <?=ds_badge($row['status'])?>
    </div>
    <div class="df-list-row-meta" style="margin-top:6px">
      <span><?=h($row['movement_date'] ?? '')?></span>
      <span><?=h($row['cname'] ?: '—')?></span>
    </div>
    <?php if($isDoc || $row['description']): ?>
    <div class="df-list-row-desc" style="margin-top:4px">
      <?php if($isDoc): ?><b><?=h($row['document_no'] ?: 'Belge')?></b> · <?php endif; ?>
      <?=h($row['description'] ?? '')?>
    </div>
    <?php endif; ?>
    <?php if($isDoc): ?>
    <div style="display:flex;gap:6px;margin-top:10px">
      <a class="df-btn df-btn--secondary df-btn--sm" href="../trade_document_view.php?id=<?=(int)$row['document_id']?>"><?=ds_icon('box',14)?> Belge</a>
    </div>
    <?php elseif(can_edit_delete()): ?>
    <div style="display:flex;gap:6px;margin-top:10px">
      <?php if($rowEditable): ?>
      <a class="df-btn df-btn--secondary df-btn--sm" href="sales.php?edit_id=<?=(int)$row['id']?>"><?=ds_icon('edit',14)?> Düzenle</a>
      <?php endif; ?>
      <form method="post" onsubmit="return confirm('Bu satış kaydı ve bağlı verileri KALICI olarak silinecek. Emin misiniz?')" style="margin:0">
        <input type="hidden" name="delete_sale" value="1">
        <input type="hidden" name="id" value="<?=(int)$row['id']?>">
        <button class="df-btn df-btn--danger df-btn--sm" type="submit"><?=ds_icon('trash',14)?></button>
      </form>
    </div>
    <?php endif; ?>
  </div>
  <?php endforeach; ?>
</div>

<script>
var PRODUCTS = <?= json_encode(array_map(function($p){
    return ['id'=>(int)$p['id'],'name'=>$p['name'],'unit'=>$p['unit'],'quantity'=>$p['quantity'],'price'=>$p['sale_price'],'vat'=>$p['vat_rate']!==null?$p['vat_rate']:20];
}, $ps), JSON_UNESCAPED_UNICODE) ?>;
// Düzenleme modunda VEYA stok-yetersiz onay bekleyen bir denemede formu dolduran veri
// (2026-07-10 migration 043 / 2026-07-11 kontrollü negatif stok politikası — web ile aynı).
var PREFILL_LINES = <?= json_encode($viewLines, JSON_UNESCAPED_UNICODE) ?>;
var rowIndex = 0;

function esc(s){ return String(s).replace(/[&<>"']/g, function(c){ return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]; }); }

function productOptionsHtml(){
    var html = '<option value="">— Seç —</option>';
    PRODUCTS.forEach(function(p){
        html += '<option value="'+p.id+'" data-price="'+(p.price||0)+'" data-vat="'+(p.vat||0)+'" data-unit="'+esc(p.unit||'')+'">'
            + esc(p.name)+' (Stok: '+(p.quantity||0)+' '+esc(p.unit||'')+')</option>';
    });
    html += '<option value="__new__">➕ Listede yok — Yeni Ürün Ekle…</option>';
    return html;
}

function addItemRow(prefill){
    var idx = rowIndex++;
    var row = document.createElement('div');
    row.className = 'df-panel';
    row.style.cssText = 'margin-bottom:10px;padding:10px';
    row.dataset.idx = idx;
    row.innerHTML =
        '<select class="row-prod" onchange="onRowProductChange(this)" style="margin-bottom:6px">'+productOptionsHtml()+'</select>'
        + '<div class="new-prod-box" style="display:none;background:rgba(37,99,235,.12);border-radius:10px;padding:8px;margin-bottom:6px">'
        + '<input type="text" class="np-name" placeholder="Ürün adı">'
        + '<input type="text" class="np-unit" placeholder="adet" value="adet">'
        + '<button type="button" class="df-btn df-btn--primary" style="width:100%" onclick="quickAddProductRow(this)">✓ Ekle ve Seç</button>'
        + '</div>'
        + '<div style="display:flex;gap:8px">'
        + '<div style="flex:1"><small class="muted">Miktar</small><input type="number" step="0.01" min="0.01" class="row-qty" value="1" oninput="calcAll()"></div>'
        + '<div style="flex:1"><small class="muted">Birim Fiyat</small><input type="number" step="0.01" min="0" class="row-price" oninput="calcAll()"></div>'
        + '<div style="flex:1"><small class="muted">KDV %</small><input type="text" inputmode="decimal" list="vatPresets" class="row-vat" value="20" oninput="calcAll()"></div>'
        + '</div>'
        + '<div style="display:flex;justify-content:space-between;align-items:center;margin-top:6px">'
        + '<span class="row-sub" style="font-weight:800">0,00 ₺</span>'
        + '<button type="button" class="df-btn df-btn--danger" onclick="removeRow(this)">🗑 Satırı Sil</button>'
        + '</div>';
    document.getElementById('itemsBody').appendChild(row);
    if(prefill){
        // Düzenleme modu: ürünün güncel varsayılan fiyatı DEĞİL, satışta o an kayıtlı olan
        // birim fiyat/KDV kullanılır (onRowProductChange bunu ezmesin diye elle set ediliyor).
        row.querySelector('.row-prod').value = prefill.id;
        row.querySelector('.new-prod-box').style.display = 'none';
        row.querySelector('.row-qty').value = prefill.qty;
        row.querySelector('.row-price').value = prefill.price;
        row.querySelector('.row-vat').value = prefill.vat;
    }
    syncHiddenInputs();
    calcAll();
}

function removeRow(btn){
    var row = btn.closest('.df-panel');
    var rows = document.querySelectorAll('#itemsBody > .df-panel');
    if(rows.length <= 1){
        row.querySelector('.row-prod').value = '';
        row.querySelector('.row-qty').value = 1;
        row.querySelector('.row-price').value = '';
        row.querySelector('.row-vat').value = 20;
    } else {
        row.remove();
    }
    syncHiddenInputs();
    calcAll();
}

function onRowProductChange(sel){
    var row = sel.closest('.df-panel');
    var box = row.querySelector('.new-prod-box');
    if(sel.value === '__new__'){
        box.style.display = 'block';
        sel.value = '';
        row.querySelector('.np-name').focus();
        return;
    }
    box.style.display = 'none';
    var opt = sel.selectedOptions[0];
    if(opt && opt.dataset.price !== undefined){
        row.querySelector('.row-price').value = opt.dataset.price;
        row.querySelector('.row-vat').value = opt.dataset.vat || 20;
    }
    syncHiddenInputs();
    calcAll();
}

function quickAddProductRow(btn){
    var row = btn.closest('.df-panel');
    var name = row.querySelector('.np-name').value;
    var unit = row.querySelector('.np-unit').value || 'adet';
    if(!name){ alert('Ürün adı girin'); return; }
    var fd = new FormData();
    fd.append('t', 'product');
    fd.append('name', name);
    fd.append('unit', unit);
    fetch('../ajax_quick_add.php', {method: 'POST', headers: {'X-CSRF-Token': window.CSRF_TOKEN}, body: fd})
        .then(r => r.json())
        .then(data => {
            if(data.ok){
                PRODUCTS.push({id: data.id, name: data.name, unit: unit, quantity: 0, price: 0, vat: 20});
                document.querySelectorAll('.row-prod').forEach(function(sel){
                    var o = document.createElement('option');
                    o.value = data.id; o.dataset.price = 0; o.dataset.vat = 20; o.dataset.unit = unit;
                    o.textContent = data.name + ' (Stok: 0 ' + unit + ')';
                    sel.insertBefore(o, sel.querySelector('option[value="__new__"]'));
                });
                var sel = row.querySelector('.row-prod');
                sel.value = data.id;
                onRowProductChange(sel);
                row.querySelector('.np-name').value = '';
                row.querySelector('.new-prod-box').style.display = 'none';
            } else {
                alert('Hata: ' + data.message);
            }
        })
        .catch(e => alert('Bağlantı hatası: ' + e));
}

// Mobil ortak css'i (common.php) select/input'lara ad koymadan otomatik genişlik/stil veriyor;
// ancak POST'ta dizi (name="x[]") gerektiği için asıl input'lar formdan AYRI tutulup submit
// anında gizli hidden alanlar olarak senkronize edilir (satır DOM yapısı basit tutulsun diye).
function syncHiddenInputs(){
    var form = document.querySelector('form');
    form.querySelectorAll('.hidden-sync').forEach(function(h){ h.remove(); });
    document.querySelectorAll('#itemsBody > .df-panel').forEach(function(row){
        ['stock_item_id','quantity','unit_price','vat_rate'].forEach(function(field, i){
            var srcClass = ['row-prod','row-qty','row-price','row-vat'][i];
            var input = document.createElement('input');
            input.type = 'hidden';
            input.className = 'hidden-sync';
            input.name = field + '[]';
            input.value = row.querySelector('.'+srcClass).value;
            form.appendChild(input);
        });
    });
}

function calcAll(){
    var subtotalAll = 0, vatAll = 0;
    document.querySelectorAll('#itemsBody > .df-panel').forEach(function(row){
        var q = parseFloat(row.querySelector('.row-qty').value) || 0;
        var p = parseFloat(row.querySelector('.row-price').value) || 0;
        var v = parseFloat(row.querySelector('.row-vat').value) || 0;
        var sub = q * p;
        var vatAmt = sub * v / 100;
        row.querySelector('.row-sub').textContent = (sub + vatAmt).toLocaleString('tr-TR', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ' ₺';
        subtotalAll += sub;
        vatAll += vatAmt;
    });
    document.getElementById('salesSubtotal').textContent = subtotalAll.toLocaleString('tr-TR', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ' ₺';
    document.getElementById('salesVat').textContent = vatAll.toLocaleString('tr-TR', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ' ₺';
    document.getElementById('tot').textContent = (subtotalAll + vatAll).toLocaleString('tr-TR', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ' ₺';
}

document.querySelector('form').addEventListener('submit', syncHiddenInputs);
if(PREFILL_LINES.length){
    PREFILL_LINES.forEach(function(l){ addItemRow(l); });
} else {
    addItemRow();
}

function onContactChange(){
    var sel=document.getElementById('contactSel');
    var box=document.getElementById('newContactBox');
    if(sel.value==='__new__'){ box.style.display='block'; sel.value=''; document.getElementById('qsContactName').focus(); }
    else box.style.display='none';
}

function quickContactSales(name, type) {
    if (!name) { alert('Ad girin'); return; }
    const fd = new FormData();
    fd.append('t', 'contact');
    fd.append('name', name);
    fd.append('contact_type', type || 'Müşteri');
    fetch('../ajax_quick_add.php', {method: 'POST', headers: {'X-CSRF-Token': window.CSRF_TOKEN}, body: fd})
        .then(r => r.json())
        .then(data => {
            if (data.ok) {
                const sel = document.getElementById('contactSel');
                const opt = document.createElement('option');
                opt.value = data.id;
                opt.textContent = data.name;
                opt.selected = true;
                sel.insertBefore(opt, sel.querySelector('option[value="__new__"]'));
                document.getElementById('qsContactName').value = '';
                document.getElementById('newContactBox').style.display='none';
            } else alert('Hata: ' + data.message);
        })
        .catch(e => alert('Hata: ' + e));
}
</script>

<?php botx(); ?>
