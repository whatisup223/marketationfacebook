<?php
require_once __DIR__ . '/includes/functions.php';

try {
    $pdo = getDB();
    if (!$pdo) {
        die("Could not connect to DB");
    }

    $sql = "CREATE TABLE IF NOT EXISTS auto_reply_rules (
        id INT AUTO_INCREMENT PRIMARY KEY,
        page_id VARCHAR(50) NOT NULL,
        trigger_type ENUM('keyword', 'default') NOT NULL DEFAULT 'keyword',
        keywords TEXT COMMENT 'Comma separated keywords or JSON',
        reply_message TEXT,
        reply_image_url VARCHAR(255) DEFAULT NULL,
        active TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_page (page_id),
        INDEX idx_trigger (trigger_type)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

    $pdo->exec($sql);
    echo "Table auto_reply_rules created successfully!";

} catch (PDOException $e) {
    die("DB Error: " . $e->getMessage());
}
?>