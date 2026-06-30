<?php
// İşe/cariye bağlı sohbeti aç (yoksa oluştur), messages.php?thread=X'e yönlendir.
require_once 'common.php';
$pdo=db(); $me=(int)($_SESSION['user']['id']??0);
$type=$_GET['type']??''; $ref=(int)($_GET['ref']??0);
if(!in_array($type,['job','cari'],true) || !$ref){ header('Location: messages.php'); exit; }

try{
    // Var olan thread?
    $f=$pdo->prepare("SELECT id FROM chat_threads WHERE type=? AND ref_id=? LIMIT 1");
    $f->execute([$type,$ref]); $tid=(int)($f->fetch()['id']??0);

    if(!$tid){
        // Başlık + ilk üyeler
        $members=[$me];
        if($type==='job'){
            $j=$pdo->prepare("SELECT title,job_no,responsible_personnel_id FROM jobs WHERE id=?"); $j->execute([$ref]); $jr=$j->fetch();
            $title='İş: '.($jr['title']?:('#'.$ref));
            if(!empty($jr['responsible_personnel_id'])){
                $uu=$pdo->prepare("SELECT id FROM app_users WHERE personnel_id=? AND active=1 LIMIT 1"); $uu->execute([$jr['responsible_personnel_id']]);
                $ru=$uu->fetch(); if($ru) $members[]=(int)$ru['id'];
            }
        } else { // cari
            $c=$pdo->prepare("SELECT name FROM contacts WHERE id=?"); $c->execute([$ref]); $cr=$c->fetch();
            $title='Cari: '.($cr['name']?:('#'.$ref));
            // cari temsilcisi (varsa) üyeliğe
            try{ $rp=$pdo->prepare("SELECT user_id FROM contact_representatives WHERE contact_id=?"); $rp->execute([$ref]);
                 foreach($rp->fetchAll() as $r){ if(!empty($r['user_id'])) $members[]=(int)$r['user_id']; } }catch(Throwable $e){}
        }
        $pdo->prepare("INSERT INTO chat_threads(type,title,ref_id,created_by) VALUES(?,?,?,?)")->execute([$type,$title,$ref,$me]);
        $tid=(int)$pdo->lastInsertId();
        $ins=$pdo->prepare("INSERT IGNORE INTO chat_thread_members(thread_id,user_id) VALUES(?,?)");
        foreach(array_unique($members) as $uid){ if($uid) $ins->execute([$tid,$uid]); }
    } else {
        // Üye değilsem ekle (sohbeti açan katılır)
        $pdo->prepare("INSERT IGNORE INTO chat_thread_members(thread_id,user_id) VALUES(?,?)")->execute([$tid,$me]);
    }
    header('Location: messages.php?thread='.$tid); exit;
}catch(Throwable $e){
    header('Location: messages.php'); exit;
}
