<?php
require_once __DIR__.'/boot.php';
require_login();
require_once __DIR__.'/stock_lib.php';

$pdo = db();
$ok  = '';
$er  = '';

function sales_qty_fmt($v)
{
    return rtrim(rtrim(number_format((float)$v, 2, '.', ''), '0'), '.');
}

function sales_acc_for_method($pdo, $method)
{
    $map = ['Peşin' => 'Kasa', 'Kredi Kartı' => 'Kredi Kartı', 'Banka Havalesi' => 'Banka'];
    $type = isset($map[$method]) ? $map[$method] : 'Kasa';
    try {
        $s = $pdo->prepare("SELECT id FROM finance_accounts WHERE account_type=? AND active=1 ORDER BY id LIMIT 1");
        $s->execute([$type]);
        $r = $s->fetch();
        return $r ? (int)$r['id'] : null;
    } catch (Throwable $e) {
        return null;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
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
        } else {
            // Yeni satış kaydı — bir cariye TEK seferde BİRDEN FAZLA ürün satırı (sepet)
            // eklenebilir (2026-07-03 kullanıcı isteği: "bir kişiye bir firmaya birden fazla
            // ürün satılabilir"). Tüm satırlar tek finance_movements kaydında toplanır, her
            // satır kendi stock_movements kaydını (aynı finance_movement_id ile) alır.
            $contact  = (int)$_POST['contact_id'];
            $method   = $_POST['payment_method'] ?? 'Peşin';
            $ids      = $_POST['stock_item_id'] ?? [];
            $qtys     = $_POST['quantity'] ?? [];
            $prices   = $_POST['unit_price'] ?? [];
            $vatRates = $_POST['vat_rate'] ?? [];

            if (!$contact) throw new Exception('Cari seçin.');
            if (!is_array($ids) || !count($ids)) throw new Exception('En az bir ürün satırı ekleyin.');

            $lines = [];
            foreach ($ids as $i => $pid) {
                $pid = (int)$pid;
                $qty = (float)($qtys[$i] ?? 0);
                $price = (float)($prices[$i] ?? 0);
                $vatRate = (float)($vatRates[$i] ?? 0);
                if (!$pid || $qty <= 0) continue;
                if ($price < 0) throw new Exception('Fiyat geçersiz.');

                $p = $pdo->prepare("SELECT * FROM stock_items WHERE id=?");
                $p->execute([$pid]);
                $item = $p->fetch();
                if (!$item) continue;

                $subtotal = $qty * $price;
                $vatAmount = $vatRate > 0 ? round($subtotal * $vatRate / 100, 2) : 0;
                $lineTotal = round($subtotal + $vatAmount, 2);
                $cost = (float)(isset($item['avg_cost']) && $item['avg_cost'] ? $item['avg_cost'] : (isset($item['purchase_price']) ? $item['purchase_price'] : 0));

                $lines[] = [
                    'item' => $item, 'qty' => $qty, 'price' => $price, 'vat_rate' => $vatRate,
                    'vat_amount' => $vatAmount, 'line_total' => $lineTotal, 'profit' => ($price - $cost) * $qty,
                ];
            }
            if (!$lines) throw new Exception('En az bir geçerli ürün satırı ekleyin.');

            $grandTotal = 0; $grandVat = 0; $profitTotal = 0; $descParts = [];
            foreach ($lines as $l) {
                $grandTotal += $l['line_total'];
                $grandVat   += $l['vat_amount'];
                $profitTotal += $l['profit'];
                $descParts[] = $l['item']['name'] . ' x' . sales_qty_fmt($l['qty']);
            }
            $desc = implode(', ', $descParts) . ' satış';

            // Sepetteki BÜTÜN satırlar tek transaction içinde işlenir (2026-07-03 düzeltmesi:
            // çoklu ürüne geçilince, bir satırın stok güncellemesi/hareketi başarısız olursa
            // önceki satırların stoku düşmüş ama finans kaydı hiç oluşmamış kalabiliyordu —
            // tek ürün döneminde imkânsız olan yeni bir yarım-işlem riskiydi).
            $pdo->beginTransaction();
            try {
                // 1) Stok düş (her satır için)
                foreach ($lines as $l) {
                    $pdo->prepare("UPDATE stock_items SET quantity=quantity-? WHERE id=?")->execute([$l['qty'], $l['item']['id']]);
                }

                // 2) Finans hareketi ÖNCE oluşturulur (sepetin TOPLAMI ile) — id'si stok
                // hareketlerine kesin referans olarak yazılacak.
                $accId = sales_acc_for_method($pdo, $method);
                $pdo->prepare(
                    "INSERT INTO finance_movements(contact_id,direction,amount,vat_rate,vat_amount,payment_channel,account_id,status,movement_date,description,movement_type)
                     VALUES(?,?,?,?,?,?,?,?,?,?,'sale')"
                )->execute([
                    $contact, 'in', $grandTotal, count($lines) === 1 ? ($lines[0]['vat_rate'] ?: null) : null, $grandVat,
                    $method, $accId, 'Tahsil Edildi', date('Y-m-d'), $desc
                ]);
                $financeMovementId = (int)$pdo->lastInsertId();

                // 3) Her satır için stok hareketi — hepsi aynı finance_movement_id ile kesin referanslı
                foreach ($lines as $l) {
                    $pdo->prepare(
                        "INSERT INTO stock_movements(stock_item_id,finance_movement_id,direction,quantity,reason,note,created_at) VALUES(?,?,?,?,?,?,NOW())"
                    )->execute([$l['item']['id'], $financeMovementId, 'out', $l['qty'], 'Satış', 'Web satış (' . $method . ')']);
                }

                // 4) Kasa bakiyesi güncelle (KDV dahil gerçek tutar)
                if ($accId) {
                    $pdo->prepare("UPDATE finance_accounts SET current_balance=current_balance+? WHERE id=?")
                        ->execute([$grandTotal, $accId]);
                }

                $pdo->commit();
            } catch (Throwable $e) {
                $pdo->rollBack();
                throw $e;
            }

            // 5) Log
            try {
                if (function_exists('activity_log')) {
                    activity_log('Satış', 'Web', $desc . ' ' . money($grandTotal) . ' (kâr ' . money($profitTotal) . ')', $method, 'sale', $lines[0]['item']['id'], 'sales.php', '🧾');
                }
            } catch (Throwable $e) {}

            $kz = $profitTotal >= 0
                ? 'Kâr: ' . money($profitTotal)
                : 'Zarar: ' . money(-$profitTotal);

            $ok = h(implode(', ', $descParts)) . ' satıldı: <b>' . money($grandTotal) . '</b>' . ($grandVat > 0 ? ' (KDV dahil, KDV: ' . money($grandVat) . ')' : '') . ' (' . h($method) . ') &mdash; ' . $kz;
        }
    } catch (Throwable $e) {
        $er = $e->getMessage();
    }
}

// Listeler
$contacts = $pdo->query("SELECT id,name FROM contacts ORDER BY name")->fetchAll();
$products  = $pdo->query(
    "SELECT id,name,quantity,unit,sale_price,avg_cost,purchase_price,vat_rate FROM stock_items WHERE COALESCE(active,1)=1 ORDER BY name"
)->fetchAll();

require_once __DIR__.'/layout_top.php';
?>

<h1>Hızlı Satış</h1>

<?php if ($ok): ?>
<div class="ok"><?= $ok ?></div>
<?php endif; ?>
<?php if ($er): ?>
<div class="alert"><?= h($er) ?></div>
<?php endif; ?>

<div style="display:grid;grid-template-columns:1fr 340px;gap:20px;align-items:start">

  <!-- Sol: form -->
  <div class="panel">
    <div class="panel-head"><h2>Satış Formu</h2></div>
    <form method="post" id="salesForm">
      <div class="form-grid">

        <div class="full">
          <label>Cari (Müşteri)</label>
          <select name="contact_id" id="contactSel" class="full" required onchange="onContactChange()">
            <option value="">— Seç —</option>
            <?php foreach ($contacts as $c): ?>
              <option value="<?= (int)$c['id'] ?>"><?= h($c['name']) ?></option>
            <?php endforeach; ?>
            <option value="__new__">➕ Listede yok — Yeni Cari Ekle…</option>
          </select>
          <div id="newContactBox" class="full" style="display:none;background:#eef4ff;border:1px solid #bfdbfe;border-radius:12px;padding:12px;margin-top:8px">
            <input type="text" id="contactName" placeholder="Müşteri adı" style="width:100%;border:1px solid #d0d5dd;border-radius:10px;padding:10px;margin-bottom:8px">
            <select id="contactType" style="width:100%;border:1px solid #d0d5dd;border-radius:10px;padding:10px;margin-bottom:8px">
              <option>Müşteri</option><option>Tedarikçi</option><option>Diğer</option>
            </select>
            <button type="button" class="btn" style="width:100%" onclick="quickAddContact(document.getElementById('contactName').value, document.getElementById('contactType').value)">✓ Ekle ve Seç</button>
          </div>
        </div>

        <div class="full">
          <label>Ödeme Yöntemi</label>
          <select name="payment_method" required>
            <option>Peşin</option>
            <option>Kredi Kartı</option>
            <option>Banka Havalesi</option>
          </select>
        </div>

      </div><!-- /form-grid -->

      <div class="full" style="margin-top:14px">
        <label style="font-weight:800">Ürünler</label>
        <div style="overflow:auto">
        <table class="sales-items-tbl" style="width:100%;border-collapse:collapse">
          <thead>
            <tr style="text-align:left;font-size:12px;color:#667085">
              <th style="padding:4px 6px">Ürün</th>
              <th style="padding:4px 6px;width:90px">Miktar</th>
              <th style="padding:4px 6px;width:120px">Birim Fiyat (₺)</th>
              <th style="padding:4px 6px;width:80px">KDV %</th>
              <th style="padding:4px 6px;width:110px;text-align:right">Ara Toplam</th>
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
        <button type="button" class="btn secondary small" style="margin-top:8px" onclick="addItemRow()">➕ Satır Ekle</button>
      </div>

      <div class="panel" style="background:#f0f9ff;border:1px solid #bae6fd;margin:16px 0;padding:16px 18px">
        <div style="display:flex;justify-content:space-between;padding:3px 0"><span class="muted">Ara Toplam</span><b id="salesSubtotal">0,00 ₺</b></div>
        <div style="display:flex;justify-content:space-between;padding:3px 0"><span class="muted">KDV</span><b id="salesVat">0,00 ₺</b></div>
        <div style="display:flex;justify-content:space-between;padding:8px 0 0;border-top:1px solid #bae6fd;margin-top:6px">
          <span style="font-size:15px;font-weight:800">Genel Toplam</span>
          <span id="salesTotal" style="font-size:26px;font-weight:900;color:#0369a1">0,00 ₺</span>
        </div>
      </div>

      <button type="submit" class="btn" style="width:100%;padding:14px;font-size:16px">
        🧾 Satışı Tamamla
      </button>
    </form>
  </div><!-- /panel -->

  <!-- Sağ: son satışlar -->
  <div>
    <div class="panel">
      <div class="panel-head"><h2 style="font-size:15px">Son Satışlar</h2></div>
      <?php
      try {
          $recent = $pdo->query(
              "SELECT fm.id, fm.movement_date, fm.amount, fm.vat_amount, fm.description, fm.payment_channel, c.name AS cname
               FROM finance_movements fm
               LEFT JOIN contacts c ON c.id=fm.contact_id
               WHERE fm.movement_type='sale' OR fm.movement_type='mobile_sale'
               ORDER BY fm.id DESC LIMIT 10"
          )->fetchAll();
      } catch (Throwable $e) {
          $recent = [];
      }
      if ($recent): ?>
      <table>
        <thead><tr><th>Tarih</th><th>Cari</th><th>Açıklama</th><th style="text-align:right">Tutar</th><th style="text-align:right">KDV</th><?php if(can_edit_delete()): ?><th>İşlem</th><?php endif; ?></tr></thead>
        <tbody>
        <?php foreach ($recent as $row): ?>
          <tr>
            <td class="nowrap"><?= h($row['movement_date']) ?></td>
            <td><?= h($row['cname'] ?? '—') ?></td>
            <td class="muted" style="font-size:12px"><?= h($row['description'] ?? '') ?></td>
            <td style="text-align:right;font-weight:800;color:#166534"><?= money($row['amount']) ?></td>
            <td style="text-align:right;color:#667085;font-size:12px"><?= $row['vat_amount'] > 0 ? money($row['vat_amount']) : '—' ?></td>
            <?php if(can_edit_delete()): ?>
            <td>
              <form method="post" action="sil.php" style="display:inline-block;margin:0"
                    onsubmit="return confirm('Bu satış kaydı ve bağlı verileri KALICI olarak silinecek. Emin misiniz?')">
                <input type="hidden" name="t" value="sale">
                <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                <button class="btn danger small" type="submit">🗑 Sil</button>
              </form>
            </td>
            <?php endif; ?>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <?php else: ?>
        <p class="muted">Henüz kayıt yok.</p>
      <?php endif; ?>
    </div>
  </div><!-- /sağ -->

</div><!-- /grid -->

<script>
var PRODUCTS = <?= json_encode(array_map(function($p){
    return ['id'=>(int)$p['id'],'name'=>$p['name'],'unit'=>$p['unit'],'quantity'=>$p['quantity'],'price'=>$p['sale_price'],'vat'=>$p['vat_rate']!==null?$p['vat_rate']:20];
}, $products), JSON_UNESCAPED_UNICODE) ?>;
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

function addItemRow(){
    var idx = rowIndex++;
    var tr = document.createElement('tr');
    tr.dataset.idx = idx;
    tr.innerHTML =
        '<td style="padding:4px 6px">'
        + '<select name="stock_item_id[]" class="row-prod" required onchange="onRowProductChange(this)">'+productOptionsHtml()+'</select>'
        + '<div class="new-prod-box" style="display:none;background:#eef4ff;border:1px solid #bfdbfe;border-radius:10px;padding:8px;margin-top:6px">'
        + '<input type="text" class="np-name" placeholder="Ürün adı" style="width:100%;border:1px solid #d0d5dd;border-radius:8px;padding:6px;margin-bottom:6px">'
        + '<input type="text" class="np-unit" placeholder="adet" value="adet" style="width:100%;border:1px solid #d0d5dd;border-radius:8px;padding:6px;margin-bottom:6px">'
        + '<button type="button" class="btn small" style="width:100%" onclick="quickAddProductRow(this)">✓ Ekle ve Seç</button>'
        + '</div></td>'
        + '<td style="padding:4px 6px"><input type="number" step="0.01" min="0.01" name="quantity[]" class="row-qty" value="1" required oninput="calcAll()" style="width:80px"></td>'
        + '<td style="padding:4px 6px"><input type="number" step="0.01" min="0" name="unit_price[]" class="row-price" required oninput="calcAll()" style="width:110px"></td>'
        + '<td style="padding:4px 6px"><input type="text" inputmode="decimal" list="vatPresets" name="vat_rate[]" class="row-vat" value="20" oninput="calcAll()" style="width:70px"></td>'
        + '<td class="row-sub" style="padding:4px 6px;text-align:right;font-weight:800">0,00 ₺</td>'
        + '<td style="padding:4px 6px"><button type="button" class="btn danger small" onclick="removeRow(this)">🗑</button></td>';
    document.getElementById('itemsBody').appendChild(tr);
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

addItemRow();

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
