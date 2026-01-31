<?php
// migrations/027_add_bot_intelligence_columns.php
// Description: Add bot cooldown and scheduling columns to fb_pages

require_once __DIR__ . '/../includes/db_config.php';

try {
    $stmt = $pdo->query("SHOW COLUMNS FROM fb_pages");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $changes = 0;

    if (!in_array('bot_cooldown_seconds', $columns)) {
        $pdo->exec("ALTER TABLE fb_pages ADD COLUMN bot_cooldown_seconds INT DEFAULT 0 AFTER page_access_token");
        echo "Added 'bot_cooldown_seconds' to fb_pages.<br>";
        $changes++;
    }

    if (!in_array('bot_schedule_enabled', $columns)) {
        $pdo->exec("ALTER TABLE fb_pages ADD COLUMN bot_schedule_enabled TINYINT(1) DEFAULT 0 AFTER bot_cooldown_seconds");
        echo "Added 'bot_schedule_enabled' to fb_pages.<br>";
        $changes++;
    }

    if (!in_array('bot_schedule_start', $columns)) {
        $pdo->exec("ALTER TABLE fb_pages ADD COLUMN bot_schedule_start TIME NULL AFTER bot_schedule_enabled");
        echo "Added 'bot_schedule_start' to fb_pages.<br>";
        $changes++;
    }

    if (!in_array('bot_schedule_end', $columns)) {
        $pdo->exec("ALTER TABLE fb_pages ADD COLUMN bot_schedule_end TIME NULL AFTER bot_schedule_start");
        echo "Added 'bot_schedule_end' to fb_pages.<br>";
        $changes++;
    }

    if (!in_array('bot_exclude_keywords', $columns)) {
        $pdo->exec("ALTER TABLE fb_pages ADD COLUMN bot_exclude_keywords TINYINT(1) DEFAULT 0 AFTER bot_schedule_end");
        echo "Added 'bot_exclude_keywords' to fb_pages.<br>";
        $changes++;
    }

    if ($changes === 0) {
        echo "No changes needed for fb_pages intelligence columns.<br>";
    } else {
        echo "Migration 027 (Bot Intelligence Columns) completed successfully with $changes changes.<br>";
    }

} catch (PDOException $e) {
    echo "Error in migration 027: " . $e->getMessage() . "<br>";
}
