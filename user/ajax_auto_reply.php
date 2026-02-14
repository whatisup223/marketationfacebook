<?php
require_once '../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$action = $_REQUEST['action'] ?? '';
$pdo = getDB();
if (!$pdo) {
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit;
}
if ($action === 'get_webhook_info') {
    try {
        $stmt = $pdo->prepare("SELECT webhook_token, verify_token FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
        $current_url = "$protocol://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
        $dir_url = dirname($current_url);
        $root_url = dirname($dir_url);

        $token = $user['webhook_token'] ?? '';
        $webhook_url = rtrim($root_url, '/') . '/webhook.php?uid=' . $token;

        echo json_encode([
            'success' => true,
            'webhook_url' => $webhook_url,
            'verify_token' => $user['verify_token'] ?? ''
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

if ($action === 'fetch_rules') {
    $page_id = $_GET['page_id'] ?? '';
    $source = $_GET['source'] ?? 'comment'; // 'comment' or 'message'
    $platform = $_GET['platform'] ?? 'facebook';

    if (!$page_id) {
        echo json_encode(['success' => false, 'error' => 'Page ID is required']);
        exit;
    }

    try {
        // Safe fetch with platform filter
        $stmt = $pdo->prepare("SELECT * FROM auto_reply_rules WHERE page_id = ? AND reply_source = ? AND platform = ? ORDER BY trigger_type DESC, created_at DESC");
        $stmt->execute([$page_id, $source, $platform]);
        $rules = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['success' => true, 'rules' => $rules]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

if ($action === 'fetch_page_settings') {
    $page_id = $_GET['page_id'] ?? '';
    $platform = $_GET['platform'] ?? 'facebook';
    if (!$page_id) {
        echo json_encode(['success' => false, 'error' => 'Page ID is required']);
        exit;
    }

    try {
        $id_column = ($platform === 'instagram') ? 'ig_business_id' : 'page_id';
        $stmt = $pdo->prepare("SELECT bot_cooldown_seconds, bot_schedule_enabled, bot_schedule_start, bot_schedule_end, bot_exclude_keywords, bot_ai_sentiment_enabled, bot_anger_keywords, bot_repetition_threshold, bot_handover_reply FROM fb_pages WHERE $id_column = ?");
        $stmt->execute([$page_id]);
        $settings = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$settings) {
            echo json_encode(['success' => false, 'error' => 'Page not found']);
            exit;
        }

        echo json_encode(['success' => true, 'settings' => $settings]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

if ($action === 'fetch_payloads') {
    $page_id = $_GET['page_id'] ?? '';
    $source = $_GET['source'] ?? 'message';

    if (!$page_id) {
        echo json_encode(['success' => false, 'error' => 'Page ID is required']);
        exit;
    }

    try {
        // Fetch all rules for this page
        $stmt = $pdo->prepare("SELECT id, reply_message, keywords FROM auto_reply_rules WHERE page_id = ? AND reply_source = ?");
        $stmt->execute([$page_id, $source]);
        $rules = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $payloads = [];
        foreach ($rules as $rule) {
            if ($rule['keywords']) {
                $payloads[] = [
                    'id' => $rule['id'],
                    'label' => 'Rule: ' . substr($rule['keywords'], 0, 30) . '...'
                ];
            }
        }

        echo json_encode(['success' => true, 'payloads' => $payloads]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

if ($action === 'get_token_debug') {
    $page_id = $_GET['page_id'] ?? '';
    if (!$page_id) {
        echo json_encode(['success' => false, 'error' => 'Page ID is required']);
        exit;
    }

    $stmt = $pdo->prepare("SELECT page_access_token FROM fb_pages WHERE page_id = ?");
    $stmt->execute([$page_id]);
    $page = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$page) {
        echo json_encode(['success' => false, 'error' => 'Page not found']);
        exit;
    }

    $token = $page['page_access_token'];
    $masked_token = substr($token, 0, 8) . '...' . substr($token, -8);

    echo json_encode([
        'success' => true,
        'masked_token' => $masked_token,
        'length' => strlen($token)
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Regenerate Webhook URL ID only
    if ($action === 'regenerate_webhook') {
        try {
            $new_token = bin2hex(random_bytes(16));
            $stmt = $pdo->prepare("UPDATE users SET webhook_token = ? WHERE id = ?");
            $stmt->execute([$new_token, $_SESSION['user_id']]);

            // Return new URL info
            $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
            $current_url = "$protocol://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
            $root_url = dirname(dirname($current_url));
            $webhook_url = rtrim($root_url, '/') . '/webhook.php?uid=' . $new_token;

            echo json_encode(['success' => true, 'webhook_url' => $webhook_url]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    // Regenerate Verify Token only
    if ($action === 'regenerate_verify') {
        try {
            $new_token = bin2hex(random_bytes(16));
            $stmt = $pdo->prepare("UPDATE users SET verify_token = ? WHERE id = ?");
            $stmt->execute([$new_token, $_SESSION['user_id']]);
            echo json_encode(['success' => true, 'verify_token' => $new_token]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    if ($action === 'save_rule') {
        $page_id = $_POST['page_id'] ?? '';
        $type = $_POST['type'] ?? 'keyword';
        $keywords = $_POST['keywords'] ?? '';
        $reply = $_POST['reply'] ?? '';
        $reply_buttons = $_POST['reply_buttons'] ?? null; // JSON String or null
        $rule_id = $_POST['rule_id'] ?? '';
        $hide_comment = isset($_POST['hide_comment']) ? (int) $_POST['hide_comment'] : 0;
        $is_ai_safe = isset($_POST['is_ai_safe']) ? (int) $_POST['is_ai_safe'] : 1;
        $bypass_schedule = isset($_POST['bypass_schedule']) ? (int) $_POST['bypass_schedule'] : 0;
        $bypass_cooldown = isset($_POST['bypass_cooldown']) ? (int) $_POST['bypass_cooldown'] : 0;
        $reply_image_url = $_POST['reply_image_url'] ?? null;
        $private_reply_enabled = isset($_POST['private_reply_enabled']) ? (int) $_POST['private_reply_enabled'] : 0;
        $private_reply_text = $_POST['private_reply_text'] ?? null;
        $auto_like_comment = isset($_POST['auto_like_comment']) ? (int) $_POST['auto_like_comment'] : 0;
        $source = $_POST['source'] ?? 'comment';
        $platform = $_POST['platform'] ?? 'facebook';

        // Allow empty reply ONLY for default type (to disable it)
        if (!$page_id || ($type !== 'default' && !$reply)) {
            echo json_encode(['success' => false, 'error' => 'Missing required fields']);
            exit;
        }

        try {
            if ($rule_id) {
                // Update
                $stmt = $pdo->prepare("UPDATE auto_reply_rules SET trigger_type = ?, keywords = ?, reply_message = ?, `reply_buttons` = ?, reply_image_url = ?, hide_comment = ?, is_ai_safe = ?, bypass_schedule = ?, bypass_cooldown = ?, private_reply_enabled = ?, private_reply_text = ?, auto_like_comment = ?, reply_source = ?, platform = ? WHERE id = ? AND page_id = ?");
                $stmt->execute([$type, $keywords, $reply, $reply_buttons, $reply_image_url, $hide_comment, $is_ai_safe, $bypass_schedule, $bypass_cooldown, $private_reply_enabled, $private_reply_text, $auto_like_comment, $source, $platform, $rule_id, $page_id]);
            } else {
                // Check if existing default rule
                if ($type === 'default') {
                    $check = $pdo->prepare("SELECT id FROM auto_reply_rules WHERE page_id = ? AND trigger_type = 'default' AND reply_source = ? AND platform = ?");
                    $check->execute([$page_id, $source, $platform]);
                    if ($check->fetch()) {
                        $stmt = $pdo->prepare("UPDATE auto_reply_rules SET reply_message = ?, `reply_buttons` = ?, reply_image_url = ?, hide_comment = ?, private_reply_enabled = ?, private_reply_text = ?, auto_like_comment = ?, is_ai_safe = ?, bypass_schedule = ?, bypass_cooldown = ? WHERE page_id = ? AND trigger_type = 'default' AND reply_source = ? AND platform = ?");
                        $stmt->execute([$reply, $reply_buttons, $reply_image_url, $hide_comment, $private_reply_enabled, $private_reply_text, $auto_like_comment, $is_ai_safe, $bypass_schedule, $bypass_cooldown, $page_id, $source, $platform]);
                        echo json_encode(['success' => true, 'message' => __('rule_saved')]);
                        exit;
                    }
                }

                // Insert
                $stmt = $pdo->prepare("INSERT INTO auto_reply_rules (page_id, trigger_type, keywords, reply_message, `reply_buttons`, reply_image_url, hide_comment, is_ai_safe, bypass_schedule, bypass_cooldown, private_reply_enabled, private_reply_text, auto_like_comment, reply_source, platform) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$page_id, $type, $keywords, $reply, $reply_buttons, $reply_image_url, $hide_comment, $is_ai_safe, $bypass_schedule, $bypass_cooldown, $private_reply_enabled, $private_reply_text, $auto_like_comment, $source, $platform]);
            }
            echo json_encode(['success' => true, 'message' => __('rule_saved')]);
            exit;
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            exit;
        }
    }

    // Toggle Rule Active Status (NEW)
    if ($action === 'toggle_rule') {
        $rule_id = $_POST['rule_id'] ?? '';
        $is_active = isset($_POST['is_active']) ? (int) $_POST['is_active'] : 0;

        if (!$rule_id) {
            echo json_encode(['success' => false, 'error' => 'Rule ID is required']);
            exit;
        }

        try {
            $stmt = $pdo->prepare("UPDATE auto_reply_rules SET is_active = ? WHERE id = ?");
            $stmt->execute([$is_active, $rule_id]);

            echo json_encode(['success' => true, 'message' => 'Rule status updated']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    if ($action === 'delete_rule') {
        $rule_id = $_POST['rule_id'] ?? '';
        $page_id = $_POST['page_id'] ?? '';

        if (!$rule_id || !$page_id) {
            echo json_encode(['success' => false, 'error' => 'Missing Rule ID or Page ID']);
            exit;
        }

        try {
            $stmt = $pdo->prepare("DELETE FROM auto_reply_rules WHERE id = ? AND page_id = ?");
            $stmt->execute([$rule_id, $page_id]);
            echo json_encode(['success' => true, 'message' => 'Rule deleted']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    if ($action === 'save_page_settings') {
        $page_id = $_POST['page_id'] ?? '';
        $cooldown = (int) ($_POST['cooldown'] ?? 0);
        $schedule_enabled = (int) ($_POST['schedule_enabled'] ?? 0);
        $schedule_start = $_POST['schedule_start'] ?? '00:00';
        $schedule_end = $_POST['schedule_end'] ?? '23:59';
        $exclude_keywords = (int) ($_POST['exclude_keywords'] ?? 0);
        $ai_sentiment = (int) ($_POST['ai_sentiment'] ?? 1);
        $anger_keywords = $_POST['anger_keywords'] ?? '';
        $repetition_threshold = (int) ($_POST['repetition_threshold'] ?? 3);
        $handover_reply = $_POST['handover_reply'] ?? '';
        $platform = $_POST['platform'] ?? 'facebook';

        if (!$page_id) {
            echo json_encode(['success' => false, 'error' => 'Page ID is required']);
            exit;
        }

        try {
            $id_column = ($platform === 'instagram') ? 'ig_business_id' : 'page_id';
            $stmt = $pdo->prepare("UPDATE fb_pages SET bot_cooldown_seconds = ?, bot_schedule_enabled = ?, bot_schedule_start = ?, bot_schedule_end = ?, bot_exclude_keywords = ?, bot_ai_sentiment_enabled = ?, bot_anger_keywords = ?, bot_repetition_threshold = ?, bot_handover_reply = ? WHERE $id_column = ?");
            $stmt->execute([$cooldown, $schedule_enabled, $schedule_start, $schedule_end, $exclude_keywords, $ai_sentiment, $anger_keywords, $repetition_threshold, $handover_reply, $page_id]);
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    if ($action === 'subscribe_page') {
        $page_id = $_POST['page_id'] ?? '';
        if (!$page_id) {
            echo json_encode(['success' => false, 'error' => 'Page ID is required']);
            exit;
        }

        try {
            $platform = $_POST['platform'] ?? 'facebook';
            $id_column = ($platform === 'instagram') ? 'ig_business_id' : 'page_id';

            // Get page access token AND verify page exists via correct column
            // We also need the FB page_id because subscription is done on the FB Page object
            $stmt = $pdo->prepare("SELECT page_access_token, page_id FROM fb_pages WHERE $id_column = ?");
            $stmt->execute([$page_id]);
            $page = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$page) {
                echo json_encode(['success' => false, 'error' => 'Page not found']);
                exit;
            }

            // For API subscription, we ALWAYS use the Facebook Page ID
            $target_subscribe_id = $page['page_id'];

            require_once '../includes/facebook_api.php';
            $fb = new FacebookAPI();
            // Pass correct page_id to subscribe
            $res = $fb->subscribeApp($target_subscribe_id, $page['page_access_token']);

            if (isset($res['success']) && $res['success']) {
                echo json_encode(['success' => true, 'message' => __('page_protected_success')]);
            } else {
                echo json_encode(['success' => false, 'error' => $res['error']['message'] ?? 'Unknown error']);
            }
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    if ($action === 'fetch_stats') {
        $page_id = $_POST['page_id'] ?? '';
        $range = $_POST['range'] ?? '7d'; // 24h, 7d, 30d, custom
        $start_date = $_POST['start_date'] ?? null;
        $end_date = $_POST['end_date'] ?? null;

        if (!$page_id) {
            echo json_encode(['success' => false, 'error' => 'Page ID is required']);
            exit;
        }

        try {
            // Logic to calculate stats based on logs table
            // This is a placeholder, actual logic depends on how logs are stored
            echo json_encode([
                'success' => true,
                'total_replies' => 0,
                'keyword_matches' => 0,
                'ai_replies' => 0,
                'handovers' => 0,
                'chart_data' => []
            ]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    if ($action === 'unsubscribe_page') {
        $page = null;
        try {
            $page_id = $_POST['page_id'] ?? '';
            $platform = $_POST['platform'] ?? 'facebook';
            $id_column = ($platform === 'instagram') ? 'ig_business_id' : 'page_id';

            $stmt = $pdo->prepare("SELECT page_access_token, page_id FROM fb_pages WHERE $id_column = ?");
            $stmt->execute([$page_id]);
            $page = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$page)
                throw new Exception('Page not found');

            $target_id = $page['page_id'];
            require_once '../includes/facebook_api.php';
            $fb = new FacebookAPI();

            // Unsubscribe
            $res = $fb->makeRequest("$target_id/subscribed_apps", [], $page['page_access_token'], 'DELETE');

            if (isset($res['success']) && $res['success']) {
                echo json_encode(['success' => true, 'message' => 'Auto reply stopped']);
            } else {
                throw new Exception($res['error']['message'] ?? 'Unknown error');
            }
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
}