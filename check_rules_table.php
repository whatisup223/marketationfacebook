<?php
require_once 'includes/db_config.php';
$s = $pdo->prepare('DESCRIBE auto_reply_rules');
$s->execute();
print_r($s->fetchAll(PDO::FETCH_ASSOC));
