<?php
/**
 * Enable detailed webhook debugging
 */
require_once __DIR__ . '/includes/functions.php';

$pdo = getDB();

// Enable debug logging temporarily
$debugContent = '<?php
function debugLog($msg)
{
    $logFile = __DIR__ . \'/MASTER_DEBUG.log\';
    $timestamp = date(\'Y-m-d H:i:s\');
    $content = (is_array($msg) || is_object($msg)) ? json_encode($msg, JSON_UNESCAPED_UNICODE) : $msg;
    file_put_contents($logFile, "[$timestamp] $content\n", FILE_APPEND);
}
';

file_put_contents(__DIR__ . '/webhook.php', str_replace(
    'function debugLog($msg)
{
    // Production: Disabled for performance. Use MASTER_DEBUG.log only when needed for debugging.
    /*
    $logFile = __DIR__ . \'/MASTER_DEBUG.log\';
    $timestamp = date(\'Y-m-d H:i:s\');
    $content = (is_array($msg) || is_object($msg)) ? json_encode($msg, JSON_UNESCAPED_UNICODE) : $msg;
    file_put_contents($logFile, "[$timestamp] $content\n", FILE_APPEND);
    */
}',
    'function debugLog($msg)
{
    $logFile = __DIR__ . \'/MASTER_DEBUG.log\';
    $timestamp = date(\'Y-m-d H:i:s\');
    $content = (is_array($msg) || is_object($msg)) ? json_encode($msg, JSON_UNESCAPED_UNICODE) : $msg;
    file_put_contents($logFile, "[$timestamp] $content\n", FILE_APPEND);
}',
    file_get_contents(__DIR__ . '/webhook.php')
));

echo "✅ Debug logging ENABLED!\n\n";
echo "Now:\n";
echo "1. Send a test message to Instagram\n";
echo "2. Click a button\n";
echo "3. Check MASTER_DEBUG.log file\n";
echo "4. Look for the exact point where it stops\n\n";
echo "To view logs: https://yoursite.com/MASTER_DEBUG.log\n";
