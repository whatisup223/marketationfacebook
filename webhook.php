<?php
/**
 * Universal Webhook Handler
 * Handles both Evolution API (WhatsApp) and Facebook (Comments & Messenger)
 */

$logFile = 'c:/Users/AHL ELSONA/Desktop/marketationfacebook/webhook_log.txt';
$input = file_get_contents('php://input');
// For debugging - remove in production
// file_put_contents($logFile, date('Y-m-d H:i:s') . " - Input: " . $input . "\n", FILE_APPEND);

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

// A. Check if it's Facebook Webhook
if (isset($data['object']) && ($data['object'] === 'page' || $data['object'] === 'instagram')) {
    handleFacebookEvent($data, $pdo);
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
    foreach ($data['entry'] as $entry) {
        $page_id = $entry['id'] ?? '';

        // Handle Messaging (Messenger)
        if (isset($entry['messaging'])) {
            foreach ($entry['messaging'] as $messaging) {
                $sender_id = $messaging['sender']['id'] ?? '';
                if ($sender_id == $page_id)
                    continue; // Skip messages sent by the page itself

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

                processAutoReply($pdo, $page_id, $sender_id, $message_text, 'message', null, '');
            }
        }

        // Handle Feed (Comments)
        if (isset($entry['changes'])) {
            foreach ($entry['changes'] as $change) {
                if ($change['field'] === 'feed') {
                    $val = $change['value'];
                    if ($val['item'] === 'comment') {
                        $verb = $val['verb'] ?? '';
                        if ($verb === 'add' || $verb === 'edited') {
                            $sender_id = $val['from']['id'] ?? '';
                            if ($sender_id == $page_id)
                                continue;

                            $comment_id = $val['comment_id'] ?? $val['id'] ?? '';
                            $message_text = $val['message'] ?? '';
                            $sender_name = $val['from']['name'] ?? '';
                            $sender_id = $val['from']['id'] ?? '';

                            // 1. Check Moderation (Must check for both additions and edits)
                            $is_moderated = processModeration($pdo, $page_id, $comment_id, $message_text, $sender_name);

                            // 2. Only Auto-Reply if it's a NEW comment and NOT moderated
                            if ($verb === 'add' && !$is_moderated) {
                                processAutoReply($pdo, $page_id, $comment_id, $message_text, 'comment', $sender_id, $sender_name);
                            }
                        }
                    }
                }
            }
        }
    }
}

function processAutoReply($pdo, $page_id, $target_id, $incoming_text, $source, $actual_sender_id = null, $sender_name = '')
{
    // 1. Fetch Page Settings (Token, Schedule, Cooldown, AI Intelligence)
    $stmt = $pdo->prepare("SELECT p.page_access_token, p.page_name, p.bot_cooldown_seconds, p.bot_schedule_enabled, p.bot_schedule_start, p.bot_schedule_end, p.bot_exclude_keywords, p.bot_ai_sentiment_enabled, p.bot_anger_keywords, p.bot_repetition_threshold, p.bot_handover_reply, a.user_id 
                           FROM fb_pages p 
                           JOIN fb_accounts a ON p.account_id = a.id 
                           WHERE p.page_id = ?");
    $stmt->execute([$page_id]);
    $page = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$page || !$page['page_access_token'])
        return;

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
        $stmt = $pdo->prepare("SELECT * FROM bot_conversation_states WHERE page_id = ? AND user_id = ? AND reply_source = ? LIMIT 1");
        $stmt->execute([$page_id, $customer_id, $source]);
        $state = $stmt->fetch(PDO::FETCH_ASSOC);

        // If state is handover, bot is manually silenced for this user
        if ($state && $state['conversation_state'] === 'handover') {
            return;
        }

    }

    // 2. Find Rule Match
    // 2.1 Try Keywords First
    $stmt = $pdo->prepare("SELECT * FROM auto_reply_rules WHERE page_id = ? AND reply_source = ? AND trigger_type = 'keyword' ORDER BY created_at DESC");
    $stmt->execute([$page_id, $source]);
    $rules = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $reply_msg = '';
    $hide_comment = 0;
    $is_keyword_match = false;

    foreach ($rules as $rule) {
        $keywords = explode(',', $rule['keywords']);
        foreach ($keywords as $kw) {
            $kw = trim($kw);
            if (empty($kw))
                continue;

            // Improved matching: Whole Word Match using Regex (supports Arabic/UTF-8)
            $pattern = '/(?<![\p{L}\p{N}])' . preg_quote($kw, '/') . '(?![\p{L}\p{N}])/ui';

            if (preg_match($pattern, $incoming_text)) {
                $reply_msg = $rule['reply_message'];
                $hide_comment = $rule['hide_comment'];
                $is_keyword_match = true;
                $matched_rule = $rule;
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
                $stmt = $pdo->prepare("INSERT INTO bot_conversation_states (page_id, user_id, user_name, last_user_message, last_user_message_at, conversation_state, is_anger_detected, reply_source) 
                                        VALUES (?, ?, ?, ?, NOW(), 'handover', 1, ?) 
                                        ON DUPLICATE KEY UPDATE user_name = VALUES(user_name), last_user_message = VALUES(last_user_message), last_user_message_at = NOW(), conversation_state = 'handover', is_anger_detected = 1");
                $stmt->execute([$page_id, $customer_id, $sender_name, $incoming_text, $source]);

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
                    $fb = new FacebookAPI(); // Ensure instance
                    if ($source === 'message') {
                        $fb->sendMessage($page_id, $access_token, $customer_id, $page['bot_handover_reply']);
                    } else {
                        // For comments, we reply to the comment
                        $fb->replyToComment($target_id, $page['bot_handover_reply'], $access_token);
                    }
                }

                return; // SILENCE immediately
            }
        }
    }

    // 2.3 If no keyword match AND no anger silence, try Default Rule
    if (!$reply_msg) {
        $stmt = $pdo->prepare("SELECT * FROM auto_reply_rules WHERE page_id = ? AND reply_source = ? AND trigger_type = 'default' LIMIT 1");
        $stmt->execute([$page_id, $source]);
        $def = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($def) {
            $reply_msg = $def['reply_message'];
            $hide_comment = $def['hide_comment'];
            $matched_rule = $def;
            $matched_rule = $def;
        }
    }

    if (!$reply_msg)
        return;

    // Capture Private Reply Settings from the matched rule
    $private_reply_enabled = (int) ($matched_rule['private_reply_enabled'] ?? 0);
    $private_reply_text = $matched_rule['private_reply_text'] ?? '';
    // Capture Auto Like Settings
    $auto_like_comment = (int) ($matched_rule['auto_like_comment'] ?? 0);

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
            $mention = '@[' . $customer_id . ']';
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
                    $fb = new FacebookAPI(); // Ensure instance
                    if ($source === 'message') {
                        $fb->sendMessage($page_id, $access_token, $customer_id, $page['bot_handover_reply']);
                    } else {
                        // For comments, we reply to the comment
                        $fb->replyToComment($customer_id, $page['bot_handover_reply'], $access_token);
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
            $convs = $fb->makeRequest("{$page_id}/conversations", [
                'fields' => 'messages.limit(5){id,from,created_time}'
            ], $access_token);

            if (isset($convs['data'][0]['messages']['data'])) {
                foreach ($convs['data'][0]['messages']['data'] as $msg) {
                    $msg_sender_id = $msg['from']['id'] ?? '';
                    $fb_msg_id = $msg['id'] ?? '';

                    if ($msg_sender_id == $page_id) {
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

                    if ($reply_sender_id == $page_id) {
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
            $fb->sendImageMessage($page_id, $access_token, $target_id, $image_url);
        }

        // Then send text with buttons
        if ($buttons && is_array($buttons) && count($buttons) > 0) {
            $res = $fb->sendButtonMessage($page_id, $access_token, $target_id, $reply_msg, $buttons);
        } else {
            $res = $fb->sendMessage($page_id, $access_token, $target_id, $reply_msg);
        }
    } else {
        $res = $fb->replyToComment($target_id, $reply_msg, $access_token);

        // DEBUG: Log the result of the comment reply
        file_put_contents(__DIR__ . '/debug_fb.txt', date('Y-m-d H:i:s') . " - Comment Reply Res: " . json_encode($res) . " | ID: $target_id | Msg: $reply_msg\n", FILE_APPEND);

        if ($hide_comment) {
            $fb->hideComment($target_id, $access_token, true);
        }

        // --- NEW: PRIVATE REPLY TO COMMENT ---
        if ($private_reply_enabled && !empty($private_reply_text)) {
            // Facebook allows sending a private message to the person who commented
            // We use the specific endpoint for this: /{comment_id}/private_replies
            // Note: This only works if the comment is less than 7 days old (usually instant)
            $fb->replyPrivateToComment($target_id, $private_reply_text, $access_token);
        }

        // --- NEW: AUTO LIKE COMMENT ---
        if ($auto_like_comment) {
            $fb->likeComment($target_id, $access_token);
        }
    }

    // 7. Update Tracking (ID Log & Conversation State)
    $sent_id = $res['id'] ?? $res['message_id'] ?? null;
    if ($sent_id) {
        $rule_id = $matched_rule['id'] ?? null;
        $stmt = $pdo->prepare("INSERT IGNORE INTO bot_sent_messages (message_id, page_id, rule_id, reply_source, user_id) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$sent_id, $page_id, $rule_id, $source, $customer_id]);
    }

    if ($customer_id) {
        $repeat_val = ($state && $state['last_bot_reply_text'] === $reply_msg) ? $state['repeat_count'] + 1 : 1;
        $stmt = $pdo->prepare("INSERT INTO bot_conversation_states (page_id, user_id, user_name, last_user_message, last_user_message_at, conversation_state, last_bot_reply_text, repeat_count, reply_source) 
                               VALUES (?, ?, ?, ?, NOW(), 'active', ?, 1, ?) 
                               ON DUPLICATE KEY UPDATE user_name = VALUES(user_name), last_user_message = VALUES(last_user_message), last_user_message_at = NOW(), last_bot_reply_text = ?, repeat_count = ?, conversation_state = 'active'");
        $stmt->execute([$page_id, $customer_id, $sender_name, $incoming_text, $reply_msg, $source, $reply_msg, $repeat_val]);
    }
}

function processModeration($pdo, $page_id, $comment_id, $message_text, $sender_name = '')
{
    // 1. Get Rules
    $stmt = $pdo->prepare("SELECT * FROM fb_moderation_rules WHERE page_id = ? AND is_active = 1");
    $stmt->execute([$page_id]);
    $rules = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$rules)
        return false;

    $violation = false;
    $reason = "";

    // A. Check Phone Numbers
    if ($rules['hide_phones']) {
        // Pattern for mobile numbers
        if (preg_match('/(\d{8,15})|(\+?\d{1,4}[\s-]?\d{3,4}[\s-]?\d{4})/', $message_text)) {
            $violation = true;
            $reason = "Phone Number Detected";
        }
    }

    // B. Check Links/URLs
    if (!$violation && $rules['hide_links']) {
        if (preg_match('/(https?:\/\/[^\s]+)|(www\.[^\s]+)|([a-zA-Z0-9-]+\.(com|net|org|me|info|biz|co|app|xyz))/i', $message_text)) {
            $violation = true;
            $reason = "Link Detected";
        }
    }

    // C. Check Banned Keywords
    if (!$violation && !empty($rules['banned_keywords'])) {
        $keywords = explode(',', $rules['banned_keywords']);
        foreach ($keywords as $kw) {
            $kw = trim($kw);
            if (!empty($kw) && mb_stripos($message_text, $kw) !== false) {
                $violation = true;
                $reason = "Banned Keyword: $kw";
                break;
            }
        }
    }

    if ($violation) {
        $fb = new FacebookAPI();
        $stmt = $pdo->prepare("SELECT page_access_token FROM fb_pages WHERE page_id = ?");
        $stmt->execute([$page_id]);
        $token = $stmt->fetchColumn();

        if ($token) {
            if ($rules['action_type'] === 'hide') {
                $fb->hideComment($comment_id, $token, true);
            } else {
                $fb->makeRequest($comment_id, [], $token, 'DELETE');
            }

            // Log the action
            $stmt = $pdo->prepare("INSERT INTO fb_moderation_logs (user_id, page_id, comment_id, comment_text, sender_name, reason, action_taken) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$rules['user_id'], $page_id, $comment_id, $message_text, $sender_name, $reason, $rules['action_type']]);
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
