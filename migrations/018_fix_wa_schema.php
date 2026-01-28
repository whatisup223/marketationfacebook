<?php
/**
 * Migration: Fix and Update WhatsApp Tables Schema
 * Version: 018
 * Description: Ensures that wa_campaigns and settings tables have the latest schema columns (gateway_mode, external_config).
 */

function migrate_018_up($pdo)
{
    // 1. Ensure wa_campaigns has 'gateway_mode' and correct 'media_type'
    try {
        // Check if gateway_mode exists
        $stmt = $pdo->query("SHOW COLUMNS FROM wa_campaigns LIKE 'gateway_mode'");
        if ($stmt->rowCount() == 0) {
            $pdo->exec("ALTER TABLE wa_campaigns ADD COLUMN `gateway_mode` ENUM('qr', 'meta', 'twilio', 'other') NOT NULL DEFAULT 'qr' AFTER `campaign_name`");
        }

        // Update media_type enum to include all types (if not already)
        $pdo->exec("ALTER TABLE wa_campaigns MODIFY COLUMN `media_type` ENUM('text', 'image', 'image_local', 'video', 'video_local', 'document', 'document_local', 'location') NOT NULL DEFAULT 'text'");

    } catch (PDOException $e) {
        // Error implies table might not exist or other issue, but we proceed
        error_log("Migrate 018 (wa_campaigns) Warning: " . $e->getMessage());
    }

    // 2. Ensure user_wa_settings exists and has 'external_config'
    try {
        // Create table logic if not exists (Best to be explicit here)
        $sql = "CREATE TABLE IF NOT EXISTS `user_wa_settings` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT NOT NULL UNIQUE,
            `active_gateway` ENUM('qr', 'external') DEFAULT 'qr',
            `external_provider` VARCHAR(50) DEFAULT 'meta',
            `external_config` TEXT DEFAULT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
        $pdo->exec($sql);

        // Check for 'external_config' column specifically (for existing older tables)
        $stmt = $pdo->query("SHOW COLUMNS FROM user_wa_settings LIKE 'external_config'");
        if ($stmt->rowCount() == 0) {
            $pdo->exec("ALTER TABLE user_wa_settings ADD COLUMN `external_config` TEXT DEFAULT NULL AFTER `external_provider`");
        }
    } catch (PDOException $e) {
        error_log("Migrate 018 (user_wa_settings) Warning: " . $e->getMessage());
    }

    // 3. Ensure wa_accounts exists (Basic check, usually created by 015)
    // If table missing, create it now as fallback
    $pdo->exec("CREATE TABLE IF NOT EXISTS `wa_accounts` (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    return true;
}

function migrate_018_down($pdo)
{
    // We generally don't want to revert structural additions that might lose data
    return true;
}

function migrate_018_description()
{
    return "Fixes schema for WhatsApp tables to ensure gateway_mode and external_config columns exist.";
}
