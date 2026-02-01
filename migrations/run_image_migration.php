<?php
// Migration: Add image support to auto-reply rules
// Handle path whether running from root or migrations folder
if (file_exists(__DIR__ . '/../includes/functions.php')) {
    require_once __DIR__ . '/../includes/functions.php';
} else {
    require_once 'includes/functions.php';
}

try {
    $pdo = getDB();

    // Get database name
    $dbName = $pdo->query("SELECT DATABASE()")->fetchColumn();

    // Check if column already exists in auto_reply_rules
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count 
        FROM INFORMATION_SCHEMA.COLUMNS 
        WHERE TABLE_SCHEMA = ? 
        AND TABLE_NAME = 'auto_reply_rules' 
        AND COLUMN_NAME = 'reply_image_url'
    ");
    $stmt->execute([$dbName]);
    $hasImageColumn = $stmt->fetchColumn() > 0;

    if (!$hasImageColumn) {
        $pdo->exec("ALTER TABLE auto_reply_rules ADD COLUMN reply_image_url TEXT DEFAULT NULL");
        echo "✅ Added reply_image_url to auto_reply_rules\n";
    } else {
        echo "ℹ️  Column reply_image_url already exists in auto_reply_rules\n";
    }

    // Check if column already exists in fb_pages
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count 
        FROM INFORMATION_SCHEMA.COLUMNS 
        WHERE TABLE_SCHEMA = ? 
        AND TABLE_NAME = 'fb_pages' 
        AND COLUMN_NAME = 'default_reply_image_url'
    ");
    $stmt->execute([$dbName]);
    $hasDefaultImageColumn = $stmt->fetchColumn() > 0;

    if (!$hasDefaultImageColumn) {
        $pdo->exec("ALTER TABLE fb_pages ADD COLUMN default_reply_image_url TEXT DEFAULT NULL");
        echo "✅ Added default_reply_image_url to fb_pages\n";
    } else {
        echo "ℹ️  Column default_reply_image_url already exists in fb_pages\n";
    }

    echo "\n✅ Migration completed successfully!\n";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
