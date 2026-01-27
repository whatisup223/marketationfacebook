<?php
require_once __DIR__ . '/includes/db_config.php';
$tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
echo "REMAINING TABLES:\n";
foreach ($tables as $t) {
    echo "- $t\n";
}
?>