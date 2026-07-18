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
if(isset($_GET['ok'])) echo ds_alert('success','Kayıt eklendi.');
if(isset($_GET['deleted'])) echo ds_alert('success','Kayıt silindi.');
if(!empty($_SESSION['cn_err'])){ echo ds_alert('danger',$_SESSION['cn_err']); unset($_SESSION['cn_err']); }

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
<div class="df-stat-row">
  <div class="df-stat"><span>🧾 Portföyde</span><strong><?=$countPortfoyde?></strong></div>
</div>
<style>
.df-stat-row{display:grid;grid-template-columns:1fr 1fr;gap:10px}
.df-stat{background:var(--df-surface-sunken,rgba(255,255,255,.06));border-radius:var(--df-radius-md,14px);padding:12px;display:flex;flex-direction:column;gap:4px}
.df-stat span{font-size:12px;color:var(--df-ink-500,#94a3b8)}
.df-stat strong{font-size:18px;font-weight:900}
</style>

<details class="df-panel" style="margin-top:12px"><summary style="font-weight:900;cursor:pointer"><?=ds_icon('plus',14)?> Yeni Çek / Senet Kaydı</summary>
<form method="post" style="margin-top:10px" enctype="multipart/form-data">
  <label>Yön <small class="muted">(bizim verdiğimiz mi, bize verilen mi)</small></label>
  <select name="direction" id="cn-dir-new"><?php foreach($dirOpts as $dk=>$dl): ?><option value="<?=$dk?>"><?=h($dl)?></option><?php endforeach; ?></select>
  <label>Tür</label>
  <select name="type"><?php foreach($typeOpts as $tk=>$tl): ?><option value="<?=$tk?>"><?=h($tl)?></option><?php endforeach; ?></select>
  <label>Numara</label>
  <input name="number" placeholder="Çek/senet numarası">
  <label>Tutar</label>
  <input type="number" step="0.01" name="amount" required>
  <label>Vade Tarihi</label>
  <input type="date" name="due_date">
  <label>Cari <small class="muted">(opsiyonel)</small></label>
  <select name="contact_id" id="contactSel" onchange="onCnContactChange()"><option value="">— Cari seçilmedi —</option>
  <?php foreach($contacts as $c): ?><option value="<?=$c['id']?>"><?=h($c['name'])?></option><?php endforeach; ?>
  <option value="__new__">➕ Listede yok — Yeni Cari Ekle…</option>
  </select>
  <div id="newContactBox" class="df-panel" style="display:none;background:rgba(37,99,235,.12);margin:6px 0 12px">
    <input type="text" id="qnContactName" placeholder="Müşteri adı">
    <button type="button" class="df-btn df-btn--primary" style="width:100%" onclick="quickContactCheckMob(document.getElementById('qnContactName').value)"><?=ds_icon('check',14)?> Ekle ve Seç</button>
  </div>
  <label>Banka Adı <small class="muted">(çek ise)</small></label>
  <input name="bank_name">
  <label>Not</label>
  <textarea name="notes" rows="2"></textarea>
  <label>Fotoğraf / Dosya <small class="muted">(opsiyonel, jpg/png/webp/gif/pdf, en fazla 15 MB)</small></label>
  <input type="file" name="attachment" accept=".jpg,.jpeg,.png,.webp,.gif,.pdf">
  <button class="df-btn df-btn--primary df-btn--lg" name="add_cn" value="1" style="width:100%;margin-top:8px"><?=ds_icon('check',16)?> Kaydet</button>
</form>
</details>

<div class="df-panel" style="display:flex;gap:8px;flex-wrap:wrap;margin-top:12px">
  <a class="df-btn <?=$dirFilter===''?'df-btn--primary':'df-btn--secondary'?>" href="checks_notes.php<?=$typeFilter?'?type='.$typeFilter:''?>" style="flex:1;justify-content:center">Tüm Yönler</a>
  <?php foreach($dirOpts as $dk=>$dl): ?>
  <a class="df-btn <?=$dirFilter===$dk?'df-btn--primary':'df-btn--secondary'?>" href="checks_notes.php?direction=<?=$dk?><?=$typeFilter?'&type='.$typeFilter:''?>" style="flex:1;justify-content:center"><?=h($dl)?></a>
  <?php endforeach; ?>
</div>

<div class="df-panel" style="display:flex;gap:8px;flex-wrap:wrap;margin-top:12px">
  <a class="df-btn <?=$typeFilter===''?'df-btn--primary':'df-btn--secondary'?>" href="checks_notes.php<?=$dirFilter?'?direction='.$dirFilter:''?>" style="flex:1;justify-content:center">Tümü</a>
  <?php foreach($typeOpts as $tk=>$tl): ?>
  <a class="df-btn <?=$typeFilter===$tk?'df-btn--primary':'df-btn--secondary'?>" href="checks_notes.php?type=<?=$tk?><?=$dirFilter?'&direction='.$dirFilter:''?>" style="flex:1;justify-content:center"><?=h($tl)?></a>
  <?php endforeach; ?>
</div>

<div class="df-panel" style="margin-top:12px"><b><?=ds_icon('info',16)?> Kayıtlar</b>
<?php
if(!$rows) ds_empty_state('Henüz kayıt yok — yukarıdan ekleyin.');
foreach($rows as $r){
    $overdue = $r['status']==='portfoyde' && $r['due_date'] && $r['due_date']<$today;
    $upcoming = $r['status']==='portfoyde' && $r['due_date'] && $r['due_date']>=$today && $r['due_date']<=$soon;
    $color = $overdue ? 'var(--df-danger-ink)' : ($upcoming ? 'var(--df-warning-ink)' : 'var(--df-success-ink)');
    $ic = $r['type']==='senet' ? '📝' : '🧾';
    $att = !empty($r['attachment']) ? ' 📎' : '';
    $rDir = $r['direction'] ?? 'alinan';
    $dirIc = $rDir==='verilen' ? '📤' : '📥';
    $finIc = !empty($r['finance_movement_id']) ? ' 💰' : (!empty($r['contact_id']) ? ' ⚠️' : '');
    $titleHtml = $dirIc.$ic.' <b>'.h($typeOpts[$r['type']]??$r['type']).' '.h($r['number']?:'').'</b>'.$att;
    $descHtml = h(($r['contact_name']?:'-').' · '.($r['due_date']?:'Vadesiz').($overdue?' ⚠️':($upcoming?' ⏳':''))).$finIc;
    $metaHtml = '<b style="color:'.$color.'">'.mm($r['amount']).'</b>';
    ds_list_item($titleHtml, 'check_note_view.php?id='.(int)$r['id'], $descHtml, $metaHtml);
}
?>
</div>

<script>
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
    fetch('../ajax_quick_add.php', {method: 'POST', headers: {'X-CSRF-Token': window.CSRF_TOKEN}, body: fd})
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
