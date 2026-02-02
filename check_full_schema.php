<?php
require_once 'includes/db_config.php';
$tables = ['fb_leads', 'fb_pages', 'fb_accounts'];
foreach ($tables as $t) {
    echo "--- Table: $t ---\n";
    $stmt = $pdo->query("DESCRIBE $t");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo $row['Field'] . " | " . $row['Type'] . "\n";
    }
}
