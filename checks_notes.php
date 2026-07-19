<?php
require_once __DIR__.'/boot.php';
require_login();
require_once __DIR__.'/activity_lib.php';
require_once __DIR__.'/checks_notes_lib.php';

$pdo=db();
$error='';
$ok = !empty($_GET['deleted']) ? 'Kayıt silindi.' : (!empty($_GET['ok']) ? 'Kayıt eklendi.' : '');
if(!empty($_SESSION['cn_err'])){ $error=$_SESSION['cn_err']; unset($_SESSION['cn_err']); }
$typeFilter = $_GET['type'] ?? '';
$statusFilter = $_GET['status'] ?? '';
$dirFilter = $_GET['direction'] ?? '';
$u = current_user();

if($_SERVER['REQUEST_METHOD']==='POST'){
    // save_cn artık PRG (Post-Redirect-Get) deseniyle redirect ediyor — UX/STABILITY PATCH-002'de
    // bulundu: önceden redirect YOKTU, sayfa yenilendiğinde (F5) form yeniden POST edilip AYNI
    // çek/senedin ikinci bir kaydı + ikinci bir otomatik hatırlatma görevi oluşuyordu (takvimde aynı
    // çekin iki kez görünmesinin kök nedeni). mobile/checks_notes.php zaten bu deseni kullanıyordu.
    if(isset($_POST['save_cn'])){
        try{
            $newId = checks_notes_create($pdo, $_POST, $u['id'] ?? null);
            activity_log('Finans','Çek/Senet',
                (($_POST['type'] ?? '')==='senet'?'Senet':'Çek').' kaydı eklendi',
                trim($_POST['number'] ?? '').' · '.number_format((float)str_replace(',','.',$_POST['amount'] ?? 0),2,',','.').' ₺',
                'checks_notes',$newId,'checks_notes.php','🧾');
            header('Location: checks_notes.php?ok=1'); exit;
        }catch(Throwable $e){
            $_SESSION['cn_err']=$e->getMessage();
            header('Location: checks_notes.php'); exit;
        }
    }elseif(isset($_POST['delete_cn'])){
        try{
            if(!can_edit_delete()){
                $error='Silme için yetkiniz yok.';
            }else{
                $res=checks_notes_delete($pdo,(int)$_POST['id']);
                if($res['ok']) $ok=$res['msg']; else $error=$res['msg'];
            }
        }catch(Throwable $e){ $error=$e->getMessage(); }
    }elseif(isset($_POST['reopen_cn'])){
        // ÇEK/SENET SİLME REGRESYONU (2026-07-19, gerçek USER TEST) — Ciro Edildi/Tahsil Edildi/
        // Ödendi durumundaki kayıtlar liste ekranında Sil/Düzenle GÖSTERMEZ (bilerek — kontrolsüz
        // silme finans matematiğini bozar, checks_notes_can_delete() zaten bunu engelliyor).
        // Kullanıcı bunu "silemiyorum" olarak yaşıyordu çünkü tek yol Detay sayfasına gidip
        // "İşlemi Geri Al" bulmaktı — bu artık liste satırından TEK TIKLA yapılabiliyor, aynı
        // checks_notes_reopen() (Detay sayfasıyla BİREBİR aynı fonksiyon) çağrılıyor.
        try{
            if(!can_edit_delete()){
                $error='Bu işlem için yetkiniz yok.';
            }else{
                checks_notes_reopen($pdo,$u['id'] ?? null,(int)$_POST['id'],'');
                $ok='İşlem geri alındı — kayıt tekrar Portföyde/Bekliyor durumunda. Şimdi Sil/Düzenle kullanılabilir.';
            }
        }catch(Throwable $e){ $error=$e->getMessage(); }
    }
}

require_once __DIR__.'/layout_top.php';

$contacts=$pdo->query("SELECT id,name,type FROM contacts ORDER BY name")->fetchAll();
$typeOpts=checks_notes_types();
$dirOpts=checks_notes_directions();
$statusOpts=checks_notes_statuses('alinan');
$rows=checks_notes_list($pdo, $typeFilter ?: null, $statusFilter ?: null, $dirFilter ?: null);
$today=date('Y-m-d');
$soon=date('Y-m-d', strtotime('+7 days'));
?>

<?php
$__cnActions = ds_button('Finans Paneli','finance.php','secondary','','',true) . ds_button('Hesaplar','finance_accounts.php','secondary','','',true);
ds_page_header('Çek / Senet Takibi', ds_icon('tag',24), '', $__cnActions, false, true);
?>

<?php if($error): ?><?=ds_alert('danger',$error)?><?php endif; ?>
<?php if($ok): ?><?=ds_alert('success',$ok)?><?php endif; ?>
<?php if(!checks_notes_lifecycle_ready()): ?>
<?=ds_alert('danger','⚠️ Çek/Senet yaşam döngüsü (Tahsil Et / Öde / Ciro Et / İşlemi Geri Al) bu sunucuda henüz AKTİF DEĞİL — migration 048 çalıştırılmamış. "Düzenle" ve "Sil" (henüz sonuçlanmamış kayıtlarda) etkilenmez, ama tahsilat/ödeme/ciro/geri alma denendiğinde hata verir. Çözüm: migrate.php çalıştırılmalı.')?>
<?php endif; ?>

<section class="df-card">
<h2 style="font-size:var(--df-type-section-size);margin:0 0 var(--df-space-3)">Yeni Çek / Senet Kaydı</h2>
<form method="post" class="df-form-grid-2" enctype="multipart/form-data">

<?php
$__dirOptsHtml='';
foreach($dirOpts as $dk=>$dl){ $__dirOptsHtml.='<option value="'.h($dk).'">'.h($dl).'</option>'; }
ds_form_field('Yön', '<select name="direction" id="cn-dir-new">'.$__dirOptsHtml.'</select>', 'Bizim verdiğimiz mi, bize verilen mi');

$__typeOptsHtml='';
foreach($typeOpts as $tk=>$tl){ $__typeOptsHtml.='<option value="'.h($tk).'">'.h($tl).'</option>'; }
ds_form_field('Tür', '<select name="type">'.$__typeOptsHtml.'</select>');
?>

<?php ds_form_field('Numara', '<input name="number" placeholder="Çek/senet numarası">'); ?>
<?php ds_form_field('Tutar', '<input type="number" step="0.01" name="amount" required>'); ?>
<?php ds_form_field('Vade Tarihi', '<input type="date" name="due_date">'); ?>

<?php
$__contactOptsHtml='<option value="">Cari seçilmedi</option>';
foreach($contacts as $c){ $__contactOptsHtml.='<option value="'.$c['id'].'">'.h($c['name'].' / '.$c['type']).'</option>'; }
$__contactOptsHtml.='<option value="__new__">➕ Listede yok — Yeni Cari Ekle…</option>';
ds_form_field('Cari', '<select name="contact_id" id="contactSel" onchange="onCnContactChange()">'.$__contactOptsHtml.'</select>', 'Opsiyonel — kimden alındı/kime verildi');
?>

<div class="df-form-span-2" id="newContactBox" style="display:none;background:var(--df-info-soft);border:1px solid var(--df-hairline);border-radius:var(--df-radius-md);padding:12px;margin:8px 0">
  <input type="text" id="contactNameCheck" placeholder="Müşteri adı" style="width:100%;margin-bottom:8px">
  <select id="contactTypeCheck" style="width:100%;margin-bottom:8px">
    <option>Müşteri</option><option>Tedarikçi</option><option>Diğer</option>
  </select>
  <button type="button" class="df-btn df-btn--secondary" style="width:100%" onclick="quickAddContactCheck(document.getElementById('contactNameCheck').value, document.getElementById('contactTypeCheck').value)">✓ Ekle ve Seç</button>
</div>

<?php
ds_form_field('Banka Adı', '<input name="bank_name">', 'Çek ise');
?>

<div class="df-form-span-2"><?php ds_form_field('Not', '<textarea name="notes" rows="2"></textarea>'); ?></div>
<div class="df-form-span-2"><?php ds_form_field('Fotoğraf / Dosya', '<input type="file" name="attachment" accept=".jpg,.jpeg,.png,.webp,.gif,.pdf">', 'Çekin/senedin fotoğrafı, opsiyonel — jpg/png/webp/gif/pdf, en fazla 15 MB'); ?></div>

<div class="df-form-span-2"><button class="df-btn df-btn--primary" name="save_cn" value="1">Kaydet</button></div>
</form>
</section>

<section class="df-card" style="margin-top:var(--df-space-4)">
<h2 style="font-size:var(--df-type-section-size);margin:0 0 var(--df-space-3)">Kayıtlar</h2>

<div style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:10px">
<?php
$__dirTabs=[['label'=>'Tüm Yönler','url'=>'checks_notes.php?'.($typeFilter?'type='.h($typeFilter).'&':'').($statusFilter?'status='.h($statusFilter):''),'active'=>$dirFilter==='']];
foreach($dirOpts as $dk=>$dl){ $__dirTabs[]=['label'=>$dl,'url'=>'checks_notes.php?direction='.$dk.($typeFilter?'&type='.h($typeFilter):'').($statusFilter?'&status='.h($statusFilter):''),'active'=>$dirFilter===$dk]; }
ds_tabs($__dirTabs);
?>
</div>
<div style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:10px">
<?php
$__typeTabs=[['label'=>'Tüm Türler','url'=>'checks_notes.php?'.($dirFilter?'direction='.h($dirFilter).'&':'').($statusFilter?'status='.h($statusFilter):''),'active'=>$typeFilter==='']];
foreach($typeOpts as $tk=>$tl){ $__typeTabs[]=['label'=>$tl,'url'=>'checks_notes.php?type='.$tk.($dirFilter?'&direction='.h($dirFilter):'').($statusFilter?'&status='.h($statusFilter):''),'active'=>$typeFilter===$tk]; }
ds_tabs($__typeTabs);
?>
</div>
<div style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:16px">
<?php
$__statusTabs=[['label'=>'Tüm Durumlar','url'=>'checks_notes.php?'.($dirFilter?'direction='.h($dirFilter).'&':'').($typeFilter?'type='.h($typeFilter):''),'active'=>$statusFilter==='']];
foreach($statusOpts as $sk=>$sl){ $__statusTabs[]=['label'=>$sl,'url'=>'checks_notes.php?status='.$sk.($dirFilter?'&direction='.h($dirFilter):'').($typeFilter?'&type='.h($typeFilter):''),'active'=>$statusFilter===$sk]; }
ds_tabs($__statusTabs);
?>
</div>

<div class="df-table-wrap"><table class="df-table">
<thead><tr><th>Yön</th><th>Tür</th><th>No</th><th>Tutar</th><th>Vade</th><th>Cari</th><th>Banka</th><th>Durum</th><th>Dosya</th><th>İşlem</th></tr></thead>
<tbody>
<?php
foreach($rows as $r){
    $rid=(int)$r['id'];
    $rDir = $r['direction'] ?? 'alinan';
    $rStatusOpts = checks_notes_statuses($rDir);
    $overdue = $r['status']==='portfoyde' && $r['due_date'] && $r['due_date']<$today;
    $upcoming = $r['status']==='portfoyde' && $r['due_date'] && $r['due_date']>=$today && $r['due_date']<=$soon;
    $rowStyle = $overdue ? "background:var(--df-danger-soft)" : ($upcoming ? "background:var(--df-warning-soft)" : "");
    $statusTone = checks_notes_status_tone($r['status']);
    echo "<tr id='cn-row-".$rid."' style='$rowStyle'>";
    echo "<td>".ds_badge($dirOpts[$rDir] ?? $rDir, $rDir==='verilen'?'orange':'blue')."</td>";
    echo "<td>".h($typeOpts[$r['type']] ?? $r['type'])."</td>";
    echo "<td>".h($r['number'] ?: '-')."</td>";
    echo "<td>".money($r['amount'])."</td>";
    echo "<td>".h($r['due_date'] ?: '-').($overdue?' ⚠️ Vadesi geçti':($upcoming?' ⏳ Yaklaşıyor':''))."</td>";
    echo "<td>".h($r['contact_name'] ?: '-').(!empty($r['finance_movement_id'])?' <span title="Cari bakiyeye işlendi" style="color:var(--df-success)">💰</span>':(!empty($r['contact_id'])?' <span title="Finans hareketi oluşturulamadı" style="color:var(--df-danger)">⚠️</span>':''))."</td>";
    echo "<td>".h($r['bank_name'] ?: '-')."</td>";
    echo "<td>".ds_badge($rStatusOpts[$r['status']] ?? $r['status'], $statusTone)."</td>";
    echo "<td>";
    if(!empty($r['attachment'])) echo "<a href='".h(base_url().$r['attachment'])."' target='_blank'>📎 Dosyayı Gör</a>"; else echo "<span style='color:var(--df-ink-500)'>-</span>";
    echo "</td>";
    echo "<td><div class='row-actions'>";
    echo "<a class='df-btn df-btn--secondary df-btn--sm' href='check_note_view.php?id=".$rid."'>🔎 Detay</a>";
    // P0 ÇEK/SENET YAŞAM DÖNGÜSÜ (2026-07-18): Tahsil Et/Ciro Et/Öde/Karşılıksız/İptal artık
    // check_note_view.php'de (durum makinesi + gerçek kasa/banka hareketi ile) — bu liste satırı
    // artık serbest bir "Durum" dropdown'ı İÇERMİYOR (kritik veri bütünlüğü açığıydı). Sil sadece
    // henüz finansal aksiyon almamış kayıtlarda gösterilir (checks_notes_can_delete()).
    if(can_edit_delete() && checks_notes_can_delete($r)){
        echo "<form method='post' style='display:inline' onsubmit=\"return confirm('Bu kaydı silmek istediğinize emin misiniz?')\">"
            ."<input type='hidden' name='id' value='".$rid."'>"
            ."<button class='df-btn df-btn--danger df-btn--sm' name='delete_cn' value='1'>🗑 Sil</button>"
            ."</form>";
    }
    // ÇEK/SENET SİLME REGRESYONU (2026-07-19, gerçek USER TEST) — Ciro Edildi/Tahsil Edildi/Ödendi
    // durumunda Sil/Düzenle GÖRÜNMEZ (bilerek), tek çıkış yolu "İşlemi Geri Al" idi ama bunun için
    // Detay sayfasına gitmek gerekiyordu — kullanıcı bunu "silemiyorum, bin kez söyledim" olarak
    // yaşadı. Artık liste satırından TEK TIKLA — Detay'daki checks_notes_reopen() İLE AYNI çağrı.
    if(can_edit_delete() && in_array('reopen', checks_notes_available_actions($r), true)){
        echo "<form method='post' style='display:inline' onsubmit=\"return confirm('Bu işlem geri alınacak, kayıt tekrar Portföyde/Bekliyor durumuna dönecek. Emin misiniz?')\">"
            ."<input type='hidden' name='id' value='".$rid."'>"
            ."<button class='df-btn df-btn--secondary df-btn--sm' name='reopen_cn' value='1'>↩️ Geri Al</button>"
            ."</form>";
    }
    echo "</div></td>";
    echo "</tr>";
}
if(!$rows) echo "<tr><td colspan='10' style='color:var(--df-ink-500)'>Kayıt yok.</td></tr>";
?>
</tbody>
</table></div>
</section>

<style>
body.nav-compact .df-form-grid-2{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:0 var(--df-space-4)}
body.nav-compact .df-form-span-2{grid-column:1 / -1}
@media(max-width:640px){body.nav-compact .df-form-grid-2{grid-template-columns:1fr}}
</style>
<script>
// Yön (Alınan/Verilen) değişince Durum seçeneklerinin ETİKETLERİ değişir (değerler aynı kalır) —
// ör. verilen bir çek için "Portföyde" yerine "Verildi (Bekliyor)" gösterilir (2026-07-03).
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

    fetch('ajax_quick_add.php', {method: 'POST', headers: {'X-CSRF-Token': window.CSRF_TOKEN}, body: fd})
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
