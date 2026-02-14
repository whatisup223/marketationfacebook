<?php
require 'includes/db_config.php';
$stmt = $pdo->query('DESCRIBE auto_reply_rules');
file_put_contents('rules_schema.txt', print_r($stmt->fetchAll(PDO::FETCH_ASSOC), true));
