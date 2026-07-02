<?php
// OTS Muhasebe — paylaşılan fonksiyonlar (web + mobil)

function acc_categories($pdo, $type=null){
    try{
        if($type){
            $s=$pdo->prepare("SELECT * FROM accounting_categories WHERE type=? AND active=1 ORDER BY sort_order,name");
            $s->execute([$type]); return $s->fetchAll();
        }
        return $pdo->query("SELECT * FROM accounting_categories WHERE active=1 ORDER BY type DESC,sort_order,name")->fetchAll();
    }catch(Throwable $e){ return []; }
}

function acc_summary($pdo, $month=null, $year=null){
    $month=$month ?: (int)date('m');
    $year=$year ?: (int)date('Y');
    try{
        $s=$pdo->prepare("SELECT type,SUM(amount) total FROM accounting_entries WHERE YEAR(entry_date)=? AND MONTH(entry_date)=? GROUP BY type");
        $s->execute([$year,$month]); $rows=$s->fetchAll(PDO::FETCH_KEY_PAIR);
        return ['gelir'=>(float)($rows['gelir']??0),'gider'=>(float)($rows['gider']??0),'month'=>$month,'year'=>$year];
    }catch(Throwable $e){ return ['gelir'=>0,'gider'=>0,'month'=>$month,'year'=>$year]; }
}

function acc_personnel_summary($pdo, $year=null){
    $year=$year ?: (int)date('Y');
    try{
        $s=$pdo->prepare("SELECT p.name, SUM(ae.amount) total, ae.payment_type
            FROM accounting_entries ae JOIN personnel p ON p.id=ae.personnel_id
            WHERE YEAR(ae.entry_date)=? AND ae.personnel_id IS NOT NULL
            GROUP BY ae.personnel_id, ae.payment_type ORDER BY p.name");
        $s->execute([$year]); return $s->fetchAll();
    }catch(Throwable $e){ return []; }
}

function acc_group_summary($pdo, $month, $year){
    try{
        $s=$pdo->prepare("SELECT ac.group_name, ac.type, SUM(ae.amount) total
            FROM accounting_entries ae JOIN accounting_categories ac ON ac.id=ae.category_id
            WHERE YEAR(ae.entry_date)=? AND MONTH(ae.entry_date)=?
            GROUP BY ac.group_name, ac.type ORDER BY ac.type DESC, total DESC");
        $s->execute([$year,$month]); return $s->fetchAll();
    }catch(Throwable $e){ return []; }
}
