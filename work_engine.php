<?php
require_once __DIR__.'/boot.php';

function work_engine_add_event($jobId, $title, $description='', $type='İş'){
    try{
        db()->prepare("INSERT INTO work_events(job_id,event_type,title,description,created_by) VALUES(?,?,?,?,?)")
            ->execute([$jobId,$type,$title,$description,$_SESSION['user']['id'] ?? null]);
    }catch(Throwable $e){}
}

function work_engine_progress($jobId){
    try{
        $pdo=db();
        $s=$pdo->prepare("SELECT COUNT(*) total, SUM(CASE WHEN status='Tamamlandı' THEN 1 ELSE 0 END) done FROM work_checklists WHERE job_id=?");
        $s->execute([$jobId]);
        $r=$s->fetch();
        $total=(int)($r['total'] ?? 0);
        $done=(int)($r['done'] ?? 0);
        if($total<=0) return 0;
        return (int)round(($done/$total)*100);
    }catch(Throwable $e){
        return 0;
    }
}

function work_engine_seed_checklist($jobId, $jobType){
    try{
        $pdo=db();
        $s=$pdo->prepare("SELECT COUNT(*) c FROM work_checklists WHERE job_id=?");
        $s->execute([$jobId]);
        if((int)($s->fetch()['c'] ?? 0)>0) return;

        $templates=[
            '3d_imalat'=>['Planlama','Malzeme Kontrol','3D Baskı','Kalite Kontrol','Paketleme','Stok / Teslim'],
            'uv_baski'=>['Grafik Onayı','Malzeme Hazırlık','UV Baskı','Kalite Kontrol','Teslim'],
            'lazer'=>['Dosya Kontrol','Malzeme Hazırlık','Lazer Kesim/Kazıma','Temizlik','Teslim'],
            'grafik_tasarim'=>['Brief','Tasarım','Revize','Müşteri Onayı','Baskıya Hazır'],
            'dis_atolye'=>['Tedarikçiye Verildi','Dış Üretim','Teslim Alındı','Kalite Kontrol','Müşteriye Teslim'],
            'tedarikcide_uretim'=>['Sipariş','Tedarikçide Üretim','Sevkiyat','Teslim Alındı','Muhasebe'],
            'montaj'=>['Randevu','Malzeme Hazırlık','Montaj','Fotoğraf/Kontrol','Teslim'],
            'satin_alma'=>['Talep','Onay','Sipariş','Teslim Alındı','Ödeme/Muhasebe'],
            'karma'=>['Teklif','Onay','Grafik/Tasarım','Satın Alma','Üretim/Dış Tedarik','Montaj/Teslim','Tahsilat']
        ];

        $items=$templates[$jobType] ?? $templates['karma'];
        $ins=$pdo->prepare("INSERT INTO work_checklists(job_id,title,sort_order,status) VALUES(?,?,?,'Bekliyor')");
        $i=1;
        foreach($items as $item){
            $ins->execute([$jobId,$item,$i++]);
        }
        work_engine_add_event($jobId,'İş akışı oluşturuldu',implode(', ',$items),'Sistem');
    }catch(Throwable $e){}
}

function work_engine_status_tone($status){
    if(in_array($status,['Tamamlandı','Teslim Edildi'])) return 'green';
    if(in_array($status,['Devam Ediyor','Üretimde','Montajda'])) return 'blue';
    if(in_array($status,['Gecikti','Acil','Çok Acil'])) return 'red';
    if(in_array($status,['Bekliyor','Onay Bekliyor'])) return 'yellow';
    return 'gray';
}
?>