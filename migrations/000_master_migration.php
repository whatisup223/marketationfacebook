<?php
/**
 * Master Migration File
 * This file consolidates all previous database migrations (000-037) into a single, idempotent script.
 * It is safe to run multiple times and ensures the database schema is up to date.
 */

if (!isset($pdo)) {
    require_once __DIR__ . '/../includes/functions.php';
    $pdo = getDB();
}

/**
 * Helper function to check if a column exists in a table
 */
if (!function_exists('columnExists')) {
    function columnExists($pdo, $table, $column)
    {
        $dbName = $pdo->query("SELECT DATABASE()")->fetchColumn();
        $stmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM INFORMATION_SCHEMA.COLUMNS 
            WHERE TABLE_SCHEMA = ? 
            AND TABLE_NAME = ? 
            AND COLUMN_NAME = ?
        ");
        $stmt->execute([$dbName, $table, $column]);
        return $stmt->fetchColumn() > 0;
    }
}

/**
 * Helper function to check if a table exists
 */
if (!function_exists('tableExists')) {
    function tableExists($pdo, $table)
    {
        $dbName = $pdo->query("SELECT DATABASE()")->fetchColumn();
        $stmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM INFORMATION_SCHEMA.TABLES 
            WHERE TABLE_SCHEMA = ? 
            AND TABLE_NAME = ?
        ");
        $stmt->execute([$dbName, $table]);
        return $stmt->fetchColumn() > 0;
    }
}

/**
 * Helper function to check if an index exists
 */
if (!function_exists('indexExists')) {
    function indexExists($pdo, $table, $indexName)
    {
        $stmt = $pdo->prepare("SHOW INDEX FROM `$table` WHERE Key_name = ?");
        $stmt->execute([$indexName]);
        return $stmt->fetch() !== false;
    }
}

try {
    echo "Starting Master Migration...\n";

    // --- 000: Initial Setup (Users, Accounts, Pages, etc.) ---
    $pdo->exec("CREATE TABLE IF NOT EXISTS `users` (
        `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
        `username` varchar(50) NOT NULL,
        `password` varchar(255) NOT NULL,
        `email` varchar(100) DEFAULT NULL,
        `role` enum('user','admin') NOT NULL DEFAULT 'user',
        `status` enum('active','inactive') NOT NULL DEFAULT 'active',
        `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`id`),
        UNIQUE KEY `username` (`username`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `fb_accounts` (
        `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
        `user_id` int(11) UNSIGNED NOT NULL,
        `fb_user_id` varchar(100) NOT NULL,
        `fb_user_name` varchar(255) DEFAULT NULL,
        `access_token` text NOT NULL,
        `status` enum('active','expired') NOT NULL DEFAULT 'active',
        `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`id`),
        KEY `user_id` (`user_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `fb_pages` (
        `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
        `account_id` int(11) UNSIGNED NOT NULL,
        `page_id` varchar(100) NOT NULL,
        `page_name` varchar(255) NOT NULL,
        `page_access_token` text NOT NULL,
        `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`id`),
        KEY `account_id` (`account_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `tickets` (
        `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
        `user_id` int(11) UNSIGNED NOT NULL,
        `subject` varchar(255) NOT NULL,
        `status` enum('open','closed','pending') NOT NULL DEFAULT 'open',
        `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`id`),
        KEY `user_id` (`user_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // --- 002: WhatsApp Campaigns ---
    $pdo->exec("CREATE TABLE IF NOT EXISTS `wa_campaigns` (
        `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
        `user_id` int(11) UNSIGNED NOT NULL,
        `campaign_name` varchar(255) NOT NULL,
        `gateway_mode` enum('qr','meta','twilio','other') NOT NULL DEFAULT 'qr',
        `selected_accounts` text DEFAULT NULL,
        `message` text NOT NULL,
        `media_type` enum('text','image','image_local','video','video_local','document','document_local','location') NOT NULL DEFAULT 'text',
        `media_url` text DEFAULT NULL,
        `media_file_path` varchar(500) DEFAULT NULL,
        `numbers` longtext NOT NULL,
        `delay_min` int(11) NOT NULL DEFAULT 10,
        `delay_max` int(11) NOT NULL DEFAULT 25,
        `switch_every` int(11) DEFAULT 5,
        `status` enum('pending','running','paused','completed','failed','cancelled') NOT NULL DEFAULT 'pending',
        `total_count` int(11) NOT NULL DEFAULT 0,
        `sent_count` int(11) NOT NULL DEFAULT 0,
        `failed_count` int(11) NOT NULL DEFAULT 0,
        `current_number_index` int(11) NOT NULL DEFAULT 0,
        `current_account_index` int(11) NOT NULL DEFAULT 0,
        `messages_sent_with_current_account` int(11) NOT NULL DEFAULT 0,
        `error_log` longtext DEFAULT NULL,
        `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
        `started_at` timestamp NULL DEFAULT NULL,
        `completed_at` timestamp NULL DEFAULT NULL,
        `paused_at` timestamp NULL DEFAULT NULL,
        PRIMARY KEY (`id`),
        KEY `user_id` (`user_id`),
        KEY `status` (`status`),
        KEY `created_at` (`created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // --- 011-012: Link & Simplify Tickets ---
    if (!columnExists($pdo, 'tickets', 'campaign_id')) {
        $pdo->exec("ALTER TABLE `tickets` ADD COLUMN `campaign_id` int(11) DEFAULT NULL");
    }

    // --- 013-014: FB Pages Cursor & User Points ---
    if (!columnExists($pdo, 'fb_pages', 'last_cursor')) {
        $pdo->exec("ALTER TABLE `fb_pages` ADD COLUMN `last_cursor` text DEFAULT NULL");
    }
    if (!columnExists($pdo, 'users', 'points')) {
        $pdo->exec("ALTER TABLE `users` ADD COLUMN `points` int(11) DEFAULT 0");
    }

    // --- 015-016: WhatsApp Accounts & User Settings ---
    $pdo->exec("CREATE TABLE IF NOT EXISTS `wa_accounts` (
        `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
        `user_id` int(11) UNSIGNED NOT NULL,
        `instance_name` varchar(100) NOT NULL,
        `status` enum('connected','disconnected','pairing') NOT NULL DEFAULT 'disconnected',
        `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`id`),
        KEY `user_id` (`user_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `user_wa_settings` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT NOT NULL,
        `active_gateway` ENUM('qr', 'external') DEFAULT 'qr',
        `external_provider` VARCHAR(50) DEFAULT 'meta',
        `external_config` TEXT DEFAULT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY `unique_user` (`user_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // --- 020-021: AutoReply Rules & Webhook Token ---
    $pdo->exec("CREATE TABLE IF NOT EXISTS `auto_reply_rules` (
        `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
        `page_id` varchar(100) NOT NULL,
        `trigger_type` enum('keyword', 'default') NOT NULL DEFAULT 'keyword',
        `keywords` text DEFAULT NULL,
        `reply_message` text NOT NULL,
        `hide_comment` tinyint(1) DEFAULT 0,
        `reply_source` enum('comment','message') DEFAULT 'comment',
        `is_active` tinyint(1) DEFAULT 1,
        `reply_buttons` text DEFAULT NULL,
        `reply_image_url` text DEFAULT NULL,
        `is_ai_safe` tinyint(1) DEFAULT 1,
        `bypass_schedule` tinyint(1) DEFAULT 0,
        `bypass_cooldown` tinyint(1) DEFAULT 0,
        `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`id`),
        KEY `page_id` (`page_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    if (!columnExists($pdo, 'users', 'webhook_token')) {
        $pdo->exec("ALTER TABLE `users` ADD COLUMN `webhook_token` varchar(100) DEFAULT NULL, ADD UNIQUE KEY (`webhook_token`)");
    }

    // --- 022-023: FB Scheduled Posts ---
    $pdo->exec("CREATE TABLE IF NOT EXISTS `fb_scheduled_posts` (
        `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
        `user_id` int(11) UNSIGNED NOT NULL,
        `page_id` varchar(100) NOT NULL,
        `message` text DEFAULT NULL,
        `image_url` text DEFAULT NULL,
        `video_url` text DEFAULT NULL,
        `media_type` enum('text','image','video') NOT NULL DEFAULT 'text',
        `scheduled_at` datetime NOT NULL,
        `status` enum('pending','posted','failed') NOT NULL DEFAULT 'pending',
        `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`id`),
        KEY `user_id` (`user_id`),
        KEY `status` (`status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // --- 025: Fix FB Pages Uniqueness ---
    if (indexExists($pdo, 'fb_pages', 'page_id')) {
        $pdo->exec("ALTER TABLE fb_pages DROP INDEX page_id");
    }
    if (indexExists($pdo, 'fb_pages', 'unique_page_id')) {
        $pdo->exec("ALTER TABLE fb_pages DROP INDEX unique_page_id");
    }
    if (!indexExists($pdo, 'fb_pages', 'unique_acc_page')) {
        try {
            $pdo->exec("ALTER TABLE fb_pages ADD UNIQUE KEY `unique_acc_page` (account_id, page_id)");
        } catch (PDOException $e) {
            if ($e->getCode() == '23000') { // Duplicate entry error
                echo "⚠️ Notice: Could not create unique index 'unique_acc_page' due to existing duplicate data in 'fb_pages'. This is expected if the same page was added multiple times to the same account. Skipping...\n";
            } else {
                throw $e;
            }
        }
    }

    // --- 026: FB Moderation ---
    $pdo->exec("CREATE TABLE IF NOT EXISTS `fb_moderation_rules` (
        `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
        `user_id` int(11) UNSIGNED NOT NULL,
        `page_id` varchar(100) NOT NULL,
        `is_active` tinyint(1) DEFAULT 1,
        `hide_phones` tinyint(1) DEFAULT 0,
        `hide_links` tinyint(1) DEFAULT 0,
        `banned_keywords` text DEFAULT NULL,
        `action_type` enum('hide','delete') DEFAULT 'hide',
        PRIMARY KEY (`id`),
        UNIQUE KEY `page_id` (`page_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // --- 027-029: FB Pages Intelligence & SaaS Settings ---
    if (!columnExists($pdo, 'fb_pages', 'bot_cooldown_seconds')) {
        $pdo->exec("ALTER TABLE fb_pages 
            ADD COLUMN bot_cooldown_seconds INT DEFAULT 0,
            ADD COLUMN bot_schedule_enabled TINYINT(1) DEFAULT 0,
            ADD COLUMN bot_schedule_start TIME DEFAULT '00:00:00',
            ADD COLUMN bot_schedule_end TIME DEFAULT '23:59:59',
            ADD COLUMN bot_exclude_keywords TINYINT(1) DEFAULT 0,
            ADD COLUMN bot_ai_sentiment_enabled TINYINT(1) DEFAULT 1,
            ADD COLUMN bot_anger_keywords TEXT NULL,
            ADD COLUMN default_reply_image_url TEXT DEFAULT NULL");

        // Defaults for anger keywords
        $default_anger = "نصاب,نصابين,رد يا,فينكم,اشتريت,موصلش,عايز أكلم حد,شكوى,مدير,بني آدم,غالي,حرام";
        $pdo->prepare("UPDATE fb_pages SET bot_anger_keywords = ? WHERE bot_anger_keywords IS NULL")->execute([$default_anger]);
    }

    // --- 028, 033: Bot Sent Messages Tracking ---
    $pdo->exec("CREATE TABLE IF NOT EXISTS `bot_sent_messages` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `message_id` VARCHAR(100) NOT NULL,
        `page_id` VARCHAR(100) NOT NULL,
        `rule_id` INT DEFAULT NULL,
        `reply_source` ENUM('comment', 'message') DEFAULT 'message',
        `user_id` VARCHAR(100) DEFAULT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY `unique_msg` (`message_id`),
        KEY `page_id` (`page_id`),
        KEY `user_id` (`user_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // --- 029, 030, 034: Conversation States (Handover Protocol) ---
    $pdo->exec("CREATE TABLE IF NOT EXISTS `bot_conversation_states` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `page_id` VARCHAR(100) NOT NULL,
        `user_id` VARCHAR(100) NOT NULL,
        `user_name` VARCHAR(255) DEFAULT NULL,
        `reply_source` ENUM('comment', 'message') NOT NULL DEFAULT 'message',
        `conversation_state` ENUM('active', 'handover', 'resolved') DEFAULT 'active',
        `last_user_message` TEXT DEFAULT NULL,
        `last_bot_reply_text` TEXT DEFAULT NULL,
        `repeat_count` INT DEFAULT 0,
        `is_anger_detected` TINYINT(1) DEFAULT 0,
        `last_user_message_at` DATETIME DEFAULT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY `page_user_source` (`page_id`, `user_id`, `reply_source`),
        INDEX (`conversation_state`),
        INDEX (`is_anger_detected`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // --- 036: Compliance Tables ---
    $pdo->exec("CREATE TABLE IF NOT EXISTS `bot_audience` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `page_id` VARCHAR(100) NOT NULL,
        `user_id` VARCHAR(100) NOT NULL,
        `last_interaction_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `source` ENUM('comment', 'message') DEFAULT 'message',
        `is_window_open` TINYINT(1) DEFAULT 1,
        UNIQUE KEY `page_user` (`page_id`, `user_id`),
        INDEX (`page_id`),
        INDEX (`user_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `bot_optouts` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `page_id` VARCHAR(100) NOT NULL,
        `user_id` VARCHAR(100) NOT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY `page_user_opt` (`page_id`, `user_id`),
        INDEX (`page_id`),
        INDEX (`user_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    echo "✅ Master Migration completed successfully!\n";

} catch (Exception $e) {
    echo "❌ Master Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
