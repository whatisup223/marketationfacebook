<?php
// Quick Fix: Ensure bot_conversation_states table exists with platform column
require_once __DIR__ . '/includes/db_config.php';

try {
    $pdo = getDB();

    // Create table if not exists
    $pdo->exec("CREATE TABLE IF NOT EXISTS `bot_conversation_states` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `page_id` VARCHAR(100) NOT NULL,
        `user_id` VARCHAR(100) NOT NULL,
        `user_name` VARCHAR(255) DEFAULT NULL,
        `reply_source` ENUM('comment', 'message') NOT NULL DEFAULT 'message',
        `platform` ENUM('facebook', 'instagram') DEFAULT 'facebook',
        `conversation_state` ENUM('active', 'handover', 'resolved') DEFAULT 'active',
        `last_user_message` TEXT DEFAULT NULL,
        `last_bot_reply_text` TEXT DEFAULT NULL,
        `repeat_count` INT DEFAULT 0,
        `is_anger_detected` TINYINT(1) DEFAULT 0,
        `last_user_message_at` DATETIME DEFAULT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY `page_user_source_plt` (`page_id`, `user_id`, `reply_source`, `platform`),
        INDEX (`conversation_state`),
        INDEX (`is_anger_detected`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    echo "✅ Table 'bot_conversation_states' created/verified successfully!\n";

    // Verify platform column exists
    $check = $pdo->query("SHOW COLUMNS FROM bot_conversation_states LIKE 'platform'");
    if ($check->fetch()) {
        echo "✅ Column 'platform' exists!\n";
    } else {
        echo "❌ Column 'platform' missing - adding now...\n";
        $pdo->exec("ALTER TABLE bot_conversation_states ADD COLUMN platform ENUM('facebook', 'instagram') DEFAULT 'facebook' AFTER reply_source");
        $pdo->exec("ALTER TABLE bot_conversation_states DROP INDEX page_user_source");
        $pdo->exec("ALTER TABLE bot_conversation_states ADD UNIQUE KEY `page_user_source_plt` (page_id, user_id, reply_source, platform)");
        echo "✅ Column 'platform' added successfully!\n";
    }

    echo "\n🎉 Handover system is now ready!\n";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
