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
     * @return array{jobs:array,contacts:array,stock:array,personnel:array,accounts:array,movements:array,checks:array,quotes:array}
     */
    function search_run(PDO $pdo, $q) {
        $q = trim((string)$q);
        $out = ['jobs'=>[], 'contacts'=>[], 'stock'=>[], 'personnel'=>[], 'accounts'=>[], 'movements'=>[], 'checks'=>[], 'quotes'=>[]];
        if ($q === '') return $out;
        $like = '%'.$q.'%';

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

        // Personel — DÜZELTİLDİ: title/department yerine gerçek kolonlar role/work_type
        if (function_exists('user_can') && user_can('personnel')) { try {
            $s = $pdo->prepare("SELECT id,name,role,phone,work_type,email FROM personnel
                WHERE name LIKE ? OR role LIKE ? OR work_type LIKE ? OR phone LIKE ? OR email LIKE ?
                ORDER BY id DESC LIMIT 20");
            $s->execute([$like,$like,$like,$like,$like]);
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
        $qNorm = mb_strtolower($q, 'UTF-8');
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
