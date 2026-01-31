<?php
require_once __DIR__ . '/includes/functions.php';
$pdo = getDB();
$stmt = $pdo->query("SELECT id, status, content, error_message, media_url, post_type FROM fb_scheduled_posts ORDER BY id DESC LIMIT 10");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $row) {
    echo "ID: {$row['id']} | Status: {$row['status']} | Type: {$row['post_type']}\n";
    echo "Content: " . substr($row['content'], 0, 50) . "\n";
    echo "Error: {$row['error_message']}\n";
    echo "Media: {$row['media_url']}\n";
    echo "-----------------------------------\n";
}
