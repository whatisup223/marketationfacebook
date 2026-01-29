<?php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'marketation_db');

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

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
    echo "SUCCESS: Table auto_reply_rules created in marketation_db.";
} catch (PDOException $e) {
    echo "ERROR: " . $e->getMessage();
}
?>