<?php
// ACANS OTS — Üretim Aşamaları (mobil + web ortak)

// İş tipine göre varsayılan aşama şablonu
function stage_template($type){
  $t=[
    '3d_imalat'     =>['Tasarım','Dilimleme','Baskı','Kontrol','Teslim'],
    'uv_baski'      =>['Tasarım','Baskı','Kontrol','Teslim'],
    'lazer'         =>['Tasarım','Kesim','Kontrol','Teslim'],
    'grafik_tasarim'=>['Brief','Tasarım','Revizyon','Onay','Teslim'],
    'montaj'        =>['Hazırlık','Üretim','Montaj','Kontrol','Teslim'],
    'dis_atolye'    =>['Sipariş','Üretim (Dış)','Teslim Alındı','Kontrol','Teslim'],
  ];
  return $t[$type] ?? ['Teklif','Onay','Üretim','Kontrol','Teslim'];
}

function stages_install($pdo){
  try{ $pdo->exec("CREATE TABLE IF NOT EXISTS job_stages(id INT AUTO_INCREMENT PRIMARY KEY, job_id INT NOT NULL, stage_name VARCHAR(80), sort_order INT DEFAULT 0, status VARCHAR(20) DEFAULT 'Bekliyor', started_at DATETIME NULL, completed_at DATETIME NULL, note TEXT, KEY idx_job(job_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"); }catch(Throwable $e){}
}

// İşin aşaması yoksa şablondan oluştur
function ensure_stages($pdo,$jobId,$type){
  stages_install($pdo);
  $c=$pdo->prepare("SELECT COUNT(*) c FROM job_stages WHERE job_id=?"); $c->execute([$jobId]);
  if((int)$c->fetch()['c']>0) return;
  $i=0; $ins=$pdo->prepare("INSERT INTO job_stages(job_id,stage_name,sort_order,status) VALUES(?,?,?,'Bekliyor')");
  foreach(stage_template($type) as $s){ $ins->execute([$jobId,$s,$i++]); }
}

function get_stages($pdo,$jobId){
  try{ $s=$pdo->prepare("SELECT * FROM job_stages WHERE job_id=? ORDER BY sort_order,id"); $s->execute([$jobId]); return $s->fetchAll(); }catch(Throwable $e){ return []; }
}

// Aşama durumu güncelle (Bekliyor/Devam/Tamam)
function set_stage($pdo,$stageId,$jobId,$status){
  $sql="UPDATE job_stages SET status=?";
  if($status==='Devam') $sql.=", started_at=IFNULL(started_at,NOW())";
  if($status==='Tamam') $sql.=", completed_at=NOW(), started_at=IFNULL(started_at,NOW())";
  $sql.=" WHERE id=? AND job_id=?";
  try{ $pdo->prepare($sql)->execute([$status,$stageId,$jobId]); }catch(Throwable $e){}
}

// İlerleme: [done,total,pct,current_name]
function stage_progress($stages){
  $total=count($stages); if(!$total) return [0,0,0,''];
  $done=0;$cur='';
  foreach($stages as $s){ if($s['status']==='Tamam') $done++; }
  foreach($stages as $s){ if($s['status']!=='Tamam'){ $cur=$s['stage_name']; break; } }
  if($cur==='' && $total) $cur='Tamamlandı';
  return [$done,$total,(int)round($done/$total*100),$cur];
}

// Görsel aşama şeridi — dış sarmalayıcı + AJAX script (mobil + web ortak)
function stages_html($pdo,$jobId,$actionBase){
  return '<div id="acStages">'.stages_inner_html($pdo,$jobId).'</div>'.stages_script();
}

// AJAX script — aşama butonları tüm sayfayı yenilemeden çalışır (donmayı önler)
function stages_script(){
  static $done=false; if($done) return ''; $done=true;
  return '<script>(function(){var c=document.getElementById("acStages");if(!c||c._b)return;c._b=1;'
    .'function flash(m){var x=document.createElement("div");x.textContent=m;x.style.cssText="position:fixed;left:50%;top:14px;transform:translateX(-50%);background:#16a34a;color:#fff;padding:10px 16px;border-radius:10px;z-index:99999;font-weight:700;box-shadow:0 6px 18px rgba(0,0,0,.35)";document.body.appendChild(x);setTimeout(function(){x.remove();},2600);}'
    .'c.addEventListener("submit",function(e){var f=e.target;if(!f||f.tagName!=="FORM")return;e.preventDefault();var fd=new FormData(f);var sb=e.submitter||f.querySelector("button[name]");if(sb&&sb.name)fd.append(sb.name,sb.value);fd.append("stage_ajax","1");'
    .'c.style.opacity=".5";fetch(location.href,{method:"POST",body:fd,credentials:"same-origin"}).then(function(r){return r.json();}).then(function(d){c.style.opacity="1";if(d&&d.stages!=null)c.innerHTML=d.stages;if(d&&d.flash)flash(d.flash);}).catch(function(){location.reload();});});})();</script>';
}

// AJAX yanıtı — job_view'lerin EN BAŞINDA çağrılır; stage_ajax POST'unu yakalar ve sadece şeridi döner
function stage_ajax_respond($pdo,$jobId){
  if(empty($_POST['stage_ajax'])) return;
  $type=''; try{ $q=$pdo->prepare("SELECT job_type FROM jobs WHERE id=?"); $q->execute([$jobId]); $type=$q->fetch()['job_type']??''; }catch(Throwable $e){}
  handle_stage_post($pdo,$jobId,$type);
  $flash=''; if(!empty($_SESSION['flash'])){ $flash=$_SESSION['flash']; unset($_SESSION['flash']); }
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode(['stages'=>stages_inner_html($pdo,$jobId),'flash'=>$flash]);
  exit;
}

// İç içerik (ilerleme + aşama satırları) — sarmalayıcısız
function stages_inner_html($pdo,$jobId){
  $stages=get_stages($pdo,$jobId);
  if(!$stages){
    return '<form method="post"><button class="btn dark" name="init_stages" value="1" style="width:100%;padding:12px">⚙ Üretim aşamalarını oluştur</button></form>';
  }
  list($d,$t,$pct,$cur)=stage_progress($stages);
  $h='<div style="margin-bottom:10px"><div style="display:flex;justify-content:space-between;font-size:13px"><span class="muted">İlerleme: '.$d.'/'.$t.'</span><b>%'.$pct.'</b></div>';
  $h.='<div style="height:10px;background:rgba(255,255,255,.1);border-radius:6px;overflow:hidden;margin-top:4px"><div style="height:100%;width:'.$pct.'%;background:linear-gradient(90deg,#22c55e,#a3e635)"></div></div>';
  $h.='<div class="muted" style="font-size:12px;margin-top:4px">Şu an: <b>'.htmlspecialchars($cur).'</b></div></div>';
  foreach($stages as $s){
    $st=$s['status']; $col=$st==='Tamam'?'#22c55e':($st==='Devam'?'#eab308':'#64748b');
    $ic=$st==='Tamam'?'✅':($st==='Devam'?'🔄':'⚪');
    $h.='<div style="display:flex;align-items:center;gap:8px;padding:8px 0;border-bottom:1px solid rgba(255,255,255,.06)">';
    $h.='<span style="font-size:16px">'.$ic.'</span><span style="flex:1"><b>'.htmlspecialchars($s['stage_name']).'</b> <span style="color:'.$col.';font-size:12px;font-weight:700">'.$st.'</span></span>';
    if($st!=='Tamam'){
      if($st!=='Devam') $h.='<form method="post" style="margin:0"><input type="hidden" name="stage_id" value="'.$s['id'].'"><button class="btn" name="stage_set" value="Devam" style="background:#2563eb;color:#fff;padding:6px 10px;font-size:12px">Başlat</button></form>';
      $h.='<form method="post" style="margin:0"><input type="hidden" name="stage_id" value="'.$s['id'].'"><button class="btn" name="stage_set" value="Tamam" style="background:#16a34a;color:#fff;padding:6px 10px;font-size:12px">✓</button></form>';
    } else {
      $h.='<form method="post" style="margin:0"><input type="hidden" name="stage_id" value="'.$s['id'].'"><button class="btn" name="stage_set" value="Bekliyor" style="background:#334155;color:#fff;padding:6px 10px;font-size:12px">↺</button></form>';
    }
    $h.='</div>';
  }
  return $h;
}

// POST işle (job_view'lerden çağrılır). true dönerse bir şey yapıldı.
function handle_stage_post($pdo,$jobId,$type){
  if(!empty($_POST['init_stages'])){ ensure_stages($pdo,$jobId,$type); return true; }
  if(isset($_POST['stage_set']) && (int)($_POST['stage_id']??0)){
    set_stage($pdo,(int)$_POST['stage_id'],$jobId,$_POST['stage_set']);
    // Son aşama da "Tamam" olduysa üretimi otomatik stoğa ekle (ürün bağlıysa)
    if(($_POST['stage_set']??'')==='Tamam'){
      $stages=get_stages($pdo,$jobId); list($d,$t,$pct,$cur)=stage_progress($stages);
      if($t>0 && $d>=$t){
        list($pok,$pmsg)=produce_to_stock($pdo,$jobId);
        if($pok) $_SESSION['flash']='📦 '.$pmsg;
      }
    }
    return true;
  }
  return false;
}

// Üretilen ürünü stoğa ekle (bir kez). Dönüş: [ok,msg]
function produce_to_stock($pdo,$jobId){
  try{
    $j=$pdo->prepare("SELECT produce_item_id,produce_qty,produced,title FROM jobs WHERE id=?"); $j->execute([$jobId]); $j=$j->fetch();
    if(!$j || !$j['produce_item_id'] || (float)$j['produce_qty']<=0) return [false,'Bu işe bağlı stok ürünü/adet yok.'];
    if((int)$j['produced']===1) return [false,'Zaten stoğa eklenmiş.'];
    $pdo->prepare("UPDATE stock_items SET quantity=quantity+? WHERE id=?")->execute([(float)$j['produce_qty'],(int)$j['produce_item_id']]);
    $pdo->prepare("INSERT INTO stock_movements(stock_item_id,job_id,direction,quantity,reason,created_at) VALUES(?,?,'in',?,?,NOW())")
        ->execute([(int)$j['produce_item_id'],$jobId,(float)$j['produce_qty'],'Üretim: '.$j['title']]);
    $pdo->prepare("UPDATE jobs SET produced=1 WHERE id=?")->execute([$jobId]);
    return [true,'Stoğa '.rtrim(rtrim(number_format((float)$j['produce_qty'],3,',','.'),'0'),',').' eklendi.'];
  }catch(Throwable $e){ return [false,$e->getMessage()]; }
}

// Üretim→stok kutusu (mobil+web ortak). $j: jobs satırı (id,produce_item_id,produce_qty,produced).
function produce_box_html($pdo,$j){
  $h='<div style="margin-top:12px;padding:11px;background:rgba(34,197,94,.08);border:1px solid rgba(34,197,94,.3);border-radius:10px">';
  if(!empty($j['produce_item_id'])){
    $pnm='?'; try{ $pn=$pdo->prepare("SELECT name FROM stock_items WHERE id=?"); $pn->execute([(int)$j['produce_item_id']]); $r=$pn->fetch(); if($r)$pnm=$r['name']; }catch(Throwable $e){}
    $pq=rtrim(rtrim(number_format((float)$j['produce_qty'],3,',','.'),'0'),',');
    $h.='📦 Üretilecek stok: <b>'.htmlspecialchars($pnm).'</b> × '.$pq;
    if((int)$j['produced']===1){
      $h.='<div style="color:#16a34a;font-weight:700;margin-top:6px">✅ Stoğa eklendi</div>';
    }else{
      $h.='<div style="font-size:12px;opacity:.8;margin:4px 0">Tüm aşamalar "Tamam" olunca otomatik eklenir. Hemen eklemek için:</div>';
      $h.='<form method="post" style="margin:0"><button class="btn" name="produce_stock" value="1" style="background:#16a34a;color:#fff;padding:10px;width:100%">📦 Üretimi Stoğa Ekle ('.$pq.')</button></form>';
    }
  }else{
    $items=[]; try{ $items=$pdo->query("SELECT id,name FROM stock_items ORDER BY name LIMIT 300")->fetchAll(); }catch(Throwable $e){}
    $h.='<b>📦 Stok ürünü bağla</b><div style="font-size:12px;opacity:.8;margin:2px 0 6px">Üretim bitince bu ürün otomatik stoğa girsin.</div>';
    $h.='<form method="post" style="margin:0"><div style="display:flex;gap:8px">';
    $h.='<select name="link_item_id" style="flex:2"><option value="">— Ürün seç —</option>';
    foreach($items as $it){ $h.='<option value="'.(int)$it['id'].'">'.htmlspecialchars($it['name']).'</option>'; }
    $h.='</select><input name="link_qty" placeholder="Adet" inputmode="decimal" style="flex:1"></div>';
    $h.='<button class="btn dark" name="link_produce" value="1" style="width:100%;margin-top:8px;padding:10px">🔗 Bağla</button></form>';
  }
  return $h.'</div>';
}

// Üretim→stok POST (link + manuel ekle). true dönerse bir şey yapıldı.
function handle_produce_post($pdo,$jobId){
  if(isset($_POST['link_produce'])){
    $it=(int)($_POST['link_item_id']??0); $q=(float)str_replace(',','.',$_POST['link_qty']??'0');
    if($it>0 && $q>0){
      try{ $pdo->prepare("UPDATE jobs SET produce_item_id=?,produce_qty=?,produced=0 WHERE id=?")->execute([$it,$q,$jobId]); $_SESSION['flash']='🔗 Stok ürünü bağlandı.'; }
      catch(Throwable $e){ $_SESSION['flash']='⚠️ Bağlanamadı (DB kolonları yok → migrate çalıştır).'; }
    }else{ $_SESSION['flash']='⚠️ Ürün ve adet seç.'; }
    return true;
  }
  if(isset($_POST['produce_stock'])){
    list($pok,$pmsg)=produce_to_stock($pdo,$jobId);
    $_SESSION['flash']=($pok?'✅ ':'⚠️ ').$pmsg;
    return true;
  }
  return false;
}
