<?php
require_once __DIR__ . '/includes/db_config.php';
$stmt = $pdo->query("DESCRIBE fb_pages");
$cols = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($cols as $col) {
    echo $col['Field'] . "\n";
}
