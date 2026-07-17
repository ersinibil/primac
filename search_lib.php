<?php
// search_lib.php — Global arama ortak mantığı. Web (search.php) ve mobil (mobile/search.php)
// aynı sorguları burdan kullanır, kopya SQL yazılmaz.
//
// NOT (2026-07-02 bugfix): eski search.php'de AYNI SINIF hata 3 bölümde birden vardı — sorgular var
// olmayan kolonları arıyor, `try/catch` "Unknown column" hatasını sessizce yutup boş dizi döndürüyordu:
//   - Personel: `title`/`department` yok → gerçek kolonlar `role`/`work_type` (001_core_auth.sql).
//   - Cari: `tax_no` yok → gerçek kolon `tax_number` (002_contacts_crm.sql).
//   - Stok: `sku`/`description` yok → gerçek kolonlar `product_code`/`barcode`/`notes` (004_stock_products.sql).
// Yani "İşler" dışındaki neredeyse tüm bölümler hep boş dönüyordu. Hepsi aşağıda düzeltildi.

// FAZ 2B-ii-R/2b (2026-07-17) — AKILLI ARAMA. Product Owner kararı: "Search yalnızca SQL LIKE
// değildir." Bu katman search_run()'ın kayıt bazlı LIKE sorgularına (jobs/contacts/stock/... —
// hiçbiri bu turda DEĞİŞMEDİ, bilinçli kapsam kararı) DOKUNMAZ; yalnızca 'pages' (modül/sayfa
// kısayolu) katmanını nav_lib.php::nav_search_index()'in TEK KAYNAK modül kataloğuna bağlar ve
// üstüne Türkçe normalizasyon + prefix + kısmi + fuzzy (Levenshtein) eşleşme ekler. Yeni bir
// modül/URL listesi burada TEKRAR TANIMLANMAZ — PARITY-003'ün (bkz. memory/backlog.md) önlediği
// "elle kopyalanan URL haritası zamanla sapar" hatasını burada yeniden açmamak için.
if (!function_exists('search_normalize')) {
    // ş/ğ/ı/ö/ü/ç -> s/g/i/o/u/c, Türkçe İ/I -> i (mb_strtolower'ın noktalı-ı artefaktını önler).
    // Yalnızca bu dosyadaki PHP-taraflı alias/fuzzy karşılaştırmasını etkiler — DB sorgularının
    // LIKE/collation davranışı DEĞİŞMEDİ (bilinçli kapsam kararı, R/2b teslim notunda işlendi).
    function search_normalize($s) {
        $s = (string)$s;
        $s = str_replace(['İ','I'], ['i','i'], $s);
        $s = mb_strtolower($s, 'UTF-8');
        return trim(strtr($s, ['ş'=>'s','ğ'=>'g','ı'=>'i','ö'=>'o','ü'=>'u','ç'=>'c']));
    }
}

if (!function_exists('search_module_score')) {
    // Normalize edilmiş sorgu ($qNorm) ile tek bir anahtar kelimenin ($kwNorm) eşleşme kademesi.
    // Küçük sayı = güçlü eşleşme: 0=tam eşleşme (kanonik ad), 1=tam eşleşme (alias),
    // 2=prefix (iki yönlü — "wha"->"whatsapp" ve "stok az"->"stok"), 3=kısmi/içerme (iki yönlü),
    // 4=fuzzy (Levenshtein, küçük yazım hataları). Eşleşme yoksa null.
    function search_module_score($qNorm, $kwNorm, $isCanonical) {
        if ($qNorm === '' || $kwNorm === '') return null;
        if ($qNorm === $kwNorm) return $isCanonical ? 0 : 1;
        $qLen = mb_strlen($qNorm, 'UTF-8'); $kwLen = mb_strlen($kwNorm, 'UTF-8');
        if ($qLen >= 2 && $kwLen >= 2 && (strpos($kwNorm, $qNorm) === 0 || strpos($qNorm, $kwNorm) === 0)) return 2;
        if ($qLen >= 3 && $kwLen >= 3 && (strpos($kwNorm, $qNorm) !== false || strpos($qNorm, $kwNorm) !== false)) return 3;
        if ($qLen >= 4 && $qLen <= 40 && $kwLen <= 40 && levenshtein($qNorm, $kwNorm) <= ($qLen <= 5 ? 1 : 2)) return 4;
        return null;
    }
}

if (!function_exists('search_module_shortcuts')) {
    /**
     * Modül/sayfa kısayolu sonuçları — search_run()'ın 'pages' anahtarını besler. $canSee/$isAdmin/
     * $platform tıpkı nav_search_index() gibi (yetkisiz/platformda gizli hedef ASLA girmez).
     * @return array<int, array{label:string, icon:string, target:string}> target = nav_taxonomy() key'i
     *   (tüketici sayfa nav_module_by_key()+nav_url_for_platform() ile gerçek href'e çevirir).
     */
    function search_module_shortcuts($q, $canSee, $isAdmin, $platform = 'web') {
        $qNorm = search_normalize($q);
        if ($qNorm === '' || mb_strlen($qNorm, 'UTF-8') < 2) return [];
        if (!function_exists('nav_search_index')) return [];

        // Kategori bazlı emoji (legacy web + mobil dalları hâlâ $pg['icon']'u ham basıyor — bkz.
        // mobile/search.php satır ~40, search.php legacy dalı — compact web dalı zaten hiçbir liste
        // satırında ikon basmıyor, ds_list_item()'a icon parametresi bu turda EKLENMEDİ, kapsam dışı).
        $catIcon = ['isler'=>'📋', 'ticaret'=>'🧾', 'uretim_stok'=>'📦', 'finans'=>'💰', 'yonetim'=>'⚙️'];
        $keyIcon = ['dashboard'=>'🏠', 'messages'=>'💬', 'notifications'=>'🔔', 'wa_conversations'=>'💬', 'profile'=>'👤'];

        $scored = [];
        foreach (nav_search_index($canSee, $isAdmin, $platform) as $entry) {
            $best = search_module_score($qNorm, search_normalize($entry['actionLabel']), true);
            foreach ($entry['keywords'] as $kw) {
                $s = search_module_score($qNorm, search_normalize($kw), false);
                if ($s !== null && ($best === null || $s < $best)) $best = $s;
            }
            if ($best !== null) $scored[] = ['tier' => $best, 'entry' => $entry];
        }
        usort($scored, function($a, $b) {
            return $a['tier'] !== $b['tier'] ? $a['tier'] <=> $b['tier'] : strcmp($a['entry']['actionLabel'], $b['entry']['actionLabel']);
        });
        $out = [];
        foreach (array_slice($scored, 0, 8) as $s) {
            $e = $s['entry'];
            $out[] = ['label' => $e['actionLabel'], 'icon' => $keyIcon[$e['key']] ?? ($catIcon[$e['categoryKey']] ?? '🔗'), 'target' => $e['key']];
        }
        return $out;
    }
}

if (!function_exists('search_hl')) {
    // Eşleşen kısmı <mark> ile vurgular. $text zaten htmlspecialchars'lanır (çağıran taraf tekrar kaçırmasın).
    function search_hl($text, $q) {
        $text = (string)$text;
        if ($q === '') return htmlspecialchars($text);
        return preg_replace('/('.preg_quote(htmlspecialchars($q), '/').')/iu',
            '<mark style="background:#fef08a;border-radius:3px;padding:0 2px">$1</mark>',
            htmlspecialchars($text));
    }
}

if (!function_exists('search_run')) {
    /**
     * Tüm modüllerde arama yapar, ham satırları döner (HTML/link üretmez — bunu çağıran sayfa yapar,
     * çünkü web ve mobil detay sayfalarının URL'leri farklı).
     * $platform (FAZ 2B-ii-R/2b, 2026-07-17): 'web'|'mobile' — yalnızca 'pages' katmanının
     * nav_search_index()'i (mobileHide filtresi) doğru platformla sorgulaması için. Varsayılan
     * 'web' — mevcut web çağrı siteleri (search.php x2) DEĞİŞMEDİ, yalnızca mobile/search.php
     * kendi çağrısına 'mobile' eklemesi gerekti (bkz. features.md R/2b notu).
     * @return array{jobs:array,contacts:array,stock:array,personnel:array,accounts:array,movements:array,checks:array,quotes:array,documents:array,files:array,tasks:array,users:array,notes:array,messages:array,pages:array}
     */
    function search_run(PDO $pdo, $q, $platform = 'web') {
        $q = trim((string)$q);
        $out = ['jobs'=>[], 'contacts'=>[], 'stock'=>[], 'personnel'=>[], 'accounts'=>[], 'movements'=>[], 'checks'=>[], 'quotes'=>[], 'documents'=>[], 'files'=>[], 'tasks'=>[], 'users'=>[], 'notes'=>[], 'messages'=>[], 'pages'=>[]];
        if ($q === '') return $out;
        $like = '%'.$q.'%';
        // Modül-adı kısayolu tespiti (çek/teklif/belge/personel gibi) — aşağıda tekrar kullanılıyor,
        // en üstte tek yerden hesaplanıyor.
        $qNorm = mb_strtolower($q, 'UTF-8');

        // Her bölüm kendi modül yetkisiyle korunuyor — 2026-07-02 güvenlik denetiminde bulundu:
        // yetkisi olmayan personel arama üzerinden finans bakiyesi/IBAN, personel iletişim bilgisi
        // gibi verileri görebiliyordu (menüde gizli olan modüller aramada açıktı). user_can() admin
        // için zaten true dönüyor, session'a değil DB'ye bakıyor (boot.php).

        // İşler
        if (function_exists('user_can') && user_can('jobs')) { try {
            $s = $pdo->prepare("SELECT j.id, j.job_no, j.title, j.status, c.name customer
                FROM jobs j LEFT JOIN contacts c ON c.id=j.customer_id
                WHERE j.title LIKE ? OR j.job_no LIKE ? OR j.description LIKE ? OR c.name LIKE ?
                ORDER BY j.id DESC LIMIT 30");
            $s->execute([$like,$like,$like,$like]);
            $out['jobs'] = $s->fetchAll();
        } catch (Throwable $e) {} }

        // Cari — DÜZELTİLDİ: tax_no yerine gerçek kolon tax_number
        if (function_exists('user_can') && user_can('contacts')) { try {
            $s = $pdo->prepare("SELECT id,name,phone,email,city FROM contacts
                WHERE name LIKE ? OR phone LIKE ? OR email LIKE ? OR tax_number LIKE ? OR city LIKE ?
                ORDER BY id DESC LIMIT 30");
            $s->execute([$like,$like,$like,$like,$like]);
            $out['contacts'] = $s->fetchAll();
        } catch (Throwable $e) {} }

        // Stok — DÜZELTİLDİ: sku/description yerine gerçek kolonlar product_code/barcode/notes
        if (function_exists('user_can') && user_can('stock')) { try {
            $s = $pdo->prepare("SELECT id,name,quantity,unit,sale_price FROM stock_items
                WHERE name LIKE ? OR product_code LIKE ? OR barcode LIKE ? OR notes LIKE ?
                ORDER BY id DESC LIMIT 30");
            $s->execute([$like,$like,$like,$like]);
            $out['stock'] = $s->fetchAll();
        } catch (Throwable $e) {} }

        // Personel — DÜZELTİLDİ: title/department yerine gerçek kolonlar role/work_type.
        // 2026-07-04 KÖK NEDEN BULUNDU: kolonlar/yetki kontrolü/render zaten doğruydu (hepsi
        // kontrol edildi) — asıl sorun "personel" kelimesinin kendisinin arandığında (bir isim
        // değil, MODÜL ADININ kendisi) hiçbir personel kaydının name/role/work_type/phone/email
        // alanında bu kelime geçmediği için 0 sonuç dönmesiydi. "çek"/"teklif"/"belge"/"rapor" için
        // zaten var olan "modül adı yazılırsa son kayıtlar listelensin" deseni personelde YOKTU —
        // aynı desen burada da uygulandı.
        $personnelModuleMatch = in_array($qNorm, ['personel','personeller','personelim','çalışan','çalışanlar'], true);
        if (function_exists('user_can') && user_can('personnel')) { try {
            $sql = $personnelModuleMatch
                ? "SELECT id,name,role,phone,work_type,email FROM personnel ORDER BY id DESC LIMIT 20"
                : "SELECT id,name,role,phone,work_type,email FROM personnel
                    WHERE name LIKE ? OR role LIKE ? OR work_type LIKE ? OR phone LIKE ? OR email LIKE ?
                    ORDER BY id DESC LIMIT 20";
            $s = $pdo->prepare($sql);
            $s->execute($personnelModuleMatch ? [] : [$like,$like,$like,$like,$like]);
            $out['personnel'] = $s->fetchAll();
        } catch (Throwable $e) {} }

        // Finans Hesapları (Kasa/Banka/Kredi Kartı/POS)
        if (function_exists('user_can') && user_can('finance')) { try {
            $s = $pdo->prepare("SELECT id,name,account_type,bank_name,iban,current_balance FROM finance_accounts
                WHERE name LIKE ? OR account_type LIKE ? OR bank_name LIKE ? OR iban LIKE ?
                ORDER BY id DESC LIMIT 20");
            $s->execute([$like,$like,$like,$like]);
            $out['accounts'] = $s->fetchAll();
        } catch (Throwable $e) {} }

        // Finans Hareketleri (tahsilat/ödeme/transfer)
        if (function_exists('user_can') && user_can('finance')) { try {
            $s = $pdo->prepare("SELECT m.id, m.direction, m.amount, m.payment_channel, m.status, m.movement_date,
                    m.description, c.name contact_name
                FROM finance_movements m LEFT JOIN contacts c ON c.id=m.contact_id
                WHERE m.description LIKE ? OR m.payment_channel LIKE ? OR c.name LIKE ?
                ORDER BY m.id DESC LIMIT 20");
            $s->execute([$like,$like,$like]);
            $out['movements'] = $s->fetchAll();
        } catch (Throwable $e) {} }

        // Çek / Senet — Finans modülünün bir parçası. "Çek"/"Senet" TÜR adının kendisi yazılırsa
        // (numara/banka/cari alanında geçmese bile) o türdeki tüm kayıtlar listelensin — 2026-07-02
        // kullanıcı bildirimi: "aramada çek'i göremedim" (sadece alan içeriği aranıyordu, tür adı değil).
        // NOT: $qNorm artık en üstte hesaplanıyor (personel modül-adı kısayoluyla paylaşılıyor).
        $typeMatch = null;
        if (in_array($qNorm, ['çek','cek'], true)) $typeMatch = 'cek';
        elseif (in_array($qNorm, ['senet','senedi','senetler'], true)) $typeMatch = 'senet';
        if (function_exists('user_can') && user_can('finance')) { try {
            $sql = "SELECT k.id,k.type,k.number,k.amount,k.due_date,k.status,k.bank_name,c.name contact_name
                FROM checks_notes k LEFT JOIN contacts c ON c.id=k.contact_id
                WHERE k.number LIKE ? OR k.bank_name LIKE ? OR c.name LIKE ?".($typeMatch ? " OR k.type=?" : "")."
                ORDER BY k.id DESC LIMIT 20";
            $s = $pdo->prepare($sql);
            $params = [$like,$like,$like];
            if ($typeMatch) $params[] = $typeMatch;
            $s->execute($params);
            $out['checks'] = $s->fetchAll();
        } catch (Throwable $e) {} }

        // Teklif — "çek"/"senet" ile aynı desen (2026-07-03 kullanıcı bildirimi: "teklif" yazınca
        // 0 sonuç dönüyordu, çünkü bu genel kelime hiçbir teklif no/müşteri adıyla eşleşmiyordu).
        // Modül adının kendisi yazılırsa (içerikte geçmese bile) son teklifler listelensin.
        $quoteModuleMatch = in_array($qNorm, ['teklif','teklifler','teklifi'], true);
        if (function_exists('user_can') && user_can('teklif')) { try {
            $sql = $quoteModuleMatch
                ? "SELECT id,quote_no,customer_name,total,status FROM quotes ORDER BY id DESC LIMIT 20"
                : "SELECT id,quote_no,customer_name,total,status FROM quotes WHERE quote_no LIKE ? OR customer_name LIKE ? ORDER BY id DESC LIMIT 20";
            $s = $pdo->prepare($sql);
            $s->execute($quoteModuleMatch ? [] : [$like,$like]);
            $out['quotes'] = $s->fetchAll();
        } catch (Throwable $e) {} }

        // Ticari Belgeler (Alış/Satış — trade_documents) — "çek"/"teklif" ile aynı desen, daha önce
        // arama kapsamında HİÇ yoktu (2026-07-03 modül-zinciri denetiminde bulundu). Modül adı
        // ("belge"/"belgeler") yazılırsa son belgeler, yoksa belge no/cari adına göre eşleşme.
        $docModuleMatch = in_array($qNorm, ['belge','belgeler','ticari belge','fatura','faturalar'], true);
        if (function_exists('user_can') && user_can('contacts')) { try {
            $sql = "SELECT d.id,d.document_no,d.document_type,d.grand_total,d.paid_amount,d.status,d.document_date,c.name contact_name
                FROM trade_documents d LEFT JOIN contacts c ON c.id=d.contact_id
                WHERE ".($docModuleMatch ? "1=1" : "d.document_no LIKE ? OR c.name LIKE ?")."
                ORDER BY d.id DESC LIMIT 20";
            $s = $pdo->prepare($sql);
            $s->execute($docModuleMatch ? [] : [$like,$like]);
            $out['documents'] = $s->fetchAll();
        } catch (Throwable $e) {} }

        // İş Dosyaları (job_files) — 2026-07-04 kapsam genişletme: 'documents' anahtarı sadece
        // trade_documents'i (ticari belge) kapsıyordu, işlere yüklenen gerçek dosyalar (çizim, teslim
        // fişi vb.) arama dışındaydı. Ayrı anahtar (`files`) — trade_documents ile şeması/render'ı
        // tamamen farklı (dosya adı/tipi vs. tutar/durum), aynı diziye karıştırılmadı. 'jobs' yetkisiyle
        // korunuyor (iş listesiyle aynı görünürlük sınırı).
        if (function_exists('user_can') && user_can('jobs')) { try {
            $s = $pdo->prepare("SELECT jf.id, jf.job_id, jf.original_name, jf.file_type, jf.approval_status, jf.created_at,
                    j.job_no, j.title job_title
                FROM job_files jf LEFT JOIN jobs j ON j.id=jf.job_id
                WHERE jf.original_name LIKE ? OR j.job_no LIKE ? OR j.title LIKE ?
                ORDER BY jf.id DESC LIMIT 20");
            $s->execute([$like,$like,$like]);
            $out['files'] = $s->fetchAll();
        } catch (Throwable $e) {} }

        // Görevler (tasks) — 'jobs' tablosundan AYRI (bkz. PROJECT_RULES.md kavram standardı: "İş
        // Emirleri" ≠ "Görevlerim"). "Görev" modül adının kendisi yazılırsa son görevler listelensin
        // (aynı desen). 'tasks' yetkisiyle korunuyor.
        $taskModuleMatch = in_array($qNorm, ['görev','görevler','görevlerim'], true);
        if (function_exists('user_can') && user_can('tasks')) { try {
            $sql = "SELECT t.id, t.title, t.description, t.status, t.priority, t.due_date, t.job_id,
                    j.job_no, p.name personnel_name
                FROM tasks t LEFT JOIN jobs j ON j.id=t.job_id LEFT JOIN personnel p ON p.id=t.personnel_id
                WHERE ".($taskModuleMatch ? "1=1" : "t.title LIKE ? OR t.description LIKE ? OR p.name LIKE ?")."
                ORDER BY t.id DESC LIMIT 20";
            $s = $pdo->prepare($sql);
            $s->execute($taskModuleMatch ? [] : [$like,$like,$like]);
            $out['tasks'] = $s->fetchAll();
        } catch (Throwable $e) {} }

        // Kullanıcılar (app_users / users.php) — sadece 'users' (Kullanıcı/Yetki) yetkisi olanlar
        // başka kullanıcıların telefon/e-posta bilgisini arama sonucunda görebilir (users.php'nin
        // kendisiyle aynı görünürlük sınırı — page_module_map() 'users.php'=>'users').
        if (function_exists('user_can') && user_can('users')) { try {
            $s = $pdo->prepare("SELECT id,username,full_name,phone,email,role FROM app_users
                WHERE username LIKE ? OR full_name LIKE ? OR phone LIKE ? OR email LIKE ?
                ORDER BY id DESC LIMIT 20");
            $s->execute([$like,$like,$like,$like]);
            $out['users'] = $s->fetchAll();
        } catch (Throwable $e) {} }

        // Notlarım (personal_notes) — KİŞİSEL alan, `tasks` tablosuyla karışmaz (bkz. 037_personal_notes
        // migration notu: "personel görmesin"). Modül seviyesinde bir user_can() izni YOK (notes.php da
        // sadece require_login() istiyor, herkes kendi notunu görebiliyor) — güvenlik sınırı burada
        // `user_id=?` filtresi: oturum sahibi SADECE KENDİ notunu arayabilir, başkasının notu asla
        // görünmez (IDOR'a kapalı).
        if (function_exists('current_user')) { try {
            $meU = current_user();
            $meId = (int)($meU['id'] ?? 0);
            if ($meId > 0) {
                $s = $pdo->prepare("SELECT id,title,note,status,due_date FROM personal_notes
                    WHERE user_id=? AND (title LIKE ? OR note LIKE ?)
                    ORDER BY id DESC LIMIT 20");
                $s->execute([$meId,$like,$like]);
                $out['notes'] = $s->fetchAll();
            }
        } catch (Throwable $e) {} }

        // Mesajlar (internal_messages) — modül seviyesinde izin yok (messages.php da herkese açık,
        // sadece require_login()); güvenlik sınırı burada da SAHİPLİK/ÜYELİK: 1-1 mesajlarda oturum
        // sahibi gönderen ya da alıcı olmalı, grup mesajlarında ise chat_thread_members'ta üye olmalı
        // — başka bir kullanıcının dahil olmadığı hiçbir mesaj arama sonucunda görünmez (IDOR'a kapalı).
        if (function_exists('current_user')) { try {
            $meU = current_user();
            $meId = (int)($meU['id'] ?? 0);
            if ($meId > 0) {
                $s = $pdo->prepare("SELECT m.id, m.message, m.created_at, m.thread_id, m.sender_user_id, m.receiver_user_id,
                        su.full_name sender_name, su.username sender_username,
                        ru.full_name receiver_name, ru.username receiver_username,
                        th.title thread_title
                    FROM internal_messages m
                    LEFT JOIN app_users su ON su.id=m.sender_user_id
                    LEFT JOIN app_users ru ON ru.id=m.receiver_user_id
                    LEFT JOIN chat_threads th ON th.id=m.thread_id
                    WHERE m.message LIKE ?
                        AND (
                            (m.thread_id IS NULL AND (m.sender_user_id=? OR m.receiver_user_id=?))
                            OR (m.thread_id IS NOT NULL AND EXISTS(
                                SELECT 1 FROM chat_thread_members cm WHERE cm.thread_id=m.thread_id AND cm.user_id=?
                            ))
                        )
                    ORDER BY m.id DESC LIMIT 20");
                $s->execute([$like,$meId,$meId,$meId]);
                $rows = $s->fetchAll();
                foreach ($rows as &$mrow) {
                    if ($mrow['thread_id']) {
                        $mrow['with_label'] = $mrow['thread_title'] ?: 'Grup';
                        $mrow['with_user_id'] = null;
                    } else {
                        $otherIsSender = ((int)$mrow['sender_user_id'] !== $meId);
                        $mrow['with_label'] = $otherIsSender
                            ? ($mrow['sender_name'] ?: $mrow['sender_username'])
                            : ($mrow['receiver_name'] ?: $mrow['receiver_username']);
                        $mrow['with_user_id'] = $otherIsSender ? (int)$mrow['sender_user_id'] : (int)$mrow['receiver_user_id'];
                    }
                }
                unset($mrow);
                $out['messages'] = $rows;
            }
        } catch (Throwable $e) {} }

        // Sayfa kısayolları — veri satırı değil, doğrudan bir ekrana yönlendirme. 2026-07-03'te
        // 4 elle-yazılmış girdiyle başladı (ekstre/rapor/muhasebe/takvim); FAZ 2B-ii-R/2b'de TÜM
        // nav_taxonomy() kataloğuna (Türkçe-normalize + prefix + kısmi + fuzzy eşleşmeli) genişledi
        // — bkz. search_module_shortcuts(). Web/mobil URL'leri farklı olduğu için burada sadece
        // anahtar/etiket/ikon döner, gerçek href'i çağıran sayfa kendi platformuna göre kurar.
        $out['pages'] = search_module_shortcuts(
            $q,
            function($p) { return function_exists('user_can') && user_can($p); },
            function_exists('is_admin') && is_admin(),
            $platform
        );

        return $out;
    }
}

if (!function_exists('search_total_count')) {
    function search_total_count($result) {
        $n = 0;
        foreach ($result as $rows) $n += count($rows);
        return $n;
    }
}
