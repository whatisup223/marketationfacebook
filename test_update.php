<?php
require_once 'includes/db_config.php';
try {
    $stmt = $pdo->prepare("UPDATE auto_reply_rules SET reply_buttons = '[]' WHERE id > 0 LIMIT 1");
    $stmt->execute();
    echo "Update Successful. Column is writable.";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
