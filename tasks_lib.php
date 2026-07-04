<?php
// tasks_lib.php — "İşlerim" (tasks tablosu) için web+mobil ORTAK iş mantığı.
// Kapsam (2026-07-04, kullanıcı isteği): task detay/düzenle/soft-delete/yorum/dosya.
// Migration: database/migrations/040_task_edit_detail_soft_delete.sql
//   (tasks.created_by/updated_by/deleted_at + task_comments + task_files).
//
// Soft delete kuralı: hiçbir SELECT bu dosyadan geçmeden `deleted_at IS NULL` filtresiz tasks'a
// bakmamalı. Var olan (bu dosyanın dışındaki) bazı sayaç sorguları (jobs.php, personnel_view.php)
// bilinçli olarak DOKUNULMADI (paralel çalışma / kapsam disiplini) — bkz. memory/backlog.md.

// Giriş yapan kullanıcının bağlı personel id'si (birçok dosyada kopyalanmış aynı bloğun ortak hali).
function task_my_personnel_id($pdo,$uid){
    $uid=(int)$uid;
    if(!$uid) return 0;
    try{
        $r=$pdo->prepare("SELECT personnel_id FROM app_users WHERE id=?");
        $r->execute([$uid]);
        return (int)($r->fetch()['personnel_id'] ?? 0);
    }catch(Throwable $e){ return 0; }
}

// Tek bir görevi (silinmemiş) ilişkili bilgilerle birlikte getirir.
function task_fetch($pdo,$id){
    $st=$pdo->prepare("SELECT t.*, j.id job_real, j.job_no, p.name pname, p.phone pphone,
        cu.full_name creator_name, cu.username creator_username,
        uu.full_name updater_name, uu.username updater_username
        FROM tasks t
        LEFT JOIN jobs j ON j.id=t.job_id
        LEFT JOIN personnel p ON p.id=t.personnel_id
        LEFT JOIN app_users cu ON cu.id=t.created_by
        LEFT JOIN app_users uu ON uu.id=t.updated_by
        WHERE t.id=? AND t.deleted_at IS NULL");
    $st->execute([(int)$id]);
    return $st->fetch();
}

// Görev sahipliği: oluşturan YA DA atanan personel kendisi mi?
function task_is_owner($task,$uid,$pid){
    $uid=(int)$uid; $pid=(int)$pid;
    if($uid && (int)($task['created_by'] ?? 0)===$uid) return true;
    if($pid && (int)($task['personnel_id'] ?? 0)===$pid) return true;
    return false;
}

// Düzenleme yetkisi: admin / 'edit_delete' yetkili / görevi oluşturan / göreve atanan.
function task_can_edit($task,$uid,$pid){
    if(is_admin() || can_edit_delete()) return true;
    return task_is_owner($task,$uid,$pid);
}

// Silme yetkisi düzenlemeyle aynı kural (kullanıcı isteği: "admin veya oluşturan/atanan").
function task_can_delete($task,$uid,$pid){
    return task_can_edit($task,$uid,$pid);
}

// "Atanan Personel" alanını DEĞİŞTİRME yetkisi düzenlemeden daha dar tutuldu — sıradan bir
// personel kendi görevini düzenleyebilir ama başkasına devredemez (IDOR/yetki genişletme riski).
function task_can_reassign($task,$uid){
    if(is_admin() || can_edit_delete()) return true;
    return $uid && (int)($task['created_by'] ?? 0)===(int)$uid;
}

// Durum güncelle — sahiplik kontrolü ÇAĞIRAN tarafından yapılmalı (bu fonksiyon zaten
// task_can_edit ile korunan çağrı noktalarından çağrılıyor). Mevcut kodda tekrarlanan
// completed_at/started_at mantığı burada tek merkezde.
function task_set_status($pdo,$id,$status,$uid=null){
    if(!in_array($status,['Atandı','Devam Ediyor','Tamamlandı','İptal'],true)) return false;
    $st=$pdo->prepare("UPDATE tasks SET status=?, updated_by=COALESCE(?,updated_by),
        completed_at=IF(?='Tamamlandı',NOW(),completed_at),
        started_at=IF(?='Devam Ediyor' AND started_at IS NULL,NOW(),started_at)
        WHERE id=? AND deleted_at IS NULL");
    return $st->execute([$status,$uid?:null,$status,$status,(int)$id]);
}

// Düzenleme formu — $post ham $_POST dizisi. $allowReassign false ise personnel_id'ye dokunmaz.
function task_apply_edit($pdo,$id,array $post,$uid,$allowReassign){
    $title=trim($post['title'] ?? '');
    if($title==='') throw new Exception('Başlık boş olamaz.');
    $desc=trim($post['description'] ?? '');
    $due=$post['due_date'] ?? '';
    $due=$due?:null;
    $prio=$post['priority'] ?? 'Normal';
    if(!in_array($prio,['Normal','Yüksek','Acil'],true)) $prio='Normal';
    $status=$post['status'] ?? null;
    if($status!==null && !in_array($status,['Atandı','Devam Ediyor','Tamamlandı','İptal'],true)) $status=null;

    if($allowReassign){
        $pid=(int)($post['personnel_id'] ?? 0) ?: null;
        $sql="UPDATE tasks SET title=?, description=?, due_date=?, priority=?, personnel_id=?, updated_by=?".
             ($status!==null ? ", status=?, completed_at=IF(?='Tamamlandı',NOW(),completed_at), started_at=IF(?='Devam Ediyor' AND started_at IS NULL,NOW(),started_at)" : "").
             " WHERE id=? AND deleted_at IS NULL";
        $params=[$title,$desc,$due,$prio,$pid,$uid?:null];
        if($status!==null) $params=array_merge($params,[$status,$status,$status]);
        $params[]=(int)$id;
    }else{
        $sql="UPDATE tasks SET title=?, description=?, due_date=?, priority=?, updated_by=?".
             ($status!==null ? ", status=?, completed_at=IF(?='Tamamlandı',NOW(),completed_at), started_at=IF(?='Devam Ediyor' AND started_at IS NULL,NOW(),started_at)" : "").
             " WHERE id=? AND deleted_at IS NULL";
        $params=[$title,$desc,$due,$prio,$uid?:null];
        if($status!==null) $params=array_merge($params,[$status,$status,$status]);
        $params[]=(int)$id;
    }
    $pdo->prepare($sql)->execute($params);
    try{ if(function_exists('activity_log')) activity_log('Görev','Düzenleme','#'.(int)$id.' · '.$title,'','task',(int)$id,'','✏️'); }catch(Throwable $e){}
}

// Soft delete — hiçbir kayıt fiziksel silinmez. Çağrı öncesi task_can_delete() ile yetki kontrolü
// çağıran tarafından yapılmalı.
function task_soft_delete($pdo,$id,$uid=null){
    $st=$pdo->prepare("UPDATE tasks SET deleted_at=NOW(), updated_by=COALESCE(?,updated_by) WHERE id=? AND deleted_at IS NULL");
    $ok=$st->execute([$uid?:null,(int)$id]);
    try{ if(function_exists('activity_log')) activity_log('Görev','Silme (soft)','#'.(int)$id,'','task',(int)$id,'','🗑'); }catch(Throwable $e){}
    return $ok;
}

// ---- Yorumlar ----
function task_comments_list($pdo,$taskId){
    try{
        $st=$pdo->prepare("SELECT c.*, u.full_name, u.username FROM task_comments c LEFT JOIN app_users u ON u.id=c.user_id WHERE c.task_id=? ORDER BY c.id DESC");
        $st->execute([(int)$taskId]);
        return $st->fetchAll();
    }catch(Throwable $e){ return []; }
}

function task_comment_add($pdo,$taskId,$uid,$text){
    $text=trim((string)$text);
    if($text==='') throw new Exception('Yorum boş olamaz.');
    $pdo->prepare("INSERT INTO task_comments(task_id,user_id,comment) VALUES(?,?,?)")->execute([(int)$taskId,(int)$uid ?: null,$text]);
    try{ if(function_exists('activity_log')) activity_log('Görev','Yorum','#'.(int)$taskId,mb_substr($text,0,80),'task',(int)$taskId,'','💬'); }catch(Throwable $e){}
}

// ---- Dosyalar ----
function task_files_list($pdo,$taskId){
    try{
        $st=$pdo->prepare("SELECT f.*, u.full_name, u.username FROM task_files f LEFT JOIN app_users u ON u.id=f.uploaded_by WHERE f.task_id=? ORDER BY f.id DESC");
        $st->execute([(int)$taskId]);
        return $st->fetchAll();
    }catch(Throwable $e){ return []; }
}

// job_view.php'deki upload deseniyle aynı (beyaz liste, boyut limiti, rastgele stored_name).
function task_file_upload($pdo,$taskId,$uid,array $file){
    if(!isset($file['tmp_name']) || $file['tmp_name']===''){
        throw new Exception('Dosya alanı sunucuya ulaşmadı.');
    }
    if($file['error'] !== UPLOAD_ERR_OK){
        throw new Exception('Dosya yükleme hatası. Kod: '.$file['error']);
    }
    $uploadDir=__DIR__.'/uploads/task_files';
    if(!is_dir($uploadDir)){
        if(!mkdir($uploadDir,0755,true)) throw new Exception('uploads/task_files klasörü oluşturulamadı.');
    }
    if(!is_writable($uploadDir)) throw new Exception('uploads/task_files klasörü yazılabilir değil.');

    $original=$file['name'];
    $size=(int)$file['size'];
    $mime=$file['type'] ?? '';
    $ext=strtolower(pathinfo($original,PATHINFO_EXTENSION));
    // 'svg' bilinçli olarak dışarıda (2026-07-03 güvenlik denetiminde job_files için de aynı karar
    // alındı — public/paylaşım sayfalarında stored XSS riski).
    $allowed=['jpg','jpeg','png','webp','gif','pdf','ai','cdr','eps','stl','obj','3mf','zip','rar','mp4','mov','doc','docx','xls','xlsx'];
    if(!in_array($ext,$allowed,true)) throw new Exception('Bu dosya türüne izin verilmiyor: '.$ext);
    if($size > 50*1024*1024) throw new Exception('Dosya 50 MB üzerinde olamaz.');

    $stored='task_'.(int)$taskId.'_'.date('YmdHis').'_'.bin2hex(random_bytes(4)).'.'.$ext;
    $target=$uploadDir.'/'.$stored;
    if(!move_uploaded_file($file['tmp_name'],$target)) throw new Exception('Dosya yüklenemedi (yazma izni/limit).');

    $relative='uploads/task_files/'.$stored;
    $pdo->prepare("INSERT INTO task_files(task_id,uploaded_by,original_name,stored_name,file_path,mime_type,file_size) VALUES(?,?,?,?,?,?,?)")
        ->execute([(int)$taskId,(int)$uid ?: null,$original,$stored,$relative,$mime,$size]);
    try{ if(function_exists('activity_log')) activity_log('Görev','Dosya Ekleme','#'.(int)$taskId,$original,'task',(int)$taskId,'','📎'); }catch(Throwable $e){}
    return (int)$pdo->lastInsertId();
}

// Dosya silme — sadece yükleyen kullanıcı ya da düzenleme yetkisi olan silebilir. Fiziksel dosya
// da silinir (job_files'tan farklı olarak burada paylaşım/onay akışı yok, güvenle kaldırılabilir).
function task_file_delete($pdo,$taskId,$fileId,$uid){
    $st=$pdo->prepare("SELECT * FROM task_files WHERE id=? AND task_id=?");
    $st->execute([(int)$fileId,(int)$taskId]);
    $f=$st->fetch();
    if(!$f) return false;
    $canDelete = is_admin() || can_edit_delete() || ((int)($f['uploaded_by'] ?? 0)===(int)$uid && $uid);
    if(!$canDelete) throw new Exception('Bu dosyayı silme yetkiniz yok.');
    try{ $path=__DIR__.'/'.$f['file_path']; if($f['file_path'] && is_file($path)) @unlink($path); }catch(Throwable $e){}
    $pdo->prepare("DELETE FROM task_files WHERE id=?")->execute([(int)$fileId]);
    return true;
}
