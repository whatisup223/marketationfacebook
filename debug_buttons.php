<?php
/**
 * Check Button Data in Rules
 */
require_once __DIR__ . '/includes/functions.php';
header('Content-Type: text/html; charset=utf-8');

echo "<!DOCTYPE html><html><head><meta charset='utf-8'><title>Button Data Debug</title>";
echo "<style>body{font-family:monospace;background:#1a1a1a;color:#0f0;padding:20px}h2{color:#ff0}pre{background:#222;padding:10px;overflow-x:auto;color:#0ff}.error{color:#f00}.success{color:#0f0}.warning{color:#ff0}</style></head><body>";

echo "<h1>🔍 BUTTON DATA DEBUG</h1>";

$pdo = getDB();

// Get the specific rule
$stmt = $pdo->prepare("SELECT * FROM auto_reply_rules WHERE id = 48");
$stmt->execute();
$rule = $stmt->fetch(PDO::FETCH_ASSOC);

if ($rule) {
    echo "<h2>Rule ID: 48 (الحملات الاعلانيه)</h2>";
    echo "<p><strong>Keywords:</strong> {$rule['keywords']}</p>";
    echo "<p><strong>Reply Message:</strong></p>";
    echo "<pre>" . htmlspecialchars($rule['reply_message']) . "</pre>";

    echo "<h2>Button Data (reply_buttons column):</h2>";
    echo "<pre>" . htmlspecialchars($rule['reply_buttons']) . "</pre>";

    echo "<h2>Decoded Button Data:</h2>";
    $buttons = json_decode($rule['reply_buttons'], true);
    if (json_last_error() === JSON_ERROR_NONE) {
        echo "<pre>" . print_r($buttons, true) . "</pre>";

        if (is_array($buttons) && count($buttons) > 0) {
            echo "<p class='success'>✅ Buttons are valid JSON array!</p>";
            echo "<p>Button count: " . count($buttons) . "</p>";
        } else {
            echo "<p class='error'>❌ Buttons are NOT a valid array!</p>";
        }
    } else {
        echo "<p class='error'>❌ JSON DECODE ERROR: " . json_last_error_msg() . "</p>";
    }

    // Check default rule too
    echo "<h2>Default Rule (ID: 44) Buttons:</h2>";
    $stmt2 = $pdo->prepare("SELECT * FROM auto_reply_rules WHERE id = 44");
    $stmt2->execute();
    $default_rule = $stmt2->fetch(PDO::FETCH_ASSOC);

    if ($default_rule) {
        echo "<pre>" . htmlspecialchars($default_rule['reply_buttons']) . "</pre>";

        $def_buttons = json_decode($default_rule['reply_buttons'], true);
        if (json_last_error() === JSON_ERROR_NONE) {
            echo "<h3>Decoded:</h3>";
            echo "<pre>" . print_r($def_buttons, true) . "</pre>";
        }
    }

} else {
    echo "<p class='error'>❌ Rule not found!</p>";
}

echo "<h2>✅ Debug Complete</h2>";
echo "</body></html>";
