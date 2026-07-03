<?php
require_once 'common.php';
if(!$isAdmin){ header('Location: index.php'); exit; }
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

topx('Muhasebe Kategorileri');
?>
<?php if($msg): ?><div class="notice"><?=htmlspecialchars($msg)?></div><?php endif; ?>
<?php if($err): ?><div class="err"><?=htmlspecialchars($err)?></div><?php endif; ?>

<details class="panel" open>
  <summary style="font-weight:900;cursor:pointer">➕ Yeni Kategori</summary>
  <form method="post" style="margin-top:10px">
    <input type="hidden" name="save_cat" value="1">
    <input type="hidden" name="id" value="0">
    <label style="color:#94a3b8;font-size:12px">Ad</label>
    <input name="name" required placeholder="Kategori adı">
    <label style="color:#94a3b8;font-size:12px">Tür</label>
    <select name="type"><option value="gider">Gider</option><option value="gelir">Gelir</option></select>
    <label style="color:#94a3b8;font-size:12px">Grup</label>
    <input name="group_name" placeholder="Personel, Vergi, İşletme...">
    <label style="color:#94a3b8;font-size:12px">Sıra</label>
    <input type="number" name="sort_order" value="50">
    <label style="display:flex;align-items:center;gap:8px;margin:8px 0"><input type="checkbox" name="active" value="1" checked style="width:auto"> Aktif</label>
    <button class="btn dark" style="width:100%;padding:12px">➕ Ekle</button>
  </form>
</details>

<?php $lastType=''; foreach($cats as $c):
    if($c['type']!==$lastType){ echo '<div style="font-weight:900;color:'.($c['type']==='gelir'?'#4ade80':'#f87171').';margin:14px 4px 6px">'.($c['type']==='gelir'?'📈 Gelir':'📉 Gider').'</div>'; $lastType=$c['type']; }
?>
<div class="item" style="<?=!$c['active']?'opacity:.5':''?>">
  <div style="display:flex;justify-content:space-between;align-items:center;gap:8px">
    <span><?=htmlspecialchars($c['name'])?> <span class="small">[<?=htmlspecialchars($c['group_name'])?>]</span></span>
  </div>
  <div style="display:flex;gap:8px;margin-top:8px">
    <form method="post" style="flex:1">
      <input type="hidden" name="save_cat" value="1">
      <input type="hidden" name="id" value="<?=(int)$c['id']?>">
      <input type="hidden" name="name" value="<?=htmlspecialchars($c['name'])?>">
      <input type="hidden" name="type" value="<?=htmlspecialchars($c['type'])?>">
      <input type="hidden" name="group_name" value="<?=htmlspecialchars($c['group_name'])?>">
      <input type="hidden" name="sort_order" value="<?=(int)$c['sort_order']?>">
      <input type="hidden" name="active" value="<?=$c['active']?0:1?>">
      <button class="btn" style="width:100%;padding:8px;background:rgba(255,255,255,.12);color:#fff;font-size:12px"><?=$c['active']?'Pasif Yap':'Aktif Yap'?></button>
    </form>
    <form method="post" style="flex:1" onsubmit="return confirm('Silinsin mi?')">
      <button name="del_cat" value="<?=(int)$c['id']?>" class="btn" style="width:100%;padding:8px;background:rgba(239,68,68,.2);color:#fca5a5;font-size:12px">Sil</button>
    </form>
  </div>
</div>
<?php endforeach; ?>
<?php if(!$cats): ?><div class="panel muted" style="text-align:center">Kategori yok.</div><?php endif; ?>
<?php botx(); ?>
