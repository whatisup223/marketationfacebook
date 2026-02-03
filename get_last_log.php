<?php
require 'includes/db_config.php';
$stmt = $pdo->query("SELECT * FROM wa_notification_logs ORDER BY id DESC LIMIT 1");
$log = $stmt->fetch(PDO::FETCH_ASSOC);
echo "LAST LOG:\n";
print_r($log);
