<?php
// Secure log viewer
// Only allow logged in admins
session_start();
if (!isset($_SESSION['user_id'])) {
    die("Access Denied");
}

$logFile = __DIR__ . '/debug_errors.txt';

echo "<h2>Server Error Log Viewer</h2>";
echo "<p>File: " . htmlspecialchars($logFile) . "</p>";
echo "<hr>";

if (!file_exists($logFile)) {
    echo "<h3>✅ No errors found (File does not exist yet).</h3>";
    echo "<p>Try running the campaign again to generate errors.</p>";
} else {
    $content = file_get_contents($logFile);
    if (empty($content)) {
        echo "<h3>✅ Log file is empty.</h3>";
    } else {
        echo "<pre style='background:#f8d7da; padding:15px; border:1px solid #f5c6cb; border-radius:5px; color:#721c24; overflow-x:auto;'>" . htmlspecialchars($content) . "</pre>";

        // Add Clear Button
        echo '<form method="POST">';
        echo '<button type="submit" name="clear" style="background:red;color:white;padding:10px;border:none;cursor:pointer;">🗑️ Clear Log</button>';
        echo '</form>';

        if (isset($_POST['clear'])) {
            file_put_contents($logFile, ''); // Empty file
            echo "<script>window.location.href=window.location.href;</script>"; // Reload
        }
    }
}
?>