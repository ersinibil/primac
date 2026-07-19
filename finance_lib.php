<?php
/* OTS Finans Hesapları (Kasa/Banka/Kredi Kartı/POS) — paylaşılan fonksiyonlar (web + mobil).
 * finance_accounts.php / finance_account_view.php / mobile/kasa.php / mobile/account_view.php ortak kullanır. */

if(file_exists(__DIR__.'/audit_lib.php')) require_once __DIR__.'/audit_lib.php';

function finance_account_types(){
    return ['Banka','Kasa','Kredi Kartı','POS','Diğer'];
}

/* ---------------------------------------------------------------------------------------------
 * Hesap Listesi Filtreleme (FINANCE ACCOUNT LIST FILTER UX, 2026-07-14) — web (finance_accounts.php)
 * + mobil (mobile/kasa.php) ORTAK. Tek doğru kaynak burada, iki ayrı yerde tekrarlanıp
 * birbirinden sapma riski taşımaz.
 * ------------------------------------------------------------------------------------------- */

// Sekme grubu: 3 ana tür (Banka/Kasa/Kredi Kartı) kendi başına, geri kalan HER ŞEY (POS, Diğer,
// ileride eklenecek yeni bir tür) "Diğer" sekmesinin altında toplanır — yeni bir tür eklenirse
// kod değişmeden otomatik "Diğer" altında görünür, kaybolmaz.
function finance_account_main_types(){
    return ['Banka','Kasa','Kredi Kartı'];
}

// $type filtre değeri normalize: gerçek account_type değerlerinden biri (geriye dönük uyumluluk —
// finance.php'nin var olan ?type=Banka/Kasa/Kredi Kartı/POS derin linkleri KIRILMAZ, tek tek
// eşleşmeye devam eder) YA DA yeni 'Diger' sekme değeri (3 ana grup DIŞINDAKİ tüm türler).
// Tanınmayan/boş değer → filtre yok ("Tümü"), whitelist dışı bir string asla SQL'e ham yansımaz.
function finance_account_filter_where($type, $status, $bank, $q){
    $where = [];
    $params = [];

    $mainTypes = finance_account_main_types();
    if($type === 'Diger'){
        $placeholders = implode(',', array_fill(0, count($mainTypes), '?'));
        $where[] = "account_type NOT IN ($placeholders)";
        foreach($mainTypes as $t) $params[] = $t;
    } elseif(in_array($type, finance_account_types(), true)){
        $where[] = 'account_type=?';
        $params[] = $type;
    }
    // (aksi halde: type boş ya da tanınmayan değer → filtre eklenmez, "Tümü")

    if($status === 'active'){
        $where[] = 'active=1';
    } elseif($status === 'passive'){
        $where[] = 'active=0';
    }
    // (aksi halde: 'active' değilse mevcut ekranın eski varsayılanı korunur — filtre yok, tümü)

    $bank = trim((string)$bank);
    if($bank !== ''){
        // utf8mb4_unicode_ci zaten çoğu durumda case-insensitive eşleşir ama Türkçe İ/ı için
        // güvenilir değil — UPPER(TRIM()) ile açıkça normalize edip karşılaştırıyoruz.
        $where[] = 'UPPER(TRIM(bank_name))=UPPER(TRIM(?))';
        $params[] = $bank;
    }

    $q = trim((string)$q);
    if($q !== ''){
        $where[] = '(name LIKE ? OR bank_name LIKE ? OR iban LIKE ? OR card_last4 LIKE ?)';
        $like = '%'.$q.'%';
        array_push($params, $like, $like, $like, $like);
    }

    return [$where ? ('WHERE '.implode(' AND ', $where)) : '', $params];
}

// Banka filtre dropdown'ının seçenekleri — SABİT DEĞİL, mevcut kayıtlardan DISTINCT üretilir.
// Büyük/küçük harf farkı olan aynı banka adı ("GARANTİ" / "Garanti") kullanıcıya mükerrer
// seçenek olarak GÖRÜNMEZ — normalize edilmiş anahtara göre gruplanır, grup içindeki en sık
// kullanılan yazım biçimi temsilci etiket olarak gösterilir. Veri tabanında hiçbir satır
// otomatik UPDATE EDİLMEZ, sadece sunumda normalize edilir.
function finance_account_bank_options($pdo){
    try{
        $rows = $pdo->query("SELECT bank_name, COUNT(*) c FROM finance_accounts
            WHERE bank_name IS NOT NULL AND TRIM(bank_name)<>'' GROUP BY bank_name")->fetchAll();
    }catch(Throwable $e){ return []; }
    $groups = [];
    foreach($rows as $r){
        $label = trim($r['bank_name']);
        if($label==='') continue;
        $key = mb_strtoupper($label, 'UTF-8');
        if(!isset($groups[$key]) || (int)$r['c'] > $groups[$key]['c']){
            $groups[$key] = ['label'=>$label, 'c'=>(int)$r['c']];
        }
    }
    $labels = array_map(function($g){ return $g['label']; }, $groups);
    sort($labels, SORT_FLAG_CASE | SORT_STRING);
    return $labels;
}

// Tür sekmelerinin yanındaki adet sayaçları — TEK aggregate sorgu (ağır ek sorgu YOK). $status
// mevcut durum filtresini de yansıtır (ör. "Aktif" seçiliyken sekme sayıları da sadece aktifleri sayar).
function finance_account_type_counts($pdo, $status=''){
    $counts = ['all'=>0, 'Kasa'=>0, 'Banka'=>0, 'Kredi Kartı'=>0, 'Diger'=>0];
    $statusWhere = $status==='active' ? 'WHERE active=1' : ($status==='passive' ? 'WHERE active=0' : '');
    try{
        $rows = $pdo->query("SELECT account_type, COUNT(*) c FROM finance_accounts $statusWhere GROUP BY account_type")->fetchAll();
    }catch(Throwable $e){ return $counts; }
    $mainTypes = finance_account_main_types();
    foreach($rows as $r){
        $c = (int)$r['c'];
        $counts['all'] += $c;
        if(in_array($r['account_type'], $mainTypes, true)) $counts[$r['account_type']] += $c;
        else $counts['Diger'] += $c;
    }
    return $counts;
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
        // P0 VERİ BÜTÜNLÜĞÜ (2026-07-19, pilot öncesi kapanış — gerçek bulgu): bu fonksiyon önceden
        // SADECE finance_movements + accounting_entries'e bakıyordu — trade_documents.account_id
        // (006_trade.sql) ve checks_notes.settle_account_id (048_checks_notes_lifecycle.sql) da
        // finance_accounts.id'ye referans veren GERÇEK şema alanları ama hiç kontrol edilmiyordu.
        // Normal akışta bu ikisi zaten finance_movements'ta da bir satırla eşleşir (yukarıdaki kontrol
        // zaten yakalar) — ama tutarsız/eski/elle düzeltilmiş bir kayıtta SADECE bu referans kalmış
        // olabilir. "Emin olunamıyorsa güvenli tarafta kal" ilkesiyle ayrıca kontrol ediliyor.
        try{
            $td=$pdo->prepare("SELECT COUNT(*) c FROM trade_documents WHERE account_id=?");
            $td->execute([$id]);
            if((int)$td->fetch()['c'] > 0) return true;
        }catch(Throwable $e){}
        try{
            $cn=$pdo->prepare("SELECT COUNT(*) c FROM checks_notes WHERE settle_account_id=?");
            $cn->execute([$id]);
            if((int)$cn->fetch()['c'] > 0) return true;
        }catch(Throwable $e){}
        return false;
    }catch(Throwable $e){ return true; } // emin olunamıyorsa güvenli tarafta kal, silmeyi engelle
}

/**
 * VERİ BÜTÜNLÜĞÜ RAPORU (2026-07-19, pilot öncesi kapanış — salt-okunur, hiçbir şeyi değiştirmez).
 * Gerçek şemadaki finance_accounts.id referanslarından (finance_movements.account_id/
 * target_account_id, trade_documents.account_id, checks_notes.settle_account_id) artık VAR OLMAYAN
 * bir hesaba işaret edenleri bulur — yani hesabı silinmiş ama kaydı yetim kalmış satırlar. Otomatik
 * onarım YAPMAZ ("otomatik uydurma hesap oluşturma" — Product Owner kararı), sadece raporlar.
 * @return array ['finance_movements'=>[...], 'trade_documents'=>[...], 'checks_notes'=>[...]]
 */
function finance_account_orphan_report($pdo){
    $out=['finance_movements'=>[],'trade_documents'=>[],'checks_notes'=>[]];
    try{
        $s=$pdo->query("SELECT fm.id,fm.movement_date,fm.description,fm.amount,fm.direction,fm.account_id,fm.target_account_id,fm.contact_id,c.name contact_name
            FROM finance_movements fm LEFT JOIN contacts c ON c.id=fm.contact_id
            WHERE (fm.account_id IS NOT NULL AND NOT EXISTS(SELECT 1 FROM finance_accounts fa WHERE fa.id=fm.account_id))
               OR (fm.target_account_id IS NOT NULL AND NOT EXISTS(SELECT 1 FROM finance_accounts fa WHERE fa.id=fm.target_account_id))
            ORDER BY fm.id DESC LIMIT 200");
        $out['finance_movements']=$s->fetchAll();
    }catch(Throwable $e){}
    try{
        $s=$pdo->query("SELECT id,document_no,document_type,document_date,grand_total,account_id FROM trade_documents
            WHERE account_id IS NOT NULL AND NOT EXISTS(SELECT 1 FROM finance_accounts fa WHERE fa.id=trade_documents.account_id)
            ORDER BY id DESC LIMIT 200");
        $out['trade_documents']=$s->fetchAll();
    }catch(Throwable $e){}
    try{
        $s=$pdo->query("SELECT cn.id,cn.type,cn.number,cn.amount,cn.status,cn.settle_account_id,c.name contact_name FROM checks_notes cn LEFT JOIN contacts c ON c.id=cn.contact_id
            WHERE cn.settle_account_id IS NOT NULL AND NOT EXISTS(SELECT 1 FROM finance_accounts fa WHERE fa.id=cn.settle_account_id)
            ORDER BY cn.id DESC LIMIT 200");
        $out['checks_notes']=$s->fetchAll();
    }catch(Throwable $e){}
    return $out;
}
function finance_account_orphan_count($pdo){
    $r=finance_account_orphan_report($pdo);
    return count($r['finance_movements'])+count($r['trade_documents'])+count($r['checks_notes']);
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

/**
 * FİNANS ÇEKİRDEK DÜZELTMESİ (2026-07-10) — bir finans hareketinin ekranda gösterilecek "Tip"
 * etiketi. Satış/Alış/Belge kaynaklı otomatik kayıtlar açık cari borç/alacak temsil eder, GERÇEK
 * bir tahsilat/ödeme değildir — bu yüzden "Tahsilat"/"Ödeme" etiketi SADECE elle girilmiş
 * (movement_type: normal/mobile) gerçek nakit/banka hareketlerinde kullanılır. 'document' tipi
 * hem Satış Belgesi hem Alış Belgesi olabilir — ikisi de aynı movement_type'ı paylaştığı için
 * (trade_core.php::trade_apply_document()) ayrım $row['direction']'dan yapılır (satış belgesi
 * her zaman 'in', alış belgesi her zaman 'out' — bkz. trade_core.php $direction ataması).
 */
function finance_movement_type_label($row){
    $type = $row['movement_type'] ?? '';
    if($type === 'transfer') return 'Transfer';
    if(in_array($type, ['sale','mobile_sale'], true)) return 'Satış';
    if($type === 'purchase') return 'Alış';
    if($type === 'document') return ($row['direction'] ?? '')==='in' ? 'Satış Belgesi' : 'Alış Belgesi';
    return ($row['direction'] ?? '')==='in' ? 'Tahsilat' : 'Ödeme';
}

/**
 * Bir hareketin GERÇEK bir kasa/banka/kart hareketi olup olmadığını belirler — satış/alış
 * (Bekliyor) hiçbir hesabı etkilemediği için account_id NULL kalır; bu yüzden "gerçek nakit"
 * kriteri movement_type enumerasyonu yerine account_id IS NOT NULL üzerinden, kendi kendini
 * tanımlayan (self-describing) bir şekilde kurulur — ileride yeni bir movement_type eklense
 * bile bu kriter otomatik doğru kalır.
 */
function finance_movement_is_real_cash_sql($alias='finance_movements'){
    return "$alias.account_id IS NOT NULL";
}

/**
 * TEK merkezi karar fonksiyonu (FINANCE CRUD UX PATCH 001, 2026-07-12): bir finance_movements
 * satırının hangi ekranda görünürse görünsün (contact_view.php, finance_account_view.php,
 * finance.php, mobil eşdeğerleri) Düzenle/Sil gösterip göstermeyeceğine ve gösterilmiyorsa hangi
 * "kaynağı aç" bağlantısının sunulacağına karar verir. Buton görünürlüğü her ekranda ayrı ayrı
 * yazılmasın diye — çağıran taraf sadece bunu okur, kendi $canEdit mantığını YAZMAZ.
 *
 * Kesin ayrım: SADECE elle girilmiş bağımsız hareketler (movement_type: normal/mobile — bağımsız
 * tahsilat/ödeme/gelir/gider) düzenlenebilir/silinebilir. document_id dolu, sale/purchase kaynaklı,
 * settles_movement_id ilişkili veya transfer gibi otomatik/bağlı hareketler KAPALI — bunlar için
 * ait olduğu ekranın linki gösterilir.
 *
 * @return array [
 *   'manual'=>bool, 'editable'=>bool, 'deletable'=>bool, 'source_type'=>string|null,
 *   'source_label'=>string|null, 'source_url'=>string|null, 'block_reason'=>string|null,
 * ]
 */
function finance_movement_actions($row){
    $type = $row['movement_type'] ?? '';
    $manual = in_array($type, finance_movement_editable_types(), true);

    if($manual){
        return ['manual'=>true, 'editable'=>true, 'deletable'=>true,
            'source_type'=>null, 'source_label'=>null, 'source_url'=>null, 'block_reason'=>null];
    }

    $hasDocument = !empty($row['document_id']);
    $hasSettles = !empty($row['settles_movement_id']); // migration 042 — henüz hiçbir yazma yolu yok, ileriye dönük

    if($hasDocument){
        $sourceType='document'; $sourceLabel='🧾 Belgeyi Aç';
        $sourceUrl='trade_document_view.php?id='.(int)$row['document_id'];
        $blockReason='Bu hareket bir Alış/Satış Belgesine bağlı.';
    }elseif(in_array($type, ['sale','mobile_sale'], true)){
        $sourceType='sale'; $sourceLabel='🧾 Satışı Aç';
        $sourceUrl='sales.php?edit_id='.(int)$row['id'];
        $blockReason='Bu hareket bir satıştan otomatik oluştu.';
    }elseif($type === 'purchase'){
        $sourceType='purchase'; $sourceLabel='🛒 Alışı Aç';
        $sourceUrl='purchase.php?edit_id='.(int)$row['id'];
        $blockReason='Bu hareket bir alıştan otomatik oluştu.';
    }elseif($hasSettles){
        $sourceType='settlement'; $sourceLabel='🔗 Bağlı Hareketi Aç';
        $sourceUrl='finance.php';
        $blockReason='Bu hareket başka bir kaydı kapatıyor.';
    }elseif($type === 'transfer'){
        $sourceType='transfer'; $sourceLabel=null; $sourceUrl=null;
        $blockReason='Bu hareket bir hesaplar arası transferden otomatik oluştu.';
    }else{
        $sourceType=null; $sourceLabel=null; $sourceUrl=null;
        $blockReason='Bu hareket başka bir işlemden otomatik oluştu.';
    }

    return ['manual'=>false, 'editable'=>false, 'deletable'=>false,
        'source_type'=>$sourceType, 'source_label'=>$sourceLabel, 'source_url'=>$sourceUrl, 'block_reason'=>$blockReason];
}

/**
 * "context" + basit bir tamsayı id'den GÜVENLİ bir iç URL üretir (FINANCE CRUD UX PATCH 001,
 * 2026-07-12) — asla ham bir URL/host kabul ETMEZ, sadece bilinen birkaç ekran adı + id.
 * contact_view.php/finance_account_view.php'den Düzenle/Sil'e tıklandığında, işlem sonrası
 * kullanıcı geldiği ekrana dönsün diye finance_new.php ve sil.php tarafından kullanılır.
 * Open redirect YOK — whitelist dışına asla çıkamaz.
 */
function finance_return_url($context, $ref, $default='finance.php'){
    $ref = (int)$ref;
    switch($context){
        case 'contact': return $ref>0 ? 'contact_view.php?id='.$ref : $default;
        case 'account': return $ref>0 ? 'finance_account_view.php?id='.$ref : $default;
        case 'finance': return 'finance.php';
        default:        return $default;
    }
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
    // GİDER TÜRÜ CONTEXT-AWARE (2026-07-04): category_id artık Ödeme/Gider (out) tarafında sihirbazda
    // gösterilmiyor (Gider Türü artık payment_type) — sadece Tahsilat (in/Kategori) formu bu alanı
    // yönetiyor. Ödeme (out) düzenlemesinde formda bu alan hiç yok/gizli olduğu için eski kaydın
    // category_id'sini SIFIRLAMAMAK için (varsa) korunuyor, sadece 'in' düzenlemesinde güncelleniyor.
    $catId = $direction==='in' ? ((int)($data['category_id'] ?? 0) ?: null) : $row['category_id'];
    // FINANCE UX REFACTOR (2026-07-04): "Ne kaydediyorsun?" sihirbazının "Personel Ödemesi" adımı
    // için — kolon migration 035'te zaten var (accounting.php'de kullanılıyordu), Ödeme/Gider
    // düzenlemesine buradan ilk kez ekleniyor. DB şeması DEĞİŞMEDİ, sadece bu UPDATE'e dahil edildi.
    $personnelId = (int)($data['personnel_id'] ?? 0) ?: null;
    // GİDER TÜRÜ CONTEXT-AWARE (2026-07-04): sihirbazın adıma özel "Gider Türü" seçimi — mevcut
    // payment_type kolonuna yazılır (migration 035'te zaten vardı). DB şeması DEĞİŞMEDİ.
    $paymentType = array_key_exists('payment_type',$data) ? (trim((string)$data['payment_type']) ?: null) : ($row['payment_type'] ?? null);
    $channel = trim($data['payment_channel'] ?? '') ?: $row['payment_channel'];
    $date = !empty($data['movement_date']) ? $data['movement_date'] : $row['movement_date'];
    $desc = trim($data['description'] ?? '');
    $ref = trim($data['reference_no'] ?? '');
    $status = $direction==='in' ? 'Tahsil Edildi' : 'Ödendi';

    finance_movement_reverse_balance($pdo,$row);

    $pdo->prepare("UPDATE finance_movements SET contact_id=?,category_id=?,personnel_id=?,direction=?,amount=?,payment_channel=?,payment_type=?,account_id=?,status=?,movement_date=?,description=?,reference_no=? WHERE id=?")
        ->execute([$contactId,$catId,$personnelId,$direction,$amount,$channel,$paymentType,$accountId,$status,$date,$desc,$ref,$id]);

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
            'payment_type'=>$paymentType,
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
    // GİDER TÜRÜ CONTEXT-AWARE (2026-07-04): payment_type artık her adımın kendine özgü (adımlar
    // arasında çakışmayan) Gider Türü değerini taşıyor (bkz. finance_expense_type_options()) — en
    // kesin ipucu bu olduğu için eski grup-adı/hesap-türü tahmininden ÖNCE kontrol edilir.
    $ptStep = finance_expense_type_step_for_value($row['payment_type'] ?? '');
    if($ptStep) return $ptStep;
    if(in_array((string)($row['payment_type'] ?? ''), ['sgk','vergi'], true)) return 'vergi';
    if($groupLc!=='' && (strpos($groupLc,'vergi')!==false || strpos($groupLc,'sgk')!==false)) return 'vergi';
    if($groupLc!=='' && (strpos($groupLc,'araç')!==false || strpos($groupLc,'arac')!==false)) return 'arac';
    if(strpos($accLc,'kredi kartı')!==false || strpos($accLc,'kredi')!==false || strpos($groupLc,'kart')!==false) return 'kart';
    if(!empty($row['contact_id'])) return 'cari';
    if(!empty($row['category_id'])) return 'isletme';
    return 'diger';
}

/* ---------------------------------------------------------------------------------------------
 * GİDER TÜRÜ CONTEXT-AWARE SİHİRBAZ (2026-07-04) — "Ne kaydediyorsun?" adımına göre "Gider Türü"
 * dropdown'unun BİREBİR seçenek listesi (web+mobil TEK kaynak). Önceden bu liste her adımda aynı
 * (tüm accounting_categories 'gider' satırları) geliyordu — artık adıma özel, sabit bir katalog.
 * DB şemasına YENİ bir kolon eklenmedi: değer, finance_movements.payment_type'a yazılır (bu kolon
 * migration 035'te zaten vardı, önceden sadece accounting.php'nin "Personel ise Ödeme Türü" alt
 * seçiminde kullanılıyordu — burada TÜM adımlara genişletildi). accounting_categories/category_id
 * dokunulmadı, sadece Gelir/Kategori tarafında (Tahsilat akışı) kullanılmaya devam ediyor.
 * --------------------------------------------------------------------------------------------- */
function finance_expense_type_options(){
    return [
        'cari' => [
            ['v'=>'borc_odemesi','t'=>'Borç Ödemesi'],
            ['v'=>'avans_iadesi','t'=>'Avans İadesi'],
            ['v'=>'cari_mahsup','t'=>'Cari Mahsup'],
            ['v'=>'diger','t'=>'Diğer'],
        ],
        'isletme' => [
            ['v'=>'kira','t'=>'Kira'],
            ['v'=>'elektrik','t'=>'Elektrik'],
            ['v'=>'su','t'=>'Su'],
            ['v'=>'internet','t'=>'İnternet'],
            ['v'=>'telefon','t'=>'Telefon'],
            ['v'=>'yazilim','t'=>'Yazılım'],
            ['v'=>'ofis_malzemesi','t'=>'Ofis Malzemesi'],
            ['v'=>'temizlik','t'=>'Temizlik'],
            ['v'=>'kargo','t'=>'Kargo'],
            ['v'=>'bakim','t'=>'Bakım'],
            ['v'=>'aidat','t'=>'Aidat'],
            ['v'=>'diger','t'=>'Diğer'],
        ],
        'personel' => [
            ['v'=>'maas','t'=>'Maaş'],
            ['v'=>'avans','t'=>'Avans'],
            ['v'=>'prim','t'=>'Prim / İkramiye'],
            ['v'=>'sgk_isveren','t'=>'SGK Primi İşveren'],
            ['v'=>'sgk_isci','t'=>'SGK Primi İşçi'],
            ['v'=>'yol','t'=>'Yol Gideri'],
            ['v'=>'yemek','t'=>'Yemek'],
            ['v'=>'konaklama','t'=>'Konaklama'],
            ['v'=>'harcirah','t'=>'Harcırah'],
            ['v'=>'diger','t'=>'Diğer'],
        ],
        'vergi' => [
            ['v'=>'muhtasar','t'=>'Muhtasar'],
            ['v'=>'kdv','t'=>'KDV'],
            ['v'=>'damga','t'=>'Damga Vergisi'],
            ['v'=>'gecici_vergi','t'=>'Geçici Vergi'],
            ['v'=>'sgk','t'=>'SGK'],
            ['v'=>'bagkur','t'=>'Bağkur'],
            ['v'=>'diger','t'=>'Diğer'],
        ],
        'kart' => [
            ['v'=>'kredi_karti_odemesi','t'=>'Kredi Kartı Ödemesi'],
            ['v'=>'kredi_odemesi','t'=>'Kredi Ödemesi'],
            ['v'=>'faiz','t'=>'Faiz'],
            ['v'=>'komisyon','t'=>'Komisyon'],
            ['v'=>'eft_havale','t'=>'EFT / Havale'],
            ['v'=>'pos_kesintisi','t'=>'POS Kesintisi'],
            ['v'=>'diger','t'=>'Diğer'],
        ],
        'arac' => [
            ['v'=>'yakit','t'=>'Yakıt'],
            ['v'=>'ogs_hgs','t'=>'OGS / HGS'],
            ['v'=>'servis','t'=>'Servis'],
            ['v'=>'lastik','t'=>'Lastik'],
            ['v'=>'muayene','t'=>'Muayene'],
            ['v'=>'sigorta','t'=>'Sigorta'],
            ['v'=>'kasko','t'=>'Kasko'],
            ['v'=>'yag_bakimi','t'=>'Yağ Bakımı'],
            ['v'=>'ceza','t'=>'Ceza'],
            ['v'=>'otopark','t'=>'Otopark'],
            ['v'=>'diger','t'=>'Diğer'],
        ],
        'diger' => [
            ['v'=>'genel_gider','t'=>'Genel Gider'],
            ['v'=>'diger_odeme','t'=>'Diğer Ödeme'],
            ['v'=>'aciklamali_kayit','t'=>'Açıklamalı Kayıt'],
        ],
    ];
}

// Bu adımlarda "Gider Türü" seçimi sunucu tarafında ZORUNLU (JS'e ek, savunma katmanlı) —
// diğer adımlarda (cari/personel/kart/diger) opsiyonel kalır (o adımların asıl zorunlu alanı başka:
// cari->contact_id, personel->personnel_id, diger->description).
function finance_expense_type_required_steps(){
    return ['isletme','vergi','arac'];
}

// Bir payment_type değerinin (varsa) hangi sihirbaz adımına ait olduğunu bulur — adımlar arası
// çakışma yok ('diger' hariç, o yüzden hariç tutulur). finance_record_type_info() eski kayıtları
// da (varsa payment_type'a bakarak) doğru adımla açabilsin diye kullanılır.
function finance_expense_type_step_for_value($value){
    $value=(string)$value;
    if($value==='' || $value==='diger') return null;
    static $map=null;
    if($map===null){
        $map=[];
        foreach(finance_expense_type_options() as $step=>$opts){
            foreach($opts as $o){
                if($o['v']==='diger') continue;
                if(!isset($map[$o['v']])) $map[$o['v']]=$step;
            }
        }
    }
    return isset($map[$value]) ? $map[$value] : null;
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
