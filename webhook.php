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
                    if ($val['item'] === 'comment' && $val['verb'] === 'add') {
                        $sender_id = $val['from']['id'] ?? '';
                        if ($sender_id == $page_id)
                            continue;

                        $comment_id = $val['comment_id'] ?? '';
                        $message_text = $val['message'] ?? '';

                        processAutoReply($pdo, $page_id, $comment_id, $message_text, 'comment');
                    }
                }
            }
        }
    }
}

function processAutoReply($pdo, $page_id, $target_id, $incoming_text, $source)
{
    // 1. Get Page Token
    $stmt = $pdo->prepare("SELECT page_access_token FROM fb_pages WHERE page_id = ?");
    $stmt->execute([$page_id]);
    $access_token = $stmt->fetchColumn();
    if (!$access_token)
        return;

    // 2. Fetch Rules (Keyword First)
    $stmt = $pdo->prepare("SELECT * FROM auto_reply_rules WHERE page_id = ? AND reply_source = ? AND trigger_type = 'keyword' ORDER BY created_at DESC");
    $stmt->execute([$page_id, $source]);
    $rules = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $reply_msg = '';
    $hide_comment = 0;

    foreach ($rules as $rule) {
        $keywords = explode(',', $rule['keywords']);
        foreach ($keywords as $kw) {
            $kw = trim($kw);
            if (empty($kw))
                continue;
            // Case-insensitive match
            if (mb_stripos($incoming_text, $kw) !== false) {
                $reply_msg = $rule['reply_message'];
                $hide_comment = $rule['hide_comment'];
                break 2;
            }
        }
    }

    // 3. If no keyword match, try Default
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

    $fb = new FacebookAPI();
    if ($source === 'message') {
        // Send Messenger Reply
        $fb->sendMessage($page_id, $access_token, $target_id, $reply_msg);
    } else {
        // Handle Comment
        // 1. Reply to comment
        $fb->replyToComment($target_id, $reply_msg, $access_token);
        // 2. Hide if needed
        if ($hide_comment) {
            $fb->hideComment($target_id, $access_token, true);
        }
    }
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
