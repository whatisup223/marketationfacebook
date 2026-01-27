<?php
require_once __DIR__ . '/includes/db_config.php';

echo "FULL DATABASE INVENTORY\n";
echo "=======================\n";

try {
    $tablesStmt = $pdo->query("SHOW TABLES");
    $tables = $tablesStmt->fetchAll(PDO::FETCH_COLUMN);

    foreach ($tables as $table) {
        echo "\n[TABLE] : $table\n";
        $colsStmt = $pdo->query("DESCRIBE `$table` ");
        while ($col = $colsStmt->fetch(PDO::FETCH_ASSOC)) {
            echo "  - {$col['Field']} | Type: {$col['Type']} | Null: {$col['Null']} | Default: {$col['Default']}\n";
        }

        // Count rows for context
        $count = $pdo->query("SELECT COUNT(*) FROM `$table` ")->fetchColumn();
        echo "  > Total Rows: $count\n";
    }

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage();
}
?>