<?php
require_once 'includes/db_config.php';
try {
    $pdo->exec("ALTER TABLE auto_reply_rules ADD COLUMN reply_buttons TEXT DEFAULT NULL");
    echo "Column added successfully";
} catch (PDOException $e) {
    echo "Error (might already exist): " . $e->getMessage();
}
?>