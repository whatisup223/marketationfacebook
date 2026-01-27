<?php
// Secure log viewer
// Only allow logged in admins
session_start();
if (!isset($_SESSION['user_id'])) {
    die("Access Denied");
}

$logFile = __DIR__ . '/debug_errors.txt';
$transLog = __DIR__ . '/transaction_log.txt';

echo '<body style="font-family:sans-serif; background:#f4f6f9; padding:20px;">';
echo "<h2 style='color:#2c3e50;'>🛡️ Server & Transaction Log Viewer</h2>";

// 1. Transaction Log
echo "<h3 style='color:#007bff;'>📝 API Transaction Log (Success/Fail details)</h3>";
if (!file_exists($transLog)) {
    echo "<p>No transactions logged yet.</p>";
} else {
    $tContent = file_get_contents($transLog);
    echo "<div style='background:white; padding:15px; border:1px solid #dcdcdc; border-radius:5px;'>";
    if (empty($tContent)) {
        echo "<em>Log is empty.</em>";
    } else {
        echo "<pre style='color:#333; overflow-x:auto; max-height:400px; font-size:12px;'>" . htmlspecialchars($tContent) . "</pre>";
    }
    echo "</div>";
}

// 2. Fatal Errors
echo "<h3 style='color:#dc3545;'>🚨 Fatal Server Errors (PHP Crashes)</h3>";
echo "<p>File: " . htmlspecialchars($logFile) . "</p>";

if (!file_exists($logFile)) {
    echo "<h4 style='color:green;'>✅ No fatal errors found.</h4>";
} else {
    $content = file_get_contents($logFile);
    echo "<div style='background:#fff3cd; padding:15px; border:1px solid #ffeeba; border-radius:5px;'>";
    if (empty($content)) {
        echo "<em>Error log is empty.</em>";
    } else {
        echo "<pre style='color:#856404; overflow-x:auto; max-height:400px;'>" . htmlspecialchars($content) . "</pre>";
    }
    echo "</div>";
}

// Clear Button
echo '<br><hr>';
echo '<form method="POST">';
echo '<button type="submit" name="clear" style="background:#dc3545; color:white; padding:10px 20px; border:none; border-radius:4px; cursor:pointer; font-weight:bold;">🗑️ Clear All Logs</button>';
echo '</form>';

if (isset($_POST['clear'])) {
    file_put_contents($logFile, '');
    file_put_contents($transLog, '');
    echo "<script>window.location.href=window.location.href;</script>";
}
echo '</body>';
?>