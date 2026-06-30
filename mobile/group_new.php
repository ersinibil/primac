<?php
require_once 'common.php';
$pdo=db(); $me=(int)($_SESSION['user']['id']??0); $ok=''; $er='';

if($_SERVER['REQUEST_METHOD']==='POST'){
    try{
        $title=trim($_POST['title']??'');
        $members=array_map('intval',(array)($_POST['members']??[]));
        if($title==='') throw new Exception('Grup adı girin.');
        $members=array_values(array_unique(array_filter($members)));
        if(count($members)<1) throw new Exception('En az bir üye seçin.');
        $pdo->prepare("INSERT INTO chat_threads(type,title,created_by) VALUES('group',?,?)")->execute([$title,$me]);
        $tid=(int)$pdo->lastInsertId();
        // Üyeler + oluşturan
        $ins=$pdo->prepare("INSERT IGNORE INTO chat_thread_members(thread_id,user_id) VALUES(?,?)");
        $ins->execute([$tid,$me]);
        foreach($members as $uid){ if($uid!==$me) $ins->execute([$tid,$uid]); }
        // Üyelere bildirim/push
        $sname=$_SESSION['user']['name']??$_SESSION['user']['username']??'Kullanıcı';
        if(file_exists(__DIR__.'/../push_lib.php')){ require_once __DIR__.'/../push_lib.php';
            foreach($members as $uid){ if($uid!==$me) try{ push_to_user($uid,'👥 '.$title,$sname.' seni gruba ekledi','messages.php?thread='.$tid); }catch(Throwable $e){} }
        }
        header('Location: messages.php?thread='.$tid); exit;
    }catch(Throwable $e){ $er=$e->getMessage(); }
}

topx('Yeni Grup');
$users=$pdo->query("SELECT id,full_name,username FROM app_users WHERE id<>$me AND active=1 ORDER BY full_name,username")->fetchAll();
?>
<?php if($er): ?><div class="err"><?=htmlspecialchars($er)?></div><?php endif; ?>
<div class="panel">
<form method="post">
  <label>Grup Adı</label><input name="title" required placeholder="örn. Üretim Ekibi">
  <label>Üyeler</label>
  <div style="display:flex;flex-direction:column;gap:6px;margin-top:6px">
  <?php foreach($users as $u): $nm=$u['full_name']?:$u['username']; ?>
    <label style="display:flex;align-items:center;gap:10px;background:rgba(255,255,255,.06);border-radius:12px;padding:10px;margin:0">
      <input type="checkbox" name="members[]" value="<?=$u['id']?>" style="width:auto;margin:0">
      <span><?=htmlspecialchars($nm)?></span>
    </label>
  <?php endforeach; ?>
  </div>
  <button class="btn dark" style="width:100%;padding:14px;margin-top:12px">👥 Grubu Oluştur</button>
</form>
</div>
<?php botx(); ?>
