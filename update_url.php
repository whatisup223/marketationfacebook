<?php
// update_url.php - Force update for Evolution URL
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Load the functions file which contains DB connection logic
// Assuming this file is in the root directory, and functions.php is in includes/
if (file_exists(__DIR__ . '/includes/functions.php')) {
    require_once __DIR__ . '/includes/functions.php';
} elseif (file_exists(__DIR__ . '/../includes/functions.php')) {
    require_once __DIR__ . '/../includes/functions.php';
} else {
    die("Error: Could not find includes/functions.php");
}

try {
    // Get DB connection using the project's native function
    $pdo = getDB();

    $new_url = 'https://peaceful-numeric-utils-telecommunications.trycloudflare.com';

    // 1. Update Settings Table
    $stmt = $pdo->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'wa_evolution_url'");
    if ($stmt->execute([$new_url])) {
        echo "<h1 style='color:green'>✅ Database Updated Successfully</h1>";
        echo "<p>New URL set to: <strong>$new_url</strong></p>";
        echo "<p>Go back to <a href='admin/settings.php'>Admin Settings</a> and verify.</p>";
    } else {
        echo "<h1 style='color:red'>❌ Error Updating Database</h1>";
        print_r($stmt->errorInfo());
    }

} catch (Exception $e) {
    echo "<h1>Error: " . $e->getMessage() . "</h1>";
}
