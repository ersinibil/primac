<?php
require_once __DIR__.'/boot.php';
require_login();
$pdo=db();
if(!is_admin()){ require_once __DIR__.'/layout_top.php'; echo '<div class="alert">Bu sayfa sadece yöneticiye açıktır.</div>'; require __DIR__.'/layout_bottom.php'; exit; }

// Kategori → tablolar. Kullanıcı/personel/hesap/kategori KORUNUR.
$groups = [
  'jobs'     => ['📋 İşler & Üretim', ['jobs','job_stages','job_files','job_notes','job_logs','work_checklists','work_events']],
  'tasks'    => ['🎯 Görevler', ['tasks']],
  'quotes'   => ['📄 Teklifler', ['quotes','quote_items']],
  'contacts' => ['👥 Cariler (müşteri/tedarikçi)', ['contacts','contact_representatives']],
  'finance'  => ['💰 Finans / Cari Hareketleri', ['finance_movements']],
  'stockmov' => ['📦 Stok Hareketleri', ['stock_movements']],
  'products' => ['🏷️ Ürünler / Stok Kartları', ['stock_items']],
  'trade'    => ['🧾 Ticari Belgeler', ['trade_documents','trade_document_items']],
  'messages' => ['💬 Mesaj & Bildirim', ['internal_messages','internal_notifications','chat_threads','chat_thread_members','chat_typing']],
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
require_once __DIR__.'/layout_top.php';
?>
<div class="panel-head"><h1>🧹 Veri Temizleme (Canlıya Hazırlık)</h1><a class="btn secondary" href="dashboard.php">Panel</a></div>

<?php if($done): ?><div class="ok">Temizlendi: <?=h(implode(', ',$done))?></div><?php endif; ?>
<?php if($msg): ?><div class="alert"><?=h($msg)?></div><?php endif; ?>

<section class="panel" style="max-width:680px;border:1px solid #b91c1c">
  <div style="color:#fca5a5;font-weight:800;margin-bottom:6px">⚠️ DİKKAT — geri alınamaz!</div>
  <p class="muted" style="margin:0 0 12px">Seçtiğin kategorilerin <b>tüm kayıtları silinir</b>. Kullanıcılar, personel, banka/kasa hesapları ve ürün kategorileri <b>korunur</b>. Canlıya geçmeden test verisini temizlemek içindir.</p>
  <form method="post">
    <table style="width:100%;border-collapse:collapse">
      <thead><tr style="text-align:left;color:#7f95b2;font-size:13px"><th style="padding:6px">Sil</th><th style="padding:6px">Kategori</th><th style="padding:6px;text-align:right">Kayıt</th></tr></thead>
      <tbody>
      <?php foreach($groups as $key=>$g):
        $cnt=0; foreach($g[1] as $t){ $c=tcount($pdo,$t); if($c>0)$cnt+=$c; } ?>
        <tr style="border-top:1px solid rgba(255,255,255,.08)">
          <td style="padding:8px 6px"><input type="checkbox" name="g[]" value="<?=h($key)?>" <?=$cnt==0?'disabled':''?>></td>
          <td style="padding:8px 6px"><?=h($g[0])?><br><small class="muted"><?=h(implode(', ',$g[1]))?></small></td>
          <td style="padding:8px 6px;text-align:right;font-weight:700"><?=$cnt?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <label style="display:flex;align-items:center;gap:8px;margin-top:12px"><input type="checkbox" name="reset_balance" value="1"> Finans seçilirse kasa/banka bakiyelerini açılış değerine sıfırla</label>
    <div style="margin-top:14px">
      <label>Onay — kutuya büyük harfle <b>SİL</b> yaz:</label>
      <input name="confirm" placeholder="SİL" autocomplete="off" style="max-width:160px">
    </div>
    <button class="btn" style="background:#b91c1c;margin-top:12px" onclick="return confirm('Seçili kategoriler KALICI olarak silinecek. Emin misin?')">🗑️ Seçilenleri Sil</button>
  </form>
</section>

<section class="panel" style="max-width:680px">
  <b>Korunan tablolar:</b> <span class="muted">app_users, personnel, personnel_devices, finance_accounts, product_categories, product_brands, product_units, push_subs</span>
</section>
<?php require __DIR__.'/layout_bottom.php'; ?>
