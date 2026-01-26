<?php
// migrations/001_add_batch_columns.php
// Migration: Add batch_size and last_activity columns

require_once __DIR__ . '/../includes/db_config.php';

echo "Running migration: Add batch columns...\n";

try {
    // Add batch_size column
    try {
        $pdo->exec("ALTER TABLE campaigns ADD COLUMN batch_size INT DEFAULT 1");
        echo "✓ Added batch_size column\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column') !== false) {
            echo "ℹ️ batch_size column already exists\n";
        } else {
            throw $e;
        }
    }

    // Add last_activity column
    try {
        $pdo->exec("ALTER TABLE campaigns ADD COLUMN last_activity DATETIME DEFAULT NULL");
        echo "✓ Added last_activity column\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column') !== false) {
            echo "ℹ️ last_activity column already exists\n";
        } else {
            throw $e;
        }
    }

    echo "✅ Migration completed successfully!\n";

} catch (Exception $e) {
    echo "❌ Migration failed: " . $e->getMessage() . "\n";
    throw $e;
}
