<?php
require_once 'includes/db_config.php';
try {
    $stmt = $pdo->query("SHOW PROCESSLIST");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        print_r($row);
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
