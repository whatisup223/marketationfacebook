<?php
/**
 * Instagram Button Debug Script
 * Tests why buttons stop working after first click
 */

require_once __DIR__ . '/includes/functions.php';
header('Content-Type: text/html; charset=utf-8');

echo "<!DOCTYPE html><html><head><meta charset='utf-8'><title>Instagram Button Debug</title>";
echo "<style>body{font-family:monospace;background:#1a1a1a;color:#0f0;padding:20px}h2{color:#ff0}table{border-collapse:collapse;width:100%;margin:20px 0}th,td{border:1px solid #333;padding:8px;text-align:left}th{background:#333}.error{color:#f00}.success{color:#0f0}.warning{color:#ff0}pre{background:#222;padding:10px;overflow-x:auto}</style></head><body>";

echo "<h1>🔍 INSTAGRAM BUTTON DEBUG</h1>";

$pdo = getDB();

// 1. Check bot_audience table
echo "<h2>1️⃣ Bot Audience Table (24-Hour Window Tracking)</h2>";
try {
    $check = $pdo->query("SHOW TABLES LIKE 'bot_audience'");
    if ($check->fetch()) {
        echo "<p class='success'>✅ Table 'bot_audience' EXISTS</p>";

        // Show last 20 interactions
        $stmt = $pdo->query("SELECT * FROM bot_audience ORDER BY last_interaction_at DESC LIMIT 20");
        $audience = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (count($audience) > 0) {
            echo "<table><tr><th>Page ID</th><th>User ID</th><th>Last Interaction</th><th>Source</th><th>Window Open</th><th>Time Since</th></tr>";
            foreach ($audience as $a) {
                $timeSince = time() - strtotime($a['last_interaction_at']);
                $hours = floor($timeSince / 3600);
                $mins = floor(($timeSince % 3600) / 60);
                $windowStatus = ($timeSince < 86400) ? "<span class='success'>OPEN</span>" : "<span class='error'>CLOSED</span>";

                echo "<tr>";
                echo "<td>" . substr($a['page_id'], 0, 15) . "...</td>";
                echo "<td>" . substr($a['user_id'], 0, 15) . "...</td>";
                echo "<td>{$a['last_interaction_at']}</td>";
                echo "<td>{$a['source']}</td>";
                echo "<td>$windowStatus</td>";
                echo "<td>{$hours}h {$mins}m ago</td>";
                echo "</tr>";
            }
            echo "</table>";
        } else {
            echo "<p class='warning'>⚠️ No interactions tracked yet</p>";
        }
    } else {
        echo "<p class='error'>❌ Table 'bot_audience' DOES NOT EXIST!</p>";
        echo "<p>Creating table now...</p>";
        $pdo->exec("CREATE TABLE IF NOT EXISTS `bot_audience` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `page_id` VARCHAR(100) NOT NULL,
            `user_id` VARCHAR(100) NOT NULL,
            `last_interaction_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `source` ENUM('comment', 'message') DEFAULT 'message',
            `is_window_open` TINYINT(1) DEFAULT 1,
            UNIQUE KEY `page_user` (`page_id`, `user_id`),
            INDEX (`page_id`),
            INDEX (`user_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        echo "<p class='success'>✅ Table created!</p>";
    }
} catch (Exception $e) {
    echo "<p class='error'>❌ Error: " . $e->getMessage() . "</p>";
}

// 2. Check recent webhook events
echo "<h2>2️⃣ Recent Webhook Events</h2>";
if (file_exists(__DIR__ . '/debug_webhook.txt')) {
    $logs = file(__DIR__ . '/debug_webhook.txt');
    $recent = array_slice($logs, -20);
    echo "<pre>" . implode("", $recent) . "</pre>";
} else {
    echo "<p class='warning'>⚠️ No webhook debug log found</p>";
}

// 3. Check compliance logs
echo "<h2>3️⃣ Compliance Engine Logs</h2>";
if (file_exists(__DIR__ . '/debug_compliance.txt')) {
    $logs = file(__DIR__ . '/debug_compliance.txt');
    $recent = array_slice($logs, -20);
    echo "<pre>" . implode("", $recent) . "</pre>";
} else {
    echo "<p class='warning'>⚠️ No compliance debug log found</p>";
}

// 4. Check bot_sent_messages for rate limiting
echo "<h2>4️⃣ Recent Bot Messages (Rate Limit Check)</h2>";
try {
    $stmt = $pdo->query("SELECT * FROM bot_sent_messages ORDER BY created_at DESC LIMIT 20");
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($messages) > 0) {
        echo "<table><tr><th>Page ID</th><th>User ID</th><th>Message ID</th><th>Platform</th><th>Created At</th></tr>";
        foreach ($messages as $m) {
            echo "<tr>";
            echo "<td>" . substr($m['page_id'], 0, 15) . "...</td>";
            echo "<td>" . substr($m['user_id'], 0, 15) . "...</td>";
            echo "<td>" . substr($m['message_id'], 0, 20) . "...</td>";
            echo "<td>{$m['platform']}</td>";
            echo "<td>{$m['created_at']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p class='warning'>⚠️ No messages sent yet</p>";
    }
} catch (Exception $e) {
    echo "<p class='error'>❌ Error: " . $e->getMessage() . "</p>";
}

// 5. Test scenario
echo "<h2>5️⃣ Test Scenario</h2>";
echo "<p>To test Instagram buttons:</p>";
echo "<ol>";
echo "<li>Send a message to your Instagram page</li>";
echo "<li>Click a button in the reply</li>";
echo "<li>Refresh this page to see the logs</li>";
echo "<li>Check if the 24-hour window is OPEN</li>";
echo "<li>Check if there are any compliance blocks</li>";
echo "</ol>";

echo "<h2>✅ Debug Complete</h2>";
echo "<p><a href='debug_handover.php' style='color:#0ff'>← Back to Handover Debug</a></p>";
echo "</body></html>";
