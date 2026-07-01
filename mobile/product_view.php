<?php
require_once 'common.php';
$pdo=db(); $me=(int)($_SESSION['user']['id']??0);
$id=(int)($_GET['id']??0);

/* Stok hareketi (giriş/çıkış) — çıktıdan önce */
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['mv'])){
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
    try{
        $pdo->prepare("UPDATE stock_items SET active=1-COALESCE(active,1) WHERE id=?")->execute([$id]);
    }catch(Throwable $e){}
    header('Location: product_view.php?id='.$id); exit;
}
/* Ürün bilgisi düzenle */
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['save_product'])){
    try{
        $f=function($k){return (float)str_replace(',','.',$_POST[$k]??'0');};
        $pdo->prepare("UPDATE stock_items SET name=?,product_code=?,barcode=?,unit=?,sale_price=?,purchase_price=?,critical_level=?,shelf_code=?,warehouse=?,notes=?,active=? WHERE id=?")
            ->execute([trim($_POST['name']),trim($_POST['product_code']??''),trim($_POST['barcode']??''),trim($_POST['unit']??''),$f('sale_price'),$f('purchase_price'),$f('critical_level'),trim($_POST['shelf_code']??''),trim($_POST['warehouse']??''),trim($_POST['notes']??''),isset($_POST['active'])?1:0,$id]);
        try{ if(function_exists('activity_log')) activity_log('Stok','Düzenleme',trim($_POST['name']),'','stock',$id,'product_view.php?id='.$id,'✏️'); }catch(Throwable $e){}
    }catch(Throwable $e){}
    header('Location: product_view.php?id='.$id.'&ok=1'); exit;
}

topx('Ürün');
if(!empty($_GET['ok'])) echo '<div class="notice">Ürün güncellendi.</div>';
try{
    $s=$pdo->prepare("SELECT * FROM stock_items WHERE id=?"); $s->execute([$id]); $p=$s->fetch();
    if(!$p) throw new Exception('Ürün bulunamadı.');
    $krit=($p['quantity']<=($p['critical_level']??0));
    $aktif=!isset($p['active']) || $p['active'];
?>
<div class="panel">
  <h2 style="margin:0 0 4px"><?=htmlspecialchars($p['name'])?><?php if(!$aktif): ?> <span style="font-size:13px;background:#ef4444;color:#fff;border-radius:8px;padding:2px 8px;font-weight:700">Pasif</span><?php endif; ?></h2>
  <div class="muted"><?=htmlspecialchars($p['product_code']??'')?><?=$p['barcode']?' · 📷 '.htmlspecialchars($p['barcode']):''?></div>
  <div style="display:flex;gap:10px;margin-top:12px;flex-wrap:wrap">
    <div style="flex:1;min-width:120px;background:<?=$krit?'rgba(248,113,113,.15)':'rgba(34,197,94,.12)'?>;border-radius:14px;padding:12px">
      <div class="muted" style="font-size:12px">Mevcut Stok</div>
      <div style="font-size:26px;font-weight:900;color:<?=$krit?'#f87171':'#22c55e'?>"><?=htmlspecialchars(rtrim(rtrim(number_format($p['quantity'],2,',','.'),'0'),','))?> <?=htmlspecialchars($p['unit']??'')?></div>
      <?php if($krit): ?><small style="color:#f87171">⚠️ Kritik (≤<?=htmlspecialchars($p['critical_level'])?>)</small><?php endif; ?>
    </div>
    <div style="flex:1;min-width:120px;background:rgba(255,255,255,.06);border-radius:14px;padding:12px">
      <div class="muted" style="font-size:12px">Satış / Alış</div>
      <div style="font-weight:900;font-size:16px"><?=mm($p['sale_price']??0)?></div>
      <small class="muted"><?=mm($p['purchase_price']??0)?> alış</small>
    </div>
  </div>
  <?php if(!empty($p['shelf_code'])||!empty($p['warehouse'])): ?>
  <div style="margin-top:8px" class="muted">📍 <?=htmlspecialchars(($p['warehouse']??'').' '.($p['shelf_code']??''))?></div>
  <?php endif; ?>
  <div style="display:flex;gap:8px;margin-top:12px;flex-wrap:wrap">
    <form method="post" style="margin:0">
      <input type="hidden" name="toggle_active" value="1">
      <button class="btn" style="background:<?=$aktif?'#64748b':'#16a34a'?>;color:#fff;padding:9px 16px;font-size:14px" onclick="return confirm('<?=$aktif?'Ürünü pasife al?':'Ürünü aktif yap?'?>')"><?=$aktif?'Pasife Al':'Aktif Yap'?></button>
    </form>
    <?php
    require_once dirname(__DIR__).'/boot.php';
    if(is_admin()):
    ?>
    <form method="post" style="margin:0" onsubmit="return confirm('Bu ürünü ve tüm hareketlerini silmek istediğinizden emin misiniz? Bu işlem GERİ ALINAMAZ.')">
      <input type="hidden" name="delete_product" value="1">
      <button class="btn" style="background:#dc2626;color:#fff;padding:9px 16px;font-size:14px">🗑 Sil</button>
    </form>
    <?php endif; ?>
  </div>
</div>

<details class="panel">
  <summary style="font-weight:900;cursor:pointer">✏️ Ürün Bilgilerini Düzenle</summary>
  <form method="post" style="margin-top:10px">
    <label>Ürün Adı</label><input name="name" value="<?=htmlspecialchars($p['name'])?>" required>
    <div style="display:flex;gap:10px"><div style="flex:1"><label>Kod</label><input name="product_code" value="<?=htmlspecialchars($p['product_code']??'')?>"></div>
      <div style="flex:1"><label>Barkod</label><input name="barcode" value="<?=htmlspecialchars($p['barcode']??'')?>"></div></div>
    <div style="display:flex;gap:10px"><div style="flex:1"><label>Satış ₺</label><input name="sale_price" value="<?=htmlspecialchars($p['sale_price']??'0')?>"></div>
      <div style="flex:1"><label>Alış ₺</label><input name="purchase_price" value="<?=htmlspecialchars($p['purchase_price']??'0')?>"></div></div>
    <div style="display:flex;gap:10px"><div style="flex:1"><label>Birim</label><input name="unit" value="<?=htmlspecialchars($p['unit']??'')?>"></div>
      <div style="flex:1"><label>Kritik Seviye</label><input name="critical_level" value="<?=htmlspecialchars($p['critical_level']??'0')?>"></div></div>
    <div style="display:flex;gap:10px"><div style="flex:1"><label>Raf</label><input name="shelf_code" value="<?=htmlspecialchars($p['shelf_code']??'')?>"></div>
      <div style="flex:1"><label>Depo</label><input name="warehouse" value="<?=htmlspecialchars($p['warehouse']??'')?>"></div></div>
    <label>Not</label><textarea name="notes" rows="2"><?=htmlspecialchars($p['notes']??'')?></textarea>
    <label style="display:flex;align-items:center;gap:8px"><input type="checkbox" name="active" <?=$aktif?'checked':''?> style="width:auto"> Aktif ürün</label>
    <button class="btn dark" name="save_product" value="1" style="width:100%;padding:13px;margin-top:8px">💾 Kaydet</button>
  </form>
</details>

<div class="panel">
  <b>📦 Stok Hareketi</b>
  <form method="post" style="margin-top:8px">
    <div style="display:flex;gap:8px">
      <input type="number" step="0.01" name="quantity" placeholder="Miktar" required style="flex:1;margin:0">
      <input name="reason" placeholder="Sebep (ops.)" style="flex:1;margin:0">
    </div>
    <div style="display:flex;gap:8px;margin-top:8px">
      <button class="btn" name="mv" value="in" style="flex:1;background:#16a34a;color:#fff;padding:12px">📥 Giriş</button>
      <button class="btn" name="mv" value="out" style="flex:1;background:#b91c1c;color:#fff;padding:12px">📤 Çıkış</button>
    </div>
  </form>
</div>

<div class="panel">
  <b>📜 Hareket Geçmişi</b>
  <?php
  $mv=$pdo->prepare("SELECT * FROM stock_movements WHERE stock_item_id=? ORDER BY id DESC LIMIT 50"); $mv->execute([$id]); $rows=$mv->fetchAll();
  if(!$rows) echo '<p class="muted" style="margin:8px 0 0">Henüz hareket yok.</p>';
  foreach($rows as $m): $in=$m['direction']==='in'; ?>
    <div class="item" style="display:flex;justify-content:space-between;align-items:center">
      <div><b style="color:<?=$in?'#22c55e':'#f87171'?>"><?=$in?'📥 Giriş':'📤 Çıkış'?> <?=htmlspecialchars(rtrim(rtrim(number_format($m['quantity'],2,',','.'),'0'),','))?></b>
        <?=$m['reason']?'<br><small class="muted">'.htmlspecialchars($m['reason']).'</small>':''?></div>
      <small class="muted"><?=htmlspecialchars(date('d.m.Y H:i',strtotime($m['created_at'])))?></small>
    </div>
  <?php endforeach; ?>
</div>
<?php
}catch(Throwable $e){ echo '<div class="err">'.htmlspecialchars($e->getMessage()).'</div>'; }
botx();
