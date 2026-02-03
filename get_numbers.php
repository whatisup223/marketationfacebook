<?php
require 'includes/db_config.php';
$stmt = $pdo->prepare('SELECT wa_notification_numbers FROM users WHERE id = 1');
$stmt->execute();
echo "NUMBERS: " . $stmt->fetchColumn() . "\n";
