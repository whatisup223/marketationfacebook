<?php
// user/ajax_moderator.php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/facebook_api.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$pdo = getDB();
$action = $_GET['action'] ?? $_POST['action'] ?? '';

// 0. Webhook Info
if ($action === 'get_webhook_info') {
    try {
        $stmt = $pdo->prepare("SELECT webhook_token, verify_token FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
        $current_url = "$protocol://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
        $dir_url = dirname($current_url);
        $root_url = dirname($dir_url);

        $token = $user['webhook_token'] ?? '';
        $webhook_url = rtrim($root_url, '/') . '/webhook.php?uid=' . $token;

        echo json_encode([
            'status' => 'success',
            'webhook_url' => $webhook_url,
            'verify_token' => $user['verify_token'] ?? ''
        ]);
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

// 1. Get Rules
if ($action === 'get_rules') {
    $page_id = $_GET['page_id'] ?? '';
    $platform = $_GET['platform'] ?? 'facebook';
    if (!$page_id) {
        echo json_encode(['status' => 'error', 'message' => 'Page ID required']);
        exit;
    }

    // Now we can safely query by platform
    $stmt = $pdo->prepare("SELECT * FROM fb_moderation_rules WHERE page_id = ? AND user_id = ? AND platform = ?");
    $stmt->execute([$page_id, $user_id, $platform]);
    $rules = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$rules) {
        // Return default empty rules
        $rules = [
            'page_id' => $page_id,
            'banned_keywords' => '',
            'hide_phones' => 0,
            'hide_links' => 0,
            'action_type' => 'hide',
            'is_active' => 0,
            'platform' => $platform
        ];
    }

    echo json_encode(['status' => 'success', 'data' => $rules]);
    exit;
}

// 2. Save Rules
if ($action === 'save_rules' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $page_id = $_POST['page_id'] ?? '';
    $platform = $_POST['platform'] ?? 'facebook';
    $banned_keywords = $_POST['banned_keywords'] ?? '';
    $hide_phones = isset($_POST['hide_phones']) && ($_POST['hide_phones'] == 1 || $_POST['hide_phones'] == 'on') ? 1 : 0;
    $hide_links = isset($_POST['hide_links']) && ($_POST['hide_links'] == 1 || $_POST['hide_links'] == 'on') ? 1 : 0;
    $action_type = $_POST['action_type'] ?? 'hide';
    $is_active = isset($_POST['is_active']) && ($_POST['is_active'] == 1 || $_POST['is_active'] == 'on') ? 1 : 0;

    if (!$page_id) {
        echo json_encode(['status' => 'error', 'message' => 'Page ID required']);
        exit;
    }

    // Check if exists
    $stmt = $pdo->prepare("SELECT id FROM fb_moderation_rules WHERE page_id = ? AND user_id = ? AND platform = ?");
    $stmt->execute([$page_id, $user_id, $platform]);
    $existing_id = $stmt->fetchColumn();

    if ($existing_id) {
        $stmt = $pdo->prepare("UPDATE fb_moderation_rules SET banned_keywords = ?, hide_phones = ?, hide_links = ?, action_type = ?, is_active = ? WHERE id = ?");
        $stmt->execute([$banned_keywords, $hide_phones, $hide_links, $action_type, $is_active, $existing_id]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO fb_moderation_rules (user_id, page_id, platform, banned_keywords, hide_phones, hide_links, action_type, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$user_id, $page_id, $platform, $banned_keywords, $hide_phones, $hide_links, $action_type, $is_active]);
    }

    echo json_encode(['status' => 'success', 'message' => 'Rules saved successfully']);
    exit;
}

// 3. Get Logs
if ($action === 'get_logs') {
    $page_id = $_GET['page_id'] ?? '';
    $platform = $_GET['platform'] ?? 'facebook';
    $stmt = $pdo->prepare("SELECT * FROM fb_moderation_logs WHERE user_id = ? " . ($page_id ? "AND page_id = ?" : "") . " AND platform = ? ORDER BY created_at DESC LIMIT 50");
    if ($page_id) {
        $stmt->execute([$user_id, $page_id, $platform]);
    } else {
        $stmt->execute([$user_id, $platform]);
    }
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['status' => 'success', 'data' => $logs]);
    exit;
}

// 4. Delete Log & Comment
if ($action === 'delete_log' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $log_id = $_POST['log_id'] ?? '';
    if (!$log_id) {
        echo json_encode(['status' => 'error', 'message' => 'Log ID required']);
        exit;
    }

    $stmt = $pdo->prepare("SELECT * FROM fb_moderation_logs WHERE id = ? AND user_id = ?");
    $stmt->execute([$log_id, $user_id]);
    $log = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$log) {
        echo json_encode(['status' => 'error', 'message' => 'Log not found']);
        exit;
    }

    // Attempt to delete from Facebook/Instagram
    $id_column = ($log['platform'] === 'instagram') ? 'ig_business_id' : 'page_id';
    $stmt = $pdo->prepare("SELECT page_access_token FROM fb_pages WHERE $id_column = ?");
    $stmt->execute([$log['page_id']]);
    $token = $stmt->fetchColumn();

    if ($token && !empty($log['comment_id'])) {
        $fb = new FacebookAPI();
        try {
            $fb->makeRequest($log['comment_id'], [], $token, 'DELETE');
        } catch (Exception $e) {
            // Silently ignore if already deleted or error
        }
    }

    // Delete from DB
    $stmt = $pdo->prepare("DELETE FROM fb_moderation_logs WHERE id = ?");
    $stmt->execute([$log_id]);

    echo json_encode(['status' => 'success', 'message' => 'Log and comment deleted']);
    exit;
}

// 5. Token Debug/Webhook Check
if ($action === 'get_token_debug') {
    $platform = $_GET['platform'] ?? 'facebook';
    $id_column = ($platform === 'instagram') ? 'ig_business_id' : 'page_id';

    $stmt = $pdo->prepare("SELECT page_access_token, page_id FROM fb_pages WHERE $id_column = ?");
    $stmt->execute([$page_id]);
    $page = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$page || !$page['page_access_token']) {
        echo json_encode(['status' => 'error', 'message' => 'Page token not found']);
        exit;
    }

    $token = $page['page_access_token'];
    $target_page_id = $page['page_id']; // For subscription check on FB


    try {
        $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
        $stmt->execute(['fb_app_id']);
        $app_id = $stmt->fetchColumn();
        $stmt->execute(['fb_app_secret']);
        $app_secret = $stmt->fetchColumn();

        if (!$app_id || !$app_secret) {
            echo json_encode(['status' => 'error', 'message' => 'App ID or Secret missing']);
            exit;
        }

        $fb = new FacebookAPI();
        $app_access_token = "$app_id|$app_secret";

        // Debug Token
        $debug = $fb->debugToken($token, $app_access_token);
        $isValid = isset($debug['data']['is_valid']) && $debug['data']['is_valid'];

        // Check Webhook Subscription
        $is_subscribed = false;
        try {
            $subs = $fb->makeRequest("$target_page_id/subscribed_apps", [], $token, 'GET');
            if (isset($subs['data'])) {
                foreach ($subs['data'] as $app) {
                    if (isset($app['id']) && $app['id'] == $app_id) {
                        $is_subscribed = true;
                        break;
                    }
                }
            }
        } catch (Exception $e) {
            // Ignore subscription check error
        }

        echo json_encode([
            'status' => 'success',
            'data' => [
                'valid' => $isValid,
                'subscribed' => $is_subscribed,
                'masked_token' => substrmask($token)
            ]
        ]);
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => 'Debug failed: ' . $e->getMessage()]);
    }
    exit;
}

function substrmask($token)
{
    return substr($token, 0, 10) . '...' . substr($token, -5);
}

// 6. Subscribe Page
if ($action === 'subscribe_page' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $page_id = $_POST['page_id'] ?? '';
    $platform = $_POST['platform'] ?? 'facebook';
    if (!$page_id) {
        echo json_encode(['status' => 'error', 'message' => 'Page ID is required']);
        exit;
    }
    try {
        $id_column = ($platform === 'instagram') ? 'ig_business_id' : 'page_id';
        $stmt = $pdo->prepare("SELECT page_access_token, page_id FROM fb_pages WHERE $id_column = ?");
        $stmt->execute([$page_id]);
        $page = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$page) {
            echo json_encode(['status' => 'error', 'message' => 'Page not found']);
            exit;
        }

        // Use platform-specific ID for subscription
        $target_subscribe_id = ($platform === 'instagram') ? $page_id : $page['page_id'];

        $fb = new FacebookAPI();
        // Subscribe the app
        $res = $fb->subscribeApp($target_subscribe_id, $page['page_access_token'], $platform);

        if (isset($res['success']) && $res['success']) {
            echo json_encode(['status' => 'success', 'message' => __('page_protected_success')]);
        } else {
            $err = isset($res['error']['message']) ? $res['error']['message'] : json_encode($res);
            echo json_encode(['status' => 'error', 'message' => 'FB Error: ' . $err]);
        }
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

// 7. Unsubscribe Page
if ($action === 'unsubscribe_page' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $page_id = $_POST['page_id'] ?? '';
    $platform = $_POST['platform'] ?? 'facebook';

    if (!$page_id) {
        echo json_encode(['status' => 'error', 'message' => 'Page ID is required']);
        exit;
    }
    try {
        $id_column = ($platform === 'instagram') ? 'ig_business_id' : 'page_id';
        $stmt = $pdo->prepare("SELECT page_access_token, page_id FROM fb_pages WHERE $id_column = ?");
        $stmt->execute([$page_id]);
        $page = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$page) {
            echo json_encode(['status' => 'error', 'message' => 'Page not found']);
            exit;
        }

        $target_unsubscribe_id = ($platform === 'instagram') ? $page_id : $page['page_id'];
        $fb = new FacebookAPI();
        // Unsubscribe
        $res = $fb->makeRequest("$target_unsubscribe_id/subscribed_apps", [], $page['page_access_token'], 'DELETE');

        if (isset($res['success']) && $res['success']) {
            echo json_encode(['status' => 'success', 'message' => __('page_protection_stopped')]);
        } else {
            $err = isset($res['error']['message']) ? $res['error']['message'] : json_encode($res);
            echo json_encode(['status' => 'error', 'message' => 'FB Error: ' . $err]);
        }
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
