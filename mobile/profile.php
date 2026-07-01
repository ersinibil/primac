<?php
require_once 'common.php';
$pdo=db(); $me=(int)($_SESSION['user']['id'] ?? 0);
$ok=''; $er='';
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['change_pass'])){
    try{
        $new=$_POST['new_pass'] ?? '';
        if(strlen($new)<4) throw new Exception('Şifre en az 4 karakter olmalı.');
        $pdo->prepare("UPDATE app_users SET password_hash=? WHERE id=?")->execute([password_hash($new,PASSWORD_DEFAULT),$me]);
        $ok='Şifre güncellendi.';
    }catch(Throwable $e){ $er=$e->getMessage(); }
}
topx('Profilim');
$u=$pdo->prepare("SELECT * FROM app_users WHERE id=?"); $u->execute([$me]); $user=$u->fetch();
$ad=$user['full_name'] ?? ($_SESSION['user']['name'] ?? 'Kullanıcı');
// Kişisel istatistik (personel ise)
$pid=(int)($_SESSION['user']['personnel_id'] ?? 0);
$st=['is'=>0,'gorev'=>0];
if($pid){
  $st['is']=safe_count("SELECT COUNT(*) c FROM jobs WHERE responsible_personnel_id=$pid AND status NOT IN ('Tamamlandı','İptal','Teslim Edildi')");
  $st['gorev']=safe_count("SELECT COUNT(*) c FROM tasks WHERE personnel_id=$pid AND status NOT IN ('Tamamlandı','İptal')");
}
?>
<?php if($ok): ?><div class="notice"><?=htmlspecialchars($ok)?></div><?php endif; ?>
<?php if($er): ?><div class="err"><?=htmlspecialchars($er)?></div><?php endif; ?>
<div class="panel" style="text-align:center">
  <div style="width:72px;height:72px;border-radius:50%;background:#3b82f6;display:flex;align-items:center;justify-content:center;font-weight:900;font-size:30px;margin:0 auto 8px"><?=htmlspecialchars(mb_strtoupper(mb_substr($ad,0,1)))?></div>
  <h2 style="margin:0"><?=htmlspecialchars($ad)?></h2>
  <small class="muted"><?=htmlspecialchars($user['username'] ?? '')?> · <?=$isAdmin?'Yönetici':'Personel'?></small>
</div>

<?php if($pid): ?>
<div class="grid">
  <div class="card blue"><span>📋</span><b><?=$st['is']?></b><small>Açık iş</small></div>
  <div class="card teal"><span>✅</span><b><?=$st['gorev']?></b><small>Açık görev</small></div>
</div>
<?php endif; ?>

<div class="panel">
  <b>🔑 Şifre Değiştir</b>
  <form method="post" style="margin-top:8px">
    <input type="password" name="new_pass" placeholder="Yeni şifre" required>
    <button class="btn dark" name="change_pass" value="1" style="width:100%;padding:13px">Güncelle</button>
  </form>
</div>

<?php if($isAdmin): ?>
<a class="btn" style="width:100%;background:#334155;color:#fff;margin-bottom:10px" href="../dashboard.php">🖥 Masaüstü Sürüme Geç</a>
<?php endif; ?>
<a class="btn" style="width:100%;background:#7f1d1d;color:#fff" href="../logout.php">🚪 Çıkış Yap</a>

<?php botx(); ?>
