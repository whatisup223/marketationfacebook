<?php
// campaign_batch_handler.php - FULL LOGIC VERSION
// Respects: Batch Size, Retry Count, Retry Delay, and Waiting Interval (via client control)

error_reporting(0);
ini_set('display_errors', 0);
ob_start();

require_once __DIR__ . '/../includes/db_config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/facebook_api.php';

// Safe Shutdown Logic
register_shutdown_function(function () {
    $error = error_get_last();
    if ($error !== NULL && $error['type'] === E_ERROR) {
        // Log removed for production clean-up
        @ob_clean();
        echo json_encode(['status' => 'stopped', 'error' => 'Fatal Server Error']);
    }
});

ob_clean();
header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
// IMPORTANT: Release session lock immediately. 
// This prevents this long-running script from blocking other page loads (like refresh or dashboard).
session_write_close();

$campaign_id = $_POST['campaign_id'] ?? 0;

try {
    // 1. Get Campaign Settings (Server Truth)
    // We trust DB settings over client input for Retry/Delay to be secure/consistent
    $stmt = $pdo->prepare("SELECT batch_size, retry_count, retry_delay, status FROM campaigns WHERE id = ? AND user_id = ?");
    $stmt->execute([$campaign_id, $user_id]);
    $campSettings = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$campSettings || $campSettings['status'] !== 'running') {
        echo json_encode(['status' => 'stopped', 'message' => 'Campaign not running']);
        exit;
    }

    // Default Fallbacks
    $batch_size = ($campSettings['batch_size'] > 0) ? $campSettings['batch_size'] : 5;
    $max_retries = ($campSettings['retry_count'] !== null) ? $campSettings['retry_count'] : 1;
    $retry_delay = ($campSettings['retry_delay'] !== null) ? $campSettings['retry_delay'] : 10;

    // Hard Limit for safety
    if ($batch_size > 50)
        $batch_size = 50;

    // 2. Fetch Pending Items
    // LOGIC: Status is pending, AND (Its first time OR its time to retry)
    // AND attempts is less than max allowed
    $sql = "
        SELECT q.id as q_id, q.attempts_count, q.next_retry_at,
               c.message_text, c.image_url, 
               l.fb_user_id, l.fb_user_name, p.page_access_token, p.page_id as fb_page_id
        FROM campaign_queue q
        JOIN campaigns c ON q.campaign_id = c.id
        JOIN fb_leads l ON q.lead_id = l.id
        JOIN fb_pages p ON c.page_id = p.id
        WHERE q.campaign_id = ? 
          AND q.status = 'pending'
          AND (q.next_retry_at IS NULL OR q.next_retry_at <= NOW())
          AND q.attempts_count <= ? 
        ORDER BY q.next_retry_at ASC, q.id ASC
        LIMIT $batch_size
    ";

    $qStmt = $pdo->prepare($sql);
    $qStmt->execute([$campaign_id, $max_retries]);
    $items = $qStmt->fetchAll(PDO::FETCH_ASSOC);

    // 3. If Empty, Check why?
    if (empty($items)) {
        // Are there items waiting for retry in the future?
        $waitStmt = $pdo->prepare("SELECT MIN(next_retry_at) FROM campaign_queue WHERE campaign_id = ? AND status = 'pending' AND next_retry_at > NOW()");
        $waitStmt->execute([$campaign_id]);
        $nextUp = $waitStmt->fetchColumn();

        if ($nextUp) {
            // Yes, just wait
            $waitSec = strtotime($nextUp) - time();
            echo json_encode([
                'status' => 'waiting_retry',
                'message' => 'Waiting for retries...',
                'next_retry_in' => ($waitSec > 0 ? $waitSec : 5)
            ]);
        } else {
            // No, are there any pending at all? (maybe stuck?)
            $countStmt = $pdo->prepare("SELECT COUNT(*) FROM campaign_queue WHERE campaign_id = ? AND status = 'pending'");
            $countStmt->execute([$campaign_id]);
            $left = $countStmt->fetchColumn();

            if ($left == 0) {
                $pdo->prepare("UPDATE campaigns SET status = 'completed' WHERE id = ?")->execute([$campaign_id]);
                echo json_encode(['status' => 'completed']);
            } else {
                // Items exist but max retries reached? (Should be marked failed, but if logic stuck)
                // Mark them failed strictly
                $pdo->prepare("UPDATE campaign_queue SET status = 'failed', error_message = 'Max retries exceeded' WHERE campaign_id = ? AND status = 'pending' AND attempts_count > ?")
                    ->execute([$campaign_id, $max_retries]);

                echo json_encode(['status' => 'waiting_retry', 'next_retry_in' => 5]);
            }
        }
        exit;
    }

    $fb = new FacebookAPI();
    $processed_results = [];

    // 4. Process Batch PARALLEL (Max Speed)
    $batch_payloads = [];
    $map_key_to_item = [];

    foreach ($items as $idx => $item) {
        $message = $item['message_text'];
        $message = str_replace('{{name}}', $item['fb_user_name'] ?? 'User', $message);

        // Optimizing Image: If user uses our local uploads, we can force a smaller version
        // Assuming typical structure: uploads/campaigns/image.jpg -> uploads/campaigns/image_small.jpg
        // For now, we rely on parallel sending to overcome latency.

        $batch_payloads[$idx] = [
            'page_id' => $item['fb_page_id'],
            'access_token' => $item['page_access_token'],
            'recipient_id' => $item['fb_user_id'],
            'message_text' => $message,
            'image_url' => $item['image_url']
        ];
        $map_key_to_item[$idx] = $item;
    }

    // Execute Parallel Send
    $batch_responses = $fb->sendBatchMessages($batch_payloads);

    // Process Results
    foreach ($batch_responses as $idx => $response) {
        $item = $map_key_to_item[$idx];
        $queue_id = $item['q_id'];
        $current_attempts = $item['attempts_count'];

        if (isset($response['error'])) {
            // FAILED
            $new_attempts = $current_attempts + 1;
            $error_msg = is_array($response['error']) ? ($response['error']['message'] ?? json_encode($response['error'])) : $response['error'];

            if ($new_attempts <= $max_retries) {
                // RETRY LATER
                $next_time = date('Y-m-d H:i:s', time() + $retry_delay);
                $update = $pdo->prepare("UPDATE campaign_queue SET attempts_count = ?, next_retry_at = ?, error_message = ? WHERE id = ?");
                $update->execute([$new_attempts, $next_time, "Retry #$new_attempts: $error_msg", $queue_id]);

                $processed_results[] = [
                    'id' => $queue_id,
                    'status' => 'retrying',
                    'error' => $error_msg
                ];
            } else {
                // HARD FAIL
                $update = $pdo->prepare("UPDATE campaign_queue SET status = 'failed', attempts_count = ?, error_message = ? WHERE id = ?");
                $update->execute([$new_attempts, "Max retries ($new_attempts): $error_msg", $queue_id]);

                $pdo->exec("UPDATE campaigns SET failed_count = failed_count + 1 WHERE id = $campaign_id");

                $processed_results[] = [
                    'id' => $queue_id,
                    'status' => 'failed',
                    'error' => $error_msg
                ];
            }

        } else {
            // SUCCESS
            $update = $pdo->prepare("UPDATE campaign_queue SET status = 'sent', sent_at = NOW(), attempts_count = attempts_count + 1 WHERE id = ?");
            $update->execute([$queue_id]);

            $pdo->exec("UPDATE campaigns SET sent_count = sent_count + 1 WHERE id = $campaign_id");

            $processed_results[] = [
                'id' => $queue_id,
                'status' => 'sent'
            ];
        }
    }

    echo json_encode([
        'status' => 'batch_processed',
        'processed_count' => count($processed_results),
        'results' => $processed_results
    ]);

} catch (Exception $e) {
    ob_clean();
    echo json_encode(['status' => 'stopped', 'error' => 'Logic Error: ' . $e->getMessage()]);
}
?>