<?php
// Migration: 020_add_autoreply_and_verify_token.php
// Description: Add auto_reply_rules table and verify_token/webhook_token to users

$pdo = getDB();

try {
    // 1. Add verify_token and webhook_token to users table if not exists
    $columns = $pdo->query("SHOW COLUMNS FROM users")->fetchAll(PDO::FETCH_COLUMN);

    if (!in_array('verify_token', $columns)) {
        $pdo->exec("ALTER TABLE users ADD COLUMN verify_token VARCHAR(100) DEFAULT NULL AFTER role");
        echo "Added verify_token column to users table.<br>";

        // Generate tokens for existing users
        $stmt = $pdo->query("SELECT id FROM users WHERE verify_token IS NULL");
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($users as $user) {
            $token = bin2hex(random_bytes(16));
            $pdo->prepare("UPDATE users SET verify_token = ? WHERE id = ?")->execute([$token, $user['id']]);
        }
        echo "Generated verify_tokens for existing users.<br>";
    }

    if (!in_array('webhook_token', $columns)) {
        $pdo->exec("ALTER TABLE users ADD COLUMN webhook_token VARCHAR(100) DEFAULT NULL AFTER verify_token");
        echo "Added webhook_token column to users table.<br>";

        // Generate tokens if needed
        $stmt = $pdo->query("SELECT id FROM users WHERE webhook_token IS NULL");
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($users as $user) {
            $token = bin2hex(random_bytes(16));
            $pdo->prepare("UPDATE users SET webhook_token = ? WHERE id = ?")->execute([$token, $user['id']]);
        }
        echo "Generated webhook_tokens for existing users.<br>";
    }

    // 2. Create auto_reply_rules table
    $sql = "CREATE TABLE IF NOT EXISTS auto_reply_rules (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        page_id VARCHAR(50) NOT NULL,
        keyword VARCHAR(255) NOT NULL,
        reply_text TEXT NOT NULL,
        match_type ENUM('exact', 'contains') DEFAULT 'contains',
        is_active TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX (user_id),
        INDEX (page_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

    $pdo->exec($sql);
    echo "Checked/Created auto_reply_rules table.<br>";

} catch (PDOException $e) {
    echo "Error in migration 020: " . $e->getMessage() . "<br>";
    throw $e;
}
?>