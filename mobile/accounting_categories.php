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
<?php if($msg): ?><?=ds_alert('success',$msg)?><?php endif; ?>
<?php if($err): ?><?=ds_alert('danger',$err)?><?php endif; ?>

<details class="df-panel" open>
  <summary style="font-weight:900;cursor:pointer"><?=ds_icon('plus',16)?> Yeni Kategori</summary>
  <form method="post" style="margin-top:10px">
    <input type="hidden" name="save_cat" value="1">
    <input type="hidden" name="id" value="0">
    <label>Ad</label>
    <input name="name" required placeholder="Kategori adı">
    <label>Tür</label>
    <select name="type"><option value="gider">Gider</option><option value="gelir">Gelir</option></select>
    <label>Grup</label>
    <input name="group_name" placeholder="Personel, Vergi, İşletme...">
    <label>Sıra</label>
    <input type="number" name="sort_order" value="50">
    <label style="display:flex;align-items:center;gap:8px;margin:8px 0"><input type="checkbox" name="active" value="1" checked style="width:auto"> Aktif</label>
    <button class="df-btn df-btn--primary df-btn--lg" style="width:100%"><?=ds_icon('plus',16)?> Ekle</button>
  </form>
</details>

<?php $lastType=''; foreach($cats as $c):
    if($c['type']!==$lastType){ echo '<div class="df-badge df-badge--'.($c['type']==='gelir'?'success':'danger').'" style="margin:14px 4px 6px">'.($c['type']==='gelir'?'📈 Gelir':'📉 Gider').'</div>'; $lastType=$c['type']; }
?>
<div class="df-panel" style="margin-top:10px<?=!$c['active']?';opacity:.5':''?>">
  <div style="display:flex;justify-content:space-between;align-items:center;gap:8px">
    <span class="df-list-row-title"><?=htmlspecialchars($c['name'])?> <span class="muted" style="font-size:12px;font-weight:400">[<?=htmlspecialchars($c['group_name'])?>]</span></span>
  </div>
  <div style="display:flex;gap:8px;margin-top:10px">
    <form method="post" style="flex:1">
      <input type="hidden" name="save_cat" value="1">
      <input type="hidden" name="id" value="<?=(int)$c['id']?>">
      <input type="hidden" name="name" value="<?=htmlspecialchars($c['name'])?>">
      <input type="hidden" name="type" value="<?=htmlspecialchars($c['type'])?>">
      <input type="hidden" name="group_name" value="<?=htmlspecialchars($c['group_name'])?>">
      <input type="hidden" name="sort_order" value="<?=(int)$c['sort_order']?>">
      <input type="hidden" name="active" value="<?=$c['active']?0:1?>">
      <button class="df-btn df-btn--secondary" style="width:100%"><?=$c['active']?'Pasif Yap':'Aktif Yap'?></button>
    </form>
    <form method="post" style="flex:1" onsubmit="return confirm('Silinsin mi?')">
      <button name="del_cat" value="<?=(int)$c['id']?>" class="df-btn df-btn--danger" style="width:100%"><?=ds_icon('trash',14)?> Sil</button>
    </form>
  </div>
</div>
<?php endforeach; ?>
<?php if(!$cats): ?><?php ds_empty_state('Kategori yok.', null, ds_icon('wallet',20)); ?><?php endif; ?>
<?php botx(); ?>
