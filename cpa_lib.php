<?php
/* P1 — CUSTOMER PROCUREMENT ALLOCATION (CPA) / Müşteriye Özel Tedarik Takibi (2026-07-18,
 * Product Owner kararı). Her müşteri+ürün için tercih edilen tedarikçi(ler)i öncelik sırasıyla
 * saklar, satın alma sırasında "akıllı öneri" (zorunlu değil) üretir. Şema: database/migrations/
 * 045_cpa_preferences.sql. Kayıtlar SİLİNMEZ — kaldırma status='Pasif' ile yapılır, her değişiklik
 * audit_lib.php::audit_log() ile 'cpa_preferences' tablosuna karşı loglanır (geçmiş budur, ayrı bir
 * versiyon tablosu icat edilmedi). Web + mobil ortak.
 */

function cpa_install(){
    try{
        db()->exec("CREATE TABLE IF NOT EXISTS cpa_preferences (
          id INT AUTO_INCREMENT PRIMARY KEY,
          customer_id INT NOT NULL,
          stock_item_id INT NOT NULL,
          supplier_id INT NOT NULL,
          priority INT NOT NULL DEFAULT 1,
          is_default TINYINT(1) NOT NULL DEFAULT 0,
          status VARCHAR(20) NOT NULL DEFAULT 'Aktif',
          notes TEXT NULL,
          created_by INT NULL,
          created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
          updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          UNIQUE KEY uniq_customer_product_supplier (customer_id, stock_item_id, supplier_id),
          KEY idx_customer_product (customer_id, stock_item_id),
          KEY idx_supplier (supplier_id),
          KEY idx_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }catch(Throwable $e){}
}

// Madde 8 — "Satış ve Satın Alma yöneticileri düzenleyebilsin, diğerleri sadece görüntüleyebilsin."
// 'stock' modül yetkisi zaten hem sales.php hem purchase.php'yi kapsıyor (page_module_map) — aynı
// yetkiyi + can_edit_delete() (var olan kaydı değiştirme genel yetkisi) "yönetici" eşiği olarak
// kullanıyoruz, yeni bir izin kavramı icat edilmedi.
function cpa_can_edit(){
    return is_admin() || (function_exists('user_can') && user_can('stock') && function_exists('can_edit_delete') && can_edit_delete());
}
function cpa_can_view(){
    return is_admin() || (function_exists('user_can') && (user_can('stock') || user_can('contacts')));
}

// Bir müşterinin tüm CPA tercihleri (varsayılan olarak sadece Aktif olanlar) — ürün/tedarikçi
// adlarıyla birlikte, contact_view.php "CPA" bölümü için.
function cpa_list_for_customer($pdo, $customerId, $includeInactive=false){
    cpa_install();
    try{
        $sql="SELECT cp.*, si.name AS product_name, si.unit AS product_unit, s.name AS supplier_name
              FROM cpa_preferences cp
              LEFT JOIN stock_items si ON si.id=cp.stock_item_id
              LEFT JOIN contacts s ON s.id=cp.supplier_id
              WHERE cp.customer_id=?".($includeInactive?'':" AND cp.status='Aktif'")."
              ORDER BY si.name, cp.is_default DESC, cp.priority ASC";
        $st=$pdo->prepare($sql);
        $st->execute([(int)$customerId]);
        return $st->fetchAll();
    }catch(Throwable $e){ return []; }
}

// Bir ürünün hangi müşteriler için özel tedarikçi tanımı içerdiği — product_view.php
// "CPA Kullanımı" bölümü için.
function cpa_list_for_product($pdo, $stockItemId, $includeInactive=false){
    cpa_install();
    try{
        $sql="SELECT cp.*, c.name AS customer_name, s.name AS supplier_name
              FROM cpa_preferences cp
              LEFT JOIN contacts c ON c.id=cp.customer_id
              LEFT JOIN contacts s ON s.id=cp.supplier_id
              WHERE cp.stock_item_id=?".($includeInactive?'':" AND cp.status='Aktif'")."
              ORDER BY c.name, cp.is_default DESC, cp.priority ASC";
        $st=$pdo->prepare($sql);
        $st->execute([(int)$stockItemId]);
        return $st->fetchAll();
    }catch(Throwable $e){ return []; }
}

// Madde 2 — satın alma sırasında ürün seçildiğinde "akıllı öneri". purchase.php'de tek bir
// müşteri alanı olmadığı için (o ekran doğrudan tedarikçiden alış yapar) ürün bazlı, TÜM
// müşterilerdeki aktif tercihleri döner — çağıran taraf (AJAX) bunu bilgilendirici bir ipucu
// olarak gösterir, hiçbir alanı otomatik doldurmaz (Product Owner: "öneri zorunlu değil").
function cpa_suggest_by_product($pdo, $stockItemId){
    cpa_install();
    try{
        $st=$pdo->prepare("SELECT cp.*, c.name AS customer_name, s.name AS supplier_name
              FROM cpa_preferences cp
              LEFT JOIN contacts c ON c.id=cp.customer_id
              LEFT JOIN contacts s ON s.id=cp.supplier_id
              WHERE cp.stock_item_id=? AND cp.status='Aktif'
              ORDER BY cp.is_default DESC, cp.priority ASC LIMIT 5");
        $st->execute([(int)$stockItemId]);
        return $st->fetchAll();
    }catch(Throwable $e){ return []; }
}

// Belirli bir müşteri+ürün için tercih sırası — ileride (job/sipariş akışında gerçek müşteri
// bağlamı olan bir ekran eklenirse) doğrudan kullanılabilir, bugün purchase.php'de KULLANILMIYOR
// (o ekranda müşteri bağlamı yok, bkz. cpa_suggest_by_product()).
function cpa_suggest($pdo, $customerId, $stockItemId){
    cpa_install();
    try{
        $st=$pdo->prepare("SELECT cp.*, s.name AS supplier_name
              FROM cpa_preferences cp LEFT JOIN contacts s ON s.id=cp.supplier_id
              WHERE cp.customer_id=? AND cp.stock_item_id=? AND cp.status='Aktif'
              ORDER BY cp.is_default DESC, cp.priority ASC");
        $st->execute([(int)$customerId,(int)$stockItemId]);
        return $st->fetchAll();
    }catch(Throwable $e){ return []; }
}

// Yeni tercih ekler VEYA (customer,product,supplier) üçlüsü zaten varsa (Aktif ya da Pasif)
// günceller — aynı üçlü için tekrar satır açılmaz (UNIQUE KEY), var olan kayıt canlandırılır/
// güncellenir. is_default=1 verilirse aynı müşteri+ürün için diğer tüm satırlar 0'a çekilir
// (DB seviyesinde kısmi UNIQUE index MySQL 5.7'de yok, uygulama seviyesinde garanti edilir).
// Her değişiklik audit_log'a yazılır (eski/yeni durum karşılaştırması geçmiş kaydı görevi görür).
// @throws Exception yetkisiz erişim veya satın alma miktarı/gerekli alan eksikse
function cpa_upsert($pdo, $userId, $customerId, $stockItemId, $supplierId, $priority, $isDefault, $notes){
    if(!cpa_can_edit()) throw new Exception('Bu işlem için yönetici yetkisi gerekir.');
    $customerId=(int)$customerId; $stockItemId=(int)$stockItemId; $supplierId=(int)$supplierId;
    if(!$customerId || !$stockItemId || !$supplierId) throw new Exception('Müşteri, ürün ve tedarikçi seçimi zorunlu.');
    $priority=max(1,(int)$priority);
    $isDefault=$isDefault?1:0;
    $notes=trim((string)$notes);
    cpa_install();

    $ex=$pdo->prepare("SELECT * FROM cpa_preferences WHERE customer_id=? AND stock_item_id=? AND supplier_id=?");
    $ex->execute([$customerId,$stockItemId,$supplierId]);
    $old=$ex->fetch();

    if($isDefault){
        $pdo->prepare("UPDATE cpa_preferences SET is_default=0 WHERE customer_id=? AND stock_item_id=?")
            ->execute([$customerId,$stockItemId]);
    }

    if($old){
        $pdo->prepare("UPDATE cpa_preferences SET priority=?, is_default=?, status='Aktif', notes=? WHERE id=?")
            ->execute([$priority,$isDefault,$notes,$old['id']]);
        if(function_exists('audit_log')) audit_log($userId,'update','cpa_preferences',$old['id'],$old,['priority'=>$priority,'is_default'=>$isDefault,'status'=>'Aktif','notes'=>$notes]);
        return (int)$old['id'];
    }

    $pdo->prepare("INSERT INTO cpa_preferences(customer_id,stock_item_id,supplier_id,priority,is_default,status,notes,created_by) VALUES(?,?,?,?,?,'Aktif',?,?)")
        ->execute([$customerId,$stockItemId,$supplierId,$priority,$isDefault,$notes,$userId?:null]);
    $newId=(int)$pdo->lastInsertId();
    if(function_exists('audit_log')) audit_log($userId,'create','cpa_preferences',$newId,null,['customer_id'=>$customerId,'stock_item_id'=>$stockItemId,'supplier_id'=>$supplierId,'priority'=>$priority,'is_default'=>$isDefault]);
    return $newId;
}

// Madde 4 — "CPA geçmişi saklansın, tedarikçi değişiklikleri silinmesin" — bu yüzden DELETE değil,
// sadece status değişimi (Aktif<->Pasif). $id her zaman DB'den doğrulanır (IDOR'a kapalı).
function cpa_set_status($pdo, $userId, $id, $status){
    if(!cpa_can_edit()) throw new Exception('Bu işlem için yönetici yetkisi gerekir.');
    $id=(int)$id;
    $status=in_array($status,['Aktif','Pasif'],true)?$status:'Pasif';
    $cur=$pdo->prepare("SELECT * FROM cpa_preferences WHERE id=?"); $cur->execute([$id]); $old=$cur->fetch();
    if(!$old) throw new Exception('Kayıt bulunamadı.');
    $pdo->prepare("UPDATE cpa_preferences SET status=? WHERE id=?")->execute([$status,$id]);
    if(function_exists('audit_log')) audit_log($userId,'update','cpa_preferences',$id,['status'=>$old['status']],['status'=>$status]);
}
