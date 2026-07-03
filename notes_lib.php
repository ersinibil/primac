<?php
// notes_lib.php — Kişisel görev/not alanı (web + mobil ortak).
// personal_notes tablosu (migration 037) her zaman user_id ile filtrelenir — hiçbir sorgu bu
// filtresiz çalışmaz, böylece bir kullanıcının notu başka hiçbir ekrandan (tasks.php dahil)
// görünmez. Oluşturmada kullanıcıya (kendine) hem sistem içi bildirim+push hem gerçek WhatsApp
// (wa_send, ayarlıysa) hem iç mesaj (internal_messages) gönderilir.

function personal_notes_has_table($pdo){
    static $ok=null;
    if($ok!==null) return $ok;
    try{ $pdo->query("SELECT 1 FROM personal_notes LIMIT 1"); $ok=true; }
    catch(Throwable $e){ $ok=false; }
    return $ok;
}

// Kullanıcının kendi telefon numarasını bul: önce bağlı personel kaydı, yoksa app_users.phone.
function my_phone($pdo, $userId){
    try{
        $s=$pdo->prepare("SELECT p.phone FROM app_users u LEFT JOIN personnel p ON p.id=u.personnel_id WHERE u.id=? AND p.phone IS NOT NULL AND p.phone<>''");
        $s->execute([$userId]);
        $r=$s->fetch();
        if($r && $r['phone']) return $r['phone'];
    }catch(Throwable $e){}
    try{
        $s=$pdo->prepare("SELECT phone FROM app_users WHERE id=?");
        $s->execute([$userId]);
        $r=$s->fetch();
        return $r['phone'] ?? '';
    }catch(Throwable $e){ return ''; }
}

function personal_note_create($pdo, $userId, $title, $note='', $dueDate=null){
    $title=trim($title);
    if($title==='') throw new Exception('Başlık girin.');
    if(!personal_notes_has_table($pdo)) throw new Exception('Not tablosu henüz kurulmadı (migration 037 çalıştırılmalı).');

    $pdo->prepare("INSERT INTO personal_notes(user_id,title,note,due_date,status) VALUES(?,?,?,?,'Açık')")
        ->execute([$userId,$title,trim($note),$dueDate?:null]);
    $id=(int)$pdo->lastInsertId();

    $msg = trim($note).($dueDate?"\n📅 ".$dueDate:'');

    // 1) Sistem içi bildirim + Web Push (mobile/common.php::notify_user — job/task ile aynı desen)
    if(function_exists('notify_user')){
        try{ notify_user($userId,'📝 Not: '.$title,$msg,'mytasks.php'); }catch(Throwable $e){}
    }

    // 2) İç mesaj (kendine) — Mesajlar ekranında da görünsün
    try{
        $pdo->prepare("INSERT INTO internal_messages(sender_user_id,receiver_user_id,message,is_read) VALUES(?,?,?,0)")
            ->execute([$userId,$userId,'📝 Not: '.$title.($msg?"\n".$msg:'')]);
    }catch(Throwable $e){}

    // 3) Gerçek WhatsApp (kendi numarasına) — wa_send() ayarlıysa (wa_settings.php) otomatik
    // gönderir, ayarlı değilse sessizce false döner (hata değil, sadece devre dışı).
    if(function_exists('wa_send')){
        try{
            $phone=my_phone($pdo,$userId);
            if($phone) wa_send($phone,'📝 Not: '.$title.($msg?"\n".$msg:''));
        }catch(Throwable $e){}
    }

    return $id;
}

function personal_notes_list($pdo, $userId, $status='open'){
    if(!personal_notes_has_table($pdo)) return [];
    $w = $status==='done' ? "status='Tamamlandı'" : "status NOT IN ('Tamamlandı')";
    $s=$pdo->prepare("SELECT * FROM personal_notes WHERE user_id=? AND $w ORDER BY (due_date IS NULL), due_date, id DESC LIMIT 100");
    $s->execute([$userId]);
    return $s->fetchAll();
}

// Bu ayki (YYYY-MM) notlar — takvim entegrasyonu için.
function personal_notes_for_month($pdo, $userId, $ym){
    if(!personal_notes_has_table($pdo)) return [];
    try{
        $s=$pdo->prepare("SELECT id,title,due_date,status FROM personal_notes WHERE user_id=? AND due_date IS NOT NULL AND DATE_FORMAT(due_date,'%Y-%m')=? AND status<>'Tamamlandı' ORDER BY due_date");
        $s->execute([$userId,$ym]);
        return $s->fetchAll();
    }catch(Throwable $e){ return []; }
}

function personal_note_set_status($pdo, $id, $userId, $status){
    if(!in_array($status,['Açık','Tamamlandı'],true)) return false;
    $s=$pdo->prepare("UPDATE personal_notes SET status=? WHERE id=? AND user_id=?");
    return $s->execute([$status,(int)$id,$userId]);
}

function personal_note_delete($pdo, $id, $userId){
    $s=$pdo->prepare("DELETE FROM personal_notes WHERE id=? AND user_id=?");
    return $s->execute([(int)$id,$userId]);
}
