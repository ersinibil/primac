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
    // FINANCE CRUD UX PATCH 001 (2026-07-12): contact_view.php/finance_account_view.php gibi
    // ekranlardan silindiğinde kullanıcı geldiği ekrana dönsün — finance_return_url() sadece
    // bilinen ekran adı + tamsayı id kabul eder (open redirect yok, ham URL asla kullanılmaz).
    $returnUrl = finance_return_url($_POST['return_context'] ?? '', $_POST['return_ref'] ?? '', $back);
    redirect($returnUrl.(strpos($returnUrl,'?')!==false?'&':'?').'deleted=1');
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

// Cari silme: bağlı finans/iş/belge/teklif/whatsapp kaydı varsa kalıcı silmez, pasife alır
// (contacts_lib.php::contact_delete_or_deactivate() — sil.php + mobile/contact_view.php ortak).
if($t==='contact'){
    require_once __DIR__.'/contacts_lib.php';
    try{
        $res=contact_delete_or_deactivate($pdo,$id);
        if(!$res['ok']) exit('Silinemedi: '.htmlspecialchars($res['msg']));
        try{ if(function_exists('activity_log')) activity_log('Silme',$res['msg'],$table.' #'.$id,'','admin',null,$back,$res['deactivated']?'⏸':'🗑'); }catch(Throwable $e){}
    }catch(Throwable $e){ exit('Silinemedi: '.htmlspecialchars($e->getMessage())); }
    redirect($back.($res['deactivated']?'?deactivated=1':'?deleted=1'));
}

// İş emri silme: finans/stok etkisi varsa engelle (kaynağından geri alınmalı), yoksa güvenli sil —
// jobs_lib.php::job_delete_or_deactivate() (web sil.php + mobile/job_view.php ortak, 2026-07-19
// USER TEST bulgusu: iki ayrı guard'sız kör-DELETE yolu tek güvenli fonksiyonda birleştirildi).
if($t==='job'){
    require_once __DIR__.'/jobs_lib.php';
    try{
        $res=job_delete_or_deactivate($pdo, $_SESSION['user']['id'] ?? 0, $id);
        if(!$res['ok']) exit(h($res['msg']));
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

// PİLOT ÖNCESİ KAPANIŞ (2026-07-19, gerçek bulgu — "Personel = Ana Varlık" kararı): bu dal önceden
// bağlı app_users hesabını pasifleştirdikten SONRA aşağıdaki genel bloğa (satır ~139) düşüp
// personnel satırını FİZİKSEL SİLİYORDU — "kör cascade DELETE" tam olarak bu. Personel ana varlık
// olduğu için normal UI'dan asla fiziksel silinmez, sadece pasife alınır (bağlı hesap da pasife
// alınır) — job/task/finance_movements gibi geçmiş kayıtlar personnel_id'yi hâlâ referans ediyor,
// fiziksel silme onları da yetim bırakırdı. redirect ile fonksiyon burada SONLANIYOR, aşağıdaki
// genel DELETE bloğuna hiç düşmüyor.
if($t==='personnel'){
    try{
        $pdo->prepare("UPDATE app_users SET active=0 WHERE personnel_id=?")->execute([$id]);
        $pdo->prepare("UPDATE personnel SET active=0 WHERE id=?")->execute([$id]);
        try{ if(function_exists('activity_log')) activity_log('Pasife Alma','Personel',$table.' #'.$id,'','admin',null,$back,'⏸'); }catch(Throwable $e){}
    }catch(Throwable $e){ exit('İşlem başarısız: '.htmlspecialchars($e->getMessage())); }
    redirect($back.'?deactivated=1');
}

try{
    try{ $pdo->exec("SET FOREIGN_KEY_CHECKS=0"); }catch(Throwable $e){}
    foreach($children as $ct=>$cf){ try{ $pdo->prepare("DELETE FROM `$ct` WHERE `$cf`=?")->execute([$id]); }catch(Throwable $e){} }
    $pdo->prepare("DELETE FROM `$table` WHERE id=?")->execute([$id]);
    try{ $pdo->exec("SET FOREIGN_KEY_CHECKS=1"); }catch(Throwable $e){}
    try{ if(function_exists('activity_log')) activity_log('Silme','Kayıt silindi',$table.' #'.$id,'','admin',null,$back,'🗑'); }catch(Throwable $e){}
}catch(Throwable $e){ exit('Silinemedi: '.htmlspecialchars($e->getMessage())); }
redirect($back.'?deleted=1');
