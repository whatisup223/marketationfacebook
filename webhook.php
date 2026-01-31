<?php
/**
 * Universal Webhook Handler
 * Handles both Evolution API (WhatsApp) and Facebook (Comments & Messenger)
 */

$logFile = __DIR__ . '/webhook_log.txt';
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

require_once __DIR__ . '/includes/db_config.php';
require_once __DIR__ . '/includes/facebook_api.php';

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

                $message_text = $messaging['message']['text'] ?? '';
                if (!$message_text)
                    continue;

                processAutoReply($pdo, $page_id, $sender_id, $message_text, 'message');
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

                            $comment_id = $val['comment_id'] ?? '';
                            $message_text = $val['message'] ?? '';
                            $sender_name = $val['from']['name'] ?? '';

                            // 1. Check Moderation (Must check for both additions and edits)
                            $is_moderated = processModeration($pdo, $page_id, $comment_id, $message_text, $sender_name);

                            // 2. Only Auto-Reply if it's a NEW comment and NOT moderated
                            if ($verb === 'add' && !$is_moderated) {
                                processAutoReply($pdo, $page_id, $comment_id, $message_text, 'comment', $sender_id);
                            }
                        }
                    }
                }
            }
        }
    }
}

function processAutoReply($pdo, $page_id, $target_id, $incoming_text, $source, $actual_sender_id = null)
{
    // 1. Fetch Page Settings (Token, Schedule, Cooldown)
    $stmt = $pdo->prepare("SELECT page_access_token, bot_cooldown_seconds, bot_schedule_enabled, bot_schedule_start, bot_schedule_end, bot_exclude_keywords FROM fb_pages WHERE page_id = ?");
    $stmt->execute([$page_id]);
    $page = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$page || !$page['page_access_token'])
        return;

    $access_token = $page['page_access_token'];

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
            // This ensures "تم" matches as a whole word but not inside "تمام"
            $pattern = '/(?<![\p{L}\p{N}])' . preg_quote($kw, '/') . '(?![\p{L}\p{N}])/ui';

            if (preg_match($pattern, $incoming_text)) {
                $reply_msg = $rule['reply_message'];
                $hide_comment = $rule['hide_comment'];
                $is_keyword_match = true;
                break 2;
            }
        }
    }

    // 2.2 If no keyword match, try Default Rule
    if (!$reply_msg) {
        $stmt = $pdo->prepare("SELECT * FROM auto_reply_rules WHERE page_id = ? AND reply_source = ? AND trigger_type = 'default' LIMIT 1");
        $stmt->execute([$page_id, $source]);
        $def = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($def) {
            $reply_msg = $def['reply_message'];
            $hide_comment = $def['hide_comment'];
        }
    }

    if (!$reply_msg)
        return;

    // 3. Decision Logic: Can we bypass Schedule and Cooldown?
    $exclude_keywords = (int) ($page['bot_exclude_keywords'] ?? 0);
    $is_exempt = ($is_keyword_match && $exclude_keywords === 1);

    // 4. Global Schedule Check (Skip if it's an exempt keyword)
    if (!$is_exempt && $page['bot_schedule_enabled']) {
        date_default_timezone_set('Africa/Cairo');
        $now = date('H:i');
        $start = !empty($page['bot_schedule_start']) ? substr($page['bot_schedule_start'], 0, 5) : '00:00';
        $end = !empty($page['bot_schedule_end']) ? substr($page['bot_schedule_end'], 0, 5) : '23:59';

        $is_inside = ($start <= $end) ? ($now >= $start && $now <= $end) : ($now >= $start || $now <= $end);
        if (!$is_inside)
            return;
    }

    // 6. Cooldown / Human Takeover Logic
    $cooldown_seconds = (int) ($page['bot_cooldown_seconds'] ?? 0);
    $should_check_cooldown = ($cooldown_seconds > 0 && !$is_exempt);

    if ($should_check_cooldown) {
        // Cooldown check is only relevant for Messenger or identification-based sources
        // For comments, we check the user thread via target_id (sender_id)
        $thread_user_id = ($source === 'message') ? $target_id : $actual_sender_id;
        $fb = new FacebookAPI();

        // A. Check Messenger Conversation for Admin activity
        if ($thread_user_id) {
            $convs = $fb->makeRequest("{$page_id}/conversations", [
                'user_id' => $thread_user_id,
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
    $fb = new FacebookAPI();
    $res = null;
    if ($source === 'message') {
        $res = $fb->sendMessage($page_id, $access_token, $target_id, $reply_msg);
    } else {
        $res = $fb->replyToComment($target_id, $reply_msg, $access_token);
        if ($hide_comment) {
            $fb->hideComment($target_id, $access_token, true);
        }
    }

    // 6. Log Bot Reply ID to database
    $sent_id = $res['id'] ?? $res['message_id'] ?? null;
    if ($sent_id) {
        $stmt = $pdo->prepare("INSERT IGNORE INTO bot_sent_messages (message_id, page_id) VALUES (?, ?)");
        $stmt->execute([$sent_id, $page_id]);
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
