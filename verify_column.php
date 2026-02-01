<?php
require_once 'includes/db_config.php';
try {
    $stmt = $pdo->query("SELECT reply_buttons FROM auto_reply_rules LIMIT 1");
    echo "Query Successful. Column exists and is accessible.";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
