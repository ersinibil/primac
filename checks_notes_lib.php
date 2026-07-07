<?php
/* OTS Çek / Senet Takibi — paylaşılan fonksiyonlar (web + mobil).
 * checks_notes.php / check_note_view.php / mobile/checks_notes.php / mobile/check_note_view.php ortak kullanır.
 * Tablo: database/migrations/024_checks_notes.sql
 * Dosya eki + otomatik görev: database/migrations/026_checks_notes_attachment.sql,
 * database/migrations/027_checks_notes_task_link.sql */

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
    $status = $data['status'] ?? 'portfoyde';
    if(!array_key_exists($status, checks_notes_statuses($direction))) $status='portfoyde';
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

function checks_notes_update($pdo, $id, array $data){
    $id=(int)$id;
    $row=checks_notes_get($pdo,$id);
    if(!$row) throw new Exception('Kayıt bulunamadı.');

    $type = ($data['type'] ?? $row['type'])==='senet' ? 'senet' : 'cek';
    $direction = ($data['direction'] ?? ($row['direction'] ?? 'alinan'))==='verilen' ? 'verilen' : 'alinan';
    $amount = (float)str_replace(',', '.', (string)($data['amount'] ?? $row['amount']));
    if($amount<=0) throw new Exception('Tutar sıfırdan büyük olmalı.');
    $status = $data['status'] ?? $row['status'];
    if(!array_key_exists($status, checks_notes_statuses($direction))) $status=$row['status'];
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

    // Durum "Tahsil Edildi/Ciro Edildi/İptal" olduysa ilişkili hatırlatma görevini tamamlanmış işaretle.
    checks_notes_sync_task_status($pdo, $row['task_id'] ?? null, $status);

    // Bağlı finans hareketini (cari bakiye) yeni değerlerle senkronize et.
    try{
        $fmId = checks_notes_sync_finance($pdo, $id, $type, $direction, $amount, $contactId, trim($data['bank_name'] ?? $row['bank_name']), trim($data['number'] ?? $row['number']), $row['finance_movement_id'] ?? null);
        if($fmId && $fmId != ($row['finance_movement_id'] ?? null)) $pdo->prepare("UPDATE checks_notes SET finance_movement_id=? WHERE id=?")->execute([$fmId,$id]);
    }catch(Throwable $e){ /* finans senkronu ikincil — çek/senet güncellemesini bozmasın */ }

    return true;
}

// Dönüş: ['ok'=>bool,'msg'=>string]
function checks_notes_delete($pdo, $id){
    $id=(int)$id;
    if($id<1) return ['ok'=>false,'msg'=>'Geçersiz kayıt.'];
    $row=checks_notes_get($pdo,$id);
    if(!$row) return ['ok'=>false,'msg'=>'Kayıt bulunamadı.'];
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
