<?php
require_once __DIR__ . '/includes/functions.php';

$pdo = getDB();

echo "Creating wa_campaigns table...\n";

$sql = "CREATE TABLE IF NOT EXISTS `wa_campaigns` (
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
    KEY `status` (`status`),
    KEY `created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

try {
    $pdo->exec($sql);
    echo "✅ Table wa_campaigns created successfully!\n";
} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
