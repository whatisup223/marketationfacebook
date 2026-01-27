<?php
// migrations/000_initial_setup.php
// Description: Master migration for the unified Marketing Automation System.
// This file consolidates all previous schema updates into a single clean start.
// Created: 2026-01-27

require_once __DIR__ . '/../includes/db_config.php';

try {
    global $pdo;
    echo "Starting Master Initial Setup...<br>";

    // 1. Leads Table (Customers)
    $pdo->exec("CREATE TABLE IF NOT EXISTS `fb_leads` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `fb_user_id` varchar(100) NOT NULL,
        `fb_user_name` varchar(255) DEFAULT NULL,
        `page_id` varchar(100) DEFAULT NULL,
        `first_seen` datetime DEFAULT CURRENT_TIMESTAMP,
        `last_interaction` datetime DEFAULT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `unique_user_page` (`fb_user_id`, `page_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    echo " - Table 'fb_leads' [OK]<br>";

    // 2. Facebook Pages
    $pdo->exec("CREATE TABLE IF NOT EXISTS `fb_pages` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `page_id` varchar(100) NOT NULL,
        `page_name` varchar(255) DEFAULT NULL,
        `page_access_token` text NOT NULL,
        `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `page_id` (`page_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    echo " - Table 'fb_pages' [OK]<br>";

    // 3. Main Campaigns Table
    // Consolidated with all legacy updates (batch, retry status, last_activity)
    $pdo->exec("CREATE TABLE IF NOT EXISTS `campaigns` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `user_id` int(11) NOT NULL,
        `page_id` int(11) NOT NULL,
        `campaign_name` varchar(255) NOT NULL,
        `message_text` text NOT NULL,
        `image_url` varchar(500) DEFAULT NULL,
        `status` enum('draft','running','paused','completed','cancelled') DEFAULT 'draft',
        `batch_size` int(11) DEFAULT 5,
        `retry_count` int(11) DEFAULT 1,
        `retry_delay` int(11) DEFAULT 10,
        `sent_count` int(11) DEFAULT 0,
        `failed_count` int(11) DEFAULT 0,
        `last_activity` datetime DEFAULT NULL,
        `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    echo " - Table 'campaigns' [OK]<br>";

    // 4. Campaign Queue (Master Queue)
    // Consolidated with retry logic columns
    $pdo->exec("CREATE TABLE IF NOT EXISTS `campaign_queue` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `campaign_id` int(11) NOT NULL,
        `lead_id` int(11) NOT NULL,
        `status` enum('pending','sent','failed') DEFAULT 'pending',
        `attempts_count` int(11) DEFAULT 0,
        `next_retry_at` datetime DEFAULT NULL,
        `error_message` text,
        `sent_at` datetime DEFAULT NULL,
        PRIMARY KEY (`id`),
        KEY `idx_campaign_status` (`campaign_id`, `status`),
        CONSTRAINT `fk_q_campaign` FOREIGN KEY (`campaign_id`) REFERENCES `campaigns` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    echo " - Table 'campaign_queue' [OK]<br>";

    // 5. Pricing Plans (For the landing page)
    $pdo->exec("CREATE TABLE IF NOT EXISTS `pricing_plans` (
        `id` int(11) AUTO_INCREMENT PRIMARY KEY,
        `plan_name_ar` varchar(255) NOT NULL,
        `plan_name_en` varchar(255) NOT NULL,
        `price` decimal(10, 2) NOT NULL,
        `currency_ar` varchar(50) DEFAULT 'ريال',
        `currency_en` varchar(50) DEFAULT 'SAR',
        `description_ar` text,
        `description_en` text,
        `is_featured` boolean DEFAULT 0,
        `is_active` boolean DEFAULT 1,
        `created_at` timestamp DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    echo " - Table 'pricing_plans' [OK]<br>";

    echo "<b>Master Initial Setup Completed Successfully!</b><br>";

} catch (Exception $e) {
    echo "<span style='color:red'>Critical Error: " . $e->getMessage() . "</span><br>";
}
?>