<?php
require_once 'includes/db_config.php';
$stmt = $pdo->query("SHOW TABLES");
print_r($stmt->fetchAll(PDO::FETCH_COLUMN));
