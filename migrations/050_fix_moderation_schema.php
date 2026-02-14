<?php
// Fix Moderation Tables Schema

if (!isset($pdo)) {
    require_once __DIR__ . '/../includes/functions.php';
    $pdo = getDB();
}

try {
    // 1. Ensure fb_moderation_rules exists and has platform column
    $pdo->exec("CREATE TABLE IF NOT EXISTS `fb_moderation_rules` (
        `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
        `user_id` int(11) UNSIGNED NOT NULL,
        `page_id` varchar(100) NOT NULL,
        `is_active` tinyint(1) DEFAULT 1,
        `hide_phones` tinyint(1) DEFAULT 0,
        `hide_links` tinyint(1) DEFAULT 0,
        `banned_keywords` text DEFAULT NULL,
        `action_type` enum('hide','delete') DEFAULT 'hide',
        `platform` enum('facebook','instagram') DEFAULT 'facebook',
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // Add platform column if table already existed but missing platform
    try {
        $pdo->exec("ALTER TABLE `fb_moderation_rules` ADD COLUMN `platform` enum('facebook','instagram') DEFAULT 'facebook'");
    } catch (Exception $e) {
    }

    // 2. Fix Unique Index on fb_moderation_rules (page_id -> page_id + platform)
    try {
        $pdo->exec("ALTER TABLE `fb_moderation_rules` DROP INDEX `page_id`");
    } catch (Exception $e) {
    }

    try {
        $pdo->exec("ALTER TABLE `fb_moderation_rules` ADD UNIQUE KEY `idx_page_platform` (`page_id`, `platform`)");
    } catch (Exception $e) {
    }


    // 3. Create fb_moderation_logs table if not exists
    $pdo->exec("CREATE TABLE IF NOT EXISTS `fb_moderation_logs` (
        `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
        `user_id` int(11) UNSIGNED NOT NULL,
        `page_id` varchar(100) NOT NULL,
        `post_id` varchar(100) DEFAULT NULL,
        `comment_id` varchar(100) DEFAULT NULL,
        `user_name` varchar(255) DEFAULT NULL,
        `content` text DEFAULT NULL,
        `reason` varchar(255) DEFAULT NULL,
        `action_taken` enum('hide','delete') DEFAULT 'hide',
        `platform` enum('facebook','instagram') DEFAULT 'facebook',
        `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`id`),
        KEY `user_id` (`user_id`),
        KEY `page_id` (`page_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    echo "✅ Migration 050: Logic updated successfully!\n";

} catch (Exception $e) {
    echo "❌ Migration 050 Failed: " . $e->getMessage() . "\n";
}
