<?php
try {
    $pdo = new PDO("mysql:host=localhost;dbname=marketation_fb;charset=utf8mb4", "root", "", [
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
    ]);
    $stmt = $pdo->query("DESCRIBE auto_reply_rules");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "marketation_fb Columns: " . implode(", ", $columns);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
