<?php
require_once __DIR__.'/boot.php';
require_login();
require_once __DIR__.'/activity_lib.php';
require_once __DIR__.'/checks_notes_lib.php';

$pdo=db();
$error='';
$ok = !empty($_GET['deleted']) ? 'Kayıt silindi.' : '';
$typeFilter = $_GET['type'] ?? '';
$statusFilter = $_GET['status'] ?? '';
$u = current_user();

if($_SERVER['REQUEST_METHOD']==='POST'){
    try{
        if(isset($_POST['save_cn'])){
            $newId = checks_notes_create($pdo, $_POST, $u['id'] ?? null);
            $ok='Kayıt eklendi.';
            activity_log('Finans','Çek/Senet',
                (($_POST['type'] ?? '')==='senet'?'Senet':'Çek').' kaydı eklendi',
                trim($_POST['number'] ?? '').' · '.number_format((float)str_replace(',','.',$_POST['amount'] ?? 0),2,',','.').' ₺',
                'checks_notes',$newId,'checks_notes.php','🧾');
        }elseif(isset($_POST['edit_cn'])){
            if(!can_edit_delete()){
                $error='Bu işlem için yetkiniz yok.';
            }else{
                checks_notes_update($pdo, (int)$_POST['id'], $_POST);
                $ok='Kayıt güncellendi.';
            }
        }elseif(isset($_POST['delete_cn'])){
            if(!can_edit_delete()){
                $error='Silme için yetkiniz yok.';
            }else{
                $res=checks_notes_delete($pdo,(int)$_POST['id']);
                if($res['ok']) $ok=$res['msg']; else $error=$res['msg'];
            }
        }
    }catch(Throwable $e){
        $error=$e->getMessage();
    }
}

require_once __DIR__.'/layout_top.php';

$contacts=$pdo->query("SELECT id,name,type FROM contacts ORDER BY name")->fetchAll();
$typeOpts=checks_notes_types();
$statusOpts=checks_notes_statuses();
$rows=checks_notes_list($pdo, $typeFilter ?: null, $statusFilter ?: null);
$today=date('Y-m-d');
$soon=date('Y-m-d', strtotime('+7 days'));
?>

<div class="panel-head">
<h1>Çek / Senet Takibi</h1>
<div class="actions">
<a class="btn secondary" href="finance.php">Finans Paneli</a>
<a class="btn secondary" href="finance_accounts.php">Hesaplar</a>
</div>
</div>

<?php if($error): ?><div class="alert"><?=h($error)?></div><?php endif; ?>
<?php if($ok): ?><div class="ok"><?=h($ok)?></div><?php endif; ?>

<section class="panel">
<h2>Yeni Çek / Senet Kaydı</h2>
<form method="post" class="form-grid" enctype="multipart/form-data">

<label>Tür
<select name="type">
<?php foreach($typeOpts as $tk=>$tl): ?>
<option value="<?=h($tk)?>"><?=h($tl)?></option>
<?php endforeach; ?>
</select>
</label>

<label>Numara
<input name="number" placeholder="Çek/senet numarası">
</label>

<label>Tutar
<input type="number" step="0.01" name="amount" required>
</label>

<label>Vade Tarihi
<input type="date" name="due_date">
</label>

<label>Cari <small style="font-weight:400;color:#667085">(opsiyonel — kimden alındı/kime verildi)</small>
<select name="contact_id" id="contactSel" onchange="onCnContactChange()">
<option value="">Cari seçilmedi</option>
<?php foreach($contacts as $c): ?>
<option value="<?=$c['id']?>"><?=h($c['name'].' / '.$c['type'])?></option>
<?php endforeach; ?>
<option value="__new__">➕ Listede yok — Yeni Cari Ekle…</option>
</select>
</label>
<div id="newContactBox" style="display:none;background:#eef4ff;border:1px solid #bfdbfe;border-radius:12px;padding:12px;margin:8px 0">
  <input type="text" id="contactNameCheck" placeholder="Müşteri adı" style="width:100%;border:1px solid #d0d5dd;border-radius:10px;padding:10px;margin-bottom:8px">
  <select id="contactTypeCheck" style="width:100%;border:1px solid #d0d5dd;border-radius:10px;padding:10px;margin-bottom:8px">
    <option>Müşteri</option><option>Tedarikçi</option><option>Diğer</option>
  </select>
  <button type="button" class="btn" style="width:100%" onclick="quickAddContactCheck(document.getElementById('contactNameCheck').value, document.getElementById('contactTypeCheck').value)">✓ Ekle ve Seç</button>
</div>

<label>Banka Adı <small style="font-weight:400;color:#667085">(çek ise)</small>
<input name="bank_name">
</label>

<label>Durum
<select name="status">
<?php foreach($statusOpts as $sk=>$sl): ?>
<option value="<?=h($sk)?>" <?=$sk==='portfoyde'?'selected':''?>><?=h($sl)?></option>
<?php endforeach; ?>
</select>
</label>

<label class="full">Not
<textarea name="notes" rows="2"></textarea>
</label>

<label class="full">Fotoğraf / Dosya <small style="font-weight:400;color:#667085">(çekin/senedin fotoğrafı, opsiyonel — jpg/png/webp/gif/pdf, en fazla 15 MB)</small>
<input type="file" name="attachment" accept=".jpg,.jpeg,.png,.webp,.gif,.pdf">
</label>

<button class="btn" name="save_cn" value="1">Kaydet</button>
</form>
</section>

<section class="panel">
<h2>Kayıtlar</h2>
<div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:14px">
<a class="btn small <?=$typeFilter===''?'':'secondary'?>" href="checks_notes.php<?=$statusFilter?'?status='.h($statusFilter):''?>">Tümü</a>
<?php foreach($typeOpts as $tk=>$tl): ?>
<a class="btn small <?=$typeFilter===$tk?'':'secondary'?>" href="checks_notes.php?type=<?=$tk?><?=$statusFilter?'&status='.h($statusFilter):''?>"><?=h($tl)?></a>
<?php endforeach; ?>
<span style="width:1px;background:#e5e7eb;margin:0 4px"></span>
<a class="btn small <?=$statusFilter===''?'':'secondary'?>" href="checks_notes.php<?=$typeFilter?'?type='.h($typeFilter):''?>">Tüm Durumlar</a>
<?php foreach($statusOpts as $sk=>$sl): ?>
<a class="btn small <?=$statusFilter===$sk?'':'secondary'?>" href="checks_notes.php?status=<?=$sk?><?=$typeFilter?'&type='.h($typeFilter):''?>"><?=h($sl)?></a>
<?php endforeach; ?>
</div>

<table>
<thead><tr><th>Tür</th><th>No</th><th>Tutar</th><th>Vade</th><th>Cari</th><th>Banka</th><th>Durum</th><th>Dosya</th><th>İşlem</th></tr></thead>
<tbody>
<?php
foreach($rows as $r){
    $rid=(int)$r['id'];
    $overdue = $r['status']==='portfoyde' && $r['due_date'] && $r['due_date']<$today;
    $upcoming = $r['status']==='portfoyde' && $r['due_date'] && $r['due_date']>=$today && $r['due_date']<=$soon;
    $rowStyle = $overdue ? "background:#fef2f2" : ($upcoming ? "background:#fffbeb" : "");
    $statusTone = ['portfoyde'=>'blue','tahsil_edildi'=>'green','ciro_edildi'=>'purple','karsiliksiz'=>'red','iptal'=>'gray'][$r['status']] ?? 'gray';
    echo "<tr style='$rowStyle'>";
    echo "<td>".h($typeOpts[$r['type']] ?? $r['type'])."</td>";
    echo "<td>".h($r['number'] ?: '-')."</td>";
    echo "<td>".money($r['amount'])."</td>";
    echo "<td>".h($r['due_date'] ?: '-').($overdue?' ⚠️ Vadesi geçti':($upcoming?' ⏳ Yaklaşıyor':''))."</td>";
    echo "<td>".h($r['contact_name'] ?: '-')."</td>";
    echo "<td>".h($r['bank_name'] ?: '-')."</td>";
    echo "<td>".badge($statusOpts[$r['status']] ?? $r['status'], $statusTone)."</td>";
    echo "<td>";
    if(!empty($r['attachment'])) echo "<a href='".h(base_url().$r['attachment'])."' target='_blank'>📎 Dosyayı Gör</a>"; else echo "<span class='muted'>-</span>";
    echo "</td>";
    echo "<td>";
    if(can_edit_delete()){
        echo "<button type='button' class='btn small secondary' onclick=\"document.getElementById('edit-cn-".$rid."').style.display=(document.getElementById('edit-cn-".$rid."').style.display==='none'?'table-row':'none')\">✏️ Düzenle</button> ";
        echo "<form method='post' style='display:inline' onsubmit=\"return confirm('Bu kaydı silmek istediğinize emin misiniz?')\">"
            ."<input type='hidden' name='id' value='".$rid."'>"
            ."<button class='btn small danger' name='delete_cn' value='1'>🗑 Sil</button>"
            ."</form>";
    }
    echo "</td>";
    echo "</tr>";

    if(can_edit_delete()){
    echo "<tr id='edit-cn-".$rid."' style='display:none;background:#f9fafb'><td colspan='9'>";
    echo "<form method='post' class='form-grid' style='margin:10px 0' enctype='multipart/form-data'>";
    echo "<input type='hidden' name='id' value='".$rid."'>";
    echo "<label>Tür<select name='type'>";
    foreach($typeOpts as $tk=>$tl){ echo "<option value='".h($tk)."' ".($r['type']===$tk?'selected':'').">".h($tl)."</option>"; }
    echo "</select></label>";
    echo "<label>Numara<input name='number' value='".h($r['number'])."'></label>";
    echo "<label>Tutar<input type='number' step='0.01' name='amount' value='".h($r['amount'])."' required></label>";
    echo "<label>Vade Tarihi<input type='date' name='due_date' value='".h($r['due_date'])."'></label>";
    echo "<label>Cari<select name='contact_id'><option value=''>Cari seçilmedi</option>";
    foreach($contacts as $c){ echo "<option value='".$c['id']."' ".((int)$r['contact_id']===(int)$c['id']?'selected':'').">".h($c['name'].' / '.$c['type'])."</option>"; }
    echo "</select></label>";
    echo "<label>Banka Adı<input name='bank_name' value='".h($r['bank_name'])."'></label>";
    echo "<label>Durum<select name='status'>";
    foreach($statusOpts as $sk=>$sl){ echo "<option value='".h($sk)."' ".($r['status']===$sk?'selected':'').">".h($sl)."</option>"; }
    echo "</select></label>";
    echo "<label class='full'>Not<textarea name='notes' rows='2'>".h($r['notes'])."</textarea></label>";
    echo "<label class='full'>Fotoğraf / Dosya <small style='font-weight:400;color:#667085'>(yeni dosya seçilirse eskisinin yerine geçer, boş bırakılırsa mevcut korunur)</small>";
    if(!empty($r['attachment'])) echo " — <a href='".h(base_url().$r['attachment'])."' target='_blank'>📎 Mevcut dosyayı gör</a>";
    echo "<input type='file' name='attachment' accept='.jpg,.jpeg,.png,.webp,.gif,.pdf'></label>";
    echo "<button class='btn' name='edit_cn' value='1'>💾 Kaydet</button>";
    echo "</form>";
    echo "</td></tr>";
    }
}
if(!$rows) echo "<tr><td colspan='9' class='muted'>Kayıt yok.</td></tr>";
?>
</tbody>
</table>
</section>

<script>
// Dropdown'da "Listede yok — Yeni Ekle" seçilince kutuyu aç (2026-07-03 kullanıcı isteği)
function onCnContactChange(){
    var sel=document.getElementById('contactSel');
    var box=document.getElementById('newContactBox');
    if(sel.value==='__new__'){ box.style.display='block'; sel.value=''; document.getElementById('contactNameCheck').focus(); }
    else box.style.display='none';
}
function quickAddContactCheck(name, type) {
    if (!name) return;
    const fd = new FormData();
    fd.append('t', 'contact');
    fd.append('name', name);
    fd.append('contact_type', type || 'Müşteri');

    fetch('ajax_quick_add.php', {method: 'POST', body: fd})
        .then(r => r.json())
        .then(data => {
            if (data.ok) {
                const sel = document.getElementById('contactSel');
                const opt = document.createElement('option');
                opt.value = data.id;
                opt.textContent = data.name;
                opt.selected = true;
                sel.insertBefore(opt, sel.querySelector('option[value="__new__"]'));
                document.getElementById('contactNameCheck').value = '';
                document.getElementById('newContactBox').style.display='none';
            } else {
                alert('Hata: ' + data.message);
            }
        })
        .catch(e => alert('Bağlantı hatası: ' + e));
}
</script>

<?php require_once __DIR__.'/layout_bottom.php'; ?>
