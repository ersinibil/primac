<?php
require_once __DIR__.'/boot.php';
require_login();
require_once __DIR__.'/stock_lib.php';

$pdo = db();
$ok  = '';
$er  = '';
$stockShortage = null; // KONTROLLÜ NEGATİF STOK POLİTİKASI (2026-07-11) — bkz. stock_lib.php

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $confirmNegativeStock = !empty($_POST['allow_negative_stock']);
        if (isset($_POST['delete_sale'])) {
            // Satış silme
            if (!can_edit_delete()) throw new Exception('Silme için yetkiniz yok.');
            $saleId = (int)$_POST['id'];
            $res = stock_reverse_sale($pdo, $saleId);
            if ($res['ok']) {
                $ok = $res['message'];
            } else {
                $er = $res['message'];
            }
        } elseif (isset($_POST['edit_id'])) {
            // Satış düzenleme (2026-07-10, migration 043: satır bazlı fiyat/KDV altyapısı)
            if (!can_edit_delete()) throw new Exception('Düzenleme için yetkiniz yok.');
            $editId = (int)$_POST['edit_id'];
            $elig = stock_can_edit_sale($pdo, $editId);
            if (!$elig['editable']) throw new Exception($elig['reason']);
            try {
                $res = stock_update_sale(
                    $pdo, $editId, (int)$_POST['contact_id'],
                    $_POST['stock_item_id'] ?? [], $_POST['quantity'] ?? [], $_POST['unit_price'] ?? [], $_POST['vat_rate'] ?? [],
                    'Web satış', $confirmNegativeStock
                );
                if ($res['ok']) { $ok = $res['message']; } else { $er = $res['message']; }
            } catch (StockShortageException $e) {
                $stockShortage = $e->shortages;
            }
        } else {
            // Yeni satış kaydı — bir cariye TEK seferde BİRDEN FAZLA ürün satırı (sepet)
            // eklenebilir (2026-07-03 kullanıcı isteği: "bir kişiye bir firmaya birden fazla
            // ürün satılabilir"). Tüm satırlar tek finance_movements kaydında toplanır, her
            // satır kendi stock_movements kaydını (aynı finance_movement_id ile) alır.
            //
            // FİNANS ÇEKİRDEK DÜZELTMESİ (2026-07-10): satış ekranı tahsilat YAPMAZ. Ödeme
            // yöntemi seçimi kaldırıldı — her satış cariye açık borç oluşturur, durum her zaman
            // "Bekliyor", kasa/banka/kart hiçbir zaman etkilenmez. Borcun kapanması SADECE
            // Tahsilat ekranından (finance_new.php / mobile/collection.php) yapılır.
            $contact  = (int)$_POST['contact_id'];
            $ids      = $_POST['stock_item_id'] ?? [];
            $qtys     = $_POST['quantity'] ?? [];
            $prices   = $_POST['unit_price'] ?? [];
            $vatRates = $_POST['vat_rate'] ?? [];

            if (!$contact) throw new Exception('Cari seçin.');

            // Oluşturma mantığı stock_lib.php::stock_create_sale()'e taşındı (Flow Unification 001,
            // 2026-07-11 — trade_document_new.php'nin de aynı çekirdeği kullanabilmesi için); davranış/
            // görünüm birebir korunuyor, sadece DB yazımı ortak fonksiyonda.
            try {
                $res = stock_create_sale($pdo, $contact, $ids, $qtys, $prices, $vatRates, 'Web satış', $confirmNegativeStock);
            } catch (StockShortageException $e) {
                $stockShortage = $e->shortages;
                $res = null;
            }

            if ($res) {
            $lines = $res['lines'];
            $grandTotal = $res['grand_total']; $grandVat = $res['grand_vat'];
            $profitTotal = $res['profit_total']; $descParts = $res['desc_parts']; $desc = $res['desc'];

            try {
                if (function_exists('activity_log')) {
                    activity_log('Satış', 'Web', $desc . ' ' . money($grandTotal) . ' (kâr ' . money($profitTotal) . ')', 'Açık borç', 'sale', $lines[0]['item']['id'], 'sales.php', '🧾');
                }
            } catch (Throwable $e) {}

            $kz = $profitTotal >= 0
                ? 'Kâr: ' . money($profitTotal)
                : 'Zarar: ' . money(-$profitTotal);

            $ok = h(implode(', ', $descParts)) . ' satıldı: <b>' . money($grandTotal) . '</b>' . ($grandVat > 0 ? ' (KDV dahil, KDV: ' . money($grandVat) . ')' : '') . ' — cariye açık borç olarak kaydedildi (Bekliyor) &mdash; ' . $kz;
            }
        }
    } catch (Throwable $e) {
        $er = $e->getMessage();
    }
}

// Düzenleme modu (2026-07-10, migration 043): ?edit_id=N ile mevcut bir satışı forma doldurup
// düzenlemeye aç. Başarılı bir düzenleme sonrası ($ok set) forma dönmez, temiz "yeni satış"
// görünümüne geçer (aksi halde başarısız denemede DB'deki ORİJİNAL veriyle tekrar dolu açılır).
// Stok yetersiz uyarısı bekliyorsa ($stockShortage) GET'ten değil, kullanıcının az önce
// POSTladığı ham değerlerden yeniden doldurulur (aşağıda).
$editId = (int)($_GET['edit_id'] ?? 0);
$editMode = null;
$justEdited = isset($_POST['edit_id']) && $ok !== '';
if ($editId && !$justEdited && !$stockShortage && can_edit_delete()) {
    $elig = stock_can_edit_sale($pdo, $editId);
    if ($elig['editable']) {
        $editMode = ['id' => $editId, 'sale' => $elig['sale'], 'lines' => $elig['lines']];
    } elseif ($er === '') {
        $er = $elig['reason'];
    }
}

// Görünüm durumu (2026-07-11): normal düzenleme modu VEYA stok-yetersiz onay bekleyen bir
// deneme — ikisi de aynı "düzenleme görünümü" şablonunu kullanır, sadece veri kaynağı farklı.
$isEditView = false;
$viewEditId = null;
$viewContactId = null;
$viewLines = [];
if ($stockShortage) {
    $isEditView = isset($_POST['edit_id']);
    $viewEditId = $isEditView ? (int)$_POST['edit_id'] : null;
    $viewContactId = (int)($_POST['contact_id'] ?? 0);
    foreach ($_POST['stock_item_id'] ?? [] as $i => $pid) {
        if (!$pid) continue;
        $viewLines[] = [
            'id' => (int)$pid,
            'qty' => (float)($_POST['quantity'][$i] ?? 0),
            'price' => (float)($_POST['unit_price'][$i] ?? 0),
            'vat' => (float)($_POST['vat_rate'][$i] ?? 0),
        ];
    }
} elseif ($editMode) {
    $isEditView = true;
    $viewEditId = $editMode['id'];
    $viewContactId = (int)$editMode['sale']['contact_id'];
    foreach ($editMode['lines'] as $l) {
        $viewLines[] = ['id'=>$l['stock_item_id'], 'qty'=>$l['quantity'], 'price'=>$l['unit_price'], 'vat'=>$l['vat_rate']];
    }
}

// Listeler
$contacts = $pdo->query("SELECT id,name FROM contacts ORDER BY name")->fetchAll();
$products  = $pdo->query(
    "SELECT id,name,quantity,unit,sale_price,avg_cost,purchase_price,vat_rate FROM stock_items WHERE COALESCE(active,1)=1 ORDER BY name"
)->fetchAll();

require_once __DIR__.'/layout_top.php';
?>

<?php
$__actions = ds_button('Satın Alma', 'purchase.php', 'secondary', '', '', true)
    . ds_button('Satış Belgesi', 'trade_document_new.php?type=sale', 'secondary', '', '', true);
ds_page_header('Hızlı Satış', ds_icon('tag',24), '', $__actions, true, true);
?>

<?php if ($ok): ?><?=ds_alert('success',$ok)?><?php endif; ?>
<?php if ($er): ?><?=ds_alert('danger',$er)?><?php endif; ?>

<div style="display:grid;grid-template-columns:1fr;gap:20px;align-items:start">

  <!-- Sol: form (JS ile dinamik satır eklenen kritik akış — .row-prod/.row-qty/.row-price/
       .row-vat/.row-sub/#itemsBody class'ları JS'e SIKI bağlı, DEĞİŞTİRİLMEDİ; RELEASE 0.9
       (2026-07-17) DS migration'ı tamamlandı — sadece görsel katman (class/inline stil) DS
       token'larına/df-btn'e taşındı, JS hiçbir selector'ı değişmedi, iş mantığına dokunulmadı.) -->
  <div class="df-card">
    <h2 class="df-section-title" style="margin:0 0 var(--df-space-3)"><?= $isEditView ? '✏️ Satışı Düzenle' : 'Satış Formu' ?></h2>
    <div class="df-alert df-alert--info" style="margin-bottom:var(--df-space-3)">
      Bu ekran tahsilat yapmaz — satış cariye açık borç (Bekliyor) olarak kaydedilir. Tahsilat
      <a href="finance_new.php?direction=in">Tahsilat ekranından</a> ayrıca girilir.
    </div>
    <form method="post" id="salesForm">
      <?php if ($viewEditId): ?><input type="hidden" name="edit_id" value="<?= (int)$viewEditId ?>"><?php endif; ?>
      <?php if ($stockShortage): ?>
      <div class="df-alert df-alert--warning" style="margin-bottom:var(--df-space-3)">
        <b>⚠️ Mevcut stok bu satış için yetersiz.</b><br>
        İşlem tamamlanırsa aşağıdaki ürün(ler)de stok negatife düşecek — KONTROLLÜ NEGATİF STOK
        POLİTİKASI gereği, devam etmek için onayınız gerekiyor:
        <ul style="margin:8px 0 8px 20px;padding:0">
          <?php foreach ($stockShortage as $s): ?>
          <li><b><?= h($s['name']) ?></b> — mevcut <?= h(stock_qty_fmt($s['available_stock'])) ?> <?= h($s['unit']) ?>,
              satış <?= h(stock_qty_fmt($s['requested_qty'])) ?> <?= h($s['unit']) ?>,
              işlem sonrası <b style="color:var(--df-danger)"><?= h(stock_qty_fmt($s['resulting_stock'])) ?> <?= h($s['unit']) ?></b></li>
          <?php endforeach; ?>
        </ul>
        <label style="display:block;background:var(--df-surface);border-radius:var(--df-radius-md);padding:var(--df-space-2);margin-top:var(--df-space-2)">
          <input type="checkbox" name="allow_negative_stock" value="1" style="width:auto;display:inline-block;margin-right:6px">
          Stok yetersiz olsa da bu satışa devam etmek istiyorum (satın alım daha sonra tamamlanacak).
        </label>
      </div>
      <?php endif; ?>

      <?php
      $__contactOpts = '';
      foreach ($contacts as $c) { $__contactOpts .= '<option value="'.(int)$c['id'].'" '.($viewContactId===(int)$c['id'] ? 'selected' : '').'>'.h($c['name']).'</option>'; }
      ds_form_field('Cari (Müşteri)', '<select name="contact_id" id="contactSel" required onchange="onContactChange()"><option value="">— Seç —</option>'.$__contactOpts.'<option value="__new__">➕ Listede yok — Yeni Cari Ekle…</option></select>');
      ?>
      <div id="newContactBox" class="df-card" style="display:none;background:var(--df-accent-soft);border-color:transparent;margin-top:var(--df-space-2)">
        <input type="text" id="contactName" placeholder="Müşteri adı" style="margin-bottom:var(--df-space-2)">
        <select id="contactType" style="margin-bottom:var(--df-space-2)">
          <option>Müşteri</option><option>Tedarikçi</option><option>Diğer</option>
        </select>
        <button type="button" class="df-btn df-btn--primary" style="width:100%" onclick="quickAddContact(document.getElementById('contactName').value, document.getElementById('contactType').value)">✓ Ekle ve Seç</button>
      </div>

      <div style="margin-top:var(--df-space-4)">
        <label style="font-weight:700;font-size:13px;color:var(--df-ink-900);display:block;margin-bottom:var(--df-space-2)">Ürünler</label>
        <div class="df-table-wrap">
        <table class="df-table">
          <thead>
            <tr>
              <th>Ürün</th>
              <th style="width:90px">Miktar</th>
              <th style="width:120px">Birim Fiyat (₺)</th>
              <th style="width:80px">KDV %</th>
              <th style="width:110px;text-align:right">Ara Toplam</th>
              <th style="width:36px"></th>
            </tr>
          </thead>
          <tbody id="itemsBody"></tbody>
        </table>
        </div>
        <datalist id="vatPresets">
          <option value="0"></option>
          <option value="1"></option>
          <option value="8"></option>
          <option value="10"></option>
          <option value="20"></option>
        </datalist>
        <button type="button" class="df-btn df-btn--secondary df-btn--sm" style="margin-top:var(--df-space-2)" onclick="addItemRow()">➕ Satır Ekle</button>
      </div>

      <div class="df-card" style="background:var(--df-accent-soft);border-color:transparent;margin:var(--df-space-4) 0">
        <div style="display:flex;justify-content:space-between;padding:3px 0"><span class="df-muted">Ara Toplam</span><b id="salesSubtotal">0,00 ₺</b></div>
        <div style="display:flex;justify-content:space-between;padding:3px 0"><span class="df-muted">KDV</span><b id="salesVat">0,00 ₺</b></div>
        <div style="display:flex;justify-content:space-between;padding:var(--df-space-2) 0 0;border-top:1px solid var(--df-hairline);margin-top:6px">
          <span style="font-size:15px;font-weight:800">Genel Toplam</span>
          <span id="salesTotal" style="font-size:26px;font-weight:900;color:var(--df-accent-soft-ink)">0,00 ₺</span>
        </div>
      </div>

      <button type="submit" class="df-btn df-btn--primary df-btn--lg" style="width:100%">
        <?php if ($stockShortage): ?>⚠️ Onaylıyorum, Devam Et<?php elseif ($isEditView): ?>💾 Değişiklikleri Kaydet<?php else: ?>🧾 Satışı Tamamla (Açık Borç)<?php endif; ?>
      </button>
      <?php if ($isEditView && !$stockShortage): ?><a href="sales.php" class="df-btn df-btn--secondary" style="width:100%;margin-top:var(--df-space-2);justify-content:center">✕ Vazgeç</a><?php endif; ?>
    </form>
  </div><!-- /panel -->

  <!-- Sağ: son satışlar (salt-okunur — JS bağımlılığı yok, güvenle DS'e taşındı) -->
  <div>
    <div class="df-card">
      <h2 style="font-size:15px;margin:0 0 var(--df-space-3)">Son Satışlar</h2>
      <?php
      try {
          $recent = $pdo->query(
              "SELECT fm.id, fm.movement_date, fm.amount, fm.vat_amount, fm.description, fm.status, fm.document_id, c.name AS cname, td.document_no
               FROM finance_movements fm
               LEFT JOIN contacts c ON c.id=fm.contact_id
               LEFT JOIN trade_documents td ON td.id=fm.document_id
               WHERE fm.movement_type='sale' OR fm.movement_type='mobile_sale'
               ORDER BY fm.id DESC LIMIT 10"
          )->fetchAll();
      } catch (Throwable $e) {
          $recent = [];
      }
      if ($recent): ?>
      <div class="df-table-wrap">
      <table class="df-table" style="min-width:600px">
        <thead><tr><th>Tarih</th><th>Cari</th><th>Açıklama</th><th style="text-align:right">Tutar</th><th style="text-align:right">KDV</th><th>Durum</th><?php if(can_edit_delete()): ?><th>İşlem</th><?php endif; ?></tr></thead>
        <tbody>
        <?php foreach ($recent as $row): ?>
          <?php $isDoc = !empty($row['document_id']); ?>
          <?php $rowEditable = !$isDoc && can_edit_delete() && stock_can_edit_sale($pdo, (int)$row['id'])['editable']; ?>
          <tr>
            <td class="nowrap"><?= h($row['movement_date']) ?></td>
            <td><?= h($row['cname'] ?? '—') ?></td>
            <td style="font-size:12px;color:var(--df-ink-500)">
              <?php if ($isDoc): ?><span class="df-badge df-badge--info"><?= h($row['document_no'] ?: 'Belge') ?></span> <?php endif; ?>
              <?= h($row['description'] ?? '') ?>
            </td>
            <td style="text-align:right;font-weight:800;color:var(--df-success-ink)"><?= money($row['amount']) ?></td>
            <td style="text-align:right;color:var(--df-ink-500);font-size:12px"><?= $row['vat_amount'] > 0 ? money($row['vat_amount']) : '—' ?></td>
            <td><?= ds_badge($row['status']) ?></td>
            <?php if(can_edit_delete()): ?>
            <td class="nowrap"><div class="row-actions">
              <?php if ($isDoc): ?>
              <a class="df-btn df-btn--secondary df-btn--sm" href="trade_document_view.php?id=<?= (int)$row['document_id'] ?>">🧾 Belgeyi Aç</a>
              <?php else: ?>
              <?php if ($rowEditable): ?>
              <a class="df-btn df-btn--secondary df-btn--sm" href="sales.php?edit_id=<?= (int)$row['id'] ?>">✏️ Düzenle</a>
              <?php endif; ?>
              <form method="post" action="sil.php" style="display:inline-block;margin:0"
                    onsubmit="return confirm('Bu satış kaydı ve bağlı verileri KALICI olarak silinecek. Emin misiniz?')">
                <input type="hidden" name="t" value="sale">
                <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                <button class="df-btn df-btn--danger df-btn--sm" type="submit">🗑 Sil</button>
              </form>
              <?php endif; ?>
            </div></td>
            <?php endif; ?>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      </div>
      <?php else: ?>
        <p style="color:var(--df-ink-500)">Henüz kayıt yok.</p>
      <?php endif; ?>
    </div>
  </div><!-- /sağ -->

</div><!-- /grid -->

<script>
var PRODUCTS = <?= json_encode(array_map(function($p){
    return ['id'=>(int)$p['id'],'name'=>$p['name'],'unit'=>$p['unit'],'quantity'=>$p['quantity'],'price'=>$p['sale_price'],'vat'=>$p['vat_rate']!==null?$p['vat_rate']:20];
}, $products), JSON_UNESCAPED_UNICODE) ?>;
// Düzenleme modunda VEYA stok-yetersiz onay bekleyen bir denemede formu dolduran veri
// (2026-07-10 migration 043 / 2026-07-11 kontrollü negatif stok politikası).
var PREFILL_LINES = <?= json_encode($viewLines, JSON_UNESCAPED_UNICODE) ?>;
var rowIndex = 0;

function esc(s){ return String(s).replace(/[&<>"']/g, function(c){ return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]; }); }

function productOptionsHtml(){
    var html = '<option value="">— Seç —</option>';
    PRODUCTS.forEach(function(p){
        html += '<option value="'+p.id+'" data-price="'+(p.price||0)+'" data-vat="'+(p.vat||0)+'" data-stock="'+(p.quantity||0)+'" data-unit="'+esc(p.unit||'')+'">'
            + esc(p.name)+' (Stok: '+(p.quantity||0)+' '+esc(p.unit||'')+')</option>';
    });
    html += '<option value="__new__">➕ Listede yok — Yeni Ürün Ekle…</option>';
    return html;
}

function addItemRow(prefill){
    var idx = rowIndex++;
    var tr = document.createElement('tr');
    tr.dataset.idx = idx;
    tr.innerHTML =
        '<td style="padding:4px 6px">'
        + '<select name="stock_item_id[]" class="row-prod" required onchange="onRowProductChange(this)">'+productOptionsHtml()+'</select>'
        + '<div class="new-prod-box df-card" style="display:none;background:var(--df-accent-soft);border-color:transparent;padding:8px;margin-top:6px">'
        + '<input type="text" class="np-name" placeholder="Ürün adı" style="margin-bottom:6px">'
        + '<input type="text" class="np-unit" placeholder="adet" value="adet" style="margin-bottom:6px">'
        + '<button type="button" class="df-btn df-btn--primary df-btn--sm" style="width:100%" onclick="quickAddProductRow(this)">✓ Ekle ve Seç</button>'
        + '</div></td>'
        + '<td style="padding:4px 6px"><input type="number" step="0.01" min="0.01" name="quantity[]" class="row-qty" value="1" required oninput="calcAll()" style="width:80px"></td>'
        + '<td style="padding:4px 6px"><input type="number" step="0.01" min="0" name="unit_price[]" class="row-price" required oninput="calcAll()" style="width:110px"></td>'
        + '<td style="padding:4px 6px"><input type="text" inputmode="decimal" list="vatPresets" name="vat_rate[]" class="row-vat" value="20" oninput="calcAll()" style="width:70px"></td>'
        + '<td class="row-sub" style="padding:4px 6px;text-align:right;font-weight:800">0,00 ₺</td>'
        + '<td style="padding:4px 6px"><button type="button" class="df-btn df-btn--danger df-btn--sm" onclick="removeRow(this)">🗑</button></td>';
    document.getElementById('itemsBody').appendChild(tr);
    if(prefill){
        // Düzenleme modu: ÜRÜNÜN GÜNCEL varsayılan fiyatı DEĞİL, satışta o an kayıtlı olan
        // birim fiyat/KDV kullanılır (onRowProductChange bunu ezmesin diye elle set ediliyor).
        tr.querySelector('.row-prod').value = prefill.id;
        tr.querySelector('.new-prod-box').style.display = 'none';
        tr.querySelector('.row-qty').value = prefill.qty;
        tr.querySelector('.row-price').value = prefill.price;
        tr.querySelector('.row-vat').value = prefill.vat;
    }
    calcAll();
}

function removeRow(btn){
    var tr = btn.closest('tr');
    var rows = document.querySelectorAll('#itemsBody tr');
    if(rows.length <= 1){
        tr.querySelector('.row-prod').value = '';
        tr.querySelector('.row-qty').value = 1;
        tr.querySelector('.row-price').value = '';
        tr.querySelector('.row-vat').value = 20;
    } else {
        tr.remove();
    }
    calcAll();
}

function onRowProductChange(sel){
    var tr = sel.closest('tr');
    var box = tr.querySelector('.new-prod-box');
    if(sel.value === '__new__'){
        box.style.display = 'block';
        sel.value = '';
        tr.querySelector('.np-name').focus();
        return;
    }
    box.style.display = 'none';
    var opt = sel.selectedOptions[0];
    if(opt && opt.dataset.price !== undefined){
        tr.querySelector('.row-price').value = opt.dataset.price;
        tr.querySelector('.row-vat').value = opt.dataset.vat || 20;
    }
    calcAll();
}

function quickAddProductRow(btn){
    var tr = btn.closest('tr');
    var name = tr.querySelector('.np-name').value;
    var unit = tr.querySelector('.np-unit').value || 'adet';
    if(!name){ alert('Ürün adı girin'); return; }
    var fd = new FormData();
    fd.append('t', 'product');
    fd.append('name', name);
    fd.append('unit', unit);
    fetch('ajax_quick_add.php', {method: 'POST', headers: {'X-CSRF-Token': window.CSRF_TOKEN}, body: fd})
        .then(r => r.json())
        .then(data => {
            if(data.ok){
                PRODUCTS.push({id: data.id, name: data.name, unit: unit, quantity: 0, price: 0, vat: 20});
                document.querySelectorAll('.row-prod').forEach(function(sel){
                    var o = document.createElement('option');
                    o.value = data.id; o.dataset.price = 0; o.dataset.vat = 20; o.dataset.stock = 0; o.dataset.unit = unit;
                    o.textContent = data.name + ' (Stok: 0 ' + unit + ')';
                    sel.insertBefore(o, sel.querySelector('option[value="__new__"]'));
                });
                var sel = tr.querySelector('.row-prod');
                sel.value = data.id;
                onRowProductChange(sel);
                tr.querySelector('.np-name').value = '';
                tr.querySelector('.new-prod-box').style.display = 'none';
            } else {
                alert('Hata: ' + data.message);
            }
        })
        .catch(e => alert('Bağlantı hatası: ' + e));
}

function calcAll(){
    var subtotalAll = 0, vatAll = 0;
    document.querySelectorAll('#itemsBody tr').forEach(function(tr){
        var q = parseFloat(tr.querySelector('.row-qty').value) || 0;
        var p = parseFloat(tr.querySelector('.row-price').value) || 0;
        var v = parseFloat(tr.querySelector('.row-vat').value) || 0;
        var sub = q * p;
        var vatAmt = sub * v / 100;
        tr.querySelector('.row-sub').textContent = (sub + vatAmt).toLocaleString('tr-TR', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ' ₺';
        subtotalAll += sub;
        vatAll += vatAmt;
    });
    document.getElementById('salesSubtotal').textContent = subtotalAll.toLocaleString('tr-TR', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ' ₺';
    document.getElementById('salesVat').textContent = vatAll.toLocaleString('tr-TR', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ' ₺';
    document.getElementById('salesTotal').textContent = (subtotalAll + vatAll).toLocaleString('tr-TR', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ' ₺';
}

if(PREFILL_LINES.length){
    PREFILL_LINES.forEach(function(l){ addItemRow(l); });
} else {
    addItemRow();
}

// Dropdown'da "Listede yok — Yeni Ekle" seçilince kutuyu aç (2026-07-03 kullanıcı isteği —
// önce dropdown'a bakıp yok deyip ayrı bir "+" düğmesine gitmek yerine, seçim anında aynı yerde çıksın).
function onContactChange(){
    var sel=document.getElementById('contactSel');
    var box=document.getElementById('newContactBox');
    if(sel.value==='__new__'){ box.style.display='block'; sel.value=''; document.getElementById('contactName').focus(); }
    else box.style.display='none';
}

function quickAddContact(name, type) {
    if (!name) return;
    const fd = new FormData();
    fd.append('t', 'contact');
    fd.append('name', name);
    fd.append('contact_type', type || 'Müşteri');

    fetch('ajax_quick_add.php', {method: 'POST', headers: {'X-CSRF-Token': window.CSRF_TOKEN}, body: fd})
        .then(r => r.json())
        .then(data => {
            if (data.ok) {
                const sel = document.getElementById('contactSel');
                const opt = document.createElement('option');
                opt.value = data.id;
                opt.textContent = data.name;
                opt.selected = true;
                sel.insertBefore(opt, sel.querySelector('option[value="__new__"]'));
                document.getElementById('contactName').value = '';
                document.getElementById('newContactBox').style.display='none';
            } else {
                alert('Hata: ' + data.message);
            }
        })
        .catch(e => alert('Bağlantı hatası: ' + e));
}
</script>

<?php require_once __DIR__.'/layout_bottom.php'; ?>
