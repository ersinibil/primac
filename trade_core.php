<?php
// ACANS OS v19 Trade Helper

if(file_exists(__DIR__.'/activity_lib.php')) require_once __DIR__.'/activity_lib.php';

function trade_next_no($type){
    $prefix=$type==='purchase'?'ALI':'SAT';
    return $prefix.'-'.date('Ymd').'-'.random_int(1000,9999);
}

function trade_money($v){
    return number_format((float)$v,2,',','.').' ₺';
}

function trade_ensure_product($name,$unit,$unitPrice,$type){
    $pdo=db();

    $s=$pdo->prepare("SELECT * FROM stock_items WHERE name=? LIMIT 1");
    $s->execute([trim($name)]);
    $existing=$s->fetch();
    if($existing) return [(int)$existing['id'], false];

    $code='URN-'.date('ymd').'-'.random_int(100,999);

    $purchasePrice=$type==='purchase'?(float)$unitPrice:0;
    $salePrice=$type==='sale'?(float)$unitPrice:0;

    $stmt=$pdo->prepare("INSERT INTO stock_items(
        product_code,name,unit,quantity,critical_level,purchase_price,sale_price,avg_cost,last_purchase_price,active
    ) VALUES(?,?,?,?,?,?,?,?,?,1)");
    $stmt->execute([
        $code,
        trim($name),
        trim($unit) ?: 'adet',
        0,
        0,
        $purchasePrice,
        $salePrice,
        $purchasePrice,
        $purchasePrice
    ]);

    return [(int)$pdo->lastInsertId(), true];
}

function trade_apply_document($documentId){
    $pdo=db();

    $ds=$pdo->prepare("SELECT d.*, c.name contact_name, a.name account_name FROM trade_documents d LEFT JOIN contacts c ON c.id=d.contact_id LEFT JOIN finance_accounts a ON a.id=d.account_id WHERE d.id=?");
    $ds->execute([$documentId]);
    $doc=$ds->fetch();
    if(!$doc) throw new Exception('Belge bulunamadı.');

    $items=$pdo->prepare("SELECT * FROM trade_document_items WHERE document_id=?");
    $items->execute([$documentId]);
    $rows=$items->fetchAll();

    foreach($rows as $it){
        if(!$it['stock_item_id']) continue;

        $sid=(int)$it['stock_item_id'];
        $qty=(float)$it['quantity'];
        $unitPrice=(float)$it['unit_price'];

        $ps=$pdo->prepare("SELECT * FROM stock_items WHERE id=?");
        $ps->execute([$sid]);
        $p=$ps->fetch();
        if(!$p) continue;

        if($doc['document_type']==='purchase'){
            $oldQty=(float)$p['quantity'];
            $oldAvg=(float)($p['avg_cost'] ?: $p['purchase_price']);
            $newQty=$oldQty+$qty;
            $newAvg=$newQty>0 ? (($oldQty*$oldAvg)+($qty*$unitPrice))/$newQty : $unitPrice;

            $pdo->prepare("UPDATE stock_items SET quantity=?, avg_cost=?, purchase_price=?, last_purchase_price=? WHERE id=?")
                ->execute([$newQty,$newAvg,$unitPrice,$unitPrice,$sid]);

            try{
                $pdo->prepare("INSERT INTO stock_movements(stock_item_id,movement_type,quantity,unit_cost,unit_sale,total_cost,total_sale,contact_id,supplier_id,movement_date,description)
                    VALUES(?,?,?,?,?,?,?,?,?,?,?)")
                    ->execute([$sid,'in',$qty,$unitPrice,0,$qty*$unitPrice,0,$doc['contact_id'],$doc['contact_id'],$doc['document_date'],'Alış belgesi: '.$doc['document_no']]);
            }catch(Throwable $e){}
        }

        if($doc['document_type']==='sale'){
            $cost=(float)($p['avg_cost'] ?: $p['purchase_price']);

            $pdo->prepare("UPDATE stock_items SET quantity=quantity-? WHERE id=?")
                ->execute([$qty,$sid]);

            try{
                $pdo->prepare("INSERT INTO stock_movements(stock_item_id,movement_type,quantity,unit_cost,unit_sale,total_cost,total_sale,contact_id,movement_date,description)
                    VALUES(?,?,?,?,?,?,?,?,?,?)")
                    ->execute([$sid,'sale',$qty,$cost,$unitPrice,$qty*$cost,$qty*$unitPrice,$doc['contact_id'],$doc['document_date'],'Satış belgesi: '.$doc['document_no']]);
            }catch(Throwable $e){}
        }
    }

    // Cari/finans hareketi
    $direction=$doc['document_type']==='purchase'?'out':'in';
    $status=$direction==='in'?'Tahsil Edildi':'Ödendi';
    $channel=$doc['account_id']?'Hesap':'Cari Belge';

    $paid=(float)$doc['paid_amount'];
    if($paid>0 && $doc['account_id']){
        $pdo->prepare("INSERT INTO finance_movements(contact_id,job_id,direction,amount,payment_channel,account_id,status,movement_date,description,movement_type,document_id)
            VALUES(?,NULL,?,?,?,?,?,?,?,'document',?)")
            ->execute([$doc['contact_id'],$direction,$paid,$channel,$doc['account_id'],$status,$doc['document_date'],$doc['document_no'].' ödeme/tahsilat',$documentId]);

        if($direction==='in'){
            $pdo->prepare("UPDATE finance_accounts SET current_balance=current_balance+? WHERE id=?")->execute([$paid,$doc['account_id']]);
        }else{
            $pdo->prepare("UPDATE finance_accounts SET current_balance=current_balance-? WHERE id=?")->execute([$paid,$doc['account_id']]);
        }
    }

    if(function_exists('activity_log')){
        $mod=$doc['document_type']==='purchase'?'Alış':'Satış';
        $icon=$doc['document_type']==='purchase'?'🛒':'🧾';
        activity_log('Cari',$mod,$mod.' belgesi oluşturuldu',($doc['contact_name'] ?: 'Cari yok').' · '.$doc['document_no'].' · '.trade_money($doc['grand_total']),'trade_document',$documentId,'trade_document_view.php?id='.$documentId,$icon);
    }
}
?>