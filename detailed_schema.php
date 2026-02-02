<?php
require_once 'includes/db_config.php';
function describe($table, $pdo)
{
    echo "--- Table: $table ---\n";
    $stmt = $pdo->query("DESCRIBE $table");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo sprintf("%-20s | %s\n", $row['Field'], $row['Type']);
    }
}
describe('fb_leads', $pdo);
describe('fb_pages', $pdo);
