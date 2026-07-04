<?php
/* Ortak kayıt silme — varsayılan admin/yönetici, bazı türlerde (bkz. $editDeleteTypes)
 * 'edit_delete' yetkisi verilmiş personel de yapabilir. POST + onay ile çağrılır.
 * Kullanım: <form method="post" action="sil.php"> t=<tür> id=<id> </form>
 * İlişkili alt kayıtları da temizler. Whitelist dışına çıkamaz (SQL injection güvenli). */
require_once __DIR__.'/boot.php';
require_login();
$pdo=db();

// tür => [ana tablo, dönüş sayfası, [alt tablo=>foreign key, ...]]
$map=[
    'contact'   => ['contacts',          'contacts.php',          ['contact_representatives'=>'contact_id']],
    'job'       => ['jobs',              'jobs.php',              ['job_stages'=>'job_id','job_files'=>'job_id','job_notes'=>'job_id','tasks'=>'job_id']],
    'quote'     => ['quotes',            'teklif.php',            ['quote_items'=>'quote_id']],
    'task'      => ['tasks',             'tasks.php',             []],
    'product'   => ['stock_items',       'stock.php',             ['stock_movements'=>'stock_item_id']],
    'personnel' => ['personnel',         'personnel.php',         ['personnel_devices'=>'personnel_id']],
    'finance'   => ['finance_movements', 'finance.php',           []],
    'account'   => ['finance_accounts',  'finance_accounts.php',  []],
    'accounting' => ['accounting_entries', 'accounting.php',      []],
    'product_category' => ['product_categories', 'product_categories.php', []],
    'sale' => ['finance_movements', 'sales.php', []],
];
// Bu türlerde silme, admin dışında 'edit_delete' yetkisi verilmiş personele de açık (kademeli
// olarak genişletiliyor — bkz. memory/features.md). Listede olmayan türler admin-only kalır.
$editDeleteTypes = ['account','finance','task','accounting','product_category','sale'];

$t  = $_POST['t'] ?? '';
$id = (int)($_POST['id'] ?? 0);
if($_SERVER['REQUEST_METHOD']!=='POST' || !isset($map[$t]) || $id<1){ redirect('dashboard.php'); }
$allowed = in_array($t,$editDeleteTypes,true) ? can_edit_delete() : is_admin();
if(!$allowed){ http_response_code(403); exit('Bu işlem için yetkiniz yok.'); }

list($table,$back,$children) = $map[$t];

// Finans hesapları: hareketlerde kullanılmışsa kalıcı silme referans bütünlüğünü bozar —
// finance_lib.php üzerinden kontrollü sil (kullanılıyorsa pasife al, değilse kalıcı sil).
if($t==='account'){
    require_once __DIR__.'/finance_lib.php';
    try{
        $res=finance_account_delete($pdo,$id);
        try{ if(function_exists('activity_log')) activity_log('Silme',$res['msg'],$table.' #'.$id,'','admin',null,$back,'🗑'); }catch(Throwable $e){}
    }catch(Throwable $e){ exit('Silinemedi: '.htmlspecialchars($e->getMessage())); }
    redirect($back.'?deleted=1');
}

// Finans hareketleri (tahsilat/ödeme): silmeden önce ilgili hesabın bakiyesi geri alınmalı ve
// satış/belge/transfer'den otomatik oluşan satırlar (diğer modüllerle senkron) burada silinemez —
// finance_lib.php üzerinden kontrollü sil.
if($t==='finance'){
    require_once __DIR__.'/finance_lib.php';
    try{
        $res=finance_movement_delete($pdo,$id);
        if(!$res['ok']) exit('Silinemedi: '.htmlspecialchars($res['msg']));
        try{ if(function_exists('activity_log')) activity_log('Silme',$res['msg'],$table.' #'.$id,'','admin',null,$back,'🗑'); }catch(Throwable $e){}
    }catch(Throwable $e){ exit('Silinemedi: '.htmlspecialchars($e->getMessage())); }
    redirect($back.'?deleted=1');
}

// Satış (satış hareketi silme): stoku geri koy, finans hareketini sil/geri al —
// stock_lib.php üzerinden kontrollü sil.
if($t==='sale'){
    require_once __DIR__.'/stock_lib.php';
    try{
        $res=stock_reverse_sale($pdo,$id);
        if(!$res['ok']) exit('Silinemedi: '.htmlspecialchars($res['message']));
        try{ if(function_exists('activity_log')) activity_log('Silme',$res['message'],$table.' #'.$id,'','admin',null,'sales.php','🗑'); }catch(Throwable $e){}
    }catch(Throwable $e){ exit('Silinemedi: '.htmlspecialchars($e->getMessage())); }
    redirect('sales.php?deleted=1');
}

// Görev silme: SOFT DELETE (tasks_lib.php) — hiçbir görev fiziksel silinmez, deleted_at ile
// listelerden gizlenir (kullanıcı isteği, 2026-07-04). tasks.php'nin JOIN'lediği job_id foreign
// key'i etkilenmez, alt kayıt (children) temizliği burada gerekmiyor.
if($t==='task'){
    require_once __DIR__.'/tasks_lib.php';
    try{
        $me=(int)($_SESSION['user']['id']??0);
        task_soft_delete($pdo,$id,$me);
    }catch(Throwable $e){ exit('Silinemedi: '.htmlspecialchars($e->getMessage())); }
    redirect($back.'?deleted=1');
}

// Muhasebe kaydı silme: accounting_lib.php üzerinden kontrollü sil (hesap bakiyesi geri alınır).
if($t==='accounting'){
    require_once __DIR__.'/accounting_lib.php';
    try{
        $res=accounting_entry_delete($pdo,$id);
        if(!$res['ok']) exit('Silinemedi: '.htmlspecialchars($res['msg']));
        try{ if(function_exists('activity_log')) activity_log('Silme',$res['msg'],$table.' #'.$id,'','admin',null,$back,'🗑'); }catch(Throwable $e){}
    }catch(Throwable $e){ exit('Silinemedi: '.htmlspecialchars($e->getMessage())); }
    redirect($back.'?deleted=1');
}

// Ürün kategorisi silme: kulllanımda mı kontrol et, kullanılıyorsa pasife al, değilse kalıcı sil.
if($t==='product_category'){
    try{
        // Kategori kaç ürün ile ilişkili?
        $s=$pdo->prepare("SELECT COUNT(*) c FROM stock_items WHERE category_id=?");
        $s->execute([$id]);
        $count=(int)$s->fetch()['c'];
        if($count>0){
            // Kullanılıyor: pasife al
            $pdo->prepare("UPDATE product_categories SET active=0 WHERE id=?")->execute([$id]);
            $msg='Kategori '.$count.' ürün ile ilişkili olduğu için kalıcı silinemedi, pasife alındı.';
        }else{
            // Kullanılmıyor: kalıcı sil
            $pdo->prepare("DELETE FROM product_categories WHERE id=?")->execute([$id]);
            $msg='Kategori silindi.';
        }
        try{ if(function_exists('activity_log')) activity_log('Silme',$msg,$table.' #'.$id,'','admin',null,$back,'🗑'); }catch(Throwable $e){}
    }catch(Throwable $e){ exit('Silinemedi: '.htmlspecialchars($e->getMessage())); }
    redirect($back.'?deleted=1');
}

// GÜVENLİK (2026-07-03 denetiminde bulundu): personel silinirken bağlı app_users hesabı pasife
// alınmıyordu — silinen personelin kullanıcı adı/şifresi (veya "beni hatırla" çerezi) hâlâ geçerli
// kalıp giriş yapabiliyordu. Personel silinmeden ÖNCE bağlı hesabı pasifleştir.
if($t==='personnel'){
    try{ $pdo->prepare("UPDATE app_users SET active=0 WHERE personnel_id=?")->execute([$id]); }catch(Throwable $e){}
}

try{
    try{ $pdo->exec("SET FOREIGN_KEY_CHECKS=0"); }catch(Throwable $e){}
    foreach($children as $ct=>$cf){ try{ $pdo->prepare("DELETE FROM `$ct` WHERE `$cf`=?")->execute([$id]); }catch(Throwable $e){} }
    $pdo->prepare("DELETE FROM `$table` WHERE id=?")->execute([$id]);
    try{ $pdo->exec("SET FOREIGN_KEY_CHECKS=1"); }catch(Throwable $e){}
    try{ if(function_exists('activity_log')) activity_log('Silme','Kayıt silindi',$table.' #'.$id,'','admin',null,$back,'🗑'); }catch(Throwable $e){}
}catch(Throwable $e){ exit('Silinemedi: '.htmlspecialchars($e->getMessage())); }
redirect($back.'?deleted=1');
