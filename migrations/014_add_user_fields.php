<?php
// migrations/014_add_user_fields.php
// Migration: Add username and phone columns to users table

require_once __DIR__ . '/../includes/db_config.php';

echo "Running migration: Add username and phone to users table...\n";

try {
    // Add username column
    try {
        $pdo->exec("ALTER TABLE users ADD COLUMN username VARCHAR(50) DEFAULT NULL UNIQUE");
        echo "✓ Added username column\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column') !== false) {
            echo "ℹ️ username column already exists\n";
        } else {
            throw $e;
        }
    }

    // Add phone column
    try {
        $pdo->exec("ALTER TABLE users ADD COLUMN phone VARCHAR(20) DEFAULT NULL");
        echo "✓ Added phone column\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column') !== false) {
            echo "ℹ️ phone column already exists\n";
        } else {
            throw $e;
        }
    }

    echo "✅ Migration completed successfully!\n";

} catch (Exception $e) {
    echo "❌ Migration failed: " . $e->getMessage() . "\n";
    throw $e;
}
