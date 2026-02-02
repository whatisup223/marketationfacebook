<?php
require 'includes/db_config.php';
try {
    $pdo->exec("ALTER TABLE users ADD COLUMN smtp_config TEXT DEFAULT NULL");
    echo "Column smtp_config added successfully.\n";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
        echo "Column smtp_config already exists.\n";
    } else {
        echo "Error: " . $e->getMessage() . "\n";
    }
}
?>