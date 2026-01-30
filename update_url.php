<?php
require_once 'includes/db.php';
$new_url = 'https://peaceful-numeric-utils-telecommunications.trycloudflare.com';

// Update the setting in the database
$stmt = $pdo->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'wa_evolution_url'");
$stmt->execute([$new_url]);

echo "<h1>Success! 🚀</h1>";
echo "<p>Evolution API URL has been updated in the database to:</p>";
echo "<code style='background:#eee;padding:5px;font-size:1.2em'>$new_url</code>";
echo "<br><br><p>You can now try adding a WhatsApp account from the dashboard.</p>";
