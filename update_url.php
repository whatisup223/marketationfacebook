<?php
// update_url.php - Force update for Evolution URL
require_once 'includes/db.php';

$new_url = 'https://peaceful-numeric-utils-telecommunications.trycloudflare.com';

// 1. Update Settings Table
$stmt = $pdo->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'wa_evolution_url'");
if ($stmt->execute([$new_url])) {
    echo "<h1>✅ Database Updated Successfully</h1>";
    echo "<p>New URL: <strong>$new_url</strong></p>";
} else {
    echo "<h1>❌ Error Updating Database</h1>";
    print_r($stmt->errorInfo());
}
