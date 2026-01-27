<?php
require_once 'includes/functions.php';
$pdo = getDB();
$s = $pdo->query("SHOW CREATE TABLE campaign_queue");
$r = $s->fetch(PDO::FETCH_ASSOC);
echo $r['Create Table'];
?>