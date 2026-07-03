<?php
require_once 'common.php';
require_once dirname(__DIR__).'/checks_notes_lib.php';
$pdo=db();

// Yeni çek/senet ekle (POST topx'tan ÖNCE → redirect, PRG)
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['add_cn'])){
    $err='';
    try{
        checks_notes_create($pdo, $_POST, $u['id'] ?? null);
    }catch(Throwable $e){ $err=$e->getMessage(); }
    if($err===''){ header('Location: checks_notes.php?ok=1'); exit; }
    $_SESSION['cn_err']=$err;
    header('Location: checks_notes.php'); exit;
}

topx('Çek / Senet');
if(isset($_GET['ok'])) echo '<div class="ok">Kayıt eklendi.</div>';
if(isset($_GET['deleted'])) echo '<div class="ok">Kayıt silindi.</div>';
if(!empty($_SESSION['cn_err'])){ echo '<div class="err">'.htmlspecialchars($_SESSION['cn_err']).'</div>'; unset($_SESSION['cn_err']); }

$typeOpts=checks_notes_types();
$dirOpts=checks_notes_directions();
$statusOpts=checks_notes_statuses('alinan');
$typeFilter=$_GET['type'] ?? '';
$dirFilter=$_GET['direction'] ?? '';
$contacts=[];
try{ $contacts=$pdo->query("SELECT id,name FROM contacts ORDER BY name")->fetchAll(); }catch(Throwable $e){}
$rows=checks_notes_list($pdo, $typeFilter ?: null, null, $dirFilter ?: null);
$today=date('Y-m-d'); $soon=date('Y-m-d', strtotime('+7 days'));
$countPortfoyde=0;
foreach($rows as $r){ if($r['status']==='portfoyde') $countPortfoyde++; }
?>
<div class="grid">
  <div class="card blue"><span>🧾</span><b><?=$countPortfoyde?></b><small>Portföyde</small></div>
</div>

<details class="panel"><summary style="font-weight:900;cursor:pointer">➕ Yeni Çek / Senet Kaydı</summary>
<form method="post" style="margin-top:10px" enctype="multipart/form-data">
  <label>Yön <small class="muted">(bizim verdiğimiz mi, bize verilen mi)</small></label>
  <select name="direction" id="cn-dir-new" onchange="updateCnStatusLabels(this)"><?php foreach($dirOpts as $dk=>$dl): ?><option value="<?=$dk?>"><?=htmlspecialchars($dl)?></option><?php endforeach; ?></select>
  <label>Tür</label>
  <select name="type"><?php foreach($typeOpts as $tk=>$tl): ?><option value="<?=$tk?>"><?=htmlspecialchars($tl)?></option><?php endforeach; ?></select>
  <label>Numara</label>
  <input name="number" placeholder="Çek/senet numarası">
  <label>Tutar</label>
  <input type="number" step="0.01" name="amount" required>
  <label>Vade Tarihi</label>
  <input type="date" name="due_date">
  <label>Cari <small class="muted">(opsiyonel)</small></label>
  <select name="contact_id" id="contactSel" onchange="onCnContactChange()"><option value="">— Cari seçilmedi —</option>
  <?php foreach($contacts as $c): ?><option value="<?=$c['id']?>"><?=htmlspecialchars($c['name'])?></option><?php endforeach; ?>
  <option value="__new__">➕ Listede yok — Yeni Cari Ekle…</option>
  </select>
  <div id="newContactBox" style="display:none;background:rgba(37,99,235,.12);border-radius:12px;padding:10px;margin:6px 0 12px">
    <input type="text" id="qnContactName" placeholder="Müşteri adı">
    <button type="button" class="btn dark" style="width:100%" onclick="quickContactCheckMob(document.getElementById('qnContactName').value)">✓ Ekle ve Seç</button>
  </div>
  <label>Banka Adı <small class="muted">(çek ise)</small></label>
  <input name="bank_name">
  <label>Durum</label>
  <select name="status" id="cn-status-new"><?php foreach($statusOpts as $sk=>$sl): ?><option value="<?=$sk?>" <?=$sk==='portfoyde'?'selected':''?>><?=htmlspecialchars($sl)?></option><?php endforeach; ?></select>
  <label>Not</label>
  <textarea name="notes" rows="2"></textarea>
  <label>Fotoğraf / Dosya <small class="muted">(opsiyonel, jpg/png/webp/gif/pdf, en fazla 15 MB)</small></label>
  <input type="file" name="attachment" accept=".jpg,.jpeg,.png,.webp,.gif,.pdf">
  <button class="btn dark" name="add_cn" value="1" style="width:100%;padding:13px;margin-top:8px">💾 Kaydet</button>
</form>
</details>

<div class="panel" style="display:flex;gap:8px;flex-wrap:wrap">
  <a class="btn <?=$dirFilter===''?'dark':'secondary'?>" href="checks_notes.php<?=$typeFilter?'?type='.$typeFilter:''?>" style="flex:1;text-align:center">Tüm Yönler</a>
  <?php foreach($dirOpts as $dk=>$dl): ?>
  <a class="btn <?=$dirFilter===$dk?'dark':'secondary'?>" href="checks_notes.php?direction=<?=$dk?><?=$typeFilter?'&type='.$typeFilter:''?>" style="flex:1;text-align:center"><?=htmlspecialchars($dl)?></a>
  <?php endforeach; ?>
</div>

<div class="panel" style="display:flex;gap:8px;flex-wrap:wrap">
  <a class="btn <?=$typeFilter===''?'dark':'secondary'?>" href="checks_notes.php<?=$dirFilter?'?direction='.$dirFilter:''?>" style="flex:1;text-align:center">Tümü</a>
  <?php foreach($typeOpts as $tk=>$tl): ?>
  <a class="btn <?=$typeFilter===$tk?'dark':'secondary'?>" href="checks_notes.php?type=<?=$tk?><?=$dirFilter?'&direction='.$dirFilter:''?>" style="flex:1;text-align:center"><?=htmlspecialchars($tl)?></a>
  <?php endforeach; ?>
</div>

<div class="panel"><b>📜 Kayıtlar</b>
<?php
if(!$rows) echo '<p class="muted" style="margin:10px 0 0">Henüz kayıt yok — yukarıdan ekleyin.</p>';
foreach($rows as $r){
    $overdue = $r['status']==='portfoyde' && $r['due_date'] && $r['due_date']<$today;
    $upcoming = $r['status']==='portfoyde' && $r['due_date'] && $r['due_date']>=$today && $r['due_date']<=$soon;
    $color = $overdue ? '#f87171' : ($upcoming ? '#f59e0b' : '#4ade80');
    $ic = $r['type']==='senet' ? '📝' : '🧾';
    $att = !empty($r['attachment']) ? ' 📎' : '';
    $rDir = $r['direction'] ?? 'alinan';
    $dirIc = $rDir==='verilen' ? '📤' : '📥';
    $finIc = !empty($r['finance_movement_id']) ? ' 💰' : (!empty($r['contact_id']) ? ' ⚠️' : '');
    echo '<a class="item" href="check_note_view.php?id='.(int)$r['id'].'" style="display:flex;justify-content:space-between;align-items:center">'
       .'<span>'.$dirIc.$ic.' <b>'.htmlspecialchars($typeOpts[$r['type']]??$r['type']).' '.htmlspecialchars($r['number']?:'').$att.'</b><br>'
       .'<small class="muted">'.htmlspecialchars(($r['contact_name']?:'-').' · '.($r['due_date']?:'Vadesiz').($overdue?' ⚠️':($upcoming?' ⏳':''))).$finIc.'</small></span>'
       .'<b style="color:'.$color.'">'.mm($r['amount']).'</b></a>';
}
?>
</div>

<script>
var CN_STATUS_LABELS = {
  portfoyde: {alinan:'Portföyde', verilen:'Verildi (Bekliyor)'},
  tahsil_edildi: {alinan:'Tahsil Edildi', verilen:'Ödendi'},
  ciro_edildi: {alinan:'Ciro Edildi', verilen:'Ciro Edildi'},
  karsiliksiz: {alinan:'Karşılıksız', verilen:'Karşılıksız Döndü'},
  iptal: {alinan:'İptal', verilen:'İptal'}
};
function updateCnStatusLabels(dirSel){
    var form = dirSel.closest('form');
    var statusSel = form ? form.querySelector('select[name="status"]') : null;
    if(!statusSel) return;
    Array.prototype.forEach.call(statusSel.options, function(opt){
        var lbl = CN_STATUS_LABELS[opt.value];
        if(lbl) opt.textContent = lbl[dirSel.value] || lbl.alinan;
    });
}
function onCnContactChange(){
    var sel=document.getElementById('contactSel');
    var box=document.getElementById('newContactBox');
    if(sel.value==='__new__'){ box.style.display='block'; sel.value=''; document.getElementById('qnContactName').focus(); }
    else box.style.display='none';
}
function quickContactCheckMob(name) {
    if (!name) { alert('Ad girin'); return; }
    const fd = new FormData();
    fd.append('t', 'contact');
    fd.append('name', name);
    fd.append('contact_type', 'Müşteri');
    fetch('../ajax_quick_add.php', {method: 'POST', body: fd})
        .then(r => r.json())
        .then(data => {
            if (data.ok) {
                const sel = document.getElementById('contactSel');
                const opt = document.createElement('option');
                opt.value = data.id;
                opt.textContent = data.name;
                opt.selected = true;
                sel.insertBefore(opt, sel.querySelector('option[value="__new__"]'));
                document.getElementById('qnContactName').value = '';
                document.getElementById('newContactBox').style.display='none';
            } else alert('Hata: ' + data.message);
        })
        .catch(e => alert('Hata: ' + e));
}
</script>

<?php botx(); ?>
