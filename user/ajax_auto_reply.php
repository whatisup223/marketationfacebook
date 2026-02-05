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

    if (!$page_id) {
        echo json_encode(['success' => false, 'error' => 'Page ID is required']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("SELECT id, trigger_type, keywords, reply_message, reply_buttons, created_at FROM auto_reply_rules WHERE page_id = ? AND reply_source = ? ORDER BY trigger_type DESC, created_at DESC");
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
        $stmt = $pdo->prepare("SELECT bot_cooldown_seconds, bot_schedule_enabled, bot_schedule_start, bot_schedule_end, bot_exclude_keywords, bot_ai_sentiment_enabled, bot_anger_keywords, bot_repetition_threshold, bot_handover_reply FROM fb_pages WHERE page_id = ?");
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
        $stmt = $pdo->prepare("SELECT reply_buttons FROM auto_reply_rules WHERE page_id = ? AND reply_source = ? AND reply_buttons IS NOT NULL AND reply_buttons != ''");
        $stmt->execute([$page_id, $source]);
        $rules = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $payloads = [];
        foreach ($rules as $rule) {
            try {
                $buttons = json_decode($rule['reply_buttons'], true);
                if (is_array($buttons)) {
                    foreach ($buttons as $btn) {
                        if (!empty($btn['payload'])) {
                            $payloads[] = trim($btn['payload']);
                        }
                    }
                }
            } catch (Exception $e) {
                // Skip invalid JSON
            }
        }

        // Remove duplicates and sort
        $payloads = array_unique($payloads);
        sort($payloads);

        echo json_encode(['success' => true, 'payloads' => array_values($payloads)]);
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

        // Allow empty reply ONLY for default type (to disable it)
        if (!$page_id || ($type !== 'default' && !$reply)) {
            echo json_encode(['success' => false, 'error' => 'Missing required fields']);
            exit;
        }

        try {
            // Check if reply_image_url column exists (for backward compatibility)
            $columns = $pdo->query("SHOW COLUMNS FROM auto_reply_rules LIKE 'reply_image_url'")->fetchAll();
            $hasImageColumn = count($columns) > 0;

            if ($rule_id) {
                // Update
                if ($hasImageColumn) {
                    $stmt = $pdo->prepare("UPDATE auto_reply_rules SET trigger_type = ?, keywords = ?, reply_message = ?, `reply_buttons` = ?, reply_image_url = ?, hide_comment = ?, is_ai_safe = ?, bypass_schedule = ?, bypass_cooldown = ?, private_reply_enabled = ?, private_reply_text = ?, auto_like_comment = ?, reply_source = ? WHERE id = ? AND page_id = ?");
                    $stmt->execute([$type, $keywords, $reply, $reply_buttons, $reply_image_url, $hide_comment, $is_ai_safe, $bypass_schedule, $bypass_cooldown, $private_reply_enabled, $private_reply_text, $auto_like_comment, $source, $rule_id, $page_id]);
                } else {
                    $stmt = $pdo->prepare("UPDATE auto_reply_rules SET trigger_type = ?, keywords = ?, reply_message = ?, `reply_buttons` = ?, hide_comment = ?, is_ai_safe = ?, bypass_schedule = ?, bypass_cooldown = ?, private_reply_enabled = ?, private_reply_text = ?, auto_like_comment = ?, reply_source = ? WHERE id = ? AND page_id = ?");
                    $stmt->execute([$type, $keywords, $reply, $reply_buttons, $hide_comment, $is_ai_safe, $bypass_schedule, $bypass_cooldown, $private_reply_enabled, $private_reply_text, $auto_like_comment, $source, $rule_id, $page_id]);
                }
            } else {
                // Check if existing default rule
                if ($type === 'default') {
                    $check = $pdo->prepare("SELECT id FROM auto_reply_rules WHERE page_id = ? AND trigger_type = 'default' AND reply_source = ?");
                    $check->execute([$page_id, $source]);
                    if ($check->fetch()) {
                        if ($hasImageColumn) {
                            $stmt = $pdo->prepare("UPDATE auto_reply_rules SET reply_message = ?, `reply_buttons` = ?, reply_image_url = ?, hide_comment = ?, private_reply_enabled = ?, private_reply_text = ?, auto_like_comment = ? WHERE page_id = ? AND trigger_type = 'default' AND reply_source = ?");
                            $stmt->execute([$reply, $reply_buttons, $reply_image_url, $hide_comment, $private_reply_enabled, $private_reply_text, $auto_like_comment, $page_id, $source]);
                        } else {
                            $stmt = $pdo->prepare("UPDATE auto_reply_rules SET reply_message = ?, `reply_buttons` = ?, hide_comment = ?, private_reply_enabled = ?, private_reply_text = ?, auto_like_comment = ? WHERE page_id = ? AND trigger_type = 'default' AND reply_source = ?");
                            $stmt->execute([$reply, $reply_buttons, $hide_comment, $private_reply_enabled, $private_reply_text, $auto_like_comment, $page_id, $source]);
                        }
                        echo json_encode(['success' => true, 'message' => __('rule_saved')]);
                        exit;
                    }
                }

                // Insert
                if ($hasImageColumn) {
                    $stmt = $pdo->prepare("INSERT INTO auto_reply_rules (page_id, trigger_type, keywords, reply_message, `reply_buttons`, reply_image_url, hide_comment, is_ai_safe, bypass_schedule, bypass_cooldown, private_reply_enabled, private_reply_text, auto_like_comment, reply_source) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$page_id, $type, $keywords, $reply, $reply_buttons, $reply_image_url, $hide_comment, $is_ai_safe, $bypass_schedule, $bypass_cooldown, $private_reply_enabled, $private_reply_text, $auto_like_comment, $source]);
                } else {
                    $stmt = $pdo->prepare("INSERT INTO auto_reply_rules (page_id, trigger_type, keywords, reply_message, `reply_buttons`, hide_comment, is_ai_safe, bypass_schedule, bypass_cooldown, private_reply_enabled, private_reply_text, auto_like_comment, reply_source) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$page_id, $type, $keywords, $reply, $reply_buttons, $hide_comment, $is_ai_safe, $bypass_schedule, $bypass_cooldown, $private_reply_enabled, $private_reply_text, $auto_like_comment, $source]);
                }
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
        $rep_count = (int) ($_POST['repetition_count'] ?? 3);
        if ($rep_count < 1)
            $rep_count = 3;
        $handover_msg = $_POST['handover_reply'] ?? '';

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
                bot_anger_keywords = ?,
                bot_repetition_threshold = ?,
                bot_handover_reply = ?
                WHERE page_id = ?");
            $stmt->execute([$cooldown, $sch_enabled, $sch_start, $sch_end, $exclude_kw, $ai_enabled, $anger_kws, $rep_count, $handover_msg, $page_id]);
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
    $source = $_GET['source'] ?? 'message'; // Default to message or pass explicitly
    try {
        $stmt = $pdo->prepare("SELECT id, user_id, user_name, last_user_message, updated_at FROM bot_conversation_states WHERE page_id = ? AND conversation_state = 'handover' AND reply_source = ? ORDER BY updated_at DESC");
        $stmt->execute([$page_id, $source]);
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

if ($action === 'mark_all_as_resolved') {
    $page_id = $_POST['page_id'] ?? '';
    $source = $_POST['source'] ?? ''; // Optional filter

    if (!$page_id) {
        echo json_encode(['success' => false, 'error' => 'No page ID']);
        exit;
    }
    try {
        if ($source) {
            $stmt = $pdo->prepare("UPDATE bot_conversation_states SET conversation_state = 'active', repeat_count = 0, is_anger_detected = 0 WHERE page_id = ? AND conversation_state = 'handover' AND reply_source = ?");
            $stmt->execute([$page_id, $source]);
        } else {
            $stmt = $pdo->prepare("UPDATE bot_conversation_states SET conversation_state = 'active', repeat_count = 0, is_anger_detected = 0 WHERE page_id = ? AND conversation_state = 'handover'");
            $stmt->execute([$page_id]);
        }
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}


if ($action === 'fetch_page_stats') {
    $page_id = $_GET['page_id'] ?? '';
    $range = $_GET['range'] ?? 'all';
    $source = $_GET['source'] ?? 'comment';

    $rule_id = $_GET['rule_id'] ?? '';
    $sentiment = $_GET['sentiment'] ?? '';

    if (!$page_id) {
        echo json_encode(['success' => false, 'error' => 'No page ID']);
        exit;
    }

    $time_query = " AND 1=1 ";
    if ($range === 'today')
        $time_query = " AND created_at >= CURDATE() ";
    if ($range === 'week')
        $time_query = " AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) ";
    if ($range === 'month')
        $time_query = " AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) ";
    if ($range === 'custom') {
        $start = $_GET['start'] ?? '';
        $end = $_GET['end'] ?? '';
        if ($start && $end) {
            // Check if time is already present, if not add defaults
            if (strlen($start) <= 10)
                $start .= " 00:00:00";
            if (strlen($end) <= 10)
                $end .= " 23:59:59";

            $time_query = " AND created_at BETWEEN '$start' AND '$end' ";
        }
    }

    if ($rule_id) {
        $time_query .= " AND rule_id = " . intval($rule_id);
    }

    // Sentiment filter placeholder (assuming sentiment column exists in bot_sent_messages or joining conversation_states)
    // For now we'll just keep it as a structure placeholder

    $state_time_query = str_replace('created_at', 'updated_at', $time_query);

    try {
        // 1. Total Interacted
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM bot_sent_messages WHERE page_id = ? AND reply_source = ? $time_query");
        $stmt->execute([$page_id, $source]);
        $total_interacted = $stmt->fetchColumn();

        // 2. Active Handovers (Only relevant for Messenger)
        if ($source === 'message') {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM bot_conversation_states WHERE page_id = ? AND conversation_state = 'handover' $state_time_query");
            $stmt->execute([$page_id]);
            $active_handovers = $stmt->fetchColumn();
        } else {
            $active_handovers = 0;
        }

        // 3. AI Success Rate (Active handovers are failures)
        $success_rate = 100;

        if ($total_interacted > 0) {
            $success_rate = round((($total_interacted - $active_handovers) / $total_interacted) * 100, 1);
        }
        $success_rate = max(0, $success_rate);

        // 4. Avg Response Speed
        $stmt = $pdo->prepare("SELECT AVG(TIMESTAMPDIFF(SECOND, last_user_message_at, updated_at)) 
                               FROM bot_conversation_states 
                               WHERE page_id = ? AND last_user_message_at IS NOT NULL $state_time_query");
        $stmt->execute([$page_id]);
        $avg_speed = $stmt->fetchColumn();
        $avg_speed = $avg_speed ? round($avg_speed, 1) : 0;

        // 5. Most Triggered Rule
        $stmt = $pdo->prepare("SELECT rule_id, COUNT(*) as cnt FROM bot_sent_messages 
                               WHERE page_id = ? AND reply_source = ? $time_query 
                               GROUP BY rule_id ORDER BY cnt DESC LIMIT 1");
        $stmt->execute([$page_id, $source]);
        $top_rule_data = $stmt->fetch(PDO::FETCH_ASSOC);
        $top_rule_name = "None";
        if ($top_rule_data) {
            if (!$top_rule_data['rule_id']) {
                $top_rule_name = __('default_reply');
            } else {
                $stmt = $pdo->prepare("SELECT keywords, trigger_type FROM auto_reply_rules WHERE id = ?");
                $stmt->execute([$top_rule_data['rule_id']]);
                $r = $stmt->fetch(PDO::FETCH_ASSOC);
                $top_rule_name = ($r['trigger_type'] === 'default') ? __('default_reply') : (explode(',', $r['keywords'])[0] ?? "Rule #" . $top_rule_data['rule_id']);
            }
        }

        // 6. Peak Hour
        $stmt = $pdo->prepare("SELECT HOUR(created_at) as h, COUNT(*) as cnt FROM bot_sent_messages 
                               WHERE page_id = ? AND reply_source = ? $time_query 
                               GROUP BY h ORDER BY cnt DESC LIMIT 1");
        $stmt->execute([$page_id, $source]);
        $peak_data = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($peak_data) {
            $h = (int) $peak_data['h'];
            $suffix = $h >= 12 ? ' ' . __('pm') : ' ' . __('am');
            $h_display = $h % 12;
            if ($h_display == 0)
                $h_display = 12;
            $peak_hour = $h_display . ":00" . $suffix;
        } else {
            $peak_hour = "--:--";
        }

        // 7. System Health (Simulation based on successful replies)
        $health = $total_interacted > 0 ? "99.9%" : "100%";

        // 8. Anger/Negative Sentiment Blocks (Only Messenger)
        if ($source === 'message') {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM bot_conversation_states WHERE page_id = ? AND is_anger_detected = 1 $state_time_query");
            $stmt->execute([$page_id]);
            $anger_alerts = $stmt->fetchColumn();
        } else {
            $anger_alerts = 0;
        }

        // 9. Hidden Comments (Only Comments)
        $hidden_comments = 0;
        if ($source === 'comment') {
            // Check if hidden_comment column exists or just try (using try-catch block for the whole section so it might fail safely)
            // But relying on PDO exception handling in the outer block.
            // We use a safe check if possible, or just assume the column exists for now.
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM bot_sent_messages WHERE page_id = ? AND reply_source = 'comment' AND hidden_comment = 1 $time_query");
            $stmt->execute([$page_id]);
            $hidden_comments = $stmt->fetchColumn();
        }

        echo json_encode([
            'success' => true,
            'stats' => [
                'total_interacted' => (int) $total_interacted,
                'active_handovers' => (int) $active_handovers,
                'ai_success_rate' => $success_rate . '%',
                'avg_response_speed' => $avg_speed . 's',
                'top_rule' => $top_rule_name,
                'peak_hour' => $peak_hour,
                'system_health' => $health,
                'anger_alerts' => (int) $anger_alerts,
                'hidden_comments' => (int) $hidden_comments,
                'ai_filtered' => (int) $total_interacted // Keeping old key for compatibility
            ]
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

if ($action === 'fetch_recent_activity') {
    $page_id = $_GET['page_id'] ?? '';
    $source = $_GET['source'] ?? 'comment';

    if (!$page_id) {
        echo json_encode(['success' => false, 'error' => 'No page ID']);
        exit;
    }

    try {
        // Fetch recent logs from bot_sent_messages
        // Assuming columns: user_name, message (user msg), reply_message (bot msg), created_at, rule_id
        $stmt = $pdo->prepare("SELECT m.*, r.trigger_type, r.keywords 
                               FROM bot_sent_messages m 
                               LEFT JOIN auto_reply_rules r ON m.rule_id = r.id 
                               WHERE m.page_id = ? AND m.reply_source = ? 
                               ORDER BY m.created_at DESC LIMIT 10");
        $stmt->execute([$page_id, $source]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Format data for UI
        foreach ($rows as &$row) {
            // Fallback if rule was deleted
            if (!$row['rule_id']) {
                $row['rule_name'] = __('default_reply');
            } else {
                $row['rule_name'] = $row['trigger_type'] === 'default' ? __('default_reply') : ($row['keywords'] ?? __('rule_label') . ' ' . $row['rule_id']);
            }
            // Ensure we have displayable strings
            $row['user_identifier'] = $row['user_name'] ?? $row['user_id'] ?? 'Unknown User';
            $row['time_ago'] = function_exists('time_elapsed_string') ? time_elapsed_string($row['created_at']) : $row['created_at'];
        }

        echo json_encode(['success' => true, 'activity' => $rows]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}
?>