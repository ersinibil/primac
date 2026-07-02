<?php require_once 'common.php'; topx(app_config()['app_name'] ?? 'OTS');
$open=mc("SELECT COUNT(*) c FROM jobs WHERE status NOT IN ('Tamamlandı','İptal','Teslim Edildi')");
if($isAdmin){
  $contacts=mc("SELECT COUNT(*) c FROM contacts");
  $stock=mc("SELECT COUNT(*) c FROM stock_items");
  $crit=mc("SELECT COUNT(*) c FROM stock_items WHERE quantity<=critical_level");
  $overdue=mc("SELECT COUNT(*) c FROM jobs WHERE status NOT IN ('Tamamlandı','İptal','Teslim Edildi') AND due_date IS NOT NULL AND due_date<CURDATE()");
  $todayIn=0; try{ $todayIn=(float)(db()->query("SELECT COALESCE(SUM(amount),0) s FROM finance_movements WHERE direction='in' AND DATE(movement_date)=CURDATE()")->fetch()['s']??0); }catch(Throwable $e){}
  $topName=''; try{ $tp=db()->query("SELECT p.name, COUNT(*) c FROM jobs j JOIN personnel p ON p.id=j.responsible_personnel_id WHERE j.status IN ('Tamamlandı','Teslim Edildi') GROUP BY p.id ORDER BY c DESC LIMIT 1")->fetch(); if($tp)$topName=$tp['name'].' ('.$tp['c'].')'; }catch(Throwable $e){}
}
$myMsg=unread_msg(); $myNotif=unread_notif();
?>
<?php if($isAdmin): ?>
  <div class="panel"><b>Online Takip ve Yönetim Sistemi</b><p class="small">Yönetici paneli · Web sürümü ayrı butondadır.</p>
  <div class="grid">
    <?php card('Açık İş',$open.' adet','📋','jobs.php','blue');card('Cari',$contacts.' kayıt','👥','contacts.php','purple');card('Ürün',$stock.' stok','📦','stock.php','orange');card('Kritik',$crit.' ürün','⚠️','stock.php','red');?>
  </div></div>
  <a href="kpi.php" style="text-decoration:none"><div class="panel"><b>📊 Bugün / Özet</b>
    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:8px;font-size:13px">
      <span style="background:rgba(248,113,113,.18);color:#fca5a5;border-radius:10px;padding:6px 10px">⏰ Geciken iş: <b><?=$overdue?></b></span>
      <span style="background:rgba(34,197,94,.16);color:#86efac;border-radius:10px;padding:6px 10px">💰 Bugün tahsilat: <b><?=mm($todayIn)?></b></span>
      <?php if($topName): ?><span style="background:rgba(234,179,8,.16);color:#fde68a;border-radius:10px;padding:6px 10px">🏆 Lider: <b><?=htmlspecialchars($topName)?></b></span><?php endif; ?>
    </div>
    <small class="muted" style="display:block;margin-top:8px">Personel performansını gör →</small>
  </div></a>
  <div class="grid">
    <?php
    card('İşler','İş ve görev takibi','📋','jobs.php','green');
    card('Görevlerim','Sana atanan görevler','🎯','mytasks.php','orange');
    card('Cariler','Müşteri / tedarikçi','👥','contacts.php','purple');
    card('Satış','Mobil satış ekranı','🧾','sales.php','orange');
    card('Tahsilat','Cariye tahsilat gir','💰','collection.php','yellow');
    card('Stok','Ürünleri gör','📦','stock.php','red');
    card('Mesajlar','İç yazışma','💬','messages.php','teal');
    ?>
  </div>
<?php else: ?>
  <div class="panel"><b>Hoş geldin, <?=htmlspecialchars($name)?></b><p class="small">Personel paneli · satış, tahsilat, iş ve mesaj.</p>
  <div class="grid">
    <a href="jobs.php" class="card blue"><span>📋</span><b><?=$open?></b><small>Açık iş</small></a>
    <a href="messages.php" class="card teal"><span>💬</span><b><?=$myMsg?></b><small>Yeni mesaj</small></a>
  </div></div>
  <div class="grid">
    <?php
    card('Satış Yap','Ürün sat + tahsilat','🧾','sales.php','orange');
    card('Tahsilat','Cariden tahsilat','💰','collection.php','yellow');
    card('Cariler','Müşteri / tedarikçi','👥','contacts.php','purple');
    card('İşlerim','İş ve görev takibi','📋','jobs.php','green');
    card('Görevlerim','Sana atanan görevler','🎯','mytasks.php','blue');
    card('Stok','Ürünleri gör','📦','stock.php','red');
    card('Mesajlar','Ekiple yazış','💬','messages.php','teal');
    ?>
  </div>
<?php endif; ?>
<?php botx(); ?>
