<?php
require_once 'includes/db_config.php';
try {
    $stmt = $pdo->query("DESCRIBE auto_reply_rules");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "Columns: " . implode(", ", $columns);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>