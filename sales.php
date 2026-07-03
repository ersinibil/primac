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
            // Yeni satış kaydı
            $contact = (int)$_POST['contact_id'];
            $product = (int)$_POST['stock_item_id'];
            $qty     = (float)$_POST['quantity'];
            $price   = (float)$_POST['unit_price'];
            $method  = $_POST['payment_method'] ?? 'Peşin';

            if (!$contact) throw new Exception('Cari seçin.');
            if (!$product) throw new Exception('Ürün seçin.');
            if ($qty <= 0)  throw new Exception('Miktar geçersiz.');
            if ($price < 0) throw new Exception('Fiyat geçersiz.');

            $p = $pdo->prepare("SELECT * FROM stock_items WHERE id=?");
            $p->execute([$product]);
            $item = $p->fetch();
            if (!$item) throw new Exception('Ürün bulunamadı.');

            $total = $qty * $price;

            // 1) Stok düş
            $pdo->prepare("UPDATE stock_items SET quantity=quantity-? WHERE id=?")->execute([$qty, $product]);

            // 2) Finans hareketi ÖNCE oluşturulur — id'si stok hareketine kesin referans olarak
            // yazılacak (aynı gün birden fazla satış olunca "en son hareket" tahminiyle silme
            // yanlış kayda denk gelebiliyordu, 2026-07-03 denetiminde bulundu).
            $accId = sales_acc_for_method($pdo, $method);
            $pdo->prepare(
                "INSERT INTO finance_movements(contact_id,direction,amount,payment_channel,account_id,status,movement_date,description,movement_type)
                 VALUES(?,?,?,?,?,?,?,?,'sale')"
            )->execute([
                $contact, 'in', $total, $method, $accId, 'Tahsil Edildi',
                date('Y-m-d'),
                $item['name'] . ' x' . sales_qty_fmt($qty) . ' satış'
            ]);
            $financeMovementId = (int)$pdo->lastInsertId();

            // 3) Stok hareketi — finance_movement_id ile kesin referanslı
            try {
                $pdo->prepare(
                    "INSERT INTO stock_movements(stock_item_id,finance_movement_id,direction,quantity,reason,note,created_at) VALUES(?,?,?,?,?,?,NOW())"
                )->execute([$product, $financeMovementId, 'out', $qty, 'Satış', 'Web satış (' . $method . ')']);
            } catch (Throwable $e) {}

            // 4) Kasa bakiyesi güncelle
            if ($accId) {
                try {
                    $pdo->prepare("UPDATE finance_accounts SET current_balance=current_balance+? WHERE id=?")
                        ->execute([$total, $accId]);
                } catch (Throwable $e) {}
            }

            // 5) Kâr/zarar
            $cost   = (float)(isset($item['avg_cost']) && $item['avg_cost'] ? $item['avg_cost'] : (isset($item['purchase_price']) ? $item['purchase_price'] : 0));
            $profit = ($price - $cost) * $qty;

            // 6) Log
            try {
                if (function_exists('activity_log')) {
                    activity_log('Satış', 'Web', $item['name'] . ' ' . money($total) . ' (kâr ' . money($profit) . ')', $method, 'sale', $product, 'sales.php', '🧾');
                }
            } catch (Throwable $e) {}

            $kz = $profit >= 0
                ? 'Kâr: ' . money($profit)
                : 'Zarar: ' . money(-$profit);

            $ok = h($item['name']) . ' satıldı: <b>' . money($total) . '</b> (' . h($method) . ') &mdash; ' . $kz;
        }
    } catch (Throwable $e) {
        $er = $e->getMessage();
    }
}

// Listeler
$contacts = $pdo->query("SELECT id,name FROM contacts ORDER BY name")->fetchAll();
$products  = $pdo->query(
    "SELECT id,name,quantity,unit,sale_price,avg_cost,purchase_price FROM stock_items WHERE COALESCE(active,1)=1 ORDER BY name"
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
          <label>Ürün</label>
          <select name="stock_item_id" id="prodSel" class="full" required onchange="salesSetPrice(); onProductChange()">
            <option value="">— Seç —</option>
            <?php foreach ($products as $pr): ?>
              <option value="<?= (int)$pr['id'] ?>"
                      data-price="<?= h($pr['sale_price'] ?? 0) ?>"
                      data-stock="<?= h($pr['quantity'] ?? 0) ?>"
                      data-unit="<?= h($pr['unit'] ?? '') ?>">
                <?= h($pr['name']) ?>
                (Stok: <?= h($pr['quantity'] ?? 0) ?> <?= h($pr['unit'] ?? '') ?>)
              </option>
            <?php endforeach; ?>
            <option value="__new__">➕ Listede yok — Yeni Ürün Ekle…</option>
          </select>
          <div id="newProductBox" class="full" style="display:none;background:#eef4ff;border:1px solid #bfdbfe;border-radius:12px;padding:12px;margin-top:8px">
            <input type="text" id="productName" placeholder="Ürün adı" style="width:100%;border:1px solid #d0d5dd;border-radius:10px;padding:10px;margin-bottom:8px">
            <input type="text" id="productUnit" placeholder="adet" value="adet" style="width:100%;border:1px solid #d0d5dd;border-radius:10px;padding:10px;margin-bottom:8px">
            <button type="button" class="btn" style="width:100%" onclick="quickAddProduct(document.getElementById('productName').value, document.getElementById('productUnit').value)">✓ Ekle ve Seç</button>
          </div>
        </div>

        <div>
          <label>Miktar</label>
          <input type="number" step="0.01" min="0.01" name="quantity" id="salesQty"
                 value="1" required oninput="salesCalc()">
        </div>

        <div>
          <label>Birim Fiyat (₺)</label>
          <input type="number" step="0.01" min="0" name="unit_price" id="salesPrice"
                 required oninput="salesCalc()">
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

      <div class="panel" style="background:#f0f9ff;border:1px solid #bae6fd;margin:16px 0;text-align:center;padding:18px">
        <div class="muted" style="font-size:13px">Toplam Tutar</div>
        <div id="salesTotal" style="font-size:36px;font-weight:900;color:#0369a1;margin:4px 0">0,00 ₺</div>
      </div>

      <button type="submit" class="btn" style="width:100%;padding:14px;font-size:16px">
        🧾 Satışı Tamamla
      </button>
    </form>
  </div><!-- /panel -->

  <!-- Sağ: stok özeti -->
  <div>
    <div class="panel" id="stockInfo" style="display:none">
      <div class="panel-head"><h2 style="font-size:15px">Seçili Ürün</h2></div>
      <table>
        <tr><th>Mevcut Stok</th><td id="siStock">—</td></tr>
        <tr><th>Birim Fiyat</th><td id="siPrice">—</td></tr>
      </table>
    </div>

    <div class="panel" style="margin-top:16px">
      <div class="panel-head"><h2 style="font-size:15px">Son Satışlar</h2></div>
      <?php
      try {
          $recent = $pdo->query(
              "SELECT fm.movement_date, fm.amount, fm.description, fm.payment_channel, c.name AS cname
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
        <thead><tr><th>Tarih</th><th>Cari</th><th>Açıklama</th><th style="text-align:right">Tutar</th><?php if(can_edit_delete()): ?><th>İşlem</th><?php endif; ?></tr></thead>
        <tbody>
        <?php foreach ($recent as $row): ?>
          <tr>
            <td class="nowrap"><?= h($row['movement_date']) ?></td>
            <td><?= h($row['cname'] ?? '—') ?></td>
            <td class="muted" style="font-size:12px"><?= h($row['description'] ?? '') ?></td>
            <td style="text-align:right;font-weight:800;color:#166534"><?= money($row['amount']) ?></td>
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
function salesSetPrice() {
    var sel = document.getElementById('prodSel');
    var opt = sel.options[sel.selectedIndex];
    if (opt && opt.dataset.price !== undefined) {
        document.getElementById('salesPrice').value = opt.dataset.price;
    }
    var info = document.getElementById('stockInfo');
    if (opt && opt.value) {
        document.getElementById('siStock').textContent = (opt.dataset.stock || '0') + ' ' + (opt.dataset.unit || '');
        document.getElementById('siPrice').textContent = parseFloat(opt.dataset.price || 0).toLocaleString('tr-TR', {minimumFractionDigits: 2}) + ' ₺';
        info.style.display = 'block';
    } else {
        info.style.display = 'none';
    }
    salesCalc();
}
function salesCalc() {
    var q = parseFloat(document.getElementById('salesQty').value) || 0;
    var p = parseFloat(document.getElementById('salesPrice').value) || 0;
    document.getElementById('salesTotal').textContent =
        (q * p).toLocaleString('tr-TR', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ' ₺';
}
salesCalc();

// Dropdown'da "Listede yok — Yeni Ekle" seçilince kutuyu aç (2026-07-03 kullanıcı isteği —
// önce dropdown'a bakıp yok deyip ayrı bir "+" düğmesine gitmek yerine, seçim anında aynı yerde çıksın).
function onContactChange(){
    var sel=document.getElementById('contactSel');
    var box=document.getElementById('newContactBox');
    if(sel.value==='__new__'){ box.style.display='block'; sel.value=''; document.getElementById('contactName').focus(); }
    else box.style.display='none';
}
function onProductChange(){
    var sel=document.getElementById('prodSel');
    var box=document.getElementById('newProductBox');
    if(sel.value==='__new__'){ box.style.display='block'; sel.value=''; document.getElementById('productName').focus(); }
    else box.style.display='none';
}

function quickAddContact(name, type) {
    if (!name) return;
    const fd = new FormData();
    fd.append('t', 'contact');
    fd.append('name', name);
    fd.append('contact_type', type || 'Müşteri');

    fetch('ajax_quick_add.php', {method: 'POST', body: fd})
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

function quickAddProduct(name, unit) {
    if (!name) return;
    const fd = new FormData();
    fd.append('t', 'product');
    fd.append('name', name);
    fd.append('unit', unit || 'adet');

    fetch('ajax_quick_add.php', {method: 'POST', body: fd})
        .then(r => r.json())
        .then(data => {
            if (data.ok) {
                const sel = document.getElementById('prodSel');
                const opt = document.createElement('option');
                opt.value = data.id;
                opt.textContent = data.name + ' (Stok: 0 ' + (unit || 'adet') + ')';
                opt.selected = true;
                sel.insertBefore(opt, sel.querySelector('option[value="__new__"]'));
                salesSetPrice();
                document.getElementById('productName').value = '';
                document.getElementById('newProductBox').style.display='none';
            } else {
                alert('Hata: ' + data.message);
            }
        })
        .catch(e => alert('Bağlantı hatası: ' + e));
}
</script>

<?php require_once __DIR__.'/layout_bottom.php'; ?>
