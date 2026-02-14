<?php
require_once 'includes/db_config.php';

try {
    $pdo->exec("ALTER TABLE fb_pages 
                ADD COLUMN ig_business_id VARCHAR(50) DEFAULT NULL,
                ADD COLUMN ig_username VARCHAR(255) DEFAULT NULL,
                ADD COLUMN ig_profile_picture TEXT DEFAULT NULL");
    echo "Database updated successfully.\n";
} catch (PDOException $e) {
    echo "Error updating database: " . $e->getMessage() . "\n";
}
