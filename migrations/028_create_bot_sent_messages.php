<?php
// migrations/028_create_bot_sent_messages.php
// Description: Create table to track bot-sent messages to distinguish from human admin

require_once __DIR__ . '/../includes/db_config.php';

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS bot_sent_messages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        message_id VARCHAR(255) UNIQUE NOT NULL,
        page_id VARCHAR(100) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX (message_id),
        INDEX (page_id)
    )");
    echo "Table 'bot_sent_messages' checked/created successfully.<br>";
} catch (PDOException $e) {
    echo "Error in migration 028: " . $e->getMessage() . "<br>";
}
