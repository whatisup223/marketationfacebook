<?php
/**
 * Migration: Create User WhatsApp Settings Table
 */

function migrate_016_create_user_wa_settings($pdo)
{
    try {
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
        return true;
    } catch (PDOException $e) {
        error_log("Migration 016 Error: " . $e->getMessage());
        return false;
    }
}

if (basename(__FILE__) == basename($_SERVER['SCRIPT_FILENAME'])) {
    require_once __DIR__ . '/../includes/functions.php';
    migrate_016_create_user_wa_settings(getDB());
    echo "Migration 016 completed successfully.";
}
