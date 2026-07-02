<?php
require_once 'common.php';
require_once __DIR__.'/../accounting_lib.php';
$pdo=db();
$ok=''; $er='';

$month=(int)($_GET['m'] ?? date('m'));
$year=(int)($_GET['y'] ?? date('Y'));

// POST işlemleri (PRG deseni — topx'ten ÖNCE)
if($_SERVER['REQUEST_METHOD']==='POST'){
    // Kayıt düzenleme
    if(isset($_POST['edit_entry'])){
        try{
            if(!can_edit_delete()) throw new Exception('Bu işlem için yetkiniz yok.');
            accounting_entry_update($pdo, (int)$_POST['id'], $_POST);
            $ok='Kayıt güncellendi.';
        }catch(Throwable $e){ $er=$e->getMessage(); }
        header('Location: accounting.php?m='.$month.'&y='.$year); exit;
    }
    // Kayıt sil
    if(isset($_POST['del_entry'])){
        try{
            if(!can_edit_delete()) throw new Exception('Bu işlem için yetkiniz yok.');
            $res=accounting_entry_delete($pdo, (int)$_POST['del_entry']);
            if($res['ok']) $ok=$res['msg']; else $er=$res['msg'];
        }catch(Throwable $e){ $er=$e->getMessage(); }
        header('Location: accounting.php?m='.$month.'&y='.$year); exit;
    }
    // Yeni kayıt
    if(isset($_POST['save_entry'])){
        try{
            $type=$_POST['type'] ?? 'gider';
            $amount=(float)str_replace(',','.',$_POST['amount'] ?? '0');
            $date=$_POST['entry_date'] ?? date('Y-m-d');
            $catId=(int)($_POST['category_id']??0) ?: null;
            $desc=trim($_POST['description'] ?? '');
            $accId=(int)($_POST['account_id']??0) ?: null;
            $pid=(int)($_POST['personnel_id']??0) ?: null;
            $pt=trim($_POST['payment_type'] ?? '');
            if($amount<=0) throw new Exception('Tutar geçersiz.');
            $pdo->prepare("INSERT INTO accounting_entries(entry_date,type,category_id,amount,description,account_id,personnel_id,payment_type,created_by) VALUES(?,?,?,?,?,?,?,?,?)")
                ->execute([$date,$type,$catId,$amount,$desc,$accId,$pid,$pt,$_SESSION['user']['id']??null]);
            if($accId){
                $dir=$type==='gelir'?'+':'-';
                try{ $pdo->prepare("UPDATE finance_accounts SET current_balance=current_balance{$dir}? WHERE id=?")->execute([$amount,$accId]); }catch(Throwable $e){}
            }
            $ok='Kayıt eklendi.';
        }catch(Throwable $e){ $er=$e->getMessage(); }
        header('Location: accounting.php?m='.$month.'&y='.$year); exit;
    }
}

$sum=acc_summary($pdo,$month,$year);
$net=$sum['gelir']-$sum['gider'];
$cats=acc_categories($pdo);
try{ $accounts=$pdo->query("SELECT id,name FROM finance_accounts WHERE active=1 ORDER BY name")->fetchAll(); }catch(Throwable $e){ $accounts=[]; }
try{ $personnel=$pdo->query("SELECT id,name FROM personnel WHERE COALESCE(active,1)=1 ORDER BY name")->fetchAll(); }catch(Throwable $e){ $personnel=[]; }
try{
    $entries=$pdo->query("SELECT ae.*,ac.name cat_name,p.name pers_name FROM accounting_entries ae
        LEFT JOIN accounting_categories ac ON ac.id=ae.category_id
        LEFT JOIN personnel p ON p.id=ae.personnel_id
        WHERE YEAR(ae.entry_date)=$year AND MONTH(ae.entry_date)=$month
        ORDER BY ae.entry_date DESC,ae.id DESC LIMIT 80")->fetchAll();
}catch(Throwable $e){ $entries=[]; }

$prevM=$month===1?12:$month-1; $prevY=$month===1?$year-1:$year;
$nextM=$month===12?1:$month+1; $nextY=$month===12?$year+1:$year;

topx('Muhasebe');
?>
<?php if($ok): ?><div class="notice"><?=htmlspecialchars($ok)?></div><?php endif; ?>
<?php if($er): ?><div class="err"><?=htmlspecialchars($er)?></div><?php endif; ?>

<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px">
  <a href="?m=<?=$prevM?>&y=<?=$prevY?>" style="background:rgba(255,255,255,.12);border-radius:10px;padding:8px 14px;color:#fff;text-decoration:none;font-weight:900">‹</a>
  <span style="font-weight:900;font-size:16px"><?=date('F Y',mktime(0,0,0,$month,1,$year))?></span>
  <a href="?m=<?=$nextM?>&y=<?=$nextY?>" style="background:rgba(255,255,255,.12);border-radius:10px;padding:8px 14px;color:#fff;text-decoration:none;font-weight:900">›</a>
</div>

<div class="grid">
  <div class="card green"><span>📈</span><b><?=mm($sum['gelir'])?></b><small>Gelir</small></div>
  <div class="card red"><span>📉</span><b><?=mm($sum['gider'])?></b><small>Gider</small></div>
</div>
<div class="panel" style="text-align:center;margin-top:-4px;padding:12px">
  <small class="muted">Net</small>
  <div style="font-size:22px;font-weight:900;color:<?=$net>=0?'#22c55e':'#f87171'?>"><?=$net>=0?'+':'-'?><?=mm(abs($net))?></div>
</div>

<details class="panel">
  <summary style="font-weight:900;cursor:pointer">➕ Yeni Kayıt</summary>
  <form method="post" style="margin-top:10px">
    <label style="color:#94a3b8;font-size:12px">Tür</label>
    <select name="type" id="mtype" onchange="mFilterCats()">
      <option value="gider">📉 Gider</option>
      <option value="gelir">📈 Gelir</option>
    </select>
    <label style="color:#94a3b8;font-size:12px">Tarih</label>
    <input type="date" name="entry_date" value="<?=date('Y-m-d')?>">
    <label style="color:#94a3b8;font-size:12px">Kategori</label>
    <select name="category_id" id="mcats">
      <option value="">— Seç —</option>
      <?php foreach($cats as $c): ?>
      <option value="<?=(int)$c['id']?>" data-type="<?=htmlspecialchars($c['type'])?>" <?=$c['type']==='gelir'?'style="display:none"':''?>>[<?=htmlspecialchars($c['group_name'])?>] <?=htmlspecialchars($c['name'])?></option>
      <?php endforeach; ?>
    </select>
    <label style="color:#94a3b8;font-size:12px">Tutar (₺)</label>
    <input type="number" step="0.01" min="0.01" name="amount" required placeholder="0,00">
    <label style="color:#94a3b8;font-size:12px">Açıklama</label>
    <input name="description" placeholder="İsteğe bağlı">
    <label style="color:#94a3b8;font-size:12px">Hesap</label>
    <select name="account_id">
      <option value="">— Seçme —</option>
      <?php foreach($accounts as $a): ?><option value="<?=(int)$a['id']?>"><?=htmlspecialchars($a['name'])?></option><?php endforeach; ?>
    </select>
    <label style="color:#94a3b8;font-size:12px">Personel (ödeme ise)</label>
    <select name="personnel_id">
      <option value="">— Yok —</option>
      <?php foreach($personnel as $p): ?><option value="<?=(int)$p['id']?>"><?=htmlspecialchars($p['name'])?></option><?php endforeach; ?>
    </select>
    <label style="color:#94a3b8;font-size:12px">Ödeme Türü</label>
    <select name="payment_type">
      <option value="">—</option>
      <option value="maas">Maaş</option>
      <option value="avans">Avans</option>
      <option value="prim">Prim / İkramiye</option>
      <option value="sgk">SGK</option>
      <option value="vergi">Vergi</option>
      <option value="diger">Diğer</option>
    </select>
    <button class="btn dark" name="save_entry" value="1" style="width:100%;padding:13px;margin-top:8px">💾 Kaydet</button>
  </form>
</details>

<script>
function mFilterCats(){
  var t=document.getElementById('mtype').value;
  var opts=document.getElementById('mcats').options;
  for(var i=0;i<opts.length;i++){
    var o=opts[i];
    if(!o.dataset.type){o.style.display='';continue;}
    o.style.display=(o.dataset.type===t)?'':'none';
    if(o.dataset.type!==t&&o.selected) o.selected=false;
  }
}
function mFilterCatsEdit(id){
  var t=document.getElementById('medit'+id).value;
  var opts=document.getElementById('meditcats'+id).options;
  for(var i=0;i<opts.length;i++){
    var o=opts[i];
    if(!o.dataset.type){o.style.display='';continue;}
    o.style.display=(o.dataset.type===t)?'':'none';
    if(o.dataset.type!==t&&o.selected) o.selected=false;
  }
}
</script>

<div class="panel">
  <b>📋 <?=date('F Y',mktime(0,0,0,$month,1,$year))?> Kayıtları</b>
  <?php if(!$entries): ?><p class="muted" style="margin:10px 0 0">Bu ay kayıt yok.</p><?php endif; ?>
  <?php foreach($entries as $e):
    $ig=$e['type']==='gider'; $tc=$ig?'#f87171':'#4ade80';
  ?>
  <details class="panel" style="margin:10px 0;padding:0">
    <summary style="padding:10px;cursor:pointer;font-weight:900;display:flex;justify-content:space-between;align-items:center">
      <div>
        <b style="font-size:14px"><?=htmlspecialchars($e['cat_name'] ?: 'Kategorisiz')?></b>
        <div style="font-size:11px;color:#94a3b8;margin-top:2px">
          <?=htmlspecialchars(date('d.m.Y',strtotime($e['entry_date'])))?>
          <?php if($e['pers_name']): ?> · <?=htmlspecialchars($e['pers_name'])?><?php endif; ?>
        </div>
      </div>
      <div style="text-align:right;flex:0 0 auto;margin-left:8px">
        <b style="color:<?=$tc?>"><?=$ig?'-':'+'?><?=mm($e['amount'])?></b>
      </div>
    </summary>
    <?php if($e['description']): ?><div style="font-size:12px;color:#cbd5e1;padding:0 10px;margin-bottom:6px"><?=htmlspecialchars($e['description'])?></div><?php endif; ?>
    <?php if(can_edit_delete()): ?>
    <details style="margin:8px 10px 0;padding:8px 0;border-top:1px solid rgba(255,255,255,.08)">
      <summary style="cursor:pointer;font-weight:900;color:#22c55e">✏️ Düzenle</summary>
      <form method="post" style="margin-top:10px">
        <input type="hidden" name="id" value="<?=(int)$e['id']?>">
        <label style="color:#94a3b8;font-size:12px">Tür</label>
        <select name="type" id="medit<?=(int)$e['id']?>" onchange="mFilterCatsEdit(<?=(int)$e['id']?>)">
          <option value="gider" <?=$e['type']==='gider'?'selected':''?>>📉 Gider</option>
          <option value="gelir" <?=$e['type']==='gelir'?'selected':''?>>📈 Gelir</option>
        </select>
        <label style="color:#94a3b8;font-size:12px">Tarih</label>
        <input type="date" name="entry_date" value="<?=htmlspecialchars($e['entry_date'])?>">
        <label style="color:#94a3b8;font-size:12px">Kategori</label>
        <select name="category_id" id="meditcats<?=(int)$e['id']?>">
          <option value="">— Seç —</option>
          <?php foreach($cats as $c): ?>
          <option value="<?=(int)$c['id']?>" data-type="<?=htmlspecialchars($c['type'])?>" <?=$e['category_id']==$c['id']?'selected':''?> style="display:<?=($e['type']==='gider' && $c['type']==='gider') || ($e['type']==='gelir' && $c['type']==='gelir')?'':' none'?>;">[<?=htmlspecialchars($c['group_name'])?>] <?=htmlspecialchars($c['name'])?></option>
          <?php endforeach; ?>
        </select>
        <label style="color:#94a3b8;font-size:12px">Tutar (₺)</label>
        <input type="number" step="0.01" min="0.01" name="amount" required value="<?=htmlspecialchars(str_replace('.',',',$e['amount']))?>">
        <label style="color:#94a3b8;font-size:12px">Açıklama</label>
        <input name="description" value="<?=htmlspecialchars($e['description'] ?? '')?>">
        <label style="color:#94a3b8;font-size:12px">Hesap</label>
        <select name="account_id">
          <option value="">— Seçme —</option>
          <?php foreach($accounts as $a): ?><option value="<?=(int)$a['id']?>" <?=$e['account_id']==$a['id']?'selected':''?>><?=htmlspecialchars($a['name'])?></option><?php endforeach; ?>
        </select>
        <label style="color:#94a3b8;font-size:12px">Personel (ödeme ise)</label>
        <select name="personnel_id">
          <option value="">— Yok —</option>
          <?php foreach($personnel as $p): ?><option value="<?=(int)$p['id']?>" <?=$e['personnel_id']==$p['id']?'selected':''?>><?=htmlspecialchars($p['name'])?></option><?php endforeach; ?>
        </select>
        <label style="color:#94a3b8;font-size:12px">Ödeme Türü</label>
        <select name="payment_type">
          <option value="" <?=!$e['payment_type']?'selected':''?>>—</option>
          <option value="maas" <?=$e['payment_type']==='maas'?'selected':''?>>Maaş</option>
          <option value="avans" <?=$e['payment_type']==='avans'?'selected':''?>>Avans</option>
          <option value="prim" <?=$e['payment_type']==='prim'?'selected':''?>>Prim / İkramiye</option>
          <option value="sgk" <?=$e['payment_type']==='sgk'?'selected':''?>>SGK</option>
          <option value="vergi" <?=$e['payment_type']==='vergi'?'selected':''?>>Vergi</option>
          <option value="diger" <?=$e['payment_type']==='diger'?'selected':''?>>Diğer</option>
        </select>
        <button class="btn dark" name="edit_entry" value="1" style="width:100%;padding:13px;margin-top:8px">💾 Kaydet</button>
      </form>
    </details>
    <form method="post" style="margin:8px 10px 0 0">
      <button name="del_entry" value="<?=(int)$e['id']?>" class="btn" style="background:rgba(220,38,38,.3);color:#fca5a5;width:100%;padding:10px;margin-top:8px" onclick="return confirm('Silinsin mi?')">🗑 Sil</button>
    </form>
    <?php endif; ?>
  </details>
  <?php endforeach; ?>
</div>
<?php botx(); ?>
