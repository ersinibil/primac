<?php
require_once 'common.php';
$pdo=db(); $ok=''; $er='';
if($_SERVER['REQUEST_METHOD']==='POST'){
    try{
        $name=trim($_POST['name'] ?? '');
        if($name==='') throw new Exception('Cari adı girin.');
        $pdo->prepare("INSERT INTO contacts(name,type,phone,email,opening_balance,created_at) VALUES(?,?,?,?,?,NOW())")
            ->execute([$name,$_POST['type'] ?? 'Müşteri',trim($_POST['phone'] ?? ''),trim($_POST['email'] ?? ''),(float)($_POST['opening_balance']??0)]);
        $nid=(int)$pdo->lastInsertId();
        try{ if(function_exists('activity_log')) activity_log('Cari','Yeni',$name,$_POST['type']??'','contact',$nid,'mobile/contact_view.php?id='.$nid,'👥'); }catch(Throwable $e){}
        $ok=$name.' eklendi.';
    }catch(Throwable $e){ $er=$e->getMessage(); }
}
topx('Yeni Cari');
?>
<?php if($ok): ?><div class="notice"><?=htmlspecialchars($ok)?> · <a href="contacts.php" style="color:#fff;text-decoration:underline">Cariler</a></div><?php endif; ?>
<?php if($er): ?><div class="err"><?=htmlspecialchars($er)?></div><?php endif; ?>
<div class="panel">
<form method="post">
  <label>Cari Adı</label><input name="name" required placeholder="Müşteri / tedarikçi adı">
  <label>Tip</label>
  <select name="type"><option>Müşteri</option><option>Tedarikçi</option><option>Her İkisi</option></select>
  <label>Telefon</label><input name="phone" type="tel" placeholder="05xx...">
  <label>E-posta</label><input name="email" type="email">
  <label>Açılış Bakiyesi</label><input type="number" step="0.01" name="opening_balance" value="0">
  <button class="btn dark" style="width:100%;padding:14px;margin-top:8px">➕ Cariyi Kaydet</button>
</form>
</div>
<?php botx(); ?>
