<?php
// migrations/030_add_handover_details.php
// Description: Add detailed user information to bot_conversation_states for better human intervention tracking

require_once __DIR__ . '/../includes/db_config.php';

try {
    $stmt = $pdo->query("SHOW COLUMNS FROM bot_conversation_states");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (!in_array('user_name', $columns)) {
        $pdo->exec("ALTER TABLE bot_conversation_states ADD COLUMN user_name VARCHAR(255) NULL AFTER user_id");
        echo "Added 'user_name' to bot_conversation_states.<br>";
    }

    if (!in_array('last_user_message', $columns)) {
        $pdo->exec("ALTER TABLE bot_conversation_states ADD COLUMN last_user_message TEXT NULL AFTER user_name");
        echo "Added 'last_user_message' to bot_conversation_states.<br>";
    }

    echo "Migration 030 (Handover Details) completed successfully.<br>";

} catch (PDOException $e) {
    echo "Error in migration 030: " . $e->getMessage() . "<br>";
}
