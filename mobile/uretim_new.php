<?php
require_once 'common.php';
require_once __DIR__.'/../job_stages_lib.php';
$pdo=db(); $me=(int)($_SESSION['user']['id']??0); $ok=''; $er='';

if($_SERVER['REQUEST_METHOD']==='POST'){
    try{
        $urun=trim($_POST['urun']??''); $qty=trim($_POST['qty']??'');
        if($urun==='') throw new Exception('Ürün/iş adı girin.');
        $type=$_POST['job_type']??'3d_imalat';
        $title='🏭 '.$urun.($qty!==''?' ×'.$qty:'');
        $no=function_exists('next_job_no')?next_job_no():'UR-'.date('YmdHis');
        $resp=(int)($_POST['responsible_personnel_id']??0)?:null;
        $desc='Üretim emri. Ürün: '.$urun.($qty!==''?' · Miktar: '.$qty:'').(trim($_POST['notes']??'')!==''?"\n".trim($_POST['notes']):'');
        $pdo->prepare("INSERT INTO jobs(job_no,title,description,job_type,responsible_personnel_id,due_date,status,priority,created_at) VALUES(?,?,?,?,?,?,'Yeni',?,NOW())")
            ->execute([$no,$title,$desc,$type,$resp,($_POST['due_date']??'')?:null,$_POST['priority']??'Normal']);
        $jid=(int)$pdo->lastInsertId();
        // Üretilen ürünü stok kartına bağla (yoksa oluştur) → üretim bitince OTOMATİK stoğa girer
        $pqty=(float)preg_replace('/[^0-9.]/','',str_replace(',','.',$qty));
        $pitem=(int)($_POST['produce_item_id']??0); // (geri uyumluluk)
        if(!$pitem && $urun!==''){
            try{
                $f=$pdo->prepare("SELECT id FROM stock_items WHERE name=? LIMIT 1"); $f->execute([$urun]); $row=$f->fetch();
                if($row){ $pitem=(int)$row['id']; }
                else { $pdo->prepare("INSERT INTO stock_items(name,unit,quantity) VALUES(?,'adet',0)")->execute([$urun]); $pitem=(int)$pdo->lastInsertId(); }
            }catch(Throwable $e){}
        }
        if($pitem>0 && $pqty>0){ try{ $pdo->prepare("UPDATE jobs SET produce_item_id=?,produce_qty=?,produced=0 WHERE id=?")->execute([$pitem,$pqty,$jid]); }catch(Throwable $e){} }
        ensure_stages($pdo,$jid,$type); // üretim aşamaları otomatik
        if($resp){
            $uu=$pdo->prepare("SELECT id FROM app_users WHERE personnel_id=? AND active=1 LIMIT 1"); $uu->execute([$resp]); $u=$uu->fetch();
            if($u){
                $ru=(int)$u['id'];
                // Detaylı İÇ MESAJ (ürün/adet/talimat) — atanan kişinin sohbetine
                $tipLbl=['3d_imalat'=>'3D İmalat','uv_baski'=>'UV Baskı','lazer'=>'Lazer','montaj'=>'Montaj','dis_atolye'=>'Dış Atölye','karma'=>'Karma'][$type]??$type;
                $msg="🏭 ÜRETİM EMRİ\nÜrün: ".$urun.($qty!==''?"\nMiktar: ".$qty:'')."\nTip: ".$tipLbl."\nÖncelik: ".($_POST['priority']??'Normal').(($_POST['due_date']??'')?"\nTermin: ".$_POST['due_date']:'').(trim($_POST['notes']??'')!==''?"\nTalimat: ".trim($_POST['notes']):'')."\nİş No: ".$no." (İş Takip → aç)";
                try{ $pdo->prepare("INSERT INTO internal_messages(sender_user_id,receiver_user_id,message,is_read) VALUES(?,?,?,0)")->execute([$me,$ru,$msg]); }catch(Throwable $e){}
                // Bildirim + push
                if(function_exists('notify_user')) notify_user($ru,'🏭 Üretim emri: '.$urun.($qty!==''?' ×'.$qty:''),$tipLbl.' · '.$no,'messages.php?with='.$me);
            }
        }
        try{ if(function_exists('activity_log')) activity_log('Üretim','Emir',$title,'','job',$jid,'job_view.php?id='.$jid,'🏭'); }catch(Throwable $e){}
        header('Location: job_view.php?id='.$jid); exit;
    }catch(Throwable $e){ $er=$e->getMessage(); }
}
topx('Yeni Üretim Emri');
$pers=$pdo->query("SELECT id,name FROM personnel WHERE COALESCE(active,1)=1 ORDER BY name")->fetchAll();
$urunler=$pdo->query("SELECT name FROM stock_items ORDER BY name LIMIT 200")->fetchAll(PDO::FETCH_COLUMN);
$stokUrunler=$pdo->query("SELECT id,name,unit FROM stock_items ORDER BY name LIMIT 300")->fetchAll();
?>
<?php if($er): ?><div class="err"><?=htmlspecialchars($er)?></div><?php endif; ?>
<div class="panel">
<form method="post">
  <label>Üretilecek Ürün *</label>
  <input name="urun" list="urunlist" required placeholder="Ürün adı yaz veya seç (yoksa otomatik stok kartı açılır)">
  <datalist id="urunlist"><?php foreach($urunler as $u) echo '<option value="'.htmlspecialchars($u).'">'; ?></datalist>
  <div style="display:flex;gap:10px"><div style="flex:1"><label>Miktar (adet) *</label><input name="qty" inputmode="decimal" required placeholder="örn. 1000"></div>
  <div style="flex:1"><label>Üretim Tipi</label><select name="job_type"><option value="3d_imalat">3D İmalat</option><option value="uv_baski">UV Baskı</option><option value="lazer">Lazer</option><option value="montaj">Montaj</option><option value="dis_atolye">Dış Atölye</option><option value="karma">Karma</option></select></div></div>
  <small class="muted" style="display:block;margin:2px 0 6px">📦 Üretim aşamaları tamamlanınca bu ürün, bu adet kadar otomatik stoğa girer.</small>
  <label>Sorumlu Personel</label>
  <select name="responsible_personnel_id"><option value="">— Atanmadı —</option><?php foreach($pers as $p): ?><option value="<?=$p['id']?>"><?=htmlspecialchars($p['name'])?></option><?php endforeach; ?></select>
  <div style="display:flex;gap:10px"><div style="flex:1"><label>Öncelik</label><select name="priority"><option>Normal</option><option>Yüksek</option><option>Acil</option></select></div>
  <div style="flex:1"><label>Termin</label><input type="date" name="due_date"></div></div>
  <label>Açıklama / Talimat</label><textarea name="notes" rows="3" placeholder="Üretim talimatı, ölçü, renk vb."></textarea>
  <button class="btn dark" style="width:100%;padding:14px;margin-top:8px">🏭 Üretim Emri Oluştur (aşamalı)</button>
</form>
</div>
<p class="muted" style="text-align:center;font-size:13px">Emir oluşunca iş tipine göre üretim aşamaları otomatik açılır.</p>
<?php botx(); ?>
