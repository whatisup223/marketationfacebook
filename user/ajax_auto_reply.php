<?php
require_once '../includes/functions.php';

header('Content-Type: application/json');

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

    if (!$page_id) {
        echo json_encode(['success' => false, 'error' => 'Page ID is required']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("SELECT * FROM auto_reply_rules WHERE page_id = ? AND reply_source = ? ORDER BY trigger_type DESC, created_at DESC");
        $stmt->execute([$page_id, $source]);
        $rules = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'rules' => $rules]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

if ($action === 'fetch_page_settings') {
    $page_id = $_GET['page_id'] ?? '';
    if (!$page_id) {
        echo json_encode(['success' => false, 'error' => 'Page ID is required']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("SELECT bot_cooldown_seconds, bot_schedule_enabled, bot_schedule_start, bot_schedule_end, bot_exclude_keywords, bot_ai_sentiment_enabled, bot_anger_keywords FROM fb_pages WHERE page_id = ?");
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

if ($action === 'debug_token_info') {
    $page_id = $_GET['page_id'] ?? '';
    if (!$page_id) {
        echo json_encode(['success' => false, 'error' => 'Page ID required']);
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
        $rule_id = $_POST['rule_id'] ?? '';
        $hide_comment = isset($_POST['hide_comment']) ? (int) $_POST['hide_comment'] : 0;
        $is_ai_safe = isset($_POST['is_ai_safe']) ? (int) $_POST['is_ai_safe'] : 1;
        $bypass_schedule = isset($_POST['bypass_schedule']) ? (int) $_POST['bypass_schedule'] : 0;
        $bypass_cooldown = isset($_POST['bypass_cooldown']) ? (int) $_POST['bypass_cooldown'] : 0;
        $source = $_POST['source'] ?? 'comment';

        // Allow empty reply ONLY for default type (to disable it)
        if (!$page_id || ($type !== 'default' && !$reply)) {
            echo json_encode(['success' => false, 'error' => 'Missing required fields']);
            exit;
        }

        try {
            if ($rule_id) {
                // Update
                $stmt = $pdo->prepare("UPDATE auto_reply_rules SET trigger_type = ?, keywords = ?, reply_message = ?, hide_comment = ?, is_ai_safe = ?, bypass_schedule = ?, bypass_cooldown = ?, reply_source = ? WHERE id = ? AND page_id = ?");
                $stmt->execute([$type, $keywords, $reply, $hide_comment, $is_ai_safe, $bypass_schedule, $bypass_cooldown, $source, $rule_id, $page_id]);
            } else {
                // Check if existing default rule
                if ($type === 'default') {
                    $check = $pdo->prepare("SELECT id FROM auto_reply_rules WHERE page_id = ? AND trigger_type = 'default' AND reply_source = ?");
                    $check->execute([$page_id, $source]);
                    if ($check->fetch()) {
                        $stmt = $pdo->prepare("UPDATE auto_reply_rules SET reply_message = ?, hide_comment = ? WHERE page_id = ? AND trigger_type = 'default' AND reply_source = ?");
                        $stmt->execute([$reply, $hide_comment, $page_id, $source]);
                        echo json_encode(['success' => true, 'message' => __('rule_saved')]);
                        exit;
                    }
                }

                // Insert
                $stmt = $pdo->prepare("INSERT INTO auto_reply_rules (page_id, trigger_type, keywords, reply_message, hide_comment, is_ai_safe, bypass_schedule, bypass_cooldown, reply_source) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$page_id, $type, $keywords, $reply, $hide_comment, $is_ai_safe, $bypass_schedule, $bypass_cooldown, $source]);
            }
            echo json_encode(['success' => true, 'message' => __('rule_saved')]);
            exit;
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            exit;
        }
    }

    if ($action === 'delete_rule') {
        $id = $_POST['id'] ?? '';
        if (!$id) {
            echo json_encode(['success' => false]);
            exit;
        }
        $stmt = $pdo->prepare("DELETE FROM auto_reply_rules WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(['success' => true, 'message' => __('rule_deleted')]);
        exit;
    }

    if ($action === 'subscribe_page') {
        $page_id = $_POST['page_id'] ?? '';
        if (!$page_id) {
            echo json_encode(['success' => false, 'error' => 'Page ID is required']);
            exit;
        }

        try {
            // Get page access token
            $stmt = $pdo->prepare("SELECT page_access_token FROM fb_pages WHERE page_id = ?");
            $stmt->execute([$page_id]);
            $page = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$page) {
                echo json_encode(['success' => false, 'error' => 'Page not found in database']);
                exit;
            }

            require_once '../includes/facebook_api.php';
            $fb = new FacebookAPI();
            $res = $fb->subscribeApp($page_id, $page['page_access_token']);

            if (isset($res['success']) && $res['success']) {
                echo json_encode(['success' => true, 'message' => 'Page successfully subscribed to Webhook!']);
            } else {
                $err = isset($res['error']['message']) ? $res['error']['message'] : json_encode($res);
                echo json_encode(['success' => false, 'error' => 'FB Error: ' . $err]);
            }
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    if ($action === 'save_page_settings') {
        $page_id = $_POST['page_id'] ?? '';
        $cooldown = (int) ($_POST['cooldown_seconds'] ?? 0);
        $sch_enabled = (int) ($_POST['schedule_enabled'] ?? 0);
        $sch_start = $_POST['schedule_start'] ?? '00:00';
        $sch_end = $_POST['schedule_end'] ?? '23:59';
        $exclude_kw = (int) ($_POST['exclude_keywords'] ?? 0);
        $ai_enabled = (int) ($_POST['ai_sentiment_enabled'] ?? 1);
        $anger_kws = $_POST['anger_keywords'] ?? '';

        if (!$page_id) {
            echo json_encode(['success' => false, 'error' => 'Page ID is required']);
            exit;
        }

        try {
            $stmt = $pdo->prepare("UPDATE fb_pages SET 
                bot_cooldown_seconds = ?, 
                bot_schedule_enabled = ?, 
                bot_schedule_start = ?, 
                bot_schedule_end = ?,
                bot_exclude_keywords = ?,
                bot_ai_sentiment_enabled = ?,
                bot_anger_keywords = ?
                WHERE page_id = ?");
            $stmt->execute([$cooldown, $sch_enabled, $sch_start, $sch_end, $exclude_kw, $ai_enabled, $anger_kws, $page_id]);
            echo json_encode(['success' => true, 'message' => __('settings_updated')]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    if ($action === 'unsubscribe_page') {
        $page_id = $_POST['page_id'] ?? '';
        if (!$page_id) {
            echo json_encode(['success' => false, 'error' => 'Page ID is required']);
            exit;
        }

        try {
            // Get page access token
            $stmt = $pdo->prepare("SELECT page_access_token FROM fb_pages WHERE page_id = ?");
            $stmt->execute([$page_id]);
            $page = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$page) {
                echo json_encode(['success' => false, 'error' => 'Page not found in database']);
                exit;
            }

            require_once '../includes/facebook_api.php';
            $fb = new FacebookAPI();
            // Using a new method unsubscribeApp (need to add it) or makeRequest manually
            // DELETE /{page_id}/subscribed_apps
            $res = $fb->makeRequest("$page_id/subscribed_apps", [], $page['page_access_token'], 'DELETE');

            if (isset($res['success']) && $res['success']) {
                echo json_encode(['success' => true, 'message' => 'Page Auto-Reply Stopped (Unsubscribed)!']);
            } else {
                $err = isset($res['error']['message']) ? $res['error']['message'] : json_encode($res);
                echo json_encode(['success' => false, 'error' => 'FB Error: ' . $err]);
            }
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
}

if ($action === 'fetch_handover_conversations') {
    $page_id = $_GET['page_id'] ?? '';
    if (!$page_id) {
        echo json_encode(['success' => false, 'error' => 'No page ID']);
        exit;
    }
    try {
        $stmt = $pdo->prepare("SELECT * FROM bot_conversation_states WHERE page_id = ? AND conversation_state = 'handover' ORDER BY updated_at DESC");
        $stmt->execute([$page_id]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'conversations' => $rows]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

if ($action === 'mark_as_resolved') {
    $id = $_POST['id'] ?? '';
    if (!$id) {
        echo json_encode(['success' => false, 'error' => 'No state ID']);
        exit;
    }
    try {
        $stmt = $pdo->prepare("UPDATE bot_conversation_states SET conversation_state = 'active', repeat_count = 0, is_anger_detected = 0 WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}
?>