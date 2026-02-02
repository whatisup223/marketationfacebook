<?php
require_once __DIR__ . '/includes/db_config.php';
$stmt = $pdo->query('DESCRIBE fb_leads');
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo $row['Field'] . ' | ' . $row['Type'] . "\n";
}
