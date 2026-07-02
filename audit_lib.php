<?php
/* Değişmez (immutable) denetim günlüğü — kim-ne-zaman-ne-değiştirdi kaydı.
 * Kritik finansal işlemler (hesap/hareket düzenle-sil) ve muhasebe gider/gelir güncellemesi için.
 * Web + Mobil ortak. Audit log yazımı asla ana işlemi bozmasın diye try/catch ile sarılı. */

// Audit log tablosunu kur (migration konusunda başarısız olduysa runtime oluştur)
function audit_install(){
    try{
        db()->exec("CREATE TABLE IF NOT EXISTS audit_log (
          id INT AUTO_INCREMENT PRIMARY KEY,
          user_id INT NULL COMMENT 'app_users.id',
          action VARCHAR(20) NOT NULL COMMENT 'create/update/delete',
          table_name VARCHAR(80) NOT NULL COMMENT 'etkilenen tablo adı',
          record_id INT NULL COMMENT 'etkilenen satırın PK',
          old_value LONGTEXT NULL COMMENT 'güncelleme/silme öncesi JSON',
          new_value LONGTEXT NULL COMMENT 'güncelleme/yeni kayıt sonrası JSON',
          ip_address VARCHAR(45) NULL COMMENT 'müşteri IP'si',
          created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          KEY idx_user_action (user_id, action),
          KEY idx_table_record (table_name, record_id),
          KEY idx_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }catch(Throwable $e){}
}

// Denetim günlüğüne kayıt ekle. Eski/yeni değerler JSON string'e dönüştürülür.
// $oldValue/$newValue: null = girdi yok; array veya obje = json_encode ile sakla; string = olduğu gibi.
function audit_log($userId, $action, $tableName, $recordId, $oldValue=null, $newValue=null){
    try{
        audit_install();
        $ipAddr = $_SERVER['REMOTE_ADDR'] ?? $_SERVER['HTTP_CF_CONNECTING_IP'] ?? null;

        // Değerleri JSON string'e çevir (varsa)
        $oldJson = null;
        $newJson = null;
        if($oldValue !== null){
            if(is_array($oldValue) || is_object($oldValue)){
                $oldJson = json_encode($oldValue, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }else{
                $oldJson = (string)$oldValue;
            }
        }
        if($newValue !== null){
            if(is_array($newValue) || is_object($newValue)){
                $newJson = json_encode($newValue, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }else{
                $newJson = (string)$newValue;
            }
        }

        $stmt = db()->prepare(
            "INSERT INTO audit_log (user_id, action, table_name, record_id, old_value, new_value, ip_address)
             VALUES (?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $userId ? (int)$userId : null,
            $action,
            $tableName,
            $recordId ? (int)$recordId : null,
            $oldJson,
            $newJson,
            $ipAddr
        ]);
    }catch(Throwable $e){
        // Audit log yazımı başarısız olsa bile ana işlemi bozmasın
    }
}

// Denetim günlüğünü listele (admin panel, tablo/tarih/kullanıcı filtresi ile)
function audit_log_list($pdo, $limit=200, $tableFilter='', $userFilter='', $dateFrom='', $dateTo=''){
    try{
        $sql = "SELECT * FROM audit_log WHERE 1=1";
        $params = [];

        if($tableFilter !== ''){
            $sql .= " AND table_name = ?";
            $params[] = $tableFilter;
        }
        if($userFilter !== ''){
            $sql .= " AND user_id = ?";
            $params[] = (int)$userFilter;
        }
        if($dateFrom !== ''){
            $sql .= " AND DATE(created_at) >= ?";
            $params[] = $dateFrom;
        }
        if($dateTo !== ''){
            $sql .= " AND DATE(created_at) <= ?";
            $params[] = $dateTo;
        }

        $sql .= " ORDER BY created_at DESC LIMIT " . (int)$limit;

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }catch(Throwable $e){
        return [];
    }
}

// Tüm tablolar ve onlardan kaçar kayıt denetim günlüğüne geçti
function audit_log_table_stats($pdo){
    try{
        $stats = [];
        $result = $pdo->query("SELECT table_name, COUNT(*) cnt FROM audit_log GROUP BY table_name ORDER BY cnt DESC");
        foreach($result->fetchAll() as $row){
            $stats[$row['table_name']] = (int)$row['cnt'];
        }
        return $stats;
    }catch(Throwable $e){
        return [];
    }
}

// Tek bir kaydın denetim tarihçesi
function audit_log_record_history($pdo, $tableName, $recordId){
    try{
        $stmt = $pdo->prepare("SELECT * FROM audit_log WHERE table_name=? AND record_id=? ORDER BY created_at DESC");
        $stmt->execute([$tableName, (int)$recordId]);
        return $stmt->fetchAll();
    }catch(Throwable $e){
        return [];
    }
}
?>
