<?php
require_once __DIR__ . '/includes/functions.php';

try {
    $pdo = getDB();

    // Check if verify_token exists
    $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'verify_token'");
    if (!$stmt->fetch()) {
        $pdo->exec("ALTER TABLE users ADD COLUMN verify_token VARCHAR(100) DEFAULT NULL");
        echo "Column verify_token added to users table.<br>";

        // Populate existing users: verify_token can be same as webhook_token initially or new random
        $stmt = $pdo->query("SELECT id, webhook_token FROM users");
        while ($row = $stmt->fetch()) {
            // Generate a separate random token for verification
            $v_token = bin2hex(random_bytes(16));
            $update = $pdo->prepare("UPDATE users SET verify_token = ? WHERE id = ?");
            $update->execute([$v_token, $row['id']]);
            echo "Generated verify_token for user " . $row['id'] . "<br>";
        }
    } else {
        echo "Column verify_token already exists.<br>";
    }

    echo "Done.";

} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}
?>