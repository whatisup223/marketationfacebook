<?php
require_once 'includes/db_config.php';
try {
    $stmt = $pdo->query("DESCRIBE auto_reply_rules");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "Column: [" . $row['Field'] . "] Length: " . strlen($row['Field']) . "\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
