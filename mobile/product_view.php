<?php
require_once 'common.php';
$pdo=db(); $me=(int)($_SESSION['user']['id']??0);
$id=(int)($_GET['id']??0);

/* Stok hareketi (giriş/çıkış) — çıktıdan önce. 2026-07-03 güvenlik denetiminde bulundu: bu üç
   POST işlemi (mv/toggle_active/save_product) hiç yetki kontrolü yapmıyordu — 'stock' yetkisi
   olmayan biri bile fiyat/stok değiştirebiliyordu (web'de aynı düzeltme yapıldı, bkz. product_view.php). */
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['mv'])){
    if(!user_can('stock')){ header('Location: product_view.php?id='.$id); exit; }
    try{
        $dir=$_POST['mv']==='in'?'in':'out';
        $qty=(float)($_POST['quantity']??0);
        $reason=trim($_POST['reason']??'');
        if($qty>0){
            $pdo->prepare("INSERT INTO stock_movements(stock_item_id,direction,quantity,reason,note,created_at) VALUES(?,?,?,?,?,NOW())")
                ->execute([$id,$dir,$qty,$reason,'']);
            $pdo->prepare("UPDATE stock_items SET quantity=quantity".($dir==='in'?'+':'-')."? WHERE id=?")->execute([$qty,$id]);
            $pn=$pdo->prepare("SELECT name FROM stock_items WHERE id=?"); $pn->execute([$id]); $pnm=$pn->fetch()['name']??'';
            try{ if(function_exists('activity_log')) activity_log('Stok',$dir==='in'?'Giriş':'Çıkış',$pnm.' '.$qty,'','stock',$id,'product_view.php?id='.$id,$dir==='in'?'📥':'📤'); }catch(Throwable $e){}
        }
    }catch(Throwable $e){}
    header('Location: product_view.php?id='.$id); exit;
}
/* Ürün sil — admin */
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['delete_product'])){
    require_once dirname(__DIR__).'/boot.php';
    if(is_admin()){
        try{
            $pdo->prepare("DELETE FROM stock_movements WHERE stock_item_id=?")->execute([$id]);
            $pdo->prepare("DELETE FROM stock_items WHERE id=?")->execute([$id]);
        }catch(Throwable $e){}
    }
    header('Location: stock.php?deleted=1'); exit;
}
/* Aktif/Pasif toggle */
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['toggle_active'])){
    if(!user_can('stock')){ header('Location: product_view.php?id='.$id); exit; }
    try{
        $pdo->prepare("UPDATE stock_items SET active=1-COALESCE(active,1) WHERE id=?")->execute([$id]);
    }catch(Throwable $e){}
    header('Location: product_view.php?id='.$id); exit;
}
/* Ürün bilgisi düzenle */
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['save_product'])){
    if(!user_can('stock')){ header('Location: product_view.php?id='.$id.'&ok=1'); exit; }
    try{
        $f=function($k){return (float)str_replace(',','.',$_POST[$k]??'0');};
        $pdo->prepare("UPDATE stock_items SET name=?,product_code=?,barcode=?,unit=?,sale_price=?,purchase_price=?,critical_level=?,shelf_code=?,warehouse=?,notes=?,active=? WHERE id=?")
            ->execute([trim($_POST['name']),trim($_POST['product_code']??''),trim($_POST['barcode']??''),trim($_POST['unit']??''),$f('sale_price'),$f('purchase_price'),$f('critical_level'),trim($_POST['shelf_code']??''),trim($_POST['warehouse']??''),trim($_POST['notes']??''),isset($_POST['active'])?1:0,$id]);
        try{ if(function_exists('activity_log')) activity_log('Stok','Düzenleme',trim($_POST['name']),'','stock',$id,'product_view.php?id='.$id,'✏️'); }catch(Throwable $e){}
    }catch(Throwable $e){}
    header('Location: product_view.php?id='.$id.'&ok=1'); exit;
}

topx('Ürün');
if(!empty($_GET['ok'])) echo ds_alert('success','Ürün güncellendi.');
try{
    $s=$pdo->prepare("SELECT * FROM stock_items WHERE id=?"); $s->execute([$id]); $p=$s->fetch();
    if(!$p) throw new Exception('Ürün bulunamadı.');
    $krit=($p['quantity']<=($p['critical_level']??0));
    $aktif=!isset($p['active']) || $p['active'];
?>
<div class="df-panel">
  <h2 style="margin:0 0 4px"><?=h($p['name'])?><?php if(!$aktif): ?> <?=ds_badge('Pasif','red')?><?php endif; ?></h2>
  <div class="muted"><?=h($p['product_code']??'')?><?=$p['barcode']?' · '.ds_icon('search',13).' '.h($p['barcode']):''?></div>
  <div style="display:flex;gap:10px;margin-top:12px;flex-wrap:wrap">
    <div style="flex:1;min-width:120px;background:<?=$krit?'rgba(248,113,113,.15)':'rgba(34,197,94,.12)'?>;border-radius:14px;padding:12px">
      <div class="muted" style="font-size:12px">Mevcut Stok</div>
      <div style="font-size:26px;font-weight:900;color:<?=$krit?'#f87171':'#22c55e'?>"><?=h(rtrim(rtrim(number_format($p['quantity'],2,',','.'),'0'),','))?> <?=h($p['unit']??'')?></div>
      <?php if($krit): ?><small style="color:#f87171"><?=ds_icon('info',12)?> Kritik (≤<?=h($p['critical_level'])?>)</small><?php endif; ?>
    </div>
    <div style="flex:1;min-width:120px;background:rgba(255,255,255,.06);border-radius:14px;padding:12px">
      <div class="muted" style="font-size:12px">Satış / Alış</div>
      <div style="font-weight:900;font-size:16px"><?=mm($p['sale_price']??0)?></div>
      <small class="muted"><?=mm($p['purchase_price']??0)?> alış</small>
    </div>
  </div>
  <?php if(!empty($p['shelf_code'])||!empty($p['warehouse'])): ?>
  <div style="margin-top:8px" class="muted"><?=ds_icon('box',13)?> <?=h(($p['warehouse']??'').' '.($p['shelf_code']??''))?></div>
  <?php endif; ?>
  <div style="display:flex;gap:8px;margin-top:12px;flex-wrap:wrap">
    <form method="post" style="margin:0">
      <input type="hidden" name="toggle_active" value="1">
      <button class="df-btn <?=$aktif?'df-btn--secondary':'df-btn--primary'?> df-btn--sm" onclick="return confirm('<?=$aktif?'Ürünü pasife al?':'Ürünü aktif yap?'?>')"><?=$aktif?'Pasife Al':'Aktif Yap'?></button>
    </form>
    <?php
    require_once dirname(__DIR__).'/boot.php';
    if(is_admin()):
    ?>
    <form method="post" style="margin:0" onsubmit="return confirm('Bu ürünü ve tüm hareketlerini silmek istediğinizden emin misiniz? Bu işlem GERİ ALINAMAZ.')">
      <input type="hidden" name="delete_product" value="1">
      <button class="df-btn df-btn--danger df-btn--sm"><?=ds_icon('trash',14)?> Sil</button>
    </form>
    <?php endif; ?>
  </div>
</div>

<details class="df-panel" style="margin-top:12px">
  <summary style="font-weight:900;cursor:pointer"><?=ds_icon('edit',14)?> Ürün Bilgilerini Düzenle</summary>
  <form method="post" style="margin-top:10px">
    <label>Ürün Adı</label><input name="name" value="<?=h($p['name'])?>" required>
    <div style="display:flex;gap:10px"><div style="flex:1"><label>Kod</label><input name="product_code" value="<?=h($p['product_code']??'')?>"></div>
      <div style="flex:1"><label>Barkod</label><input name="barcode" value="<?=h($p['barcode']??'')?>"></div></div>
    <div style="display:flex;gap:10px"><div style="flex:1"><label>Satış ₺</label><input name="sale_price" value="<?=h($p['sale_price']??'0')?>"></div>
      <div style="flex:1"><label>Alış ₺</label><input name="purchase_price" value="<?=h($p['purchase_price']??'0')?>"></div></div>
    <div style="display:flex;gap:10px"><div style="flex:1"><label>Birim</label><input name="unit" value="<?=h($p['unit']??'')?>"></div>
      <div style="flex:1"><label>Kritik Seviye</label><input name="critical_level" value="<?=h($p['critical_level']??'0')?>"></div></div>
    <div style="display:flex;gap:10px"><div style="flex:1"><label>Raf</label><input name="shelf_code" value="<?=h($p['shelf_code']??'')?>"></div>
      <div style="flex:1"><label>Depo</label><input name="warehouse" value="<?=h($p['warehouse']??'')?>"></div></div>
    <label>Not</label><textarea name="notes" rows="2"><?=h($p['notes']??'')?></textarea>
    <label style="display:flex;align-items:center;gap:8px"><input type="checkbox" name="active" <?=$aktif?'checked':''?> style="width:auto"> Aktif ürün</label>
    <button class="df-btn df-btn--primary df-btn--lg" name="save_product" value="1" style="width:100%;margin-top:8px"><?=ds_icon('check',16)?> Kaydet</button>
  </form>
</details>

<div class="df-panel" style="margin-top:12px">
  <b><?=ds_icon('box',16)?> Stok Hareketi</b>
  <form method="post" style="margin-top:8px">
    <div style="display:flex;gap:8px">
      <input type="number" step="0.01" name="quantity" placeholder="Miktar" required style="flex:1;margin:0">
      <input name="reason" placeholder="Sebep (ops.)" style="flex:1;margin:0">
    </div>
    <div style="display:flex;gap:8px;margin-top:8px">
      <button class="df-btn df-btn--primary" name="mv" value="in" style="flex:1;justify-content:center">📥 Giriş</button>
      <button class="df-btn df-btn--danger" name="mv" value="out" style="flex:1;justify-content:center">📤 Çıkış</button>
    </div>
  </form>
</div>

<div class="df-panel" style="margin-top:12px">
  <b><?=ds_icon('info',16)?> Hareket Geçmişi</b>
  <?php
  $mv=$pdo->prepare("SELECT * FROM stock_movements WHERE stock_item_id=? ORDER BY id DESC LIMIT 50"); $mv->execute([$id]); $rows=$mv->fetchAll();
  if(!$rows) ds_empty_state('Henüz hareket yok.');
  foreach($rows as $m): $in=$m['direction']==='in'; ?>
    <div class="df-list-row-meta" style="display:flex;justify-content:space-between;align-items:center;padding:10px 0;border-top:1px solid rgba(255,255,255,.08)">
      <div><b style="color:<?=$in?'#22c55e':'#f87171'?>"><?=$in?'📥 Giriş':'📤 Çıkış'?> <?=h(rtrim(rtrim(number_format($m['quantity'],2,',','.'),'0'),','))?></b>
        <?=$m['reason']?'<br><small class="muted">'.h($m['reason']).'</small>':''?></div>
      <small class="muted"><?=h(date('d.m.Y H:i',strtotime($m['created_at'])))?></small>
    </div>
  <?php endforeach; ?>
</div>
<?php
}catch(Throwable $e){ echo ds_alert('danger',$e->getMessage()); }
botx();
