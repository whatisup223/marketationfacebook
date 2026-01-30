<?php
// migrations/026_create_fb_moderation_tables.php
require_once __DIR__ . '/../includes/db_config.php';

try {
    // 1. Table for rules
    $pdo->exec("CREATE TABLE IF NOT EXISTS fb_moderation_rules (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        page_id VARCHAR(50) NOT NULL,
        banned_keywords TEXT NULL,
        hide_phones TINYINT(1) DEFAULT 0,
        hide_links TINYINT(1) DEFAULT 0,
        action_type ENUM('hide', 'delete') DEFAULT 'hide',
        is_active TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX (user_id),
        INDEX (page_id),
        INDEX (is_active)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    // 2. Table for logs
    $pdo->exec("CREATE TABLE IF NOT EXISTS fb_moderation_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        page_id VARCHAR(50) NOT NULL,
        comment_id VARCHAR(100) NOT NULL,
        comment_text TEXT NULL,
        sender_name VARCHAR(255) NULL,
        reason VARCHAR(100) NULL,
        action_taken VARCHAR(50) NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX (user_id),
        INDEX (page_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    echo "Tables 'fb_moderation_rules' and 'fb_moderation_logs' created successfully.";
} catch (PDOException $e) {
    throw new Exception("Migration 026 failed: " . $e->getMessage());
}
