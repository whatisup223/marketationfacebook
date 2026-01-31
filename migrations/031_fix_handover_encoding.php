<?php
// migrations/031_fix_handover_encoding.php
// Description: Fix character encoding for bot_conversation_states table to support Arabic characters

require_once __DIR__ . '/../includes/db_config.php';

try {
    // 1. Convert the entire table to utf8mb4
    $pdo->exec("ALTER TABLE bot_conversation_states CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "Converted 'bot_conversation_states' table to utf8mb4.<br>";

    // 2. Ensure specific columns are definitely utf8mb4
    $pdo->exec("ALTER TABLE bot_conversation_states MODIFY user_name VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL");
    $pdo->exec("ALTER TABLE bot_conversation_states MODIFY last_user_message TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL");
    $pdo->exec("ALTER TABLE bot_conversation_states MODIFY last_bot_reply_text TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL");

    echo "Updated specific columns collation in 'bot_conversation_states'.<br>";

    echo "Migration 031 (Fix Handover Encoding) completed successfully.<br>";

} catch (PDOException $e) {
    echo "Error in migration 031: " . $e->getMessage() . "<br>";
}
