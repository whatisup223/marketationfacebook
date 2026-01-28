<?php
// migrations/013_add_last_cursor_to_fb_pages.php
// Migration: Add last_cursor column to fb_pages table to support pagination state

require_once __DIR__ . '/../includes/db_config.php';

echo "Running migration: Add last_cursor to fb_pages...\n";

try {
    // Add last_cursor column
    try {
        $pdo->exec("ALTER TABLE fb_pages ADD COLUMN last_cursor TEXT DEFAULT NULL");
        echo "✓ Added last_cursor column\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column') !== false) {
            echo "ℹ️ last_cursor column already exists\n";
        } else {
            throw $e;
        }
    }

    echo "✅ Migration completed successfully!\n";

} catch (Exception $e) {
    echo "❌ Migration failed: " . $e->getMessage() . "\n";
    throw $e;
}
