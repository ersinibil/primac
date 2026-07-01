<?php
require_once __DIR__.'/boot.php';
require_login();

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

        // 2) Stok hareketi
        try {
            $pdo->prepare(
                "INSERT INTO stock_movements(stock_item_id,direction,quantity,reason,note,created_at) VALUES(?,?,?,?,?,NOW())"
            )->execute([$product, 'out', $qty, 'Satış', 'Web satış (' . $method . ')']);
        } catch (Throwable $e) {}

        // 3) Finans hareketi
        $accId = sales_acc_for_method($pdo, $method);
        $pdo->prepare(
            "INSERT INTO finance_movements(contact_id,direction,amount,payment_channel,account_id,status,movement_date,description,movement_type)
             VALUES(?,?,?,?,?,?,?,?,'sale')"
        )->execute([
            $contact, 'in', $total, $method, $accId, 'Tahsil Edildi',
            date('Y-m-d'),
            $item['name'] . ' x' . sales_qty_fmt($qty) . ' satış'
        ]);

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
          <select name="contact_id" class="full" required>
            <option value="">— Seç —</option>
            <?php foreach ($contacts as $c): ?>
              <option value="<?= (int)$c['id'] ?>"><?= h($c['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="full">
          <label>Ürün</label>
          <select name="stock_item_id" id="prodSel" required onchange="salesSetPrice()">
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
          </select>
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
        <thead><tr><th>Tarih</th><th>Cari</th><th>Açıklama</th><th style="text-align:right">Tutar</th></tr></thead>
        <tbody>
        <?php foreach ($recent as $row): ?>
          <tr>
            <td class="nowrap"><?= h($row['movement_date']) ?></td>
            <td><?= h($row['cname'] ?? '—') ?></td>
            <td class="muted" style="font-size:12px"><?= h($row['description'] ?? '') ?></td>
            <td style="text-align:right;font-weight:800;color:#166534"><?= money($row['amount']) ?></td>
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
</script>

<?php require_once __DIR__.'/layout_bottom.php'; ?>
