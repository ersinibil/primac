<?php
/* P1 — CPA (2026-07-18): satın alma ekranında ürün seçildiğinde "akıllı öneri" — salt-okunur,
 * state değiştirmez (GET, CSRF gerekmez). purchase.php'nin ürün satırı JS'inden çağrılır.
 * GET: stock_item_id=N
 * Dönüş: JSON {ok:bool, suggestions:[{customer_name,supplier_name,priority,is_default}]}
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__.'/boot.php';
require_once __DIR__.'/cpa_lib.php';
require_login();

try{
    $sid = (int)($_GET['stock_item_id'] ?? 0);
    if(!$sid) throw new Exception('stock_item_id zorunlu.');
    $rows = cpa_suggest_by_product(db(), $sid);
    $out = array_map(function($r){
        return [
            'customer_name'=>$r['customer_name'] ?: ('#'.$r['customer_id']),
            'supplier_name'=>$r['supplier_name'] ?: ('#'.$r['supplier_id']),
            'priority'=>(int)$r['priority'],
            'is_default'=>(bool)$r['is_default'],
        ];
    }, $rows);
    echo json_encode(['ok'=>true,'suggestions'=>$out], JSON_UNESCAPED_UNICODE);
}catch(Throwable $e){
    echo json_encode(['ok'=>false,'message'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
}
