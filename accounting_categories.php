<?php
require_once __DIR__.'/layout_top.php';
if(!is_admin()){ echo '<div class="alert">Sadece yönetici erişebilir.</div>'; require __DIR__.'/layout_bottom.php'; exit; }
$pdo=db();
$msg=''; $err='';

if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['save_cat'])){
    try{
        $id=(int)($_POST['id']??0);
        $name=trim($_POST['name']??'');
        $type=$_POST['type']??'gider';
        $group=trim($_POST['group_name']??'');
        $sort=(int)($_POST['sort_order']??0);
        $active=(int)($_POST['active']??1);
        if(!$name) throw new Exception('Kategori adı zorunlu.');
        if($id){
            $pdo->prepare("UPDATE accounting_categories SET name=?,type=?,group_name=?,sort_order=?,active=? WHERE id=?")->execute([$name,$type,$group,$sort,$active,$id]);
            $msg='Kategori güncellendi.';
        }else{
            $pdo->prepare("INSERT INTO accounting_categories(name,type,group_name,sort_order,active) VALUES(?,?,?,?,?)")->execute([$name,$type,$group,$sort,$active]);
            $msg='Kategori eklendi.';
        }
    }catch(Throwable $e){ $err=$e->getMessage(); }
}
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['del_cat'])){
    try{ $pdo->prepare("DELETE FROM accounting_categories WHERE id=?")->execute([(int)$_POST['del_cat']]); $msg='Silindi.'; }
    catch(Throwable $e){ $err='Silme hatası: '.$e->getMessage(); }
}

try{ $cats=$pdo->query("SELECT * FROM accounting_categories ORDER BY type DESC,sort_order,name")->fetchAll(); }
catch(Throwable $e){ $cats=[]; }
?>
<h1>⚙ Muhasebe Kategorileri</h1>
<a class="btn secondary" href="accounting.php">← Muhasebeye Dön</a>
<?php if($msg): ?><div class="ok" style="margin-top:12px"><?=h($msg)?></div><?php endif; ?>
<?php if($err): ?><div class="alert" style="margin-top:12px"><?=h($err)?></div><?php endif; ?>

<section class="panel" style="margin-top:16px">
<h2>Yeni Kategori</h2>
<form method="post" class="form-grid">
<input type="hidden" name="save_cat" value="1">
<input type="hidden" name="id" value="0">
<label>Ad<input name="name" required placeholder="Kategori adı"></label>
<label>Tür<select name="type"><option value="gider">Gider</option><option value="gelir">Gelir</option></select></label>
<label>Grup<input name="group_name" placeholder="Personel, Vergi, İşletme..."></label>
<label>Sıra<input type="number" name="sort_order" value="50"></label>
<label style="align-items:center"><input type="checkbox" name="active" value="1" checked style="width:auto"> Aktif</label>
<div class="full"><button class="btn">➕ Ekle</button></div>
</form>
</section>

<section class="panel">
<h2>Mevcut Kategoriler</h2>
<?php $lastType=''; foreach($cats as $c):
    if($c['type']!==$lastType){ echo '<div style="font-weight:900;color:'.($c['type']==='gelir'?'#16a34a':'#dc2626').';margin:14px 0 6px 0">'.($c['type']==='gelir'?'📈 Gelir':'📉 Gider').'</div>'; $lastType=$c['type']; }
?>
<div style="display:flex;align-items:center;gap:8px;padding:7px 0;border-bottom:1px solid #f1f5f9">
  <span style="flex:1;<?=!$c['active']?'opacity:.45':''?>"><?=h($c['name'])?> <span style="color:#94a3b8;font-size:11px">[<?=h($c['group_name'])?>]</span></span>
  <form method="post" style="display:inline">
    <input type="hidden" name="save_cat" value="1">
    <input type="hidden" name="id" value="<?=(int)$c['id']?>">
    <input type="hidden" name="name" value="<?=h($c['name'])?>">
    <input type="hidden" name="type" value="<?=h($c['type'])?>">
    <input type="hidden" name="group_name" value="<?=h($c['group_name'])?>">
    <input type="hidden" name="sort_order" value="<?=(int)$c['sort_order']?>">
    <input type="hidden" name="active" value="<?=$c['active']?0:1?>">
    <button class="btn secondary" style="padding:4px 10px;font-size:12px"><?=$c['active']?'Pasif':'Aktif'?></button>
  </form>
  <form method="post" style="display:inline" onsubmit="return confirm('Silinsin mi?')">
    <button name="del_cat" value="<?=(int)$c['id']?>" class="btn danger" style="padding:4px 10px;font-size:12px">Sil</button>
  </form>
</div>
<?php endforeach; ?>
</section>
<?php require_once __DIR__.'/layout_bottom.php'; ?>
