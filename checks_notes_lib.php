<?php
/* OTS Çek / Senet Takibi — paylaşılan fonksiyonlar (web + mobil).
 * checks_notes.php / check_note_view.php / mobile/checks_notes.php / mobile/check_note_view.php ortak kullanır.
 * Tablo: database/migrations/024_checks_notes.sql */

function checks_notes_types(){
    return ['cek'=>'Çek','senet'=>'Senet'];
}

function checks_notes_statuses(){
    return [
        'portfoyde'=>'Portföyde',
        'tahsil_edildi'=>'Tahsil Edildi',
        'ciro_edildi'=>'Ciro Edildi',
        'karsiliksiz'=>'Karşılıksız',
        'iptal'=>'İptal',
    ];
}

// Liste + filtre (tür/durum). En yakın vadeli en üstte.
function checks_notes_list($pdo, $type=null, $status=null){
    $sql="SELECT cn.*, c.name contact_name FROM checks_notes cn LEFT JOIN contacts c ON c.id=cn.contact_id WHERE 1=1";
    $params=[];
    if($type){ $sql.=" AND cn.type=?"; $params[]=$type; }
    if($status){ $sql.=" AND cn.status=?"; $params[]=$status; }
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

function checks_notes_create($pdo, array $data, $userId=null){
    $type = ($data['type'] ?? 'cek')==='senet' ? 'senet' : 'cek';
    $amount = (float)str_replace(',', '.', (string)($data['amount'] ?? 0));
    if($amount<=0) throw new Exception('Tutar sıfırdan büyük olmalı.');
    $status = $data['status'] ?? 'portfoyde';
    if(!array_key_exists($status, checks_notes_statuses())) $status='portfoyde';
    $dueDate = trim($data['due_date'] ?? '') ?: null;
    $contactId = (int)($data['contact_id'] ?? 0) ?: null;

    $stmt=$pdo->prepare("INSERT INTO checks_notes(type,number,amount,due_date,contact_id,bank_name,status,notes,created_by)
        VALUES(?,?,?,?,?,?,?,?,?)");
    $stmt->execute([
        $type,
        trim($data['number'] ?? ''),
        $amount,
        $dueDate,
        $contactId,
        trim($data['bank_name'] ?? ''),
        $status,
        trim($data['notes'] ?? ''),
        $userId ?: null,
    ]);
    return (int)$pdo->lastInsertId();
}

function checks_notes_update($pdo, $id, array $data){
    $id=(int)$id;
    $row=checks_notes_get($pdo,$id);
    if(!$row) throw new Exception('Kayıt bulunamadı.');

    $type = ($data['type'] ?? $row['type'])==='senet' ? 'senet' : 'cek';
    $amount = (float)str_replace(',', '.', (string)($data['amount'] ?? $row['amount']));
    if($amount<=0) throw new Exception('Tutar sıfırdan büyük olmalı.');
    $status = $data['status'] ?? $row['status'];
    if(!array_key_exists($status, checks_notes_statuses())) $status=$row['status'];
    $dueDate = array_key_exists('due_date',$data) ? (trim($data['due_date'] ?? '') ?: null) : $row['due_date'];
    $contactId = array_key_exists('contact_id',$data) ? ((int)$data['contact_id'] ?: null) : $row['contact_id'];

    $pdo->prepare("UPDATE checks_notes SET type=?,number=?,amount=?,due_date=?,contact_id=?,bank_name=?,status=?,notes=? WHERE id=?")
        ->execute([
            $type,
            trim($data['number'] ?? $row['number']),
            $amount,
            $dueDate,
            $contactId,
            trim($data['bank_name'] ?? $row['bank_name']),
            $status,
            trim(array_key_exists('notes',$data) ? $data['notes'] : $row['notes']),
            $id,
        ]);
    return true;
}

// Dönüş: ['ok'=>bool,'msg'=>string]
function checks_notes_delete($pdo, $id){
    $id=(int)$id;
    if($id<1) return ['ok'=>false,'msg'=>'Geçersiz kayıt.'];
    $row=checks_notes_get($pdo,$id);
    if(!$row) return ['ok'=>false,'msg'=>'Kayıt bulunamadı.'];
    $pdo->prepare("DELETE FROM checks_notes WHERE id=?")->execute([$id]);
    return ['ok'=>true,'msg'=>'Kayıt silindi.'];
}

// Vadesi geçmiş ama hâlâ portföyde olan çek/senet sayısı — dashboard/uyarı amaçlı kullanılabilir.
function checks_notes_overdue_count($pdo){
    try{
        $s=$pdo->query("SELECT COUNT(*) c FROM checks_notes WHERE status='portfoyde' AND due_date IS NOT NULL AND due_date<CURDATE()");
        return (int)$s->fetch()['c'];
    }catch(Throwable $e){ return 0; }
}
