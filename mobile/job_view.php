<?php
require_once 'common.php';
require_once __DIR__.'/../job_stages_lib.php';
require_once __DIR__.'/../share_lib.php';
$pdo=db();
$id=(int)($_GET['id'] ?? 0);
$me=(int)($_SESSION['user']['id'] ?? 0);
$msg='';

stage_ajax_respond($pdo,$id); // aşama butonları AJAX (tüm sayfa yenilenmesin → donma yok)

// İşi sil (admin-only, topx'tan ÖNCE)
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['delete_job'])){
    if(!is_admin()){
        $_SESSION['job_err']='Bu işlem için yetkiniz yok.';
        header('Location: job_view.php?id='.$id); exit;
    }
    try{
        try{ $pdo->exec("SET FOREIGN_KEY_CHECKS=0"); }catch(Throwable $e){}
        // Alt kayıtları sil
        foreach(['job_stages'=>'job_id','job_files'=>'job_id','job_notes'=>'job_id','tasks'=>'job_id'] as $ct=>$cf){
            try{ $pdo->prepare("DELETE FROM `$ct` WHERE `$cf`=?")->execute([$id]); }catch(Throwable $e){}
        }
        // İşi sil
        $pdo->prepare("DELETE FROM jobs WHERE id=?")->execute([$id]);
        try{ $pdo->exec("SET FOREIGN_KEY_CHECKS=1"); }catch(Throwable $e){}
        try{ if(function_exists('activity_log')) activity_log('Silme','İş silindi','jobs #'.$id,'','admin',null,'jobs.php','🗑'); }catch(Throwable $e){}
        header('Location: jobs.php?deleted=1'); exit;
    }catch(Throwable $e){
        $_SESSION['job_err']='Silinemedi: '.$e->getMessage();
        header('Location: job_view.php?id='.$id); exit;
    }
}

// Sorumlu atama / durum güncelleme (çıktıdan önce)
if($_SERVER['REQUEST_METHOD']==='POST'){
    // Güvenlik: görüntüleme (GET) bilinçli olarak herkese açık (bildirimden açma), ama yazma
    // işlemleri 'jobs' yetkisi VEYA işin kendisine atanmış olması gerektiriyor (2026-07-03 denetimi).
    if(!job_can_write($pdo,$id)){
        $_SESSION['job_err']='Bu işlem için yetkiniz yok.';
        header('Location: job_view.php?id='.$id); exit;
    }
    try{
        // Sorumlu ATAMA/DEĞİŞTİRME sadece 'jobs' yetkisi olanlarda (UI zaten $isAdmin'e gizli) —
        // işin "sahibi" olmak, işi başkasına devretme hakkı vermemeli (job_can_write'ın own-job
        // istisnasını burada bilerek daraltıyoruz).
        if(isset($_POST['assign']) && !(function_exists('user_can') && user_can('jobs'))){
            $_SESSION['job_err']='Bu işlem için yetkiniz yok.';
            header('Location: job_view.php?id='.$id); exit;
        }
        if(isset($_POST['assign'])){
            $resp=(int)$_POST['responsible_personnel_id'] ?: null;
            $pdo->prepare("UPDATE jobs SET responsible_personnel_id=? WHERE id=?")->execute([$resp,$id]);
            if($resp){
                $j=$pdo->prepare("SELECT title,job_no FROM jobs WHERE id=?"); $j->execute([$id]); $jr=$j->fetch();
                $pn=$pdo->prepare("SELECT name FROM personnel WHERE id=?"); $pn->execute([$resp]); $pname=$pn->fetch()['name']??'';
                $uu=$pdo->prepare("SELECT id FROM app_users WHERE personnel_id=? AND active=1 LIMIT 1"); $uu->execute([$resp]); $u=$uu->fetch();
                if($u && function_exists('notify_user')) notify_user((int)$u['id'],'📋 İş atandı: '.($jr['title']??''),$pname.' · '.($jr['job_no']??''),'job_view.php?id='.$id);
                try{ if(function_exists('activity_log')) activity_log('İş','Atama',$pname.' · '.($jr['title']??''),'','job',$id,'job_view.php?id='.$id,'📋'); }catch(Throwable $e){}
            }
        }
        if(isset($_POST['set_status'])){
            $pdo->prepare("UPDATE jobs SET status=? WHERE id=?")->execute([$_POST['status'],$id]);
            try{ if(function_exists('activity_log')) activity_log('İş','Durum',$_POST['status'],'','job',$id,'job_view.php?id='.$id,'🔄'); }catch(Throwable $e){}
            // İş "Tamamlandı/Teslim" ise üretileni otomatik stoğa ekle (ürün bağlıysa, bir kez)
            if(in_array($_POST['status'],['Tamamlandı','Teslim Edildi'],true)){
                list($pok,$pmsg)=produce_to_stock($pdo,$id);
                if($pok) $_SESSION['flash']='📦 '.$pmsg;
            }
        }
        // Dosya/foto yükleme
        if(isset($_FILES['file']) && $_FILES['file']['error']===0){
            $f=$_FILES['file'];
            $ext=strtolower(pathinfo($f['name'],PATHINFO_EXTENSION));
            $allowed=['jpg','jpeg','png','gif','webp','heic','pdf','mp4','mov','m4a','mp3','wav','doc','docx','xls','xlsx'];
            if(in_array($ext,$allowed) && $f['size']<=25*1024*1024){
                // Çalışan web sürümüyle AYNI klasör: uploads/job_files
                $dir=__DIR__.'/../uploads/job_files';
                if(!is_dir($dir)) @mkdir($dir,0755,true);
                if(!is_writable($dir)) @chmod($dir,0777);
                $stored=bin2hex(random_bytes(8)).'.'.$ext;
                $dest=$dir.'/'.$stored; $saved=false;
                if(@move_uploaded_file($f['tmp_name'],$dest)) $saved=true;
                elseif(@copy($f['tmp_name'],$dest)) $saved=true;
                else { $data=@file_get_contents($f['tmp_name']); if($data!==false && @file_put_contents($dest,$data)!==false) $saved=true; }
                if($saved){
                    @chmod($dest,0644);
                    $isImg=in_array($ext,['jpg','jpeg','png','gif','webp','heic']);
                    $token=bin2hex(random_bytes(12));
                    $pdo->prepare("INSERT INTO job_files(job_id,uploaded_by,file_type,original_name,stored_name,file_path,mime_type,file_size,share_token,approval_status)
                        VALUES(?,?,?,?,?,?,?,?,?,'Taslak')")
                        ->execute([$id,$me,$isImg?'image':'file',$f['name'],$stored,'uploads/job_files/'.$stored,$f['type'] ?? '',$f['size'],$token]);
                    try{ if(function_exists('activity_log')) activity_log('İş','Dosya',$f['name'],'','job',$id,'job_view.php?id='.$id,'📎'); }catch(Throwable $e){}
                }
            }
        }
        // Dosya sil
        if(isset($_POST['del_file'])){
            $fid=(int)$_POST['del_file'];
            $r=$pdo->prepare("SELECT stored_name FROM job_files WHERE id=? AND job_id=?"); $r->execute([$fid,$id]); $fr=$r->fetch();
            if($fr){ @unlink(__DIR__.'/../uploads/'.$fr['stored_name']); $pdo->prepare("DELETE FROM job_files WHERE id=?")->execute([$fid]); }
        }
        // İş notu ekle
        if(isset($_POST['add_note']) && trim($_POST['note']??'')!==''){
            try{ $pdo->prepare("INSERT INTO job_notes(job_id,user_id,note) VALUES(?,?,?)")->execute([$id,$me,trim($_POST['note'])]); }catch(Throwable $e){}
            try{ if(function_exists('activity_log')) activity_log('İş','Not',mb_substr(trim($_POST['note']),0,60),'','job',$id,'job_view.php?id='.$id,'📝'); }catch(Throwable $e){}
        }
        // İş notu sil
        if(isset($_POST['del_note'])){
            try{ $pdo->prepare("DELETE FROM job_notes WHERE id=? AND job_id=?")->execute([(int)$_POST['del_note'],$id]); }catch(Throwable $e){}
        }
        // Üretim aşaması
        if(isset($_POST['init_stages'])||isset($_POST['stage_set'])){
            $jt=''; try{ $q=$pdo->prepare("SELECT job_type FROM jobs WHERE id=?"); $q->execute([$id]); $jt=$q->fetch()['job_type']??''; }catch(Throwable $e){}
            handle_stage_post($pdo,$id,$jt);
        }
        // Üretimi stoğa ekle / stok ürünü bağla
        handle_produce_post($pdo,$id);
        // İş bilgisi düzenle
        if(isset($_POST['save_job']) && trim($_POST['title']??'')!==''){
            $pdo->prepare("UPDATE jobs SET title=?,description=?,job_type=?,customer_id=?,due_date=?,priority=? WHERE id=?")
                ->execute([trim($_POST['title']),trim($_POST['description']??''),$_POST['job_type']??'karma',(int)($_POST['customer_id']??0)?:null,($_POST['due_date']??'')?:null,$_POST['priority']??'Normal',$id]);
            try{ if(function_exists('activity_log')) activity_log('İş','Düzenleme',trim($_POST['title']),'','job',$id,'job_view.php?id='.$id,'✏️'); }catch(Throwable $e){}
        }
    }catch(Throwable $e){}
    header('Location: job_view.php?id='.$id); exit;
}

topx('İş Detayı');
if(!empty($_SESSION['flash'])){ echo '<div class="panel" style="text-align:center;font-weight:700">'.htmlspecialchars($_SESSION['flash']).'</div>'; unset($_SESSION['flash']); }
try{
    $s=$pdo->prepare("SELECT j.*, c.name customer, p.name responsible FROM jobs j LEFT JOIN contacts c ON c.id=j.customer_id LEFT JOIN personnel p ON p.id=j.responsible_personnel_id WHERE j.id=?");
    $s->execute([$id]); $j=$s->fetch();
    if(!$j) throw new Exception('İş bulunamadı.');
    $pers=$pdo->query("SELECT id,name FROM personnel WHERE COALESCE(active,1)=1 ORDER BY name")->fetchAll();
    $cs=$pdo->query("SELECT id,name FROM contacts ORDER BY name")->fetchAll();
?>
<?php if($__navMode === 'legacy'): ?>
<div class="panel">
  <h2 style="margin:0 0 4px"><?=htmlspecialchars($j['title'])?></h2>
  <div class="muted"><?=htmlspecialchars($j['job_no']??'')?> · <?=htmlspecialchars($j['status'])?><?=$j['due_date']?' · 📅 '.htmlspecialchars($j['due_date']):''?></div>
  <?php if($j['customer']): ?><div style="margin-top:8px">👤 <?=htmlspecialchars($j['customer'])?></div><?php endif; ?>
  <div style="margin-top:4px">👷 Sorumlu: <b><?=htmlspecialchars($j['responsible'] ?: 'Atanmamış')?></b></div>
  <?php if($j['description']): ?><p class="muted" style="margin-top:10px"><?=nl2br(htmlspecialchars($j['description']))?></p><?php endif; ?>
  <a class="btn dark" href="thread_open.php?type=job&ref=<?=$id?>" style="display:block;text-align:center;margin-top:10px">💬 İş Sohbeti</a>
  <?php
    $jobPhone=preg_replace('/\D/','',($j['customer_id']?($pdo->query("SELECT phone FROM contacts WHERE id=".(int)$j['customer_id'])->fetch()['phone']??''):''));
    $jobTxt="📋 İş: ".$j['title']."\nNo: ".($j['job_no']??'')."\nDurum: ".$j['status'].($j['customer']?"\nMüşteri: ".$j['customer']:'').($j['responsible']?"\nSorumlu: ".$j['responsible']:'').($j['due_date']?"\nTermin: ".$j['due_date']:'');
    echo '<div style="margin-top:6px;font-size:13px;opacity:.85">📤 İşi paylaş:</div>';
    echo share_buttons($jobTxt,$jobPhone,'İş: '.$j['title']);
    if(!empty($j['due_date'])) echo '<a href="../ics.php?job='.$id.'" class="btn" style="display:block;text-align:center;margin-top:8px;background:#0ea5e9;color:#fff">📅 Takvime Ekle</a>';
  ?>
</div>

<details class="panel">
  <summary style="font-weight:900;cursor:pointer">✏️ İşi Düzenle</summary>
  <form method="post" style="margin-top:10px">
    <label>Başlık</label><input name="title" value="<?=htmlspecialchars($j['title'])?>" required>
    <label>Müşteri</label>
    <select name="customer_id"><option value="">— Yok —</option><?php foreach($cs as $cc): ?><option value="<?=$cc['id']?>" <?=$j['customer_id']==$cc['id']?'selected':''?>><?=htmlspecialchars($cc['name'])?></option><?php endforeach; ?></select>
    <div style="display:flex;gap:10px"><div style="flex:1"><label>İş Tipi</label>
      <select name="job_type"><?php foreach(['karma'=>'Karma','3d_imalat'=>'3D İmalat','uv_baski'=>'UV Baskı','lazer'=>'Lazer','grafik_tasarim'=>'Grafik','montaj'=>'Montaj','dis_atolye'=>'Dış Atölye'] as $k=>$v): ?><option value="<?=$k?>" <?=($j['job_type']??'')===$k?'selected':''?>><?=$v?></option><?php endforeach; ?></select></div>
      <div style="flex:1"><label>Termin</label><input type="date" name="due_date" value="<?=htmlspecialchars($j['due_date']??'')?>"></div></div>
    <label>Öncelik</label><select name="priority"><?php foreach(['Normal','Yüksek','Acil'] as $pr): ?><option <?=($j['priority']??'')===$pr?'selected':''?>><?=$pr?></option><?php endforeach; ?></select>
    <label>Açıklama</label><textarea name="description" rows="3"><?=htmlspecialchars($j['description']??'')?></textarea>
    <button class="btn dark" name="save_job" value="1" style="width:100%;padding:13px;margin-top:8px">💾 Kaydet</button>
  </form>
</details>

<?php if($isAdmin): ?>
<div class="panel">
  <form method="post" onsubmit="return confirm('Bu işi ve bağlı tüm verileri KALICI olarak silmek istediğinize emin misiniz?')" style="margin:0">
    <button class="btn" name="delete_job" value="1" style="width:100%;background:#dc2626;color:#fff;padding:12px;border-radius:14px">🗑 İşi Sil</button>
  </form>
</div>
<?php endif; ?>

<div class="panel">
  <b>🏭 Üretim Aşamaları</b>
  <div style="margin-top:8px"><?=stages_html($pdo,$id,'job_view.php?id='.$id)?></div>
  <?=produce_box_html($pdo,$j)?>
</div>

<div class="panel" style="text-align:center">
  <b>📷 İş QR Kodu</b>
  <p class="small" style="margin:4px 0 8px">Üretimde işi takip için tara</p>
  <img loading="lazy" decoding="async" src="https://api.qrserver.com/v1/create-qr-code/?size=180x180&data=<?=urlencode(base_url().'job_view.php?id='.$id)?>" alt="QR" style="width:180px;height:180px;background:#fff;border-radius:12px;padding:6px">
</div>

<?php if($isAdmin): ?>
<div class="panel"><b>Sorumlu Ata / Değiştir</b>
<form method="post" style="display:flex;gap:8px;margin-top:8px">
  <select name="responsible_personnel_id" style="flex:1;margin:0">
    <option value="">— Atanmadı —</option>
    <?php foreach($pers as $p): ?><option value="<?=$p['id']?>" <?=$j['responsible_personnel_id']==$p['id']?'selected':''?>><?=htmlspecialchars($p['name'])?></option><?php endforeach; ?>
  </select>
  <button class="btn dark" name="assign" value="1" style="width:auto;padding:12px 16px">Ata</button>
</form>
</div>
<?php endif; ?>

<div class="panel"><b>Durum Güncelle</b>
<div style="display:flex;gap:8px;margin-top:8px;flex-wrap:wrap">
  <?php foreach(['Devam Ediyor'=>'▶ Başla','Tamamlandı'=>'✓ Tamamla','Teslim Edildi'=>'📦 Teslim','İptal'=>'✕ İptal'] as $st=>$lbl): ?>
  <form method="post" style="flex:1;min-width:46%;margin:0"><input type="hidden" name="status" value="<?=$st?>"><button class="btn" name="set_status" value="1" style="width:100%;background:<?=$st==='Tamamlandı'?'#16a34a':($st==='İptal'?'#7f1d1d':'#334155')?>;color:#fff"><?=$lbl?></button></form>
  <?php endforeach; ?>
</div>
</div>

<div class="panel">
  <b>📝 Notlar</b>
  <form method="post" style="margin-top:8px">
    <textarea name="note" rows="2" placeholder="İşle ilgili not yaz…" style="margin:0"></textarea>
    <button class="btn dark" name="add_note" value="1" style="width:100%;padding:11px;margin-top:6px">+ Not Ekle</button>
  </form>
  <?php
  try{ $nt=$pdo->prepare("SELECT n.*, u.full_name, u.username FROM job_notes n LEFT JOIN app_users u ON u.id=n.user_id WHERE n.job_id=? ORDER BY n.id DESC"); $nt->execute([$id]); $notes=$nt->fetchAll(); }catch(Throwable $e){ $notes=[]; }
  foreach($notes as $n): $who=$n['full_name']?:$n['username']?:'?'; ?>
    <div class="item" style="display:flex;justify-content:space-between;align-items:flex-start;gap:8px">
      <div style="flex:1;min-width:0"><?=nl2br(htmlspecialchars($n['note']))?><br><small class="muted"><?=htmlspecialchars($who)?> · <?=htmlspecialchars(date('d.m.Y H:i',strtotime($n['created_at'])))?></small></div>
      <form method="post" style="margin:0"><button name="del_note" value="<?=(int)$n['id']?>" style="background:none;border:0;color:#f87171;font-size:18px">🗑️</button></form>
    </div>
  <?php endforeach; ?>
  <?php if(!$notes): ?><p class="muted" style="margin:8px 0 0">Henüz not yok.</p><?php endif; ?>
</div>

<div class="panel">
  <b>📎 Fotoğraf / Dosya</b>
  <form style="margin-top:8px" id="upf" onsubmit="return false">
    <input type="file" name="file" id="jfile" accept="image/*,application/pdf,video/*,audio/*" multiple required style="background:var(--df-surface-sunken,rgba(255,255,255,.07))">
    <div id="jlbl" style="color:var(--df-ink-500,#94a3b8);font-size:12px;margin:4px 0"></div>
    <button class="btn dark" type="submit" id="upbtn" style="width:100%;padding:12px">⬆ Yükle</button>
  </form>
  <script>
  (function(){
    function comp(file,cb){ if(!file||!file.type||file.type.indexOf('image/')!==0){cb(file,file?file.name:'dosya');return;}
      var img=new Image(),u=URL.createObjectURL(file);
      img.onload=function(){var max=1600,w=img.width,h=img.height;if(w>max||h>max){if(w>h){h=Math.round(h*max/w);w=max;}else{w=Math.round(w*max/h);h=max;}}
        var c=document.createElement('canvas');c.width=w;c.height=h;c.getContext('2d').drawImage(img,0,0,w,h);URL.revokeObjectURL(u);
        c.toBlob(function(b){cb(b||file,(file.name||'foto').replace(/\.[^.]+$/,'')+'.jpg');},'image/jpeg',0.82);};
      img.onerror=function(){cb(file,file.name||'dosya');};img.src=u;}
    var fi=document.getElementById('jfile'),lbl=document.getElementById('jlbl'),items=[];
    fi.addEventListener('change',function(){
      var fs=this.files; items=[];
      if(!fs||!fs.length){lbl.textContent='';return;}
      lbl.textContent='⏳ '+fs.length+' dosya hazırlanıyor...';
      var done=0,total=fs.length;
      for(var i=0;i<total;i++){ comp(fs[i],function(b,n){ items.push({b:b,n:n}); done++; if(done>=total){ var kb=0;items.forEach(function(it){kb+=(it.b.size||0);}); lbl.textContent='📎 '+items.length+' dosya ('+Math.round(kb/1024)+' KB) hazır'; } }); }
    });
    document.getElementById('upbtn').addEventListener('click',function(){
      if(!items.length){ lbl.textContent='Önce dosya seç'; return; }
      var b=this;b.disabled=true;b.textContent='⏳ Yükleniyor...';var failed=0;
      function sendWithRetry(it,tries,cb){
        var fd=new FormData();fd.append('file',it.b,it.n||'foto.jpg');
        fetch(location.href,{method:'POST',headers:{'X-CSRF-Token':window.CSRF_TOKEN},body:fd,credentials:'same-origin'}).then(function(r){return r.text();})
          .then(function(){cb(true);}).catch(function(){ if(tries>1){setTimeout(function(){sendWithRetry(it,tries-1,cb);},600);} else cb(false); });
      }
      function run(i){
        if(i>=items.length){ if(failed>0){b.disabled=false;b.textContent='⬆ Yükle';lbl.textContent='⚠️ '+failed+' dosya gitmedi';setTimeout(function(){location.reload();},1500);} else location.reload(); return; }
        if(items.length>1) lbl.textContent='⬆ Yükleniyor '+(i+1)+'/'+items.length;
        sendWithRetry(items[i],3,function(ok){ if(!ok) failed++; setTimeout(function(){run(i+1);},150); });
      }
      run(0);
    });
  })();
  </script>
  <?php
  $custPhone=preg_replace('/\D/','',$j['customer'] ? ($pdo->query("SELECT phone FROM contacts WHERE id=".(int)$j['customer_id'])->fetch()['phone'] ?? '') : '');
  $files=$pdo->prepare("SELECT * FROM job_files WHERE job_id=? ORDER BY id DESC"); $files->execute([$id]); $flist=$files->fetchAll();
  if($flist){
    echo '<div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-top:12px">';
    foreach($flist as $fl){
      $link=base_url().'public_file.php?token='.$fl['share_token'];
      $wa='https://wa.me/'.$custPhone.'?text='.rawurlencode('Onayınıza sunulan dosya: '.$link);
      $appr=$fl['approval_status'];
      $aColor=$appr==='Onaylandı'?'#22c55e':($appr==='Reddedildi'?'#f87171':'#94a3b8');
      echo '<div style="background:var(--df-surface-sunken,rgba(255,255,255,.06));border:1px solid var(--df-hairline,rgba(255,255,255,.1));border-radius:14px;padding:8px">';
      $fpath='../'.htmlspecialchars($fl['file_path']);
      $fext=strtolower(pathinfo($fl['file_path'],PATHINFO_EXTENSION));
      $fvid=in_array($fext,['mp4','mov','webm','m4v']);
      if($fl['file_type']==='image'){
        echo '<img loading="lazy" decoding="async" src="'.$fpath.'" onclick="ACANS_VIEW(\''.$fpath.'\',\'image\')" style="width:100%;height:90px;object-fit:cover;border-radius:10px;cursor:pointer">';
      }elseif($fvid){
        echo '<div onclick="ACANS_VIEW(\''.$fpath.'\',\'video\')" style="height:90px;display:flex;align-items:center;justify-content:center;font-size:34px;cursor:pointer">🎬</div>';
      }else{
        echo '<div onclick="ACANS_VIEW(\''.$fpath.'\',\'file\')" style="height:90px;display:flex;align-items:center;justify-content:center;font-size:34px;cursor:pointer">📄</div>';
      }
      echo '<small style="display:block;color:var(--df-ink-500,#94a3b8);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin-top:4px">'.htmlspecialchars($fl['original_name']).'</small>';
      echo '<small style="color:'.$aColor.';font-weight:900">'.htmlspecialchars($appr).'</small>';
      echo '<div style="display:flex;gap:6px;margin-top:6px">';
      if($custPhone) echo '<a href="'.htmlspecialchars($wa).'" class="btn" style="flex:1;background:#16a34a;color:#fff;padding:7px;font-size:12px">WhatsApp</a>';
      echo '<a href="'.htmlspecialchars($link).'" target="_blank" class="btn" style="flex:1;background:#334155;color:#fff;padding:7px;font-size:12px">Link</a>';
      echo '<form method="post" style="margin:0"><button name="del_file" value="'.(int)$fl['id'].'" class="btn" style="background:#7f1d1d;color:#fff;padding:7px 9px;font-size:12px" onclick="return confirm(\'Silinsin mi?\')">🗑</button></form>';
      echo '</div></div>';
    }
    echo '</div>';
  }else{ echo '<p class="muted" style="margin-top:10px">Henüz dosya yok.</p>'; }
  ?>
</div>

<div class="panel">
  <b>📜 Geçmiş</b>
  <?php
  try{
    $hl=$pdo->prepare("SELECT action,title,icon,user_name,created_at FROM activity_logs WHERE entity_type='job' AND entity_id=? ORDER BY id DESC LIMIT 50");
    $hl->execute([$id]); $hist=$hl->fetchAll();
  }catch(Throwable $e){ $hist=[]; }
  if(!$hist){ echo '<p class="muted" style="margin:10px 0 0">Henüz hareket kaydı yok.</p>'; }
  else {
    echo '<div style="margin-top:8px;border-left:2px solid var(--df-hairline,rgba(255,255,255,.12));padding-left:12px">';
    foreach($hist as $h){
      echo '<div style="margin-bottom:12px;position:relative">';
      echo '<div style="position:absolute;left:-18px;top:2px;font-size:13px">'.($h['icon']?:'•').'</div>';
      echo '<b style="font-size:14px">'.htmlspecialchars($h['action']).'</b> '.htmlspecialchars(mb_substr($h['title'],0,50));
      echo '<br><small class="muted">'.htmlspecialchars($h['user_name']?:'').' · '.htmlspecialchars(date('d.m.Y H:i',strtotime($h['created_at']))).'</small>';
      echo '</div>';
    }
    echo '</div>';
  }
  ?>
</div>

<?php else: ?>
<?php
// PX-001B — İş Detay v1 (mockup turu kapandı, Product Owner kararı 2026-07-16). Legacy'nin yazma/
// POST akışları hiç değişmedi — bu sadece yeni bir OKUMA dalı. Mobil zaten activity_logs kullandığı
// için job_detail_timeline() birebir aynı kaynağı okuyor (bkz. backlog PX-001B-ÖN notu).
$__pendingApprovals = job_detail_pending_approvals($pdo, $id);
$__nextStep = job_detail_next_step($j, $__pendingApprovals);
$__timeline = job_detail_timeline($id, 4);
$__jdPhone = preg_replace('/\D/','', ($j['customer_id'] ? ($pdo->query("SELECT phone FROM contacts WHERE id=".(int)$j['customer_id'])->fetch()['phone'] ?? '') : ''));
$__jdTxt = "📋 İş: ".$j['title']."\nNo: ".($j['job_no']??'')."\nDurum: ".$j['status'].($j['customer']?"\nMüşteri: ".$j['customer']:'').($j['due_date']?"\nTermin: ".$j['due_date']:'');
try{ $__s=$pdo->prepare("SELECT n.*, u.full_name, u.username FROM job_notes n LEFT JOIN app_users u ON u.id=n.user_id WHERE n.job_id=? ORDER BY n.id DESC LIMIT 1"); $__s->execute([$id]); $__jdNoteRow=$__s->fetch(); }catch(Throwable $e2){ $__jdNoteRow=null; }
try{ $__s=$pdo->prepare("SELECT * FROM job_files WHERE job_id=? ORDER BY id DESC LIMIT 2"); $__s->execute([$id]); $__jdFileRows=$__s->fetchAll(); }catch(Throwable $e2){ $__jdFileRows=[]; }
$__jdOverdue = !empty($j['due_date']) && $j['due_date'] < date('Y-m-d') && !in_array($j['status'], ['Tamamlandı','İptal','Teslim Edildi'], true);
$__jdDaysLate = $__jdOverdue ? (int)round((strtotime(date('Y-m-d')) - strtotime($j['due_date']))/86400) : 0;
$__jdNextIcons = ['call'=>'phone','money'=>'send','check'=>'check','clock'=>'calendar'];
?>
<div class="df-jd">
  <div class="df-jd-hero">
    <span class="df-badge df-badge--<?=($__jdOverdue?'danger':'info')?>"><?=h($__jdOverdue ? 'GECİKTİ' : mb_strtoupper($j['status'],'UTF-8'))?></span>
    <div class="df-jd-title"><?=h($j['title'])?></div>
    <div class="df-jd-sub"><?=h($j['customer'] ?: 'Cari yok')?><?php if($__jdOverdue): ?><span class="df-jd-dot"></span><b><?=(int)$__jdDaysLate?> gün gecikti</b><?php endif; ?></div>
  </div>

  <?php if($__nextStep): ?>
  <div class="df-jd-next">
    <div class="df-jd-next-ic"><?=ds_icon($__jdNextIcons[$__nextStep['icon']] ?? 'check', 18)?></div>
    <div class="df-jd-next-body">
      <div class="df-jd-next-label">Sonraki Adım</div>
      <div class="df-jd-next-title"><?=h($__nextStep['title'])?></div>
      <div class="df-jd-next-sub"><?=h($__nextStep['sub'])?></div>
    </div>
  </div>
  <?php else: ?>
  <div class="df-panel" style="text-align:center;padding:18px 14px">
    <div style="font-weight:700;font-size:13px">Şu an acil bir adım yok</div>
  </div>
  <?php endif; ?>

  <?php if($__timeline): ?>
  <div>
    <div class="df-jd-lab">Zaman Akışı</div>
    <div>
      <?php foreach(array_reverse($__timeline) as $__tlRow): ?>
      <div class="df-jd-tl-row"><span class="df-jd-tl-t"><?=h(date('H:i', strtotime($__tlRow['created_at'])))?></span><span class="df-jd-tl-dot"></span><span class="df-jd-tl-e"><?=h($__tlRow['title'] ?: $__tlRow['action'])?></span></div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <div>
    <div class="df-jd-lab">İlgili Kişiler</div>
    <div class="df-jd-people">
      <div class="df-jd-p-row"><span class="df-jd-p-role">Müşteri</span><span class="df-jd-p-name"><?=h($j['customer'] ?: '-')?></span></div>
      <div class="df-jd-p-row"><span class="df-jd-p-role">Sorumlu</span><span class="df-jd-p-name"><?=h($j['responsible'] ?: '-')?></span></div>
    </div>
  </div>

  <?php if($__jdFileRows): ?>
  <div id="df-jd-files">
    <div class="df-jd-lab">Dosyalar</div>
    <div class="df-jd-files">
      <?php foreach($__jdFileRows as $__f): $__ext=strtolower(pathinfo($__f['original_name'] ?? '', PATHINFO_EXTENSION)); $__isImg = in_array($__ext,['jpg','jpeg','png','gif','webp'],true) || strpos((string)$__f['mime_type'],'image/')===0; ?>
      <a class="df-jd-f-row" href="<?=$__f['file_path'] ? '../'.h($__f['file_path']) : '#'?>" target="_blank" rel="noopener">
        <span class="df-jd-f-badge<?=($__isImg?' df-jd-f-badge--img':'')?>"><?=h($__isImg?'IMG':mb_strtoupper($__ext ?: 'DOSYA','UTF-8'))?></span>
        <span class="df-jd-f-name"><?=h($__f['original_name'] ?: 'Dosya')?></span>
        <span class="df-jd-f-time"><?=h(function_exists('activity_time_ago') ? activity_time_ago($__f['created_at']) : $__f['created_at'])?></span>
      </a>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <?php if($__jdNoteRow): $__who=$__jdNoteRow['full_name'] ?: $__jdNoteRow['username'] ?: '?'; ?>
  <div>
    <div class="df-jd-lab">Notlar</div>
    <div class="df-jd-note"><b><?=h($__who)?>:</b> <?=nl2br(h($__jdNoteRow['note']))?><div class="df-jd-note-meta"><?=h($__jdNoteRow['created_at'])?></div></div>
  </div>
  <?php endif; ?>

  <div class="df-jd-bar">
    <?php if($__jdPhone): ?><a class="df-jd-bar-btn" href="tel:<?=h($__jdPhone)?>"><?=ds_icon('phone',17)?><span>Ara</span></a><?php endif; ?>
    <a class="df-jd-bar-btn" href="<?=h(wa_link($__jdTxt,$__jdPhone))?>" target="_blank" rel="noopener"><span style="font-size:15px">📱</span><span>WhatsApp</span></a>
    <a class="df-jd-bar-btn" href="#df-jd-files"><span style="font-size:15px">📎</span><span>Dosya</span></a>
    <a class="df-jd-bar-btn" href="<?=h(mail_link('İş: '.$j['title'],$__jdTxt))?>"><?=ds_icon('send',17)?><span>Paylaş</span></a>
    <form method="post" style="flex:1;margin:0"><input type="hidden" name="set_status" value="1"><input type="hidden" name="status" value="Tamamlandı"><button type="submit" class="df-jd-bar-btn is-done" style="width:100%;border:0;background:none"><?=ds_icon('check',17)?><span>Tamamlandı</span></button></form>
  </div>
</div>
<?php endif; ?>

<?php
}catch(Throwable $e){ echo '<div class="err">'.htmlspecialchars($e->getMessage()).'</div>'; }
botx();
