<?php
// migrations/000_create_base_schema.php
// Description: Create core tables for Facebook Automation System (Leads, Campaigns, Targets)

require_once __DIR__ . '/../includes/db_config.php';

try {
    global $pdo;

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
    echo "Table 'fb_leads' check/create done.\n";

    // 2. Campaigns Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS `fb_campaigns` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `campaign_name` varchar(255) NOT NULL,
        `page_id` varchar(100) NOT NULL,
        `page_access_token` text NOT NULL, /* Storing token here for offline processing */
        `message_template` text NOT NULL,
        `status` enum('draft','scheduled','active','processing','paused','completed','cancelled') DEFAULT 'draft',
        `schedule_time` datetime DEFAULT NULL,
        `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
        `completed_at` datetime DEFAULT NULL,
        `delay_min` int(11) DEFAULT 5,
        `delay_max` int(11) DEFAULT 15,
        `target_count` int(11) DEFAULT 0,
        `sent_count` int(11) DEFAULT 0,
        `last_heartbeat` datetime DEFAULT NULL,
        `note` text,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    echo "Table 'fb_campaigns' check/create done.\n";

    // 3. Campaign Targets (Queue)
    $pdo->exec("CREATE TABLE IF NOT EXISTS `campaign_targets` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `campaign_id` int(11) NOT NULL,
        `lead_id` int(11) NOT NULL,
        `status` enum('pending','sent','failed') DEFAULT 'pending',
        `sent_at` datetime DEFAULT NULL,
        `error_message` text,
        PRIMARY KEY (`id`),
        KEY `idx_campaign_status` (`campaign_id`, `status`),
        CONSTRAINT `fk_campaign` FOREIGN KEY (`campaign_id`) REFERENCES `fb_campaigns` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    echo "Table 'campaign_targets' check/create done.\n";

    echo "✅ Base Schema Created Successfully!\n";

} catch (Exception $e) {
    echo "❌ Error creating schema: " . $e->getMessage() . "\n";
}
