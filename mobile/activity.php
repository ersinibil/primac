<?php
require_once 'common.php';
block_personel();
topx('Son İşlemler');
try{
  $rows=activity_recent(60);
  activity_render_list($rows,'mobile');
}catch(Throwable $e){ echo '<div class="err">'.htmlspecialchars($e->getMessage()).'</div>'; }
botx();
