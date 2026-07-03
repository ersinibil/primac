<?php
require_once 'common.php';
$pdo=db();
if(!$isAdmin){ header('Location: index.php'); exit; }

$groups = [
  'jobs'     => ['📋 İşler & Üretim', ['jobs','job_stages','job_files','job_notes','job_logs','work_checklists','work_events']],
  'tasks'    => ['🎯 Görevler', ['tasks']],
  'quotes'   => ['📄 Teklifler', ['quotes','quote_items']],
  'contacts' => ['👥 Cariler (müşteri/tedarikçi)', ['contacts','contact_representatives']],
  'finance'  => ['💰 Finans / Cari Hareketleri', ['finance_movements']],
  'stockmov' => ['📦 Stok Hareketleri', ['stock_movements']],
  'products' => ['🏷️ Ürünler / Stok Kartları', ['stock_items']],
  'trade'    => ['🧾 Ticari Belgeler', ['trade_documents','trade_document_items']],
  'messages' => ['💬 Mesaj & Bildirim', ['internal_messages','internal_notifications','user_notification_status','chat_threads','chat_thread_members','chat_typing']],
  'activity' => ['🕘 Aktivite Kaydı', ['activity_logs']],
  'requests' => ['📨 Personel Talepleri', ['management_requests']],
];
function tcount($pdo,$t){ try{ return (int)$pdo->query("SELECT COUNT(*) c FROM `".$t."`")->fetch()['c']; }catch(Throwable $e){ return -1; } }

$done=[]; $msg='';
if($_SERVER['REQUEST_METHOD']==='POST'){
  if(($_POST['confirm']??'')!=='SİL'){ $msg='Onay kutusuna büyük harfle SİL yazmadan işlem yapılmaz.'; }
  else {
    $sel=$_POST['g']??[];
    if(!$sel){ $msg='Hiç kategori seçmedin.'; }
    else {
      try{ $pdo->exec("SET FOREIGN_KEY_CHECKS=0"); }catch(Throwable $e){}
      foreach($sel as $g){ if(!isset($groups[$g])) continue;
        foreach($groups[$g][1] as $t){
          try{ $pdo->exec("DELETE FROM `".$t."`"); try{ $pdo->exec("ALTER TABLE `".$t."` AUTO_INCREMENT=1"); }catch(Throwable $e){} $done[]=$t; }catch(Throwable $e){}
        }
      }
      if(in_array('finance',$sel,true) && !empty($_POST['reset_balance'])){
        try{ $pdo->exec("UPDATE finance_accounts SET current_balance=opening_balance"); $done[]='finance_accounts→bakiye sıfırlandı'; }catch(Throwable $e){}
      }
      try{ $pdo->exec("SET FOREIGN_KEY_CHECKS=1"); }catch(Throwable $e){}
      try{ if(function_exists('activity_log')) activity_log('Sistem','Veri Temizleme',implode(', ',$sel),'','system',null,'temizle_veri.php','🧹'); }catch(Throwable $e){}
    }
  }
}

topx('Veri Temizleme');
?>
<?php if($done): ?><div class="notice">Temizlendi: <?=htmlspecialchars(implode(', ',$done))?></div><?php endif; ?>
<?php if($msg): ?><div class="err"><?=htmlspecialchars($msg)?></div><?php endif; ?>

<div class="panel" style="border:1px solid #b91c1c">
  <div style="color:#fca5a5;font-weight:800;margin-bottom:6px">⚠️ DİKKAT — geri alınamaz!</div>
  <p class="small" style="margin:0 0 12px">Seçtiğin kategorilerin <b>tüm kayıtları silinir</b>. Kullanıcılar, personel, banka/kasa hesapları ve ürün kategorileri <b>korunur</b>. Canlıya geçmeden test verisini temizlemek içindir.</p>
  <form method="post">
    <?php foreach($groups as $key=>$g):
      $cnt=0; foreach($g[1] as $t){ $c=tcount($pdo,$t); if($c>0)$cnt+=$c; } ?>
    <label style="display:flex;align-items:flex-start;gap:10px;background:rgba(255,255,255,.06);border-radius:12px;padding:10px;margin:6px 0">
      <input type="checkbox" name="g[]" value="<?=htmlspecialchars($key)?>" <?=$cnt==0?'disabled':''?> style="width:auto;margin-top:3px">
      <span style="flex:1">
        <?=htmlspecialchars($g[0])?><br><small class="small"><?=htmlspecialchars(implode(', ',$g[1]))?></small>
      </span>
      <b><?=$cnt?></b>
    </label>
    <?php endforeach; ?>

    <label style="display:flex;align-items:center;gap:8px;margin-top:10px">
      <input type="checkbox" name="reset_balance" value="1" style="width:auto">
      <span style="font-size:13px">Finans seçilirse kasa/banka bakiyelerini açılış değerine sıfırla</span>
    </label>

    <label style="color:#94a3b8;font-size:12px;margin-top:10px">Onay — kutuya büyük harfle SİL yaz</label>
    <input name="confirm" placeholder="SİL" autocomplete="off">

    <button class="btn dark" style="width:100%;padding:13px;background:#b91c1c;color:#fff;margin-top:10px" onclick="return confirm('Seçili kategoriler KALICI olarak silinecek. Emin misin?')">🗑️ Seçilenleri Sil</button>
  </form>
</div>

<div class="panel">
  <b>Korunan tablolar:</b>
  <p class="small" style="margin-top:6px">app_users, personnel, personnel_devices, finance_accounts, product_categories, product_brands, product_units, push_subs</p>
</div>
<?php botx(); ?>
