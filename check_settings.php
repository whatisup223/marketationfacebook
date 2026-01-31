<?php
require_once __DIR__ . '/includes/db_config.php';
$stmt = $pdo->query("SELECT page_id, page_name, bot_cooldown_seconds, bot_exclude_keywords FROM fb_pages");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $row) {
    echo "Page: {$row['page_name']} ({$row['page_id']}) | Cooldown: {$row['bot_cooldown_seconds']} | Exclude: " . (isset($row['bot_exclude_keywords']) ? $row['bot_exclude_keywords'] : 'NULL') . "\n";
}
