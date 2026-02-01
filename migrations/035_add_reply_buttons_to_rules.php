<?php
// migrations/035_add_reply_buttons_to_rules.php
// Description: Add reply_buttons column to auto_reply_rules table for button support

require_once __DIR__ . '/../includes/db_config.php';

try {
    $stmt = $pdo->query("SHOW COLUMNS FROM auto_reply_rules");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (!in_array('reply_buttons', $columns)) {
        $pdo->exec("ALTER TABLE auto_reply_rules ADD COLUMN reply_buttons TEXT DEFAULT NULL AFTER reply_message");
        echo "Added 'reply_buttons' column to auto_reply_rules table.<br>";
    } else {
        echo "Column 'reply_buttons' already exists in auto_reply_rules.<br>";
    }

    echo "Migration 035 (Add Reply Buttons) completed successfully.<br>";

} catch (PDOException $e) {
    echo "Error in migration 035: " . $e->getMessage() . "<br>";
}
