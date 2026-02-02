<?php
require_once 'functions.php';
$pdo = getDB();

try {
    // Check if auto_like_comment column exists
    $stmt = $pdo->query("SHOW COLUMNS FROM auto_reply_rules LIKE 'auto_like_comment'");
    if ($stmt->rowCount() == 0) {
        $pdo->exec("ALTER TABLE auto_reply_rules ADD COLUMN auto_like_comment TINYINT(1) DEFAULT 0");
        echo "Added auto_like_comment column.\n";
    } else {
        echo "auto_like_comment already exists.\n";
    }

    echo "Database updated successfully.";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>