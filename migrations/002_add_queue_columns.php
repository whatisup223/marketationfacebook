<?php
// migrations/002_add_queue_columns.php
// Migration: Add reserved_by and reserved_at columns to campaign_queue

require_once __DIR__ . '/../includes/db_config.php';

echo "Running migration: Add queue locking columns...\n";

try {
    // Add reserved_by column
    try {
        $pdo->exec("ALTER TABLE campaign_queue ADD COLUMN reserved_by VARCHAR(50) DEFAULT NULL");
        echo "✓ Added reserved_by column\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column') !== false) {
            echo "ℹ️ reserved_by column already exists\n";
        } else {
            throw $e;
        }
    }

    // Add reserved_at column
    try {
        $pdo->exec("ALTER TABLE campaign_queue ADD COLUMN reserved_at DATETIME DEFAULT NULL");
        echo "✓ Added reserved_at column\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column') !== false) {
            echo "ℹ️ reserved_at column already exists\n";
        } else {
            throw $e;
        }
    }

    echo "✅ Migration completed successfully!\n";

} catch (Exception $e) {
    echo "❌ Migration failed: " . $e->getMessage() . "\n";
    throw $e;
}
