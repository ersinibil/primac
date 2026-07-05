<?php
require_once 'common.php';
block_personel();
topx('Son İşlemler');
try{
  $rows=db()->query("SELECT * FROM activity_logs ORDER BY id DESC LIMIT 60")->fetchAll();
  if(!$rows){ echo '<div class="panel muted">Henüz işlem kaydı yok.</div>'; }
  foreach($rows as $r){
    // PATCH-004: stored $r['url'] platforma göre yanlış olabilir (ör. web'de kaydedilmiş bir
    // mutlak yol) — asıl hedef entity_type+entity_id'ye göre 'mobile' platformu için burada
    // yeniden çözülüyor, resolver kapsamayan türlerde eski stored url'e düşülüyor.
    $resolved=activity_target_url($r['entity_type'] ?? '', $r['entity_id'] ?? null, 'mobile');
    if($resolved===false){
      echo '<div class="item" style="opacity:.55"><b>'.htmlspecialchars(($r['icon']?:'•').' '.$r['title']).'</b>';
      if(!empty($r['description'])) echo '<br><span class="muted">'.htmlspecialchars($r['description']).'</span>';
      echo '<br><small>'.htmlspecialchars(($r['user_name']?:'Sistem').' · '.($r['module']?:'').' · '.($r['created_at']??'')).' · <i>Kayıt artık mevcut değil</i></small></div>';
      continue;
    }
    $url = $resolved ?? ($r['url'] ?: '#');
    echo '<a class="item" href="'.h($url).'" style="display:block"><b>'.htmlspecialchars(($r['icon']?:'•').' '.$r['title']).'</b>';
    if(!empty($r['description'])) echo '<br><span class="muted">'.htmlspecialchars($r['description']).'</span>';
    echo '<br><small>'.htmlspecialchars(($r['user_name']?:'Sistem').' · '.($r['module']?:'').' · '.($r['created_at']??'')).'</small></a>';
  }
}catch(Throwable $e){ echo '<div class="err">'.htmlspecialchars($e->getMessage()).'</div>'; }
botx();
