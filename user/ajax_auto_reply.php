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

// Quick check/migration: Ensure platform column exists in bot_conversation_states for FB/IG separation
try {
    // 1. bot_conversation_states
    $check = $pdo->query("SHOW COLUMNS FROM bot_conversation_states LIKE 'platform'");
    if (!$check->fetch()) {
        $pdo->exec("ALTER TABLE bot_conversation_states ADD COLUMN platform ENUM('facebook', 'instagram') DEFAULT 'facebook' AFTER reply_source");
        $pdo->exec("ALTER TABLE bot_conversation_states DROP INDEX page_user_source");
        $pdo->exec("ALTER TABLE bot_conversation_states ADD UNIQUE KEY `page_user_source_plt` (page_id, user_id, reply_source, platform)");
    }

    // 2. auto_reply_rules (Common columns for IG)
    $cols = $pdo->query("SHOW COLUMNS FROM auto_reply_rules")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('platform', $cols))
        $pdo->exec("ALTER TABLE auto_reply_rules ADD COLUMN platform ENUM('facebook', 'instagram') DEFAULT 'facebook'");
    if (!in_array('is_active', $cols))
        $pdo->exec("ALTER TABLE auto_reply_rules ADD COLUMN is_active TINYINT(1) DEFAULT 1");
    if (!in_array('usage_count', $cols))
        $pdo->exec("ALTER TABLE auto_reply_rules ADD COLUMN usage_count INT DEFAULT 0");
    if (!in_array('auto_like_comment', $cols))
        $pdo->exec("ALTER TABLE auto_reply_rules ADD COLUMN auto_like_comment TINYINT(1) DEFAULT 0");
    if (!in_array('private_reply_enabled', $cols))
        $pdo->exec("ALTER TABLE auto_reply_rules ADD COLUMN private_reply_enabled TINYINT(1) DEFAULT 0");
    if (!in_array('private_reply_text', $cols))
        $pdo->exec("ALTER TABLE auto_reply_rules ADD COLUMN private_reply_text TEXT NULL");
    if (!in_array('is_ai_safe', $cols))
        $pdo->exec("ALTER TABLE auto_reply_rules ADD COLUMN is_ai_safe TINYINT(1) DEFAULT 1");
    if (!in_array('bypass_schedule', $cols))
        $pdo->exec("ALTER TABLE auto_reply_rules ADD COLUMN bypass_schedule TINYINT(1) DEFAULT 0");
    if (!in_array('bypass_cooldown', $cols))
        $pdo->exec("ALTER TABLE auto_reply_rules ADD COLUMN bypass_cooldown TINYINT(1) DEFAULT 0");

    // 3. fb_pages (IG Support)
    $pg_cols = $pdo->query("SHOW COLUMNS FROM fb_pages")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('ig_business_id', $pg_cols))
        $pdo->exec("ALTER TABLE fb_pages ADD COLUMN ig_business_id VARCHAR(100) NULL");
    if (!in_array('ig_username', $pg_cols))
        $pdo->exec("ALTER TABLE fb_pages ADD COLUMN ig_username VARCHAR(100) NULL");
    if (!in_array('bot_cooldown_seconds', $pg_cols))
        $pdo->exec("ALTER TABLE fb_pages ADD COLUMN bot_cooldown_seconds INT DEFAULT 0");
    if (!in_array('bot_ai_sentiment_enabled', $pg_cols))
        $pdo->exec("ALTER TABLE fb_pages ADD COLUMN bot_ai_sentiment_enabled TINYINT(1) DEFAULT 1");
} catch (Exception $e) {
    // Silent fail
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

if ($action === 'fetch_handover_conversations') {
    $page_id = $_GET['page_id'] ?? '';
    $platform = $_GET['platform'] ?? 'facebook';
    $source = $_GET['source'] ?? 'comment'; // 'comment' or 'message'

    if (!$page_id) {
        echo json_encode(['success' => false, 'error' => 'Page ID is required']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("SELECT * FROM bot_conversation_states WHERE page_id = ? AND platform = ? AND reply_source = ? AND conversation_state = 'handover' ORDER BY updated_at DESC");
        $stmt->execute([$page_id, $platform, $source]);
        $conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['success' => true, 'conversations' => $conversations]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
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
        $cooldown = (int) ($_POST['cooldown_seconds'] ?? $_POST['cooldown'] ?? 0);
        $schedule_enabled = (int) ($_POST['schedule_enabled'] ?? 0);
        $schedule_start = $_POST['schedule_start'] ?? '00:00';
        $schedule_end = $_POST['schedule_end'] ?? '23:59';
        $exclude_keywords = (int) ($_POST['exclude_keywords'] ?? 0);
        $ai_sentiment = (int) ($_POST['ai_sentiment_enabled'] ?? $_POST['ai_sentiment'] ?? 1);
        $anger_keywords = $_POST['anger_keywords'] ?? '';
        $repetition_threshold = (int) ($_POST['repetition_count'] ?? $_POST['repetition_threshold'] ?? 3);
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
            // We join with fb_accounts to get the user token for refreshing the page token if needed
            $stmt = $pdo->prepare("SELECT p.page_access_token, p.page_id, p.account_id, a.access_token as user_access_token 
                                   FROM fb_pages p 
                                   JOIN fb_accounts a ON p.account_id = a.id 
                                   WHERE p.$id_column = ?");
            $stmt->execute([$page_id]);
            $page = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$page) {
                echo json_encode(['success' => false, 'error' => 'Page not found']);
                exit;
            }

            // For API subscription, we ALWAYS use the Facebook Page ID
            $target_subscribe_id = $page['page_id'];
            $page_token = $page['page_access_token'];
            $user_token = $page['user_access_token'];

            require_once '../includes/facebook_api.php';
            $fb = new FacebookAPI();

            // Attempt to get a fresh Page Access Token using User Token (Recommended by FB)
            // This ensures we have the latest permissions and correct scope
            $fresh_token = false;
            if (!empty($user_token)) {
                $fresh_token = $fb->getPageAccessToken($user_token, $target_subscribe_id);
                if ($fresh_token && $fresh_token !== $page_token) {
                    // Update DB with fresh token
                    $update = $pdo->prepare("UPDATE fb_pages SET page_access_token = ? WHERE page_id = ?");
                    $update->execute([$fresh_token, $target_subscribe_id]);
                    $page_token = $fresh_token;
                }
            }

            // Pass correct page_id, fresh token and platform to subscribe
            $res = $fb->subscribeApp($target_subscribe_id, $page_token, $platform);

            if (isset($res['success']) && $res['success']) {
                echo json_encode(['status' => 'success', 'message' => __('page_protected_success')]);
            } else {
                $error_msg = $res['error']['message'] ?? 'Unknown error';
                // Add specific advice for common errors
                if (strpos($error_msg, 'access token') !== false) {
                    $error_msg .= " (Try re-connecting your Facebook account)";
                }
                echo json_encode(['status' => 'error', 'message' => $error_msg]);
            }
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    if ($action === 'fetch_page_stats') {
        $page_id = $_REQUEST['page_id'] ?? '';
        $platform = $_REQUEST['platform'] ?? 'facebook';
        $range = $_REQUEST['range'] ?? 'all';
        $source = $_REQUEST['source'] ?? 'comment';

        if (!$page_id) {
            echo json_encode(['success' => false, 'error' => 'Page ID is required']);
            exit;
        }

        try {
            // 1. Total Interacted (from bot_conversation_states)
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM bot_conversation_states WHERE page_id = ? AND platform = ? AND reply_source = ?");
            $stmt->execute([$page_id, $platform, $source]);
            $total_interacted = $stmt->fetchColumn();

            // 2. Active Handovers
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM bot_conversation_states WHERE page_id = ? AND platform = ? AND reply_source = ? AND conversation_state = 'handover'");
            $stmt->execute([$page_id, $platform, $source]);
            $active_handovers = $stmt->fetchColumn();

            // 3. Anger Alerts
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM bot_conversation_states WHERE page_id = ? AND platform = ? AND reply_source = ? AND is_anger_detected = 1");
            $stmt->execute([$page_id, $platform, $source]);
            $anger_alerts = $stmt->fetchColumn();

            // AI Success Rate (Resolved / (Resolved + Handover))
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM bot_conversation_states WHERE page_id = ? AND platform = ? AND reply_source = ? AND conversation_state = 'resolved'");
            $stmt->execute([$page_id, $platform, $source]);
            $resolved = $stmt->fetchColumn();

            $success_rate = ($resolved + $active_handovers) > 0 ? round(($resolved / ($resolved + $active_handovers)) * 100) . '%' : '100%';

            echo json_encode([
                'success' => true,
                'stats' => [
                    'total_interacted' => $total_interacted,
                    'active_handovers' => $active_handovers,
                    'anger_alerts' => $anger_alerts,
                    'ai_success_rate' => $success_rate,
                    'avg_response_speed' => '0s',
                    'top_rule' => '--',
                    'peak_hour' => '--:--',
                    'system_health' => '100%',
                    'ai_filtered' => 0
                ]
            ]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    if ($action === 'mark_as_resolved') {
        $id = $_POST['id'] ?? '';
        if (!$id) {
            echo json_encode(['success' => false, 'error' => 'ID is required']);
            exit;
        }
        try {
            $stmt = $pdo->prepare("UPDATE bot_conversation_states SET conversation_state = 'resolved', is_anger_detected = 0, repeat_count = 0 WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    if ($action === 'mark_all_as_resolved') {
        $page_id = $_POST['page_id'] ?? '';
        $platform = $_POST['platform'] ?? 'facebook';
        $source = $_POST['source'] ?? 'comment';

        if (!$page_id) {
            echo json_encode(['success' => false, 'error' => 'Page ID is required']);
            exit;
        }
        try {
            $stmt = $pdo->prepare("UPDATE bot_conversation_states SET conversation_state = 'resolved', is_anger_detected = 0, repeat_count = 0 WHERE page_id = ? AND platform = ? AND reply_source = ?");
            $stmt->execute([$page_id, $platform, $source]);
            echo json_encode(['success' => true]);
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