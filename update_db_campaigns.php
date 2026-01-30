<?php
// update_db_campaigns.php (Final Fix)
// Try to locate functions.php which connects to DB
$paths = [
    __DIR__ . '/includes/functions.php',
    __DIR__ . '/../includes/functions.php',
    'includes/functions.php'
];

$loaded = false;
foreach ($paths as $path) {
    if (file_exists($path)) {
        require_once $path;
        $loaded = true;
        break;
    }
}

if (!$loaded) {
    die("Could not find functions.php\n");
}

$pdo = getDB();

$columns = [
    'batch_size' => 'INT DEFAULT 50',
    'batch_delay' => 'INT DEFAULT 60'
];

foreach ($columns as $col => $def) {
    try {
        $pdo->query("SELECT $col FROM wa_campaigns LIMIT 1");
        echo "Column '$col' already exists.\n";
    } catch (PDOException $e) {
        $pdo->exec("ALTER TABLE wa_campaigns ADD COLUMN $col $def");
        echo "Added column '$col'.\n";
    }
}
echo "Done.\n";
