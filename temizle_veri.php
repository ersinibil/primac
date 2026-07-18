<?php
require_once __DIR__.'/boot.php';
require_login();
$pdo=db();
if(!is_admin()){ require_once __DIR__.'/layout_top.php'; echo ds_alert('danger','Bu sayfa sadece yöneticiye açıktır.'); require __DIR__.'/layout_bottom.php'; exit; }

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
require_once __DIR__.'/layout_top.php';
ds_page_header('Veri Temizleme (Canlıya Hazırlık)', ds_icon('trash',24), '', ds_button('Panel','dashboard.php','secondary','','',true), false, true);
?>

<?php if($done): ?><?=ds_alert('success','Temizlendi: '.implode(', ',$done))?><?php endif; ?>
<?php if($msg): ?><?=ds_alert('danger',$msg)?><?php endif; ?>

<section class="df-card" style="max-width:680px;margin-top:var(--df-space-4);border-color:var(--df-danger)">
  <div style="color:var(--df-danger-ink);font-weight:800;margin-bottom:6px">⚠️ DİKKAT — geri alınamaz!</div>
  <p class="df-muted" style="margin:0 0 var(--df-space-3)">Seçtiğin kategorilerin <b>tüm kayıtları silinir</b>. Kullanıcılar, personel, banka/kasa hesapları ve ürün kategorileri <b>korunur</b>. Canlıya geçmeden test verisini temizlemek içindir.</p>
  <form method="post">
    <div class="df-table-wrap"><table class="df-table">
      <thead><tr><th>Sil</th><th>Kategori</th><th style="text-align:right">Kayıt</th></tr></thead>
      <tbody>
      <?php foreach($groups as $key=>$g):
        $cnt=0; foreach($g[1] as $t){ $c=tcount($pdo,$t); if($c>0)$cnt+=$c; } ?>
        <tr>
          <td><input type="checkbox" name="g[]" value="<?=h($key)?>" <?=$cnt==0?'disabled':''?>></td>
          <td><?=h($g[0])?><br><small class="df-muted"><?=h(implode(', ',$g[1]))?></small></td>
          <td style="text-align:right;font-weight:700"><?=$cnt?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
    <label style="display:flex;align-items:center;gap:8px;margin-top:var(--df-space-3);font-size:var(--df-type-body-size);color:var(--df-ink-900)"><input type="checkbox" name="reset_balance" value="1"> Finans seçilirse kasa/banka bakiyelerini açılış değerine sıfırla</label>
    <div style="margin-top:var(--df-space-3)">
      <?php ds_form_field('Onay — kutuya büyük harfle SİL yaz', '<input name="confirm" placeholder="SİL" autocomplete="off" style="max-width:160px">'); ?>
    </div>
    <button class="df-btn df-btn--danger" style="margin-top:var(--df-space-2)" onclick="return confirm('Seçili kategoriler KALICI olarak silinecek. Emin misin?')"><?=ds_icon('trash',16)?> Seçilenleri Sil</button>
  </form>
</section>

<section class="df-card" style="max-width:680px;margin-top:var(--df-space-4)">
  <b>Korunan tablolar:</b> <span class="df-muted">app_users, personnel, personnel_devices, finance_accounts, product_categories, product_brands, product_units, push_subs</span>
</section>
<?php require __DIR__.'/layout_bottom.php'; ?>
