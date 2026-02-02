<?php
require_once 'functions.php';
$pdo = getDB();

try {
    // Check if column exists
    $stmt = $pdo->query("SHOW COLUMNS FROM auto_reply_rules LIKE 'private_reply_enabled'");
    if ($stmt->rowCount() == 0) {
        $pdo->exec("ALTER TABLE auto_reply_rules ADD COLUMN private_reply_enabled TINYINT(1) DEFAULT 0");
        echo "Added private_reply_enabled column.\n";
    } else {
        echo "private_reply_enabled already exists.\n";
    }

    $stmt = $pdo->query("SHOW COLUMNS FROM auto_reply_rules LIKE 'private_reply_text'");
    if ($stmt->rowCount() == 0) {
        $pdo->exec("ALTER TABLE auto_reply_rules ADD COLUMN private_reply_text TEXT NULL");
        echo "Added private_reply_text column.\n";
    } else {
        echo "private_reply_text already exists.\n";
    }

    echo "Database updated successfully.";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>