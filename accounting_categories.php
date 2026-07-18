<?php
require_once __DIR__.'/layout_top.php';
if(!is_admin()){ echo ds_alert('danger','Sadece yönetici erişebilir.'); require __DIR__.'/layout_bottom.php'; exit; }
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
<?php ds_page_header('Muhasebe Kategorileri', ds_icon('settings',24), '', ds_button('← Muhasebeye Dön','accounting.php','secondary','','',true), false, true); ?>
<?php if($msg): ?><?=ds_alert('success',$msg)?><?php endif; ?>
<?php if($err): ?><?=ds_alert('danger',$err)?><?php endif; ?>

<section class="df-card" style="margin-top:var(--df-space-4)">
<h2 class="df-section-title">Yeni Kategori</h2>
<form method="post" class="df-form-grid-2">
<input type="hidden" name="save_cat" value="1">
<input type="hidden" name="id" value="0">
<?php
ds_form_field('Ad', '<input name="name" required placeholder="Kategori adı">');
ds_form_field('Tür', '<select name="type"><option value="gider">Gider</option><option value="gelir">Gelir</option></select>');
ds_form_field('Grup', '<input name="group_name" placeholder="Personel, Vergi, İşletme...">');
ds_form_field('Sıra', '<input type="number" name="sort_order" value="50">');
?>
<div class="df-form-span-2"><label style="display:flex;align-items:center;gap:8px;font-size:var(--df-type-body-size);color:var(--df-ink-900)"><input type="checkbox" name="active" value="1" checked style="width:auto"> Aktif</label></div>
<div class="df-form-span-2"><button class="df-btn df-btn--primary"><?=ds_icon('plus',16)?> Ekle</button></div>
</form>
</section>

<section class="df-card" style="margin-top:var(--df-space-4)">
<h2 class="df-section-title">Mevcut Kategoriler</h2>
<?php $lastType=''; foreach($cats as $c):
    if($c['type']!==$lastType){ echo '<div style="font-weight:900;color:'.($c['type']==='gelir'?'var(--df-success-ink)':'var(--df-danger-ink)').';margin:var(--df-space-4) 0 6px 0">'.($c['type']==='gelir'?'📈 Gelir':'📉 Gider').'</div>'; $lastType=$c['type']; }
?>
<div style="display:flex;align-items:center;gap:8px;padding:7px 0;border-bottom:1px solid var(--df-hairline)">
  <span style="flex:1;<?=!$c['active']?'opacity:.45':''?>"><?=h($c['name'])?> <span style="color:var(--df-ink-300);font-size:11px">[<?=h($c['group_name'])?>]</span></span>
  <form method="post" style="display:inline">
    <input type="hidden" name="save_cat" value="1">
    <input type="hidden" name="id" value="<?=(int)$c['id']?>">
    <input type="hidden" name="name" value="<?=h($c['name'])?>">
    <input type="hidden" name="type" value="<?=h($c['type'])?>">
    <input type="hidden" name="group_name" value="<?=h($c['group_name'])?>">
    <input type="hidden" name="sort_order" value="<?=(int)$c['sort_order']?>">
    <input type="hidden" name="active" value="<?=$c['active']?0:1?>">
    <button class="df-btn df-btn--secondary df-btn--sm"><?=$c['active']?'Pasif':'Aktif'?></button>
  </form>
  <form method="post" style="display:inline" onsubmit="return confirm('Silinsin mi?')">
    <button name="del_cat" value="<?=(int)$c['id']?>" class="df-btn df-btn--danger df-btn--sm">Sil</button>
  </form>
</div>
<?php endforeach; ?>
</section>

<style>
body.nav-compact .df-form-grid-2{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:0 var(--df-space-4)}
body.nav-compact .df-form-span-2{grid-column:1 / -1}
body.nav-compact .df-section-title{font-size:var(--df-type-section-size);font-weight:var(--df-type-section-weight);color:var(--df-ink-900);margin:0 0 var(--df-space-3)}
@media(max-width:640px){body.nav-compact .df-form-grid-2{grid-template-columns:1fr}}
</style>

<?php require_once __DIR__.'/layout_bottom.php'; ?>
