<?php
require_once __DIR__ . '/includes/db_config.php';
require_once __DIR__ . '/includes/facebook_api.php';

header('Content-Type: text/plain; charset=UTF-8');

echo "========================================\n";
echo "   FACEBOOK PERMISSIONS DEBUG TOOL      \n";
echo "========================================\n\n";

// 1. Get the latest active page token from DB
$stmt = $pdo->query("SELECT p.page_name, p.page_id, p.page_access_token, a.fb_name as account_owner 
                     FROM fb_pages p 
                     JOIN fb_accounts a ON p.account_id = a.id 
                     ORDER BY p.id DESC LIMIT 1");
$page = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$page) {
    die("Error: No pages found in the database. Please link a Facebook account first.\n");
}

echo "Testing Page: " . $page['page_name'] . " (" . $page['page_id'] . ")\n";
echo "Account Owner: " . $page['account_owner'] . "\n";
echo "Token Start: " . substr($page['page_access_token'], 0, 15) . "...\n\n";

$fb = new FacebookAPI();

// 2. Check Permissions
echo "--- Checking Permissions ---\n";
$perms = $fb->makeRequest('me/permissions', [], $page['page_access_token']);

if (isset($perms['data'])) {
    $granted = [];
    $missing = [];
    $required = ['pages_read_engagement', 'pages_show_list', 'pages_manage_metadata', 'pages_read_user_content'];

    foreach ($perms['data'] as $p) {
        if ($p['status'] === 'granted') {
            $granted[] = $p['permission'];
        }
    }

    echo "✅ Granted Permissions:\n";
    foreach ($granted as $g) {
        echo "   - $g\n";
    }

    echo "\n🔍 Checking Critical Permissions for Comment Extraction:\n";
    foreach ($required as $r) {
        if (in_array($r, $granted)) {
            echo "   [OK] $r\n";
        } else {
            echo "   [MISSING] $r (Extraction might fail for some users)\n";
        }
    }
} else {
    echo "❌ Error fetching permissions: " . json_encode($perms) . "\n";
}

// 3. Check Token Details (via /me)
echo "\n--- Token Identity Check ---\n";
$me = $fb->makeRequest('me', ['fields' => 'id,name,category'], $page['page_access_token']);
if (isset($me['id'])) {
    echo "✅ Token is VALID for endpoint /me\n";
    echo "   Identity: " . ($me['name'] ?? 'Unknown') . "\n";
    echo "   Type: " . ($me['category'] ?? 'Page Token') . "\n";
} else {
    echo "❌ Token is INVALID or Expired: " . json_encode($me) . "\n";
}

// 4. Test Comment Edge & Visibility
echo "\n--- Final Test: Checking Data Visibility ---\n";
$feed = $fb->makeRequest('me/feed', ['limit' => 1, 'fields' => 'id,comments.limit(5){from,message}'], $page['page_access_token']);

if (isset($feed['data'][0])) {
    echo "✅ Success: Can read the Page Feed.\n";
    if (isset($feed['data'][0]['comments']['data'])) {
        $comments = $feed['data'][0]['comments']['data'];
        echo "✅ Success: Found " . count($comments) . " comments on the latest post.\n";

        $visible_count = 0;
        foreach ($comments as $index => $c) {
            $has_from = isset($c['from']);
            $author = $has_from ? $c['from']['name'] : "REDACTED (Hidden by Facebook)";
            echo "   Comment " . ($index + 1) . ": [" . ($has_from ? "VISIBLE" : "HIDDEN") . "] Author: $author\n";
            if ($has_from)
                $visible_count++;
        }

        if ($visible_count === 0 && count($comments) > 0) {
            echo "\n⚠️ WARNING: Facebook is hiding ALL comment authors.\n";
            echo "   This usually means your App needs 'Advanced Access' for 'pages_read_engagement'.\n";
        }
    } else {
        echo "ℹ️ Note: No comments found on the latest post to test visibility.\n";
    }
} else {
    echo "❌ Error reading feed: " . json_encode($feed) . "\n";
}

echo "\n========================================\n";
echo "Debug finished. If [MISSING] appear above, you need to re-login and accept all permissions.\n";
