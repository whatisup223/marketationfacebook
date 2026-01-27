<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>🔧 System Diagnostics Check</h2>";

// 1. Check File Permissions
$logFile = __DIR__ . '/debug_errors.txt';
echo "Checking File Write Permissions... ";
if (is_writable(__DIR__)) {
    echo "<span style='color:green; font-weight:bold;'>PASS (Writable)</span><br>";
    // Try writing a test line
    file_put_contents($logFile, date('Y-m-d H:i:s') . " - WRITE TEST OK\n", FILE_APPEND);
    echo "Test write to 'debug_errors.txt'... <span style='color:green;'>OK</span><br>";
} else {
    echo "<span style='color:red; font-weight:bold;'>FAIL (Not Writable)</span> - Logs will not appear!<br>";
}

// 2. Check Database
echo "Checking Database Connection... ";
try {
    require_once __DIR__ . '/../includes/functions.php';
    if (isset($pdo) && $pdo) {
        echo "<span style='color:green; font-weight:bold;'>PASS</span><br>";
    } else {
        echo "<span style='color:red; font-weight:bold;'>FAIL (PDO var not set)</span><br>";
    }
} catch (Exception $e) {
    echo "<span style='color:red; font-weight:bold;'>FAIL: " . $e->getMessage() . "</span><br>";
}

// 3. Check Facebook API Class
echo "Checking FacebookAPI Class... ";
try {
    if (!class_exists('FacebookAPI')) {
        require_once __DIR__ . '/../includes/facebook_api.php';
    }

    if (class_exists('FacebookAPI')) {
        $fb = new FacebookAPI();
        echo "<span style='color:green; font-weight:bold;'>PASS (Class Loaded)</span><br>";

        // Check methods
        if (method_exists($fb, 'sendMessage')) {
            echo "Method 'sendMessage'... <span style='color:green;'>FOUND</span><br>";
        } else {
            echo "Method 'sendMessage'... <span style='color:red;'>MISSING</span><br>";
        }
    } else {
        echo "<span style='color:red; font-weight:bold;'>FAIL (Class Not Found)</span><br>";
    }
} catch (Throwable $e) {
    echo "<span style='color:red; font-weight:bold;'>CRASH: " . $e->getMessage() . "</span><br>";
}

echo "<hr><h3>Done.</h3>";
?>