<?php
require_once 'includes/db_config.php';
$stmt = $pdo->query("SELECT id, fb_name, expires_at FROM fb_accounts");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
