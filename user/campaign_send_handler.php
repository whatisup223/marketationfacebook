<?php
// user/campaign_send_handler.php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/facebook_api.php';

header('Content-Type: application/json');

// Comprehensive error handling to prevent HTML errors in JSON
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../includes/php_errors.log');

// Catch all errors and convert to JSON
set_error_handler(function ($errno, $errstr, $errfile, $errline) {
    error_log("PHP Error [$errno]: $errstr in $errfile:$errline");
    echo json_encode(['status' => 'error', 'message' => "Server Error: $errstr"]);
    exit;
});

if (!isLoggedIn()) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$pdo = getDB();

$queue_id = $_POST['queue_id'] ?? 0;

if (!$queue_id) {
    echo json_encode(['status' => 'error', 'message' => 'Missing Queue ID']);
    exit;
}

try {
    // 1. Fetch Queue Item & Campaign & Lead Info
    $stmt = $pdo->prepare("
        SELECT q.id as q_id, q.status as q_status, c.id as c_id, c.message_text, c.image_url, 
               l.fb_user_id, l.fb_user_name, p.page_access_token, p.page_id as fb_page_id 
        FROM campaign_queue q
        JOIN campaigns c ON q.campaign_id = c.id
        JOIN fb_leads l ON q.lead_id = l.id
        JOIN fb_pages p ON c.page_id = p.id
        WHERE q.id = ? AND c.user_id = ?
    ");
    $stmt->execute([$queue_id, $user_id]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$item) {
        echo json_encode(['status' => 'error', 'message' => 'Queue item not found or access denied.']);
        exit;
    }

    if ($item['q_status'] == 'sent') {
        echo json_encode(['status' => 'skipped', 'message' => 'Already sent', 'q_id' => $queue_id]);
        exit;
    }

    // 2. Prepare Message
    $message = $item['message_text'];
    // Simple template replacement
    $message = str_replace('{{name}}', $item['fb_user_name'] ?? 'User', $message);

    // 3. Send via FB API
    $fb = new FacebookAPI();
    $response = $fb->sendMessage($item['fb_page_id'], $item['page_access_token'], $item['fb_user_id'], $message, $item['image_url']);

    // 4. Handle Result
    if (isset($response['error'])) {
        // Failed
        $update = $pdo->prepare("UPDATE campaign_queue SET status = 'failed', error_message = ? WHERE id = ?");
        $update->execute([$response['error'], $queue_id]);

        $pdo->exec("UPDATE campaigns SET failed_count = failed_count + 1 WHERE id = " . $item['c_id']);

        echo json_encode([
            'status' => 'error',
            'message' => $response['error'],
            'q_id' => $queue_id
        ]);
    } else {
        // Success
        $update = $pdo->prepare("UPDATE campaign_queue SET status = 'sent', sent_at = NOW() WHERE id = ?");
        $update->execute([$queue_id]);

        $pdo->exec("UPDATE campaigns SET sent_count = sent_count + 1 WHERE id = " . $item['c_id']);

        echo json_encode(['status' => 'success', 'q_id' => $queue_id]);
    }

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
