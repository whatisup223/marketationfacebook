<?php
// Migration: 024_add_messenger_bot_support.php
// Description: Add reply_source column to auto_reply_rules to support Messenger Bot

$pdo = getDB();

try {
    // Check current columns
    $stmt = $pdo->query("SHOW COLUMNS FROM auto_reply_rules");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // Add 'reply_source' if missing
    if (!in_array('reply_source', $columns)) {
        $pdo->exec("ALTER TABLE auto_reply_rules ADD COLUMN reply_source VARCHAR(50) DEFAULT 'comment' AFTER hide_comment");
        echo "Added 'reply_source' column to auto_reply_rules table.<br>";
    }

    echo "Migration 024 (Messenger Bot Support) completed successfully.<br>";

} catch (PDOException $e) {
    echo "Error in migration 024: " . $e->getMessage() . "<br>";
}
?>