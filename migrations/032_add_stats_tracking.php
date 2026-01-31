<?php
// migrations/032_add_stats_tracking.php
// Description: Add tracking columns for performance metrics

require_once __DIR__ . '/../includes/db_config.php';

try {
    $stmt = $pdo->query("SHOW COLUMNS FROM bot_conversation_states");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (!in_array('last_user_message_at', $columns)) {
        $pdo->exec("ALTER TABLE bot_conversation_states ADD COLUMN last_user_message_at TIMESTAMP NULL AFTER last_user_message");
        echo "Added 'last_user_message_at' to bot_conversation_states.<br>";
    }

    echo "Migration 032 (Stats Tracking) completed successfully.<br>";

} catch (PDOException $e) {
    echo "Error in migration 032: " . $e->getMessage() . "<br>";
}
