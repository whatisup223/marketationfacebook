<?php
// Extreme Error Suppression to output clean JSON
error_reporting(0);
ini_set('display_errors', 0);

// Start output buffering immediately
ob_start();

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/facebook_api.php';

// Clear buffer and set JSON header
ob_clean();
header('Content-Type: application/json');

// Logging setup
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/debug_errors.txt');

// Custom Panic Handler (for hard crashes)
register_shutdown_function(function () {
    $error = error_get_last();
    if ($error !== NULL && $error['type'] === E_ERROR) {
        $logMsg = date('Y-m-d H:i:s') . " FATAL: " . $error['message'] . " in " . $error['file'] . ":" . $error['line'] . "\n";
        file_put_contents(__DIR__ . '/debug_errors.txt', $logMsg, FILE_APPEND);
        ob_clean();
        echo json_encode(['status' => 'error', 'message' => 'Fatal Server Error. Check transaction log for details.']);
        exit;
    }
});

// Helper: Send JSON and Exit
function sendJson($data)
{
    ob_clean();
    echo json_encode($data);
    exit;
}

// Helper: Log Transactions
function logTransaction($msg)
{
    file_put_contents(__DIR__ . '/transaction_log.txt', date('[Y-m-d H:i:s] ') . $msg . "\n", FILE_APPEND);
}

try {
    if (!isLoggedIn()) {
        sendJson(['status' => 'error', 'message' => 'Unauthorized']);
    }

    $user_id = $_SESSION['user_id'];
    $campaign_id = $_GET['campaign_id'] ?? 0;

    // Get Next Queue Item
    // Optimized query to pick 'pending' items
    $stmt = $pdo->prepare("
        SELECT q.*, c.page_id as c_page_id, p.page_id as fb_page_id, p.access_token as page_access_token 
        FROM campaign_queue q
        JOIN campaigns c ON q.campaign_id = c.id
        JOIN facebook_pages p ON c.page_id = p.id
        WHERE q.status = 'pending' 
        AND c.user_id = ?
        AND c.status = 'running'
        AND (q.campaign_id = ? OR ? = 0)
        ORDER BY q.id ASC
        LIMIT 1
    ");
    $stmt->execute([$user_id, $campaign_id, $campaign_id]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$item) {
        sendJson(['status' => 'empty', 'message' => 'No pending messages']);
    }

    // Process Item
    $queue_id = $item['id'];

    // 1. Mark as processing (optional, to avoid race conditions if multiple workers)
    // $pdo->exec("UPDATE campaign_queue SET status = 'processing' WHERE id = $queue_id");

    // 2. Prepare Message
    $message = $item['message_text'];
    $message = str_replace('{{name}}', $item['fb_user_name'] ?? 'User', $message);

    logTransaction("Processing QID: {$queue_id} | Page: {$item['fb_page_id']} | User: {$item['fb_user_id']}");

    // 3. Send using the new Robust FacebookAPI
    $fb = new FacebookAPI();

    // Use the upgraded sendMessage which handles fallbacks internally
    $response = $fb->sendMessage(
        $item['fb_page_id'],
        $item['page_access_token'],
        $item['fb_user_id'],
        $message,
        $item['image_url']
    );

    logTransaction("FB Response for QID {$queue_id}: " . json_encode($response));

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

        sendJson([
            'status' => 'success',
            'q_id' => $queue_id
        ]);
    }

} catch (Throwable $e) {
    // Catch ANY script execution error
    $msg = "EXCEPTION: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine();
    logTransaction($msg);
    file_put_contents(__DIR__ . '/debug_errors.txt', $msg . "\n", FILE_APPEND);

    sendJson([
        'status' => 'error',
        'message' => 'Server Exception: ' . $e->getMessage(),
        'q_id' => $queue_id ?? 0
    ]);
}
?>