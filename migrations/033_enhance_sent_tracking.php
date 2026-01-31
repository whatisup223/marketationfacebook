<?php
// migrations/033_enhance_sent_tracking.php
// Description: Add rule_id, source, and user_id tracking to bot_sent_messages for advanced analytics

require_once __DIR__ . '/../includes/db_config.php';

try {
    $stmt = $pdo->query("SHOW COLUMNS FROM bot_sent_messages");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (!in_array('rule_id', $columns)) {
        $pdo->exec("ALTER TABLE bot_sent_messages ADD COLUMN rule_id INT NULL AFTER page_id");
        echo "Added 'rule_id' to bot_sent_messages.<br>";
    }

    if (!in_array('reply_source', $columns)) {
        $pdo->exec("ALTER TABLE bot_sent_messages ADD COLUMN reply_source ENUM('comment', 'message') DEFAULT 'comment' AFTER rule_id");
        echo "Added 'reply_source' to bot_sent_messages.<br>";
    }

    if (!in_array('user_id', $columns)) {
        $pdo->exec("ALTER TABLE bot_sent_messages ADD COLUMN user_id VARCHAR(100) NULL AFTER reply_source");
        echo "Added 'user_id' to bot_sent_messages.<br>";
    }

    echo "Migration 033 (Enhanced Sent Tracking) completed successfully.<br>";

} catch (PDOException $e) {
    echo "Error in migration 033: " . $e->getMessage() . "<br>";
}
