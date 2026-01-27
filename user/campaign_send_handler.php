<?php
// Start output buffering immediately to catch ANY output
ob_start();

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/facebook_api.php';

// Clear buffer before setting headers
ob_clean();
header('Content-Type: application/json');

// Simple Error Suppression & Logging
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/debug_errors.txt');

// Custom Exception Handler to catch Fatal Errors
function fatal_handler()
{
    $error = error_get_last();
    if ($error !== NULL && $error['type'] === E_ERROR) {
        $logMsg = date('Y-m-d H:i:s') . " FATAL: " . $error['message'] . " in " . $error['file'] . ":" . $error['line'] . "\n";
        file_put_contents(__DIR__ . '/debug_errors.txt', $logMsg, FILE_APPEND);
        // Attempt to return valid JSON even in death
        ob_clean();
        echo json_encode(['status' => 'error', 'message' => 'Fatal Server Error. Check logs.']);
        exit;
    }
}
register_shutdown_function('fatal_handler');

// Helper function to send clean JSON
function sendJson($data)
{
    ob_clean(); // Ensure buffer is empty
    echo json_encode($data);
    exit;
}

if (!isLoggedIn()) {
    sendJson(['status' => 'error', 'message' => 'Unauthorized']);
}

$user_id = $_SESSION['user_id'];
$pdo = getDB();

$queue_id = $_POST['queue_id'] ?? 0;

if (!$queue_id) {
    sendJson(['status' => 'error', 'message' => 'Missing Queue ID']);
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
        sendJson(['status' => 'error', 'message' => 'Queue item not found or access denied.']);
    }

    if ($item['q_status'] == 'sent') {
        sendJson(['status' => 'skipped', 'message' => 'Already sent', 'q_id' => $queue_id]);
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

        sendJson([
            'status' => 'error',
            'message' => $response['error'],
            'q_id' => $queue_id
        ]);
    } else {
        // Success
        $update = $pdo->prepare("UPDATE campaign_queue SET status = 'sent', sent_at = NOW() WHERE id = ?");
        $update->execute([$queue_id]);

        $pdo->exec("UPDATE campaigns SET sent_count = sent_count + 1 WHERE id = " . $item['c_id']);

        sendJson(['status' => 'success', 'q_id' => $queue_id]);
    }

} catch (Exception $e) {
    sendJson(['status' => 'error', 'message' => $e->getMessage()]);
}
