<?php
// cleanup_logs.php - Delete unwanted log files from the server
error_reporting(E_ALL);
ini_set('display_errors', 1);

$files_to_delete = [
    'MASTER_DEBUG.log',
    'debug.log',
    'includes/settings_debug.log',
    'user/cron_debug.txt',
    'enable_debug.php',
    'fix_anger_keywords.php',
    'fix_handover_table.php',
    'debug_buttons.php',
    'debug_handover.php',
    'debug_instagram_buttons.php',
    'debug_instagram_rules.php'
];

echo "<h2>Log Cleanup Tool</h2>";
echo "<ul>";

foreach ($files_to_delete as $file) {
    if (file_exists($file)) {
        if (unlink($file)) {
            echo "<li style='color:green'>Deleted: $file</li>";
        } else {
            echo "<li style='color:red'>Failed to delete: $file (Check permissions)</li>";
        }
    } else {
        echo "<li style='color:gray'>Not found: $file</li>";
    }
}

echo "</ul>";
echo "<p>Cleanup complete. This script will now delete itself.</p>";

// Delete self
unlink(__FILE__);
?>