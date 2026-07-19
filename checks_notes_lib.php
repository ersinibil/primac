<?php
/* OTS Çek / Senet Takibi — paylaşılan fonksiyonlar (web + mobil).
 * checks_notes.php / check_note_view.php / mobile/checks_notes.php / mobile/check_note_view.php ortak kullanır.
 * Tablo: database/migrations/024_checks_notes.sql
 * Dosya eki + otomatik görev: database/migrations/026_checks_notes_attachment.sql,
 * database/migrations/027_checks_notes_task_link.sql
 * Yaşam döngüsü (tahsil/ciro/öde/karşılıksız/iptal): database/migrations/048_checks_notes_lifecycle.sql */
require_once __DIR__.'/finance_lib.php'; // finance_movement_apply_balance()/finance_movement_reverse_balance() için

function checks_notes_types(){
    return ['cek'=>'Çek','senet'=>'Senet'];
}

// Yön: bu çeki/senedi BİZ mi verdik (kendi ödeme çekimiz) yoksa BİZE mi verildi (tahsilat çeki).
// Migration: 033_checks_notes_direction.sql. Aynı status makinesini kullanır, sadece
// checks_notes_statuses() etiketleri yöne göre değişir (ör. "Portföyde" verilen çek için anlamsızdı).
function checks_notes_directions(){
    return ['alinan'=>'Alınan (Tahsilat)','verilen'=>'Verilen (Ödeme)'];
}

function checks_notes_statuses($direction='alinan'){
    if($direction==='verilen'){
        return [
            'portfoyde'=>'Verildi (Bekliyor)',
            'tahsil_edildi'=>'Ödendi',
            'ciro_edildi'=>'Ciro Edildi',
            'karsiliksiz'=>'Karşılıksız Döndü',
            'iptal'=>'İptal',
        ];
    }
    return [
        'portfoyde'=>'Portföyde',
        'tahsil_edildi'=>'Tahsil Edildi',
        'ciro_edildi'=>'Ciro Edildi',
        'karsiliksiz'=>'Karşılıksız',
        'iptal'=>'İptal',
    ];
}

// Liste + filtre (tür/durum/yön). En yakın vadeli en üstte.
function checks_notes_list($pdo, $type=null, $status=null, $direction=null){
    $sql="SELECT cn.*, c.name contact_name FROM checks_notes cn LEFT JOIN contacts c ON c.id=cn.contact_id WHERE 1=1";
    $params=[];
    if($type){ $sql.=" AND cn.type=?"; $params[]=$type; }
    if($status){ $sql.=" AND cn.status=?"; $params[]=$status; }
    if($direction){ $sql.=" AND cn.direction=?"; $params[]=$direction; }
    $sql.=" ORDER BY (cn.status='portfoyde') DESC, cn.due_date IS NULL, cn.due_date ASC, cn.id DESC";
    $s=$pdo->prepare($sql);
    $s->execute($params);
    return $s->fetchAll();
}

function checks_notes_get($pdo, $id){
    $s=$pdo->prepare("SELECT cn.*, c.name contact_name FROM checks_notes cn LEFT JOIN contacts c ON c.id=cn.contact_id WHERE cn.id=?");
    $s->execute([(int)$id]);
    $r=$s->fetch();
    return $r ?: null;
}

// Görev detay ekranındaki "Çek / Senet Bilgileri" kartı için: bir tasks.id'den, o görevi
// otomatik oluşturan çek/senet kaydını bulur (checks_notes.task_id → tasks.id, TEK güvenilir
// bağ — bkz. migration 027). Görev çek/senet kaynaklı değilse null döner.
function checks_notes_get_by_task($pdo, $taskId){
    $s=$pdo->prepare("SELECT cn.*, c.name contact_name FROM checks_notes cn LEFT JOIN contacts c ON c.id=cn.contact_id WHERE cn.task_id=?");
    $s->execute([(int)$taskId]);
    $r=$s->fetch();
    return $r ?: null;
}

// Portföy durumuna göre badge rengi — checks_notes.php (liste) ve task_view.php (Çek/Senet
// Bilgileri kartı) ortak kullanır.
function checks_notes_status_tone($status){
    return ['portfoyde'=>'blue','tahsil_edildi'=>'green','ciro_edildi'=>'purple','karsiliksiz'=>'red','iptal'=>'gray'][$status] ?? 'gray';
}

// $_FILES['attachment'] varsa yükler, uploads/check_files altına taşır ve kök-göreli yolu döner.
// Dosya seçilmediyse null döner (mevcut ek korunur). Gerçek bir yükleme hatası olursa Exception fırlatır.
function checks_notes_handle_upload(){
    if(empty($_FILES['attachment']) || $_FILES['attachment']['error']===UPLOAD_ERR_NO_FILE){
        return null;
    }
    $f=$_FILES['attachment'];
    if($f['error'] !== UPLOAD_ERR_OK){
        $errors=[
            UPLOAD_ERR_INI_SIZE=>"Dosya sunucunun izin verdiği boyuttan büyük.",
            UPLOAD_ERR_FORM_SIZE=>"Dosya form limitinden büyük.",
            UPLOAD_ERR_PARTIAL=>"Dosya eksik yüklendi.",
            UPLOAD_ERR_NO_TMP_DIR=>"Sunucuda geçici klasör yok.",
            UPLOAD_ERR_CANT_WRITE=>"Dosya sunucuya yazılamadı.",
            UPLOAD_ERR_EXTENSION=>"PHP eklentisi yüklemeyi durdurdu."
        ];
        throw new Exception($errors[$f['error']] ?? "Dosya yükleme hatası. Kod: ".$f['error']);
    }

    $uploadDir=__DIR__.'/uploads/check_files';
    if(!is_dir($uploadDir)){
        if(!mkdir($uploadDir,0755,true)){
            throw new Exception("uploads/check_files klasörü oluşturulamadı.");
        }
    }
    if(!is_writable($uploadDir)){
        throw new Exception("uploads/check_files klasörü yazılabilir değil. cPanel izinlerini kontrol et.");
    }

    $original=$f['name'];
    $tmp=$f['tmp_name'];
    $size=(int)$f['size'];
    $ext=strtolower(pathinfo($original, PATHINFO_EXTENSION));

    $allowed=['jpg','jpeg','png','webp','gif','pdf'];
    if(!in_array($ext,$allowed,true)){
        throw new Exception("Bu dosya türüne izin verilmiyor: ".$ext.". İzin verilenler: ".implode(', ',$allowed));
    }
    if($size > 15*1024*1024){
        throw new Exception("Dosya 15 MB üzerinde olamaz.");
    }

    $stored='cn_'.date('YmdHis').'_'.bin2hex(random_bytes(4)).'.'.$ext;
    $target=$uploadDir.'/'.$stored;
    if(!move_uploaded_file($tmp,$target)){
        throw new Exception("Dosya yüklenemedi. Sunucu yazma izni veya dosya limiti olabilir.");
    }
    return 'uploads/check_files/'.$stored;
}

function checks_notes_contact_name($pdo, $contactId){
    if(!$contactId) return null;
    try{
        $s=$pdo->prepare("SELECT name FROM contacts WHERE id=?");
        $s->execute([(int)$contactId]);
        $r=$s->fetch();
        return $r ? $r['name'] : null;
    }catch(Throwable $e){ return null; }
}

function checks_notes_task_title($type, $number, $amount){
    $label = $type==='senet' ? 'Senet Vadesi' : 'Çek Vadesi';
    $num = trim((string)$number) !== '' ? $number : '(numarasız)';
    return $label.': '.$num.' — '.number_format((float)$amount,2,',','.').' ₺';
}

// NOT (güvenlik denetimi 2026-07-02): bu açıklama tasks.php üzerinden 'finance' yetkisi olmayan
// ama 'tasks' yetkisi olan personele de görünür (mobil mytasks.php personnel_id filtreliyor ama
// web tasks.php filtrelemiyor). Bu yüzden cari/banka/tutar gibi hassas alanlar BURAYA yazılmıyor —
// sadece Finans modülüne yönlendiren nötr bir metin var. Detay checks_notes.php/check_note_view.php'de.
function checks_notes_task_description($pdo, $type, $contactId, $bankName, $amount, $status){
    $label = $type==='senet' ? 'senedin' : 'çekin';
    return "Vadesi yaklaşan bir ".$label." hatırlatması. Cari/banka/tutar detayı için Finans → Çek/Senet ekranına bakın.";
}

// Yeni çek/senet kaydı için otomatik hatırlatma görevi oluşturur (muhasebe/yönetim iş ekranı — tasks tablosu).
// job_id=NULL (belirli bir işe bağlı değil), personnel_id=NULL (genel/atanmamış görev — tasks.php ve
// mobile/mytasks.php admin görünümünde görünür). checks_notes.task_id'ye geri yazılır (durum senkronu için).
function checks_notes_auto_create_task($pdo, $cnId, array $cn){
    if(empty($cn['due_date'])) return null; // vadesiz kayıt için hatırlatma görevi oluşturma

    $title = checks_notes_task_title($cn['type'], $cn['number'] ?? '', $cn['amount']);
    $desc  = checks_notes_task_description($pdo, $cn['type'], $cn['contact_id'] ?? null, $cn['bank_name'] ?? '', $cn['amount'], $cn['status']);

    $daysUntil = (strtotime($cn['due_date']) - strtotime(date('Y-m-d'))) / 86400;
    $priority = $daysUntil <= 7 ? 'Yüksek' : 'Normal';

    $stmt=$pdo->prepare("INSERT INTO tasks(job_id,personnel_id,title,description,due_date,status,priority) VALUES(NULL,NULL,?,?,?,?,?)");
    $stmt->execute([$title,$desc,$cn['due_date'],'Açık',$priority]);
    $taskId=(int)$pdo->lastInsertId();

    $pdo->prepare("UPDATE checks_notes SET task_id=? WHERE id=?")->execute([$taskId,(int)$cnId]);
    return $taskId;
}

// Durum "Tahsil Edildi / Ciro Edildi / İptal" olduğunda ilişkili hatırlatma görevini tamamlanmış işaretler.
function checks_notes_sync_task_status($pdo, $taskId, $status){
    if(!$taskId) return;
    $terminal=['tahsil_edildi','ciro_edildi','iptal'];
    if(!in_array($status,$terminal,true)) return;
    try{
        $pdo->prepare("UPDATE tasks SET status='Tamamlandı', completed_at=IF(completed_at IS NULL,NOW(),completed_at) WHERE id=? AND status<>'Tamamlandı'")
            ->execute([(int)$taskId]);
    }catch(Throwable $e){ /* görev senkronu ikincil — çek/senet güncellemesini bozmasın */ }
}

// Çek/senedin cari bakiyeye yansıyan gerçek finans hareketini oluşturur/günceller.
// Alınan = Tahsilat (direction='in'), Verilen = Ödeme (direction='out') — finance_new.php'deki
// Tahsilat/Ödeme ekranıyla BİREBİR aynı mantık (account_id=NULL: çek tahsil/ödenene kadar fiziken
// bir banka/kasa hesabında değildir, Veresiye satın almadaki mevcut davranışla tutarlı — sadece
// cari bakiyeyi etkiler, hesap bakiyesini değil). Migration: 034_checks_notes_finance_link.sql.
function checks_notes_sync_finance($pdo, $cnId, $type, $direction, $amount, $contactId, $bankName, $number, $existingFmId=null){
    if(!$contactId){
        // Cari seçilmemişse bağlı bir finans hareketi olamaz — varsa eskisini kaldır.
        if($existingFmId) checks_notes_reverse_finance($pdo, $existingFmId);
        return null;
    }
    $fmDirection = $direction==='verilen' ? 'out' : 'in';
    $channel = $type==='senet' ? 'Senet' : 'Çek';
    $status = $fmDirection==='in' ? 'Tahsil Edildi' : 'Ödendi';
    $desc = ($channel).($number?' No: '.$number:'').($bankName?' · '.$bankName:'');

    if($existingFmId){
        $pdo->prepare("UPDATE finance_movements SET contact_id=?,direction=?,amount=?,payment_channel=?,status=?,description=? WHERE id=?")
            ->execute([$contactId,$fmDirection,$amount,$channel,$status,$desc,$existingFmId]);
        return $existingFmId;
    }

    $pdo->prepare("INSERT INTO finance_movements(contact_id,direction,amount,payment_channel,status,movement_date,description,movement_type)
        VALUES(?,?,?,?,?,?,?,'cek_senet')")
        ->execute([$contactId,$fmDirection,$amount,$channel,$status,date('Y-m-d'),$desc]);
    return (int)$pdo->lastInsertId();
}

function checks_notes_reverse_finance($pdo, $financeMovementId){
    if(!$financeMovementId) return;
    try{ $pdo->prepare("DELETE FROM finance_movements WHERE id=? AND movement_type='cek_senet'")->execute([(int)$financeMovementId]); }
    catch(Throwable $e){ /* finans senkronu ikincil — çek/senet işlemini bozmasın */ }
}

function checks_notes_create($pdo, array $data, $userId=null){
    $type = ($data['type'] ?? 'cek')==='senet' ? 'senet' : 'cek';
    $direction = ($data['direction'] ?? 'alinan')==='verilen' ? 'verilen' : 'alinan';
    $amount = (float)str_replace(',', '.', (string)($data['amount'] ?? 0));
    if($amount<=0) throw new Exception('Tutar sıfırdan büyük olmalı.');
    // P0 ÇEK/SENET YAŞAM DÖNGÜSÜ (2026-07-18, Product Owner kararı): "Başlangıç durumu: PORTFÖYDE"
    // / "VERİLDİ-BEKLİYOR" — yeni kayıt HER ZAMAN portfoyde ile başlar, serbestçe seçilebilir
    // olmaktan çıkarıldı (önceden burada gelen $data['status'] doğrudan kabul ediliyordu — bu, bir
    // kaydın hiçbir gerçek kasa/banka hareketi olmadan doğrudan "Tahsil Edildi" olarak
    // oluşturulabilmesine izin veriyordu). Tahsil/ciro/ödeme SADECE checks_notes_collect()/pay()/
    // endorse() üzerinden, gerçek finans hareketiyle BİRLİKTE olur.
    $status = 'portfoyde';
    $dueDate = trim($data['due_date'] ?? '') ?: null;
    $contactId = (int)($data['contact_id'] ?? 0) ?: null;
    $number = trim($data['number'] ?? '');
    $bankName = trim($data['bank_name'] ?? '');

    $attachment = checks_notes_handle_upload();

    $stmt=$pdo->prepare("INSERT INTO checks_notes(type,direction,number,amount,due_date,contact_id,bank_name,status,notes,attachment,created_by)
        VALUES(?,?,?,?,?,?,?,?,?,?,?)");
    $stmt->execute([
        $type,
        $direction,
        $number,
        $amount,
        $dueDate,
        $contactId,
        $bankName,
        $status,
        trim($data['notes'] ?? ''),
        $attachment,
        $userId ?: null,
    ]);
    $newId = (int)$pdo->lastInsertId();

    // Cari seçiliyse gerçek bir finans hareketi oluştur (Alınan=Tahsilat/Verilen=Ödeme) — bu kayıt
    // artık cari bakiyeyi gerçekten etkiler, sadece bir takip kartı olmaktan çıktı (2026-07-03).
    try{
        $fmId = checks_notes_sync_finance($pdo, $newId, $type, $direction, $amount, $contactId, $bankName, $number);
        if($fmId) $pdo->prepare("UPDATE checks_notes SET finance_movement_id=? WHERE id=?")->execute([$fmId,$newId]);
    }catch(Throwable $e){ /* finans senkronu ikincil — çek/senet kaydı yine de oluşmuş olmalı */ }

    // Otomatik görev: vade tarihi girilmişse muhasebe/yönetim iş ekranına (tasks) hatırlatma düşer.
    // Görev otomasyonu ikincildir — başarısız olsa da çek/senet kaydı yine de oluşmuş olmalı.
    try{
        checks_notes_auto_create_task($pdo, $newId, [
            'type'=>$type,'number'=>$number,'amount'=>$amount,'due_date'=>$dueDate,
            'contact_id'=>$contactId,'bank_name'=>$bankName,'status'=>$status,
        ]);
    }catch(Throwable $e){ /* yut — ana kayıt zaten oluştu */ }

    return $newId;
}

// P0 ÇEK/SENET YAŞAM DÖNGÜSÜ (2026-07-18, Product Owner kararı): bu genel düzenleme fonksiyonu
// ARTIK durum (status) alanını KABUL ETMEZ — önceden serbest bir "Durum" dropdown'ıyla, hiçbir
// kasa/banka/cari hareketi oluşturmadan doğrudan 'tahsil_edildi'/'ciro_edildi' yazılabiliyordu
// (kritik veri bütünlüğü açığı: rozet "Tahsil Edildi" derdi ama hiçbir yerde gerçek para hareketi
// olmazdı). Durum SADECE checks_notes_collect()/pay()/endorse()/bounce()/cancel() üzerinden,
// doğru finans hareketiyle BİRLİKTE değişir. Ayrıca sadece 'portfoyde' (henüz hiçbir finansal
// aksiyon almamış) kayıtlar düzenlenebilir — "Final durumdaki kayıt üzerinde tekrar finansal işlem
// yapılamasın" kuralı, temel alan düzenlemesini de kapsar (tutar/tarih/cari sonradan değişirse
// zaten tamamlanmış bir tahsilat/ciro ile tutarsız kalırdı).
function checks_notes_update($pdo, $id, array $data){
    $id=(int)$id;
    $row=checks_notes_get($pdo,$id);
    if(!$row) throw new Exception('Kayıt bulunamadı.');
    if($row['status']!=='portfoyde') throw new Exception('Bu kayıt sonuçlanmış (tahsil/ödeme/ciro/karşılıksız/iptal) — artık düzenlenemez.');

    $type = ($data['type'] ?? $row['type'])==='senet' ? 'senet' : 'cek';
    $direction = ($data['direction'] ?? ($row['direction'] ?? 'alinan'))==='verilen' ? 'verilen' : 'alinan';
    $amount = (float)str_replace(',', '.', (string)($data['amount'] ?? $row['amount']));
    if($amount<=0) throw new Exception('Tutar sıfırdan büyük olmalı.');
    $status = 'portfoyde'; // durum bu fonksiyondan asla değişmez — bkz. üstteki not
    $dueDate = array_key_exists('due_date',$data) ? (trim($data['due_date'] ?? '') ?: null) : $row['due_date'];
    $contactId = array_key_exists('contact_id',$data) ? ((int)$data['contact_id'] ?: null) : $row['contact_id'];

    $newAttachment = checks_notes_handle_upload();
    $attachment = $newAttachment !== null ? $newAttachment : $row['attachment'];

    $pdo->prepare("UPDATE checks_notes SET type=?,direction=?,number=?,amount=?,due_date=?,contact_id=?,bank_name=?,status=?,notes=?,attachment=? WHERE id=?")
        ->execute([
            $type,
            $direction,
            trim($data['number'] ?? $row['number']),
            $amount,
            $dueDate,
            $contactId,
            trim($data['bank_name'] ?? $row['bank_name']),
            $status,
            trim(array_key_exists('notes',$data) ? $data['notes'] : $row['notes']),
            $attachment,
            $id,
        ]);

    // Bağlı finans hareketini (cari bakiye) yeni değerlerle senkronize et.
    try{
        $fmId = checks_notes_sync_finance($pdo, $id, $type, $direction, $amount, $contactId, trim($data['bank_name'] ?? $row['bank_name']), trim($data['number'] ?? $row['number']), $row['finance_movement_id'] ?? null);
        if($fmId && $fmId != ($row['finance_movement_id'] ?? null)) $pdo->prepare("UPDATE checks_notes SET finance_movement_id=? WHERE id=?")->execute([$fmId,$id]);
    }catch(Throwable $e){ /* finans senkronu ikincil — çek/senet güncellemesini bozmasın */ }

    return true;
}

// Dönüş: ['ok'=>bool,'msg'=>string]
// P0 ÇEK/SENET YAŞAM DÖNGÜSÜ (2026-07-18, Product Owner kararı): "Tahsil edilmiş, ödenmiş veya
// ciro edilmiş bir çek silinmemeli" — silme artık checks_notes_can_delete() kapısından geçmeden
// hiçbir zaman canlı bir kasa/banka veya tedarikçi-kapama hareketini orphan BIRAKMAZ.
function checks_notes_delete($pdo, $id){
    $id=(int)$id;
    if($id<1) return ['ok'=>false,'msg'=>'Geçersiz kayıt.'];
    $row=checks_notes_get($pdo,$id);
    if(!$row) return ['ok'=>false,'msg'=>'Kayıt bulunamadı.'];
    if(!checks_notes_can_delete($row)){
        return ['ok'=>false,'msg'=>'Tahsil edilmiş, ödenmiş veya ciro edilmiş bir çek/senet silinemez — önce "İptal" akışıyla ilgili hareketi geri alın.'];
    }
    if(!empty($row['finance_movement_id'])) checks_notes_reverse_finance($pdo, $row['finance_movement_id']);
    $pdo->prepare("DELETE FROM checks_notes WHERE id=?")->execute([$id]);
    return ['ok'=>true,'msg'=>'Kayıt silindi (bağlı finans hareketi de kaldırıldı).'];
}

// Vadesi geçmiş ama hâlâ portföyde olan çek/senet sayısı — dashboard/uyarı amaçlı kullanılabilir.
function checks_notes_overdue_count($pdo){
    try{
        $s=$pdo->query("SELECT COUNT(*) c FROM checks_notes WHERE status='portfoyde' AND due_date IS NOT NULL AND due_date<CURDATE()");
        return (int)$s->fetch()['c'];
    }catch(Throwable $e){ return 0; }
}

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * ÇEK / SENET — GERÇEK FİNANSAL YAŞAM DÖNGÜSÜ (2026-07-18, Product Owner kararı)
 * ═══════════════════════════════════════════════════════════════════════════════════════════
 * TEMEL MUHASEBE KURALI: checks_notes_create() (yukarıda) bir çek/senet kabul edildiğinde/
 * verildiğinde, cari_id doluysa ZATEN gerçek bir finance_movements kaydı (finance_movement_id,
 * account_id=NULL) oluşturup carinin borcunu/alacağını kapatıyor. Bu bölümdeki fonksiyonlar
 * (tahsil/ciro/öde/karşılıksız/iptal) o cariyi İKİNCİ KEZ ETKİLEMEZ — sadece:
 *   - Tahsil Et / Öde: SEÇİLEN kasa/banka hesabına (settle_account_id) gerçek nakit hareketi
 *     (contact_id=NULL, account_id DOLU) — finance_movement_apply_balance() ile.
 *   - Ciro Et: ciro edilen tedarikçinin borcunu kapatan bir hareket (contact_id=ciro_contact_id,
 *     account_id=NULL) — "Mevcut cari ödeme mantığını kullan" (aynı desen: checks_notes_create()'in
 *     orijinal kabul hareketiyle BİREBİR aynı yapı, kasa/banka hareketi OLUŞTURMAZ).
 *   - Karşılıksız / İptal: orijinal kabul hareketini (finance_movement_id) checks_notes_reverse_
 *     finance() ile geri alır — carinin borcu/alacağı yeniden açılır, çift kayıt ÜRETİLMEZ (aynı
 *     tek fonksiyon hem silmede hem burada kullanılıyor).
 *
 * ÇÖZÜLDÜ (2026-07-19 doğrulaması — bu not daha önce burada "PO kararı bekliyor" diyordu, GÜNCEL
 * DEĞİLDİ): yukarıdaki paragrafın bahsettiği işaret sorunu contacts_lib.php::contact_balance_case_sql()
 * içinde AYNI GÜN (2026-07-18, "P0 KAPANIŞ DÜZELTMESİ") içinde çözülmüştü — 'cek_senet' ve
 * 'cek_senet_ciro' artık 'normal'/'mobile' ile AYNI dalda (direction='in' → bakiyeyi AZALTIR,
 * direction='out' → bakiyeyi ARTIRIR), bu dosyanın eski yorumu sadece o düzeltmeden ÖNCE
 * yazılmış ve düzeltmeden sonra silinmemiş kalıntıydı. Formül canlı SUM() ile hesaplandığı için
 * (hiçbir satır kalıcı olarak "borç/alacak" işaretiyle saklanmıyor) düzeltme geçmiş kayıtları da
 * otomatik kapsıyor, ayrıca bir veri migrasyonu gerekmiyor. Kanıt — dört senaryo elle izlendi:
 *   1) Müşteriden çek ALINDI (direction='in', movement_type='cek_senet'): case → -amount, bakiye
 *      azalır (Tahsilat gibi). 5.000 borçlu → 1.000'lık çek → 4.000. DOĞRU.
 *   2) Tahsil Et/Öde (checks_notes_collect()/pay()): finance_movements satırı contact_id=NULL ile
 *      oluşuyor (satır 437/473) — cari sorgusu (`WHERE contact_id=?`) bu satırı hiç görmez, cari
 *      ikinci kez ETKİLENMEZ.
 *   3) Ciro Et (checks_notes_endorse()): kaynak carinin contact_id'sine yeni bir satır YAZILMAZ
 *      (sadece hedef tedarikçiye contact_id=$ciroContactId ile yazılır) — kaynak ikinci kez
 *      ETKİLENMEZ; hedef, movement_type='cek_senet_ciro' + direction='out' → case → +amount
 *      (Ödeme gibi, borcu azaltır). DOĞRU.
 *   4) Kendi verdiğimiz çek (direction='verilen'): kabul anında movement_type='cek_senet' +
 *      direction='out' → +amount (Ödeme gibi, borcu azaltır — taahhüt). checks_notes_pay() yine
 *      contact_id=NULL ile ayrı bir kasa/banka hareketi yazar, cariyi tekrar ETKİLEMEZ. DOĞRU.
 * contact_view.php ve mobile/contact_view.php ikisi de contact_balance() (bu formülü saran
 * fonksiyon) üzerinden okuyor — web/mobil parite doğrulandı, ayrı bir formül kopyası yok.
 */

// MİGRASYON TEK ŞEMA OTORİTESİ (2026-07-18) — cpa_allocation_lib.php::cpa_alloc_tables_ready() ile
// AYNI desen. 048_checks_notes_lifecycle.sql, checks_notes'a settle_*/ciro_* kolonlarını ekliyor;
// migrate.php çalıştırılmadan collect/pay/endorse/bounce/cancel çağrılırsa ham PDO "Unknown column"
// hatası fırlatmak yerine burada açık, anlaşılır bir hata verilir. Runtime'da ALTER TABLE ile
// paralel/sessiz bir şema İCAT EDİLMEZ — 048 tek otorite olarak kalır.
function checks_notes_lifecycle_ready(){
    static $ready = null;
    if($ready === null){
        try{
            $c = db()->query("SHOW COLUMNS FROM checks_notes LIKE 'settle_account_id'")->fetch();
            $ready = (bool)$c;
        }catch(Throwable $e){ $ready = false; }
    }
    return $ready;
}
function checks_notes_require_lifecycle(){
    if(!checks_notes_lifecycle_ready()){
        throw new Exception('Çek/Senet yaşam döngüsü özelliği için migration henüz çalıştırılmamış (048) — migrate.php çalıştırılmalı.');
    }
}

// Durum makinesi: hangi aksiyon butonları gösterilsin.
// FİNANS — ÇEK/SENET DÜZENLE + KONTROLLÜ SİL/GERİ AL (2026-07-19, Product Owner kararı): önceden
// tahsil_edildi/ciro_edildi durumlarında HİÇBİR aksiyon yoktu (kullanıcı test/veri hatasını asla
// düzeltemiyordu, sadece "Detay" görüyordu). Artık bu iki durumda TEK aksiyon var: "İşlemi Geri
// Al" (checks_notes_reopen()) — kaydı 'portfoyde'ye döndürür, o AN Düzenle/Tahsil/Ciro/Sil yeniden
// görünür olur (zincir: Geri Al → Düzenle/Sil, alanlar asla final işlem üzerinden sessizce
// değiştirilmez). karsiliksiz/iptal BİLİNÇLİ OLARAK bu kapsamın DIŞINDA bırakıldı (Product Owner'ın
// bu turda istediği 5 durum: Portföyde/Bekliyor/Ciro Edildi/Tahsil Edildi/Ödendi) — o iki durum
// zaten checks_notes_can_delete() üzerinden silinebiliyor, yeni epic açılmadı.
function checks_notes_available_actions($row){
    $status = $row['status'] ?? '';
    if($status === 'portfoyde'){
        return $row['direction']==='verilen' ? ['ode','duzenle','iptal'] : ['tahsil','ciro','karsiliksiz','duzenle','iptal'];
    }
    if(in_array($status, ['tahsil_edildi','ciro_edildi'], true)) return ['reopen'];
    return [];
}

// Silme SADECE finansal olarak "dokunulmamış" kayıtlarda mümkün — tahsil edilmiş/ödenmiş/ciro
// edilmiş bir çek silinirse canlı bir kasa/banka veya tedarikçi-kapama hareketi ORPHAN kalırdı
// (Product Owner kararı: "gerekiyorsa kontrollü bir İşlemi Geri Al/İptal akışı" — silme değil).
function checks_notes_can_delete($row){
    return !in_array($row['status'] ?? '', ['tahsil_edildi','ciro_edildi'], true);
}

/**
 * TAHSİL ET — alınan bir çeki/senedi fiilen kasaya/bankaya geçirir. Cariye DOKUNMAZ (zaten kapalı).
 * @throws Exception geçersiz durum/yön, kayıt yok veya geçersiz hesap
 */
function checks_notes_collect($pdo, $userId, $id, $accountId, $date, $desc=''){
    checks_notes_require_lifecycle();
    $id=(int)$id; $accountId=(int)$accountId;
    $row=checks_notes_get($pdo,$id);
    if(!$row) throw new Exception('Kayıt bulunamadı.');
    if($row['direction']!=='alinan') throw new Exception('Bu işlem sadece alınan çek/senetler için geçerli.');
    if($row['status']!=='portfoyde') throw new Exception('Bu kayıt portföyde değil, tekrar tahsil edilemez.');
    $acc=$pdo->prepare("SELECT * FROM finance_accounts WHERE id=? AND active=1"); $acc->execute([$accountId]); $account=$acc->fetch();
    if(!$account) throw new Exception('Geçerli bir kasa/banka hesabı seçin.');
    $date = trim((string)$date) ?: date('Y-m-d');

    $ownsTx=!$pdo->inTransaction();
    if($ownsTx) $pdo->beginTransaction();
    try{
        $label = ($row['type']==='senet'?'Senet':'Çek').' Tahsilatı — '.($row['type']==='senet'?'Senet':'Çek').' No: '.($row['number']?:'—').($row['contact_name']?' — '.$row['contact_name']:'');
        $pdo->prepare("INSERT INTO finance_movements(contact_id,direction,amount,account_id,payment_channel,status,movement_date,description,movement_type)
            VALUES(NULL,'in',?,?,?,'Tahsil Edildi',?,?,'cek_senet_tahsil')")
            ->execute([$row['amount'],$accountId,($row['type']==='senet'?'Senet':'Çek'),$date,$label]);
        $fmId=(int)$pdo->lastInsertId();
        finance_movement_apply_balance($pdo,'in',$accountId,$row['amount']);

        $pdo->prepare("UPDATE checks_notes SET status='tahsil_edildi', settle_date=?, settle_account_id=?, settle_finance_movement_id=?, settle_notes=? WHERE id=?")
            ->execute([$date,$accountId,$fmId,trim((string)$desc),$id]);
        checks_notes_sync_task_status($pdo, $row['task_id'] ?? null, 'tahsil_edildi');
        if(function_exists('audit_log')) audit_log($userId,'update','checks_notes',$id,['status'=>$row['status']],['status'=>'tahsil_edildi','settle_account_id'=>$accountId,'settle_finance_movement_id'=>$fmId]);
        if($ownsTx) $pdo->commit();
    }catch(Throwable $e){
        if($ownsTx) $pdo->rollBack();
        throw $e;
    }
}

/**
 * ÖDE — kendi verdiğimiz bir çeki/senedi fiilen kasadan/bankadan öder. Cariye DOKUNMAZ (zaten kapalı).
 * @throws Exception geçersiz durum/yön, kayıt yok veya geçersiz hesap
 */
function checks_notes_pay($pdo, $userId, $id, $accountId, $date, $desc=''){
    checks_notes_require_lifecycle();
    $id=(int)$id; $accountId=(int)$accountId;
    $row=checks_notes_get($pdo,$id);
    if(!$row) throw new Exception('Kayıt bulunamadı.');
    if($row['direction']!=='verilen') throw new Exception('Bu işlem sadece verilen çek/senetler için geçerli.');
    if($row['status']!=='portfoyde') throw new Exception('Bu kayıt bekleyen durumda değil, tekrar ödenemez.');
    $acc=$pdo->prepare("SELECT * FROM finance_accounts WHERE id=? AND active=1"); $acc->execute([$accountId]); $account=$acc->fetch();
    if(!$account) throw new Exception('Geçerli bir kasa/banka hesabı seçin.');
    $date = trim((string)$date) ?: date('Y-m-d');

    $ownsTx=!$pdo->inTransaction();
    if($ownsTx) $pdo->beginTransaction();
    try{
        $label = ($row['type']==='senet'?'Senet':'Çek').' Ödemesi — '.($row['type']==='senet'?'Senet':'Çek').' No: '.($row['number']?:'—').($row['contact_name']?' — '.$row['contact_name']:'');
        $pdo->prepare("INSERT INTO finance_movements(contact_id,direction,amount,account_id,payment_channel,status,movement_date,description,movement_type)
            VALUES(NULL,'out',?,?,?,'Ödendi',?,?,'cek_senet_tahsil')")
            ->execute([$row['amount'],$accountId,($row['type']==='senet'?'Senet':'Çek'),$date,$label]);
        $fmId=(int)$pdo->lastInsertId();
        finance_movement_apply_balance($pdo,'out',$accountId,$row['amount']);

        $pdo->prepare("UPDATE checks_notes SET status='tahsil_edildi', settle_date=?, settle_account_id=?, settle_finance_movement_id=?, settle_notes=? WHERE id=?")
            ->execute([$date,$accountId,$fmId,trim((string)$desc),$id]);
        checks_notes_sync_task_status($pdo, $row['task_id'] ?? null, 'tahsil_edildi');
        if(function_exists('audit_log')) audit_log($userId,'update','checks_notes',$id,['status'=>$row['status']],['status'=>'tahsil_edildi(ödendi)','settle_account_id'=>$accountId,'settle_finance_movement_id'=>$fmId]);
        if($ownsTx) $pdo->commit();
    }catch(Throwable $e){
        if($ownsTx) $pdo->rollBack();
        throw $e;
    }
}

/**
 * CİRO ET — portföydeki bir müşteri çekini bir tedarikçiye ödeme olarak devreder. Banka/kasa
 * hareketi OLUŞMAZ; "Mevcut cari ödeme mantığını kullan" (checks_notes_create()'in orijinal kabul
 * hareketiyle BİREBİR aynı yapı: contact_id dolu, account_id NULL) — ciro edilen tedarikçinin
 * borcu kapanır. Hangi müşteriden alındığı (row.contact_id) DEĞİŞMEZ, ciro_contact_id AYRICA
 * saklanır — zincir (kimden alındı → kime ciro edildi) her zaman izlenebilir.
 * @throws Exception geçersiz durum/yön, kayıt yok veya geçersiz cari
 */
function checks_notes_endorse($pdo, $userId, $id, $ciroContactId, $date, $desc=''){
    checks_notes_require_lifecycle();
    $id=(int)$id; $ciroContactId=(int)$ciroContactId;
    $row=checks_notes_get($pdo,$id);
    if(!$row) throw new Exception('Kayıt bulunamadı.');
    if($row['direction']!=='alinan') throw new Exception('Bu işlem sadece alınan çek/senetler için geçerli.');
    if($row['status']!=='portfoyde') throw new Exception('Bu kayıt portföyde değil, ciro edilemez.');
    if(!$ciroContactId) throw new Exception('Ciro edilecek cari seçilmedi.');
    $c=$pdo->prepare("SELECT id,name FROM contacts WHERE id=?"); $c->execute([$ciroContactId]); $ciroContact=$c->fetch();
    if(!$ciroContact) throw new Exception('Geçerli bir cari seçin.');
    $date = trim((string)$date) ?: date('Y-m-d');

    $ownsTx=!$pdo->inTransaction();
    if($ownsTx) $pdo->beginTransaction();
    try{
        $label = ($row['type']==='senet'?'Senet':'Çek').' Cirosu — '.($row['type']==='senet'?'Senet':'Çek').' No: '.($row['number']?:'—').($row['contact_name']?' — '.$row['contact_name'].' → '.$ciroContact['name']:'');
        $pdo->prepare("INSERT INTO finance_movements(contact_id,direction,amount,account_id,payment_channel,status,movement_date,description,movement_type)
            VALUES(?,'out',?,NULL,?,'Ödendi',?,?,'cek_senet_ciro')")
            ->execute([$ciroContactId,$row['amount'],($row['type']==='senet'?'Senet':'Çek'),$date,$label]);
        $fmId=(int)$pdo->lastInsertId();

        $pdo->prepare("UPDATE checks_notes SET status='ciro_edildi', ciro_contact_id=?, ciro_finance_movement_id=?, settle_date=?, settle_notes=? WHERE id=?")
            ->execute([$ciroContactId,$fmId,$date,trim((string)$desc),$id]);
        checks_notes_sync_task_status($pdo, $row['task_id'] ?? null, 'ciro_edildi');
        if(function_exists('audit_log')) audit_log($userId,'update','checks_notes',$id,['status'=>$row['status']],['status'=>'ciro_edildi','ciro_contact_id'=>$ciroContactId,'ciro_finance_movement_id'=>$fmId]);
        if($ownsTx) $pdo->commit();
    }catch(Throwable $e){
        if($ownsTx) $pdo->rollBack();
        throw $e;
    }
}

/**
 * KARŞILIKSIZ — alınan bir çek tahsil edilemedi. Kabul anında kapanmış olan müşteri borcu YENİDEN
 * AÇILIR (orijinal kabul hareketi checks_notes_reverse_finance() ile geri alınır — silmedeki AYNI
 * fonksiyon, çift kayıt/çift mantık ÜRETİLMEDİ).
 * @throws Exception geçersiz durum/yön veya kayıt yok
 */
function checks_notes_bounce($pdo, $userId, $id, $reason=''){
    checks_notes_require_lifecycle();
    $id=(int)$id;
    $row=checks_notes_get($pdo,$id);
    if(!$row) throw new Exception('Kayıt bulunamadı.');
    if($row['direction']!=='alinan') throw new Exception('Bu işlem sadece alınan çek/senetler için geçerli.');
    if($row['status']!=='portfoyde') throw new Exception('Bu kayıt portföyde değil.');

    $ownsTx=!$pdo->inTransaction();
    if($ownsTx) $pdo->beginTransaction();
    try{
        if(!empty($row['finance_movement_id'])) checks_notes_reverse_finance($pdo, $row['finance_movement_id']);
        $pdo->prepare("UPDATE checks_notes SET status='karsiliksiz', finance_movement_id=NULL, settle_date=?, settle_notes=? WHERE id=?")
            ->execute([date('Y-m-d'),trim((string)$reason),$id]);
        checks_notes_sync_task_status($pdo, $row['task_id'] ?? null, 'iptal'); // task 'iptal'/'ciro_edildi'/'tahsil_edildi' terminal setinde — karşılıksız da görevi kapatır
        if(function_exists('audit_log')) audit_log($userId,'update','checks_notes',$id,['status'=>$row['status']],['status'=>'karsiliksiz','reopened_contact_id'=>$row['contact_id']]);
        if($ownsTx) $pdo->commit();
    }catch(Throwable $e){
        if($ownsTx) $pdo->rollBack();
        throw $e;
    }
}

/**
 * İPTAL — portföydeki (henüz hiçbir finansal aksiyon almamış) bir çek/senet kaydı iptal edilir.
 * Kabul anında oluşmuş cari kapama hareketi varsa (alınan/verilen fark etmez) geri alınır — cari
 * bu kaydın hiç olmamış gibi kalır.
 * @throws Exception geçersiz durum veya kayıt yok
 */
function checks_notes_cancel($pdo, $userId, $id, $reason=''){
    checks_notes_require_lifecycle();
    $id=(int)$id;
    $row=checks_notes_get($pdo,$id);
    if(!$row) throw new Exception('Kayıt bulunamadı.');
    if($row['status']!=='portfoyde') throw new Exception('Bu kayıt zaten sonuçlanmış, iptal edilemez.');

    $ownsTx=!$pdo->inTransaction();
    if($ownsTx) $pdo->beginTransaction();
    try{
        if(!empty($row['finance_movement_id'])) checks_notes_reverse_finance($pdo, $row['finance_movement_id']);
        $pdo->prepare("UPDATE checks_notes SET status='iptal', finance_movement_id=NULL, settle_date=?, settle_notes=? WHERE id=?")
            ->execute([date('Y-m-d'),trim((string)$reason),$id]);
        checks_notes_sync_task_status($pdo, $row['task_id'] ?? null, 'iptal');
        if(function_exists('audit_log')) audit_log($userId,'update','checks_notes',$id,['status'=>$row['status']],['status'=>'iptal']);
        if($ownsTx) $pdo->commit();
    }catch(Throwable $e){
        if($ownsTx) $pdo->rollBack();
        throw $e;
    }
}

/**
 * İŞLEMİ GERİ AL (2026-07-19, Product Owner kararı — "kullanıcı test/veri hatasını düzeltebilmeli
 * ama finansal iz bırakmadan sessiz DELETE yapılamaz") — 'tahsil_edildi' (Tahsil Edildi/Ödendi,
 * yön farketmez — ikisi de AYNI status değeri) veya 'ciro_edildi' durumundaki bir kaydı
 * 'portfoyde'ye döndürür, İLGİLİ hareketi tersler:
 *   - ciro_edildi: ciro_finance_movement_id satırı silinir (hedef tedarikçinin borcu yeniden
 *     açılır) — checks_notes_endorse() kasa/banka hareketi OLUŞTURMADIĞI için burada da bir hesap
 *     bakiyesi tersleme YOK.
 *   - tahsil_edildi: settle_finance_movement_id satırı (gerçek kasa/banka hareketi) hem finans
 *     hesabı bakiyesinden (finance_movement_reverse_balance()) hem de tablodan silinir.
 * İKİ DURUMDA DA orijinal kabul hareketi (finance_movement_id — çek alındığında/verildiğinde
 * carinin borcunu kapatan hareket) HİÇ DOKUNULMAZ: tahsil/ciro zaten cariyi ikinci kez
 * etkilemiyordu (bkz. checks_notes_collect()/pay()/endorse() docblock'ları), geri alma da aynı
 * ilkeyi korur — cari kapalı kalır, sadece tahsil/ciro'nun KENDİ hareketi geri alınır.
 * @throws Exception geçersiz durum veya kayıt yok
 */
function checks_notes_reopen($pdo, $userId, $id, $reason=''){
    checks_notes_require_lifecycle();
    $id=(int)$id;
    $row=checks_notes_get($pdo,$id);
    if(!$row) throw new Exception('Kayıt bulunamadı.');
    if(!in_array($row['status'] ?? '', ['tahsil_edildi','ciro_edildi'], true)){
        throw new Exception('Bu kayıt geri alınabilir bir durumda değil.');
    }

    $ownsTx=!$pdo->inTransaction();
    if($ownsTx) $pdo->beginTransaction();
    try{
        if($row['status']==='ciro_edildi'){
            if(!empty($row['ciro_finance_movement_id'])){
                $pdo->prepare("DELETE FROM finance_movements WHERE id=? AND movement_type='cek_senet_ciro'")->execute([$row['ciro_finance_movement_id']]);
            }
            $pdo->prepare("UPDATE checks_notes SET status='portfoyde', ciro_contact_id=NULL, ciro_finance_movement_id=NULL, settle_date=NULL, settle_notes=? WHERE id=?")
                ->execute([trim((string)$reason), $id]);
        } else { // tahsil_edildi (alınan: "Tahsil Edildi" / verilen: "Ödendi" — aynı status)
            if(!empty($row['settle_finance_movement_id'])){
                $fmq=$pdo->prepare("SELECT * FROM finance_movements WHERE id=? AND movement_type='cek_senet_tahsil'");
                $fmq->execute([$row['settle_finance_movement_id']]);
                $fmRow=$fmq->fetch();
                if($fmRow){
                    finance_movement_reverse_balance($pdo, $fmRow);
                    $pdo->prepare("DELETE FROM finance_movements WHERE id=?")->execute([$fmRow['id']]);
                }
            }
            $pdo->prepare("UPDATE checks_notes SET status='portfoyde', settle_date=NULL, settle_account_id=NULL, settle_finance_movement_id=NULL, settle_notes=? WHERE id=?")
                ->execute([trim((string)$reason), $id]);
        }
        if(function_exists('audit_log')) audit_log($userId,'update','checks_notes',$id,['status'=>$row['status']],['status'=>'portfoyde','reopen_reason'=>trim((string)$reason)]);
        if($ownsTx) $pdo->commit();
    }catch(Throwable $e){
        if($ownsTx) $pdo->rollBack();
        throw $e;
    }
}

// Bir çek/senedin yaşam döngüsü zaman çizelgesi — check_note_view.php/mobile eşdeğeri render eder.
// Yeni bir tablo İCAT EDİLMEDİ, checks_notes'un kendi kolonlarından + finance_movements
// açıklamalarından türetilir. @return array [['date'=>string,'label'=>string,'amount'=>string|null]]
function checks_notes_history($pdo, $row){
    $steps=[];
    $partyName = $row['contact_name'] ?? null;
    $kind = $row['type']==='senet' ? 'Senet' : 'Çek';
    $verb = $row['direction']==='verilen'
        ? ($partyName ? $kind.' '.$partyName.'\'a verildi' : $kind.' verildi')
        : ($partyName ? $kind.' '.$partyName.'\'dan alındı' : $kind.' alındı');
    $steps[] = ['date'=>$row['created_at'], 'label'=>$verb, 'amount'=>money($row['amount']), 'tone'=>'neutral'];

    if($row['status']==='tahsil_edildi'){
        $accName='';
        if(!empty($row['settle_account_id'])){
            try{ $a=$pdo->prepare("SELECT name FROM finance_accounts WHERE id=?"); $a->execute([$row['settle_account_id']]); $accName=$a->fetch()['name']??''; }catch(Throwable $e){}
        }
        if($row['direction']==='verilen'){
            $steps[] = ['date'=>$row['settle_date'], 'label'=>($accName?:'Hesaptan').' ödendi', 'amount'=>'-'.money($row['amount']), 'tone'=>'danger'];
        }else{
            $steps[] = ['date'=>$row['settle_date'], 'label'=>($accName?:'Hesaba').' tahsil edildi', 'amount'=>'+'.money($row['amount']), 'tone'=>'success'];
        }
    }elseif($row['status']==='ciro_edildi'){
        $ciroName='';
        if(!empty($row['ciro_contact_id'])){
            try{ $c=$pdo->prepare("SELECT name FROM contacts WHERE id=?"); $c->execute([$row['ciro_contact_id']]); $ciroName=$c->fetch()['name']??''; }catch(Throwable $e){}
        }
        $steps[] = ['date'=>$row['settle_date'], 'label'=>($ciroName?:'Tedarikçiye').' ciro edildi', 'amount'=>money($row['amount']), 'tone'=>'info'];
    }elseif($row['status']==='karsiliksiz'){
        $steps[] = ['date'=>$row['settle_date'] ?: date('Y-m-d'), 'label'=>'Karşılıksız döndü — cari borç yeniden açıldı', 'amount'=>null, 'tone'=>'danger'];
    }elseif($row['status']==='iptal'){
        $steps[] = ['date'=>$row['settle_date'] ?: date('Y-m-d'), 'label'=>'İptal edildi', 'amount'=>null, 'tone'=>'neutral'];
    }
    return $steps;
}
