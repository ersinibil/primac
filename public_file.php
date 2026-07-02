<?php
// Müşteri onay sayfası — GİRİŞSİZ. Paylaşılan dosyayı gösterir, onay/ret alır.
require_once __DIR__.'/boot.php';
$pdo=db();
$token=preg_replace('/[^a-f0-9]/','',$_GET['token'] ?? '');
$ok=''; $err='';

$st=$pdo->prepare("SELECT f.*, j.title job_title, j.job_no, j.customer_id, c.name customer FROM job_files f
    LEFT JOIN jobs j ON j.id=f.job_id LEFT JOIN contacts c ON c.id=j.customer_id WHERE f.share_token=? LIMIT 1");
$st->execute([$token]);
$file=$st->fetch();

if($file && $_SERVER['REQUEST_METHOD']==='POST'){
    $decision=$_POST['decision'] ?? '';
    if(in_array($decision,['Onaylandı','Reddedildi'])){
        $pdo->prepare("UPDATE job_files SET approval_status=?, customer_note=? WHERE id=?")
            ->execute([$decision,trim($_POST['note'] ?? ''),$file['id']]);
        try{ $pdo->prepare("INSERT INTO internal_notifications(title,message,action_url,is_read) VALUES(?,?,?,0)")
            ->execute(['🖼 Müşteri '.$decision.': '.$file['original_name'],($file['customer']?:'').' · '.($file['job_no']?:''),!empty($file['job_id'])?('job_view.php?id='.$file['job_id']):null]); }catch(Throwable $e){}
        try{ if(function_exists('activity_log')) activity_log('Onay','Müşteri',$decision.' · '.$file['original_name'],'','job_file',(int)$file['id'],'',$decision==='Onaylandı'?'✅':'❌'); }catch(Throwable $e){}
        $ok='Yanıtınız kaydedildi. Teşekkürler.';
        $file['approval_status']=$decision;
    }
}
?><!doctype html><html lang="tr"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Onay · ACANS OTS</title>
<style>
body{margin:0;background:#0f172a;color:#fff;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Arial,sans-serif;min-height:100vh}
.wrap{max-width:560px;margin:auto;padding:18px}
.card{background:#1e293b;border:1px solid rgba(255,255,255,.1);border-radius:20px;padding:18px;margin-bottom:14px}
h1{font-size:20px}.muted{color:#94a3b8;font-size:14px}
img{width:100%;border-radius:14px}
.btn{display:inline-block;border:0;border-radius:14px;padding:14px;font-weight:900;font-size:16px;width:100%;cursor:pointer;text-decoration:none;text-align:center;color:#fff}
textarea{width:100%;border:0;border-radius:12px;padding:12px;margin:8px 0;font-size:15px}
.ok{background:#16a34a;color:#fff;padding:12px;border-radius:12px}.tag{display:inline-block;padding:4px 12px;border-radius:999px;font-weight:900;font-size:13px}
</style></head><body><div class="wrap">
<?php if(!$file): ?>
  <div class="card"><h1>Dosya bulunamadı</h1><p class="muted">Bağlantı geçersiz veya süresi dolmuş olabilir.</p></div>
<?php else: ?>
  <div class="card">
    <h1>📋 <?=htmlspecialchars($file['job_title'] ?: 'Dosya Onayı')?></h1>
    <p class="muted"><?=htmlspecialchars($file['customer'] ?: '')?><?=$file['job_no']?' · '.htmlspecialchars($file['job_no']):''?></p>
    <?php
    $img=$file['file_type']==='image';
    $url=base_url().htmlspecialchars($file['file_path']);
    if($img): ?><img src="<?=$url?>" alt=""><?php else: ?>
      <a class="btn" style="background:#334155" href="<?=$url?>" target="_blank">📄 Dosyayı Aç (<?=htmlspecialchars($file['original_name'])?>)</a>
    <?php endif; ?>
  </div>

  <?php if($ok): ?><div class="card"><div class="ok"><?=htmlspecialchars($ok)?></div></div><?php endif; ?>

  <?php $cur=$file['approval_status']; ?>
  <div class="card">
    <p>Durum:
      <span class="tag" style="background:<?=$cur==='Onaylandı'?'#16a34a':($cur==='Reddedildi'?'#7f1d1d':'#334155')?>"><?=htmlspecialchars($cur ?: 'Onay Bekliyor')?></span>
    </p>
    <?php if($cur!=='Onaylandı'): ?>
    <form method="post">
      <textarea name="note" rows="2" placeholder="Not (opsiyonel)"></textarea>
      <div style="display:flex;gap:10px">
        <button class="btn" style="background:#16a34a" name="decision" value="Onaylandı">✓ Onaylıyorum</button>
        <button class="btn" style="background:#b91c1c" name="decision" value="Reddedildi">✕ Revizyon</button>
      </div>
    </form>
    <?php else: ?><p class="muted">Bu dosyayı onayladınız. Teşekkürler.</p><?php endif; ?>
  </div>
<?php endif; ?>
<p class="muted" style="text-align:center">ACANS OTS · Online Takip Sistemi</p>
</div></body></html>
