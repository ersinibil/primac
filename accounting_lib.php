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

// KDV oranı seçenekleri (TR standart oranları)
function acc_vat_rates(){ return [1,10,20]; }

// $rawAmount kullanıcının girdiği tutar. mode='dahil' → tutar zaten toplam, KDV ayrıca hesaplanır
// (bilgi amaçlı, tutar değişmez). mode='haric' → tutar KDV'siz taban kabul edilir, toplam
// (amount kolonuna yazılacak gerçek tutar) = taban + KDV hesaplanır. mode='yok' → KDV yok.
// Dönen: ['amount'=>gerçek toplam (DB'ye yazılacak), 'vat_rate'=>..., 'vat_amount'=>...]
function acc_calc_vat($rawAmount, $mode, $rate){
    $rawAmount=(float)$rawAmount;
    if($mode==='haric' && $rate>0){
        $vatAmount=round($rawAmount*$rate/100,2);
        return ['amount'=>round($rawAmount+$vatAmount,2),'vat_rate'=>$rate,'vat_amount'=>$vatAmount];
    }
    if($mode==='dahil' && $rate>0){
        // Toplam zaten KDV dahil — içindeki KDV payını geriye doğru ayıkla (bilgi amaçlı)
        $vatAmount=round($rawAmount-($rawAmount/(1+$rate/100)),2);
        return ['amount'=>$rawAmount,'vat_rate'=>$rate,'vat_amount'=>$vatAmount];
    }
    return ['amount'=>$rawAmount,'vat_rate'=>null,'vat_amount'=>0];
}

// 2026-07-03: Muhasebe artık ayrı bir tablo (accounting_entries) değil — finance_movements'ta
// movement_type='muhasebe' olan satırlar. Bkz. database/migrations/035_finance_accounting_merge.sql.
// Dışarıya (accounting.php'ye) dönen veri şekli aynı kalır: 'gelir'/'gider' dili korunur,
// direction('in'/'out') <-> type('gelir'/'gider') çevrimi bu dosyada yapılır.

function acc_summary($pdo, $month=null, $year=null){
    $month=$month ?: (int)date('m');
    $year=$year ?: (int)date('Y');
    try{
        $s=$pdo->prepare("SELECT direction,SUM(amount) total FROM finance_movements WHERE movement_type='muhasebe' AND YEAR(movement_date)=? AND MONTH(movement_date)=? GROUP BY direction");
        $s->execute([$year,$month]); $rows=$s->fetchAll(PDO::FETCH_KEY_PAIR);
        return ['gelir'=>(float)($rows['in']??0),'gider'=>(float)($rows['out']??0),'month'=>$month,'year'=>$year];
    }catch(Throwable $e){ return ['gelir'=>0,'gider'=>0,'month'=>$month,'year'=>$year]; }
}

function acc_personnel_summary($pdo, $year=null){
    $year=$year ?: (int)date('Y');
    try{
        $s=$pdo->prepare("SELECT p.name, SUM(fm.amount) total, fm.payment_type
            FROM finance_movements fm JOIN personnel p ON p.id=fm.personnel_id
            WHERE fm.movement_type='muhasebe' AND YEAR(fm.movement_date)=? AND fm.personnel_id IS NOT NULL
            GROUP BY fm.personnel_id, fm.payment_type ORDER BY p.name");
        $s->execute([$year]); return $s->fetchAll();
    }catch(Throwable $e){ return []; }
}

function acc_group_summary($pdo, $month, $year){
    try{
        $s=$pdo->prepare("SELECT ac.group_name, IF(fm.direction='in','gelir','gider') type, SUM(fm.amount) total
            FROM finance_movements fm JOIN accounting_categories ac ON ac.id=fm.category_id
            WHERE fm.movement_type='muhasebe' AND YEAR(fm.movement_date)=? AND MONTH(fm.movement_date)=?
            GROUP BY ac.group_name, fm.direction ORDER BY fm.direction ASC, total DESC");
        $s->execute([$year,$month]); return $s->fetchAll();
    }catch(Throwable $e){ return []; }
}

// Muhasebe kaydını düzenler. Hesap bakiyesi: eski etkiyi geri al, yeni etkiyi uygula.
function accounting_entry_update($pdo, $id, array $data){
    $id=(int)$id;
    // Eski kaydı al (movement_type='muhasebe' guard — bu fonksiyon başka türden bir finans
    // hareketini asla düzenlemesin, sadece muhasebe ekranından girilenleri)
    $s=$pdo->prepare("SELECT * FROM finance_movements WHERE id=? AND movement_type='muhasebe'");
    $s->execute([$id]);
    $old=$s->fetch();
    if(!$old) throw new Exception('Kayıt bulunamadı.');
    $oldType = $old['direction']==='in' ? 'gelir' : 'gider';

    $type=$data['type'] ?? $oldType;
    $rawAmount=(float)str_replace(',','.',$data['amount'] ?? $old['amount']);
    $vatMode=$data['vat_mode'] ?? $old['vat_mode'] ?? 'yok';
    $vatRate=(float)($data['vat_rate'] ?? $old['vat_rate'] ?? 0);
    $vc=acc_calc_vat($rawAmount,$vatMode,$vatRate);
    $amount=$vc['amount'];
    $date=$data['entry_date'] ?: $old['movement_date'];
    // GİDER TÜRÜ CONTEXT-AWARE (2026-07-04): category_id artık Gider tarafında sihirbazda
    // gösterilmiyor (Gider Türü artık payment_type) — sadece Gelir (Kategori) formu bu alanı
    // yönetiyor. Gider düzenlemesinde formda bu alan hiç yok/gizli olduğu için eski kaydın
    // category_id'sini SIFIRLAMAMAK için (varsa) korunuyor, sadece Gelir düzenlemesinde güncelleniyor.
    $catId = $type==='gelir' ? ((int)($data['category_id']??$old['category_id']) ?: null) : $old['category_id'];
    $desc=trim($data['description'] ?? $old['description'] ?? '');
    $refNo=trim($data['reference_no'] ?? $old['reference_no'] ?? '');
    $accId=(int)($data['account_id']??$old['account_id']) ?: null;
    $pid=(int)($data['personnel_id']??$old['personnel_id']) ?: null;
    $pt=trim($data['payment_type'] ?? $old['payment_type'] ?? '');
    $contactId=array_key_exists('contact_id',$data) ? ((int)$data['contact_id'] ?: null) : $old['contact_id'];
    $direction = $type==='gelir' ? 'in' : 'out';

    if($amount<=0) throw new Exception('Tutar sıfırdan büyük olmalı.');

    // Eski hesap bakiyesini geri al — YÖN TERS ÇEVRİLİR (gelir eklemişti → şimdi çıkar, gider çıkarmıştı → şimdi ekle)
    if($old['account_id']){
        $dir=$old['direction']==='in'?'-':'+';
        try{ $pdo->prepare("UPDATE finance_accounts SET current_balance=current_balance{$dir}? WHERE id=?")->execute([$old['amount'],$old['account_id']]); }catch(Throwable $e){}
    }

    // Kaydı güncelle
    $pdo->prepare("UPDATE finance_movements SET movement_date=?,direction=?,category_id=?,amount=?,vat_mode=?,vat_rate=?,vat_amount=?,description=?,reference_no=?,account_id=?,personnel_id=?,payment_type=?,contact_id=?,status=? WHERE id=?")
        ->execute([$date,$direction,$catId,$amount,$vatMode,$vc['vat_rate'],$vc['vat_amount'],$desc,$refNo,$accId,$pid,$pt,$contactId,($direction==='in'?'Tahsil Edildi':'Ödendi'),$id]);

    // Yeni hesap bakiyesini uygula
    if($accId){
        $dir=$direction==='in'?'+':'-';
        try{ $pdo->prepare("UPDATE finance_accounts SET current_balance=current_balance{$dir}? WHERE id=?")->execute([$amount,$accId]); }catch(Throwable $e){}
    }

    return true;
}

// Muhasebe kaydını siler, hesap bakiyesini geri alır.
function accounting_entry_delete($pdo, $id){
    $id=(int)$id;
    if($id<1) return ['ok'=>false,'msg'=>'Geçersiz kayıt.'];

    $s=$pdo->prepare("SELECT * FROM finance_movements WHERE id=? AND movement_type='muhasebe'");
    $s->execute([$id]);
    $row=$s->fetch();
    if(!$row) return ['ok'=>false,'msg'=>'Kayıt bulunamadı.'];

    // Hesap bakiyesini geri al — YÖN TERS ÇEVRİLİR (gelir eklemişti → şimdi çıkar, gider çıkarmıştı → şimdi ekle)
    if($row['account_id']){
        $dir=$row['direction']==='in'?'-':'+';
        try{ $pdo->prepare("UPDATE finance_accounts SET current_balance=current_balance{$dir}? WHERE id=?")->execute([$row['amount'],$row['account_id']]); }catch(Throwable $e){}
    }

    $pdo->prepare("DELETE FROM finance_movements WHERE id=? AND movement_type='muhasebe'")->execute([$id]);
    return ['ok'=>true,'msg'=>'Kayıt silindi, hesap bakiyesi güncellendi.'];
}
