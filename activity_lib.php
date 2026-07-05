<?php
// ACANS OS Core Activity Logger v17

function activity_install(){
    try{
        db()->exec("CREATE TABLE IF NOT EXISTS activity_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NULL,
            user_name VARCHAR(160) NULL,
            module VARCHAR(80) NULL,
            action VARCHAR(120) NULL,
            title VARCHAR(255) NULL,
            description TEXT NULL,
            entity_type VARCHAR(80) NULL,
            entity_id INT NULL,
            url VARCHAR(255) NULL,
            icon VARCHAR(20) DEFAULT '•',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_module(module),
            INDEX idx_entity(entity_type, entity_id),
            INDEX idx_created(created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }catch(Throwable $e){}
}

function activity_user_name(){
    return $_SESSION['user']['name'] ?? $_SESSION['user']['username'] ?? 'Sistem';
}

function activity_user_id(){
    return $_SESSION['user']['id'] ?? null;
}

function activity_log($module,$action,$title,$description='',$entityType='',$entityId=null,$url='',$icon='•'){
    try{
        activity_install();
        $stmt=db()->prepare("INSERT INTO activity_logs(user_id,user_name,module,action,title,description,entity_type,entity_id,url,icon)
            VALUES(?,?,?,?,?,?,?,?,?,?)");
        $stmt->execute([
            activity_user_id(),
            activity_user_name(),
            $module,
            $action,
            $title,
            $description,
            $entityType,
            $entityId,
            $url,
            $icon
        ]);
    }catch(Throwable $e){}
}

function activity_recent($limit=20,$entityType='',$entityId=null){
    activity_install();
    if($entityType && $entityId){
        $s=db()->prepare("SELECT * FROM activity_logs WHERE entity_type=? AND entity_id=? ORDER BY id DESC LIMIT ".(int)$limit);
        $s->execute([$entityType,(int)$entityId]);
        return $s->fetchAll();
    }
    return db()->query("SELECT * FROM activity_logs ORDER BY id DESC LIMIT ".(int)$limit)->fetchAll();
}

function activity_time_ago($date){
    $ts=strtotime($date);
    if(!$ts) return '';
    $diff=time()-$ts;
    if($diff<60) return $diff.' sn önce';
    if($diff<3600) return floor($diff/60).' dk önce';
    if($diff<86400) return floor($diff/3600).' saat önce';
    return date('d.m.Y H:i',$ts);
}

// UX/STABILITY PATCH-004 — merkezi "activity target resolver". Önceden her activity_log() çağrısı
// kendi $url string'ini write-time'da donduruyordu (bazıları base_url().'mobile/...' gibi TEK
// platforma kilitli) — Son İşlemler bu stored url'i hiç sorgulamadan basıyordu, bu yüzden web'de
// açılan bir kayıt mobile route'a, mobilde açılan bir kayıt web route'a gidebiliyordu. Bu fonksiyon
// entity_type+entity_id+platform'a göre RENDER ANINDA doğru hedefi üretir; eski/stored url sadece bu
// haritanın kapsamadığı türler için (ör. 'admin' silme denetimi, satış/satın alma — bunların per-kayıt
// bir detay ekranı hiç yok) fallback olarak kullanılmaya devam eder.
// Dönüş: string (çözülmüş URL) | false (kayıt silinmiş/artık yok) | null (bu tür resolver kapsamında
// değil — çağıran stored url'e düşmeli).
function activity_target_url($entityType,$entityId,$platform='web'){
    static $map=null;
    if($map===null){
        $map=[
            // Aynı basename web+mobilde de var — bare path her iki bağlamdan da doğru çözülür.
            'contact'        => ['table'=>'contacts',       'web'=>'contact_view.php?id=%d',        'mobile'=>'contact_view.php?id=%d'],
            'job'            => ['table'=>'jobs',            'web'=>'job_view.php?id=%d',            'mobile'=>'job_view.php?id=%d'],
            'job_file'       => ['table'=>'jobs',            'web'=>'job_view.php?id=%d',            'mobile'=>'job_view.php?id=%d'],
            'task'           => ['table'=>'tasks',           'web'=>'task_view.php?id=%d',           'mobile'=>'task_view.php?id=%d','softDelete'=>'deleted_at'],
            'quote'          => ['table'=>'quotes',          'web'=>'teklif.php?id=%d',              'mobile'=>'teklif.php?id=%d'],
            'product'        => ['table'=>'stock_items',     'web'=>'product_view.php?id=%d',        'mobile'=>'product_view.php?id=%d'],
            'stock'          => ['table'=>'stock_items',     'web'=>'product_view.php?id=%d',        'mobile'=>'product_view.php?id=%d'],
            // Platforma göre FARKLI dosya/klasör — mutlak (base_url()) path zorunlu, aksi halde
            // hangi taraftan render edildiğine göre yanlış klasöre düşer.
            'personnel'      => ['table'=>'personnel',       'web'=>'personnel_edit.php?id=%d',      'mobile'=>'mobile/personnel_view.php?id=%d','absolute'=>true],
            'trade_document' => ['table'=>'trade_documents', 'web'=>'trade_document_view.php?id=%d', 'mobile'=>'trade_document_view.php?id=%d','absolute'=>true],
        ];
    }
    if(empty($map[$entityType]) || empty($entityId)) return null;
    $cfg=$map[$entityType]; $entityId=(int)$entityId;
    try{
        $sql="SELECT 1 FROM {$cfg['table']} WHERE id=?";
        if(!empty($cfg['softDelete'])) $sql.=" AND {$cfg['softDelete']} IS NULL";
        $st=db()->prepare($sql); $st->execute([$entityId]);
        if(!$st->fetchColumn()) return false;
    }catch(Throwable $e){ return null; }
    $tpl=($platform==='mobile')?$cfg['mobile']:$cfg['web'];
    $path=sprintf($tpl,$entityId);
    return !empty($cfg['absolute']) ? base_url().$path : $path;
}

// Bir Son İşlemler satırını resolver + stored-url fallback ile tek bir <a>/<div> bloğuna çevirir
// (web ve mobil render'ı aynı mantığı paylaşsın diye ortak fonksiyona çıkarıldı).
function activity_row_html($r,$platform='web'){
    $resolved=activity_target_url($r['entity_type'] ?? '', $r['entity_id'] ?? null, $platform);
    $icon=h($r['icon'] ?: '•'); $title=h($r['title']); $desc=$r['description']?"<p>".h($r['description'])."</p>":'';
    $meta=h($r['user_name'] ?: 'Sistem')." · ".h($r['module'])." · ".h(activity_time_ago($r['created_at']));
    if($resolved===false){
        return "<div class='activity-item' style='opacity:.55;cursor:default'>"
            ."<div class='activity-icon'>$icon</div>"
            ."<div class='activity-body'><div><b>$title</b></div>$desc"
            ."<small>$meta · <i>Kayıt artık mevcut değil</i></small></div></div>";
    }
    $url=$resolved ?? ($r['url'] ?: '#');
    return "<a class='activity-item' href='".h($url)."'>"
        ."<div class='activity-icon'>$icon</div>"
        ."<div class='activity-body'><div><b>$title</b></div>$desc"
        ."<small>$meta</small></div></a>";
}

function activity_render_list($rows,$platform='web'){
    if(!$rows){
        echo "<p class='muted'>Henüz işlem kaydı yok.</p>";
        return;
    }
    echo "<div class='activity-list'>";
    foreach($rows as $r){ echo activity_row_html($r,$platform); }
    echo "</div>";
}

// Bir kullanıcının (personelin) yaptığı işlemler — kim ne yaptı kaydı (mobil+web ortak, tema-nötr)
function activity_user_html($pdo,$userId,$limit=30){
    $userId=(int)$userId;
    if(!$userId) return '<p style="opacity:.7;margin:8px 0">Bu personelin giriş hesabı yok — işlemleri ayrı izlenemez.</p>';
    try{
        $s=$pdo->prepare("SELECT module,action,title,icon,created_at FROM activity_logs WHERE user_id=? ORDER BY id DESC LIMIT ".(int)$limit);
        $s->execute([$userId]); $rows=$s->fetchAll();
    }catch(Throwable $e){ return '<p style="opacity:.7;margin:8px 0">İşlem kaydı tablosu yok (migrate gerekli).</p>'; }
    if(!$rows) return '<p style="opacity:.7;margin:8px 0">Henüz işlem kaydı yok.</p>';
    $h='';
    foreach($rows as $r){
        $h.='<div style="display:flex;gap:8px;align-items:flex-start;padding:7px 0;border-bottom:1px solid rgba(128,128,128,.18)">';
        $h.='<span style="font-size:15px">'.h($r['icon'] ?: '•').'</span>';
        $h.='<div style="flex:1;min-width:0"><b>'.h($r['action']).'</b> <small style="opacity:.65">'.h($r['module']).'</small><br><small style="opacity:.8">'.h($r['title']).'</small></div>';
        $h.='<small style="opacity:.6;white-space:nowrap">'.h(date('d.m H:i',strtotime($r['created_at']))).'</small></div>';
    }
    return $h;
}
?>