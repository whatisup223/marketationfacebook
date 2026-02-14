<?php
/**
 * Universal Webhook Handler
 * Handles both Evolution API (WhatsApp) and Facebook (Comments & Messenger)
 */

$logFile = __DIR__ . '/webhook_log.txt';
$input = file_get_contents('php://input');
// For debugging - remove in production
file_put_contents($logFile, date('Y-m-d H:i:s') . " - Input: " . $input . "\n", FILE_APPEND);

function debugLog($msg)
{
    $logFile = __DIR__ . '/debug_webhook.txt';
    file_put_contents($logFile, date('Y-m-d H:i:s') . " - " . (is_array($msg) ? json_encode($msg) : $msg) . "\n", FILE_APPEND);
}

// 1. Handle Facebook Verification (GET request)
if (isset($_GET['hub_mode']) && $_GET['hub_mode'] === 'subscribe') {
    $uid = $_GET['uid'] ?? '';
    $verify_token = $_GET['hub_verify_token'] ?? '';
    $challenge = $_GET['hub_challenge'] ?? '';

    if ($uid) {
        require_once __DIR__ . '/includes/db_config.php';
        $stmt = $pdo->prepare("SELECT verify_token FROM users WHERE webhook_token = ?");
        $stmt->execute([$uid]);
        $user_verify_token = $stmt->fetchColumn();

        if ($user_verify_token && $verify_token === $user_verify_token) {
            echo $challenge;
            exit;
        }
    }
    http_response_code(403);
    exit;
}

// 2. Handle Data Payload (POST request)
$data = json_decode($input, true);
if (!$data) {
    exit;
}

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/facebook_api.php';
require_once __DIR__ . '/includes/ComplianceEngine.php';

$pdo = getDB();

// --- PRODUCTION PATCH: Ensure columns exist before using them ---
try {
    $cols = $pdo->query("SHOW COLUMNS FROM fb_pages")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('ig_business_id', $cols))
        $pdo->exec("ALTER TABLE fb_pages ADD COLUMN ig_business_id VARCHAR(100) NULL");

    $rules_cols = $pdo->query("SHOW COLUMNS FROM auto_reply_rules")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('platform', $rules_cols))
        $pdo->exec("ALTER TABLE auto_reply_rules ADD COLUMN platform ENUM('facebook', 'instagram') DEFAULT 'facebook'");
    if (!in_array('auto_like_comment', $rules_cols))
        $pdo->exec("ALTER TABLE auto_reply_rules ADD COLUMN auto_like_comment TINYINT(1) DEFAULT 0");
    if (!in_array('private_reply_enabled', $rules_cols))
        $pdo->exec("ALTER TABLE auto_reply_rules ADD COLUMN private_reply_enabled TINYINT(1) DEFAULT 0");
} catch (Exception $em) {
    debugLog("Migration Error: " . $em->getMessage());
}

// A. Check if it's Facebook Webhook
if (isset($data['object']) && ($data['object'] === 'page' || $data['object'] === 'instagram')) {
    file_put_contents('debug_webhook.txt', date('Y-m-d H:i:s') . " - Processing FB Event: " . json_encode($data) . "\n", FILE_APPEND);
    try {
        handleFacebookEvent($data, $pdo);
    } catch (Exception $e) {
        file_put_contents('debug_webhook.txt', date('Y-m-d H:i:s') . " - EXCEPTION: " . $e->getMessage() . "\n", FILE_APPEND);
    }
    exit;
}

// B. Check if it's Evolution API (WhatsApp)
if (isset($data['instance']) && isset($data['event'])) {
    handleEvolutionEvent($data, $pdo);
    exit;
}

// ----------------------------------------------------------------------
// Facebook Handler
// ----------------------------------------------------------------------
function handleFacebookEvent($data, $pdo)
{
    $platform = ($data['object'] === 'instagram') ? 'instagram' : 'facebook';
    foreach ($data['entry'] as $entry) {
        $id = $entry['id'] ?? ''; // This could be Page ID or Instagram Business ID

        // Handle Messaging (Messenger)
        if (isset($entry['messaging'])) {
            foreach ($entry['messaging'] as $messaging) {
                $sender_id = $messaging['sender']['id'] ?? '';
                if ($sender_id == $id)
                    continue; // Skip messages sent by the page/account itself

                // Check for Text Message, Quick Reply, or Postback (Button Click)
                $message_text = '';
                if (isset($messaging['message']['quick_reply']['payload'])) {
                    $message_text = $messaging['message']['quick_reply']['payload'];
                } elseif (isset($messaging['message']['text'])) {
                    $message_text = $messaging['message']['text'];
                } elseif (isset($messaging['postback']['payload'])) {
                    $message_text = $messaging['postback']['payload'];
                }

                if (!$message_text)
                    continue;

                processAutoReply($pdo, $id, $sender_id, $message_text, 'message', null, '', $platform);
            }
        }

        // Handle Feed/Comments (Comments)
        if (isset($entry['changes'])) {
            foreach ($entry['changes'] as $change) {
                if ($change['field'] === 'feed' || $change['field'] === 'comments') {
                    $val = $change['value'];
                    // For Instagram, sometimes it's not under 'item'
                    $item = $val['item'] ?? ($platform === 'instagram' ? 'comment' : '');
                    if ($item === 'comment') {
                        $verb = $val['verb'] ?? ($platform === 'instagram' ? 'add' : '');
                        // Force 'add' for Instagram if not present, as IG webhook structure differs
                        if ($platform === 'instagram' && empty($verb)) {
                            $verb = 'add';
                        }

                        if ($verb === 'add' || $verb === 'edited') {
                            $sender_id = $val['from']['id'] ?? '';
                            if ($sender_id == $id)
                                continue;

                            $comment_id = $val['comment_id'] ?? $val['id'] ?? '';
                            $post_id = $val['post_id'] ?? $val['media_id'] ?? '';
                            $parent_id = $val['parent_id'] ?? null;

                            // Instagram comment text is often just under 'text'
                            $message_text = $val['message'] ?? $val['text'] ?? '';
                            $sender_name = $val['from']['name'] ?? $val['from']['username'] ?? '';

                            debugLog("Processing Comment: ID=$comment_id, Post=$post_id, Parent=$parent_id, Platform=$platform, Verb=$verb");

                            // 1. Check Moderation (Must check for both additions and edits)
                            $is_moderated = processModeration($pdo, $id, $comment_id, $message_text, $sender_name, $platform, $post_id);
                            debugLog("Moderation Final Result: " . ($is_moderated ? "MODERATED (Action Taken)" : "PASSED (No Violation)"));

                            // 2. Only Auto-Reply if it's a NEW comment and NOT moderated
                            if ($verb === 'add' && !$is_moderated) {
                                debugLog("Triggering Auto-Reply flow for $comment_id");
                                // We pass parent_id to skip private replies on nested comments for IG later
                                processAutoReply($pdo, $id, $comment_id, $message_text, 'comment', $sender_id, $sender_name, $platform, $post_id, $parent_id);
                            }
                        }
                    }
                }
            }
        }
    }
}

function processAutoReply($pdo, $page_id, $target_id, $incoming_text, $source, $actual_sender_id = null, $sender_name = '', $platform = 'facebook', $post_id = null, $parent_id = null)
{
    // 1. Fetch Page Settings (Token, Schedule, Cooldown, AI Intelligence)
    // Try primary lookup by whichever ID Meta sent
    $stmt = $pdo->prepare("SELECT p.page_id as fb_page_id, p.page_access_token, p.page_name, p.bot_cooldown_seconds, p.bot_schedule_enabled, p.bot_schedule_start, p.bot_schedule_end, p.bot_exclude_keywords, p.bot_ai_sentiment_enabled, p.bot_anger_keywords, p.bot_repetition_threshold, p.bot_handover_reply, a.user_id 
                           FROM fb_pages p 
                           JOIN fb_accounts a ON p.account_id = a.id 
                           WHERE p.page_id = ? OR p.ig_business_id = ?");
    $stmt->execute([$page_id, $page_id]);
    $page = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$page || !$page['page_access_token'])
        return;

    // Match Rule ID: Dashboard usually saves by the ID provided in the dropdown
    // For Instagram it's Business ID, for Facebook it's Page ID
    $db_page_id = $page_id;

    // Use FB Page ID for API actor
    $fb_page_id = $page['fb_page_id'];
    $api_actor_id = $fb_page_id;
    $access_token = $page['page_access_token'];
    $customer_id = ($source === 'message') ? $target_id : $actual_sender_id;

    // Fetch user name for Messenger if empty
    if ($source === 'message' && empty($sender_name) && $customer_id) {
        $fb = new FacebookAPI();
        $res = $fb->makeRequest($customer_id, ['fields' => 'name'], $access_token);
        if (isset($res['name']))
            $sender_name = $res['name'];
    }

    // 2. Check Conversation State (Handover Protocol)
    if ($customer_id) {
        $stmt = $pdo->prepare("SELECT * FROM bot_conversation_states WHERE page_id = ? AND user_id = ? AND reply_source = ? AND platform = ? LIMIT 1");
        $stmt->execute([$page_id, $customer_id, $source, $platform]);
        $state = $stmt->fetch(PDO::FETCH_ASSOC);

        // If state is handover, bot is manually silenced for this user
        if ($state && $state['conversation_state'] === 'handover') {
            return;
        }

    }

    // 2. Find Rule Match
    // Fetch ALL active rules for this page, platform and source
    // Search using both possible IDs (Page ID and IG Business ID) for maximum robustness
    $stmt = $pdo->prepare("SELECT * FROM auto_reply_rules WHERE (page_id = ? OR page_id = ?) AND reply_source = ? AND platform = ? AND is_active = 1 ORDER BY trigger_type DESC, id DESC");
    $stmt->execute([$page['fb_page_id'], $page['ig_business_id'], $source, $platform]);
    $all_rules = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $matched_rule = null;
    $reply_msg = '';
    $hide_comment = 0;
    $is_keyword_match = false;

    // 2.1 Keyword matching loop
    foreach ($all_rules as $rule) {
        if ($rule['trigger_type'] !== 'keyword')
            continue;

        $keywords = preg_split('/[,،]/u', $rule['keywords']);
        foreach ($keywords as $kw) {
            $kw = trim($kw);
            if (empty($kw))
                continue;

            // Improved matching: Whole Word Match using Regex + fallback substring
            $pattern = '/(?<![\p{L}\p{N}])' . preg_quote($kw, '/') . '(?![\p{L}\p{N}])/ui';
            if (preg_match($pattern, $incoming_text) || mb_stripos($incoming_text, $kw) !== false) {
                $matched_rule = $rule;
                $reply_msg = $rule['reply_message'];
                $hide_comment = (int) $rule['hide_comment'];
                $is_keyword_match = true;
                debugLog("Matched Keyword Rule: '$kw' for Input: '$incoming_text'");
                break 2;
            }
        }
    }

    // 2.2 Global Anger Detection (Only if NO keyword match found)
    // If we have a direct keyword match, we assume specific intent and reply regardless of tone.
    // If we're falling back to default/AI, we check for anger first.
    if (!$reply_msg && $page['bot_ai_sentiment_enabled'] && !empty($page['bot_anger_keywords'])) {
        $anger_kws = explode(',', $page['bot_anger_keywords']);
        foreach ($anger_kws as $akw) {
            $akw = trim($akw);
            if (!empty($akw) && mb_stripos($incoming_text, $akw) !== false) {
                // Anger detected! Switch to handover
                $stmt = $pdo->prepare("INSERT INTO bot_conversation_states (page_id, user_id, user_name, last_user_message, last_user_message_at, conversation_state, is_anger_detected, reply_source, platform) 
                                        VALUES (?, ?, ?, ?, NOW(), 'handover', 1, ?, ?) 
                                        ON DUPLICATE KEY UPDATE user_name = VALUES(user_name), last_user_message = VALUES(last_user_message), last_user_message_at = NOW(), conversation_state = 'handover', is_anger_detected = 1");
                $stmt->execute([$page_id, $customer_id, $sender_name, $incoming_text, $source, $platform]);

                // Add Internal Notification
                $notify_title = 'handover_notification_title';
                $section_key = ($source === 'message') ? 'nav_messenger_bot' : 'nav_auto_reply';

                $notify_msg = json_encode([
                    'key' => 'handover_notification_msg',
                    'params' => [$page['page_name'], $section_key],
                    'param_keys' => [1]
                ]);

                $notify_link = ($source === 'message') ? 'user/page_messenger_bot.php' : 'user/page_auto_reply.php';
                addNotification($page['user_id'], $notify_title, $notify_msg, $notify_link);

                // --- SEND EMAIL ALERT ---
                triggerHandoverEmail($pdo, $page['user_id'], $page['page_name'], $source, $sender_name, $incoming_text);

                // Send Handover Reply if set (Anger case)
                if (!empty($page['bot_handover_reply'])) {
                    $fb = new FacebookAPI();
                    $h_msg = $page['bot_handover_reply'];

                    // 1. Spintax
                    if (strpos($h_msg, '|') !== false) {
                        $opts = explode('|', $h_msg);
                        $h_msg = trim($opts[array_rand($opts)]);
                    }

                    if ($source === 'message') {
                        // Messenger: Link Name
                        $p_msg = str_replace('{name}', $sender_name, $h_msg);
                        $fb->sendMessage($api_actor_id, $access_token, $customer_id, $p_msg);
                    } else {
                        // Comments: Smart Mention for Public, Name for Private
                        $mention = ($platform === 'instagram') ? '@' . $sender_name : '@[' . $customer_id . ']';
                        $pub_msg = str_replace('{name}', $mention, $h_msg);
                        $priv_msg = str_replace('{name}', $sender_name, $h_msg);

                        // Execute All
                        $fb->likeComment($target_id, $access_token, $platform);
                        $fb->replyToComment($target_id, $pub_msg, $access_token, $platform);
                        try {
                            $res_p = $fb->replyPrivateToComment($target_id, $priv_msg, $access_token, $platform);
                            debugLog("Handover Private Reply Result: " . json_encode($res_p));
                        } catch (Exception $ext) {
                            debugLog("Handover Private Reply ERROR: " . $ext->getMessage());
                        }
                    }
                }

                return; // SILENCE immediately
            }
        }
    }

    // 2.3 If no keyword match AND no anger silence, try Default Rule
    if (!$matched_rule) {
        foreach ($all_rules as $rule) {
            if ($rule['trigger_type'] === 'default') {
                $matched_rule = $rule;
                $reply_msg = $rule['reply_message'];
                $hide_comment = (int) $rule['hide_comment'];
                debugLog("Matched Default Rule for $target_id");
                break;
            }
        }
    }

    if (!$matched_rule) {
        debugLog("No rule matched (keywords or default) for $target_id");
        return;
    }

    // Capture Private Reply Settings from the matched rule
    $private_reply_enabled = (int) ($matched_rule['private_reply_enabled'] ?? 0);
    $private_reply_text = $matched_rule['private_reply_text'] ?? '';
    // Capture Auto Like Settings (Support aliases for safety)
    $auto_like_comment = (int) ($matched_rule['auto_like_comment'] ?? $matched_rule['auto_like'] ?? 0);

    debugLog("Rule Settings for $target_id: Like=" . ($auto_like_comment ? "YES" : "NO") . ", Private=" . ($private_reply_enabled ? "YES" : "NO") . ", Platform=" . $platform);

    // --- QUICK WINS IMPLEMENTATION ---
    // 1. Random Variations (Spintax): explode by | and pick random
    if (strpos($reply_msg, '|') !== false) {
        $options = explode('|', $reply_msg);
        $reply_msg = trim($options[array_rand($options)]);
    }
    if ($private_reply_enabled && !empty($private_reply_text) && strpos($private_reply_text, '|') !== false) {
        $options = explode('|', $private_reply_text);
        $private_reply_text = trim($options[array_rand($options)]);
    }

    // 2. Mention User: Smart replacement based on source and destination
    // For the PUBLIC COMMENT: We use @[ID] (Blue mention)
    // For ANY MESSAGE (Private or Direct): We use the Name
    if ($source === 'comment') {
        // Public Reply (The actual comment)
        if (!empty($customer_id)) {
            $mention = ($platform === 'instagram') ? '@' . $sender_name : '@[' . $customer_id . ']';
            $reply_msg = str_replace('{name}', $mention, $reply_msg);
        }
        // Private Reply (The inbox message sent after comment)
        if (!empty($sender_name)) {
            $private_reply_text = str_replace('{name}', $sender_name, $private_reply_text);
        }
    } else {
        // Source is 'message' (Direct Messenger Bot)
        if (!empty($sender_name)) {
            $reply_msg = str_replace('{name}', $sender_name, $reply_msg);
            $private_reply_text = str_replace('{name}', $sender_name, $private_reply_text);
        }
    }

    // 4. Advanced SaaS Logic (Repeat Detection only)
    $is_ai_safe = (int) ($matched_rule['is_ai_safe'] ?? 1);

    if ($page['bot_ai_sentiment_enabled'] && $is_ai_safe && $customer_id) {
        // Anger Detection moved to top (Global Check)

        // B. Repeat Detection (Configurable Threshold)
        if ($state && $state['last_bot_reply_text'] === $reply_msg) {
            $threshold = (int) ($page['bot_repetition_threshold'] ?? 3);
            if ($threshold < 1)
                $threshold = 3;

            $new_count = $state['repeat_count'] + 1;

            if ($new_count >= $threshold) {
                // Too many repeats! Switch to handover
                $stmt = $pdo->prepare("UPDATE bot_conversation_states SET conversation_state = 'handover', repeat_count = ?, last_user_message = ?, last_user_message_at = NOW(), user_name = ? WHERE id = ?");
                $stmt->execute([$new_count, $incoming_text, $sender_name, $state['id']]);

                // Add Internal Notification
                $notify_title = 'handover_notification_title';
                $section_key = ($source === 'message') ? 'nav_messenger_bot' : 'nav_auto_reply';

                $notify_msg = json_encode([
                    'key' => 'handover_notification_msg',
                    'params' => [$page['page_name'], $section_key],
                    'param_keys' => [1]
                ]);

                $notify_link = ($source === 'message') ? 'user/page_messenger_bot.php' : 'user/page_auto_reply.php';
                addNotification($page['user_id'], $notify_title, $notify_msg, $notify_link);

                // --- SEND EMAIL ALERT ---
                triggerHandoverEmail($pdo, $page['user_id'], $page['page_name'], $source, $sender_name, $incoming_text);

                // Send Handover Reply if set
                if (!empty($page['bot_handover_reply'])) {
                    $fb = new FacebookAPI();
                    $h_msg = $page['bot_handover_reply'];

                    if (strpos($h_msg, '|') !== false) {
                        $opts = explode('|', $h_msg);
                        $h_msg = trim($opts[array_rand($opts)]);
                    }

                    if ($source === 'message') {
                        $p_msg = str_replace('{name}', $sender_name, $h_msg);
                        $fb->sendMessage($api_actor_id, $access_token, $customer_id, $p_msg);
                    } else {
                        $pub_msg = str_replace('{name}', $mention, $h_msg);
                        $priv_msg = str_replace('{name}', $sender_name, $h_msg);

                        $fb->likeComment($target_id, $access_token, $platform);
                        $fb->replyToComment($target_id, $pub_msg, $access_token, $platform);
                        try {
                            $private_res = $fb->replyPrivateToComment($target_id, $priv_msg, $access_token, $platform);
                            debugLog("Private Reply Res for $target_id: " . json_encode($private_res));
                        } catch (Exception $e) {
                            debugLog("Private Reply ERROR for $target_id: " . $e->getMessage());
                        }
                    }
                }

                return; // SILENCE
            }
        }
    }

    // 5. Decision Logic: Schedule & Cooldown
    $bypass_schedule = (int) ($matched_rule['bypass_schedule'] ?? 0);
    $bypass_cooldown = (int) ($matched_rule['bypass_cooldown'] ?? 0);

    // Compatibility: If it's a keyword match and old global exclude is ON, force bypass
    if ($is_keyword_match && (int) $page['bot_exclude_keywords'] === 1) {
        $bypass_schedule = 1;
        $bypass_cooldown = 1;
    }

    // 5.1 Schedule Check
    if (!$bypass_schedule && $page['bot_schedule_enabled']) {
        date_default_timezone_set('Africa/Cairo');
        $now = date('H:i');
        $start = !empty($page['bot_schedule_start']) ? substr($page['bot_schedule_start'], 0, 5) : '00:00';
        $end = !empty($page['bot_schedule_end']) ? substr($page['bot_schedule_end'], 0, 5) : '23:59';

        $is_inside = ($start <= $end) ? ($now >= $start && $now <= $end) : ($now >= $start || $now <= $end);
        if (!$is_inside)
            return;
    }

    // 5.2 Cooldown / Human Takeover Logic
    $cooldown_seconds = (int) ($page['bot_cooldown_seconds'] ?? 0);
    $should_check_cooldown = ($cooldown_seconds > 0 && !$bypass_cooldown);

    if ($should_check_cooldown) {
        // Cooldown check is only relevant for Messenger or identification-based sources
        // For comments, we check the user thread via target_id (sender_id)
        $thread_user_id = ($source === 'message') ? $target_id : $actual_sender_id;
        $fb = new FacebookAPI();

        // A. Check Messenger Conversation for Admin activity
        if ($thread_user_id) {
            $convs = $fb->makeRequest("{$api_actor_id}/conversations", [
                'fields' => 'messages.limit(5){id,from,created_time}'
            ], $access_token);

            if (isset($convs['data'][0]['messages']['data'])) {
                foreach ($convs['data'][0]['messages']['data'] as $msg) {
                    $msg_sender_id = $msg['from']['id'] ?? '';
                    $fb_msg_id = $msg['id'] ?? '';

                    if ($msg_sender_id == $api_actor_id || $msg_sender_id == $page_id) {
                        // Check if this ID is in our bot_sent_messages table
                        $chk = $pdo->prepare("SELECT id FROM bot_sent_messages WHERE message_id = ? OR ? LIKE CONCAT('%', message_id, '%') OR message_id LIKE CONCAT('%', ?, '%') LIMIT 1");
                        $chk->execute([$fb_msg_id, $fb_msg_id, $fb_msg_id]);

                        if (!$chk->fetch()) {
                            // Message from Page, NOT in Bot database -> It's a Human Admin
                            $created_time = strtotime($msg['created_time']);
                            if ((time() - $created_time) < $cooldown_seconds) {
                                return; // SILENCE
                            }
                            break;
                        }
                        // If it's the bot, we continue to check older messages
                    }
                }
            }
        }

        // B. Check Comment Replies if source is comment
        if ($source === 'comment') {
            $replies = $fb->makeRequest("{$target_id}/comments", [
                'fields' => 'id,from,created_time',
                'limit' => 5
            ], $access_token);

            if (isset($replies['data'])) {
                foreach ($replies['data'] as $reply) {
                    $reply_sender_id = $reply['from']['id'] ?? '';
                    $fb_reply_id = $reply['id'] ?? '';

                    if ($reply_sender_id == $api_actor_id || $reply_sender_id == $page_id) {
                        // Robust check for comment IDs
                        $chk = $pdo->prepare("SELECT id FROM bot_sent_messages WHERE message_id = ? OR ? LIKE CONCAT('%', message_id, '%') OR message_id LIKE CONCAT('%', ?, '%') LIMIT 1");
                        $chk->execute([$fb_reply_id, $fb_reply_id, $fb_reply_id]);

                        if (!$chk->fetch()) {
                            // Reply from Page, NOT in Bot database -> It's a Human Admin
                            $created_time = strtotime($reply['created_time']);
                            if ((time() - $created_time) < $cooldown_seconds) {
                                return; // SILENCE
                            }
                            break;
                        }
                    }
                }
            }
        }
    }

    // 5. Execute Reply
    // COMPLIANCE CHECK: Init Engine
    $compliance = new ComplianceEngine($pdo, $page_id, $access_token);

    // A. Track this user interaction (opens window)
    $compliance->refreshLastInteraction($customer_id, $source);

    // B. Check if we CAN send (Rate Limit + Protocol)
    $msg_type = ($source === 'comment') ? 'COMMENT' : 'RESPONSE';
    $policy = $compliance->canSendMessage($customer_id, $msg_type);
    if (!$policy['allowed']) {
        // Log this rejection for debugging
        file_put_contents(__DIR__ . '/debug_compliance.txt', date('Y-m-d H:i:s') . " - BLOCKED: " . $policy['reason'] . "\n", FILE_APPEND);
        return;
    }

    $fb = new FacebookAPI();
    $res = null;
    $buttons = isset($matched_rule['reply_buttons']) ? json_decode($matched_rule['reply_buttons'], true) : null;
    $image_url = isset($matched_rule['reply_image_url']) ? $matched_rule['reply_image_url'] : null;

    if ($source === 'message') {
        // Send image first if exists
        if (!empty($image_url)) {
            $fb->sendImageMessage($api_actor_id, $access_token, $target_id, $image_url);
        }

        // Then send text with buttons
        if ($buttons && is_array($buttons) && count($buttons) > 0) {
            $res = $fb->sendButtonMessage($api_actor_id, $access_token, $target_id, $reply_msg, $buttons);
        } else {
            $res = $fb->sendMessage($api_actor_id, $access_token, $target_id, $reply_msg);
        }

        error_log("WEBHOOK DEBUG: Messenger Send Res: " . json_encode($res));
    } else {
        $res = $fb->replyToComment($target_id, $reply_msg, $access_token, $platform);

        // DEBUG: Log the result of the comment reply
        file_put_contents(__DIR__ . '/debug_fb.txt', date('Y-m-d H:i:s') . " - Comment Reply Res: " . json_encode($res) . " | ID: $target_id | Msg: $reply_msg\n", FILE_APPEND);

        if ($hide_comment) {
            $fb->hideComment($target_id, $access_token, true, $platform);
        }

        // --- NEW: PRIVATE REPLY TO COMMENT ---
        // For Instagram, private_replies only works for TSL (Top Level Comments) and NOT Story comments.
        // Story comments in webhooks often lack media info or have specific flags.
        $is_ig_reply = ($platform === 'instagram' && !empty($parent_id) && $parent_id !== $post_id);

        if ($private_reply_enabled && !empty($private_reply_text)) {
            if ($is_ig_reply) {
                debugLog("Skipping IG Private Reply: Nested comments not supported by API ($target_id)");
            } else {
                try {
                    $p_res = $fb->replyPrivateToComment($target_id, $private_reply_text, $access_token, $platform);
                    debugLog("Auto-Reply Private Result for $target_id: " . json_encode($p_res));
                    file_put_contents(__DIR__ . '/debug_fb.txt', date('Y-m-d H:i:s') . " - Private Reply Res: " . json_encode($p_res) . " | ID: $target_id\n", FILE_APPEND);
                } catch (Exception $ep) {
                    debugLog("Auto-Reply Private ERROR for $target_id: " . $ep->getMessage());
                }
            }
        }

        // --- NEW: AUTO LIKE COMMENT ---
        if ($auto_like_comment) {
            try {
                $like_res = $fb->likeComment($target_id, $access_token, $platform);
                debugLog("Auto-Like Result for $target_id: " . json_encode($like_res));
                file_put_contents(__DIR__ . '/debug_fb.txt', date('Y-m-d H:i:s') . " - Auto-Like Res: " . json_encode($like_res) . " | ID: $target_id\n", FILE_APPEND);
            } catch (Exception $el) {
                debugLog("Auto-Like ERROR for $target_id: " . $el->getMessage());
            }
        }
    }

    // 7. Update Tracking (ID Log & Conversation State)
    $sent_id = $res['id'] ?? $res['message_id'] ?? null;
    if ($sent_id) {
        $rule_id = $matched_rule['id'] ?? null;
        $stmt = $pdo->prepare("INSERT IGNORE INTO bot_sent_messages (message_id, page_id, rule_id, reply_source, user_id) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$sent_id, $page_id, $rule_id, $source, $customer_id]);

        // --- NEW: Increment Usage Count ---
        if ($rule_id) {
            try {
                // Check if usage_count exists first (safe update)
                $check = $pdo->query("SHOW COLUMNS FROM auto_reply_rules LIKE 'usage_count'")->fetchAll();
                if (count($check) > 0) {
                    $stmt = $pdo->prepare("UPDATE auto_reply_rules SET usage_count = COALESCE(usage_count, 0) + 1 WHERE id = ?");
                    $stmt->execute([$rule_id]);
                }
            } catch (Exception $e) {
                // Ignore
            }
        }
    }

    if ($customer_id) {
        $repeat_val = ($state && $state['last_bot_reply_text'] === $reply_msg) ? $state['repeat_count'] + 1 : 1;
        $stmt = $pdo->prepare("INSERT INTO bot_conversation_states (page_id, user_id, user_name, last_user_message, last_user_message_at, conversation_state, last_bot_reply_text, repeat_count, reply_source, platform) 
                               VALUES (?, ?, ?, ?, NOW(), 'active', ?, 1, ?, ?) 
                               ON DUPLICATE KEY UPDATE user_name = VALUES(user_name), last_user_message = VALUES(last_user_message), last_user_message_at = NOW(), last_bot_reply_text = ?, repeat_count = ?, conversation_state = 'active'");
        $stmt->execute([$page_id, $customer_id, $sender_name, $incoming_text, $reply_msg, $source, $platform, $reply_msg, $repeat_val]);
    }
}

function processModeration($pdo, $id, $comment_id, $message_text, $sender_name = '', $platform = 'facebook', $post_id = null)
{
    // Get Moderation Settings
    $stmt = $pdo->prepare("SELECT * FROM fb_moderation_rules WHERE page_id = ? AND platform = ? LIMIT 1");
    $stmt->execute([$id, $platform]);
    $rules = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$rules || (isset($rules['is_active']) && !$rules['is_active'])) {
        debugLog("No active moderation rules found for Page ID: $id on Platform: $platform");
        return false;
    }
    debugLog("Moderation Rules Found for $id ($platform): Phones=" . ($rules['hide_phones'] ? 'ON' : 'OFF') . ", Links=" . ($rules['hide_links'] ? 'ON' : 'OFF') . ", Action=" . $rules['action_type']);

    $violation = false;
    $reason = "";

    // A. Check Phone Numbers - Improved Regex for International and Local formats + Arabic digits
    if ($rules['hide_phones']) {
        // Matches typical numbers 8+ digits, supports Arabic digits ٠-٩
        $phone_pattern = '/(\+?[\d٠-٩]{1,4}[\s-]?[\d٠-٩]{7,14})|([\d٠-٩]{8,15})/u';
        if (preg_match($phone_pattern, $message_text)) {
            $violation = true;
            $reason = "Phone Number Detected";
        }
    }

    // B. Check Links/URLs - Improved Regex + /u flag
    if (!$violation && $rules['hide_links']) {
        // Matches http, www, and common TLDs to catch sneaky links
        $link_pattern = '/(https?:\/\/[^\s]+)|(www\.[^\s]+)|([a-zA-Z0-9-]+\.(com|net|org|me|info|biz|co|app|xyz|site|online|link))/iu';
        if (preg_match($link_pattern, $message_text)) {
            $violation = true;
            $reason = "Link Detected";
        }
    }

    // C. Check Banned Keywords
    if (!$violation && !empty($rules['banned_keywords'])) {
        $keywords = preg_split('/[,،]/u', $rules['banned_keywords']);
        foreach ($keywords as $kw) {
            $kw = trim($kw);
            if (!empty($kw) && mb_stripos($message_text, $kw) !== false) {
                $violation = true;
                $reason = "Banned Keyword: $kw";
                break;
            }
        }
    }

    debugLog("Violation Check: " . ($violation ? "YES - $reason" : "NO") . " for message: $message_text");

    if ($violation) {
        $fb = new FacebookAPI();
        $stmt = $pdo->prepare("SELECT page_access_token FROM fb_pages WHERE page_id = ? OR ig_business_id = ?");
        $stmt->execute([$id, $id]);
        $token = $stmt->fetchColumn();

        // Fallback for token if ig_business_id column failed
        if (!$token) {
            $stmt = $pdo->prepare("SELECT page_access_token FROM fb_pages WHERE page_id = ?");
            $stmt->execute([$id]);
            $token = $stmt->fetchColumn();
        }

        if ($token) {
            if ($rules['action_type'] === 'hide') {
                $hide_res = $fb->hideComment($comment_id, $token, true, $platform);
                debugLog("Hide Action Result for $comment_id: " . json_encode($hide_res));
            } else {
                $del_res = $fb->makeRequest($comment_id, [], $token, 'DELETE');
                debugLog("Delete Action Result for $comment_id: " . json_encode($del_res));
            }

            // Log the action to DB
            $stmt = $pdo->prepare("INSERT INTO fb_moderation_logs (user_id, page_id, post_id, comment_id, content, user_name, reason, action_taken, platform) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$rules['user_id'], $id, $post_id, $comment_id, $message_text, $sender_name, $reason, $rules['action_type'], $platform]);
        }
        return true; // Handled by moderation
    }

    return false;
}



// ----------------------------------------------------------------------
// Evolution API (WhatsApp) Handler
// ----------------------------------------------------------------------
function handleEvolutionEvent($data, $pdo)
{
    $instanceName = $data['instance'] ?? '';
    $eventType = $data['event'] ?? '';

    switch ($eventType) {
        case 'connection.update':
            $state = $data['data']['state'] ?? '';
            $status = ($state === 'open') ? 'connected' : (($state === 'connecting') ? 'pairing' : 'disconnected');
            $stmt = $pdo->prepare("UPDATE wa_accounts SET status = ? WHERE instance_name = ?");
            $stmt->execute([$status, $instanceName]);
            break;

        case 'messages.upsert':
            // Logic for WA auto-reply could go here
            break;
    }
}

function triggerHandoverEmail($pdo, $user_id, $page_name, $source, $sender_name, $incoming_text)
{
    $stmt = $pdo->prepare("SELECT email, username, preferences, smtp_config FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user)
        return;

    $smtp = json_decode($user['smtp_config'] ?: '{}', true);
    if (empty($smtp['enabled']))
        return;

    $prefs = json_decode($user['preferences'] ?: '{}', true);
    $user_lang = $prefs['lang'] ?? 'ar';

    $subject = sprintf(__('handover_alert_subject', $user_lang), $page_name);

    $body = "
    <div dir=\"" . ($user_lang === 'ar' ? 'rtl' : 'ltr') . "\" style=\"font-family: sans-serif; line-height: 1.6; color: #333;\">
        <h2 style=\"color: #4f46e5;\">" . __('handover_alert_subject', $user_lang) . "</h2>
        <p>" . sprintf(__('handover_alert_body_intro', $user_lang), $user['username'] ?? 'User') . "</p>
        <p>" . sprintf(__('handover_alert_body_msg', $user_lang), $page_name) . "</p>
        <hr style=\"border: none; border-top: 1px solid #eee; margin: 20px 0;\">
        <p><strong>" . __('customer_name', $user_lang) . ":</strong> " . htmlspecialchars($sender_name) . "</p>
        <p><strong>" . __('message_snippet', $user_lang) . ":</strong><br>
           <i style=\"color: #666;\">\"" . htmlspecialchars(mb_substr($incoming_text, 0, 200)) . "...\"</i></p>
        <p style=\"margin-top: 30px;\">
            <a href=\"https://" . ($_SERVER['HTTP_HOST'] ?? 'marketation.net') . "/user/\" style=\"background: #4f46e5; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block;\">
                " . __('view_conversation', $user_lang) . "
            </a>
        </p>
    </div>";

    sendUserEmail($user_id, $user['email'], $subject, $body, $smtp);
}
