<?php
require_once 'includes/db_config.php';
$stmt = $pdo->query("DESCRIBE fb_leads");
$columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
file_put_contents('schema_output.json', json_encode($columns, JSON_PRETTY_PRINT));
print_r($columns);
