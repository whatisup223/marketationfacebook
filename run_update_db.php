<?php
require_once __DIR__ . '/includes/db_config.php';

echo "Updating database schema for Auto Reply improvements...\n";

try {
    $pdo = getDB();

    // 1. Add usage_count column
    try {
        $pdo->exec("ALTER TABLE auto_reply_rules ADD COLUMN usage_count INT DEFAULT 0");
        echo "✅ Added 'usage_count' column.\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column') !== false) {
            echo "ℹ️ 'usage_count' column already exists.\n";
        } else {
            echo "❌ Failed to add 'usage_count': " . $e->getMessage() . "\n";
        }
    }

    // 2. Add is_active column
    try {
        $pdo->exec("ALTER TABLE auto_reply_rules ADD COLUMN is_active TINYINT(1) DEFAULT 1");
        echo "✅ Added 'is_active' column.\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column') !== false) {
            echo "ℹ️ 'is_active' column already exists.\n";
        } else {
            echo "❌ Failed to add 'is_active': " . $e->getMessage() . "\n";
        }
    }

    echo "\nDatabase update completed successfully! 🚀\n";

} catch (Exception $e) {
    echo "❌ Critical Error: " . $e->getMessage() . "\n";
}
?>