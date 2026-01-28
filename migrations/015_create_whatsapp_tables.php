<?php
/**
 * Migration: Create WhatsApp Accounts Table
 */

function migrate_015_create_whatsapp_tables($pdo)
{
    try {
        $sql = "CREATE TABLE IF NOT EXISTS `wa_accounts` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT NOT NULL,
            `instance_name` VARCHAR(255) NOT NULL,
            `phone` VARCHAR(50) DEFAULT NULL,
            `account_name` VARCHAR(255) DEFAULT NULL,
            `status` ENUM('connected', 'disconnected', 'pairing') DEFAULT 'disconnected',
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

        $pdo->exec($sql);
        return true;
    } catch (PDOException $e) {
        error_log("Migration 015 Error: " . $e->getMessage());
        return false;
    }
}

// Execute migration if called directly (optional, depends on how the system runs migrations)
if (basename(__FILE__) == basename($_SERVER['SCRIPT_FILENAME'])) {
    require_once __DIR__ . '/../includes/functions.php';
    migrate_015_create_whatsapp_tables(getDB());
    echo "Migration 015 completed.";
}
