<?php
// backups/generate_full_dump.php
require_once __DIR__ . '/../includes/db_config.php';

$backupFile = __DIR__ . '/pre_cleanup_backup_' . date('Y_m_d_H_i') . '.sql';

echo "Starting DB Backup...\n";

try {
    // This is a simplified dump script using PHP
    $handle = fopen($backupFile, 'w');
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

    foreach ($tables as $table) {
        // Create Table
        $res = $pdo->query("SHOW CREATE TABLE `$table`")->fetch(PDO::FETCH_ASSOC);
        fwrite($handle, "\n\n" . $res['Create Table'] . ";\n\n");

        // Dump Data
        $rows = $pdo->query("SELECT * FROM `$table`")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $row) {
            $keys = array_keys($row);
            $values = array_values($row);
            $values = array_map(function ($v) use ($pdo) {
                return $v === null ? 'NULL' : $pdo->quote($v);
            }, $values);

            $sql = "INSERT INTO `$table` (`" . implode("`, `", $keys) . "`) VALUES (" . implode(", ", $values) . ");\n";
            fwrite($handle, $sql);
        }
    }
    fclose($handle);
    echo "SUCCESS: Backup created at $backupFile\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
?>