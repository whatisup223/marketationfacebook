<?php
require_once 'includes/db_config.php';
try {
    $pdo->exec("ALTER TABLE fb_leads ADD COLUMN lead_source VARCHAR(20) DEFAULT 'messenger' AFTER fb_user_name");
    $pdo->exec("ALTER TABLE fb_leads ADD COLUMN post_id VARCHAR(100) DEFAULT NULL AFTER lead_source");
    echo "Columns added successfully";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
