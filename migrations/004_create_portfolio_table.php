<?php
// migrations/004_create_portfolio_table.php
require_once __DIR__ . '/../includes/functions.php';

$pdo = getDB();

try {
    $sql = "CREATE TABLE IF NOT EXISTS portfolio_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title_ar VARCHAR(255) NOT NULL,
        title_en VARCHAR(255) NOT NULL,
        description_ar TEXT,
        description_en TEXT,
        item_type ENUM('image', 'iframe') DEFAULT 'image',
        content_url TEXT NOT NULL,
        preview_url TEXT,
        display_order INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

    $pdo->exec($sql);
    echo "Portfolio table created successfully!\n";
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}
