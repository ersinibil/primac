<?php
/* PX-001 (2026-07-16) — Home Screen v1.1 gerçek veri katmanı. Web (dashboard.php) ve mobil
 * (mobile/index.php) compact modun ORTAK kaynağıdır — tek sorgu seti, iki render yüzeyi.
 * Tasarım mockup'ında onaylanan yapı: tek "hero" (en acil), sessiz "Sırada" kuyruğu, "Devam Et"
 * bağlamsal kartları. Kritik uyarılar (gecikmiş iş/kritik stok) ayrı bir banner DEĞİL, kuyruğun
 * içinde birer satır (Product Owner kararı).
 *
 * Öncelik puanlaması (v1, kasıtlı basit — "gerçek kullanım eksikleri ortaya çıkaracak" prensibiyle
 * ilk sürüm sade tutuldu, karmaşık bir sıralama motoru KURULMADI):
 * gecikmiş iş (300+gün) > gecikmiş görev (250) > onay bekleyen dosya (150) > bugünkü görev (120)
 * > kritik stok (90). En yüksek puan hero olur, kalanı Sırada'ya sıralanır.
 */

function home_build_queue($pdo, $isAdmin, $canSee, $pid, $platform = 'web'){
    $items = [];
    $canJobs = $isAdmin || $canSee('jobs');

    if($canJobs){
        try{
            $rows = $pdo->query("
                SELECT j.id, j.job_no, j.title, j.due_date, c.name AS customer_name,
                       DATEDIFF(CURDATE(), j.due_date) AS days_late
                FROM jobs j LEFT JOIN contacts c ON c.id = j.customer_id
                WHERE j.due_date IS NOT NULL AND j.due_date < CURDATE()
                AND j.status NOT IN ('Tamamlandı','İptal','Teslim Edildi')
                ORDER BY j.due_date ASC LIMIT 3
            ")->fetchAll();
            foreach($rows as $r){
                $days = (int)$r['days_late'];
                $items[] = [
                    'score' => 300 + min($days, 30),
                    'title' => $r['title'],
                    'meta'  => $r['job_no'].' · '.($r['customer_name'] ?: 'Cari yok'),
                    'pill'  => ['label' => $days.' gün gecikti', 'tone' => 'late'],
                    'url'   => 'job_view.php?id='.$r['id'],
                ];
            }
        }catch(Throwable $e){}

        try{
            $n = (int)($pdo->query("SELECT COUNT(*) c FROM job_files WHERE approval_status='Müşteri Onayı Bekliyor'")->fetch()['c'] ?? 0);
            if($n > 0){
                $items[] = ['score'=>150, 'title'=>$n.' dosya onay bekliyor', 'meta'=>'Tümünü gör', 'pill'=>['label'=>'Onayda','tone'=>'approve'], 'url'=>'approval_waiting.php'];
            }
        }catch(Throwable $e){}
    }

    if($pid && function_exists('task_my_stats')){
        try{
            $stats = task_my_stats($pdo, $pid);
            if(!empty($stats['overdue'])){
                $items[] = ['score'=>250, 'title'=>$stats['overdue'].' göreviniz gecikti', 'meta'=>'Görevlerim', 'pill'=>['label'=>'Gecikti','tone'=>'late'], 'url'=>'mytasks.php'];
            }elseif(!empty($stats['today'])){
                $items[] = ['score'=>120, 'title'=>$stats['today'].' bugünkü göreviniz var', 'meta'=>'Görevlerim', 'pill'=>['label'=>'Bugün','tone'=>'progress'], 'url'=>'mytasks.php'];
            }
        }catch(Throwable $e){}
    }

    $canStock = $isAdmin || $canSee('stock');
    if($canStock){
        try{
            $n = (int)($pdo->query("SELECT COUNT(*) c FROM stock_items WHERE quantity<=critical_level")->fetch()['c'] ?? 0);
            if($n > 0){
                $items[] = ['score'=>90, 'title'=>$n.' ürün kritik stokta', 'meta'=>'Stoğu gör', 'pill'=>['label'=>'Kritik','tone'=>'crit'], 'url'=>'stock.php?critical=1'];
            }
        }catch(Throwable $e){}
    }

    usort($items, function($a,$b){ return $b['score'] <=> $a['score']; });
    $hero = array_shift($items);
    $cap = $platform === 'mobile' ? 2 : 4;
    return ['hero'=>$hero, 'queue'=>array_slice($items,0,$cap), 'more'=>max(0, count($items)-$cap)];
}

/* "Devam Et" — v1 kasıtlı basitleştirme: kullanıcı-bazlı "son görüntülenen" takibi ALTYAPISI
 * projede henüz yok (yeni bir view-tracking tablosu bu turun kapsamı dışı). Bunun yerine gerçek,
 * sorgusu ucuz, yanıltıcı olmayan bir vekil kullanılıyor: son güncellenen açık iş / son eklenen
 * cari / son oluşturulan teklif. "Taslak iş" kavramı (mockup'ta vardı) kaldırıldı — karşılığı olan
 * gerçek bir veri yok, icat edilmedi.
 * FAZ 2C-ii EKİ (2026-07-17) — "Son Görev": $pid opsiyonel (varsayılan 0, geriye uyumlu — mevcut
 * 3 argümanlı çağrılar davranış değiştirmeden çalışmaya devam eder). Görev görüntüleme YETKİSİ
 * kontrolü burada `tasks` modül izni değil — o TÜM görevler admin ekranını kapsar; kişinin KENDİ
 * görevleri (mytasks.php ile aynı ilke) herkese açıktır, gerçek "yetki" sınırı personel kaydına
 * bağlı olup olmadığıdır ($pid), tıpkı home_build_queue()'nun görev satırı için zaten kullandığı
 * kural gibi. `tasks` tablosunda updated_at kolonu yok (bkz. migration 003) — job'daki
 * COALESCE(updated_at,created_at) deseni burada uygulanamaz, en son ATANAN açık görev (id DESC)
 * kullanılıyor. */
function home_build_continue($pdo, $isAdmin, $canSee, $pid = 0){
    $out = [];
    if($isAdmin || $canSee('jobs')){
        try{
            $r = $pdo->query("SELECT id, job_no, title FROM jobs WHERE status NOT IN ('Tamamlandı','İptal','Teslim Edildi') ORDER BY COALESCE(updated_at,created_at) DESC LIMIT 1")->fetch();
            if($r) $out[] = ['eyebrow'=>'Son Açık İş', 'title'=>$r['title'], 'url'=>'job_view.php?id='.$r['id']];
        }catch(Throwable $e){}
    }
    if($isAdmin || $canSee('contacts')){
        try{
            $r = $pdo->query("SELECT id, name FROM contacts ORDER BY id DESC LIMIT 1")->fetch();
            if($r) $out[] = ['eyebrow'=>'Son Eklenen Cari', 'title'=>$r['name'], 'url'=>'contact_view.php?id='.$r['id']];
        }catch(Throwable $e){}
    }
    if($isAdmin || $canSee('teklif')){
        try{
            $r = $pdo->query("SELECT id, quote_no, customer_name FROM quotes ORDER BY id DESC LIMIT 1")->fetch();
            if($r) $out[] = ['eyebrow'=>'Son Teklif', 'title'=>($r['customer_name'] ?: $r['quote_no']), 'url'=>'teklif.php?view='.$r['id']];
        }catch(Throwable $e){}
    }
    if($pid){
        try{
            $r = $pdo->prepare("SELECT id, title FROM tasks WHERE personnel_id=? AND deleted_at IS NULL AND status NOT IN ('Tamamlandı','İptal') ORDER BY id DESC LIMIT 1");
            $r->execute([$pid]);
            $r = $r->fetch();
            if($r) $out[] = ['eyebrow'=>'Son Görev', 'title'=>$r['title'], 'url'=>'task_view.php?id='.$r['id']];
        }catch(Throwable $e){}
    }
    return $out;
}

// FAZ 2C-ii EKİ (2026-07-17) — Nabız Satırı artık df-alert ile render ediliyor (A maddesi);
// dashboard_pulse_state()'in ürettiği 4 seviye (green/yellow/red/neutral) df-alert'in 4 tonuna
// (success/warning/danger/info) eşleniyor — yeni bir renk/durum icat edilmedi.
function home_pulse_alert_type($level){
    return ['green'=>'success','yellow'=>'warning','red'=>'danger','neutral'=>'info'][$level] ?? 'info';
}

// FAZ 2C-ii EKİ (2026-07-17) — Hızlı İşlemler (C maddesi): dashboard_quick_action_defs() (boot.php)
// emoji ikon taşıyor, yeni compact chip satırı ds_icon() istiyor — bu SADECE sunum katmanı eşlemesi,
// veri kaynağına (dashboard_quick_actions_split()) dokunulmadı. Her key mevcut ds_icon() whitelist'inden.
function home_quick_action_icon($key){
    return [
        'job'=>'briefcase','satis'=>'tag','tahsilat'=>'wallet','alis'=>'box',
        'odeme'=>'send','gorev'=>'calendar','talep'=>'info','teklif'=>'edit','mesaj'=>'chat',
    ][$key] ?? 'menu-dots';
}

/* FAZ 2C-ii EKİ (2026-07-17) — Genel Bakış (E maddesi): yalnızca Admin, varsayılan kapalı
 * accordion içindeki mini-stat satırları. YENİ finans matematiği/raporlama sorgusu İCAT EDİLMEDİ —
 * her sorgu, projede ZATEN var olan başka bir ekrandan (dashboard.php legacy KPI bloğu / mobile
 * legacy Bugün-Özet bloğu) birebir kopyalandı: bugünkü tahsilat sorgusu Finans Çekirdek
 * Stabilizasyonu'nun "yalnızca gerçek kasa/banka hareketi" düzeltmesini (account_id IS NOT NULL)
 * kullanıyor — mobile legacy'nin filtre eksik versiyonu DEĞİL, dashboard.php'nin denetimden geçmiş
 * versiyonu tercih edildi. Her alan ayrı try/catch — biri başarısız olursa diğerleri etkilenmez,
 * null dönen alan çağıran tarafta sessizce atlanır (Home çökmez).
 */
function home_build_overview($pdo){
    $out = [];
    try{
        $v = (float)($pdo->query("SELECT COALESCE(SUM(amount),0) s FROM finance_movements WHERE direction='in' AND account_id IS NOT NULL AND DATE(movement_date)=CURDATE()")->fetch()['s'] ?? 0);
        $out['today_collection'] = $v;
    }catch(Throwable $e){}
    try{
        $out['open_jobs'] = (int)($pdo->query("SELECT COUNT(*) c FROM jobs WHERE status NOT IN ('Tamamlandı','İptal','Teslim Edildi')")->fetch()['c'] ?? 0);
    }catch(Throwable $e){}
    try{
        $out['total_contacts'] = (int)($pdo->query("SELECT COUNT(*) c FROM contacts")->fetch()['c'] ?? 0);
    }catch(Throwable $e){}
    try{
        $tp = $pdo->query("SELECT p.name, COUNT(*) c FROM jobs j JOIN personnel p ON p.id=j.responsible_personnel_id WHERE j.status IN ('Tamamlandı','Teslim Edildi') GROUP BY p.id ORDER BY c DESC LIMIT 1")->fetch();
        if($tp) $out['top_personnel'] = $tp['name'].' ('.$tp['c'].')';
    }catch(Throwable $e){}
    return $out;
}

// df-badge zaten var olan 4 tonuyla (success/warning/danger/info) eşleme — yeni renk paleti icat
// edilmedi (Evolution not Revolution). 'late' ve 'crit' ayrı görünsün diye late->danger, crit->warning.
function home_pill_badge_tone($tone){
    return ['late'=>'danger','approve'=>'info','progress'=>'info','wait'=>'warning','crit'=>'warning'][$tone] ?? 'warning';
}

function home_today_label(){
    $days = ['Pazartesi','Salı','Çarşamba','Perşembe','Cuma','Cumartesi','Pazar'];
    $months = ['Ocak','Şubat','Mart','Nisan','Mayıs','Haziran','Temmuz','Ağustos','Eylül','Ekim','Kasım','Aralık'];
    $dow = $days[(int)date('N') - 1];
    $mon = $months[(int)date('n') - 1];
    return ['dow' => $dow, 'date' => date('j').' '.$mon];
}
