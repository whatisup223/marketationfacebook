<?php
require_once 'includes/db_config.php';
$stmt = $pdo->query("SHOW INDEX FROM fb_leads");
$indices = $stmt->fetchAll(PDO::FETCH_ASSOC);
file_put_contents('indices_output.json', json_encode($indices, JSON_PRETTY_PRINT));
print_r($indices);
