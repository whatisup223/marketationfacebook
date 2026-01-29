<?php
// migrations/023_update_scheduled_posts_type.php
require_once __DIR__ . '/../includes/db_config.php';

try {
    // Add post_type column if it doesn't exist
    $checkColumn = $pdo->query("SHOW COLUMNS FROM fb_scheduled_posts LIKE 'post_type'");
    if (!$checkColumn->fetch()) {
        $pdo->exec("ALTER TABLE fb_scheduled_posts ADD COLUMN post_type VARCHAR(20) DEFAULT 'feed' AFTER page_id");
        echo "Column 'post_type' added to 'fb_scheduled_posts'.<br>";
    } else {
        echo "Column 'post_type' already exists.<br>";
    }

    // Update media_type to include 'story' and 'reel' logic if needed, 
    // but we use post_type for logic. Let's ensure post_type is indexed for performance.
    $pdo->exec("ALTER TABLE fb_scheduled_posts MODIFY COLUMN post_type VARCHAR(20) DEFAULT 'feed'");

    echo "Migration 023 completed successfully.";
} catch (PDOException $e) {
    throw new Exception("Migration 023 failed: " . $e->getMessage());
}
