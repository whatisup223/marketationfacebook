<?php
// migrations/034_separate_handover_source.php
// Description: Add reply_source to bot_conversation_states to separate Message vs Comment handovers

require_once __DIR__ . '/../includes/db_config.php';

try {
    // 1. Check if column exists
    $stmt = $pdo->query("SHOW COLUMNS FROM bot_conversation_states");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (!in_array('reply_source', $columns)) {
        // Add column with a default. 'comment' is a safe guess or 'message'. 
        // Given the ambiguity, we default to 'message' as that's where handovers are most critical, 
        // but let's stick to 'message' as default for existing rows if any.
        // Actually, let's NOT set a default for future inserts, but just updates.
        $pdo->exec("ALTER TABLE bot_conversation_states ADD COLUMN reply_source ENUM('comment', 'message') NOT NULL DEFAULT 'message' AFTER user_id");
        echo "Added 'reply_source' to bot_conversation_states.<br>";
    }

    // 2. Update Constraints (UNIQUE KEY)
    // We need to drop the old unique key and add a new one including reply_source
    // Check if index exists first to avoid errors
    $stmt = $pdo->prepare("SHOW INDEX FROM bot_conversation_states WHERE Key_name = 'page_user'");
    $stmt->execute();
    if ($stmt->fetch()) {
        $pdo->exec("ALTER TABLE bot_conversation_states DROP INDEX page_user");
        echo "Dropped old unique index 'page_user'.<br>";
    }

    // Add new unique index
    // Check if new index exists
    $stmt = $pdo->prepare("SHOW INDEX FROM bot_conversation_states WHERE Key_name = 'page_user_source'");
    $stmt->execute();
    if (!$stmt->fetch()) {
        $pdo->exec("ALTER TABLE bot_conversation_states ADD UNIQUE KEY page_user_source (page_id, user_id, reply_source)");
        echo "Added new unique index 'page_user_source'.<br>";
    }

    echo "Migration 034 (Separate Handover Source) completed successfully.<br>";

} catch (PDOException $e) {
    echo "Error in migration 034: " . $e->getMessage() . "<br>";
}
