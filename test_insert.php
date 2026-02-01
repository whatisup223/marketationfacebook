<?php
require_once 'includes/db_config.php';
try {
    $stmt = $pdo->prepare("INSERT INTO auto_reply_rules (page_id, trigger_type, reply_message, reply_buttons) VALUES (0, 'test', 'test', '[]')");
    $stmt->execute();
    $id = $pdo->lastInsertId();
    $pdo->exec("DELETE FROM auto_reply_rules WHERE id = $id");
    echo "Insert Successful. Column is usable in INSERT.";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
