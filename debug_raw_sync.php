<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/facebook_api.php';

$pdo = getDB();
echo "<h1>Raw Facebook Page Data Debugger</h1>";
echo "<p>Checking raw API response for linked accounts to diagnose missing Instagram Business Accounts.</p>";

// 1. Get Active User Token from DB
$stmt = $pdo->query("SELECT id, fb_name, access_token FROM fb_accounts LIMIT 1");
$acc = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$acc) {
    echo "<b>Error:</b> No linked Facebook account found in database. Please link an account first.";
    exit;
}

$user_token = $acc['access_token'];
$fb_name = $acc['fb_name'];

echo "<h3>Using Account: " . htmlspecialchars($fb_name) . " (ID: " . $acc['id'] . ")</h3>";

// 2. Fetch Raw Data from Facebook
$fb = new FacebookAPI();
// Explicitly requesting instagram_business_account field
$endpoint = "me/accounts";
$params = [
    'fields' => 'id,name,access_token,category,instagram_business_account{id,username,name,profile_picture_url},connected_instagram_account',
    'limit' => 50
];

echo "<h4>API Request: GET $endpoint ?fields=...</h4>";

$response = $fb->makeRequest($endpoint, $params, $user_token);

if (isset($response['error'])) {
    echo "<div style='color:red; border:1px solid red; padding:10px;'>";
    echo "<b>API Error:</b> " . htmlspecialchars(json_encode($response['error'], JSON_PRETTY_PRINT));
    echo "</div>";
    echo "<p>Possible causes: Expired token, password change, or app removed from business integrations.</p>";
} elseif (isset($response['data'])) {
    echo "<table border='1' cellpadding='10' style='border-collapse:collapse; width:100%;'>";
    echo "<tr style='background:#f0f0f0;'><th>Page Name (ID)</th><th>Instagram Business Account</th><th>Connected IG (Old API)</th><th>Raw Data Dump</th></tr>";

    foreach ($response['data'] as $page) {
        $pageName = htmlspecialchars($page['name']);
        $pageId = $page['id'];

        $igBus = $page['instagram_business_account'] ?? null;
        $igConn = $page['connected_instagram_account'] ?? null; // Sometimes returns ID directly

        $igStatus = "<span style='color:red;'>Not Found</span>";
        if ($igBus) {
            $igStatus = "<span style='color:green;'><b>FOUND!</b><br>ID: " . $igBus['id'] . "<br>User: @" . ($igBus['username'] ?? 'N/A') . "</span>";
        }

        $oldIgStatus = $igConn ? "ID: " . (is_array($igConn) ? $igConn['id'] : $igConn) : "-";

        echo "<tr>";
        echo "<td><b>$pageName</b><br><small>$pageId</small></td>";
        echo "<td>$igStatus</td>";
        echo "<td>$oldIgStatus</td>";
        echo "<td><pre style='font-size:10px; max-height:100px; overflow:auto;'>" . htmlspecialchars(json_encode($page, JSON_PRETTY_PRINT)) . "</pre></td>";
        echo "</tr>";
    }
    echo "</table>";

    echo "<h3>Diagnosis:</h3>";
    echo "<ul>";
    echo "<li>If <b>Instagram Business Account</b> column says <b style='color:green'>FOUND!</b>, then the API is working perfectly, and the issue is likely in our local `fb_accounts.php` sync logic (it might be skipping it).</li>";
    echo "<li>If it says <b style='color:red'>Not Found</b> for a page you KNOW has Instagram:</li>";
    echo "<ul>";
    echo "<li>1. Verify the Instagram account is set to <b>Business</b> or <b>Creator</b> (Not Personal).</li>";
    echo "<li>2. Verify you have explicitly linked it in <b>Page Settings -> Linked Accounts -> Instagram</b>.</li>";
    echo "<li>3. Check if there is a 'Review Connection' alert on Facebook Page Settings.</li>";
    echo "</ul>";
    echo "</ul>";
} else {
    echo "Unknown response format.";
    print_r($response);
}
?>