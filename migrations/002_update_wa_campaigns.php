<?php
// Migration: Add Batching and Rotation columns to wa_campaigns table
echo "Checking wa_campaigns table structure...\n";

// Ensure DB connection is available
if (!isset($pdo)) {
    $pdo = getDB();
}

$columns = [
    'batch_size' => 'INT DEFAULT 50',
    'batch_delay' => 'INT DEFAULT 60',
    'switch_every' => 'INT DEFAULT 10', // Ensure this exists too as column
    'current_account_index' => 'INT DEFAULT 0'
];

foreach ($columns as $col => $def) {
    try {
        $pdo->query("SELECT $col FROM wa_campaigns LIMIT 1");
        echo "Column '$col' already exists - OK.\n";
    } catch (PDOException $e) {
        try {
            $pdo->exec("ALTER TABLE wa_campaigns ADD COLUMN $col $def");
            echo "Added column '$col' successfully.\n";
        } catch (PDOException $e2) {
            echo "Error adding column '$col': " . $e2->getMessage() . "\n";
        }
    }
}
?>