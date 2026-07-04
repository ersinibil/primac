<?php
require_once __DIR__.'/layout_top.php';

// Personel isminden avatar baş harfleri (fotoğraf/photo kolonu şemada yok — bkz. ROADMAP.md,
// bu yüzden placeholder olarak baş harf rozeti kullanılıyor, hayali bir kolon icat edilmedi).
function personnel_initials($name){
    $name=trim((string)$name);
    if($name==='') return '?';
    $parts=preg_split('/\s+/',$name);
    $ini='';
    foreach(array_slice($parts,0,2) as $p){ $ini.=mb_substr($p,0,1,'UTF-8'); }
    return mb_strtoupper($ini,'UTF-8') ?: '?';
}
?>

<div class="panel-head">
<h1>Personel</h1>
<a class="btn" href="personnel_new.php">+ Yeni Personel</a>
</div>

<div class="pcard-grid">
<?php
try{
    // app_users LEFT JOIN — kart üzerindeki "Mesaj Gönder" butonu için bağlı kullanıcı hesabı var mı bakılıyor.
    $rows=db()->query("SELECT p.*,
                        (SELECT u.id FROM app_users u WHERE u.personnel_id=p.id ORDER BY u.id LIMIT 1) AS linked_user_id
                        FROM personnel p
                        ORDER BY p.active DESC, p.name ASC")->fetchAll();
    foreach($rows as $r){
        $pid=(int)$r['id'];
        $openTasks=0;
        try{
            $s=db()->prepare("SELECT COUNT(*) c FROM tasks WHERE personnel_id=? AND status!='Tamamlandı'");
            $s->execute([$pid]);
            $openTasks=(int)($s->fetch()['c'] ?? 0);
        }catch(Throwable $e){}
        $todayTasks=0;
        try{
            $s=db()->prepare("SELECT COUNT(*) c FROM tasks WHERE personnel_id=? AND due_date=CURDATE()");
            $s->execute([$pid]);
            $todayTasks=(int)($s->fetch()['c'] ?? 0);
        }catch(Throwable $e){}
        ?>
        <div class="pcard">
            <div class="pcard-head">
                <div class="pcard-avatar"><?=h(personnel_initials($r['name']))?></div>
                <div class="pcard-id">
                    <strong><?=h($r['name'])?></strong>
                    <span class="muted"><?=h($r['role'] ?: 'Personel')?></span>
                </div>
                <?=$r['active']?badge('Aktif','green'):badge('Pasif','red')?>
            </div>
            <div class="pcard-info">
                <div>📞 <?=h($r['phone'] ?: '-')?></div>
                <div>✉️ <?=h($r['email'] ?? '' ?: '-')?></div>
            </div>
            <div class="pcard-stats">
                <div><strong><?=$todayTasks?></strong><small>Bugünkü Görev</small></div>
                <div><strong><?=$openTasks?></strong><small>Açık Görev</small></div>
            </div>
            <div class="pcard-actions">
                <a class="btn small" href="personnel_edit.php?id=<?=$pid?>">Detay</a>
                <a class="btn small secondary" href="personnel_edit.php?id=<?=$pid?>&tab=gorevler">Görevler</a>
                <?php if(!empty($r['linked_user_id'])): ?>
                <a class="btn small secondary" href="messages.php?u=<?=(int)$r['linked_user_id']?>">Mesaj Gönder</a>
                <?php endif; ?>
                <a class="btn small secondary" href="kpi.php">Performans</a>
            </div>
        </div>
        <?php
    }
    if(!$rows) echo '<div class="muted" style="padding:20px">Henüz personel yok.</div>';
}catch(Throwable $e){
    echo '<div class="alert">'.h($e->getMessage()).'</div>';
}
?>
</div>

<style>
.pcard-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:16px;margin:14px 0 20px}
.pcard{background:#fff;border-radius:20px;box-shadow:0 8px 28px rgba(16,24,40,.06);padding:18px;display:flex;flex-direction:column;gap:12px}
.pcard-head{display:flex;align-items:center;gap:12px}
.pcard-avatar{width:46px;height:46px;border-radius:14px;background:#111827;color:#fff;display:flex;align-items:center;justify-content:center;font-weight:900;font-size:16px;flex:0 0 auto}
.pcard-id{display:flex;flex-direction:column;flex:1;min-width:0}
.pcard-id strong{font-size:15px;color:#101828}
.pcard-info{display:flex;flex-direction:column;gap:4px;font-size:13px;color:#475467}
.pcard-stats{display:flex;gap:10px}
.pcard-stats>div{flex:1;background:#f8fafc;border-radius:14px;padding:8px 10px;text-align:center}
.pcard-stats strong{display:block;font-size:18px;color:#101828}
.pcard-stats small{color:#667085;font-size:11px}
.pcard-actions{display:flex;flex-wrap:wrap;gap:6px}
</style>

<?php require_once __DIR__.'/layout_bottom.php'; ?>
