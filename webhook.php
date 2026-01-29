<?php
// Facebook Webhook Handler
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/facebook_api.php';

// Get User ID from UID parameter (This is the webhook_token for routing)
$webhook_route_id = $_GET['uid'] ?? '';

// 1. Verification Request (GET)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['hub_mode']) && $_GET['hub_mode'] === 'subscribe') {
    $verify_token_sent = $_GET['hub_verify_token'];

    if (!$webhook_route_id) {
        http_response_code(403);
        echo 'Missing Webhook UID';
        exit;
    }

    $pdo = getDB();
    // Find user by the route ID (webhook_token) AND check if verify_token matches
    $stmt = $pdo->prepare("SELECT id FROM users WHERE webhook_token = ? AND verify_token = ?");
    $stmt->execute([$webhook_route_id, $verify_token_sent]);

    if ($stmt->fetch()) {
        echo $_GET['hub_challenge'];
        exit;
    } else {
        http_response_code(403);
        echo 'Invalid Verify Token or Webhook UID';
        exit;
    }
}

// 2. Event Notification (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if ($webhook_route_id) {
        // Optionally verify that this route ID exists to fail early
        // But processing logic will filter by page anyway.
    }

    if (isset($data['object']) && $data['object'] === 'page') {
        foreach ($data['entry'] as $entry) {
            $page_id = $entry['id'];

            // Get changes (feed)
            if (isset($entry['changes'])) {
                foreach ($entry['changes'] as $change) {
                    if ($change['field'] === 'feed') {
                        $value = $change['value'];

                        // We are interested in Comments on our posts
                        if ($value['item'] === 'comment' && $value['verb'] === 'add') {
                            $comment_id = $value['comment_id'];
                            $message = $value['message'];
                            $sender_id = $value['from']['id'];

                            // Ignore comments from the page itself
                            if ($sender_id == $page_id)
                                continue;

                            processAutoReply($page_id, $comment_id, $message, $sender_id);
                        }
                    }
                }
            }
        }
    }

    http_response_code(200);
    echo 'EVENT_RECEIVED';
    exit;
}

function processAutoReply($page_id, $comment_id, $user_message, $sender_id)
{
    global $pdo;
    $pdo = getDB();
    if (!$pdo)
        return;

    // Get Page Access Token
    $stmt = $pdo->prepare("SELECT page_access_token FROM fb_pages WHERE page_id = ?");
    $stmt->execute([$page_id]);
    $page = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$page)
        return; // Page not found in our system

    $access_token = $page['page_access_token'];

    // Get Rules for this page
    $stmt = $pdo->prepare("SELECT * FROM auto_reply_rules WHERE page_id = ? AND active = 1 ORDER BY trigger_type DESC");
    $stmt->execute([$page_id]);
    $rules = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($rules))
        return;

    $matched_rule = null;
    $default_rule = null;
    $user_message_lower = mb_strtolower($user_message);

    foreach ($rules as $rule) {
        if ($rule['trigger_type'] === 'default') {
            $default_rule = $rule;
            continue;
        }

        // Check keywords
        $keywords = explode(',', $rule['keywords']);
        foreach ($keywords as $kw) {
            $kw = trim(mb_strtolower($kw));
            if (!empty($kw) && mb_strpos($user_message_lower, $kw) !== false) {
                $matched_rule = $rule;
                break 2;
            }
        }
    }

    // Determine final rule
    $final_rule = $matched_rule ?? $default_rule;

    if ($final_rule) {
        $fb = new FacebookAPI();

        if (!empty($final_rule['reply_message'])) {
            // Spintax support
            $reply_text = preg_replace_callback('/\{([^{}]+)\}/', function ($matches) {
                $options = explode('|', $matches[1]);
                return $options[array_rand($options)];
            }, $final_rule['reply_message']);

            $fb->replyToComment($comment_id, $reply_text, $access_token);
        }
    }
}
?>