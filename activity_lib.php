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

// UX/STABILITY PATCH-004 — merkezi "activity target resolver" (REOPEN-002 ile 2026-07-06'da
// sertleştirildi). Önceden her activity_log() çağrısı kendi $url string'ini write-time'da
// donduruyordu, ve harita kapsamayan HER tür kontrolsüzce bu stored url'e düşüyordu — bu yüzden
// sale/purchase kayıtları "yeni kayıt" formuna, bazı finans kayıtları eski/localhost url'lere,
// bazı kayıtlar yanlış platformun klasörüne (mobile/... web'de, ya da tersi) gidebiliyordu.
// activity_resolve() artık tek bir durum döndürür:
//   'ok'                → gerçek/güvenli bir hedef var, $result['url'] tıklanabilir.
//   'missing'           → eşlenmiş tür ama kayıt DB'de artık yok (silinmiş) — pasif.
//   'no_detail'         → bu tür (sale/purchase) için per-kayıt detay ekranı hiç yok; stored url
//                          her zaman "yeni kayıt" formu olduğundan ASLA kullanılmaz — pasif.
//   'unsafe_stored_url' → tür haritada yok (veya entity_id yok) VE stored url
//                          activity_safe_stored_url()'den geçemedi — pasif.
// Haritada olmayan bir tür için stored url SADECE activity_safe_stored_url() onayladığında 'ok'
// olarak kullanılır — kontrolsüz körü körüne fallback yok.
function activity_resolve($r,$platform='web'){
    $entityType=$r['entity_type'] ?? '';
    $entityId=$r['entity_id'] ?? null;
    $storedUrl=$r['url'] ?? '';

    // sale/purchase: entity_id write-time'da bir stok kalemi id'si olarak kaydediliyor — "satış/alış
    // kaydı" diye ayrı bir varlık/detay ekranı hiç yok, stored url her zaman boş "yeni kayıt" formu.
    static $noDetailTypes=['sale'=>true,'purchase'=>true];
    if(isset($noDetailTypes[$entityType])) return ['status'=>'no_detail'];

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

    if(!empty($map[$entityType]) && !empty($entityId)){
        $cfg=$map[$entityType]; $eid=(int)$entityId; $exists=null;
        try{
            $sql="SELECT 1 FROM {$cfg['table']} WHERE id=?";
            if(!empty($cfg['softDelete'])) $sql.=" AND {$cfg['softDelete']} IS NULL";
            $st=db()->prepare($sql); $st->execute([$eid]);
            $exists=(bool)$st->fetchColumn();
        }catch(Throwable $e){ $exists=null; }
        if($exists===false) return ['status'=>'missing'];
        if($exists===true){
            $tpl=($platform==='mobile')?$cfg['mobile']:$cfg['web'];
            $path=sprintf($tpl,$eid);
            return ['status'=>'ok','url'=> !empty($cfg['absolute']) ? base_url().$path : $path];
        }
        // $exists===null: sorgu hatası — haritalı tür ama doğrulanamadı, aşağıdaki güvenli
        // stored-url kontrolüne düş (körü körüne DEĞİL, hâlâ activity_safe_stored_url süzgecinden).
    }

    if(activity_safe_stored_url($storedUrl,$platform)) return ['status'=>'ok','url'=>$storedUrl];
    return ['status'=>'unsafe_stored_url'];
}

// Write-time'da donmuş bir stored url'in RENDER ANINDAKİ platformda güvenle kullanılıp
// kullanılamayacağını kontrol eder. Reddedilme sebepleri: eski/yerel geliştirme host'u (localhost,
// 127.0.0.1, farklı port), web ekranında mobile/ klasörüne giden bir yol, ya da bir platformda sadece
// diğer platformun klasöründe var olan bir dosyaya giden bare (mobile/ önekisiz) bir yol.
function activity_safe_stored_url($url,$platform){
    $url=trim((string)$url);
    if($url==='' || $url==='#') return false;
    if(preg_match('#^https?://#i',$url)){
        $host=parse_url($url,PHP_URL_HOST);
        $port=parse_url($url,PHP_URL_PORT);
        if(!$host) return false;
        if(preg_match('/^(localhost|127\.0\.0\.1|0\.0\.0\.0)$/i',$host)) return false;
        $curHost=parse_url(base_url(),PHP_URL_HOST);
        if($curHost && strcasecmp($host,$curHost)!==0) return false;
        if($port) return false;
        $path=ltrim((string)parse_url($url,PHP_URL_PATH),'/');
    }else{
        $path=ltrim($url,'/');
    }
    $file=strtok($path,'?');
    if($platform==='web' && strpos($path,'mobile/')===0) return false;
    if($platform==='mobile' && strpos($path,'mobile/')!==0){
        if($file!=='' && !is_file(__DIR__.'/mobile/'.$file)) return false;
    }
    if($platform==='web' && strpos($path,'mobile/')!==0){
        if($file!=='' && !is_file(__DIR__.'/'.$file)) return false;
    }
    return true;
}

// Bir Son İşlemler satırını resolver + güvenli-fallback ile tek bir <a>/<div> bloğuna çevirir (web
// ve mobil render'ı aynı mantığı paylaşsın diye ortak fonksiyona çıkarıldı).
// PİLOT ÖNCESİ KAPANIŞ (2026-07-19, "aktif route'ta MODERN/MIXED/LEGACY" denetimi — gerçek bulgu):
// bu fonksiyon önceden kendi özel `.activity-item`/`.activity-icon`/`.activity-body` sınıflarını
// (layout_top.php'de hardcoded hex renkli, web zaten daima açık tema olduğu için görsel bir "beyaz
// üstü beyaz" hatası yoktu ama DS'in geri kalanından (df-list/ds_list_item()) FARKLI bir bileşen
// ailesiydi — aynı sayfada modern+eski karışımı hissi buradan geliyordu) kullanıyordu. Artık
// ds_list_item() (web+mobil ORTAK DS liste satırı bileşeni) ile render ediliyor — veri/link/güvenlik
// mantığı (activity_resolve()) hiç değişmedi, sadece görsel katman.
function activity_row_html($r,$platform='web'){
    $res=activity_resolve($r,$platform);
    $icon=h($r['icon'] ?: '•'); $title=h($r['title']);
    $descHtml = $r['description'] ? h($r['description']) : null;
    $metaRight = '<span class="df-text-caption">'.h(activity_time_ago($r['created_at'])).'</span>';
    $titleHtml = $icon.' <b>'.$title.'</b>';
    $descFull = '<span class="df-muted">'.h($r['user_name'] ?: 'Sistem').' · '.h($r['module']).'</span>'.($descHtml ? '<br>'.$descHtml : '');
    ob_start();
    if($res['status']==='ok'){
        ds_list_item($titleHtml, $res['url'], $descFull, $metaRight);
    }else{
        $note = $res['status']==='missing' ? 'Kayıt artık mevcut değil'
            : ($res['status']==='no_detail' ? 'Bu işlem için detay ekranı henüz yok'
            : 'Kayıt detayına güvenli şekilde ulaşılamıyor');
        ds_list_item($titleHtml, null, $descFull.'<br><i class="df-muted">'.h($note).'</i>', null, false);
    }
    return ob_get_clean();
}

function activity_render_list($rows,$platform='web'){
    if(!$rows){
        ds_empty_state('Henüz işlem kaydı yok.');
        return;
    }
    echo "<div class='df-list'>";
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