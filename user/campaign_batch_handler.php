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

// Verify ownership - try with batch_size first, fallback if column doesn't exist
try {
    $stmt = $pdo->prepare("SELECT status, waiting_interval, batch_size, retry_count, retry_delay FROM campaigns WHERE id = ? AND user_id = ?");
    $stmt->execute([$campaign_id, $user_id]);
    $camp = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // If batch_size column doesn't exist, query without it
    if (strpos($e->getMessage(), 'batch_size') !== false) {
        $stmt = $pdo->prepare("SELECT status, waiting_interval, retry_count, retry_delay FROM campaigns WHERE id = ? AND user_id = ?");
        $stmt->execute([$campaign_id, $user_id]);
        $camp = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($camp) {
            $camp['batch_size'] = 1; // Default value
        }
    } else {
        throw $e;
    }
}

if (!$camp || $camp['status'] !== 'running') {
    echo json_encode(['status' => 'stopped', 'message' => 'Campaign not running']);
    exit;
}

// FIX: Reset stuck 'processing' items that crashed > 15 mins ago
// We use reserved_at since updated_at does not exist
$pdo->exec("UPDATE campaign_queue SET status = 'pending' WHERE status = 'processing' AND (reserved_at < (NOW() - INTERVAL 15 MINUTE) OR reserved_at IS NULL) AND campaign_id = $campaign_id");

$interval = (int) ($camp['waiting_interval'] ?? 30);
$batch_size = (int) ($camp['batch_size'] ?? 1);

// Safety Cap
if ($batch_size > 50)
    $batch_size = 50;
if ($batch_size < 1)
    $batch_size = 1;

$fb = new FacebookAPI();
$processed_results = [];

// Fetch Pending Items
$qStmt = $pdo->prepare("
    SELECT q.id as q_id, c.message_text, c.image_url, 
           l.fb_user_id, l.fb_user_name, p.page_access_token, p.page_id as fb_page_id 
    FROM campaign_queue q
    JOIN campaigns c ON q.campaign_id = c.id
    JOIN fb_leads l ON q.lead_id = l.id
    JOIN fb_pages p ON c.page_id = p.id
    WHERE q.campaign_id = ? AND q.status = 'pending'
    ORDER BY q.id ASC
    LIMIT $batch_size
");
$qStmt->execute([$campaign_id]);
$items = $qStmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($items)) {
    // Check completion
    $pendingCount = $pdo->query("SELECT COUNT(*) FROM campaign_queue WHERE campaign_id = $campaign_id AND status IN ('pending', 'processing')")->fetchColumn();
    if ($pendingCount == 0) {
        $pdo->prepare("UPDATE campaigns SET status = 'completed' WHERE id = ?")->execute([$campaign_id]);
        echo json_encode(['status' => 'completed', 'message' => 'All messages sent']);
    } else {
        echo json_encode(['status' => 'no_pending', 'message' => 'No pending items in this batch']);
    }
    exit;
}

// Process batch using curl_multi for parallel execution (Non-blocking optimization)
// This is critical for Localhost performance to prevent UI freezing
// Process batch using curl_multi for parallel execution (Non-blocking optimization)
$mh = curl_multi_init();
$curl_handles = [];
$queue_results = []; // To track aggregated status per queue_id

foreach ($items as $item) {
    $queue_id = $item['q_id'];
    $message = str_replace('{{name}}', $item['fb_user_name'] ?? 'User', $item['message_text']);

    // Mark as processing
    $pdo->prepare("UPDATE campaign_queue SET status = 'processing' WHERE id = ?")->execute([$queue_id]);

    // Initialize result tracking
    $queue_results[$queue_id] = ['success' => false, 'errors' => []];

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
        curl_setopt($ch_text, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch_text, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch_text, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch_text, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4); // Force IPv4

        curl_multi_add_handle($mh, $ch_text);
        $curl_handles[$queue_id . '_txt'] = $ch_text;
    }

    // 2. Prepare IMAGE Request (if exists)
    if (!empty($item['image_url'])) {
        $imgUrl = $item['image_url'];

        // PRODUCTION FIX: Ensure URL is absolute (Full URL)
        // Facebook requires a public URL (http://...) to download the image.
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
                    'payload' => [
                        'url' => $imgUrl,
                        'is_reusable' => true
                    ]
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
        curl_setopt($ch_img, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch_img, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch_img, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch_img, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4); // Force IPv4

        curl_multi_add_handle($mh, $ch_img);
        $curl_handles[$queue_id . '_img'] = $ch_img;
    }
}

// Execute concurrently
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

// Process Results
foreach ($curl_handles as $key => $ch) {
    // Extract Queue ID from key (e.g. "123_txt" -> "123")
    $parts = explode('_', $key);
    $q_id = $parts[0];

    $response = curl_multi_getcontent($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $resArr = json_decode($response, true);

    if ($httpCode == 200 && isset($resArr['recipient_id'])) {
        $queue_results[$q_id]['success'] = true;
    } else {
        // Detailed Error Capturing for Debugging
        if ($httpCode === 0) {
            $curlError = curl_error($ch);
            $errorMsg = 'Network Error: ' . ($curlError ?: 'Connection Failed');
        } else {
            $errorMsg = $resArr['error']['message'] ?? 'HTTP Error ' . $httpCode;
        }
        $queue_results[$q_id]['errors'][] = $errorMsg;
    }

    curl_multi_remove_handle($mh, $ch);
    curl_close($ch);
}

curl_multi_close($mh);

// Final DB Updates based on Aggregated Results
foreach ($queue_results as $q_id => $res) {
    if ($res['success']) {
        // At least one part succeeded
        $pdo->prepare("UPDATE campaign_queue SET status = 'sent', sent_at = NOW() WHERE id = ?")->execute([$q_id]);
        $pdo->exec("UPDATE campaigns SET sent_count = sent_count + 1 WHERE id = $campaign_id");
        $processed_results[] = ['id' => $q_id, 'status' => 'sent'];
    } else {
        // All failed
        $errorStr = implode(' | ', array_unique($res['errors']));
        $pdo->prepare("UPDATE campaign_queue SET status = 'failed', error_message = ? WHERE id = ?")->execute([substr($errorStr, 0, 255), $q_id]);
        $pdo->exec("UPDATE campaigns SET failed_count = failed_count + 1 WHERE id = $campaign_id");
        $processed_results[] = ['id' => $q_id, 'status' => 'failed', 'error' => $errorStr];
    }
}

echo json_encode([
    'status' => 'batch_processed',
    'processed' => count($processed_results),
    'results' => $processed_results
]);
