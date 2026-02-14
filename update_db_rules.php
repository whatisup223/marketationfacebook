<?php
require_once 'includes/db_config.php';

try {
    $pdo->exec("ALTER TABLE auto_reply_rules 
                ADD COLUMN platform ENUM('facebook', 'instagram') DEFAULT 'facebook' AFTER page_id");
    echo "auto_reply_rules updated successfully.\n";
} catch (PDOException $e) {
    echo "Error updating auto_reply_rules: " . $e->getMessage() . "\n";
}
