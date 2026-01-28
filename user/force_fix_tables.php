<?php
// force_fix_tables.php
// This script forces the creation of missing tables directly, bypassing the migration system.

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../includes/functions.php';

// Check admin or logged in user (optional security, but good to have)
if (!isLoggedIn()) {
    die("Please login first.");
}

$pdo = getDB();

echo "<h1>Starting Database Repair...</h1>";

try {
    // 1. Create user_wa_settings
    echo "Check user_wa_settings... ";
    $sql1 = "CREATE TABLE IF NOT EXISTS `user_wa_settings` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT NOT NULL,
        `active_gateway` ENUM('qr', 'external') DEFAULT 'qr',
        `external_provider` VARCHAR(50) DEFAULT 'meta',
        `external_config` TEXT DEFAULT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY `unique_user` (`user_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    $pdo->exec($sql1);

    // Check if external_config exists (for old tables)
    $stmt = $pdo->query("SHOW COLUMNS FROM user_wa_settings LIKE 'external_config'");
    if ($stmt->rowCount() == 0) {
        $pdo->exec("ALTER TABLE user_wa_settings ADD COLUMN `external_config` TEXT DEFAULT NULL AFTER `external_provider`");
        echo "ADDED external_config column. ";
    }
    echo "<span style='color:green'>Done.</span><br>";

    // 2. Create wa_accounts
    echo "Check wa_accounts... ";
    $sql2 = "CREATE TABLE IF NOT EXISTS `wa_accounts` (
            `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
            `user_id` INT(11) UNSIGNED NOT NULL,
            `instance_name` VARCHAR(100) NOT NULL,
            `phone` VARCHAR(50) NULL,
            `account_name` VARCHAR(255) DEFAULT NULL,
            `status` VARCHAR(50) NOT NULL DEFAULT 'disconnected',
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `unique_instance` (`instance_name`),
            KEY `user_id` (`user_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    $pdo->exec($sql2);
    echo "<span style='color:green'>Done.</span><br>";

    // 3. Create/Update wa_campaigns
    echo "Check wa_campaigns... ";

    // Attempt create first
    $sql3 = "CREATE TABLE IF NOT EXISTS `wa_campaigns` (
        `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
        `user_id` INT(11) UNSIGNED NOT NULL,
        `campaign_name` VARCHAR(255) NOT NULL,
        `gateway_mode` ENUM('qr', 'meta', 'twilio', 'other') NOT NULL DEFAULT 'qr',
        `selected_accounts` TEXT NULL COMMENT 'JSON array of account IDs for QR mode',
        `message` TEXT NOT NULL,
        `media_type` ENUM('text', 'image', 'image_local', 'video', 'video_local', 'document', 'document_local', 'location') NOT NULL DEFAULT 'text',
        `media_url` TEXT NULL,
        `media_file_path` VARCHAR(500) NULL COMMENT 'Path to uploaded file for local media',
        `numbers` LONGTEXT NOT NULL COMMENT 'JSON array of phone numbers',
        `delay_min` INT(11) NOT NULL DEFAULT 10 COMMENT 'Minimum delay in seconds',
        `delay_max` INT(11) NOT NULL DEFAULT 25 COMMENT 'Maximum delay in seconds',
        `switch_every` INT(11) NULL DEFAULT 5 COMMENT 'Switch account every X messages (QR mode only)',
        `status` ENUM('pending', 'running', 'paused', 'completed', 'failed', 'cancelled') NOT NULL DEFAULT 'pending',
        `total_count` INT(11) NOT NULL DEFAULT 0,
        `sent_count` INT(11) NOT NULL DEFAULT 0,
        `failed_count` INT(11) NOT NULL DEFAULT 0,
        `current_number_index` INT(11) NOT NULL DEFAULT 0 COMMENT 'Resume from this index',
        `current_account_index` INT(11) NOT NULL DEFAULT 0 COMMENT 'Current account being used',
        `messages_sent_with_current_account` INT(11) NOT NULL DEFAULT 0,
        `error_log` LONGTEXT NULL COMMENT 'JSON array of errors',
        `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `started_at` TIMESTAMP NULL,
        `completed_at` TIMESTAMP NULL,
        `paused_at` TIMESTAMP NULL,
        PRIMARY KEY (`id`),
        KEY `user_id` (`user_id`),
        KEY `status` (`status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    // Added created_at, status keys, removed FK to avoid constraint errors for now (Simpler is safer)

    $pdo->exec($sql3);

    $stmt = $pdo->query("SHOW COLUMNS FROM wa_campaigns LIKE 'gateway_mode'");
    if ($stmt->rowCount() == 0) {
        $pdo->exec("ALTER TABLE wa_campaigns ADD COLUMN `gateway_mode` ENUM('qr', 'meta', 'twilio', 'other') NOT NULL DEFAULT 'qr' AFTER `campaign_name`");
        echo "ADDED gateway_mode column. ";
    }
    echo "<span style='color:green'>Done.</span><br>";

    echo "<h2>✅ ALL FIXES APPLIED SUCCESSFULLY!</h2>";
    echo "<p>You can now go back to <a href='wa_settings.php'>Settings</a>.</p>";

} catch (PDOException $e) {
    echo "<h2 style='color:red'>❌ ERROR: " . htmlspecialchars($e->getMessage()) . "</h2>";
}
