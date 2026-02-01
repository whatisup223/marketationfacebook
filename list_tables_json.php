<?php
require_once 'includes/db_config.php';
$stmt = $pdo->query("SHOW TABLES");
echo json_encode($stmt->fetchAll(PDO::FETCH_COLUMN));
