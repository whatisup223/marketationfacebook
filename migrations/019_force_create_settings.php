<?php
/**
 * Migration: Force Create Settings Table
 * Version: 019
 * Description: Force creation of user_wa_settings without FK constraints to avoid errors.
 */

function migrate_019_up($pdo)
{
    // Try to drop if exists to ensure clean state (optional, but good if table is corrupted)
    // $pdo->exec("DROP TABLE IF EXISTS `user_wa_settings`"); // Risky if data exists, assume table doesn't exist based on error

    // Create the table SIMPLE (No Foreign Keys yet)
    $sql = "CREATE TABLE IF NOT EXISTS `user_wa_settings` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT NOT NULL,
        `active_gateway` ENUM('qr', 'external') DEFAULT 'qr',
        `external_provider` VARCHAR(50) DEFAULT 'meta',
        `external_config` TEXT DEFAULT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY `unique_user` (`user_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

    try {
        $pdo->exec($sql);
    } catch (PDOException $e) {
        // If this fails, we must see why
        die("FATAL ERROR CREATING TABLE 019: " . $e->getMessage());
    }

    return true;
}

function migrate_019_down($pdo)
{
    return true;
}

function migrate_019_description()
{
    return "Force creation of user_wa_settings without strict FK constraints.";
}
