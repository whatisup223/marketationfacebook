<?php
session_start();
require_once __DIR__ . '/../includes/functions.php';

if (!isLoggedIn()) {
    header("Location: ../login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$pdo = getDB();

// 1. Get App Settings
$stmt = $pdo->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('fb_app_id', 'fb_app_secret', 'fb_api_version')");
$settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

$app_id = $settings['fb_app_id'] ?? '';
$app_secret = $settings['fb_app_secret'] ?? '';
$api_version = $settings['fb_api_version'] ?? 'v18.0';

if (empty($app_id) || empty($app_secret)) {
    die(__('config_required_title'));
}

// 2. Determine Callback URL
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'];
$current_path = strtok($_SERVER["REQUEST_URI"], '?');
// Ensure no query params in redirect_uri
$redirect_uri = $protocol . '://' . $host . $current_path;

// 3. Handle Error or Cancellation
if (isset($_GET['error'])) {
    $error_msg = $_GET['error_description'] ?? $_GET['error'];
    set_flash('error', __('fb_error') . htmlspecialchars($error_msg));
    header("Location: settings.php");
    exit;
}

// 4. Handle Code Exchange
if (isset($_GET['code'])) {

    // Validate State (CSRF Protection)
    if (!isset($_GET['state']) || !isset($_SESSION['fb_oauth_state']) || $_GET['state'] !== $_SESSION['fb_oauth_state']) {
        die(__('csrf_error'));
    }

    $code = $_GET['code'];

    // A. Exchange Code for Access Token
    $token_url = "https://graph.facebook.com/{$api_version}/oauth/access_token?client_id={$app_id}&redirect_uri=" . urlencode($redirect_uri) . "&client_secret={$app_secret}&code={$code}";

    $response = @file_get_contents($token_url);
    $data = json_decode($response, true);

    if (!isset($data['access_token'])) {
        die(__('fb_error') . ($data['error']['message'] ?? 'Unknown error'));
    }

    $short_lived_token = $data['access_token'];

    // B. Exchange for Long-Lived Token
    $exchange_url = "https://graph.facebook.com/{$api_version}/oauth/access_token?grant_type=fb_exchange_token&client_id={$app_id}&client_secret={$app_secret}&fb_exchange_token={$short_lived_token}";

    $response = @file_get_contents($exchange_url);
    $data = json_decode($response, true);

    $long_lived_token = $data['access_token'] ?? $short_lived_token;

    // C. Get User Info
    require_once __DIR__ . '/../includes/facebook_api.php';
    $fb = new FacebookAPI();

    // We make a raw request here or use the API helper if suitable
    // The API helper uses stored tokens from DB usually, but here we have a fresh token.
    // Let's use curl directly or instantiate API with token if supported.
    // The existing code used makeRequest but passed the token explicitly.
    // However, makeRequest definition is: makeRequest($endpoint, $params = [], $access_token = null)

    $user_info = $fb->makeRequest("me", ['fields' => 'id,name'], $long_lived_token);

    if (isset($user_info['id'])) {
        $fb_user_id = $user_info['id'];
        $fb_name = $user_info['name'];

        // D. Save to DB
        // Check if exists
        $stmt = $pdo->prepare("SELECT id FROM fb_accounts WHERE fb_id = ? AND user_id = ?");
        $stmt->execute([$fb_user_id, $user_id]);
        $existing = $stmt->fetch();

        if ($existing) {
            // Update
            $update = $pdo->prepare("UPDATE fb_accounts SET access_token = ?, fb_name = ?, is_active = 1, token_type = 'Long-Lived', expires_at = DATE_ADD(NOW(), INTERVAL 60 DAY) WHERE id = ?");
            $update->execute([$long_lived_token, $fb_name, $existing['id']]);
        } else {
            // Insert account
            $insert = $pdo->prepare("INSERT INTO fb_accounts (user_id, fb_name, fb_id, access_token, is_active, token_type, expires_at) VALUES (?, ?, ?, ?, 1, 'Long-Lived', DATE_ADD(NOW(), INTERVAL 60 DAY))");
            $insert->execute([$user_id, $fb_name, $fb_user_id, $long_lived_token]);
        }

        // Clear state
        unset($_SESSION['fb_oauth_state']);

        set_flash('success', __('account_added_success'));
        header("Location: settings.php");
        exit;

    } else {
        die(__('fb_error') . ($user_info['error']['message'] ?? 'Unknown error'));
    }
} else {
    // No code? Redirect to settings
    header("Location: settings.php");
    exit;
}
