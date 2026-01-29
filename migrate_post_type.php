<?php
require_once __DIR__ . '/includes/functions.php';
$pdo = getDB();
try {
    $pdo->exec("ALTER TABLE fb_scheduled_posts ADD COLUMN post_type VARCHAR(20) DEFAULT 'feed' AFTER id");
    echo "Column added successfully\n";
} catch (Exception $e) {
    echo "Error or column already exists: " . $e->getMessage() . "\n";
}
