<?php
// PUSH TEŞHİS — kurulu uygulamadan (standalone) aç. Sorunun tam yerini gösterir.
require_once __DIR__.'/boot.php';
require_once __DIR__.'/push_lib.php';
header('Content-Type:text/html;charset=utf-8');
if(empty($_SESSION['user'])){ exit('Önce giriş yap: <a href="index.php">Giriş</a>'); }
$uid=(int)$_SESSION['user']['id'];
$me=htmlspecialchars($_SESSION['user']['name'] ?? $_SESSION['user']['username'] ?? ('#'.$uid));

// Test gönderimi
$sendReport=null;
if(($_GET['send'] ?? '')==='1'){
  $sendReport=[];
  if(!push_available()){ $sendReport[]=['ok'=>false,'reason'=>'push_available() FALSE — eklenti eksik']; }
  else{
    require_once __DIR__.'/vendor/autoload.php'; push_install();
    $rows=db()->prepare("SELECT * FROM push_subs WHERE user_id=?"); $rows->execute([$uid]); $rows=$rows->fetchAll();
    if(!$rows){ $sendReport[]=['ok'=>false,'reason'=>'Bu kullanıcının KAYITLI ABONELİĞİ YOK (subscribe olmamış)']; }
    else{
      try{
        $v=push_vapid();
        $wp=new \Minishlink\WebPush\WebPush(['VAPID'=>$v]);
        $payload=json_encode(['title'=>'🔔 Test bildirimi','body'=>'Push çalışıyor! '.date('H:i:s'),'url'=>'mobile/index.php'],JSON_UNESCAPED_UNICODE);
        foreach($rows as $s){
          $sub=\Minishlink\WebPush\Subscription::create(['endpoint'=>$s['endpoint'],'publicKey'=>$s['p256dh'],'authToken'=>$s['auth'],'contentEncoding'=>'aes128gcm']);
          if(method_exists($wp,'queueNotification')) $wp->queueNotification($sub,$payload);
          else $wp->sendNotification($sub,$payload);
        }
        foreach($wp->flush() as $r){
          $sendReport[]=['ok'=>$r->isSuccess(),'reason'=>$r->isSuccess()?'GÖNDERİLDİ ✅':('HATA: '.$r->getReason()),'ep'=>substr($r->getEndpoint(),0,46)];
        }
      }catch(Throwable $e){ $sendReport[]=['ok'=>false,'reason'=>'İstisna: '.$e->getMessage()]; }
    }
  }
}

$subCount=0; try{ push_install(); $c=db()->prepare("SELECT COUNT(*) c FROM push_subs WHERE user_id=?"); $c->execute([$uid]); $subCount=(int)$c->fetch()['c']; }catch(Throwable $e){}
// Son bildirimler — target_user_id ile (zil'deki sayının kaynağı)
$notifRows=[]; try{ $nq=db()->query("SELECT id,title,target_user_id,is_read FROM internal_notifications ORDER BY id DESC LIMIT 12"); $notifRows=$nq->fetchAll(); }catch(Throwable $e){}
?><!doctype html><meta charset=utf-8><meta name=viewport content="width=device-width,initial-scale=1">
<body style="font-family:-apple-system,sans-serif;background:#0f172a;color:#fff;max-width:560px;margin:auto;padding:16px;line-height:1.7">
<h2>🔔 Push Teşhis</h2>
<p>Kullanıcı: <b><?=$me?></b></p>
<h3>1) Sunucu yetenekleri</h3>
<ul>
<li>vendor/autoload: <b><?=file_exists(__DIR__.'/vendor/autoload.php')?'VAR ✅':'YOK ❌'?></b></li>
<li>openssl: <b><?=extension_loaded('openssl')?'VAR ✅':'YOK ❌'?></b></li>
<li>curl: <b><?=extension_loaded('curl')?'VAR ✅':'YOK ❌'?></b></li>
<li>gmp: <?=extension_loaded('gmp')?'VAR ✅':'yok'?> · bcmath: <?=extension_loaded('bcmath')?'VAR ✅':'yok'?> <b><?=(extension_loaded('gmp')||extension_loaded('bcmath'))?'(yeterli ✅)':'(İKİSİ DE YOK ❌ — push imkânsız)'?></b></li>
<li><b>push_available(): <?=push_available()?'<span style="color:#4ade80">EVET ✅</span>':'<span style="color:#f87171">HAYIR ❌</span>'?></b></li>
</ul>
<h3>2) Bu cihazın aboneliği</h3>
<p>Kayıtlı abonelik: <b style="color:<?=$subCount?'#4ade80':'#f87171'?>"><?=$subCount?$subCount.' adet ✅':'0 — SUBSCRIBE OLMAMIŞ ❌'?></b></p>
<?php if(!$subCount): ?>
<p style="background:#7f1d1d;padding:10px;border-radius:10px">Abonelik yok. Sebepleri: (a) uygulama <b>ana ekrandan (standalone)</b> açılmadı, (b) bildirim izni verilmedi, (c) iOS 16.4+ değil. Uygulamayı ikondan aç, izin iste, sonra bu sayfayı yenile.</p>
<?php endif; ?>
<h3>3) Test push gönder</h3>
<a href="push_test.php?send=1" style="display:inline-block;background:#2563eb;color:#fff;padding:12px 18px;border-radius:12px;text-decoration:none;font-weight:800">🔔 Bana test push gönder</a>
<?php if($sendReport!==null): ?>
<h3>Sonuç</h3>
<?php foreach($sendReport as $r): ?>
<p style="background:<?=$r['ok']?'#14532d':'#7f1d1d'?>;padding:10px;border-radius:10px"><?=htmlspecialchars($r['reason'])?><?=isset($r['ep'])?'<br><small>'.htmlspecialchars($r['ep']).'…</small>':''?></p>
<?php endforeach; ?>
<p class=muted style="color:#94a3b8">Test pushu telefona düştüyse (uygulama KAPALIYKEN) → push çalışıyor demektir.</p>
<?php endif; ?>
<h3>4) Son bildirimler (zil sayısının kaynağı)</h3>
<p class=muted style="color:#94a3b8;font-size:13px">Senin id'in: <b><?=$uid?></b>. target=NULL → herkese görünür (global). target=senin id → sadece sana.</p>
<table style="width:100%;border-collapse:collapse;font-size:12px">
<tr style="color:#94a3b8"><td>id</td><td>başlık</td><td>target</td><td>okundu</td></tr>
<?php foreach($notifRows as $n): $glob=($n['target_user_id']===null); $mine=((int)$n['target_user_id']===$uid); ?>
<tr style="border-top:1px solid #1e293b;color:<?=($glob||$mine)?'#fff':'#475569'?>">
<td><?=$n['id']?></td><td><?=htmlspecialchars(mb_substr($n['title'],0,28))?></td>
<td><b style="color:<?=$glob?'#f87171':($mine?'#4ade80':'#475569')?>"><?=$glob?'NULL(global)':$n['target_user_id']?></b></td>
<td><?=$n['is_read']?'okundu':'<b style=color:#fbbf24>yeni</b>'?></td></tr>
<?php endforeach; ?>
</table>
<p style="color:#94a3b8;margin-top:20px"><a href="mobile/index.php" style="color:#60a5fa">← Uygulamaya dön</a> · Bu dosyayı sonra sil.</p>
</body>
