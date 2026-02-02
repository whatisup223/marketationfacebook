<?php
require_once 'includes/db_config.php';
$stmt = $pdo->query("DESCRIBE fb_leads");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $r) {
    echo $r['Field'] . ":" . $r['Type'] . "\n";
}
echo "---SEPARATOR---\n";
$stmt = $pdo->query("DESCRIBE fb_pages");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $r) {
    echo $r['Field'] . ":" . $r['Type'] . "\n";
}
