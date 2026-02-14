<?php
/**
 * HANDOVER DEBUG SCRIPT
 * This script tests the handover system and shows detailed debug info
 */

require_once __DIR__ . '/includes/functions.php';
header('Content-Type: text/html; charset=utf-8');

echo "<!DOCTYPE html><html><head><meta charset='utf-8'><title>Handover Debug</title>";
echo "<style>body{font-family:monospace;background:#1a1a1a;color:#0f0;padding:20px}h2{color:#ff0}table{border-collapse:collapse;width:100%;margin:20px 0}th,td{border:1px solid #333;padding:8px;text-align:left}th{background:#333}.error{color:#f00}.success{color:#0f0}.warning{color:#ff0}</style></head><body>";

echo "<h1>🔍 HANDOVER SYSTEM DEBUG</h1>";

$pdo = getDB();

// 1. Check if table exists
echo "<h2>1️⃣ Table Check</h2>";
try {
    $check = $pdo->query("SHOW TABLES LIKE 'bot_conversation_states'");
    if ($check->fetch()) {
        echo "<p class='success'>✅ Table 'bot_conversation_states' EXISTS</p>";

        // Show columns
        $cols = $pdo->query("SHOW COLUMNS FROM bot_conversation_states")->fetchAll(PDO::FETCH_ASSOC);
        echo "<table><tr><th>Column</th><th>Type</th><th>Default</th></tr>";
        foreach ($cols as $col) {
            echo "<tr><td>{$col['Field']}</td><td>{$col['Type']}</td><td>{$col['Default']}</td></tr>";
        }
        echo "</table>";
    } else {
        echo "<p class='error'>❌ Table 'bot_conversation_states' DOES NOT EXIST!</p>";
    }
} catch (Exception $e) {
    echo "<p class='error'>❌ Error: " . $e->getMessage() . "</p>";
}

// 2. Check fb_pages columns
echo "<h2>2️⃣ FB Pages AI Columns Check</h2>";
try {
    $cols = $pdo->query("SHOW COLUMNS FROM fb_pages")->fetchAll(PDO::FETCH_COLUMN);
    $required = ['bot_ai_sentiment_enabled', 'bot_anger_keywords', 'bot_repetition_threshold', 'bot_handover_reply'];

    echo "<table><tr><th>Column</th><th>Status</th></tr>";
    foreach ($required as $col) {
        $exists = in_array($col, $cols);
        $status = $exists ? "<span class='success'>✅ EXISTS</span>" : "<span class='error'>❌ MISSING</span>";
        echo "<tr><td>$col</td><td>$status</td></tr>";
    }
    echo "</table>";
} catch (Exception $e) {
    echo "<p class='error'>❌ Error: " . $e->getMessage() . "</p>";
}

// 3. Show current handover conversations
echo "<h2>3️⃣ Active Handover Conversations</h2>";
try {
    $stmt = $pdo->query("SELECT * FROM bot_conversation_states WHERE conversation_state = 'handover' ORDER BY updated_at DESC LIMIT 10");
    $handovers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($handovers) > 0) {
        echo "<p class='warning'>⚠️ Found " . count($handovers) . " active handovers</p>";
        echo "<table><tr><th>ID</th><th>Page ID</th><th>User ID</th><th>Platform</th><th>Source</th><th>Anger</th><th>Repeat</th><th>Last Message</th><th>Updated</th></tr>";
        foreach ($handovers as $h) {
            echo "<tr>";
            echo "<td>{$h['id']}</td>";
            echo "<td>{$h['page_id']}</td>";
            echo "<td>{$h['user_id']}</td>";
            echo "<td>{$h['platform']}</td>";
            echo "<td>{$h['reply_source']}</td>";
            echo "<td>" . ($h['is_anger_detected'] ? '🔥 YES' : 'No') . "</td>";
            echo "<td>{$h['repeat_count']}</td>";
            echo "<td>" . substr($h['last_user_message'], 0, 50) . "...</td>";
            echo "<td>{$h['updated_at']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p class='success'>✅ No active handovers (system is clean)</p>";
    }
} catch (Exception $e) {
    echo "<p class='error'>❌ Error: " . $e->getMessage() . "</p>";
}

// 4. Show all conversation states
echo "<h2>4️⃣ All Conversation States (Last 20)</h2>";
try {
    $stmt = $pdo->query("SELECT * FROM bot_conversation_states ORDER BY updated_at DESC LIMIT 20");
    $states = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($states) > 0) {
        echo "<p>Total records: " . count($states) . "</p>";
        echo "<table><tr><th>ID</th><th>Page</th><th>User</th><th>Platform</th><th>Source</th><th>State</th><th>Anger</th><th>Repeat</th><th>Updated</th></tr>";
        foreach ($states as $s) {
            $stateColor = $s['conversation_state'] === 'handover' ? 'error' : ($s['conversation_state'] === 'resolved' ? 'success' : 'warning');
            echo "<tr>";
            echo "<td>{$s['id']}</td>";
            echo "<td>" . substr($s['page_id'], 0, 15) . "...</td>";
            echo "<td>" . substr($s['user_id'], 0, 15) . "...</td>";
            echo "<td>{$s['platform']}</td>";
            echo "<td>{$s['reply_source']}</td>";
            echo "<td class='$stateColor'>{$s['conversation_state']}</td>";
            echo "<td>" . ($s['is_anger_detected'] ? '🔥' : '-') . "</td>";
            echo "<td>{$s['repeat_count']}</td>";
            echo "<td>{$s['updated_at']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p class='warning'>⚠️ No conversation states found (table is empty)</p>";
    }
} catch (Exception $e) {
    echo "<p class='error'>❌ Error: " . $e->getMessage() . "</p>";
}

// 5. Check page settings
echo "<h2>5️⃣ Page AI Settings</h2>";
try {
    $stmt = $pdo->query("SELECT page_id, page_name, ig_business_id, ig_username, bot_ai_sentiment_enabled, bot_anger_keywords, bot_repetition_threshold, bot_handover_reply FROM fb_pages LIMIT 10");
    $pages = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($pages) > 0) {
        echo "<table><tr><th>Page Name</th><th>Platform</th><th>AI Enabled</th><th>Anger Keywords</th><th>Threshold</th><th>Handover Reply</th></tr>";
        foreach ($pages as $p) {
            // FB Row
            echo "<tr>";
            echo "<td>{$p['page_name']}</td>";
            echo "<td>Facebook</td>";
            echo "<td>" . ($p['bot_ai_sentiment_enabled'] ? '✅' : '❌') . "</td>";
            echo "<td>" . (empty($p['bot_anger_keywords']) ? '<span class="error">EMPTY</span>' : substr($p['bot_anger_keywords'], 0, 30)) . "</td>";
            echo "<td>{$p['bot_repetition_threshold']}</td>";
            echo "<td>" . (empty($p['bot_handover_reply']) ? '<span class="error">EMPTY</span>' : substr($p['bot_handover_reply'], 0, 30)) . "</td>";
            echo "</tr>";

            // IG Row (if exists)
            if (!empty($p['ig_business_id'])) {
                echo "<tr>";
                echo "<td>{$p['ig_username']}</td>";
                echo "<td>Instagram</td>";
                echo "<td>" . ($p['bot_ai_sentiment_enabled'] ? '✅' : '❌') . "</td>";
                echo "<td>" . (empty($p['bot_anger_keywords']) ? '<span class="error">EMPTY</span>' : substr($p['bot_anger_keywords'], 0, 30)) . "</td>";
                echo "<td>{$p['bot_repetition_threshold']}</td>";
                echo "<td>" . (empty($p['bot_handover_reply']) ? '<span class="error">EMPTY</span>' : substr($p['bot_handover_reply'], 0, 30)) . "</td>";
                echo "</tr>";
            }
        }
        echo "</table>";
    }
} catch (Exception $e) {
    echo "<p class='error'>❌ Error: " . $e->getMessage() . "</p>";
}

// 6. Test webhook logic
echo "<h2>6️⃣ Webhook Logic Test</h2>";
echo "<p>Testing if webhook.php has handover detection code...</p>";
$webhookContent = file_get_contents(__DIR__ . '/webhook.php');
$hasAngerDetection = strpos($webhookContent, 'bot_anger_keywords') !== false;
$hasRepeatDetection = strpos($webhookContent, 'repeat_count') !== false;
$hasHandoverInsert = strpos($webhookContent, "conversation_state = 'handover'") !== false;

echo "<table><tr><th>Check</th><th>Status</th></tr>";
echo "<tr><td>Anger Detection Code</td><td>" . ($hasAngerDetection ? "<span class='success'>✅ FOUND</span>" : "<span class='error'>❌ MISSING</span>") . "</td></tr>";
echo "<tr><td>Repeat Detection Code</td><td>" . ($hasRepeatDetection ? "<span class='success'>✅ FOUND</span>" : "<span class='error'>❌ MISSING</span>") . "</td></tr>";
echo "<tr><td>Handover State Insert</td><td>" . ($hasHandoverInsert ? "<span class='success'>✅ FOUND</span>" : "<span class='error'>❌ MISSING</span>") . "</td></tr>";
echo "</table>";

echo "<h2>✅ Debug Complete</h2>";
echo "<p>Check the results above to identify the issue.</p>";
echo "</body></html>";
