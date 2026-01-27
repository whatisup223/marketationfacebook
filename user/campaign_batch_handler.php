<?php
// user/campaign_batch_handler.php
// PRODUCTION FIX: Enable error display temporarily
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start(); // Start session first
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/facebook_api.php';

header('Content-Type: application/json');
ignore_user_abort(true);
set_time_limit(300);

// Read user_id BEFORE closing session
$user_id = $_SESSION['user_id'] ?? 0;

// Now unlock session for parallel requests
if (session_status() === PHP_SESSION_ACTIVE)
    session_write_close();

// PRODUCTION FIX: If no session, try to get from POST/campaign ownership
if (!$user_id) {
    $campaign_id = $_POST['campaign_id'] ?? 0;
    if ($campaign_id) {
        // Get user_id from campaign table instead
        $pdo = getDB();
        $stmt = $pdo->prepare("SELECT user_id FROM campaigns WHERE id = ?");
        $stmt->execute([$campaign_id]);
        $user_id = $stmt->fetchColumn();
    }
}

if (!$user_id) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized - No valid session or campaign']);
    exit;
}

$pdo = getDB();
$campaign_id = $_POST['campaign_id'] ?? 0;

// Verify ownership
$stmt = $pdo->prepare("SELECT status, waiting_interval, batch_size, retry_count, retry_delay FROM campaigns WHERE id = ? AND user_id = ?");
$stmt->execute([$campaign_id, $user_id]);
$camp = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$camp || $camp['status'] !== 'running') {
    echo json_encode(['status' => 'stopped', 'message' => 'Campaign not running']);
    exit;
}

// FIX: Reset stuck 'processing' items that crashed > 15 mins ago
$pdo->exec("UPDATE campaign_queue SET status = 'pending' WHERE status = 'processing' AND (reserved_at < (NOW() - INTERVAL 15 MINUTE) OR reserved_at IS NULL) AND campaign_id = $campaign_id");

$batch_size = (int) ($camp['batch_size'] ?? 10);
$retry_max = (int) ($camp['retry_count'] ?? 1);
$retry_delay = (int) ($camp['retry_delay'] ?? 30);

// Safety Cap
if ($batch_size > 50)
    $batch_size = 50;
if ($batch_size < 1)
    $batch_size = 1;

$fb = new FacebookAPI();
$processed_results = [];

// Fetch Pending Items (respect next_retry_at)
$qStmt = $pdo->prepare("
    SELECT q.id as q_id, q.attempts_count, c.message_text, c.image_url, 
           l.fb_user_id, l.fb_user_name, p.page_access_token, p.page_id as fb_page_id 
    FROM campaign_queue q
    JOIN campaigns c ON q.campaign_id = c.id
    JOIN fb_leads l ON q.lead_id = l.id
    JOIN fb_pages p ON c.page_id = p.id
    WHERE q.campaign_id = ? 
      AND q.status = 'pending'
      AND (q.next_retry_at IS NULL OR q.next_retry_at <= NOW())
    ORDER BY q.id ASC
    LIMIT $batch_size
");
$qStmt->execute([$campaign_id]);
$items = $qStmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($items)) {
    // Check completion (include those waiting for retry)
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM campaign_queue WHERE campaign_id = ? AND status IN ('pending', 'processing')");
    $stmt->execute([$campaign_id]);
    $pendingTotal = $stmt->fetchColumn();

    if ($pendingTotal == 0) {
        $pdo->prepare("UPDATE campaigns SET status = 'completed' WHERE id = ?")->execute([$campaign_id]);
        echo json_encode(['status' => 'completed', 'message' => 'All messages processed']);
    } else {
        // Some are waiting for retry
        $stmt = $pdo->prepare("SELECT MIN(next_retry_at) FROM campaign_queue WHERE campaign_id = ? AND status = 'pending' AND next_retry_at > NOW()");
        $stmt->execute([$campaign_id]);
        $nextTime = $stmt->fetchColumn();

        echo json_encode([
            'status' => 'waiting_retry',
            'message' => 'Some items waiting for retry delay',
            'next_retry_in' => $nextTime ? (strtotime($nextTime) - time()) : 30
        ]);
    }
    exit;
}

// Process batch using curl_multi
$mh = curl_multi_init();
$curl_handles = [];
$queue_results = [];

foreach ($items as $item) {
    $queue_id = $item['q_id'];
    $message = str_replace('{{name}}', $item['fb_user_name'] ?? 'User', $item['message_text']);

    // Mark as processing
    $pdo->prepare("UPDATE campaign_queue SET status = 'processing', reserved_at = NOW() WHERE id = ?")->execute([$queue_id]);

    $queue_results[$queue_id] = [
        'success' => false,
        'errors' => [],
        'attempts' => (int) $item['attempts_count']
    ];

    // 1. Prepare TEXT Request
    if (!empty(trim($message))) {
        $ch_text = curl_init();
        $endpoint = $item['fb_page_id'] . '/messages';
        $url = 'https://graph.facebook.com/v12.0/' . $endpoint . '?access_token=' . urlencode($item['page_access_token']);

        $data_text = [
            'recipient' => ['id' => $item['fb_user_id']],
            'message' => ['text' => $message],
            'messaging_type' => 'MESSAGE_TAG',
            'tag' => 'POST_PURCHASE_UPDATE'
        ];

        curl_setopt($ch_text, CURLOPT_URL, $url);
        curl_setopt($ch_text, CURLOPT_POST, 1);
        curl_setopt($ch_text, CURLOPT_POSTFIELDS, json_encode($data_text));
        curl_setopt($ch_text, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch_text, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch_text, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch_text, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch_text, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch_text, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);

        curl_multi_add_handle($mh, $ch_text);
        $curl_handles[$queue_id . '_txt'] = $ch_text;
    }

    // 2. Prepare IMAGE Request
    if (!empty($item['image_url'])) {
        $imgUrl = $item['image_url'];
        if (strpos($imgUrl, 'http') !== 0) {
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
            $domain = $_SERVER['HTTP_HOST'];
            $imgUrl = $protocol . $domain . '/' . ltrim($imgUrl, '/');
        }

        $ch_img = curl_init();
        $endpoint = $item['fb_page_id'] . '/messages';
        $url = 'https://graph.facebook.com/v12.0/' . $endpoint . '?access_token=' . urlencode($item['page_access_token']);

        $data_img = [
            'recipient' => ['id' => $item['fb_user_id']],
            'message' => [
                'attachment' => [
                    'type' => 'image',
                    'payload' => ['url' => $imgUrl, 'is_reusable' => true]
                ]
            ],
            'messaging_type' => 'MESSAGE_TAG',
            'tag' => 'POST_PURCHASE_UPDATE'
        ];

        curl_setopt($ch_img, CURLOPT_URL, $url);
        curl_setopt($ch_img, CURLOPT_POST, 1);
        curl_setopt($ch_img, CURLOPT_POSTFIELDS, json_encode($data_img));
        curl_setopt($ch_img, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch_img, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch_img, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch_img, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch_img, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch_img, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);

        curl_multi_add_handle($mh, $ch_img);
        $curl_handles[$queue_id . '_img'] = $ch_img;
    }
}

// Execute
$active = null;
do {
    $mrc = curl_multi_exec($mh, $active);
} while ($mrc == CURLM_CALL_MULTI_PERFORM);

while ($active && $mrc == CURLM_OK) {
    if (curl_multi_select($mh) != -1) {
        do {
            $mrc = curl_multi_exec($mh, $active);
        } while ($mrc == CURLM_CALL_MULTI_PERFORM);
    }
}

// Results Processing
foreach ($curl_handles as $key => $ch) {
    $parts = explode('_', $key);
    $q_id = $parts[0];
    $response = curl_multi_getcontent($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $resArr = json_decode($response, true);

    if ($httpCode == 200 && isset($resArr['recipient_id'])) {
        $queue_results[$q_id]['success'] = true;
    } else {
        $errorMsg = $resArr['error']['message'] ?? ($httpCode === 0 ? 'Network Error' : 'HTP ' . $httpCode);
        $queue_results[$q_id]['errors'][] = $errorMsg;
    }
    curl_multi_remove_handle($mh, $ch);
    curl_close($ch);
}
curl_multi_close($mh);

// Final Disposition & Retry Logic
foreach ($queue_results as $q_id => $res) {
    if ($res['success']) {
        $pdo->prepare("UPDATE campaign_queue SET status = 'sent', sent_at = NOW(), attempts_count = attempts_count + 1 WHERE id = ?")->execute([$q_id]);
        $pdo->exec("UPDATE campaigns SET sent_count = sent_count + 1 WHERE id = $campaign_id");
        $processed_results[] = ['id' => $q_id, 'status' => 'sent'];
    } else {
        $errorStr = implode(' | ', array_unique($res['errors']));
        $new_attempts = $res['attempts'] + 1;

        if ($new_attempts <= $retry_max) {
            // Schedule Retry
            $next_retry = date('Y-m-d H:i:s', time() + $retry_delay);
            $pdo->prepare("UPDATE campaign_queue SET status = 'pending', attempts_count = ?, next_retry_at = ?, error_message = ? WHERE id = ?")
                ->execute([$new_attempts, $next_retry, "Retry $new_attempts: $errorStr", $q_id]);
            $processed_results[] = ['id' => $q_id, 'status' => 'retrying', 'error' => $errorStr];
        } else {
            // Permanent Failure
            $pdo->prepare("UPDATE campaign_queue SET status = 'failed', attempts_count = ?, error_message = ? WHERE id = ?")
                ->execute([$new_attempts, $errorStr, $q_id]);
            $pdo->exec("UPDATE campaigns SET failed_count = failed_count + 1 WHERE id = $campaign_id");
            $processed_results[] = ['id' => $q_id, 'status' => 'failed', 'error' => $errorStr];
        }
    }
}

echo json_encode([
    'status' => 'batch_processed',
    'processed' => count($processed_results),
    'results' => $processed_results
]);
