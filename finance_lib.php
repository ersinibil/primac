<?php
/* OTS Finans Hesapları (Kasa/Banka/Kredi Kartı/POS) — paylaşılan fonksiyonlar (web + mobil).
 * finance_accounts.php / finance_account_view.php / mobile/kasa.php / mobile/account_view.php ortak kullanır. */

if(file_exists(__DIR__.'/audit_lib.php')) require_once __DIR__.'/audit_lib.php';

function finance_account_types(){
    return ['Banka','Kasa','Kredi Kartı','POS','Diğer'];
}

// Hesap finance_movements'ta (doğrudan ya da transfer hedefi olarak) ya da accounting_entries'te
// (Muhasebe modülü gider/gelir/personel ödemesi kaydı) kullanılmış mı?
function finance_account_has_movements($pdo, $id){
    $id=(int)$id;
    try{
        $s=$pdo->prepare("SELECT COUNT(*) c FROM finance_movements WHERE account_id=? OR target_account_id=?");
        $s->execute([$id,$id]);
        if((int)$s->fetch()['c'] > 0) return true;
        try{
            $a=$pdo->prepare("SELECT COUNT(*) c FROM accounting_entries WHERE account_id=?");
            $a->execute([$id]);
            if((int)$a->fetch()['c'] > 0) return true;
        }catch(Throwable $e){} // accounting_entries yoksa (eski kurulum) yok say
        return false;
    }catch(Throwable $e){ return true; } // emin olunamıyorsa güvenli tarafta kal, silmeyi engelle
}

// Hesap bilgilerini günceller (ad/tür/banka/IBAN/kart/para birimi/not/aktif). Bakiye alanlarına dokunmaz.
function finance_account_update($pdo, $id, array $data){
    $id = (int)$id;
    $name = trim($data['name'] ?? '');
    if($name==='') throw new Exception('Hesap adı zorunlu.');
    $type = $data['account_type'] ?? 'Kasa';
    if(!in_array($type, finance_account_types(), true)) $type='Diğer';

    // Audit log: eski değeri güncelleme öncesi oku
    $oldRow = null;
    try{
        $s = $pdo->prepare("SELECT id,name,account_type,bank_name,iban,card_last4,currency,notes,active FROM finance_accounts WHERE id=?");
        $s->execute([$id]);
        $oldRow = $s->fetch();
    }catch(Throwable $e){}

    $pdo->prepare("UPDATE finance_accounts SET name=?,account_type=?,bank_name=?,iban=?,card_last4=?,currency=?,notes=?,active=? WHERE id=?")
        ->execute([
            $name,
            $type,
            trim($data['bank_name'] ?? ''),
            trim($data['iban'] ?? ''),
            trim($data['card_last4'] ?? ''),
            trim($data['currency'] ?? '') ?: 'TRY',
            trim($data['notes'] ?? ''),
            !empty($data['active']) ? 1 : 0,
            $id
        ]);

    // Audit log: güncelleme işlemi başarılı, kaydı yap
    $userId = current_user()['id'] ?? null;
    if(function_exists('audit_log') && $oldRow){
        audit_log($userId, 'update', 'finance_accounts', $id, $oldRow, [
            'id'=>$oldRow['id'],
            'name'=>$name,
            'account_type'=>$type,
            'bank_name'=>trim($data['bank_name'] ?? ''),
            'iban'=>trim($data['iban'] ?? ''),
            'card_last4'=>trim($data['card_last4'] ?? ''),
            'currency'=>trim($data['currency'] ?? '') ?: 'TRY',
            'notes'=>trim($data['notes'] ?? ''),
            'active'=>!empty($data['active']) ? 1 : 0
        ]);
    }

    return true;
}

// Hesabı siler. Hareketlerde kullanılmışsa (referans bütünlüğü bozulmasın diye) kalıcı silmek yerine
// pasife alır (soft-delete) — proje genelindeki ürün/stok "aktif-pasif" deseniyle tutarlı.
// Dönüş: ['ok'=>bool, 'soft'=>bool, 'msg'=>string]
function finance_account_delete($pdo, $id){
    $id=(int)$id;
    if($id<1) return ['ok'=>false,'soft'=>false,'msg'=>'Geçersiz hesap.'];

    // Audit log: silinecek hesabın eski halini oku
    $oldRow = null;
    try{
        $s = $pdo->prepare("SELECT * FROM finance_accounts WHERE id=?");
        $s->execute([$id]);
        $oldRow = $s->fetch();
    }catch(Throwable $e){}

    $userId = current_user()['id'] ?? null;

    if(finance_account_has_movements($pdo,$id)){
        $pdo->prepare("UPDATE finance_accounts SET active=0 WHERE id=?")->execute([$id]);
        // Audit log: soft-delete (pasife alma) kaydı
        if(function_exists('audit_log') && $oldRow){
            audit_log($userId, 'update', 'finance_accounts', $id, $oldRow, array_merge($oldRow, ['active'=>0]));
        }
        return ['ok'=>true,'soft'=>true,'msg'=>'Bu hesapta finans hareketleri kayıtlı olduğu için kalıcı silinemedi, pasife alındı.'];
    }
    $pdo->prepare("DELETE FROM finance_accounts WHERE id=?")->execute([$id]);
    // Audit log: kalıcı silme kaydı
    if(function_exists('audit_log') && $oldRow){
        audit_log($userId, 'delete', 'finance_accounts', $id, $oldRow, null);
    }
    return ['ok'=>true,'soft'=>false,'msg'=>'Hesap silindi.'];
}

/* ---------------------------------------------------------------------------------------------
 * Finans HAREKETLERİ (finance_movements — tahsilat/ödeme kayıtları) — paylaşılan fonksiyonlar.
 * finance.php / finance_new.php / sil.php / mobile/payment.php / mobile/collection.php /
 * mobile/kasa.php / mobile/movement_view.php ortak kullanır.
 *
 * ÖNEMLİ: finance_movements diğer modüllerden de (satış, alış/satış belgesi, hesaplar arası
 * transfer) otomatik satır oluşturuyor (movement_type: 'sale','mobile_sale','document','transfer').
 * Bu satırlar başka tabloların (stock_movements, trade_documents.paid_amount, karşı hesap bakiyesi)
 * kaynağıdır — burada düzenlenip/silinirse o modüller senkronsuz kalır. Bu yüzden düzenleme/silme
 * SADECE elle girilen 'normal' (web finance_new.php) ve 'mobile' (mobil Ödeme/Tahsilat) tipli
 * hareketlerde izinlidir; diğerleri için fonksiyonlar Exception fırlatır.
 * --------------------------------------------------------------------------------------------- */

// Bu movement_type'lar elle girilmiş, güvenle düzenlenip/silinebilir kabul edilir.
function finance_movement_editable_types(){
    return ['normal','mobile'];
}

function finance_movement_get($pdo, $id){
    $s=$pdo->prepare("SELECT * FROM finance_movements WHERE id=?");
    $s->execute([(int)$id]);
    $r=$s->fetch();
    return $r ?: null;
}

// Bir hareketin hesap bakiyelerine yaptığı etkiyi geri alır (silme/düzenlemeden önce çağrılır).
function finance_movement_reverse_balance($pdo, array $row){
    $amount=(float)$row['amount'];
    if($amount<=0) return;
    if(($row['movement_type'] ?? '')==='transfer'){
        if(!empty($row['account_id'])) $pdo->prepare("UPDATE finance_accounts SET current_balance=current_balance+? WHERE id=?")->execute([$amount,$row['account_id']]);
        if(!empty($row['target_account_id'])) $pdo->prepare("UPDATE finance_accounts SET current_balance=current_balance-? WHERE id=?")->execute([$amount,$row['target_account_id']]);
        return;
    }
    if(empty($row['account_id'])) return;
    if($row['direction']==='in'){
        $pdo->prepare("UPDATE finance_accounts SET current_balance=current_balance-? WHERE id=?")->execute([$amount,$row['account_id']]);
    }else{
        $pdo->prepare("UPDATE finance_accounts SET current_balance=current_balance+? WHERE id=?")->execute([$amount,$row['account_id']]);
    }
}

// Yeni (veya güncellenmiş) hareketin hesap bakiyesine etkisini uygular.
function finance_movement_apply_balance($pdo, $direction, $accountId, $amount){
    $accountId=(int)$accountId;
    $amount=(float)$amount;
    if(!$accountId || $amount<=0) return;
    if($direction==='in'){
        $pdo->prepare("UPDATE finance_accounts SET current_balance=current_balance+? WHERE id=?")->execute([$amount,$accountId]);
    }else{
        $pdo->prepare("UPDATE finance_accounts SET current_balance=current_balance-? WHERE id=?")->execute([$amount,$accountId]);
    }
}

// Tahsilat/ödeme kaydını günceller. Bakiye etkisi: önce eskisi geri alınır, sonra yenisi uygulanır.
function finance_movement_update($pdo, $id, array $data){
    $id=(int)$id;
    $row=finance_movement_get($pdo,$id);
    if(!$row) throw new Exception('Hareket bulunamadı.');
    if(!in_array($row['movement_type'] ?? '', finance_movement_editable_types(), true)){
        throw new Exception('Bu hareket başka bir işlemden (satış/belge/transfer) otomatik oluşturulduğu için burada düzenlenemez.');
    }

    // Audit log: eski değer (şimdiki row)
    $oldRow = $row;

    $direction = ($data['direction'] ?? $row['direction'])==='in' ? 'in' : 'out';
    $amount = (float)str_replace(',', '.', (string)($data['amount'] ?? 0));
    if($amount<=0) throw new Exception('Tutar sıfırdan büyük olmalı.');
    $accountId = (int)($data['account_id'] ?? 0);
    if(!$accountId) throw new Exception('Hesap seçilmelidir.');
    $contactId = (int)($data['contact_id'] ?? 0) ?: null;
    $catId = (int)($data['category_id'] ?? 0) ?: null;
    // FINANCE UX REFACTOR (2026-07-04): "Ne kaydediyorsun?" sihirbazının "Personel Ödemesi" adımı
    // için — kolon migration 035'te zaten var (accounting.php'de kullanılıyordu), Ödeme/Gider
    // düzenlemesine buradan ilk kez ekleniyor. DB şeması DEĞİŞMEDİ, sadece bu UPDATE'e dahil edildi.
    $personnelId = (int)($data['personnel_id'] ?? 0) ?: null;
    $channel = trim($data['payment_channel'] ?? '') ?: $row['payment_channel'];
    $date = !empty($data['movement_date']) ? $data['movement_date'] : $row['movement_date'];
    $desc = trim($data['description'] ?? '');
    $ref = trim($data['reference_no'] ?? '');
    $status = $direction==='in' ? 'Tahsil Edildi' : 'Ödendi';

    finance_movement_reverse_balance($pdo,$row);

    $pdo->prepare("UPDATE finance_movements SET contact_id=?,category_id=?,personnel_id=?,direction=?,amount=?,payment_channel=?,account_id=?,status=?,movement_date=?,description=?,reference_no=? WHERE id=?")
        ->execute([$contactId,$catId,$personnelId,$direction,$amount,$channel,$accountId,$status,$date,$desc,$ref,$id]);

    finance_movement_apply_balance($pdo,$direction,$accountId,$amount);

    // Audit log: yeni değer
    $userId = current_user()['id'] ?? null;
    if(function_exists('audit_log')){
        audit_log($userId, 'update', 'finance_movements', $id, $oldRow, [
            'id'=>$id,
            'contact_id'=>$contactId,
            'category_id'=>$catId,
            'personnel_id'=>$personnelId,
            'direction'=>$direction,
            'amount'=>$amount,
            'payment_channel'=>$channel,
            'account_id'=>$accountId,
            'status'=>$status,
            'movement_date'=>$date,
            'description'=>$desc,
            'reference_no'=>$ref
        ]);
    }

    return true;
}

/* ---------------------------------------------------------------------------------------------
 * FINANCE UX REFACTOR (2026-07-04) — "Ne kaydediyorsun?" sihirbazı, paylaşılan tanım + tür türetme.
 * Kullanıcı şikayeti: Ödeme/Gider ekranında cari/kategori/personel/kasa/ödeme yöntemi karışıyor.
 * Çözüm DB'ye yeni bir "tür" kolonu EKLEMİYOR — sihirbazın hangi adımda olduğu hiçbir yerde
 * saklanmıyor, sadece mevcut dolu alanlara bakılarak (personnel_id/contact_id/category_id/
 * account_type) her ekranda YENİDEN türetiliyor (bkz. notifications_lib.php::notif_type_info()
 * ile aynı desen — saf fonksiyon, DB yazmaz). Bu sayede eski kayıtlar da (movement_type ne olursa
 * olsun) sihirbazda doğru adımla açılabiliyor, "sistemin geriye dönük yenilenebilir olması" isteği
 * bu şekilde karşılanıyor.
 * --------------------------------------------------------------------------------------------- */

// 7 sihirbaz seçeneğinin tek kaynağı — web+mobil, Ödeme/Gider+Muhasebe ekranlarının hepsi buradan
// okur. 'field' o adımda ZORUNLU olan asıl alanı belirtir (account_id/amount zaten her adımda
// zorunlu, ayrıca burada tekrar edilmiyor).
function finance_record_type_options(){
    return [
        'cari'      => ['label'=>'Cari Ödemesi',        'icon'=>'👥', 'field'=>'contact_id'],
        'isletme'   => ['label'=>'İşletme Gideri',      'icon'=>'🧾', 'field'=>'category_id'],
        'personel'  => ['label'=>'Personel Ödemesi',    'icon'=>'👷', 'field'=>'personnel_id'],
        'vergi'     => ['label'=>'Vergi / SGK',         'icon'=>'🏛', 'field'=>'category_id', 'group_hint'=>['vergi','sgk']],
        'kart'      => ['label'=>'Banka / Kredi / Kart','icon'=>'💳', 'field'=>'account_id', 'group_hint'=>['kredi','kart','banka']],
        'arac'      => ['label'=>'Araç Gideri',         'icon'=>'🚗', 'field'=>'category_id', 'group_hint'=>['araç','arac','vehicle']],
        'diger'     => ['label'=>'Diğer',               'icon'=>'📋', 'field'=>'description'],
    ];
}

// Var olan bir finance_movements satırından en olası sihirbaz adımını türetir. $categoryGroupName
// ve $accountType çağıran ekran zaten JOIN ile çekiyorsa (liste/düzenleme sorgusu) parametre olarak
// verilebilir — yoksa fonksiyon sadece $row'daki ham kolonlara bakar (personnel_id/contact_id/
// category_id dolu mu). Öncelik sırası önemli: aynı satırda hem personnel_id hem contact_id doluysa
// (bugünkü accounting.php formu ikisine de izin veriyor) "Personel Ödemesi" kazanır.
function finance_record_type_info($row, $categoryGroupName=null, $accountType=null){
    $groupLc = mb_strtolower((string)$categoryGroupName);
    $accLc = mb_strtolower((string)$accountType);
    if(!empty($row['personnel_id'])) return 'personel';
    if(in_array((string)($row['payment_type'] ?? ''), ['sgk','vergi'], true)) return 'vergi';
    if($groupLc!=='' && (strpos($groupLc,'vergi')!==false || strpos($groupLc,'sgk')!==false)) return 'vergi';
    if($groupLc!=='' && (strpos($groupLc,'araç')!==false || strpos($groupLc,'arac')!==false)) return 'arac';
    if(strpos($accLc,'kredi kartı')!==false || strpos($accLc,'kredi')!==false || strpos($groupLc,'kart')!==false) return 'kart';
    if(!empty($row['contact_id'])) return 'cari';
    if(!empty($row['category_id'])) return 'isletme';
    return 'diger';
}

// Tahsilat/ödeme kaydını siler, hesap bakiyesini geri alır. Dönüş: ['ok'=>bool,'msg'=>string]
function finance_movement_delete($pdo, $id){
    $id=(int)$id;
    if($id<1) return ['ok'=>false,'msg'=>'Geçersiz hareket.'];
    $row=finance_movement_get($pdo,$id);
    if(!$row) return ['ok'=>false,'msg'=>'Hareket bulunamadı.'];
    if(!in_array($row['movement_type'] ?? '', finance_movement_editable_types(), true)){
        return ['ok'=>false,'msg'=>'Bu hareket başka bir işlemden (satış/belge/transfer) otomatik oluşturulduğu için burada silinemez.'];
    }
    finance_movement_reverse_balance($pdo,$row);
    $pdo->prepare("DELETE FROM finance_movements WHERE id=?")->execute([$id]);

    // Audit log: silme kaydı
    $userId = current_user()['id'] ?? null;
    if(function_exists('audit_log')){
        audit_log($userId, 'delete', 'finance_movements', $id, $row, null);
    }

    return ['ok'=>true,'msg'=>'Hareket silindi, hesap bakiyesi güncellendi.'];
}
