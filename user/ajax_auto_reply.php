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
    if (!$page_id) {
        echo json_encode(['success' => false, 'error' => 'Page ID is required']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("SELECT * FROM auto_reply_rules WHERE page_id = ? ORDER BY trigger_type DESC, created_at DESC");
        $stmt->execute([$page_id]);
        $rules = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'rules' => $rules]);
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
        $rule_id = $_POST['rule_id'] ?? '';
        $hide_comment = isset($_POST['hide_comment']) ? (int) $_POST['hide_comment'] : 0;

        if (!$page_id || !$reply) {
            echo json_encode(['success' => false, 'error' => 'Missing required fields']);
            exit;
        }

        try {
            if ($rule_id) {
                // Update
                $stmt = $pdo->prepare("UPDATE auto_reply_rules SET trigger_type = ?, keywords = ?, reply_message = ?, hide_comment = ? WHERE id = ? AND page_id = ?");
                $stmt->execute([$type, $keywords, $reply, $hide_comment, $rule_id, $page_id]);
            } else {
                // Check if existing default rule
                if ($type === 'default') {
                    $check = $pdo->prepare("SELECT id FROM auto_reply_rules WHERE page_id = ? AND trigger_type = 'default'");
                    $check->execute([$page_id]);
                    if ($check->fetch()) {
                        $stmt = $pdo->prepare("UPDATE auto_reply_rules SET reply_message = ?, hide_comment = ? WHERE page_id = ? AND trigger_type = 'default'");
                        $stmt->execute([$reply, $hide_comment, $page_id]);
                        echo json_encode(['success' => true, 'message' => __('rule_saved')]);
                        exit;
                    }
                }

                // Insert
                $stmt = $pdo->prepare("INSERT INTO auto_reply_rules (page_id, trigger_type, keywords, reply_message, hide_comment) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$page_id, $type, $keywords, $reply, $hide_comment]);
            }
            echo json_encode(['success' => true, 'message' => __('rule_saved')]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
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
}
?>