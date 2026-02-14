<?php
require_once 'includes/db_config.php';
$stmt = $pdo->query("DESCRIBE fb_pages");
$columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
$out = print_r($columns, true);
file_put_contents('schema.txt', $out);
