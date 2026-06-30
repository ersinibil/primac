<?php require_once __DIR__.'/layout_top.php'; ?>

<div class="panel-head">
<h1>Personel</h1>
<a class="btn" href="personnel_new.php">+ Yeni Personel</a>
</div>

<section class="panel">
<table>
<thead>
<tr>
<th>Personel</th>
<th>Rol</th>
<th>Telefon</th>
<th>Çalışma</th>
<th>Saatlik</th>
<th>Günlük</th>
<th>Durum</th>
<th>İşlem</th>
</tr>
</thead>
<tbody>
<?php
try{
$rows=db()->query("SELECT * FROM personnel ORDER BY active DESC, name ASC")->fetchAll();
foreach($rows as $r){
    $openTasks=0;
    try{
        $s=db()->prepare("SELECT COUNT(*) c FROM tasks WHERE personnel_id=? AND status!='Tamamlandı'");
        $s->execute([$r['id']]);
        $openTasks=(int)($s->fetch()['c'] ?? 0);
    }catch(Throwable $e){}
    echo "<tr>
    <td><b>".h($r['name'])."</b><br><span class='muted'>Açık görev: ".$openTasks."</span></td>
    <td>".h($r['role'])."</td>
    <td>".h($r['phone'])."</td>
    <td>".h($r['work_type'] ?? '-')."</td>
    <td>".money($r['hourly_rate'])."</td>
    <td>".money($r['daily_wage'])."</td>
    <td>".($r['active']?badge('Aktif','green'):badge('Pasif','red'))."</td>
    <td><a class='btn small secondary' href='personnel_edit.php?id=".h($r['id'])."'>Profili Aç</a></td>
    </tr>";
}
if(!$rows) echo "<tr><td colspan='8' class='muted'>Henüz personel yok.</td></tr>";
}catch(Throwable $e){
echo "<tr><td colspan='8'><div class='alert'>".h($e->getMessage())."</div></td></tr>";
}
?>
</tbody>
</table>
</section>

<?php require_once __DIR__.'/layout_bottom.php'; ?>
