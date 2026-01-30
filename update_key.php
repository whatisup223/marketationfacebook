<?php
// update_api_key.php - Update the Global API Key
require_once 'includes/db.php';

$new_key = 'Di0qhYGOPFuz0Hz4w8LWsHc940gqE20V';

$stmt = $pdo->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'wa_evolution_api_key'");
if ($stmt->execute([$new_key])) {
    echo "<h1>✅ API Key Updated!</h1>";
    echo "<p>Key set to: $new_key</p>";
} else {
    echo "<h1>❌ Error</h1>";
}
