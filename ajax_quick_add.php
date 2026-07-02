<?php
/* Hızlı cari/ürün ekleme — AJAX endpoint
 * Web ve mobil formlardan (sales/purchase/checks_notes) doğrudan yeni cari/ürün ekleme.
 * POST: t=contact|product, name=ad
 * Dönüş: JSON {ok:bool, id:int, name:string, message:string}
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__.'/boot.php';
require_login();
$pdo = db();

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Yalnızca POST desteklenir.');
    }

    $t = trim($_POST['t'] ?? '');
    $name = trim($_POST['name'] ?? '');

    if (!$name) {
        throw new Exception('Ad/isim zorunlu.');
    }

    if ($t === 'contact') {
        // Yeni cari hesap ekle
        if (!user_can('contacts')) {
            throw new Exception('Cari ekleme yetkisi yok.');
        }

        $type = trim($_POST['contact_type'] ?? 'Müşteri');
        $phone = trim($_POST['phone'] ?? '');

        $pdo->prepare("INSERT INTO contacts(name,type,phone,created_at) VALUES(?,?,?,NOW())")
            ->execute([$name, $type, $phone]);
        $id = (int)$pdo->lastInsertId();

        echo json_encode([
            'ok' => true,
            'id' => $id,
            'name' => $name,
            'message' => 'Cari eklendi.'
        ]);

    } elseif ($t === 'product') {
        // Yeni ürün ekle
        if (!user_can('stock')) {
            throw new Exception('Ürün ekleme yetkisi yok.');
        }

        $unit = trim($_POST['unit'] ?? 'adet');
        $code = 'URN-' . date('ymd') . '-' . random_int(100, 999);

        $pdo->prepare("INSERT INTO stock_items(product_code,name,unit,quantity,critical_level,active) VALUES(?,?,?,0,0,1)")
            ->execute([$code, $name, $unit]);
        $id = (int)$pdo->lastInsertId();

        echo json_encode([
            'ok' => true,
            'id' => $id,
            'name' => $name,
            'message' => 'Ürün eklendi.'
        ]);

    } else {
        throw new Exception('Geçersiz tür.');
    }

} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode([
        'ok' => false,
        'id' => null,
        'name' => '',
        'message' => $e->getMessage()
    ]);
}
