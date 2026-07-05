<?php
// Teklif onay sayfası — GİRİŞSİZ. Müşteri WhatsApp linkten tiklayıp onaylayıp/reddetebiliyor.
require_once __DIR__.'/boot.php';
$pdo=db();
$token=preg_replace('/[^a-f0-9]/','',$_GET['token'] ?? '');
$ok=''; $err='';

$st=$pdo->prepare("SELECT q.*, c.name customer_name_contact, c.phone FROM quotes q
    LEFT JOIN contacts c ON c.id=q.customer_id WHERE q.approval_token=? LIMIT 1");
$st->execute([$token]);
$quote=$st->fetch();

if($quote && $_SERVER['REQUEST_METHOD']==='POST'){
    $decision=$_POST['decision'] ?? '';
    if(in_array($quote['status'],['Kabul','Red'],true)){
        // Zaten karar verilmiş — tekrar oynatma (replay) engellenir, sadece UI'da değil sunucuda da.
        $err='Bu teklif için karar zaten verilmiş: '.$quote['status'].'.';
    } elseif(in_array($decision,['Kabul','Red'])){
        // Karar kaydet — WHERE'e durum şartı eklenmiş: aynı anda gelen iki istekte de (race condition)
        // sadece biri satırı güncelleyebilir (rowCount ile teyit edilir).
        $upd=$pdo->prepare("UPDATE quotes SET status=?, approval_decision_at=NOW() WHERE id=? AND status NOT IN('Kabul','Red')");
        $upd->execute([$decision,$quote['id']]);
        if($upd->rowCount()===0){
            $err='Bu teklif için karar zaten verilmiş.';
            $quote['status']=$decision; // ekranda tutarlı göster
        } else {

        // Teklifi oluşturan kişiye bildirim yolla
        try{
            $msg=$quote['customer_name']." tarafından teklifin onay durumu: ".$decision;
            $pdo->prepare("INSERT INTO internal_notifications(title,message,target_user_id,action_url,is_read) VALUES(?,?,?,?,0)")
                ->execute(['✅ Teklif '.$decision,$msg,(int)$quote['created_by'],'teklif.php?id='.$quote['id']]);
        }catch(Throwable $e){}

        // Activity log
        try{
            if(function_exists('activity_log')){
                activity_log('Teklif','Müşteri Kararı',$quote['quote_no'].' · '.$decision,'','quote',(int)$quote['id'],
                    'teklif.php?id='.$quote['id'],$decision==='Kabul'?'✅':'❌');
            }
        }catch(Throwable $e){}

        $ok='Yanıtınız kaydedildi. Teşekkürler.';
        $quote['status']=$decision;
        }
    }
}
$appName=app_config()['app_name'] ?? 'OTS';
$ogTitle = $quote ? ('📄 Teklif '.$quote['quote_no'].' · '.money((float)$quote['total'])) : 'Teklif Onayı';
$ogDesc  = $quote ? ($quote['customer_name'].' için hazırlanan teklifi görüntüleyin ve onaylayın.') : 'Teklif bulunamadı.';
$ogImage = $quote ? base_url().brand_logo() : '';
?>
<!doctype html><html lang="tr"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?=htmlspecialchars($ogTitle)?> · <?=htmlspecialchars($appName)?></title>
<!-- WhatsApp/sosyal önizleme kartı için (kullanıcı isteği 2026-07-03: "onay linki daha görsel
     göze hitap eden olmalı" — WhatsApp bu meta etiketlerinden otomatik önizleme kartı üretir) -->
<meta property="og:title" content="<?=htmlspecialchars($ogTitle)?>">
<meta property="og:description" content="<?=htmlspecialchars($ogDesc)?>">
<?php if($ogImage): ?><meta property="og:image" content="<?=htmlspecialchars($ogImage)?>"><?php endif; ?>
<meta property="og:type" content="website">
<style>
body{margin:0;background:#0f172a;color:#fff;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Arial,sans-serif;min-height:100vh}
.wrap{max-width:560px;margin:auto;padding:18px}
.card{background:#1e293b;border:1px solid rgba(255,255,255,.1);border-radius:20px;padding:18px;margin-bottom:14px}
h1{font-size:20px}.muted{color:#94a3b8;font-size:14px}
.btn{display:inline-block;border:0;border-radius:14px;padding:14px;font-weight:900;font-size:16px;width:100%;cursor:pointer;text-decoration:none;text-align:center;color:#fff;margin:6px 0}
.ok{background:#16a34a;color:#fff;padding:12px;border-radius:12px;margin-bottom:12px}.tag{display:inline-block;padding:4px 12px;border-radius:999px;font-weight:900;font-size:13px}
.item-row{display:flex;justify-content:space-between;align-items:center;padding:12px 0;border-bottom:1px solid rgba(255,255,255,.1)}
.item-row:last-child{border-bottom:none}
.item-name{flex:1}
.item-price{text-align:right;min-width:80px}
.total-row{display:flex;justify-content:space-between;align-items:center;padding:14px;background:rgba(59,130,246,.12);border-radius:12px;margin-top:12px;font-size:18px;font-weight:900}
.btn-group{display:flex;gap:8px}
.btn-group .btn{flex:1;margin:0}
</style></head><body><div class="wrap">
<?php if(!$quote): ?>
  <div class="card"><h1>Teklif bulunamadı</h1><p class="muted">Bağlantı geçersiz veya süresi dolmuş olabilir.</p></div>
<?php else: ?>
  <div id="repArea">
  <div class="card">
    <h1>📄 Teklif Onayı</h1>
    <p class="muted"><?=htmlspecialchars($quote['quote_no'])?></p>
    <?php if(!empty($quote['customer_name'])): ?>
      <p class="muted">Müşteri: <strong><?=htmlspecialchars($quote['customer_name'])?></strong></p>
    <?php endif; ?>
  </div>

  <?php if($ok): ?><div class="card"><div class="ok"><?=htmlspecialchars($ok)?></div></div><?php endif; ?>

  <!-- Teklif Özeti -->
  <div class="card">
    <h2 style="margin:0 0 12px 0;font-size:16px">Teklif Kalemler</h2>
    <?php
    $items=[];
    try{
      $it=$pdo->prepare("SELECT * FROM quote_items WHERE quote_id=? ORDER BY id");
      $it->execute([$quote['id']]);
      $items=$it->fetchAll();
    }catch(Throwable $e){}

    if($items):
      foreach($items as $item):
    ?>
    <div class="item-row">
      <div>
        <div class="item-name"><?=htmlspecialchars($item['name'])?></div>
        <div class="muted" style="font-size:12px"><?=rtrim(rtrim(number_format((float)$item['qty'],3,',','.'),'0'),',')?> Adet</div>
      </div>
      <div class="item-price"><?=money((float)$item['line_total'])?></div>
    </div>
    <?php
      endforeach;
    endif;
    ?>

    <div class="total-row">
      <div>TOPLAM</div>
      <div><?=money((float)$quote['total'])?></div>
    </div>

    <?php if(!empty($quote['notes'])): ?>
      <div style="margin-top:14px;padding:12px;background:rgba(255,255,255,.08);border-radius:8px;border-left:3px solid #3b82f6">
        <div class="muted" style="font-size:12px">Not</div>
        <div style="margin-top:4px"><?=nl2br(htmlspecialchars($quote['notes']))?></div>
      </div>
    <?php endif; ?>
  </div>
  </div>
  <button type="button" class="btn noprint" style="background:#16a34a" onclick="shareReportPDF(this)">📄 PDF İndir</button>

  <!-- Durum -->
  <div class="card">
    <p>Durum:
      <span class="tag" style="background:<?=$quote['status']==='Kabul'?'#16a34a':($quote['status']==='Red'?'#7f1d1d':'#334155')?>"><?=htmlspecialchars($quote['status'] ?: 'Onay Bekliyor')?></span>
    </p>
    <?php if($quote['status']!=='Kabul' && $quote['status']!=='Red'): ?>
    <form method="post">
      <?=csrf_field()?>
      <div class="btn-group">
        <button class="btn" style="background:#16a34a" name="decision" value="Kabul">✅ Kabul Ediyorum</button>
        <button class="btn" style="background:#b91c1c" name="decision" value="Red">❌ Reddetme</button>
      </div>
    </form>
    <?php else: ?>
      <p class="muted">Bu teklifi <?=$quote['status']==='Kabul'?'onayladınız':'reddettiniz'?>. Teşekkürler.</p>
    <?php endif; ?>
  </div>
<?php endif; ?>
<p class="muted" style="text-align:center"><?=htmlspecialchars(app_config()["app_name"] ?? "OTS")?> · Online Takip Sistemi</p>
</div>
<?php if($quote): ?>
<script>window.ACANS_REPORT_NAME='teklif_<?=htmlspecialchars($quote['quote_no'])?>';</script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="report_share.js"></script>
<?php endif; ?>
</body></html>
