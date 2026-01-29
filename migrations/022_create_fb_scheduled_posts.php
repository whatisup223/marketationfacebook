<?php
// migrations/022_create_fb_scheduled_posts.php
require_once __DIR__ . '/../includes/db_config.php';

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS fb_scheduled_posts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        page_id VARCHAR(50) NOT NULL,
        content TEXT NULL,
        media_type ENUM('text', 'image', 'video') DEFAULT 'text',
        media_url TEXT NULL,
        scheduled_at DATETIME NOT NULL,
        fb_post_id VARCHAR(100) NULL,
        status ENUM('pending', 'success', 'failed', 'canceled') DEFAULT 'pending',
        error_message TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX (user_id),
        INDEX (page_id),
        INDEX (status),
        INDEX (scheduled_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    echo "Table 'fb_scheduled_posts' created or already exists.";
} catch (PDOException $e) {
    throw new Exception("Migration 022 failed: " . $e->getMessage());
}
