<?php
require_once 'includes/db_config.php';
try {
    $stmt = $pdo->query("SHOW DATABASES");
    $dbs = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "Databases: " . implode(", ", $dbs);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
