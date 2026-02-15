<?php
/**
 * Check Auto Reply Rules for Instagram
 */
require_once __DIR__ . '/includes/functions.php';
header('Content-Type: text/html; charset=utf-8');

echo "<!DOCTYPE html><html><head><meta charset='utf-8'><title>Instagram Rules Debug</title>";
echo "<style>body{font-family:monospace;background:#1a1a1a;color:#0f0;padding:20px}h2{color:#ff0}table{border-collapse:collapse;width:100%;margin:20px 0}th,td{border:1px solid #333;padding:8px;text-align:left}th{background:#333}.error{color:#f00}.success{color:#0f0}.warning{color:#ff0}pre{background:#222;padding:10px;overflow-x:auto}</style></head><body>";

echo "<h1>🔍 INSTAGRAM AUTO REPLY RULES DEBUG</h1>";

$pdo = getDB();

// Get Instagram pages
echo "<h2>1️⃣ Instagram Pages</h2>";
$stmt = $pdo->query("SELECT page_id, page_name, ig_business_id, ig_username FROM fb_pages WHERE ig_business_id IS NOT NULL AND ig_business_id != ''");
$pages = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (count($pages) > 0) {
    echo "<table><tr><th>Page Name</th><th>FB Page ID</th><th>IG Business ID</th><th>IG Username</th></tr>";
    foreach ($pages as $p) {
        echo "<tr>";
        echo "<td>{$p['page_name']}</td>";
        echo "<td>{$p['page_id']}</td>";
        echo "<td>{$p['ig_business_id']}</td>";
        echo "<td>{$p['ig_username']}</td>";
        echo "</tr>";

        // Get rules for this page
        echo "<tr><td colspan='4'>";
        echo "<h3>Rules for {$p['page_name']} (Instagram Messenger)</h3>";

        $stmt2 = $pdo->prepare("SELECT * FROM auto_reply_rules WHERE page_id = ? AND platform = 'instagram' AND reply_source = 'message' ORDER BY trigger_type DESC, id DESC");
        $stmt2->execute([$p['ig_business_id']]);
        $rules = $stmt2->fetchAll(PDO::FETCH_ASSOC);

        if (count($rules) > 0) {
            echo "<table style='width:100%;margin:10px 0'><tr><th>ID</th><th>Type</th><th>Keywords</th><th>Reply</th><th>Active</th><th>Buttons</th></tr>";
            foreach ($rules as $r) {
                $active = $r['is_active'] ? '<span class="success">✅ YES</span>' : '<span class="error">❌ NO</span>';
                $buttons = !empty($r['reply_buttons']) ? '✅ YES' : '❌ NO';
                echo "<tr>";
                echo "<td>{$r['id']}</td>";
                echo "<td>{$r['trigger_type']}</td>";
                echo "<td>" . substr($r['keywords'], 0, 50) . "...</td>";
                echo "<td>" . substr($r['reply_message'], 0, 50) . "...</td>";
                echo "<td>$active</td>";
                echo "<td>$buttons</td>";
                echo "</tr>";
            }
            echo "</table>";
        } else {
            echo "<p class='warning'>⚠️ No rules found for Instagram Messenger!</p>";
        }

        echo "</td></tr>";
    }
    echo "</table>";
} else {
    echo "<p class='error'>❌ No Instagram pages found!</p>";
}

// Test keyword matching
echo "<h2>2️⃣ Test Keyword Matching</h2>";
echo "<p>Testing if '2' matches any keyword...</p>";

$test_text = "2";
$found = false;

foreach ($pages as $p) {
    $stmt = $pdo->prepare("SELECT * FROM auto_reply_rules WHERE page_id = ? AND platform = 'instagram' AND reply_source = 'message' AND is_active = 1");
    $stmt->execute([$p['ig_business_id']]);
    $rules = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rules as $rule) {
        if ($rule['trigger_type'] !== 'keyword')
            continue;

        $keywords = preg_split('/[,،\n]/u', $rule['keywords']);
        foreach ($keywords as $kw) {
            $kw = trim($kw);
            if (empty($kw))
                continue;

            if (mb_stripos($test_text, $kw) !== false || mb_stripos($kw, $test_text) !== false) {
                echo "<p class='success'>✅ MATCH FOUND!</p>";
                echo "<p>Rule ID: {$rule['id']}</p>";
                echo "<p>Keyword: '$kw'</p>";
                echo "<p>Reply: " . substr($rule['reply_message'], 0, 100) . "...</p>";
                $found = true;
                break 2;
            }
        }
    }
}

if (!$found) {
    echo "<p class='error'>❌ NO MATCH FOUND for '2'!</p>";
    echo "<p>This means you need to add a keyword rule for '2' or 'الحملات الاعلانيه الممولة'</p>";
}

echo "<h2>✅ Debug Complete</h2>";
echo "<p><a href='debug_instagram_buttons.php' style='color:#0ff'>← Back to Button Debug</a></p>";
echo "</body></html>";
