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
 * gerçek bir veri yok, icat edilmedi. */
function home_build_continue($pdo, $isAdmin, $canSee){
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
