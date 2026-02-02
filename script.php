<?php
require 'includes/db_config.php';
$stmt = $pdo->query('SHOW COLUMNS FROM users');
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
?>