<?php
// Disable Output first
error_reporting(0);
ini_set('display_errors', 0);
ob_start();

require_once __DIR__ . '/../includes/db_config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/facebook_api.php';

// Prepare JSON Header
ob_clean();
header('Content-Type: application/json');

// Setup Logging
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/debug_errors.txt');

// Shutdown Handler
register_shutdown_function(function () {
    $error = error_get_last();
    if ($error !== NULL && $error['type'] === E_ERROR) {
        $logMsg = date('Y-m-d H:i:s') . " FATAL BATCH ERROR: " . $error['message'] . " in " . $error['file'] . ":" . $error['line'] . "\n";
        file_put_contents(__DIR__ . '/debug_errors.txt', $logMsg, FILE_APPEND);
        @ob_clean();
        echo json_encode(['status' => 'stopped', 'error' => 'Server Fatal Error: See logs']);
    }
});

if (!isLoggedIn()) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$campaign_id = $_POST['campaign_id'] ?? 0;
// Default batch size (can be customized)
$batch_size = isset($_POST['batch_size']) ? (int) $_POST['batch_size'] : 5;
// Dynamic fallback to user settings
if (isset($campaign['batch_size']))
    $batch_size = $campaign['batch_size'];

if ($batch_size > 50)
    $batch_size = 50;
if ($batch_size < 1)
    $batch_size = 1;

$fb = new FacebookAPI();
$processed_results = [];

try {
    // 1. Fetch Pending Items
    // NO 'attempts_count', NO 'next_retry_at' to be safe
    $qStmt = $pdo->prepare("
        SELECT q.id as q_id, c.message_text, c.image_url, 
               l.fb_user_id, l.fb_user_name, p.page_access_token, p.page_id as fb_page_id,
               c.status as campaign_status
        FROM campaign_queue q
        JOIN campaigns c ON q.campaign_id = c.id
        JOIN fb_leads l ON q.lead_id = l.id
        JOIN fb_pages p ON c.page_id = p.id
        WHERE q.campaign_id = ? 
          AND q.status = 'pending'
          AND c.status = 'running'
        ORDER BY q.id ASC
        LIMIT $batch_size
    ");
    $qStmt->execute([$campaign_id]);
    $items = $qStmt->fetchAll(PDO::FETCH_ASSOC);

    // 2. Check if empty
    if (empty($items)) {
        // Double check pending count
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM campaign_queue WHERE campaign_id = ? AND status IN ('pending', 'processing')");
        $stmt->execute([$campaign_id]);
        $pendingTotal = $stmt->fetchColumn();

        if ($pendingTotal == 0) {
            // Mark complete
            $pdo->prepare("UPDATE campaigns SET status = 'completed' WHERE id = ?")->execute([$campaign_id]);
            echo json_encode(['status' => 'completed', 'message' => 'All messages processed']);
        } else {
            // Just wait (no retry logic here to avoid DB errors)
            echo json_encode([
                'status' => 'waiting_retry',
                'message' => 'Processing pending items...',
                'next_retry_in' => 5
            ]);
        }
        exit;
    }

    // 3. Process Batch
    foreach ($items as $item) {
        $queue_id = $item['q_id'];

        // Prepare Message
        $message = $item['message_text'];
        $message = str_replace('{{name}}', $item['fb_user_name'] ?? 'User', $message);

        // 4. Send API Request
        // Using updated FacebookAPI
        $response = $fb->sendMessage(
            $item['fb_page_id'],
            $item['page_access_token'],
            $item['fb_user_id'],
            $message,
            $item['image_url']
        );

        // 5. Update DB based on Result
        if (isset($response['error'])) {
            // Failed
            $error_msg = is_array($response['error']) ? json_encode($response['error']) : $response['error'];

            $update = $pdo->prepare("UPDATE campaign_queue SET status = 'failed', error_message = ? WHERE id = ?");
            $update->execute([$error_msg, $queue_id]);

            $pdo->exec("UPDATE campaigns SET failed_count = failed_count + 1 WHERE id = $campaign_id");

            $processed_results[] = [
                'id' => $queue_id,
                'status' => 'failed',
                'error' => $response['error']['message'] ?? 'API Error'
            ];

        } else {
            // Success
            $update = $pdo->prepare("UPDATE campaign_queue SET status = 'sent', sent_at = NOW() WHERE id = ?");
            $update->execute([$queue_id]);

            $pdo->exec("UPDATE campaigns SET sent_count = sent_count + 1 WHERE id = $campaign_id");

            $processed_results[] = [
                'id' => $queue_id,
                'status' => 'sent'
            ];
        }
    }

    // 6. Return Batch Report
    echo json_encode([
        'status' => 'batch_processed',
        'processed_count' => count($processed_results),
        'results' => $processed_results
    ]);

} catch (Exception $e) {
    ob_clean();
    echo json_encode([
        'status' => 'stopped',
        'error' => 'Batch Exception: ' . $e->getMessage()
    ]);
}
?>