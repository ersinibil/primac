<?php
/* FİNANS UX REGRESYON DÜZELTMESİ (2026-07-19) — Tahsilat/Ödeme ekranlarındaki cari <select>
 * TÜM contacts tablosunu (potansiyel binlerce satır) DOM'a döküyordu. Bu, aynı arama/filtre
 * mantığını (contacts.type IN (...)) sunucu tarafında, sınırlı sayıda sonuçla yapan tek ortak
 * AJAX kaynağı — web (finance_new.php) ve mobil (collection.php/payment.php) BİREBİR aynı uç
 * noktayı kullanır, filtre mantığı burada tek yerde yaşar.
 * GET: q=arama metni (isim/telefon), scope=customers|suppliers|all
 * Dönüş: JSON {ok:bool, contacts:[{id,name,type,phone}]}
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__.'/boot.php';
require_login();

try{
    $pdo = db();
    $q = trim($_GET['q'] ?? '');
    $scope = $_GET['scope'] ?? 'all';

    $where = [];
    $params = [];
    if($scope === 'customers'){
        $where[] = "type IN ('Müşteri','Her İkisi')";
    }elseif($scope === 'suppliers'){
        $where[] = "type IN ('Tedarikçi','Her İkisi')";
    }
    if($q !== ''){
        $where[] = "(name LIKE ? OR phone LIKE ?)";
        $params[] = '%'.$q.'%';
        $params[] = '%'.$q.'%';
    }

    $sql = "SELECT id,name,type,phone FROM contacts";
    if($where) $sql .= " WHERE ".implode(' AND ', $where);
    $sql .= " ORDER BY name LIMIT 20";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    echo json_encode(['ok'=>true,'contacts'=>$rows], JSON_UNESCAPED_UNICODE);
}catch(Throwable $e){
    echo json_encode(['ok'=>false,'message'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
}
