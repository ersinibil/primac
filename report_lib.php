<?php
// ACANS OS — Paylaşımlı Rapor Kütüphanesi (mobil + web ortak)
function report_modules(){ return ['tumu'=>'🗂️ Tümü','genel'=>'📊 Yekün','tahsilat'=>'💰 Tahsilat/Finans','muhasebe'=>'📒 Muhasebe','is'=>'📋 İş Takip','gorevler'=>'✓ Görevler','personel'=>'👷 Personel','satis'=>'🧾 Satış','satinalma'=>'📥 Satın Alma','teklif'=>'📄 Teklif','cari'=>'👥 Cari','stok'=>'📦 Stok']; }
// "Tümü"de yer alacak modüller (özet hariç tek tek hepsi)
function report_all_keys(){ return ['genel','tahsilat','muhasebe','is','gorevler','personel','satis','satinalma','teklif','cari','stok']; }
// Tüm modülleri tek raporda alt alta render et
function report_render_all($pdo,$appName,$from,$to,$detail=false){
  $out='';
  foreach(report_all_keys() as $m){ $R=rpt($pdo,$m,$from,$to,0,$detail); $out.=report_render($R,$appName,$from,$to,$detail).'<div style="height:20px"></div>'; }
  return $out;
}
// Tüm modüllerin CSV'si tek dosyada
function build_csv_all($pdo,$appName,$from,$to){
  $o="\xEF\xBB\xBF";
  foreach(report_all_keys() as $m){ $R=rpt($pdo,$m,$from,$to);
    $o.=csv_cell('=== '.$R['title'].' ===')."\r\n";
    foreach($R['cards'] as $c){ $o.=csv_cell($c[1]).';'.csv_cell($c[2])."\r\n"; }
    $o.="\r\n".implode(';',array_map('csv_cell',$R['table']['head']))."\r\n";
    foreach($R['table']['rows'] as $row){ $o.=implode(';',array_map('csv_cell',$row))."\r\n"; }
    $o.="\r\n\r\n";
  }
  return $o;
}

/* ===== Toplu Cari Ekstre — birden çok cariyi (mode/type ile süzülmüş) tek raporda alt alta ekstrelendirir =====
   mode: ''=Tüm Bakiyeler, 'receivable'=Alacaklı Cariler, 'payable'=Borçlu Cariler, 'zero'=Sıfır Bakiyeler
   type: ''=Tümü, 'Müşteri', 'Tedarikçi', 'Her İkisi'  (contacts_report.php'deki filtre mantığıyla aynı) */
function cari_toplu_list($pdo,$mode='',$type=''){
  $where=[]; $params=[];
  if($type){ $where[]="c.type=?"; $params[]=$type; }
  $sqlWhere = $where ? 'WHERE '.implode(' AND ',$where) : '';
  $sql="SELECT c.id,c.name,COALESCE(c.opening_balance,0)+
      COALESCE((SELECT SUM(CASE WHEN direction='in' THEN amount ELSE -amount END) FROM finance_movements WHERE contact_id=c.id),0) bal
      FROM contacts c $sqlWhere ORDER BY c.name";
  $st=$pdo->prepare($sql); $st->execute($params);
  $list=[];
  foreach($st->fetchAll() as $r){
    $bal=(float)$r['bal'];
    if($mode==='receivable' && $bal<=0) continue;
    if($mode==='payable' && $bal>=0) continue;
    if($mode==='zero' && abs($bal)>0.01) continue;
    $list[]=['id'=>(int)$r['id'],'name'=>$r['name'],'bal'=>$bal];
  }
  return $list;
}

function cari_toplu_mode_label($mode){
  return $mode==='receivable'?'Alacaklı Cariler':($mode==='payable'?'Borçlu Cariler':($mode==='zero'?'Sıfır Bakiyeler':'Tüm Cariler'));
}

// Özet başlık $R'ı üretir (kartlar + cari listesi tablosu) — $list dışarıdan verilirse tekrar sorgulanmaz
function cari_toplu_summary($pdo,$mode='',$type='',$list=null){
  if($list===null) $list=cari_toplu_list($pdo,$mode,$type);
  $totAlacak=0;$totBorc=0; foreach($list as $c){ if($c['bal']>0)$totAlacak+=$c['bal']; else $totBorc+=abs($c['bal']); }
  $S=['title'=>'Toplu Cari Ekstre — '.cari_toplu_mode_label($mode).' ('.($type?:'Tümü').')',
      'cards'=>[['👥','Cari Sayısı',count($list),'#a78bfa'],['🟢','Toplam Alacak',tl($totAlacak),'#22c55e'],['🔴','Toplam Borç',tl($totBorc),'#f87171']],
      'chart'=>null,'table'=>['head'=>['Cari','Bakiye'],'rows'=>[],'links'=>[]]];
  foreach($list as $c){ $S['table']['rows'][]=[$c['name'],tl($c['bal'])]; $S['table']['links'][]='contact_view.php?id='.$c['id']; }
  return $S;
}

// Özet kart + HER cari için tekil cari_detay ekstresini alt alta render eder (report_render_all ile aynı teknik)
function report_render_cari_toplu($pdo,$appName,$from,$to,$mode='',$type='',$detail=false){
  $out='';
  try{
    $list=cari_toplu_list($pdo,$mode,$type);
    $S=cari_toplu_summary($pdo,$mode,$type,$list);
    $out.=report_render($S,$appName,$from,$to,$detail).'<div style="height:20px"></div>';
    foreach($list as $c){
      $R=rpt($pdo,'cari_detay',$from,$to,$c['id'],$detail);
      $out.=report_render($R,$appName,$from,$to,$detail).'<div style="height:20px"></div>';
    }
  }catch(Throwable $e){ $out.='<div class="rep-card" style="color:#fca5a5">'.htmlspecialchars($e->getMessage()).'</div>'; }
  return $out;
}

// Toplu ekstrenin CSV'si — özet + her carinin kendi ekstresi tek dosyada (build_csv_all ile aynı teknik)
function build_csv_cari_toplu($pdo,$appName,$from,$to,$mode='',$type=''){
  $o="\xEF\xBB\xBF";
  $list=cari_toplu_list($pdo,$mode,$type);
  $S=cari_toplu_summary($pdo,$mode,$type,$list);
  $o.=csv_cell('=== '.$S['title'].' ===')."\r\n";
  foreach($S['cards'] as $c){ $o.=csv_cell($c[1]).';'.csv_cell($c[2])."\r\n"; }
  $o.="\r\n";
  foreach($list as $c){
    $R=rpt($pdo,'cari_detay',$from,$to,$c['id']);
    $o.=csv_cell('=== '.$R['title'].' ===')."\r\n";
    foreach($R['cards'] as $cd){ $o.=csv_cell($cd[1]).';'.csv_cell($cd[2])."\r\n"; }
    $o.="\r\n".implode(';',array_map('csv_cell',$R['table']['head']))."\r\n";
    foreach($R['table']['rows'] as $row){ $o.=implode(';',array_map('csv_cell',$row))."\r\n"; }
    $o.="\r\n\r\n";
  }
  return $o;
}

function tl($v){ return number_format((float)$v,2,',','.').' ₺'; }

function rpt($pdo,$modul,$from,$to,$ref=0,$detail=false){
  $R=['title'=>'','cards'=>[],'chart'=>null,'table'=>['head'=>[],'rows'=>[]]];
  try{ switch($modul){

  case 'tahsilat':
    $R['title']='Tahsilat / Finans';
    // Hesaplar arası transfer gerçek bir gider değildir, "Ödeme" toplamından hariç tutulur
    // (2026-07-03 modül-zinciri denetiminde bulundu — bkz. finance.php/dashboard.php aynı düzeltme).
    $f=$pdo->prepare("SELECT COALESCE(SUM(CASE WHEN direction='in' THEN amount END),0) tin, COALESCE(SUM(CASE WHEN direction='out' AND COALESCE(movement_type,'')<>'transfer' THEN amount END),0) tout, COUNT(*) n FROM finance_movements WHERE DATE(movement_date) BETWEEN ? AND ?");
    $f->execute([$from,$to]); $t=$f->fetch();
    $R['cards']=[['💰','Tahsilat',tl($t['tin']),'#22c55e'],['💸','Ödeme',tl($t['tout']),'#f87171'],['📈','Net',tl($t['tin']-$t['tout']),'#3b82f6'],['🔢','İşlem',$t['n'],'#a78bfa']];
    $ch=$pdo->prepare("SELECT COALESCE(NULLIF(payment_channel,''),'Diğer') k, SUM(amount) s FROM finance_movements WHERE direction='in' AND DATE(movement_date) BETWEEN ? AND ? GROUP BY k ORDER BY s DESC");
    $ch->execute([$from,$to]); $cd=[]; foreach($ch->fetchAll() as $r)$cd[$r['k']]=(float)$r['s'];
    $R['chart']=['Tahsilat — kanal',$cd];
    $ch2=$pdo->prepare("SELECT COALESCE(ac.name,'Kategorisiz') k, SUM(fm.amount) s FROM finance_movements fm LEFT JOIN accounting_categories ac ON ac.id=fm.category_id WHERE fm.direction='out' AND COALESCE(fm.movement_type,'')<>'transfer' AND DATE(fm.movement_date) BETWEEN ? AND ? GROUP BY k ORDER BY s DESC LIMIT 12");
    $ch2->execute([$from,$to]); $cd2=[]; foreach($ch2->fetchAll() as $r)$cd2[$r['k']]=(float)$r['s'];
    $R['chart2']=['Ödeme / Gider — kategoriye göre',$cd2];
    $m=$pdo->prepare("SELECT fm.movement_date,fm.direction,fm.contact_id,c.name cari,COALESCE(ac.name,'') kat,fm.amount,fm.description FROM finance_movements fm LEFT JOIN contacts c ON c.id=fm.contact_id LEFT JOIN accounting_categories ac ON ac.id=fm.category_id WHERE DATE(fm.movement_date) BETWEEN ? AND ? ORDER BY fm.movement_date,fm.id");
    $m->execute([$from,$to]); $R['table']['head']=['Tarih','Tür','Cari','Kategori','Tutar','Açıklama'];
    foreach($m->fetchAll() as $r){ $R['table']['rows'][]=[$r['movement_date'],$r['direction']==='in'?'Tahsilat':'Ödeme',$r['cari'],$r['kat'],tl($r['amount']),$r['description']];
      $R['table']['links'][]=$r['contact_id']?('contact_view.php?id='.(int)$r['contact_id']):null; }
    break;

  case 'muhasebe':
    // 2026-07-03: Muhasebe artık finance_movements'ta movement_type='muhasebe' olan satırlar
    // (bkz. database/migrations/035_finance_accounting_merge.sql) — ayrı bir accounting_entries
    // tablosu değil, finans genelinden okunur.
    $R['title']='Muhasebe (Kategori Gider/Gelir)';
    $s=$pdo->prepare("SELECT COALESCE(SUM(CASE WHEN direction='in' THEN amount END),0) gelir, COALESCE(SUM(CASE WHEN direction='out' THEN amount END),0) gider, COUNT(*) n FROM finance_movements WHERE movement_type='muhasebe' AND movement_date BETWEEN ? AND ?");
    $s->execute([$from,$to]); $t=$s->fetch();
    $R['cards']=[['📈','Gelir',tl($t['gelir']),'#22c55e'],['📉','Gider',tl($t['gider']),'#f87171'],['📊','Net',tl($t['gelir']-$t['gider']),'#3b82f6'],['🔢','Kayıt',$t['n'],'#a78bfa']];
    $cc=$pdo->prepare("SELECT COALESCE(ac.name,'Kategorisiz') k, SUM(fm.amount) s FROM finance_movements fm LEFT JOIN accounting_categories ac ON ac.id=fm.category_id WHERE fm.movement_type='muhasebe' AND fm.direction='out' AND fm.movement_date BETWEEN ? AND ? GROUP BY k ORDER BY s DESC LIMIT 12");
    $cc->execute([$from,$to]); $cd=[]; foreach($cc->fetchAll() as $r)$cd[$r['k']]=(float)$r['s'];
    $R['chart']=['Gider — kategoriye göre',$cd];
    $cc2=$pdo->prepare("SELECT COALESCE(ac.name,'Kategorisiz') k, SUM(fm.amount) s FROM finance_movements fm LEFT JOIN accounting_categories ac ON ac.id=fm.category_id WHERE fm.movement_type='muhasebe' AND fm.direction='in' AND fm.movement_date BETWEEN ? AND ? GROUP BY k ORDER BY s DESC LIMIT 12");
    $cc2->execute([$from,$to]); $cd2=[]; foreach($cc2->fetchAll() as $r)$cd2[$r['k']]=(float)$r['s'];
    $R['chart2']=['Gelir — kategoriye göre',$cd2];
    $m=$pdo->prepare("SELECT fm.movement_date entry_date,fm.direction,COALESCE(ac.name,'Kategorisiz') kat,fm.amount,fm.description FROM finance_movements fm LEFT JOIN accounting_categories ac ON ac.id=fm.category_id WHERE fm.movement_type='muhasebe' AND fm.movement_date BETWEEN ? AND ? ORDER BY fm.movement_date DESC,fm.id DESC");
    $m->execute([$from,$to]); $R['table']['head']=['Tarih','Tür','Kategori','Tutar','Açıklama'];
    foreach($m->fetchAll() as $r)$R['table']['rows'][]=[$r['entry_date'],$r['direction']==='in'?'Gelir':'Gider',$r['kat'],($r['direction']==='out'?'-':'').tl($r['amount']),$r['description']];
    break;

  case 'is':
    $R['title']='İş Takip';
    $a=$pdo->prepare("SELECT COUNT(*) n FROM jobs WHERE DATE(created_at) BETWEEN ? AND ?"); $a->execute([$from,$to]); $acilan=(int)$a->fetch()['n'];
    $st=$pdo->prepare("SELECT status,COUNT(*) c FROM jobs WHERE DATE(created_at) BETWEEN ? AND ? GROUP BY status"); $st->execute([$from,$to]);
    $cd=[]; $tamam=0; foreach($st->fetchAll() as $r){ $cd[$r['status']]=(int)$r['c']; if(in_array($r['status'],['Tamamlandı','Teslim Edildi']))$tamam+=(int)$r['c']; }
    $g=$pdo->prepare("SELECT COUNT(*) n FROM jobs WHERE status NOT IN ('Tamamlandı','İptal','Teslim Edildi') AND due_date IS NOT NULL AND due_date<CURDATE()"); $g->execute(); $geciken=(int)$g->fetch()['n'];
    $R['cards']=[['📋','Açılan',$acilan,'#3b82f6'],['✓','Tamamlanan',$tamam,'#22c55e'],['▶','Açık',$acilan-$tamam,'#eab308'],['⏰','Geciken',$geciken,'#f87171']];
    $R['chart']=['Duruma göre iş',$cd];
    $j=$pdo->prepare("SELECT j.id,j.job_no,j.title,j.status,j.due_date,p.name FROM jobs j LEFT JOIN personnel p ON p.id=j.responsible_personnel_id WHERE DATE(j.created_at) BETWEEN ? AND ? ORDER BY j.id DESC");
    $j->execute([$from,$to]); $R['table']['head']=['No','İş','Durum','Termin','Sorumlu'];
    foreach($j->fetchAll() as $r){ $R['table']['rows'][]=[$r['job_no'],$r['title'],$r['status'],$r['due_date'],$r['name']];
      $R['table']['links'][]='job_view.php?id='.(int)$r['id']; }
    break;

  case 'gorevler':
    $R['title']='Görevler';
    $a=$pdo->prepare("SELECT COUNT(*) n FROM tasks WHERE DATE(created_at) BETWEEN ? AND ?"); $a->execute([$from,$to]); $acilan=(int)$a->fetch()['n'];
    $st=$pdo->prepare("SELECT status,COUNT(*) c FROM tasks WHERE DATE(created_at) BETWEEN ? AND ? GROUP BY status"); $st->execute([$from,$to]);
    $cd=[]; $tamam=0; foreach($st->fetchAll() as $r){ $cd[$r['status']]=(int)$r['c']; if($r['status']==='Tamamlandı')$tamam+=(int)$r['c']; }
    $g=$pdo->prepare("SELECT COUNT(*) n FROM tasks WHERE status NOT IN ('Tamamlandı','İptal') AND due_date IS NOT NULL AND due_date<CURDATE()"); $g->execute(); $geciken=(int)$g->fetch()['n'];
    $R['cards']=[['📋','Oluşturulan',$acilan,'#3b82f6'],['✓','Tamamlanan',$tamam,'#22c55e'],['▶','Açık',$acilan-$tamam,'#eab308'],['⏰','Geciken',$geciken,'#f87171']];
    $R['chart']=['Duruma göre görev',$cd];
    $pp=$pdo->prepare("SELECT t.personnel_id, COALESCE(p.name,'Atanmamış') pn, COUNT(*) c, SUM(CASE WHEN t.status='Tamamlandı' THEN 1 ELSE 0 END) tm FROM tasks t LEFT JOIN personnel p ON p.id=t.personnel_id WHERE DATE(t.created_at) BETWEEN ? AND ? GROUP BY t.personnel_id ORDER BY c DESC");
    $pp->execute([$from,$to]); $R['table']['head']=['Personel','Toplam','Tamamlanan'];
    foreach($pp->fetchAll() as $r){ $R['table']['rows'][]=[$r['pn'],$r['c'],$r['tm']];
      $R['table']['links'][]=$r['personnel_id']?('tasks.php?f=all&p='.(int)$r['personnel_id']):null; }
    break;

  case 'personel':
    $R['title']='Personel Performans';
    $ps=$pdo->query("SELECT p.id,p.name,
      (SELECT COUNT(*) FROM jobs j WHERE j.responsible_personnel_id=p.id) ist,
      (SELECT COUNT(*) FROM jobs j WHERE j.responsible_personnel_id=p.id AND j.status IN ('Tamamlandı','Teslim Edildi')) itm,
      (SELECT COUNT(*) FROM tasks t WHERE t.personnel_id=p.id) gt,
      (SELECT COUNT(*) FROM tasks t WHERE t.personnel_id=p.id AND t.status='Tamamlandı') gtm
      FROM personnel p WHERE COALESCE(p.active,1)=1")->fetchAll();
    $cd=[]; $best=0;$bestN='';
    foreach($ps as $k=>$r){ $io=$r['ist']>0?$r['itm']/$r['ist']*100:0; $go=$r['gt']>0?$r['gtm']/$r['gt']*100:0; $sc=($r['ist']||$r['gt'])?round(0.5*$io+0.5*$go):0;
      $cd[$r['name']]=(int)$r['itm']; if($sc>$best){$best=$sc;$bestN=$r['name'];} $ps[$k]['sc']=$sc; }
    usort($ps,function($a,$b){ return $b['sc']<=>$a['sc']; });
    if($detail){
      // DETAYLI: her personelin açık işleri
      $oj=$pdo->prepare("SELECT id,job_no,title,status,due_date FROM jobs WHERE responsible_personnel_id=? AND status NOT IN ('Tamamlandı','Teslim Edildi','İptal') ORDER BY (due_date IS NULL), due_date");
      $R['table']['head']=['Personel (Puan)','İş No','Açık İş','Durum','Termin'];
      foreach($ps as $r){ $oj->execute([$r['id']]); $jobs=$oj->fetchAll(); $lbl=$r['name'].' ('.$r['sc'].')';
        if($jobs){ foreach($jobs as $i=>$j){ $gec=(!empty($j['due_date'])&&$j['due_date']<date('Y-m-d'))?' ⏰':'';
          $R['table']['rows'][]=[$i===0?$lbl:'',$j['job_no'],$j['title'],$j['status'],($j['due_date']?:'-').$gec];
          $R['table']['links'][]='job_view.php?id='.(int)$j['id']; } }
        else { $R['table']['rows'][]=[$lbl,'-','açık iş yok','','']; $R['table']['links'][]=null; } }
    } else {
      // ÖZET: KPI tablosu
      $R['table']['head']=['Personel','İş','Tamam','Görev','Puan'];
      foreach($ps as $r){ $R['table']['rows'][]=[$r['name'],$r['ist'],$r['itm'],$r['gtm'].'/'.$r['gt'],$r['sc']]; }
    }
    $R['cards']=[['👷','Personel',count($ps),'#a78bfa'],['🏆','Lider',$bestN?:'-','#eab308'],['⭐','En Yüksek Puan',$best,'#22c55e']];
    arsort($cd); $R['chart']=['Tamamlanan iş — personel',$cd];
    break;

  case 'satis':
    $R['title']='Satış';
    $s=$pdo->prepare("SELECT COALESCE(SUM(amount),0) tut,COUNT(*) n FROM finance_movements WHERE movement_type LIKE '%sale%' AND DATE(movement_date) BETWEEN ? AND ?");
    $s->execute([$from,$to]); $sd=$s->fetch();
    $R['cards']=[['🧾','Toplam Satış',tl($sd['tut']),'#f97316'],['🔢','İşlem',$sd['n'],'#3b82f6'],['📊','Ortalama',tl($sd['n']>0?$sd['tut']/$sd['n']:0),'#22c55e']];
    $cc=$pdo->prepare("SELECT c.name k,SUM(fm.amount) s FROM finance_movements fm LEFT JOIN contacts c ON c.id=fm.contact_id WHERE fm.movement_type LIKE '%sale%' AND DATE(fm.movement_date) BETWEEN ? AND ? GROUP BY fm.contact_id ORDER BY s DESC LIMIT 8");
    $cc->execute([$from,$to]); $cd=[]; foreach($cc->fetchAll() as $r)$cd[$r['k']?:'—']=(float)$r['s'];
    $R['chart']=['Cariye göre satış',$cd];
    $m=$pdo->prepare("SELECT fm.movement_date,fm.contact_id,c.name cari,fm.amount,fm.description FROM finance_movements fm LEFT JOIN contacts c ON c.id=fm.contact_id WHERE fm.movement_type LIKE '%sale%' AND DATE(fm.movement_date) BETWEEN ? AND ? ORDER BY fm.id DESC");
    $m->execute([$from,$to]); $R['table']['head']=['Tarih','Cari','Tutar','Açıklama'];
    foreach($m->fetchAll() as $r){ $R['table']['rows'][]=[$r['movement_date'],$r['cari'],tl($r['amount']),$r['description']];
      $R['table']['links'][]=$r['contact_id']?('contact_view.php?id='.(int)$r['contact_id']):null; }
    break;

  case 'satinalma':
    $R['title']='Satın Alma';
    $s=$pdo->prepare("SELECT COALESCE(SUM(fm.amount),0) tut,COUNT(*) n FROM finance_movements fm WHERE fm.movement_type='purchase' AND DATE(fm.movement_date) BETWEEN ? AND ?");
    $s->execute([$from,$to]); $sd=$s->fetch();
    $R['cards']=[['📥','Toplam Satın Alma',tl($sd['tut']),'#3b82f6'],['🔢','İşlem',$sd['n'],'#a78bfa'],['📊','Ortalama',tl($sd['n']>0?$sd['tut']/$sd['n']:0),'#22c55e']];
    // Tedarikçi bazlı kırılım
    $cc=$pdo->prepare("SELECT c.name k,SUM(fm.amount) s FROM finance_movements fm LEFT JOIN contacts c ON c.id=fm.contact_id WHERE fm.movement_type='purchase' AND DATE(fm.movement_date) BETWEEN ? AND ? GROUP BY fm.contact_id ORDER BY s DESC LIMIT 8");
    $cc->execute([$from,$to]); $cd=[]; foreach($cc->fetchAll() as $r)$cd[$r['k']?:'—']=(float)$r['s'];
    $R['chart']=['Tedarikçiye göre satın alma',$cd];
    // Ödeme yöntemi dağılımı
    $pm=$pdo->prepare("SELECT COALESCE(fm.payment_channel,'Veresiye') k,SUM(fm.amount) s FROM finance_movements fm WHERE fm.movement_type='purchase' AND DATE(fm.movement_date) BETWEEN ? AND ? GROUP BY fm.payment_channel ORDER BY s DESC");
    $pm->execute([$from,$to]); $cd2=[]; foreach($pm->fetchAll() as $r)$cd2[$r['k']]=(float)$r['s'];
    $R['chart2']=['Ödeme yöntemine göre',$cd2];
    $m=$pdo->prepare("SELECT fm.movement_date,fm.contact_id,c.name cari,fm.payment_channel,fm.amount,fm.description FROM finance_movements fm LEFT JOIN contacts c ON c.id=fm.contact_id WHERE fm.movement_type='purchase' AND DATE(fm.movement_date) BETWEEN ? AND ? ORDER BY fm.movement_date DESC");
    $m->execute([$from,$to]); $R['table']['head']=['Tarih','Tedarikçi','Ödeme Yöntemi','Tutar','Açıklama'];
    foreach($m->fetchAll() as $r){ $R['table']['rows'][]=[$r['movement_date'],$r['cari'],$r['payment_channel']??'—',tl($r['amount']),$r['description']];
      $R['table']['links'][]=$r['contact_id']?('contact_view.php?id='.(int)$r['contact_id']):null; }
    break;

  case 'teklif':
    $R['title']='Teklif';
    $s=$pdo->prepare("SELECT COUNT(*) n,COALESCE(SUM(total),0) tut,COALESCE(SUM(CASE WHEN status='Kabul' THEN total ELSE 0 END),0) kabul FROM quotes WHERE DATE(quote_date) BETWEEN ? AND ?");
    $s->execute([$from,$to]); $sd=$s->fetch();
    $R['cards']=[['📄','Teklif Sayısı',$sd['n'],'#3b82f6'],['💰','Toplam Tutar',tl($sd['tut']),'#6366f1'],['✅','Kabul Edilen',tl($sd['kabul']),'#22c55e']];
    $cc=$pdo->prepare("SELECT status k,COUNT(*) s FROM quotes WHERE DATE(quote_date) BETWEEN ? AND ? GROUP BY status");
    $cc->execute([$from,$to]); $cd=[]; foreach($cc->fetchAll() as $r)$cd[$r['k']?:'—']=(float)$r['s'];
    $R['chart']=['Duruma göre teklif',$cd];
    $m=$pdo->prepare("SELECT id,quote_date,quote_no,customer_name,total,status FROM quotes WHERE DATE(quote_date) BETWEEN ? AND ? ORDER BY id DESC");
    $m->execute([$from,$to]); $R['table']['head']=['Tarih','No','Müşteri','Tutar','Durum'];
    foreach($m->fetchAll() as $r){ $R['table']['rows'][]=[$r['quote_date'],$r['quote_no'],$r['customer_name'],tl($r['total']),$r['status']];
      $R['table']['links'][]='teklif.php?id='.(int)$r['id']; }
    break;

  case 'cari':
    $R['title']='Cari';
    $tot=(int)$pdo->query("SELECT COUNT(*) c FROM contacts")->fetch()['c'];
    $cl=$pdo->query("SELECT c.id,c.name,COALESCE(c.opening_balance,0)+
      COALESCE((SELECT SUM(CASE WHEN direction='in' THEN amount ELSE -amount END) FROM finance_movements WHERE contact_id=c.id),0) bal
      FROM contacts c ORDER BY ABS(COALESCE(c.opening_balance,0)+COALESCE((SELECT SUM(CASE WHEN direction='in' THEN amount ELSE -amount END) FROM finance_movements WHERE contact_id=c.id),0)) DESC LIMIT 50")->fetchAll();
    $alacak=0;$borc=0;$cd=[];
    foreach($cl as $r){ $b=(float)$r['bal']; if($b>0)$alacak+=$b; else $borc+=abs($b); if(count($cd)<8 && $b!=0)$cd[$r['name']]=abs($b); }
    $R['cards']=[['👥','Cari',$tot,'#a78bfa'],['🟢','Alacak',tl($alacak),'#22c55e'],['🔴','Borç',tl($borc),'#f87171']];
    $R['chart']=['En yüksek bakiye',$cd];
    $R['table']['head']=['Cari','Bakiye'];
    foreach($cl as $r){ $R['table']['rows'][]=[$r['name'],tl($r['bal'])]; $R['table']['links'][]='contact_view.php?id='.(int)$r['id']; }
    break;

  case 'stok':
    $R['title']='Stok';
    $tot=(int)$pdo->query("SELECT COUNT(*) c FROM stock_items")->fetch()['c'];
    $val=(float)$pdo->query("SELECT COALESCE(SUM(quantity*COALESCE(sale_price,0)),0) v FROM stock_items")->fetch()['v'];
    $krit=$pdo->query("SELECT id,name,quantity,unit,critical_level FROM stock_items WHERE quantity<=critical_level ORDER BY quantity")->fetchAll();
    $R['cards']=[['📦','Ürün',$tot,'#3b82f6'],['💎','Stok Değeri',tl($val),'#22c55e'],['⚠️','Kritik',count($krit),'#f87171']];
    $cd=[]; foreach($krit as $r){ if(count($cd)<8)$cd[$r['name']]=(float)$r['quantity']; }
    $R['chart']=['Kritik ürün stok',$cd];
    $R['table']['head']=['Ürün','Stok','Kritik Seviye'];
    foreach($krit as $r){ $R['table']['rows'][]=[$r['name'],$r['quantity'].' '.$r['unit'],$r['critical_level']]; $R['table']['links'][]='product_view.php?id='.(int)$r['id']; }
    break;

  case 'cari_detay': // TEK CARİ EKSTRE
    $cn=$pdo->prepare("SELECT name,COALESCE(opening_balance,0) ob FROM contacts WHERE id=?"); $cn->execute([$ref]); $cc=$cn->fetch();
    $R['title']='Cari: '.($cc['name']?:('#'.$ref));
    $ft=$pdo->prepare("SELECT COALESCE(SUM(CASE WHEN direction='in' THEN amount END),0) tin,COALESCE(SUM(CASE WHEN direction='out' THEN amount END),0) tout FROM finance_movements WHERE contact_id=? AND DATE(movement_date) BETWEEN ? AND ?");
    $ft->execute([$ref,$from,$to]); $t=$ft->fetch();
    $allBal=(float)$cc['ob'] + (float)($pdo->query("SELECT COALESCE(SUM(CASE WHEN direction='in' THEN amount ELSE -amount END),0) s FROM finance_movements WHERE contact_id=".(int)$ref)->fetch()['s']);
    $jc=$pdo->prepare("SELECT COUNT(*) n FROM jobs WHERE customer_id=?"); $jc->execute([$ref]); $jn=(int)$jc->fetch()['n'];
    $R['cards']=[['💼','Güncel Bakiye',tl($allBal),$allBal<0?'#f87171':'#22c55e'],['💰','Tahsilat',tl($t['tin']),'#22c55e'],['💸','Ödeme',tl($t['tout']),'#f87171'],['📋','İş',$jn,'#3b82f6']];
    $mv=$pdo->prepare("SELECT movement_date,direction,amount,description FROM finance_movements WHERE contact_id=? AND DATE(movement_date) BETWEEN ? AND ? ORDER BY movement_date,id");
    $mv->execute([$ref,$from,$to]); $cd=[];
    $R['table']['head']=['Tarih','Tür','Tutar','Açıklama'];
    foreach($mv->fetchAll() as $r){ $R['table']['rows'][]=[$r['movement_date'],$r['direction']==='in'?'Tahsilat':'Ödeme',($r['direction']==='in'?'':'-').tl($r['amount']),$r['description']]; $R['table']['links'][]=null; $cd[$r['direction']==='in'?'Tahsilat':'Ödeme']=($cd[$r['direction']==='in'?'Tahsilat':'Ödeme']??0)+(float)$r['amount']; }
    // işleri de ekle — 2026-07-03 düzeltmesi: eskiden job_no "Tutar" sütununa yazılıyordu
    // (parasal olmayan bir metin parasal sütunda görünüyordu), sütunlar artık başlıkla eşleşiyor.
    $jl=$pdo->prepare("SELECT id,job_no,title,status,due_date,sale_amount,created_at FROM jobs WHERE customer_id=? ORDER BY id DESC LIMIT 30"); $jl->execute([$ref]);
    foreach($jl->fetchAll() as $r){
        $tarih=$r['due_date'] ?: ($r['created_at'] ? substr($r['created_at'],0,10) : '');
        $tutar=(float)$r['sale_amount']>0 ? tl($r['sale_amount']) : '—';
        $R['table']['rows'][]=[$tarih,'📋 İş: '.$r['status'],$tutar,$r['job_no'].' — '.$r['title']];
        $R['table']['links'][]='job_view.php?id='.(int)$r['id'];
    }
    $R['chart']=['Tahsilat / Ödeme',$cd];
    break;

  default: // genel / yekün
    $R['title']='Yekün Özet';
    $f=$pdo->prepare("SELECT COALESCE(SUM(CASE WHEN direction='in' THEN amount END),0) tin,COALESCE(SUM(CASE WHEN direction='out' AND COALESCE(movement_type,'')<>'transfer' THEN amount END),0) tout FROM finance_movements WHERE DATE(movement_date) BETWEEN ? AND ?"); $f->execute([$from,$to]); $t=$f->fetch();
    $ss=$pdo->prepare("SELECT COALESCE(SUM(amount),0) s FROM finance_movements WHERE movement_type LIKE '%sale%' AND DATE(movement_date) BETWEEN ? AND ?"); $ss->execute([$from,$to]); $sale=(float)$ss->fetch()['s'];
    $jo=$pdo->prepare("SELECT COUNT(*) n FROM jobs WHERE DATE(created_at) BETWEEN ? AND ?"); $jo->execute([$from,$to]); $acilan=(int)$jo->fetch()['n'];
    $jd=$pdo->prepare("SELECT COUNT(*) n FROM jobs WHERE status IN ('Tamamlandı','Teslim Edildi') AND DATE(created_at) BETWEEN ? AND ?"); $jd->execute([$from,$to]); $tamam=(int)$jd->fetch()['n'];
    $lFin='report.php?modul=tahsilat&from='.urlencode($from).'&to='.urlencode($to);
    $lSatis='report.php?modul=satis&from='.urlencode($from).'&to='.urlencode($to);
    $lIs='report.php?modul=is&from='.urlencode($from).'&to='.urlencode($to);
    $R['cards']=[['💰','Tahsilat',tl($t['tin']),'#22c55e',$lFin],['💸','Ödeme',tl($t['tout']),'#f87171',$lFin],['📈','Net',tl($t['tin']-$t['tout']),'#3b82f6',$lFin],['🧾','Satış',tl($sale),'#f97316',$lSatis],['📋','Açılan İş',$acilan,'#a78bfa',$lIs],['✓','Tamamlanan',$tamam,'#14b8a6',$lIs]];
    $R['chart']=['Genel',['Tahsilat'=>(float)$t['tin'],'Ödeme'=>(float)$t['tout'],'Satış'=>$sale]];
    $R['table']['head']=['Kalem','Değer'];
    $R['table']['rows']=[['Tahsilat',tl($t['tin'])],['Ödeme',tl($t['tout'])],['Net',tl($t['tin']-$t['tout'])],['Satış',tl($sale)],['Açılan İş',$acilan],['Tamamlanan İş',$tamam]];
    $R['table']['links']=[$lFin,$lFin,$lFin,$lSatis,$lIs,$lIs];
  }}catch(Throwable $e){ $R['error']=$e->getMessage(); }
  return $R;
}

function csv_cell($v){ return '"'.str_replace('"','""',(string)$v).'"'; }

function build_csv($R,$appName,$from,$to){
  $o="\xEF\xBB\xBF";
  $o.=csv_cell($appName.' Rapor').';'.csv_cell($R['title']).';'.csv_cell($from.' - '.$to)."\r\n\r\n";
  foreach($R['cards'] as $c){ $o.=csv_cell($c[1]).';'.csv_cell($c[2])."\r\n"; }
  $o.="\r\n".implode(';',array_map('csv_cell',$R['table']['head']))."\r\n";
  foreach($R['table']['rows'] as $row){ $o.=implode(';',array_map('csv_cell',$row))."\r\n"; }
  return $o;
}

/* ===== Görsel infografik render (mobil + web ORTAK) ===== */
function report_render($R,$appName,$from,$to,$full=true){
  static $styled=false;          // <style> sadece bir kez çıksın (Tümü'de 7 kez tekrarlanmasın)
  $ic = htmlspecialchars(mb_substr($appName,0,1));
  // kritik değer tespiti: eksi para / "Geciken" vb.
  $crit = function($v){ $s=(string)$v; return (strpos($s,'-')===0 && strpos($s,'₺')!==false); };
  ob_start();
  if($styled){ echo "\n"; } else { $styled=true; ?>
<style>
.rep{--ink:#0f172a}
.rep *{box-sizing:border-box}
.rep-hero{background:linear-gradient(135deg,#1e3a8a 0%,#2563eb 55%,#06b6d4 100%);border-radius:20px;padding:20px;color:#fff;display:flex;align-items:center;gap:14px;box-shadow:0 12px 30px rgba(37,99,235,.35)}
.rep-hero .lg{width:58px;height:58px;border-radius:16px;background:#fff;color:#1e3a8a;display:flex;align-items:center;justify-content:center;font-weight:1000;font-size:28px;flex:0 0 auto}
.rep-hero h2{margin:0;font-size:20px}.rep-hero .dt{opacity:.9;font-size:13px;margin-top:2px}
.rep-tiles{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px;margin:14px 0}
.rep-tile{position:relative;border-radius:18px;padding:16px;color:#fff;overflow:hidden;box-shadow:0 8px 20px rgba(0,0,0,.18)}
.rep-tile .ic{font-size:26px;opacity:.9}
.rep-tile .vl{font-size:23px;font-weight:1000;margin-top:6px;line-height:1.1}
.rep-tile .lb{font-size:12.5px;opacity:.92;margin-top:3px;font-weight:600}
.rep-card{background:#0f1d33;border:1px solid #1e3350;border-radius:18px;padding:16px;margin:12px 0;color:#e5edf7}
.rep-card .ttl{font-weight:900;margin-bottom:10px;font-size:15px;color:#e5edf7}
.rep-bar{display:flex;align-items:center;gap:10px;margin:7px 0;font-size:13px}
.rep-bar .l{width:32%;color:#cbd5e1;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.rep-bar .t{flex:1;height:22px;background:rgba(255,255,255,.08);border-radius:8px;overflow:hidden}
.rep-bar .f{height:100%;border-radius:8px;background:linear-gradient(90deg,#22c55e,#a3e635)}
.rep-bar .v{width:24%;text-align:right;font-weight:800;color:#fff}
table.rep-tbl{width:100%;border-collapse:collapse;font-size:13px;color:#e5edf7}
table.rep-tbl th{text-align:left;color:#93a4bf;border-bottom:2px solid #1e3350;padding:8px 6px;font-size:11px;text-transform:uppercase;letter-spacing:.04em}
table.rep-tbl td{padding:8px 6px;border-bottom:1px solid rgba(255,255,255,.06);color:#e5edf7}
table.rep-tbl tr:nth-child(even) td{background:rgba(255,255,255,.025)}
.rep-neg{color:#f87171;font-weight:800}
.rep-foot{text-align:center;color:#64748b;font-size:12px;margin:14px 0 4px}
@media print{
  /* SADECE raporu yazdır — uygulama arayüzünü (üst bar, bildirim bandı, alt menü, sidebar) gizle */
  body{background:#fff!important}
  body *{visibility:hidden!important}
  .rep,.rep *{visibility:visible!important;-webkit-print-color-adjust:exact;print-color-adjust:exact}
  .rep{position:absolute!important;left:0;top:0;width:100%;padding:0!important}
}
</style>
<?php } ?>
<div class="rep">
  <div class="rep-hero">
    <div class="lg"><img src="<?=htmlspecialchars(base_url().(function_exists('brand_logo')?brand_logo():'logo.png'))?>" alt="<?=$ic?>" style="width:100%;height:100%;object-fit:contain;border-radius:inherit" onerror="this.parentNode.textContent='<?=$ic?>'"></div>
    <div style="flex:1"><h2><?=htmlspecialchars($appName)?> · <?=htmlspecialchars($R['title'])?> Raporu</h2>
      <div class="dt">📅 <?=htmlspecialchars($from)?> — <?=htmlspecialchars($to)?> · Oluşturma: <?=date('d.m.Y H:i')?></div></div>
  </div>

  <div class="rep-tiles">
    <?php foreach($R['cards'] as $c): $col=$c[3]; $clink=$c[4] ?? null; $tag=$clink?'a':'div'; ?>
      <<?=$tag?> class="rep-tile"<?=$clink?' href="'.htmlspecialchars($clink).'"':''?> style="background:linear-gradient(135deg,<?=$col?> 0%,<?=$col?>cc 100%)<?=$clink?';text-decoration:none;cursor:pointer':''?>">
        <div class="ic"><?=$c[0]?></div><div class="vl"><?=htmlspecialchars($c[2])?></div><div class="lb"><?=htmlspecialchars($c[1])?></div>
      </<?=$tag?>>
    <?php endforeach; ?>
  </div>

  <?php $cols=['#22c55e,#a3e635','#3b82f6,#22d3ee','#f97316,#fbbf24','#a855f7,#ec4899','#ef4444,#f97316','#14b8a6,#22d3ee']; ?>
  <?php if($R['chart'] && !empty($R['chart'][1])): $mx=max(array_map('floatval',$R['chart'][1]))?:1; $i=0; ?>
  <div class="rep-card"><div class="ttl">📊 <?=htmlspecialchars($R['chart'][0])?></div>
    <?php foreach($R['chart'][1] as $lbl=>$val): $g=$cols[$i++%count($cols)]; ?>
      <div class="rep-bar"><div class="l"><?=htmlspecialchars($lbl)?></div>
        <div class="t"><div class="f" style="width:<?=max(3,round($val/$mx*100))?>%;background:linear-gradient(90deg,<?=$g?>)"></div></div>
        <div class="v"><?=($val>=1000)?number_format($val,0,',','.'):$val?></div></div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <?php if(!empty($R['chart2']) && !empty($R['chart2'][1])): $mx2=max(array_map('floatval',$R['chart2'][1]))?:1; $i2=0; ?>
  <div class="rep-card"><div class="ttl">📊 <?=htmlspecialchars($R['chart2'][0])?></div>
    <?php foreach($R['chart2'][1] as $lbl=>$val): $g=$cols[$i2++%count($cols)]; ?>
      <div class="rep-bar"><div class="l"><?=htmlspecialchars($lbl)?></div>
        <div class="t"><div class="f" style="width:<?=max(3,round($val/$mx2*100))?>%;background:linear-gradient(90deg,<?=$g?>)"></div></div>
        <div class="v"><?=($val>=1000)?number_format($val,0,',','.'):$val?></div></div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <?php if($R['table']['rows']): ?>
  <?php $lim=$full?1000:15; $tot=count($R['table']['rows']); ?>
  <div class="rep-card" style="overflow:auto"><div class="ttl">📋 Detay (<?=$tot?> kayıt)<?=(!$full&&$tot>$lim)?' · <span style="color:#93a4bf;font-weight:400">özet — ilk '.$lim.'</span>':''?></div>
    <?php $hasLinks=!empty($R['table']['links']); ?>
    <table class="rep-tbl"><tr><?php foreach($R['table']['head'] as $h) echo '<th>'.htmlspecialchars($h).'</th>'; if($hasLinks) echo '<th></th>'; ?></tr>
    <?php foreach(array_slice($R['table']['rows'],0,$lim,true) as $i=>$row): ?>
      <tr><?php foreach($row as $cell){ $cc=$crit($cell)?' class="rep-neg"':''; echo '<td'.$cc.'>'.htmlspecialchars((string)$cell).'</td>'; }
      if($hasLinks){ $u=$R['table']['links'][$i] ?? null; echo '<td>'.($u?'<a href="'.htmlspecialchars($u).'" style="color:#60a5fa;font-weight:700;text-decoration:none;white-space:nowrap">Detay →</a>':'').'</td>'; } ?></tr>
    <?php endforeach; ?>
    </table>
    <?php if(!$full && $tot>$lim): ?><small style="color:#93a4bf">… ve <?=$tot-$lim?> kayıt daha — "Detaylı" ile görünür</small><?php endif; ?>
  </div>
  <?php endif; ?>
  <div class="rep-foot"><?=htmlspecialchars($appName)?> — Online Takip Sistemi · Bu rapor otomatik üretilmiştir</div>
</div>
  <?php return ob_get_clean();
}
