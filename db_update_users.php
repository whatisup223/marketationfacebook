<?php
require_once __DIR__ . '/includes/functions.php';

try {
    $pdo = getDB();

    // Check if column exists
    $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'webhook_token'");
    if (!$stmt->fetch()) {
        $pdo->exec("ALTER TABLE users ADD COLUMN webhook_token VARCHAR(100) DEFAULT NULL");
        echo "Column webhook_token added to users table.<br>";
    } else {
        echo "Column webhook_token already exists.<br>";
    }

    // Generate tokens for existing users using 'id'
    $stmt = $pdo->query("SELECT id FROM users WHERE webhook_token IS NULL OR webhook_token = ''");
    while ($row = $stmt->fetch()) {
        $token = bin2hex(random_bytes(16));
        $update = $pdo->prepare("UPDATE users SET webhook_token = ? WHERE id = ?");
        $update->execute([$token, $row['id']]);
        echo "Generated token for user " . $row['id'] . "<br>";
    }

    echo "Done.";

} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}
?>